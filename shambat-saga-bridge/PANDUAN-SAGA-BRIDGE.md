# Saga Bridge: presentasi yang mengendalikan Shambat secara langsung

Saga Bridge adalah modul terpisah yang menghubungkan file presentasi dengan aplikasi Shambat. Saat aktif, setiap slide bisa menjalankan transaksi sungguhan di Shambat, lalu menampilkan perjalanan saga, isi tabel outbox, stok, dan status pesanan langsung dari database. Saat mati, presentasi kembali seperti biasa dan aplikasi berjalan persis seperti sebelumnya.

Ada dua saklar yang saling melengkapi. Saklar server (`php artisan saga-bridge on|off`) membuka atau menutup semua endpoint bridge. Saklar presentasi (tombol **L**) menyalakan atau mematikan lapisan live di slide; saat mati, presentasi tidak mengirim satu request pun.

---

## 1. Pemasangan (sekali saja)

1. Salin folder `app/SagaBridge` dan `resources/saga-bridge` dari paket ini ke root proyek Shambat.
2. Daftarkan provider di `bootstrap/providers.php` (atau timpa dengan file dari paket ini):

   ```php
   return [
       App\Providers\AppServiceProvider::class,
       App\SagaBridge\SagaBridgeServiceProvider::class,
   ];
   ```

3. Tambahkan ke `.env` dan `.env.example` (opsional, nilai bawaan sudah aman):

   ```env
   # true = boleh diaktifkan. Bawaan: hanya di luar APP_ENV=production.
   SAGA_BRIDGE_ALLOWED=true
   # Kosongkan agar IP WiFi laptop terdeteksi otomatis untuk QR audiens.
   SAGA_BRIDGE_AUDIENCE_URL=
   ```

4. Kalau sebelumnya pernah menjalankan `php artisan config:cache`, jalankan `php artisan config:clear`.

Tidak ada migrasi dan tidak ada file aplikasi lain yang diubah. Status bridge disimpan di `storage/app/saga-bridge.json` (sudah di-ignore oleh git).

## 2. Menjalankan saat presentasi

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan saga-bridge on
```

Perintah kedua mencetak kunci dan tautan, misalnya:

```
  Kunci        : 7KQ2-M9XP
  Presentasi   : http://127.0.0.1:8000/presentasi?key=7KQ2-M9XP
  Lewat WiFi   : http://192.168.1.5:8000/presentasi?key=7KQ2-M9XP
  Toko (QR)    : http://192.168.1.5:8000
```

Buka tautan **Presentasi** di browser, tekan **F** untuk layar penuh. Live langsung menyala dan lampu di tombol "Live" berubah hijau. Laptop dan HP audiens harus berada di WiFi yang sama agar QR bisa dipakai.

Perintah lain:

| Perintah | Fungsi |
|---|---|
| `php artisan saga-bridge status` | Lihat status, kunci, dan tautan |
| `php artisan saga-bridge off` | Tutup semua endpoint bridge; relay outbox kembali normal |
| `php artisan saga-bridge on --new-key` | Aktifkan dengan kunci baru |
| `php artisan saga-bridge on --url=http://192.168.1.5:8000` | Paksa alamat toko untuk QR |

Mematikan dan menyalakan lagi saat presentasi terbuka itu aman: lampu berubah merah, lalu otomatis hijau lagi tanpa perlu memuat ulang halaman.

Presentasi juga bisa dibuka langsung sebagai file (`presentasi-saga-outbox.html`). Tekan **L**, isi alamat server dan kunci di panel Koneksi. Cara lewat `/presentasi` tetap lebih mulus karena panel web langsung (W) bisa login dan checkout di origin yang sama.

## 3. Pintasan keyboard

