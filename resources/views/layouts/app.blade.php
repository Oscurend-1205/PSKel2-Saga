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
    @php
        $audName = session('audience_name');
        if ($audName && (stripos($audName, 'budi') !== false || stripos($audName, 'santoso') !== false)) {
            session()->forget('audience_name');
            $audName = null;
        }
    @endphp

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
                @if($audName)
                    <span class="font-bold flex items-center gap-1 bg-[#b52a0d] px-2.5 py-0.5 rounded text-[11px]">
                        👤 {{ $audName }}
                        <a href="{{ route('guest.logout') }}" class="underline opacity-80 hover:opacity-100 ml-1 text-[10px]" title="Keluar / Ganti nama">(Ganti)</a>
                    </span>
                @else
                    <button type="button" onclick="openGuestModal()" class="font-bold underline text-yellow-200 hover:text-white flex items-center gap-1 bg-[#b52a0d] px-2.5 py-0.5 rounded text-[11px] animate-pulse">
                        👤 Masuk / Isi Nama
                    </button>
                @endif
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
                <a href="{{ route('orders.index') }}" class="relative p-2 text-white hover:opacity-90 transition flex items-center gap-2">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <span class="hidden md:inline text-xs font-bold uppercase tracking-wider">Pesanan</span>
                </a>
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

        @if(session('info'))
            <div class="p-3.5 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 flex items-center justify-between text-sm shadow-sm">
                <span class="font-medium">{{ session('info') }}</span>
                <span class="text-xs bg-sky-100 text-sky-800 font-bold px-2 py-0.5 rounded">Info</span>
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

    <!-- Modal Masuk dengan Nama (Tanpa Password) -->
    <div id="guestLoginModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 {{ $audName ? 'hidden' : '' }}">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative border-t-4 border-[#ee4d2d] transform transition-all">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-orange-100 text-[#ee4d2d] rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3 shadow-inner">
                    👋
                </div>
                <h3 class="text-xl font-bold text-slate-800">Selamat Datang di Shambat!</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Silakan masukkan nama Anda untuk simulasi transaksi Saga di layar proyektor presentasi:
                </p>
            </div>

            <form action="{{ route('guest.login') }}" method="POST" onsubmit="saveGuestToLocal()">
                @csrf
                <div class="mb-4">
                    <label for="guest_name_input" class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wider">Nama Anda</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">👤</span>
                        <input type="text" id="guest_name_input" name="name" required minlength="2" maxlength="40"
                               placeholder="Nama Anda (contoh: Rian, Putri, Dosen)"
                               value="{{ $audName ?: '' }}"
                               class="w-full border border-slate-300 rounded-xl pl-10 pr-4 py-2.5 text-sm font-medium text-slate-800 focus:outline-none focus:border-[#ee4d2d] focus:ring-2 focus:ring-[#ee4d2d]/20 transition">
                    </div>
                    <span class="text-[11px] text-slate-400 mt-1 block">Tanpa password! Nama ini akan langsung terhubung ke layar presentasi saat Anda belanja.</span>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-bold py-2.5 px-4 rounded-xl text-sm transition shadow-md hover:shadow-lg">
                        Mulai Belanja 🛍️
                    </button>
                    <button type="button" onclick="closeGuestModal()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-500 hover:bg-slate-50 transition">
                        Nanti
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openGuestModal() {
            const m = document.getElementById('guestLoginModal');
            if (m) {
                m.classList.remove('hidden');
                setTimeout(() => document.getElementById('guest_name_input')?.focus(), 100);
            }
        }
        function closeGuestModal() {
            const m = document.getElementById('guestLoginModal');
            if (m) m.classList.add('hidden');
        }
        function saveGuestToLocal() {
            const inp = document.getElementById('guest_name_input');
            if (inp && inp.value.trim()) {
                localStorage.setItem('shambat_guest_name', inp.value.trim());
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const currentName = @json($audName);
            const localName = localStorage.getItem('shambat_guest_name');
            if (localName && /budi|santoso/i.test(localName)) {
                localStorage.removeItem('shambat_guest_name');
            }

            // Jika belum ada nama di session server, auto open modal
            if (!currentName) {
                const cleanLocal = localStorage.getItem('shambat_guest_name');
                if (cleanLocal) {
                    const inp = document.getElementById('guest_name_input');
                    if (inp) inp.value = cleanLocal;
                }
                openGuestModal();
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
