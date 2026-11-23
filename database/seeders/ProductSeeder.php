<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::truncate();

        $products = [
            [
                'name' => 'Laptop Gaming ROG Zephyrus 16',
                'description' => 'Intel Core i9 14th Gen, RTX 4070 8GB, 32GB RAM DDR5, 1TB NVMe Gen4 SSD.',
                'price' => 24500000,
                'stock' => 8,
                'image_url' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Mechanical Keyboard Wireless RGB Tri-Mode',
                'description' => 'Switch Gateron Yellow Tactile, Hot-swappable, PBT Keycaps, Bluetooth 5.2 / 2.4Ghz.',
                'price' => 899000,
                'stock' => 15,
                'image_url' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Sony Wireless Noise-Cancelling Headphones WH-1000XM5',
                'description' => 'Industry-leading noise cancellation, Hi-Res Audio, 30 jam baterai fast charging.',
                'price' => 4799000,
                'stock' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Smartphone Galaxy Ultra 5G 12/256GB',
                'description' => 'Kamera 200MP Quad Telephoto, Dynamic AMOLED 2X 120Hz, Snapdragon 8 Gen 3.',
                'price' => 18999000,
                'stock' => 6,
                'image_url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Smartwatch Fitness Amoled Sapphire',
                'description' => 'Sensor SpO2, Detak Jantung 24/7, GPS Built-in, Water Resistant 5ATM, Baterai 14 Hari.',
                'price' => 1299000,
                'stock' => 12,
                'image_url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Gaming Mouse Ultralight Wireless 4K Polling',
                'description' => 'Sensor PAW3395 26000 DPI, Berat hanya 49 gram, Optical Switch Omron.',
                'price' => 650000,
                'stock' => 20,
                'image_url' => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Fast Charging Powerbank 20000mAh 65W PD',
                'description' => 'Dukungan charging laptop Type-C Power Delivery, Layar Digital LED Indikator.',
                'price' => 450000,
                'stock' => 25,
                'image_url' => 'https://images.unsplash.com/photo-1609592426867-0c1d27976692?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'TWS Bluetooth 5.3 Earbuds Low Latency',
                'description' => 'Game mode 40ms ultra low latency, Driver Titanium 13mm, Bass mendalam.',
                'price' => 299000,
                'stock' => 2, // Low stock to easily test out-of-stock
                'image_url' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($products as $p) {
            Product::create($p);
        }
    }
}
