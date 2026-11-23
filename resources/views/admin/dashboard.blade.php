@extends('layouts.admin')

@section('title', 'Dashboard Monitoring Saga & Outbox')

@section('content')
<div class="space-y-8">
    
    <!-- Top Action Bar & Metrics -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 backdrop-blur flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <h1 class="text-xl font-black text-white">Konsol Pemantauan Transaksi Terdistribusi</h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Visualisasi 4 Microservice Domains, Antrean <code>outbox_messages</code>, dan Eksekusi Saga Orchestrator.
            </p>
        </div>

        <!-- Controls: Manual Outbox Processing & Reset -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Process 1 Message -->
            <form action="{{ route('admin.outbox.process') }}" method="POST">
                @csrf
                <input type="hidden" name="mode" value="next">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-bold text-xs text-white shadow-lg shadow-indigo-600/20 transition flex items-center gap-2">
                    <span>▶️ Proses 1 Event Outbox</span>
                    @if($pendingOutboxCount > 0)
                        <span class="px-2 py-0.5 bg-white/20 rounded-full text-[11px] font-black">{{ $pendingOutboxCount }}</span>
                    @endif
                </button>
            </form>

            <!-- Process All Messages -->
            <form action="{{ route('admin.outbox.process') }}" method="POST">
                @csrf
                <input type="hidden" name="mode" value="all">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold text-xs text-white shadow-lg shadow-emerald-600/20 transition flex items-center gap-2">
                    <span>⚡ Proses Semua Outbox (All)</span>
                </button>
            </form>

            <!-- Reset Demo Data -->
            <form action="{{ route('admin.reset') }}" method="POST" onsubmit="return confirm('Reset seluruh tabel transaksi dan kembalikan stok?')">
                @csrf
                <button type="submit" class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-rose-950/80 border border-slate-700 hover:border-rose-500/50 font-semibold text-xs text-slate-300 hover:text-rose-300 transition">
                    🔄 Reset DB Demo
                </button>
            </form>
        </div>
    </div>

    <!-- System Metrics Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Total Order</span>
            <div class="text-xl font-black text-white mt-1">{{ $orders->count() }}</div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Total Payment</span>
            <div class="text-xl font-black text-emerald-400 mt-1">{{ $payments->where('status', 'PAID')->count() }} <span class="text-xs text-slate-500 font-normal">PAID</span></div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Stok Reservasi</span>
            <div class="text-xl font-black text-indigo-400 mt-1">{{ $reservations->where('status', 'RESERVED')->count() }} <span class="text-xs text-slate-500 font-normal">Aktif</span></div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Resi Kurir</span>
            <div class="text-xl font-black text-cyan-400 mt-1">{{ $shipments->where('status', 'SCHEDULED')->count() }}</div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Outbox Pending</span>
            <div class="text-xl font-black {{ $pendingOutboxCount > 0 ? 'text-amber-400 animate-pulse' : 'text-slate-400' }} mt-1">
                {{ $pendingOutboxCount }}
            </div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5">
            <span class="text-[10px] uppercase font-bold text-slate-400">Total Saga Log</span>
            <div class="text-xl font-black text-purple-400 mt-1">{{ $sagaLogs->count() }}</div>
        </div>
    </div>

    <!-- TRANSACTIONAL OUTBOX TABLE -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                <h2 class="text-base font-bold text-white">
                    Tabel Transactional Outbox (<code>outbox_messages</code>)
                </h2>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-full font-bold {{ $pendingOutboxCount > 0 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-400' }}">
                {{ $pendingOutboxCount }} Pesan Belum Terproses
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider">
                        <th class="py-2.5 px-3">ID</th>
                        <th class="py-2.5 px-3">Aggregate Domain</th>
                        <th class="py-2.5 px-3">Order Code</th>
                        <th class="py-2.5 px-3">Event Type</th>
                        <th class="py-2.5 px-3">Payload Preview</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Waktu Dibuat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($outboxMessages as $msg)
                        <tr class="hover:bg-slate-800/40">
                            <td class="py-2.5 px-3 font-mono font-bold text-slate-400">#{{ $msg->id }}</td>
                            <td class="py-2.5 px-3 font-semibold text-white">{{ $msg->aggregate_type }}</td>
                            <td class="py-2.5 px-3 font-mono text-indigo-400">{{ $msg->aggregate_id }}</td>
                            <td class="py-2.5 px-3">
                                <span class="font-bold px-2 py-0.5 rounded bg-slate-950 text-cyan-300 border border-cyan-500/30 font-mono text-[11px]">
                                    {{ $msg->event_type }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[11px] text-slate-400 max-w-xs truncate" title="{{ json_encode($msg->payload) }}">
                                {{ json_encode($msg->payload) }}
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded font-black text-[10px] {{ $msg->status === 'PROCESSED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : ($msg->status === 'FAILED' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse') }}">
                                    {{ $msg->status }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-slate-500">{{ $msg->created_at->format('H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">Belum ada pesan di tabel outbox. Lakukan checkout di toko untuk memicu event.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SAGA ORCHESTRATION AUDIT LOGS -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                <h2 class="text-base font-bold text-white">
                    Tabel Audit Log Saga (<code>saga_logs</code>)
                </h2>
            </div>
            <span class="text-xs text-slate-400">Jejak Langkah Maju (FORWARD) & Rollback Mundur (COMPENSATING)</span>
        </div>

        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider sticky top-0 bg-slate-900">
                        <th class="py-2.5 px-3">ID</th>
                        <th class="py-2.5 px-3">Order Code</th>
                        <th class="py-2.5 px-3">Action Type</th>
                        <th class="py-2.5 px-3">Step</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Details / Alasan</th>
                        <th class="py-2.5 px-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($sagaLogs as $log)
                        <tr class="hover:bg-slate-800/40 {{ $log->action_type === 'COMPENSATING' ? 'bg-rose-950/20' : '' }}">
                            <td class="py-2.5 px-3 font-mono text-slate-400">#{{ $log->id }}</td>
                            <td class="py-2.5 px-3 font-mono font-bold text-indigo-400">
                                <a href="{{ route('checkout.status', $log->order_code) }}" target="_blank" class="underline hover:text-indigo-300">
                                    {{ $log->order_code }}
                                </a>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black {{ $log->action_type === 'COMPENSATING' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' }}">
                                    {{ $log->action_type }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-white">{{ $log->saga_step }}</td>
                            <td class="py-2.5 px-3">
                                <span class="font-bold {{ $log->status === 'SUCCESS' ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[11px] text-slate-300 max-w-sm truncate" title="{{ json_encode($log->details) }}">
                                {{ json_encode($log->details) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-500">{{ $log->created_at->format('H:i:s.v') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">Belum ada riwayat eksekusi Saga.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4 MICROSERVICES DOMAIN TABLES (No Foreign Keys between them) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Orders -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
            <h3 class="font-bold text-sm text-white flex items-center justify-between">
                <span>📦 1. Order Domain (<code>orders</code>)</span>
                <span class="text-[11px] text-slate-400 font-normal">app/Services/OrderService.php</span>
            </h3>
            <div class="overflow-x-auto max-h-56">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px]">
                            <th class="py-1.5 px-2">Order Code</th>
                            <th class="py-1.5 px-2">Customer</th>
                            <th class="py-1.5 px-2">Total</th>
                            <th class="py-1.5 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach($orders as $o)
                            <tr>
                                <td class="py-1.5 px-2 font-mono text-indigo-400">{{ $o->order_code }}</td>
                                <td class="py-1.5 px-2 text-slate-300">{{ $o->customer_name }}</td>
                                <td class="py-1.5 px-2">Rp {{ number_format($o->total_amount, 0, ',', '.') }}</td>
                                <td class="py-1.5 px-2 font-bold {{ $o->status === 'COMPLETED' ? 'text-emerald-400' : ($o->status === 'CANCELED' ? 'text-rose-400' : 'text-amber-400') }}">
                                    {{ $o->status }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Payments -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
            <h3 class="font-bold text-sm text-white flex items-center justify-between">
                <span>💳 2. Payment Domain (<code>payments</code>)</span>
                <span class="text-[11px] text-slate-400 font-normal">app/Services/PaymentService.php</span>
            </h3>
            <div class="overflow-x-auto max-h-56">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px]">
                            <th class="py-1.5 px-2">Order Code</th>
                            <th class="py-1.5 px-2">TRX ID</th>
                            <th class="py-1.5 px-2">Amount</th>
                            <th class="py-1.5 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach($payments as $p)
                            <tr>
                                <td class="py-1.5 px-2 font-mono text-indigo-400">{{ $p->order_code }}</td>
                                <td class="py-1.5 px-2 font-mono text-[11px] text-slate-400">{{ $p->transaction_id ?? '-' }}</td>
                                <td class="py-1.5 px-2">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                <td class="py-1.5 px-2 font-bold {{ $p->status === 'PAID' ? 'text-emerald-400' : ($p->status === 'REFUNDED' ? 'text-purple-400' : 'text-amber-400') }}">
                                    {{ $p->status }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Inventory -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
            <h3 class="font-bold text-sm text-white flex items-center justify-between">
                <span>🏭 3. Inventory Domain (<code>products</code> & <code>stock_reservations</code>)</span>
                <span class="text-[11px] text-slate-400 font-normal">app/Services/InventoryService.php</span>
            </h3>
            <div class="overflow-x-auto max-h-56">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px]">
                            <th class="py-1.5 px-2">Produk</th>
                            <th class="py-1.5 px-2">Sisa Stok</th>
                            <th class="py-1.5 px-2">Reservasi Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach($products as $prod)
                            <tr>
                                <td class="py-1.5 px-2 text-white font-medium">{{ $prod->name }}</td>
                                <td class="py-1.5 px-2 font-bold text-indigo-300">{{ $prod->stock }} unit</td>
                                <td class="py-1.5 px-2 text-slate-400 font-mono text-[11px]">
                                    @php $lastRes = $reservations->where('product_id', $prod->id)->first(); @endphp
                                    {{ $lastRes ? $lastRes->order_code . ' (' . $lastRes->status . ')' : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Shipping -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
            <h3 class="font-bold text-sm text-white flex items-center justify-between">
                <span>🚚 4. Shipping Domain (<code>shipments</code>)</span>
                <span class="text-[11px] text-slate-400 font-normal">app/Services/ShippingService.php</span>
            </h3>
            <div class="overflow-x-auto max-h-56">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px]">
                            <th class="py-1.5 px-2">Order Code</th>
                            <th class="py-1.5 px-2">No Resi</th>
                            <th class="py-1.5 px-2">Kurir</th>
                            <th class="py-1.5 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach($shipments as $s)
                            <tr>
                                <td class="py-1.5 px-2 font-mono text-indigo-400">{{ $s->order_code }}</td>
                                <td class="py-1.5 px-2 font-mono text-[11px] text-slate-300">{{ $s->tracking_number }}</td>
                                <td class="py-1.5 px-2 text-slate-400">{{ $s->courier_name }}</td>
                                <td class="py-1.5 px-2 font-bold {{ $s->status === 'SCHEDULED' ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $s->status }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
