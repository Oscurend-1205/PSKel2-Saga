<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SagaLog;
use App\Models\Shipment;
use App\Models\StockReservation;
use Illuminate\Database\Seeder;

class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        if ($products->count() < 2) {
            return;
        }

        $p1 = $products[1]; // Mechanical Keyboard
        $p2 = $products[4]; // Smartwatch

        // Sample 1: Completed Order (Happy Path Demo)
        $orderCode1 = 'ORD-DEMO-HAPPY-01';
        $order1 = Order::create([
            'order_code' => $orderCode1,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'quantity' => 1,
            'price' => $p1->price,
            'total_amount' => $p1->price,
            'customer_name' => 'Melany Kresna Putri',
            'customer_email' => 'melany@example.com',
            'shipping_address' => 'Jl. Telekomunikasi No. 1, Bandung Tech Park',
            'status' => 'COMPLETED',
        ]);

        OrderItem::create([
            'order_code' => $orderCode1,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'quantity' => 1,
            'price' => $p1->price,
            'subtotal' => $p1->price,
        ]);

        Payment::create([
            'order_code' => $orderCode1,
            'transaction_id' => 'MIDTRANS-TRX-HAPPY-01',
            'payment_type' => 'qris_gopay',
            'amount' => $p1->price,
            'status' => 'PAID',
            'snap_token' => 'SNAP-DEMO-01',
        ]);

        StockReservation::create([
            'order_code' => $orderCode1,
            'product_id' => $p1->id,
            'quantity' => 1,
            'status' => 'RESERVED',
        ]);
        $p1->decrement('stock', 1);

        Shipment::create([
            'order_code' => $orderCode1,
            'tracking_number' => 'SG-EXP-HAPPY01',
            'courier_name' => 'SAGA-EXPRESS Instant',
            'status' => 'SCHEDULED',
            'destination_address' => $order1->shipping_address,
        ]);

        OutboxMessage::create([
            'aggregate_type' => 'Order',
            'aggregate_id' => $orderCode1,
            'event_type' => 'OrderCreated',
            'payload' => ['order_code' => $orderCode1, 'total_amount' => $order1->total_amount],
            'status' => 'PROCESSED',
            'processed_at' => now()->subMinutes(5),
        ]);

        OutboxMessage::create([
            'aggregate_type' => 'Payment',
            'aggregate_id' => $orderCode1,
            'event_type' => 'PaymentCompleted',
            'payload' => ['order_code' => $orderCode1, 'amount' => $order1->total_amount],
            'status' => 'PROCESSED',
            'processed_at' => now()->subMinutes(4),
        ]);

        OutboxMessage::create([
            'aggregate_type' => 'Inventory',
            'aggregate_id' => $orderCode1,
            'event_type' => 'StockReserved',
            'payload' => ['order_code' => $orderCode1, 'quantity' => 1],
            'status' => 'PROCESSED',
            'processed_at' => now()->subMinutes(3),
        ]);

        OutboxMessage::create([
            'aggregate_type' => 'Shipping',
            'aggregate_id' => $orderCode1,
            'event_type' => 'ShipmentScheduled',
            'payload' => ['order_code' => $orderCode1, 'tracking_number' => 'SG-EXP-HAPPY01'],
            'status' => 'PROCESSED',
            'processed_at' => now()->subMinutes(2),
        ]);

        SagaLog::create([
            'order_code' => $orderCode1,
            'saga_step' => 'OrderService::createOrder',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Pesanan berhasil dibuat (PENDING)'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode1,
            'saga_step' => 'PaymentService::markAsPaid',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Pembayaran lunas terkonfirmasi Midtrans Webhook (PAID)'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode1,
            'saga_step' => 'InventoryService::reserveStockForOrder',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Stok 1 unit Mechanical Keyboard berhasil direservasi'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode1,
            'saga_step' => 'ShippingService::scheduleShipment',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Resi pengiriman terbit SG-EXP-HAPPY01'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode1,
            'saga_step' => 'OrderService::completeOrder',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Saga selesai! Order berstatus COMPLETED'],
        ]);

        // Sample 2: Compensated Order (Demonstrating Rollback)
        $orderCode2 = 'ORD-DEMO-ROLLBACK-02';
        $order2 = Order::create([
            'order_code' => $orderCode2,
            'product_id' => $p2->id,
            'product_name' => $p2->name,
            'quantity' => 1,
            'price' => $p2->price,
            'total_amount' => $p2->price,
            'customer_name' => 'Fichri Aditya Nugraha',
            'customer_email' => 'fichri@example.com',
            'shipping_address' => 'Jl. Dipati Ukur No. 20, Bandung',
            'status' => 'CANCELED',
            'fail_reason' => 'Gagal pada tahap [Shipping]: Simulasi Gagal: API Layanan Pengiriman (Kurir) tidak merespons atau area tidak terjangkau.',
        ]);

        OrderItem::create([
            'order_code' => $orderCode2,
            'product_id' => $p2->id,
            'product_name' => $p2->name,
            'quantity' => 1,
            'price' => $p2->price,
            'subtotal' => $p2->price,
        ]);

        Payment::create([
            'order_code' => $orderCode2,
            'transaction_id' => 'MIDTRANS-TRX-FAIL-02',
            'payment_type' => 'credit_card',
            'amount' => $p2->price,
            'status' => 'REFUNDED',
            'snap_token' => 'SNAP-DEMO-02',
        ]);

        StockReservation::create([
            'order_code' => $orderCode2,
            'product_id' => $p2->id,
            'quantity' => 1,
            'status' => 'RELEASED',
        ]);

        OutboxMessage::create([
            'aggregate_type' => 'Payment',
            'aggregate_id' => $orderCode2,
            'event_type' => 'PaymentCompleted',
            'payload' => ['order_code' => $orderCode2, 'simulate_shipping_fail' => true],
            'status' => 'PROCESSED',
            'processed_at' => now()->subMinutes(1),
        ]);

        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'OrderService::createOrder',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Pesanan dibuat (PENDING)'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'PaymentService::markAsPaid',
            'action_type' => 'FORWARD',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Pembayaran dikonfirmasi (PAID)'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'ShippingService::scheduleShipment',
            'action_type' => 'FORWARD',
            'status' => 'FAILED',
            'details' => ['error' => 'Simulasi: API kurir gagal dihubungi!'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'CheckoutSagaOrchestrator::runCompensatingTransactions',
            'action_type' => 'COMPENSATING',
            'status' => 'FAILED',
            'details' => ['failed_at_step' => 'Shipping', 'message' => 'Memulai rangkaian Transaksi Kompensasi Mundur...'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'InventoryService::releaseStock',
            'action_type' => 'COMPENSATING',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Kompensasi: 1 unit Smartwatch dikembalikan ke etalase'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'PaymentService::refundPayment',
            'action_type' => 'COMPENSATING',
            'status' => 'SUCCESS',
            'details' => ['message' => 'Kompensasi: Dana dikembalikan ke pelanggan (status: REFUNDED)'],
        ]);
        SagaLog::create([
            'order_code' => $orderCode2,
            'saga_step' => 'OrderService::cancelOrder',
            'action_type' => 'COMPENSATING',
            'status' => 'SUCCESS',
            'details' => ['reason' => 'Gagal pada tahap [Shipping]', 'message' => 'Status pesanan diubah menjadi CANCELED'],
        ]);
    }
}

