@extends('layouts.app')

@section('title', 'Status Pesanan - ' . $order->order_code . ' - Shambat')

@section('content')
@php
    $statusMap = [
        'COMPLETED' => ['label' => 'Pesanan selesai', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-300', 'dot' => 'bg-emerald-500'],
        'CANCELED'  => ['label' => 'Dibatalkan',      'text' => 'text-rose-700',    'bg' => 'bg-rose-50',    'border' => 'border-rose-300',    'dot' => 'bg-rose-500'],
    ];
    $st = $statusMap[$order->status] ?? ['label' => 'Sedang diproses', 'text' => 'text-amber-700', 'bg' => 'bg-amber-50', 'border' => 'border-amber-300', 'dot' => 'bg-amber-500'];
@endphp

<div class="space-y-5">

    <!-- Header + langkah -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Pesanan</h1>
            <ol class="flex items-center gap-2 mt-1 text-xs text-slate-400">
                <li>1. Keranjang</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li>2. Checkout</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li class="text-[#ee4d2d] font-semibold">3. Selesai</li>
            </ol>
        </div>
        <a href="{{ route('catalog.index') }}" class="text-xs text-slate-500 hover:text-[#ee4d2d] hover:underline">
            Kembali ke katalog
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

        <!-- Kolom kiri -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Status -->
            <section class="bg-white border border-slate-200 border-t-2 border-t-[#ee4d2d] rounded-md p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="text-xs text-slate-400">No. pesanan</div>
                        <div class="text-lg font-bold text-slate-900">{{ $order->order_code }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Dipesan {{ $order->created_at->format('d M Y, H:i') }} WIB</div>
                    </div>
                    <div class="self-start sm:self-center inline-flex items-center gap-2 px-3 py-1.5 rounded border {{ $st['bg'] }} {{ $st['border'] }} {{ $st['text'] }} text-sm font-semibold">
                        <span class="w-2 h-2 rounded-full {{ $st['dot'] }}"></span>
                        {{ $st['label'] }}
                        <span class="text-xs font-normal opacity-70">({{ $order->status }})</span>
                    </div>
                </div>

                @if($order->fail_reason)
                    <div class="mt-4 p-4 rounded bg-rose-50 border border-rose-200 text-sm text-rose-800">
                        <div class="font-semibold">Pesanan dibatalkan dan transaksi kompensasi sudah dijalankan.</div>
                        <div class="mt-1 text-xs">{{ $order->fail_reason }}</div>
                        <div class="mt-2 text-xs text-rose-600">
                            Dana sudah dikembalikan ke pelanggan, dan stok yang sempat direservasi sudah dikembalikan ke etalase.
                        </div>
                    </div>
                @endif
            </section>

            <!-- Pengiriman -->
            <section class="bg-white border border-slate-200 rounded-md p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Pengiriman</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400">Kurir</dt>
                        <dd class="text-slate-800">{{ $shipment?->courier_name ?? 'SAGA-EXPRESS' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">No. resi</dt>
                        <dd class="{{ $shipment?->tracking_number ? 'text-slate-900 font-semibold' : 'text-slate-400' }}">
                            {{ $shipment?->tracking_number ?? 'Belum terbit' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Penerima</dt>
                        <dd class="text-slate-800">{{ $order->customer_name }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-400">Alamat</dt>
                        <dd class="text-slate-800">{{ $order->shipping_address }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Barang -->
            <section class="bg-white border border-slate-200 rounded-md">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-800">Daftar Barang</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($order->items as $item)
                        <div class="px-5 py-3 flex justify-between items-center gap-4">
                            <div class="min-w-0">
                                <div class="text-sm text-slate-800 line-clamp-1">{{ $item->product_name }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    {{ $item->quantity }} &times; Rp {{ number_format($item->price, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-3 flex justify-between items-center gap-4">
                            <div class="text-sm text-slate-800">{{ $order->product_name }}</div>
                            <div class="text-sm font-semibold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </div>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <!-- Kolom kanan -->
        <aside class="lg:sticky lg:top-6 space-y-3">
            <section class="bg-white border border-slate-200 rounded-md p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-800">Ringkasan Pembayaran</h2>

                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <dt>Metode</dt>
                        <dd class="text-slate-800">{{ $payment?->payment_type ?? 'Midtrans' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Status pembayaran</dt>
                        <dd class="text-slate-800">{{ $payment?->status ?? 'PENDING' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Ongkos kirim</dt>
                        <dd class="text-slate-800">Rp 0</dd>
                    </div>
                </dl>

                <div class="flex items-baseline justify-between pt-3 border-t border-slate-200">
                    <span class="text-sm text-slate-700">Total</span>
                    <span class="text-2xl font-bold text-[#ee4d2d]">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>

                <a href="{{ route('catalog.index') }}" class="block w-full text-center py-3 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-sm rounded">
                    Belanja Lagi
                </a>
            </section>

            <div class="bg-white border border-slate-200 rounded-md px-4 py-3 flex items-center justify-between gap-3">
                <div class="text-xs text-slate-600">Lihat jejak Saga dan outbox untuk pesanan ini.</div>
                <button type="button" onclick="openSagaTraceModal()"
                        class="px-3 py-1.5 text-xs font-semibold text-slate-700 border border-slate-300 rounded hover:bg-slate-50 whitespace-nowrap">
                    Lihat jejak
                </button>
            </div>
        </aside>

    </div>
</div>

<!-- Modal jejak Saga -->
@php
    $anyRes = $reservations->isNotEmpty() && $reservations->contains(fn($r) => $r->status === 'RESERVED');
    $allRel = $reservations->isNotEmpty() && $reservations->every(fn($r) => $r->status === 'RELEASED');
    $invStatus = $anyRes ? 'RESERVED' : ($allRel ? 'RELEASED' : 'NONE');

    $tone = function ($status) {
        return match ($status) {
            'COMPLETED', 'PAID', 'RESERVED', 'SCHEDULED', 'SUCCESS' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
            'CANCELED', 'FAILED'                                    => 'text-rose-700 bg-rose-50 border-rose-200',
            'REFUNDED', 'RELEASED'                                  => 'text-violet-700 bg-violet-50 border-violet-200',
            'NONE', 'NOT_SCHEDULED'                                 => 'text-slate-500 bg-slate-50 border-slate-200',
            default                                                 => 'text-amber-700 bg-amber-50 border-amber-200',
        };
    };

    $services = [
        ['name' => 'Order',     'value' => $order->order_code,                       'status' => $order->status],
        ['name' => 'Payment',   'value' => $payment?->payment_type ?? 'Midtrans',    'status' => $payment?->status ?? 'PENDING'],
        ['name' => 'Inventory', 'value' => $reservations->count() . ' reservasi',    'status' => $invStatus],
        ['name' => 'Shipping',  'value' => $shipment?->tracking_number ?? '-',       'status' => $shipment?->status ?? 'NOT_SCHEDULED'],
    ];
@endphp

<div id="sagaTraceModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50" onclick="if (event.target === this) closeSagaTraceModal()">
    <div class="bg-white rounded-md max-w-4xl w-full max-h-[90vh] flex flex-col border border-slate-200 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="sagaTraceTitle">

        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-4">
            <div>
                <h3 id="sagaTraceTitle" class="font-semibold text-slate-800 text-sm">Jejak Saga, {{ $order->order_code }}</h3>
                <p class="text-xs text-slate-400">Status tiap service, langkah Saga, dan aksi kompensasi.</p>
            </div>
            <button type="button" onclick="closeSagaTraceModal()" class="text-xs text-slate-500 hover:text-slate-800 hover:underline">Tutup</button>
        </div>

        <div class="p-5 overflow-y-auto space-y-6">

            <!-- Status per service -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($services as $svc)
                    <div class="border border-slate-200 rounded p-3">
                        <div class="text-xs text-slate-400">{{ $svc['name'] }} service</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 truncate">{{ $svc['value'] }}</div>
                        <span class="mt-2 inline-block px-2 py-0.5 rounded border text-[11px] font-semibold {{ $tone($svc['status']) }}">
                            {{ $svc['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <!-- Log langkah Saga -->
            <div>
                <h4 class="text-sm font-semibold text-slate-800 mb-3">Langkah Saga</h4>
                <ol class="relative border-l border-slate-200 ml-1.5 space-y-4">
                    @forelse($sagaLogs as $log)
                        @php $isComp = $log->action_type === 'COMPENSATING'; @endphp
                        <li class="pl-5 relative">
                            <span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full {{ $isComp ? 'bg-rose-500' : ($log->status === 'SUCCESS' ? 'bg-emerald-500' : 'bg-amber-500') }}"></span>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-800">{{ $log->saga_step }}</span>
                                    @if($isComp)
                                        <span class="text-[11px] px-1.5 py-0.5 rounded border text-rose-700 bg-rose-50 border-rose-200">Kompensasi</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="font-semibold {{ $log->status === 'SUCCESS' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $log->status }}</span>
                                    <span class="text-slate-400 tabular-nums">{{ $log->created_at->format('H:i:s.v') }}</span>
                                </div>
                            </div>

                            @if($log->details)
                                <details class="mt-1.5">
                                    <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-800">Detail</summary>
                                    <pre class="mt-1.5 text-[11px] text-slate-700 bg-slate-50 border border-slate-200 rounded p-2 overflow-x-auto">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </li>
                    @empty
                        <li class="pl-5 text-sm text-slate-400">Belum ada catatan langkah Saga.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button type="button" onclick="closeSagaTraceModal()" class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs rounded">
                Tutup
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openSagaTraceModal() {
        const modal = document.getElementById('sagaTraceModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeSagaTraceModal() {
        const modal = document.getElementById('sagaTraceModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSagaTraceModal();
    });
</script>
@endpush
@endsection