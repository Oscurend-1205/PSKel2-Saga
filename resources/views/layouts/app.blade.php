<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shambat - Jual Beli Online Terpercaya')</title>
    
    <!-- Shambat Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f5f5f5; color: #222; }
        code, pre { font-family: 'JetBrains Mono', monospace; }
        .shambat-orange { color: #ee4d2d; }
        .bg-shambat-orange { background-color: #ee4d2d; }
        .border-shambat-orange { border-color: #ee4d2d; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- Shambat Top Topbar -->
    <div class="bg-[#d73211] text-white text-[12px] py-1.5 px-4 hidden md:block">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <span>Seller Centre</span>
                <span>Mulai Jual</span>
                <span>Download Aplikasi</span>
                <span>Ikuti kami di Shambat</span>
            </div>
            <div class="flex items-center space-x-4">
                <span>Bantuan</span>
                <span>Bahasa Indonesia</span>
                <span class="font-bold">Budi Santoso</span>
            </div>
        </div>
    </div>

    <!-- Shambat Main Header -->
    <header class="bg-[#ee4d2d] text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4">
            <!-- Shambat Brand Logo -->
            <a href="{{ route('catalog.index') }}" class="flex items-center gap-2 text-white">
                <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-[#ee4d2d] font-black text-2xl shadow">
                    S
                </div>
                <div>
                    <div class="text-2xl font-black tracking-tight leading-none">Shambat</div>
                    <div class="text-[10px] tracking-widest uppercase opacity-90 font-medium">Saga E-Commerce</div>
                </div>
            </a>

            <!-- Search Bar Simulation -->
            <div class="flex-1 max-w-2xl hidden sm:block">
                <div class="relative flex">
                    <input type="text" placeholder="Cari barang gadget, laptop, keyboard diskon 50% di Shambat..." 
                           class="w-full bg-white text-slate-800 text-sm px-4 py-2.5 rounded-l-md focus:outline-none placeholder-slate-400">
                    <button class="bg-[#fb5533] px-6 text-white text-sm font-semibold rounded-r-md hover:bg-[#d73211] transition flex items-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Cart Icon & Nav Links -->
            <div class="flex items-center space-x-5">
                @php
                    $cartCount = array_sum(array_column(session('cart', []), 'quantity'));
                @endphp
                <a href="{{ route('cart.index') }}" class="relative p-2 text-white hover:opacity-90 transition flex items-center gap-2">
                    <div class="relative">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        @if($cartCount > 0)
                            <span class="absolute -top-1.5 -right-2 bg-white text-[#ee4d2d] text-xs font-black px-1.5 py-0.2 rounded-full border-2 border-[#ee4d2d] shadow">
                                {{ $cartCount }}
                            </span>
                        @endif
                    </div>
                    <span class="hidden md:inline text-xs font-bold uppercase tracking-wider">Keranjang</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Notification Toast / Alert -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        @if(session('status'))
            <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between text-sm shadow-sm">
                <span class="font-medium">{{ session('status') }}</span>
                <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">Sukses</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between text-sm shadow-sm">
                <span class="font-medium">{{ session('error') }}</span>
                <span class="text-xs bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded">Peringatan</span>
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

    <!-- Shambat Footer -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-8 text-slate-500 text-xs">
        <div class="max-w-7xl mx-auto px-4 grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
            <div>
                <h4 class="font-bold text-slate-800 mb-2 uppercase text-[11px]">Layanan Pelanggan</h4>
                <ul class="space-y-1">
                    <li>Bantuan Shambat</li>
                    <li>Metode Pembayaran (Midtrans)</li>
                    <li>Lacak Pesanan Shambat Express</li>
                    <li>Gratis Ongkir Xtra</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-slate-800 mb-2 uppercase text-[11px]">Arsitektur Backend</h4>
                <ul class="space-y-1">
                    <li>Distributed Transaction (Saga)</li>
                    <li>Saga Orchestration Pattern</li>
                    <li>Compensating Transaction</li>
                    <li>Transactional Outbox Pattern</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-slate-800 mb-2 uppercase text-[11px]">Simulasi Microservices</h4>
                <ul class="space-y-1">
                    <li>1. OrderService (orders)</li>
                    <li>2. PaymentService (Midtrans)</li>
                    <li>3. InventoryService (products)</li>
                    <li>4. ShippingService (shipments)</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-slate-800 mb-2 uppercase text-[11px]">Aplikasi Shambat</h4>
                <p class="mb-2">Platform E-Commerce Simulasi Transaksi Terdistribusi (Kelompok 2):</p>
                <div class="text-[11px] text-slate-400">Laravel 11 • Midtrans Snap • Saga Orchestrator</div>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 pt-6 border-t border-slate-100 text-center">
            &copy; {{ date('Y') }} Shambat Saga E-Commerce Simulation • Laravel 11 Microservices Architecture
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
