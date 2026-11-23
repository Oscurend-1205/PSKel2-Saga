<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SagaLog;
use App\Models\Shipment;
use App\Models\StockReservation;
use App\Services\InventoryService;
use App\Services\MidtransService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Outbox\OutboxProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected MidtransService $midtransService,
        protected OutboxProcessor $outboxProcessor
    ) {}

    /**
     * Show Catalog / Home Page
     */
    public function catalog(Request $request)
    {
        $products = Product::all();
        return view('catalog.index', compact('products'));
    }

    /**
     * Show Checkout Form for cart items (Shopee Style)
     */
    public function index(Request $request)
    {
        $cart = session()->get('cart', []);

        // If cart is empty, allow picking from catalog
        if (empty($cart)) {
            $defaultProduct = Product::first();
            if ($defaultProduct) {
                $cart[$defaultProduct->id] = [
                    'product_id' => $defaultProduct->id,
                    'name' => $defaultProduct->name,
                    'price' => (float) $defaultProduct->price,
                    'image_url' => $defaultProduct->image_url,
                    'quantity' => 1,
                    'stock' => $defaultProduct->stock,
                ];
                session()->put('cart', $cart);
            }
        }

        $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }

        return view('checkout.index', compact('cart', 'totalAmount'));
    }

    /**
     * Handle Checkout submission from Cart
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_email' => 'required|email|max:100',
            'shipping_address' => 'required|string|max:255',
            'simulate_inventory_fail' => 'nullable|boolean',
            'simulate_shipping_fail' => 'nullable|boolean',
            'auto_process_outbox' => 'nullable|boolean',
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('catalog.index')->with('error', 'Keranjang belanja Anda masih kosong!');
        }

        $items = [];
        $totalAmount = 0;
        $snapItemDetails = [];

        foreach ($cart as $c) {
            $subtotal = $c['price'] * $c['quantity'];
            $totalAmount += $subtotal;

            $items[] = [
                'product_id' => $c['product_id'],
                'product_name' => $c['name'],
                'price' => $c['price'],
                'quantity' => $c['quantity'],
                'subtotal' => $subtotal,
            ];

            $snapItemDetails[] = [
                'id' => (string) $c['product_id'],
                'price' => (int) $c['price'],
                'quantity' => (int) $c['quantity'],
                'name' => substr($c['name'], 0, 50),
            ];
        }

        $orderCode = 'ORD-' . strtoupper(uniqid());

        // Prepare Snap parameters for Midtrans
        $snapParams = [
            'transaction_details' => [
                'order_id' => $orderCode,
                'gross_amount' => (int) $totalAmount,
            ],
            'customer_details' => [
                'first_name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'shipping_address' => [
                    'address' => $validated['shipping_address'],
                ],
            ],
            'item_details' => $snapItemDetails,
        ];

        // Generate Snap Token
        $snapToken = $this->midtransService->createSnapToken($snapParams);

        // Step 1: Create Order & OrderItems & Outbox within DB Transaction
        $order = $this->orderService->createOrder([
            'order_code' => $orderCode,
            'items' => $items,
            'total_amount' => $totalAmount,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'shipping_address' => $validated['shipping_address'],
            'simulate_inventory_fail' => $request->boolean('simulate_inventory_fail'),
            'simulate_shipping_fail' => $request->boolean('simulate_shipping_fail'),
        ]);

        // Create Payment record with initial PENDING status
        $this->paymentService->createPaymentRecord(
            orderCode: $orderCode,
            amount: $totalAmount,
            snapToken: $snapToken
        );

        // Clear cart after checkout
        session()->forget('cart');

        // If auto_process_outbox requested, process OrderCreated
        if ($request->boolean('auto_process_outbox')) {
            $this->outboxProcessor->processAll();
        }

        return redirect()->route('checkout.pay', [
            'orderCode' => $orderCode,
            'sim_inv' => $request->boolean('simulate_inventory_fail') ? 1 : 0,
            'sim_shp' => $request->boolean('simulate_shipping_fail') ? 1 : 0,
            'auto_proc' => $request->boolean('auto_process_outbox') ? 1 : 0,
        ]);
    }

    /**
     * Show Payment Page with Midtrans Snap + Mock Payment Option
     */
    public function pay(Request $request, string $orderCode)
    {
        $order = Order::with('items')->where('order_code', $orderCode)->firstOrFail();
        $payment = Payment::where('order_code', $orderCode)->firstOrFail();

        $simInv = $request->query('sim_inv', 0);
        $simShp = $request->query('sim_shp', 0);
        $autoProc = $request->query('auto_proc', 1);

        return view('checkout.pay', compact('order', 'payment', 'simInv', 'simShp', 'autoProc'));
    }

    /**
     * Handle Official Midtrans Webhook Callback
     */
    public function midtransWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Midtrans Webhook Received: ', $payload);

        $orderCode = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        if (!$orderCode) {
            return response()->json(['message' => 'Missing order_id'], 400);
        }

        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            if ($fraudStatus === 'challenge') {
                return response()->json(['message' => 'Payment challenged'], 200);
            }

            $this->paymentService->markAsPaid($orderCode, $payload);
            $this->outboxProcessor->processAll();
        }

        return response()->json(['message' => 'Webhook handled successfully'], 200);
    }

    /**
     * Simulate Payment Callback for Testing & Demonstration
     */
    public function simulatePayment(Request $request, string $orderCode)
    {
        $order = Order::where('order_code', $orderCode)->firstOrFail();

        $simInv = $request->boolean('simulate_inventory_fail');
        $simShp = $request->boolean('simulate_shipping_fail');
        $autoProc = $request->boolean('auto_process_outbox', true);

        // Mark payment as PAID
        $this->paymentService->markAsPaid($orderCode, [
            'gross_amount' => $order->total_amount,
            'payment_type' => 'sandbox_simulation',
            'transaction_id' => 'SANDBOX-TRX-' . strtoupper(uniqid()),
            'simulate_inventory_fail' => $simInv,
            'simulate_shipping_fail' => $simShp,
        ]);

        if ($autoProc) {
            $this->outboxProcessor->processAll();
        }

        return redirect()->route('checkout.status', $orderCode)->with('success', 'Pembayaran Sandbox dikonfirmasi!');
    }

    /**
     * Real-time Order Status & Saga Trace View
     */
    public function status(string $orderCode)
    {
        $order = Order::with('items')->where('order_code', $orderCode)->firstOrFail();
        $payment = Payment::where('order_code', $orderCode)->first();
        $reservations = StockReservation::where('order_code', $orderCode)->get();
        $shipment = Shipment::where('order_code', $orderCode)->first();
        $sagaLogs = SagaLog::where('order_code', $orderCode)->orderBy('id', 'asc')->get();

        return view('checkout.status', compact('order', 'payment', 'reservations', 'shipment', 'sagaLogs'));
    }
}
