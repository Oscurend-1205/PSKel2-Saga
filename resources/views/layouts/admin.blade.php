<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') - Saga & Outbox Monitor</title>
    
    <!-- Shambat Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Top Admin Navigation Bar -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="p-2 bg-indigo-600 rounded-lg text-white font-black tracking-wider text-xs shadow-lg shadow-indigo-500/20">
                    ADMIN
                </span>
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="font-bold text-base text-white tracking-tight hover:text-indigo-400 transition flex items-center gap-2">
                        <span>Panel Monitoring Transaksi Terdistribusi</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-mono">Saga + Outbox</span>
                    </a>
                    <span class="text-[11px] text-slate-400">Modul Terpisah Layanan Microservices (Kelompok 2 Sistem Terdistribusi)</span>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center space-x-3">
                <a href="{{ route('catalog.index') }}" target="_blank" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center gap-1.5">
                    <span>🛍️ Buka Toko Shambat</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between text-xs">
                <span>{{ session('status') }}</span>
                <span class="text-[10px] bg-emerald-500/20 font-bold px-2 py-0.5 rounded">Sukses</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center justify-between text-xs">
                <span>{{ session('error') }}</span>
                <span class="text-[10px] bg-rose-500/20 font-bold px-2 py-0.5 rounded">Gagal</span>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Admin Footer -->
    <footer class="border-t border-slate-800 bg-slate-900/60 py-6 text-center text-xs text-slate-500">
        Panel Admin Terpisah • Sistem Terdistribusi (Saga Orchestration, Outbox Pattern & Midtrans Sandbox) • Kelompok 2
    </footer>

    @stack('scripts')
</body>
</html>

