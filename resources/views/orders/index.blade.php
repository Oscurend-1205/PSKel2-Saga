@extends('layouts.app')

@section('title', 'Riwayat Pesanan - Shambat')

@section('content')
@php
    $statusBadge = function ($status) {
        return match ($status) {
            'COMPLETED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'CANCELED'  => 'bg-rose-50 text-rose-700 border-rose-200',
            default     => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    };
    $statusLabel = fn ($s) => $s === 'COMPLETED' ? 'Selesai' : ($s === 'CANCELED' ? 'Dibatalkan' : 'Diproses');
@endphp

<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Riwayat Pesanan</h1>
            <p class="text-xs text-slate-400 mt-1">Semua pesanan yang dibuat oleh {{ session('audience_name') ?: session('audience_email') }}</p>
        </div>
        <a href="{{ route('catalog.index') }}" class="text-xs text-slate-500 hover:text-[#ee4d2d] hover:underline">
            Kembali ke katalog
        </a>
    </div>

    @forelse($orders as $order)
        <section class="bg-white border border-slate-200 rounded-md overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-slate-900">{{ $order->order_code }}</span>
                    <span class="text-[11px] text-slate-400">{{ $order->created_at->format('d M Y, H:i') }} WIB</span>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded border text-[11px] font-semibold {{ $statusBadge($order->status) }}">
                    {{ $statusLabel($order->status) }}
                </span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($order->items->take(3) as $item)
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
                @endforeach
                @if($order->items->count() > 3)
                    <div class="px-5 py-2 text-xs text-slate-400">+{{ $order->items->count() - 3 }} barang lainnya</div>
                @endif
            </div>

            <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-slate-600">
                    Total: <span class="font-bold text-[#ee4d2d]">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('orders.show', $order->order_code) }}"
                   class="px-4 py-1.5 text-xs font-semibold text-slate-700 border border-slate-300 rounded hover:bg-slate-50">
                    Lihat Detail &amp; Jejak Saga
                </a>
            </div>
        </section>
    @empty
        <section class="bg-white border border-slate-200 rounded-md p-10 text-center">
            <div class="text-4xl mb-3">📦</div>
            <h2 class="text-sm font-semibold text-slate-800">Belum ada pesanan</h2>
            <p class="text-xs text-slate-400 mt-1 mb-4">Pesanan Anda akan muncul di sini setelah checkout.</p>
            <a href="{{ route('catalog.index') }}" class="inline-block px-5 py-2 bg-[#ee4d2d] hover:bg-[#d73211] text-white text-sm font-semibold rounded">
                Mulai Belanja
            </a>
        </section>
    @endforelse

    @if($orders->hasPages())
        <div class="flex justify-center">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
