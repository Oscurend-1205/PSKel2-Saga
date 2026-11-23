@extends('layouts.app')

@section('title', 'Pembayaran Pesanan - ' . $order->order_code . ' - Shambat')

@section('content')
<div class="space-y-5">

    <!-- Header + langkah -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Pembayaran</h1>
            <ol class="flex items-center gap-2 mt-1 text-xs text-slate-400">
                <li>1. Keranjang</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li class="text-[#ee4d2d] font-semibold">2. Checkout</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li>3. Selesai</li>
            </ol>
        </div>
        <div class="text-xs text-slate-500">
            No. pesanan <span class="font-semibold text-slate-800">{{ $order->order_code }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

        <!-- Kolom kiri -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Barang -->
            <section class="bg-white border border-slate-200 rounded-md">
                <div class="flex items-baseline justify-between px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-800">Rincian Barang</h2>
                    <span class="text-xs text-slate-400">{{ $order->items->count() ?: 1 }} barang</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @if($order->items && $order->items->isNotEmpty())
                        @foreach($order->items as $item)
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
                    @else
                        <div class="px-5 py-3 flex justify-between items-center gap-4">
                            <div class="min-w-0">
                                <div class="text-sm text-slate-800">{{ $order->product_name }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $order->quantity }} unit</div>
                            </div>
                            <div class="text-sm font-semibold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <!-- Pengiriman -->
            <section class="bg-white border border-slate-200 rounded-md p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Dikirim ke</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
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
        </div>

        <!-- Kolom kanan -->
        <aside class="lg:sticky lg:top-6 space-y-3">

            @if($simInv || $simShp)
                <div class="bg-rose-50 border border-rose-300 rounded-md px-4 py-3">
                    <div class="text-[11px] text-rose-700">Skenario pengujian aktif</div>
                    <div class="text-sm font-semibold text-rose-800">
                        @if($simInv) Gagal di inventory @endif
                        @if($simShp) Gagal di pengiriman @endif
                    </div>
                    <div class="text-xs text-rose-700 mt-1">Setelah dibayar, transaksi kompensasi akan berjalan.</div>
                </div>
            @endif

            <section class="bg-white border border-slate-200 border-t-2 border-t-[#ee4d2d] rounded-md p-5 space-y-4">
                <div>
                    <div class="text-xs text-[#ee4d2d] font-semibold">Menunggu pembayaran</div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span class="text-sm text-slate-700">Total tagihan</span>
                        <span class="text-2xl font-bold text-slate-900">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                <button id="pay-button" type="button" class="w-full py-3 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-sm rounded">
                    Bayar dengan Midtrans
                </button>

                <form action="{{ route('checkout.simulate_pay', $order->order_code) }}" method="POST">
                    @csrf
                    <input type="hidden" name="simulate_inventory_fail" value="{{ $simInv }}">
                    <input type="hidden" name="simulate_shipping_fail" value="{{ $simShp }}">
                    <input type="hidden" name="auto_process_outbox" value="{{ $autoProc }}">
                    <button type="submit" class="w-full py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-sm rounded">
                        Simulasi bayar instan
                    </button>
                </form>

                <p class="text-[11px] text-slate-400 leading-relaxed">
                    Midtrans membuka popup Snap Sandbox. Simulasi bayar instan langsung mengirim webhook PAID, tanpa internet atau kartu kredit.
                </p>
            </section>
        </aside>

    </div>
</div>

@push('scripts')
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}"></script>
<script>
    const payButton = document.getElementById('pay-button');
    const snapToken = @json($payment->snap_token);
    const statusUrl = @json(route('checkout.status', $order->order_code));

    payButton.addEventListener('click', function () {
        if (!snapToken || snapToken.startsWith('SNAP-SIMULATED-') || snapToken.startsWith('SNAP-MOCK-')) {
            alert('Token yang dipakai adalah token simulasi. Gunakan tombol "Simulasi bayar instan" untuk menguji alur.');
            return;
        }

        window.snap.pay(snapToken, {
            onSuccess: function (result) {
                // Post confirmation to backend so Saga triggers automatically even without ngrok webhook tunnel
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('checkout.simulate_pay', $order->order_code) }}";

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = "{{ csrf_token() }}";
                form.appendChild(csrfInput);

                const invInput = document.createElement('input');
                invInput.type = 'hidden';
                invInput.name = 'simulate_inventory_fail';
                invInput.value = "{{ $simInv }}";
                form.appendChild(invInput);

                const shpInput = document.createElement('input');
                shpInput.type = 'hidden';
                shpInput.name = 'simulate_shipping_fail';
                shpInput.value = "{{ $simShp }}";
                form.appendChild(shpInput);

                const autoInput = document.createElement('input');
                autoInput.type = 'hidden';
                autoInput.name = 'auto_process_outbox';
                autoInput.value = "{{ $autoProc }}";
                form.appendChild(autoInput);

                document.body.appendChild(form);
                form.submit();
            },
            onPending: function () { window.location.href = statusUrl; },
            onError:   function () { alert('Pembayaran gagal. Silakan coba lagi.'); },
            onClose:   function () {}
        });
    });
</script>
@endpush
@endsection