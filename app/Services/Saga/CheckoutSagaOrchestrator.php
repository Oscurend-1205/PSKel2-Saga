<?php

namespace App\Services\Saga;

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\SagaLog;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\InventoryService;
use App\Services\ShippingService;
use Exception;
use Illuminate\Support\Facades\Log;

class CheckoutSagaOrchestrator
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected InventoryService $inventoryService,
        protected ShippingService $shippingService,
    ) {}

    /**
     * Handle incoming Outbox event and direct Saga workflow.
     */
    public function handleEvent(OutboxMessage $message): void
    {
        $payload = $message->payload;
        $orderCode = $payload['order_code'] ?? $message->aggregate_id;
        $order = Order::with('items')->where('order_code', $orderCode)->first();

        if (!$order) {
            Log::warning("Saga Orchestrator: Order {$orderCode} not found for event {$message->event_type}");
            return;
        }

        switch ($message->event_type) {
            case 'PaymentCompleted':
                $this->executeInventoryStep($order, $payload);
                break;

            case 'StockReserved':
                $this->executeShippingStep($order, $payload);
                break;

            case 'ShipmentScheduled':
                $this->executeCompletionStep($order, $payload);
                break;

            case 'OrderCreated':
                Log::info("Saga Orchestrator: OrderCreated acknowledged for {$orderCode}. Awaiting Payment.");
                break;

            default:
                Log::info("Saga Orchestrator: Unhandled event type {$message->event_type}");
                break;
        }
    }

    /**
     * Step 1 after payment: Reserve stock for all items
     */
    protected function executeInventoryStep(Order $order, array $payload): void
    {
        $simulateInventoryFail = (bool) ($payload['simulate_inventory_fail'] ?? false);

        // Build items list
        $itemsList = [];
        if ($order->items && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $itemsList[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product_name' => $item->product_name,
                ];
            }
        } elseif (!empty($payload['items'])) {
            $itemsList = $payload['items'];
        } elseif ($order->product_id) {
            $itemsList = [[
                'product_id' => $order->product_id,
                'quantity' => $order->quantity ?? 1,
            ]];
        }

        try {
            $this->inventoryService->reserveStockForOrder(
                orderCode: $order->order_code,
                items: $itemsList,
                simulateFail: $simulateInventoryFail
            );
        } catch (Exception $e) {
            Log::error("Saga Failed at Inventory Step: " . $e->getMessage());
            $this->runCompensatingTransactions(
                order: $order,
                failedAtStep: 'Inventory',
                reason: $e->getMessage()
            );
        }
    }

    /**
     * Step 2 after stock reserved: Schedule shipping
     */
    protected function executeShippingStep(Order $order, array $payload): void
    {
        $lastPaymentOutbox = OutboxMessage::where('aggregate_id', $order->order_code)
            ->where('event_type', 'PaymentCompleted')
            ->latest()
            ->first();

        $simulateShippingFail = false;
        if ($lastPaymentOutbox && isset($lastPaymentOutbox->payload['simulate_shipping_fail'])) {
            $simulateShippingFail = (bool) $lastPaymentOutbox->payload['simulate_shipping_fail'];
        }

        try {
            $this->shippingService->scheduleShipment(
                orderCode: $order->order_code,
                destinationAddress: $order->shipping_address,
                simulateFail: $simulateShippingFail
            );
        } catch (Exception $e) {
            Log::error("Saga Failed at Shipping Step: " . $e->getMessage());
            $this->runCompensatingTransactions(
                order: $order,
                failedAtStep: 'Shipping',
                reason: $e->getMessage()
            );
        }
    }

    /**
     * Step 3: Complete the Order
     */
    protected function executeCompletionStep(Order $order, array $payload): void
    {
        $this->orderService->completeOrder($order->order_code);
    }

    /**
     * Compensating Transaction: Run backward rollback sequence
     * 1. Cancel Shipment (if scheduled)
     * 2. Release Stock back to inventory (all reserved items in order)
     * 3. Refund Payment
     * 4. Cancel Order with reason
     */
    public function runCompensatingTransactions(Order $order, string $failedAtStep, string $reason): void
    {
        SagaLog::create([
            'order_code' => $order->order_code,
            'saga_step' => 'CheckoutSagaOrchestrator::runCompensatingTransactions',
            'action_type' => 'COMPENSATING',
            'status' => 'FAILED',
            'details' => [
                'failed_at_step' => $failedAtStep,
                'reason' => $reason,
                'message' => 'Memulai rangkaian Transaksi Kompensasi (Rollback Mundur)...',
            ],
        ]);

        // Step 1 Rollback: Cancel Shipping (if failed at or after shipping)
        if ($failedAtStep === 'Shipping') {
            $this->shippingService->cancelShipment($order->order_code);
        }

        // Step 2 Rollback: Release all stock back if it was already reserved
        $this->inventoryService->releaseStock($order->order_code);

        // Step 3 Rollback: Refund payment
        $this->paymentService->refundPayment($order->order_code);

        // Step 4 Rollback: Cancel order with explicit failure reason
        $this->orderService->cancelOrder($order->order_code, "Gagal pada tahap [{$failedAtStep}]: {$reason}");
    }
}
