<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockReservation;
use App\Models\OutboxMessage;
use App\Models\SagaLog;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Reserve stock for single item (backward compatibility wrapper)
     */
    public function reserveStock(string $orderCode, int $productId, int $quantity, bool $simulateFail = false): StockReservation
    {
        $reservations = $this->reserveStockForOrder($orderCode, [[
            'product_id' => $productId,
            'quantity' => $quantity,
        ]], $simulateFail);

        return $reservations[0];
    }

    /**
     * Reserve stock for all items in an order.
     * Throws Exception if any item lacks stock or simulation failure is active.
     */
    public function reserveStockForOrder(string $orderCode, array $items, bool $simulateFail = false): array
    {
        return DB::transaction(function () use ($orderCode, $items, $simulateFail) {
            // Check failure simulation flag
            if ($simulateFail) {
                SagaLog::create([
                    'order_code' => $orderCode,
                    'saga_step' => 'InventoryService::reserveStockForOrder',
                    'action_type' => 'FORWARD',
                    'status' => 'FAILED',
                    'details' => ['error' => 'Simulasi Kegagalan: Sistem gudang terkunci / stok fisik tidak sinkron!'],
                ]);
                throw new Exception("Simulasi Gagal: Sistem inventaris mengalami error / kehabisan stok fisik.");
            }

            $reservations = [];

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                $product = Product::lockForUpdate()->findOrFail($productId);

                if ($product->stock < $quantity) {
                    SagaLog::create([
                        'order_code' => $orderCode,
                        'saga_step' => 'InventoryService::reserveStockForOrder',
                        'action_type' => 'FORWARD',
                        'status' => 'FAILED',
                        'details' => ['error' => "Stok produk {$product->name} tidak mencukupi (sisa: {$product->stock}, butuh: {$quantity})"],
                    ]);
                    throw new Exception("Stok produk {$product->name} tidak mencukupi!");
                }

                // Deduct stock
                $product->decrement('stock', $quantity);

                // Record reservation
                $reservation = StockReservation::create([
                    'order_code' => $orderCode,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'status' => 'RESERVED',
                ]);

                $reservations[] = $reservation;
            }

            // Transactional Outbox: Write StockReserved event
            OutboxMessage::create([
                'aggregate_type' => 'Inventory',
                'aggregate_id' => $orderCode,
                'event_type' => 'StockReserved',
                'payload' => [
                    'order_code' => $orderCode,
                    'reserved_count' => count($reservations),
                    'items' => $items,
                ],
                'status' => 'PENDING',
            ]);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'InventoryService::reserveStockForOrder',
                'action_type' => 'FORWARD',
                'status' => 'SUCCESS',
                'details' => [
                    'message' => 'Stok untuk ' . count($items) . ' produk berhasil dikurangi & direservasi.',
                ],
            ]);

            return $reservations;
        });
    }

    /**
     * Compensating step: Release all reserved stock back to inventory.
     */
    public function releaseStock(string $orderCode): void
    {
        DB::transaction(function () use ($orderCode) {
            $reservations = StockReservation::where('order_code', $orderCode)
                ->where('status', 'RESERVED')
                ->get();

            $releasedCount = 0;
            foreach ($reservations as $reservation) {
                $product = Product::find($reservation->product_id);
                if ($product) {
                    $product->increment('stock', $reservation->quantity);
                }

                $reservation->update(['status' => 'RELEASED']);
                $releasedCount += $reservation->quantity;
            }

            if ($releasedCount > 0) {
                SagaLog::create([
                    'order_code' => $orderCode,
                    'saga_step' => 'InventoryService::releaseStock',
                    'action_type' => 'COMPENSATING',
                    'status' => 'SUCCESS',
                    'details' => ['message' => "Kompensasi: Total {$releasedCount} unit produk dikembalikan ke etalase toko."],
                ]);
            }
        });
    }
}
