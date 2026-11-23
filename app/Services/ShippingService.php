<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\OutboxMessage;
use App\Models\SagaLog;
use Illuminate\Support\Facades\DB;
use Exception;

class ShippingService
{
    /**
     * Schedule shipment and generate tracking number.
     * Throws Exception if simulation flag is on.
     */
    public function scheduleShipment(string $orderCode, string $destinationAddress, bool $simulateFail = false): Shipment
    {
        return DB::transaction(function () use ($orderCode, $destinationAddress, $simulateFail) {
            if ($simulateFail) {
                SagaLog::create([
                    'order_code' => $orderCode,
                    'saga_step' => 'ShippingService::scheduleShipment',
                    'action_type' => 'FORWARD',
                    'status' => 'FAILED',
                    'details' => ['error' => 'Simulasi Kegagalan: Kurir partner menolak pick-up / API Logistic gagal dihubungi!'],
                ]);
                throw new Exception("Simulasi Gagal: API Layanan Pengiriman (Kurir) tidak merespons atau area tidak terjangkau.");
            }

            $shipment = Shipment::create([
                'order_code' => $orderCode,
                'tracking_number' => 'SG-EXP-' . strtoupper(uniqid()),
                'courier_name' => 'SAGA-EXPRESS Instant',
                'status' => 'SCHEDULED',
                'destination_address' => $destinationAddress,
            ]);

            // Transactional Outbox: Write ShipmentScheduled
            OutboxMessage::create([
                'aggregate_type' => 'Shipping',
                'aggregate_id' => $orderCode,
                'event_type' => 'ShipmentScheduled',
                'payload' => [
                    'order_code' => $orderCode,
                    'tracking_number' => $shipment->tracking_number,
                    'courier_name' => $shipment->courier_name,
                ],
                'status' => 'PENDING',
            ]);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'ShippingService::scheduleShipment',
                'action_type' => 'FORWARD',
                'status' => 'SUCCESS',
                'details' => ['message' => "Pengiriman dijadwalkan. No Resi: {$shipment->tracking_number}"],
            ]);

            return $shipment;
        });
    }

    /**
     * Compensating step: Cancel scheduled shipment.
     */
    public function cancelShipment(string $orderCode): void
    {
        $shipment = Shipment::where('order_code', $orderCode)
            ->where('status', 'SCHEDULED')
            ->first();

        if ($shipment) {
            $shipment->update(['status' => 'CANCELED']);

            SagaLog::create([
                'order_code' => $orderCode,
                'saga_step' => 'ShippingService::cancelShipment',
                'action_type' => 'COMPENSATING',
                'status' => 'SUCCESS',
                'details' => ['message' => "Kompensasi: Jadwal pengiriman no resi {$shipment->tracking_number} dibatalkan."],
            ]);
        }
    }
}

