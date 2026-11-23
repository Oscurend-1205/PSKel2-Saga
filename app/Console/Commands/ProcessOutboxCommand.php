<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Outbox\OutboxProcessor;

class ProcessOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:process {--all : Process all pending messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending messages from outbox_messages table';

    /**
     * Execute the console command.
     */
    public function handle(OutboxProcessor $processor): int
    {
        $this->info('Memulai pemrosesan pesan Outbox...');

        if ($this->option('all')) {
            $count = $processor->processAll();
            $this->info("Berhasil memproses {$count} pesan Outbox.");
        } else {
            $message = $processor->processNext();
            if ($message) {
                $this->info("Berhasil memproses 1 pesan Outbox ID: {$message->id} [{$message->event_type}]");
            } else {
                $this->comment('Tidak ada pesan Outbox PENDING yang perlu diproses.');
            }
        }

        return Command::SUCCESS;
    }
}

