<?php

namespace App\SagaBridge;

use App\Models\OutboxMessage;
use App\Services\Outbox\OutboxProcessor;

/**
 * Turunan OutboxProcessor milik aplikasi. Saat presentasi menahan relay,
 * processNext() (dan otomatis processAll()) tidak memproses apa pun, sehingga
 * event menumpuk di tabel outbox persis seperti relay yang sedang mati.
 * Presentasi memprosesnya satu per satu lewat processNextForced().
 */
class PausableOutboxProcessor extends OutboxProcessor
{
    public function processNext(): ?OutboxMessage
    {
        if (! app()->runningUnitTests() && app(BridgeState::class)->relayHeld()) {
            return null;
        }

        return parent::processNext();
    }

    public function processNextForced(): ?OutboxMessage
    {
        return parent::processNext();
    }
}
