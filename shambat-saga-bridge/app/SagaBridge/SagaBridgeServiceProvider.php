<?php

namespace App\SagaBridge;

use App\SagaBridge\Console\SagaBridgeCommand;
use App\Services\Outbox\OutboxProcessor;
use Illuminate\Support\ServiceProvider;

/**
 * Saga Bridge: jembatan antara file presentasi dan aplikasi Shambat.
 *
 * Modul ini berdiri sendiri. Untuk mencabutnya, hapus folder app/SagaBridge,
 * folder resources/saga-bridge, dan satu baris di bootstrap/providers.php.
 */
class SagaBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'saga-bridge');

        $this->app->singleton(BridgeState::class, function () {
            return new BridgeState((string) config('saga-bridge.state_file'));
        });

        // Semua kode yang meminta OutboxProcessor akan mendapat versi yang bisa
        // "ditahan" dari presentasi. Saat bridge mati, perilakunya identik.
        $this->app->bind(OutboxProcessor::class, PausableOutboxProcessor::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SagaBridgeCommand::class]);
        }

        if (config('saga-bridge.allowed')) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }
    }
}
