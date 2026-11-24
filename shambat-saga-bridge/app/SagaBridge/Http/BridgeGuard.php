<?php

namespace App\SagaBridge\Http;

use App\SagaBridge\BridgeState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga semua endpoint /saga-bridge/api:
 * - menolak semua permintaan saat bridge dinonaktifkan (503),
 * - mewajibkan kunci yang dicetak oleh "php artisan saga-bridge on" (401),
 * - menambahkan header CORS agar file presentasi yang dibuka langsung
 *   (file://) tetap bisa terhubung.
 */
class BridgeGuard
{
    public function __construct(private BridgeState $state)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->withCors(response('', 204));
        }

        if (! $this->state->enabled()) {
            return $this->withCors(response()->json([
                'ok' => false,
                'enabled' => false,
                'message' => 'Saga Bridge sedang nonaktif. Jalankan: php artisan saga-bridge on',
            ], 503));
        }

        $key = $request->header('X-Saga-Bridge-Key') ?? $request->query('key');

        if (! $this->state->keyMatches(is_string($key) ? $key : null)) {
            return $this->withCors(response()->json([
                'ok' => false,
                'enabled' => true,
                'message' => 'Kunci bridge salah. Lihat kunci di output "php artisan saga-bridge status".',
            ], 401));
        }

        return $this->withCors($next($request));
    }

    private function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Saga-Bridge-Key');
        $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
