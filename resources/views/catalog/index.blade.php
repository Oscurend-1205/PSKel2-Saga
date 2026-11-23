@extends('layouts.app')

@section('title', 'Shambat Mall - Belanja Gadget Online')

@section('content')
<div class="space-y-5">

    <!-- Banner -->
    <div class="bg-[#ee4d2d] rounded-md p-6 sm:p-8 text-white flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-2">
            <span class="text-xs text-white/80">Mega Sale Gadget</span>
            <h1 class="text-2xl sm:text-3xl font-bold">Pilih barang, masuk keranjang, checkout.</h1>
            <p class="text-sm text-white/90 max-w-xl">
                Simulasi e-commerce dengan keranjang belanja, pembayaran Midtrans Sandbox, dan Saga Orchestration dengan transaksi kompensasi otomatis.
            </p>
        </div>
        @php $cCount = array_sum(array_column(session('cart', []), 'quantity')); @endphp
        <!-- <a href="{{ route('cart.index') }}" class="self-start md:self-center px-5 py-2.5 rounded bg-white text-[#ee4d2d] font-semibold text-sm hover:bg-slate-50 flex items-center gap-2 whitespace-nowrap">
            Lihat Keranjang
            @if($cCount > 0)
                <span class="bg-[#ee4d2d] text-white text-xs px-2 py-0.5 rounded-full">{{ $cCount }}</span>
            @endif
        </a> -->
    </div>

    <!-- Kategori -->
    <div class="bg-white rounded-md px-3 py-2 border border-slate-200 flex items-center justify-between text-xs text-slate-600 overflow-x-auto">
        <div class="flex items-center gap-1 whitespace-nowrap">
            <span class="px-3 py-1.5 bg-[#ee4d2d] text-white rounded cursor-pointer">Semua Produk</span>
            <span class="px-3 py-1.5 hover:bg-slate-100 rounded cursor-pointer">Laptop &amp; PC</span>
            <span class="px-3 py-1.5 hover:bg-slate-100 rounded cursor-pointer">Smartphone &amp; Gadget</span>
            <span class="px-3 py-1.5 hover:bg-slate-100 rounded cursor-pointer">Aksesoris Gaming</span>
            <span class="px-3 py-1.5 hover:bg-slate-100 rounded cursor-pointer">Audio &amp; TWS</span>
        </div>
        <div class="text-slate-400 hidden sm:block pl-4 whitespace-nowrap">
            {{ $products->count() }} produk
        </div>
    </div>

    <!-- Daftar produk -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        @foreach($products as $product)
            <div class="bg-white rounded-md border border-slate-200 hover:border-[#ee4d2d] flex flex-col overflow-hidden">
                <div class="relative bg-slate-100 h-44 overflow-hidden">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                    <div class="absolute top-2 left-2 bg-[#ee4d2d] text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm">
                        Mall
                    </div>
                    @if($product->stock <= 2)
                        <div class="absolute bottom-2 left-2 bg-amber-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-sm">
                            Sisa {{ $product->stock }}
                        </div>
                    @endif
                </div>

                <div class="p-3 flex-1 flex flex-col justify-between gap-2">
                    <div>
                        <h3 class="text-xs text-slate-800 line-clamp-2 leading-snug" title="{{ $product->name }}">
                            {{ $product->name }}
                        </h3>
                        <div class="mt-1 text-[11px] text-slate-400">
                            Rating 4.9, terjual 1,2 rb+
                        </div>
                    </div>

                    <div>
                        <div class="text-sm sm:text-base font-bold text-[#ee4d2d]">
                            Rp {{ number_format($product->price, 0, ',', '.') }}
                        </div>
                        <div class="text-[10px] text-slate-400 flex items-center justify-between mt-0.5">
                            <span>Stok {{ $product->stock }}</span>
                            <span class="text-emerald-600">Gratis ongkir</span>
                        </div>

                        <div class="grid grid-cols-2 gap-1.5 mt-3 pt-2 border-t border-slate-100">
                            <form action="{{ route('cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="w-full py-1.5 px-2 border border-[#ee4d2d] text-[#ee4d2d] hover:bg-orange-50 text-[11px] font-semibold rounded">
                                    Keranjang
                                </button>
                            </form>

                            <form action="{{ route('cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="direct_checkout" value="1">
                                <button type="submit" class="w-full py-1.5 px-2 bg-[#ee4d2d] text-white hover:bg-[#d73211] text-[11px] font-semibold rounded">
                                    Beli Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection