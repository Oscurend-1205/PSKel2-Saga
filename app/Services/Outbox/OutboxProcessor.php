<?php

namespace App\Services\Outbox;

use App\Models\OutboxMessage;
use App\Services\Saga\CheckoutSagaOrchestrator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutboxProcessor
{
    public function __construct(
        protected CheckoutSagaOrchestrator $orchestrator
    ) {}

    /**
     * Process a single pending outbox message (FIFO)
     */
    public function processNext(): ?OutboxMessage
    {
        $message = OutboxMessage::where('status', 'PENDING')
            ->orderBy('id', 'asc')
            ->first();

        if (!$message) {
            return null;
        }

        $this->processMessage($message);
        return $message;
    }

    /**
     * Process all currently pending outbox messages
     */
    public function processAll(): int
    {
        $count = 0;
        while ($message = $this->processNext()) {
            $count++;
        }
        return $count;
    }

    /**
     * Process single message with error handling and status tracking
     */
    public function processMessage(OutboxMessage $message): void
    {
        try {
            // Mark as PROCESSED first or in transaction to prevent duplicate processing
            $message->update([
                'status' => 'PROCESSED',
                'processed_at' => now(),
            ]);

            // Dispatch to Saga Orchestrator
            $this->orchestrator->handleEvent($message);

        } catch (Exception $e) {
            Log::error("Failed processing outbox message ID {$message->id}: " . $e->getMessage());

            $message->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}

