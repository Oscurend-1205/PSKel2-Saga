<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\OutboxMessage;
use App\Models\SagaLog;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Create initial payment record for checkout
     */
    public function createPaymentRecord(string $orderCode, float $amount, ?string $snapToken = null): Payment
    {
        return Payment::create([
            'order_code' => $orderCode,
            'amount' => $amount,
            'status' => 'PENDING',
            'snap_token' => $snapToken,
        ]);
    }

    /**
     * Mark payment as PAID (invoked via Midtrans Webhook or Sandbox callback)
     * Writes PaymentCompleted event to Outbox in local DB transaction.
     */
    public function markAsPaid(string $orderCode, array $payload = []): Payment
    {
        return DB::transaction(function () use ($orderCode, $payload) {
            $payment = Payment::firstOrCreate(
                ['order_code' => $orderCode],
                ['amount' => $payload['gross_amount'] ?? 0]
            );

            $payment->update([
                'transaction_id' => $payload['transaction_id'] ?? ('TRX-' . uniqid()),
                'payment_type' => $payload['payment_type'] ?? 'midtrans_snap',
                'status' => 'PAID',
                'payment_payload' => $payload,
            ]);

            // Transactional Outbox: Write PaymentCompleted
            OutboxMessage::create([
                'aggregate_type' => 'Payment',
                'aggregate_id' => $orderCode,
                'event_type' => 'PaymentCompleted',
                'payload' => array_merge($payload, [
                    'order_code' => $orderCode,
                    'amount' => $payment->amount,
                    'simulate_inventory_fail' => $payload['simulate_inventory_fail'] ?? false,
                    'simulate_shipping_fail' => $payload['simulate_shipping_fail'] ?? false,
                ]),
                'status' => 'PENDING',
            ]);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'PaymentService::markAsPaid',
                'action_type' => 'FORWARD',
                'status' => 'SUCCESS',
                'details' => ['message' => 'Pembayaran berhasil dikonfirmasi (PAID) via Midtrans Webhook/Callback'],
            ]);

            return $payment;
        });
    }

    /**
     * Compensating step: Refund payment if inventory or shipping fails.
     */
    public function refundPayment(string $orderCode): void
    {
        $payment = Payment::where('order_code', $orderCode)->first();
        if ($payment) {
            $payment->update(['status' => 'REFUNDED']);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'PaymentService::refundPayment',
                'action_type' => 'COMPENSATING',
                'status' => 'SUCCESS',
                'details' => ['message' => 'Kompensasi: Dana dikembalikan ke pelanggan (status: REFUNDED)'],
            ]);
        }
    }
}

