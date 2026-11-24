<?php

namespace App\SagaBridge\Console;

use App\Models\OutboxMessage;
use App\SagaBridge\BridgeState;
use App\SagaBridge\Support\LanAddress;
use Illuminate\Console\Command;
use Throwable;

class SagaBridgeCommand extends Command
{
    protected $signature = 'saga-bridge
        {action=status : on, off, atau status}
        {--port=8000 : Port yang dipakai "php artisan serve"}
        {--url= : Alamat toko untuk QR audiens, misalnya http://192.168.1.5:8000}
        {--new-key : Buat kunci baru (kunci lama tidak berlaku lagi)}';

    protected $description = 'Aktifkan atau nonaktifkan jembatan presentasi <-> aplikasi Shambat';

    public function handle(BridgeState $state): int
    {
        if (! $state->allowed()) {
            $this->error('Saga Bridge dikunci: SAGA_BRIDGE_ALLOWED=false (atau APP_ENV=production tanpa izin).');
            $this->line('Tambahkan SAGA_BRIDGE_ALLOWED=true di .env lalu jalankan "php artisan config:clear" bila perlu.');

            return self::FAILURE;
        }

        return match (strtolower((string) $this->argument('action'))) {
            'on' => $this->turnOn($state),
            'off' => $this->turnOff($state),
            'status' => $this->showStatus($state),
            default => $this->unknown(),
        };
    }

    private function turnOn(BridgeState $state): int
    {
        $key = $this->option('new-key') || ! $state->key() ? $this->makeKey() : $state->key();
        $url = $this->option('url');

        $state->update([
            'enabled' => true,
            'key' => $key,
            'enabled_at' => now()->toDateTimeString(),
            'audience_url' => is_string($url) && $url !== '' ? rtrim($url, '/') : null,
        ]);
        $state->releaseRelay();

        $this->newLine();
        $this->info('  Saga Bridge AKTIF');
        $this->printLinks($state);
        $this->newLine();
        $this->line('  Agar HP audiens bisa membuka toko, jalankan server dengan:');
        $this->line('  <comment>php artisan serve --host=0.0.0.0 --port='.$this->port().'</comment>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function turnOff(BridgeState $state): int
    {
        $state->update(['enabled' => false]);
        $state->releaseRelay();

        $this->newLine();
        $this->info('  Saga Bridge NONAKTIF');
        $this->line('  Semua endpoint /saga-bridge/api ditutup dan relay outbox kembali normal.');
        $this->line('  Kunci disimpan, jadi "saga-bridge on" berikutnya tetap memakai kunci yang sama.');

        $pending = $this->pendingCount();
        if ($pending > 0) {
            $this->warn("  Masih ada {$pending} event di outbox. Proses dengan: php artisan outbox:process --all");
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function showStatus(BridgeState $state): int
    {
        $this->newLine();
        if (! $state->enabled()) {
            $this->line('  Saga Bridge: <comment>nonaktif</comment>   (aktifkan: php artisan saga-bridge on)');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->info('  Saga Bridge: aktif sejak '.$state->all()['enabled_at']);
        $this->line('  Relay outbox: '.($state->relayHeld() ? 'ditahan oleh presentasi' : 'berjalan normal'));
        $this->line('  Event menunggu: '.$this->pendingCount());
        $this->printLinks($state);
        $this->newLine();

        return self::SUCCESS;
    }

    private function printLinks(BridgeState $state): void
    {
        $port = $this->port();
        $key = (string) $state->key();
        $lan = LanAddress::detect();
        $audience = $state->all()['audience_url'] ?: ($lan ? "http://{$lan}:{$port}" : null);

        $this->newLine();
        $this->line("  Kunci        : <info>{$key}</info>");
        $this->line("  Presentasi   : http://127.0.0.1:{$port}/presentasi?key={$key}");
        if ($lan) {
            $this->line("  Lewat WiFi   : http://{$lan}:{$port}/presentasi?key={$key}");
        }
        if ($audience) {
            $this->line("  Toko (QR)    : {$audience}");
        }
    }

    private function unknown(): int
    {
        $this->error('Aksi tidak dikenal. Pakai: php artisan saga-bridge on | off | status');

        return self::INVALID;
    }

    private function port(): int
    {
        return max(1, (int) $this->option('port'));
    }

    private function pendingCount(): int
    {
        try {
            return OutboxMessage::where('status', 'PENDING')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function makeKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $key = '';
        for ($i = 0; $i < 8; $i++) {
            $key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($key, 0, 4).'-'.substr($key, 4);
    }
}