| Tombol | Aksi |
|---|---|
| **L** | Nyalakan / matikan live |
| **G** | Jalankan aksi khusus untuk slide yang sedang tampil |
| **1** / **2** / **3** / **4** | Skenario: sukses, gagal stok, gagal kirim, unit terakhir |
| **T** | Buka / tutup panggung saga |
| **Y** | Ganti tampilan panggung: alur saga atau tabel outbox asli |
| **Q** | Layar audiens dengan QR toko |
| **W** | Layar terbagi: slide di kiri, aplikasi Shambat di kanan |
| **N** | Relay mengirim tepat satu event dari outbox |
| **Shift+N** | Hidupkan relay (dari mode Manual) atau kirim semua event |
| **M** | Ganti mode relay: Sinematik, Manual, Instan |
| **D** | Sematkan panel kontrol |
| **Shift+R** (dua kali) | Kosongkan data demo dan isi ulang stok |
| **Esc** | Tutup lapisan yang paling atas |

Panel kontrol muncul di kiri bawah saat mouse digerakkan, sama seperti bilah navigasi bawaan.

## 4. Mode relay outbox

- **Sinematik** (bawaan): relay ditahan di server, lalu presentasi mengirim satu event setiap kali animasi sebelumnya selesai. Hasilnya saga berjalan dengan tempo yang bisa diikuti mata, tetapi tetap 100% dieksekusi oleh kode Shambat.
- **Manual**: relay ditahan dan event baru dikirim saat kamu menekan **N**. Cocok untuk menjelaskan langkah demi langkah dan untuk mensimulasikan "relay mati".
- **Instan**: relay berjalan normal seperti aplikasi aslinya.

Penahanan relay memakai sistem sewa (lease) 15 detik yang diperpanjang setiap detik oleh presentasi. Kalau browser tertutup atau laptop tidur, relay otomatis berjalan normal lagi, jadi toko tidak pernah macet.

## 5. Naskah demo per slide

Di slide yang punya aksi, kapsul di kiri bawah menampilkan "Coba di Shambat: …". Tekan **G**.

| Slide | Yang terjadi saat menekan G | Poin yang bisa diucapkan |
|---|---|---|
| 1 | Layar audiens dengan QR | "Silakan buka toko kami di HP. Pesanan kalian akan muncul di layar sepanjang presentasi." |
| 3 | Checkout yang gagal di kurir | "Ini masalah di slide tadi, tapi sungguhan. Lihat stok yang sudah terpotong kembali lagi." |
| 5 | Dua pembeli berebut unit terakhir | "Isolation: `lockForUpdate` memastikan hanya satu yang dapat. Yang kalah dikompensasi dan dananya dikembalikan." |
| 8 | Saga gagal di tahap stok | "Kompensasi berjalan mundur, dicatat di saga_logs." |
| 12, 14, 22, 25 | Happy path | "Orkestrator menerima event dari outbox dan memberi perintah ke tiap layanan." |
| 15 | Failure path (gagal kirim) | "Urutan kompensasi terbalik: lepas stok, kembalikan dana, batalkan pesanan." |
| 18 | Relay ditahan + satu pesanan | "Pesanan dan event sudah tersimpan dalam satu transaksi. Event menunggu relay." Tekan **N** beberapa kali. |
| 19 | Tabel `outbox_messages` asli | "Ini isi tabelnya sekarang. Perhatikan status dan processed_at berubah saat relay bekerja." |
| 23 | Halaman status pesanan asli (layar terbagi) | "Pembeli melihat status yang bergerak, bukan berhasil instan." |
| 24 | Dashboard admin asli (layar terbagi) | "Kode Laravel di slide ini yang sedang berjalan di kanan." |
| 26 | Relay ditahan + tiga pesanan menumpuk | Angka backlog di slide ini berubah menjadi angka asli dari Shambat. Tekan **Shift+N**: "Relay hidup lagi, backlog terkirim berurutan." |
| 28 | Toko di layar terbagi | "Pesan dari web, lihat sagamu di layar." |
| 32 | Rekap pesanan audiens | Tutup dengan jumlah pesanan audiens yang sukses dan yang dikompensasi. |

