<?php

namespace App\SagaBridge\Support;

use App\Models\Product;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Skenario demo yang dijalankan dari presentasi. Semuanya memakai service asli
 * aplikasi (OrderService, PaymentService, orkestrator saga), jadi yang tampil
 * di layar adalah perilaku Shambat yang sesungguhnya.
 */
class Scenarios
{
    /** Domain email penanda pesanan buatan presenter (bukan dari audiens). */
    public const EMAIL_DOMAIN = '@saga-bridge.demo';

    public const TYPES = ['sukses', 'gagal_stok', 'gagal_kirim', 'rebutan'];

    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
    ) {
    }

    /**
     * @return array{orders: string[], note: string}
     */
    public function run(string $type, int $count = 1): array
    {
        $count = max(1, min(5, $count));

        switch ($type) {
            case 'sukses':
                $codes = [];
                for ($i = 0; $i < $count; $i++) {
                    $codes[] = $this->checkout($count > 1 ? 'Presenter '.($i + 1) : 'Presenter');
                }

                return [
                    'orders' => $codes,
                    'note' => $count > 1
                        ? "{$count} pesanan dibuat dan dibayar."
                        : 'Pesanan dibuat dan dibayar. Saga dimulai.',
                ];

            case 'gagal_stok':
                return [
                    'orders' => [$this->checkout('Presenter', ['simulate_inventory_fail' => true])],
                    'note' => 'Pesanan dibayar, tetapi gudang akan gagal mereservasi stok.',
                ];

            case 'gagal_kirim':
                return [
                    'orders' => [$this->checkout('Presenter', ['simulate_shipping_fail' => true])],
                    'note' => 'Pesanan dibayar, stok akan direservasi, lalu kurir menolak.',
                ];

            case 'rebutan':
                return $this->lastUnitRace();
        }

        throw new InvalidArgumentException("Skenario tidak dikenal: {$type}");
    }

    private function lastUnitRace(): array
    {
        $product = Product::where('stock', '>', 0)->orderBy('stock')->orderBy('id')->first();

        if (! $product) {
            throw new RuntimeException('Semua stok habis. Reset data demo dulu (Shift+R).');
        }

        // Sisakan tepat satu unit agar "unit terakhir" benar-benar terjadi.
        $product->stock = 1;
        $product->save();

        $first = $this->checkout('Pembeli A', [], $product->fresh(), 1);
        $second = $this->checkout('Pembeli B', [], $product->fresh(), 1);

        return [
            'orders' => [$first, $second],
            'note' => "Stok {$product->name} disisakan 1 unit. Dua pembeli sudah membayar, hanya satu yang akan mendapatkannya.",
        ];
    }

    private function checkout(string $buyer, array $flags = [], ?Product $product = null, ?int $quantity = null): string
    {
        $product ??= Product::where('stock', '>', 0)->inRandomOrder()->first() ?? Product::first();

        if (! $product) {
            throw new RuntimeException('Belum ada produk. Reset data demo dulu (Shift+R).');
        }

        $quantity ??= $product->stock >= 4 ? 2 : 1;
        $price = (float) $product->price;
        $total = $price * $quantity;
        $code = 'ORD-'.strtoupper(uniqid());
        $failStock = (bool) ($flags['simulate_inventory_fail'] ?? false);
        $failShip = (bool) ($flags['simulate_shipping_fail'] ?? false);

        // Langkah 1: pesanan + event OrderCreated dalam satu transaksi lokal (outbox).
        $this->orders->createOrder([
            'order_code' => $code,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $total,
            ]],
            'total_amount' => $total,
            'customer_name' => $buyer,
            'customer_email' => Str::slug($buyer).self::EMAIL_DOMAIN,
            'shipping_address' => 'Panggung presentasi Kelompok 2',
            'simulate_inventory_fail' => $failStock,
            'simulate_shipping_fail' => $failShip,
        ]);

        $this->payments->createPaymentRecord($code, $total, 'SAGA-BRIDGE-'.strtoupper(Str::random(8)));

        // Langkah 2: pembayaran sukses, seperti callback Midtrans (PaymentCompleted ke outbox).
        $this->payments->markAsPaid($code, [
            'gross_amount' => $total,
            'payment_type' => 'saga_bridge_demo',
            'transaction_id' => 'BRIDGE-TRX-'.strtoupper(Str::random(12)),
            'simulate_inventory_fail' => $failStock,
            'simulate_shipping_fail' => $failShip,
        ]);

        return $code;
    }
}
