<?php

namespace App\SagaBridge\Http;

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Product;
use App\Models\SagaLog;
use App\SagaBridge\BridgeState;
use App\SagaBridge\PausableOutboxProcessor;
use App\SagaBridge\Support\LanAddress;
use App\SagaBridge\Support\Scenarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class BridgeController
{
    public function __construct(private BridgeState $state)
    {
    }

    /** GET /presentasi : file presentasi dilayani dari origin yang sama dengan toko. */
    public function presentation()
    {
        $file = config('saga-bridge.presentation_file') ?: resource_path('saga-bridge/presentasi.html');

        if (! is_file($file)) {
            return response('File presentasi belum ada di resources/saga-bridge/presentasi.html', 404);
        }

        return response()->file($file, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function preflight()
    {
        return response('', 204);
    }

    /** GET /saga-bridge/api/ping */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'relay_held' => $this->state->relayHeld(),
            'last_log_id' => (int) (SagaLog::max('id') ?? 0),
            'audience_url' => $this->audienceUrl($request),
            'enabled_at' => $this->state->all()['enabled_at'],
            'server_time' => now()->format('H:i:s'),
        ]);
    }

    /**
     * GET /saga-bridge/api/feed?after={id}&hold={0|1}
     * Dipanggil presentasi tiap detik. "hold=1" sekaligus memperpanjang
     * penahanan relay outbox (lease), jadi relay tidak macet kalau presentasi ditutup.
     */
    public function feed(Request $request): JsonResponse
    {
        if ($request->boolean('hold')) {
            $this->state->holdRelay((int) config('saga-bridge.hold_lease_seconds', 15));
        } else {
            $this->state->releaseRelay();
        }

        $after = max(0, (int) $request->query('after', 0));
        $enabledAt = $this->state->all()['enabled_at'];

        $logs = SagaLog::where('id', '>', $after)
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (SagaLog $log) => $this->presentLog($log))
            ->values();

        $queue = OutboxMessage::where('status', 'PENDING')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(fn (OutboxMessage $message) => $this->presentMessage($message))
            ->values();

        $outbox = OutboxMessage::orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (OutboxMessage $message) => $this->presentMessage($message))
            ->values();

        $products = Product::orderBy('id')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (int) $product->stock,
            ])
            ->values();

        $orders = Order::orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => $this->presentOrder($order))
            ->values();

        $audience = Order::where('customer_email', 'not like', '%'.Scenarios::EMAIL_DOMAIN);
        if ($enabledAt) {
            $audience->where('created_at', '>=', $enabledAt);
        }

        return response()->json([
            'ok' => true,
            'relay_held' => $this->state->relayHeld(),
            'last_log_id' => (int) (SagaLog::max('id') ?? 0),
            'logs' => $logs,
            'queue' => $queue,
            'queue_total' => OutboxMessage::where('status', 'PENDING')->count(),
            'outbox' => $outbox,
            'products' => $products,
            'orders' => $orders,
            'stats' => [
                'orders' => Order::count(),
                'completed' => Order::where('status', 'COMPLETED')->count(),
                'canceled' => Order::where('status', 'CANCELED')->count(),
                'pending' => Order::where('status', 'PENDING')->count(),
                'audience' => $audience->count(),
            ],
            'server_time' => now()->format('H:i:s'),
        ]);
    }

    /** POST /saga-bridge/api/scenario {type, count, hold} */
    public function scenario(Request $request, Scenarios $scenarios, PausableOutboxProcessor $processor): JsonResponse
    {
        $type = (string) $request->input('type', 'sukses');

        if (! in_array($type, Scenarios::TYPES, true)) {
            return response()->json(['ok' => false, 'message' => "Skenario tidak dikenal: {$type}"], 422);
        }

        if ($request->boolean('hold')) {
            $this->state->holdRelay((int) config('saga-bridge.hold_lease_seconds', 15));
        } else {
            $this->state->releaseRelay();
        }

        try {
            $result = $scenarios->run($type, (int) $request->input('count', 1));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Sama seperti CheckoutController: setelah bayar, relay langsung bekerja,
        // kecuali presentasi sedang menahannya (mode Sinematik / Manual).
        $processed = $processor->processAll();

        return response()->json([
            'ok' => true,
            'type' => $type,
            'orders' => $result['orders'],
            'note' => $result['note'],
            'processed' => $processed,
            'relay_held' => $this->state->relayHeld(),
        ]);
    }

    /** POST /saga-bridge/api/step : relay mengirim tepat satu event (FIFO). */
    public function step(PausableOutboxProcessor $processor): JsonResponse
    {
        $message = $processor->processNextForced();

        return response()->json([
            'ok' => true,
            'processed' => $message ? $this->presentMessage($message) : null,
            'remaining' => OutboxMessage::where('status', 'PENDING')->count(),
        ]);
    }

    /** POST /saga-bridge/api/flush : relay hidup kembali dan mengosongkan antrean. */
    public function flush(PausableOutboxProcessor $processor): JsonResponse
    {
        $count = 0;
        while ($count < 200 && $processor->processNextForced()) {
            $count++;
        }

        return response()->json(['ok' => true, 'processed' => $count]);
    }

    /** POST /saga-bridge/api/release : lepaskan penahanan relay segera. */
    public function release(): JsonResponse
    {
        $this->state->releaseRelay();

        return response()->json(['ok' => true]);
    }

    /** POST /saga-bridge/api/reset : kosongkan transaksi demo dan isi ulang stok. */
    public function reset(): JsonResponse
    {
        foreach (['order_items', 'orders', 'payments', 'stock_reservations', 'shipments', 'outbox_messages', 'saga_logs'] as $table) {
            DB::table($table)->delete();
        }

        Artisan::call('db:seed', ['--class' => 'ProductSeeder', '--force' => true]);

        return response()->json([
            'ok' => true,
            'message' => 'Data demo dikosongkan dan stok produk dikembalikan.',
        ]);
    }

    private function presentLog(SagaLog $log): array
    {
        return [
            'id' => $log->id,
            'order' => $log->order_code,
            'step' => $log->saga_step,
            'action' => $log->action_type,
            'status' => $log->status,
            'details' => $log->details ?? [],
            'at' => $log->created_at?->format('H:i:s'),
        ];
    }

    private function presentMessage(OutboxMessage $message): array
    {
        return [
            'id' => $message->id,
            'type' => $message->aggregate_type,
            'order' => $message->aggregate_id,
            'event' => $message->event_type,
            'status' => $message->status,
            'created' => $message->created_at?->format('H:i:s'),
            'processed' => $message->processed_at?->format('H:i:s'),
        ];
    }

    private function cleanCustomerName(?string $name): string
    {
        $name = (string) $name;
        $clean = trim(preg_replace('/\s*\(.*?\)\s*/', '', $name));
        $clean = trim(preg_replace('/(\+62|08)[0-9\- ]+/', '', $clean));
        if (empty($clean) || stripos($clean, 'budi') !== false || stripos($clean, 'santoso') !== false) {
            return 'Audiens';
        }
        return $clean;
    }

    private function presentOrder(Order $order): array
    {
        return [
            'code' => $order->order_code,
            'customer' => $this->cleanCustomerName($order->customer_name),
            'product' => $order->product_name,
            'quantity' => (int) $order->quantity,
            'total' => (float) $order->total_amount,
            'status' => $order->status,
            'reason' => $order->fail_reason,
            'presenter' => str_ends_with((string) $order->customer_email, Scenarios::EMAIL_DOMAIN),
            'at' => $order->created_at?->format('H:i:s'),
        ];
    }

    private function audienceUrl(Request $request): string
    {
        $fixed = config('saga-bridge.audience_url') ?: $this->state->all()['audience_url'];

        if (is_string($fixed) && $fixed !== '') {
            return rtrim($fixed, '/');
        }

        if (! in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)) {
            return $request->getSchemeAndHttpHost();
        }

        $ip = LanAddress::detect();

        return $ip
            ? $request->getScheme().'://'.$ip.':'.$request->getPort()
            : $request->getSchemeAndHttpHost();
    }
}