Slide lain juga bisa diberi aksi dengan menambahkan atribut, misalnya `<section class="slide" data-live="gagal_stok">`.

## 6. Skenario

| Skenario | Yang dilakukan bridge | Yang terlihat |
|---|---|---|
| Sukses (1) | Membuat dan membayar satu pesanan lewat `OrderService` dan `PaymentService` | Semua langkah hijau sampai Selesai |
| Gagal stok (2) | Sama, dengan `simulate_inventory_fail` | Stok gagal, dana dikembalikan, pesanan dibatalkan |
| Gagal kirim (3) | Sama, dengan `simulate_shipping_fail` | Stok sempat berkurang lalu kembali, dana dikembalikan |
| Unit terakhir (4) | Menyisakan 1 unit pada produk dengan stok terkecil, lalu dua pembeli membayar | Satu selesai, satu dikompensasi karena stok tidak cukup (bukan simulasi) |

Pesanan buatan presenter memakai email `@saga-bridge.demo`, sehingga pesanan audiens bisa dihitung terpisah.

## 7. Jika ada kendala

| Gejala | Penyebab dan solusi |
|---|---|
| Lampu merah, "Bridge nonaktif di server" | Jalankan `php artisan saga-bridge on`. |
| "Kunci salah" | Lihat kunci dengan `php artisan saga-bridge status`, isi di panel Koneksi. |
| "Modul belum terpasang" (404) | Provider belum didaftarkan, `SAGA_BRIDGE_ALLOWED=false`, atau `APP_ENV=production`. Cek `.env` lalu `php artisan config:clear`. |
| HP audiens tidak bisa membuka toko | Server harus dijalankan dengan `--host=0.0.0.0`, laptop dan HP di WiFi yang sama, dan firewall mengizinkan port 8000. Bisa juga atur alamat dengan `--url=`. |
| QR tidak muncul | Pustaka QR dimuat dari internet. Tanpa internet, layar menampilkan alamat untuk diketik. |
| Panel web kosong atau tidak bisa checkout | Buka presentasi lewat `/presentasi`, bukan sebagai file. |
| Pesanan audiens berhenti di "Diproses" | Relay sedang ditahan (mode Manual). Tekan **Shift+N**, atau matikan live. |

## 8. Mencabut modul

Hapus folder `app/SagaBridge` dan `resources/saga-bridge`, lalu hapus baris `App\SagaBridge\SagaBridgeServiceProvider::class` di `bootstrap/providers.php`. Untuk presentasi, hapus blok `SHAMBAT LIVE` di akhir file HTML (ditandai komentar awal dan akhir).

## 9. Cara kerjanya secara singkat

- `SagaBridgeServiceProvider` mendaftarkan rute `/presentasi` dan `/saga-bridge/api/*`, perintah `saga-bridge`, dan mengganti `OutboxProcessor` dengan turunannya, `PausableOutboxProcessor`. Turunan ini hanya menolak memproses outbox saat presentasi sedang menahan relay; di luar itu perilakunya identik.
- `BridgeGuard` menolak semua request saat bridge mati (503) atau kuncinya salah (401), dan menambahkan header CORS agar file presentasi lokal tetap bisa terhubung.
- Presentasi membaca `/saga-bridge/api/feed` setiap detik. Setiap baris baru di `saga_logs` diterjemahkan menjadi adegan di panggung: titik bergerak dari toko ke layanan, dari layanan ke outbox, dari outbox ke orkestrator, dan merah saat kompensasi.
- Catatan: log `FAILED` yang ditulis di dalam `DB::transaction` milik `InventoryService` dan `ShippingService` ikut ter-rollback saat exception dilempar. Panggung tetap menampilkan kegagalan itu dari log kompensasi orkestrator (`failed_at_step`).
