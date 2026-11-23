<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Exception;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production', false);
        Config::$isSanitized = (bool) config('midtrans.is_sanitized', true);
        Config::$is3ds = (bool) config('midtrans.is_3ds', true);
    }

    /**
     * Create Snap Token from Midtrans Sandbox
     */
    public function createSnapToken(array $params): string
    {
        try {
            // Check if server key is default placeholder; if so, create simulated snap token
            if (empty(Config::$serverKey) || Config::$serverKey === 'SB-Mid-server-TEST_KEY') {
                return 'SNAP-SIMULATED-' . uniqid();
            }

            return Snap::getSnapToken($params);
        } catch (Exception $e) {
            Log::warning('Midtrans Snap SDK Error (falling back to mock token): ' . $e->getMessage());
            return 'SNAP-MOCK-' . uniqid();
        }
    }
}

