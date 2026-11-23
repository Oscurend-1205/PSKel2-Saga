@extends('layouts.app')

@section('title', 'Keranjang Belanja - Shambat')

@section('content')
<div class="space-y-5">

    <!-- Header + langkah -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Keranjang Belanja</h1>
            <ol class="flex items-center gap-2 mt-1 text-xs text-slate-400">
                <li class="text-[#ee4d2d] font-semibold">1. Keranjang</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li>2. Checkout</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li>3. Selesai</li>
            </ol>
        </div>
        <a href="{{ route('catalog.index') }}" class="text-xs text-slate-500 hover:text-[#ee4d2d] hover:underline">
            Lanjut belanja
        </a>
    </div>

    @if(empty($cart))
        <!-- Keranjang kosong -->
        <div class="bg-white border border-slate-200 rounded-md px-6 py-14 text-center">
            <h2 class="text-lg font-semibold text-slate-800">Keranjang masih kosong</h2>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mt-2">
                Tambahkan produk dari katalog untuk mencoba alur transaksi Saga.
            </p>
            <a href="{{ route('catalog.index') }}" class="inline-block mt-5 px-6 py-2.5 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-sm rounded">
                Lihat Katalog
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <!-- Kolom kiri: daftar barang -->
            <section class="lg:col-span-2 bg-white border border-slate-200 rounded-md">
                <div class="flex items-baseline justify-between px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-800">Barang di keranjang</h2>
                    <span class="text-xs text-slate-400">{{ $totalQuantity }} barang</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($cart as $item)
                        @php $subtotal = $item['price'] * $item['quantity']; @endphp
                        <div class="px-5 py-4 flex gap-4">
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded border border-slate-200 flex-shrink-0">

                            <div class="flex-1 min-w-0 flex flex-col justify-between gap-3">
                                <div class="flex justify-between gap-4">
                                    <div class="min-w-0">
                                        <h3 class="text-sm text-slate-800 line-clamp-2 leading-snug">{{ $item['name'] }}</h3>
                                        <div class="text-xs text-slate-400 mt-1">
                                            Rp {{ number_format($item['price'], 0, ',', '.') }} per unit
                                            @if($item['stock'] <= 2)
                                                <span class="text-amber-600">, sisa {{ $item['stock'] }}</span>
                                            @else
                                                <span>, stok {{ $item['stock'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 whitespace-nowrap">
                                        Rp {{ number_format($subtotal, 0, ',', '.') }}
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <!-- Kuantitas -->
                                    <div class="inline-flex items-center border border-slate-300 rounded">
                                        <form action="{{ route('cart.update') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                            <input type="hidden" name="action" value="minus">
                                            <button type="submit" aria-label="Kurangi" class="w-8 h-8 text-slate-600 hover:bg-slate-100">-</button>
                                        </form>
                                        <span class="w-10 text-center text-sm font-semibold text-slate-800 border-x border-slate-300 leading-8">{{ $item['quantity'] }}</span>
                                        <form action="{{ route('cart.update') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                            <input type="hidden" name="action" value="plus">
                                            <button type="submit" aria-label="Tambah" class="w-8 h-8 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                                                    {{ $item['quantity'] >= $item['stock'] ? 'disabled' : '' }}>+</button>
                                        </form>
                                    </div>

                                    <form action="{{ route('cart.remove') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-600 hover:underline">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 text-xs">
                    <form action="{{ route('cart.clear') }}" method="POST" onsubmit="return confirm('Kosongkan semua barang di keranjang?')">
                        @csrf
                        <button type="submit" class="text-slate-500 hover:text-rose-600 hover:underline">Kosongkan keranjang</button>
                    </form>
                </div>
            </section>

            <!-- Kolom kanan: ringkasan -->
            <aside class="lg:sticky lg:top-6 space-y-3">
                <section class="bg-white border border-slate-200 border-t-2 border-t-[#ee4d2d] rounded-md p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-slate-800">Ringkasan Belanja</h2>

                    <dl class="space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <dt>Harga barang ({{ $totalQuantity }})</dt>
                            <dd class="text-slate-800">Rp {{ number_format($totalAmount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Ongkos kirim</dt>
                            <dd class="text-emerald-700">Gratis</dd>
                        </div>
                    </dl>

                    <div class="flex items-baseline justify-between pt-3 border-t border-slate-200">
                        <span class="text-sm text-slate-700">Total</span>
                        <span class="text-2xl font-bold text-[#ee4d2d]">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                    </div>

                    <a href="{{ route('checkout.index') }}" class="block w-full text-center py-3 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-sm rounded">
                        Checkout
                    </a>
                </section>

                <div class="bg-orange-50 border border-orange-200 rounded-md px-4 py-3 text-xs text-[#c23a1e]">
                    Voucher gratis ongkir otomatis dipakai untuk pesanan ini.
                </div>
            </aside>

        </div>
    @endif

</div>
@endsection