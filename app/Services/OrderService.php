<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxMessage;
use App\Models\SagaLog;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Create initial Order (supports single item or multi-item cart)
     * and write OrderCreated event to Outbox within a single DB transaction.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];

            // If single item passed directly (backward compatibility)
            if (empty($items) && isset($data['product_id'])) {
                $items = [[
                    'product_id' => $data['product_id'],
                    'product_name' => $data['product_name'] ?? 'Product #' . $data['product_id'],
                    'quantity' => $data['quantity'] ?? 1,
                    'price' => $data['price'] ?? $data['total_amount'],
                    'subtotal' => $data['total_amount'],
                ]];
            }

            $firstItem = $items[0] ?? null;

            $order = Order::create([
                'order_code' => $data['order_code'],
                'product_id' => $firstItem['product_id'] ?? null,
                'product_name' => count($items) > 1 ? ($firstItem['product_name'] . ' (+' . (count($items) - 1) . ' produk lainnya)') : ($firstItem['product_name'] ?? 'Multiple Items'),
                'quantity' => array_sum(array_column($items, 'quantity')),
                'price' => $firstItem['price'] ?? 0,
                'total_amount' => $data['total_amount'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'shipping_address' => $data['shipping_address'],
                'status' => 'PENDING',
            ]);

            // Save order items
            foreach ($items as $item) {
                OrderItem::create([
                    'order_code' => $order->order_code,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'] ?? 'Product',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'] ?? ($item['price'] * $item['quantity']),
                ]);
            }

            // Transactional Outbox: Write OrderCreated
            OutboxMessage::create([
                'aggregate_type' => 'Order',
                'aggregate_id' => $order->order_code,
                'event_type' => 'OrderCreated',
                'payload' => [
                    'order_code' => $order->order_code,
                    'items' => $items,
                    'total_amount' => $order->total_amount,
                    'customer_name' => $order->customer_name,
                    'shipping_address' => $order->shipping_address,
                    'simulate_inventory_fail' => $data['simulate_inventory_fail'] ?? false,
                    'simulate_shipping_fail' => $data['simulate_shipping_fail'] ?? false,
                ],
                'status' => 'PENDING',
            ]);

            SagaLog::create([
                'order_code' => $order->order_code,
                'saga_step' => 'OrderService::createOrder',
                'action_type' => 'FORWARD',
                'status' => 'SUCCESS',
                'details' => [
                    'message' => 'Pesanan berhasil dibuat (' . count($items) . ' produk) dengan status PENDING',
                    'order_code' => $order->order_code
                ],
            ]);

            return $order;
        });
    }

    /**
     * Complete Order after successful saga flow.
     */
    public function completeOrder(string $orderCode): void
    {
        $order = Order::where('order_code', $orderCode)->firstOrFail();
        $order->update(['status' => 'COMPLETED']);

        SagaLog::create([
            'order_code' => $orderCode,
            'saga_step' => 'OrderService::completeOrder',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Saga selesai! Status pesanan diubah ke COMPLETED'],
        ]);
    }

    /**
     * Compensating step: Cancel order due to downstream failure.
     */
    public function cancelOrder(string $orderCode, string $reason): void
    {
        $order = Order::where('order_code', $orderCode)->first();
        if ($order) {
            $order->update([
                'status' => 'CANCELED',
                'fail_reason' => $reason,
            ]);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'OrderService::cancelOrder',
                'action_type' => 'COMPENSATING',
                'status' => 'SUCCESS',
                'details' => ['reason' => $reason, 'message' => 'Kompensasi: Status pesanan diubah menjadi CANCELED'],
            ]);
        }
    }
}
