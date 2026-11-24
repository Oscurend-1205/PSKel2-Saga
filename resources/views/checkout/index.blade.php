@extends('layouts.app')

@section('title', 'Checkout Pesanan - Shambat')

@section('content')
<div class="space-y-5">

    <!-- Header + langkah -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 border-b border-slate-200 pb-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Checkout</h1>
            <ol class="flex items-center gap-2 mt-1 text-xs text-slate-400">
                <li>1. Keranjang</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li class="text-[#ee4d2d] font-semibold">2. Checkout</li>
                <li class="w-6 h-px bg-slate-300"></li>
                <li>3. Selesai</li>
            </ol>
        </div>
        <a href="{{ route('cart.index') }}" class="text-xs text-slate-500 hover:text-[#ee4d2d] hover:underline">
            Kembali ke keranjang
        </a>
    </div>

    <form action="{{ route('checkout.store') }}" method="POST" id="checkoutForm">
        @csrf

        <!-- Nilai dari modal skenario -->
        <input type="hidden" name="simulate_inventory_fail" id="simInvInput" value="0">
        <input type="hidden" name="simulate_shipping_fail" id="simShpInput" value="0">
        <input type="hidden" name="auto_process_outbox" id="autoProcInput" value="1">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <!-- Kolom kiri -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Alamat pengiriman -->
                <section class="bg-white border border-slate-200 border-t-2 border-t-[#ee4d2d] rounded-md p-5">
                    <div class="flex items-baseline justify-between mb-3">
                        <h2 class="text-sm font-semibold text-[#ee4d2d]">Alamat Pengiriman</h2>
                        <span class="text-xs text-slate-400">Alamat utama</span>
                    </div>

                    @php
                        $audName = session('audience_name');
                        if ($audName && (stripos($audName, 'budi') !== false || stripos($audName, 'santoso') !== false)) {
                            $audName = null;
                        }
                        $defaultName = $audName ?: '';
                        $defaultEmail = $defaultName ? (\Illuminate\Support\Str::slug($defaultName) . '@audiens.shambat.id') : '';
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="customer_name" class="block text-xs text-slate-600 mb-1">Nama penerima</label>
                            <input type="text" id="customer_name" name="customer_name" required value="{{ old('customer_name', $defaultName) }}"
                                   placeholder="Contoh: Rian Pratama"
                                   class="w-full border border-slate-300 rounded px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-[#ee4d2d]">
                        </div>
                        <div>
                            <label for="customer_email" class="block text-xs text-slate-600 mb-1">Email penerima</label>
                            <input type="email" id="customer_email" name="customer_email" required value="{{ old('customer_email', $defaultEmail) }}"
                                   placeholder="email@example.com"
                                   class="w-full border border-slate-300 rounded px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-[#ee4d2d]">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="shipping_address" class="block text-xs text-slate-600 mb-1">Alamat lengkap</label>
                            <textarea id="shipping_address" name="shipping_address" required rows="2"
                                      class="w-full border border-slate-300 rounded px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-[#ee4d2d]">Jl. Merdeka No. 12, RT 01/RW 02, Gambir, Jakarta Pusat 10110</textarea>
                        </div>
                    </div>
                </section>

                <!-- Produk dipesan -->
                <section class="bg-white border border-slate-200 rounded-md">
                    <div class="flex items-baseline justify-between px-5 py-3 border-b border-slate-100">
                        <h2 class="text-sm font-semibold text-slate-800">Produk Dipesan</h2>
                        <span class="text-xs text-slate-400">{{ count($cart) }} jenis barang</span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach($cart as $item)
                            @php $subtotal = $item['price'] * $item['quantity']; @endphp
                            <div class="px-5 py-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-14 h-14 object-cover rounded border border-slate-200 flex-shrink-0">
                                    <div class="min-w-0">
                                        <h3 class="text-sm text-slate-800 line-clamp-1">{{ $item['name'] }}</h3>
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            {{ $item['quantity'] }} &times; Rp {{ number_format($item['price'], 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-slate-900 whitespace-nowrap">
                                    Rp {{ number_format($subtotal, 0, ',', '.') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-5 py-3 border-t border-dashed border-slate-200 flex items-center justify-between text-xs">
                        <span class="text-slate-600">Pengiriman: SAGA-EXPRESS Reguler</span>
                        <span class="text-emerald-700 font-semibold">Gratis</span>
                    </div>
                </section>
            </div>

            <!-- Kolom kanan -->
            <aside class="lg:sticky lg:top-6 space-y-3">

                <!-- Skenario pengujian -->
                <div class="bg-amber-50 border border-amber-300 rounded-md px-4 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] text-amber-800">Skenario pengujian Saga</div>
                        <div id="currentScenarioBadge" class="text-sm font-semibold text-slate-800 truncate">Normal</div>
                    </div>
                    <button type="button" onclick="openSagaModal()"
                            class="px-3 py-1.5 text-xs font-semibold text-amber-900 border border-amber-400 rounded hover:bg-amber-100 whitespace-nowrap">
                        Ubah
                    </button>
                </div>

                <!-- Rincian pembayaran -->
                <section class="bg-white border border-slate-200 rounded-md p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-slate-800">Rincian Pembayaran</h2>

                    <dl class="space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <dt>Subtotal produk ({{ count($cart) }})</dt>
                            <dd class="text-slate-800">Rp {{ number_format($totalAmount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Ongkos kirim</dt>
                            <dd class="text-slate-800">Rp 0</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Biaya layanan</dt>
                            <dd class="text-slate-800">Rp 0</dd>
                        </div>
                    </dl>

                    <div class="flex items-baseline justify-between pt-3 border-t border-slate-200">
                        <span class="text-sm text-slate-700">Total</span>
                        <span class="text-2xl font-bold text-[#ee4d2d]">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                    </div>

                    <button type="submit" class="w-full py-3 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-sm rounded">
                        Buat Pesanan &amp; Bayar
                    </button>
                    <p class="text-[11px] text-slate-400 text-center">Pembayaran diproses melalui Midtrans Snap (Sandbox).</p>
                </section>
            </aside>

        </div>
    </form>

</div>

<!-- Modal skenario Saga -->
<div id="sagaModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50" onclick="if (event.target === this) closeSagaModal()">
    <div class="bg-white rounded-md max-w-2xl w-full border border-slate-200 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="sagaModalTitle">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <h3 id="sagaModalTitle" class="font-semibold text-slate-800 text-sm">Skenario Pengujian Saga</h3>
            <button type="button" onclick="closeSagaModal()" class="text-xs text-slate-500 hover:text-slate-800 hover:underline">Tutup</button>
        </div>

        <div class="p-5 space-y-4">
            <p class="text-xs text-slate-500">
                Pilih alur normal, atau picu kegagalan untuk melihat compensating transaction (rollback otomatis ke semua service terkait).
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="flex flex-col gap-1 p-3 rounded border border-slate-200 hover:border-[#ee4d2d] cursor-pointer has-[:checked]:border-[#ee4d2d] has-[:checked]:bg-orange-50/40">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="modal_scenario" value="none" checked class="text-[#ee4d2d] focus:ring-0">
                        <span class="text-xs font-semibold text-slate-800">Normal</span>
                    </span>
                    <span class="text-[11px] text-slate-500 leading-relaxed">
                        Bayar PAID, stok direservasi, resi terbit, order COMPLETED.
                    </span>
                </label>

                <label class="flex flex-col gap-1 p-3 rounded border border-slate-200 hover:border-rose-400 cursor-pointer has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/40">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="modal_scenario" value="inventory_fail" class="text-rose-600 focus:ring-0">
                        <span class="text-xs font-semibold text-rose-800">Gagal di inventory</span>
                    </span>
                    <span class="text-[11px] text-slate-500 leading-relaxed">
                        Bayar PAID, inventory gagal. Kompensasi: refund payment, order CANCELED.
                    </span>
                </label>

                <label class="flex flex-col gap-1 p-3 rounded border border-slate-200 hover:border-orange-400 cursor-pointer has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/40">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="modal_scenario" value="shipping_fail" class="text-orange-600 focus:ring-0">
                        <span class="text-xs font-semibold text-orange-800">Gagal di pengiriman</span>
                    </span>
                    <span class="text-[11px] text-slate-500 leading-relaxed">
                        Bayar PAID, stok berkurang, kurir gagal. Kompensasi: release stok, refund, order CANCELED.
                    </span>
                </label>
            </div>

            <label class="flex items-center gap-2 pt-3 border-t border-slate-100 cursor-pointer">
                <input type="checkbox" id="modalAutoProc" checked class="rounded border-slate-300 text-[#ee4d2d] focus:ring-0">
                <span class="text-xs text-slate-700">Eksekusi outbox otomatis (hilangkan centang untuk demo eksekusi manual)</span>
            </label>
        </div>

        <div class="flex justify-end gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50">
            <button type="button" onclick="closeSagaModal()" class="px-4 py-2 text-slate-600 hover:text-slate-900 text-xs font-semibold">
                Batal
            </button>
            <button type="button" onclick="applySagaScenario()" class="px-5 py-2 bg-[#ee4d2d] hover:bg-[#d73211] text-white font-semibold text-xs rounded">
                Terapkan
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const scenarioLabels = {
        none:           { text: 'Normal',              color: 'text-slate-800' },
        inventory_fail: { text: 'Gagal di inventory',  color: 'text-rose-700' },
        shipping_fail:  { text: 'Gagal di pengiriman', color: 'text-orange-700' },
    };

    let appliedScenario = 'none';
    let appliedAutoProc = true;

    function openSagaModal() {
        // Samakan isi modal dengan skenario yang sedang aktif
        document.querySelector(`input[name="modal_scenario"][value="${appliedScenario}"]`).checked = true;
        document.getElementById('modalAutoProc').checked = appliedAutoProc;

        const modal = document.getElementById('sagaModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeSagaModal() {
        const modal = document.getElementById('sagaModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function applySagaScenario() {
        appliedScenario = document.querySelector('input[name="modal_scenario"]:checked').value;
        appliedAutoProc = document.getElementById('modalAutoProc').checked;

        document.getElementById('simInvInput').value = appliedScenario === 'inventory_fail' ? '1' : '0';
        document.getElementById('simShpInput').value = appliedScenario === 'shipping_fail' ? '1' : '0';
        document.getElementById('autoProcInput').value = appliedAutoProc ? '1' : '0';

        const label = scenarioLabels[appliedScenario];
        const badge = document.getElementById('currentScenarioBadge');
        badge.innerText = label.text + (appliedAutoProc ? '' : ', outbox manual');
        badge.className = 'text-sm font-semibold truncate ' + label.color;

        closeSagaModal();
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSagaModal();
    });

    // Auto-fill nama penerima dari localStorage jika form belum terisi
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('customer_name');
        const emailInput = document.getElementById('customer_email');
        const saved = localStorage.getItem('shambat_guest_name');
        if (nameInput && (!nameInput.value || /budi|santoso/i.test(nameInput.value))) {
            if (saved && !/budi|santoso/i.test(saved)) {
                nameInput.value = saved;
                if (emailInput && !emailInput.value) {
                    const slug = saved.toLowerCase().replace(/[^a-z0-9]/g, '');
                    emailInput.value = (slug || 'audiens') + '@audiens.shambat.id';
                }
            } else {
                nameInput.value = '';
            }
        }
    });
</script>
@endpush
@endsection