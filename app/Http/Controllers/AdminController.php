<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SagaLog;
use App\Models\Shipment;
use App\Models\StockReservation;
use App\Services\Outbox\OutboxProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        protected OutboxProcessor $outboxProcessor
    ) {}

    /**
     * Admin Dashboard: Monitor Saga State, Outbox, and Domain Tables
     */
    public function index()
    {
        $orders = Order::latest()->take(20)->get();
        $payments = Payment::latest()->take(20)->get();
        $products = Product::all();
        $reservations = StockReservation::latest()->take(20)->get();
        $shipments = Shipment::latest()->take(20)->get();
        $outboxMessages = OutboxMessage::latest()->take(30)->get();
        $sagaLogs = SagaLog::latest()->take(40)->get();

        $pendingOutboxCount = OutboxMessage::where('status', 'PENDING')->count();

        return view('admin.dashboard', compact(
            'orders',
            'payments',
            'products',
            'reservations',
            'shipments',
            'outboxMessages',
            'sagaLogs',
            'pendingOutboxCount'
        ));
    }

    /**
     * Trigger Outbox Processing manually from Admin Panel
     */
    public function processOutbox(Request $request)
    {
        $mode = $request->input('mode', 'next'); // 'next' or 'all'

        if ($mode === 'all') {
            $processedCount = $this->outboxProcessor->processAll();
            $message = "Berhasil memproses seluruh antrean outbox ({$processedCount} event dieksekusi).";
        } else {
            $msg = $this->outboxProcessor->processNext();
            $message = $msg 
                ? "Berhasil memproses 1 pesan Outbox: [{$msg->event_type}] untuk pesanan {$msg->aggregate_id}."
                : "Tidak ada pesan Outbox PENDING.";
        }

        return redirect()->route('admin.dashboard')->with('status', $message);
    }

    /**
     * Reset Demo Data (re-seed products, clear transactions & logs)
     */
    public function resetDemo()
    {
        DB::statement('DELETE FROM orders');
        DB::statement('DELETE FROM payments');
        DB::statement('DELETE FROM stock_reservations');
        DB::statement('DELETE FROM shipments');
        DB::statement('DELETE FROM outbox_messages');
        DB::statement('DELETE FROM saga_logs');

        Artisan::call('db:seed', ['--class' => 'ProductSeeder']);

        return redirect()->route('admin.dashboard')->with('status', 'Database berhasil di-reset ke kondisi awal untuk simulasi demonstrasi!');
    }
}

