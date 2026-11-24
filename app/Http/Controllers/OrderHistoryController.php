<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SagaLog;
use App\Models\Shipment;
use App\Models\StockReservation;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    /**
     * Scope query pesanan milik audiens yang sedang login (guest session).
     */
    private function audienceOrders(Request $request)
    {
        $name  = session('audience_name');
        $email = session('audience_email');

        return Order::query()
            ->where(function ($q) use ($name, $email) {
                if ($name) {
                    $q->where('customer_name', $name);
                }
                if ($email) {
                    $q->orWhere('customer_email', $email);
                }
            });
    }

    /**
     * Tampilkan riwayat pesanan milik audiens.
     */
    public function index(Request $request)
    {
        if (!session('audience_name') && !session('audience_email')) {
            return redirect()->route('catalog.index')
                ->with('info', 'Masukkan nama Anda terlebih dahulu untuk melihat riwayat pesanan.');
        }

        $orders = $this->audienceOrders($request)
            ->with('items')
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * Detail satu pesanan (hanya bila milik audiens yang sedang login).
     */
    public function show(Request $request, string $orderCode)
    {
        if (!session('audience_name') && !session('audience_email')) {
            return redirect()->route('orders.index');
        }

        $order = $this->audienceOrders($request)
            ->with('items')
            ->where('order_code', $orderCode)
            ->firstOrFail();

        $payment     = Payment::where('order_code', $orderCode)->first();
        $reservations = StockReservation::where('order_code', $orderCode)->get();
        $shipment    = Shipment::where('order_code', $orderCode)->first();
        $sagaLogs    = SagaLog::where('order_code', $orderCode)->orderBy('id', 'asc')->get();

        return view('checkout.status', compact('order', 'payment', 'reservations', 'shipment', 'sagaLogs'));
    }
}
