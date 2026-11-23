<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SagaLog;
use App\Models\Shipment;
use App\Models\StockReservation;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\Outbox\OutboxProcessor;
use App\Services\PaymentService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SagaDistributedTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::create([
            'name' => 'Demo Phone',
            'price' => 5000000,
            'stock' => 10,
        ]);
        Product::create([
            'name' => 'Demo Earbuds',
            'price' => 500000,
            'stock' => 5,
        ]);
    }

    public function test_happy_path_completes_order_via_saga_and_outbox(): void
    {
        $orderService = app(OrderService::class);
        $paymentService = app(PaymentService::class);
        $outboxProcessor = app(OutboxProcessor::class);

        $product = Product::first();
        $orderCode = 'ORD-HAPPY-01';

        $order = $orderService->createOrder([
            'order_code' => $orderCode,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => $product->price,
            'total_amount' => $product->price * 2,
            'customer_name' => 'Budi',
            'customer_email' => 'budi@test.com',
            'shipping_address' => 'Jakarta Selatan',
        ]);

        $this->assertEquals('PENDING', $order->status);

        $paymentService->createPaymentRecord($orderCode, $order->total_amount);
        $paymentService->markAsPaid($orderCode, ['gross_amount' => $order->total_amount]);

        $outboxProcessor->processAll();

        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);

        $payment = Payment::where('order_code', $orderCode)->first();
        $this->assertEquals('PAID', $payment->status);

        $reservation = StockReservation::where('order_code', $orderCode)->first();
        $this->assertEquals('RESERVED', $reservation->status);
        $this->assertEquals(2, $reservation->quantity);

        $product->refresh();
        $this->assertEquals(8, $product->stock); // 10 - 2

        $shipment = Shipment::where('order_code', $orderCode)->first();
        $this->assertEquals('SCHEDULED', $shipment->status);
        $this->assertNotNull($shipment->tracking_number);
    }

    public function test_compensating_transaction_when_inventory_fails(): void
    {
        $orderService = app(OrderService::class);
        $paymentService = app(PaymentService::class);
        $outboxProcessor = app(OutboxProcessor::class);

        $product = Product::first();
        $orderCode = 'ORD-FAIL-INV-01';

        $order = $orderService->createOrder([
            'order_code' => $orderCode,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => $product->price,
            'total_amount' => $product->price,
            'customer_name' => 'Budi',
            'customer_email' => 'budi@test.com',
            'shipping_address' => 'Jakarta',
            'simulate_inventory_fail' => true,
        ]);

        $paymentService->createPaymentRecord($orderCode, $order->total_amount);
        $paymentService->markAsPaid($orderCode, [
            'gross_amount' => $order->total_amount,
            'simulate_inventory_fail' => true,
        ]);

        $outboxProcessor->processAll();

        $order->refresh();
        $this->assertEquals('CANCELED', $order->status);
        $this->assertStringContainsString('Inventory', $order->fail_reason);

        $payment = Payment::where('order_code', $orderCode)->first();
        $this->assertEquals('REFUNDED', $payment->status);

        $product->refresh();
        $this->assertEquals(10, $product->stock); // Stock intact

        $shipment = Shipment::where('order_code', $orderCode)->first();
        $this->assertNull($shipment);
    }

    public function test_compensating_transaction_when_shipping_fails(): void
    {
        $orderService = app(OrderService::class);
        $paymentService = app(PaymentService::class);
        $outboxProcessor = app(OutboxProcessor::class);

        $product = Product::first();
        $orderCode = 'ORD-FAIL-SHP-01';

        $order = $orderService->createOrder([
            'order_code' => $orderCode,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => $product->price,
            'total_amount' => $product->price * 2,
            'customer_name' => 'Budi',
            'customer_email' => 'budi@test.com',
            'shipping_address' => 'Bandung',
            'simulate_shipping_fail' => true,
        ]);

        $paymentService->createPaymentRecord($orderCode, $order->total_amount);
        $paymentService->markAsPaid($orderCode, [
            'gross_amount' => $order->total_amount,
            'simulate_shipping_fail' => true,
        ]);

        $outboxProcessor->processAll();

        $order->refresh();
        $this->assertEquals('CANCELED', $order->status);
        $this->assertStringContainsString('Shipping', $order->fail_reason);

        $payment = Payment::where('order_code', $orderCode)->first();
        $this->assertEquals('REFUNDED', $payment->status);

        $reservation = StockReservation::where('order_code', $orderCode)->first();
        $this->assertEquals('RELEASED', $reservation->status);

        $product->refresh();
        $this->assertEquals(10, $product->stock);

        $compensatingLogs = SagaLog::where('order_code', $orderCode)
            ->where('action_type', 'COMPENSATING')
            ->count();
        $this->assertGreaterThan(0, $compensatingLogs);
    }

    public function test_shopee_cart_add_and_multi_item_checkout(): void
    {
        $products = Product::all();
        $prod1 = $products[0];
        $prod2 = $products[1];

        // 1. Prepare Cart with 2 different items
        $cart = [
            $prod1->id => [
                'product_id' => $prod1->id,
                'name' => $prod1->name,
                'price' => (float) $prod1->price,
                'image_url' => 'https://via.placeholder.com/150',
                'quantity' => 1,
                'stock' => $prod1->stock,
            ],
            $prod2->id => [
                'product_id' => $prod2->id,
                'name' => $prod2->name,
                'price' => (float) $prod2->price,
                'image_url' => 'https://via.placeholder.com/150',
                'quantity' => 2,
                'stock' => $prod2->stock,
            ],
        ];

        // 2. Checkout from cart
        $response = $this->withSession(['cart' => $cart])->post(route('checkout.store'), [
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@shopee.com',
            'shipping_address' => 'Jakarta Selatan',
            'simulate_inventory_fail' => false,
            'simulate_shipping_fail' => false,
            'auto_process_outbox' => true,
        ]);

        $response->assertStatus(302);

        $order = Order::with('items')->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(2, $order->items->count());
        $this->assertEquals(6000000, (int) $order->total_amount); // (5000000*1) + (500000*2)

        // 3. Simulate payment success
        $this->post(route('checkout.simulate_pay', $order->order_code), [
            'simulate_inventory_fail' => false,
            'simulate_shipping_fail' => false,
            'auto_process_outbox' => true,
        ]);

        // 4. Assert order completed and stock reserved for BOTH items
        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);

        $reservations = StockReservation::where('order_code', $order->order_code)->get();
        $this->assertEquals(2, $reservations->count());

        $prod1->refresh();
        $prod2->refresh();
        $this->assertEquals(9, $prod1->stock); // 10 - 1
        $this->assertEquals(3, $prod2->stock); // 5 - 2
    }
}
