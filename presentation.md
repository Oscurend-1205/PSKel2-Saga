

## Page 01
## Sistem
## Terdistribusi
## DISTRIBUTED
## TRANSACTION, SAGA, DAN
## TRANSACTIONAL OUTBOX
kelompok 2
## -

## Page 02
## Sistem
## Terdistribusi
Meet the Team
1.Melany Kresna Putri 24102015
2.Devia Amanda Prameswari 24102007
3.M. Auril Aditya Narendra 24102018
4.Audina Ike Wati 24102028
5.Viqro Tunada Hanunnisa 24102022
6.Syani Carissa Syawaluna 24102038
7.Fichri Aditya Nugraha 24102010

## Page 03
## Sistem
## Terdistribusi
Pembeli menekan Bayar
di aplikasi mobile
## CHECKOUT BERHASIL SEPARUH: STOK SUDAH TERPOTONG,
## PEMBAYARAN GAGAL, PESANAN MENGGANTUNG
## Layanan Pesanan
menyimpan
pesanan. Berhasil
## Layanan Stok
mengurangi 2 unit
## Berhasil
## Layanan Pembayaran
menolak. Saldo kurang
Pertanyaan untuk kelas
Siapa yang mengembalkan 2 unit stok
tadi?
Bisakah layanan Pesanan me-rollback
database milik layanan Stok?
Bagaimana layanan lain pasti tahu bahwa
pesanan sudah dibuat?
Akar masalah
Satu proses bisnis menyentuh beberapa layanan
yang masing-masing punya database sendiri.
Tidak ada satu transaksi pun yang bisa
membungkus semuanya, sehingga kegagalan di
tengah jalan meninggalkan data setengah jadi

## Page 04
## Sistem
## Terdistribusi
Menjelaskan mengapa transaksi lokal (ACID) tidak cukup ketika
satu proses menyentuh beberapa layanan
## SETELAH 60 MENIT, KITA MAMPU MERANCANG PROSES LINTAS
## LAYANAN YANG TETAP KONSISTEN
Membedakan saga koreografi dan orkestrasi serta konsep
transaksi kompensasi
Menjelaskan transactional outbox untuk mencegah data
tersimpan tanpa event
Merancang alur pesanan, stok, pembayaran, dan pengiriman

## Atomicity
Semua operasi berhasil,
atau tidak ada yang
berhasil
Contoh: Pesanan & stok
tersimpan bersama, atau
keduanya batal
## Consistency
Data berpindah dari satu
status valid ke status
valid lain
Contoh: Stok tidak pernah
bernilai negatif
## Page 05
## Sistem
## Terditribusi
## DI DALAM SATU DATABASE, TRANSAKSI DIJAMIN ACID: SEMUA
## BERHASIL ATAU TIDAK SAMA SEKALI
## Isolation
Transaksi bersamaan
memberi hasil sama
seperti dijalankan
berurutan
Contoh: Dua pembeli tidak
mendapat unit terakhir yang
sama
## Durability
Perubahan tetap
tersimpan setelah
commit, meski terjadi
kegagalan
Contoh: Server mati setelah
COMMIT, pesanan tetap ada

PesananPesananPesananPesanan
## DB
## Page 06
## Sistem
## Terdistribusi
Monolith: satu database
## DI MICROSERVICES, TIAP LAYANAN PUNYA DATABASE SENDIRI, SEHINGGA
## ACID TIDAK LAGI MENYEBRANG
Satu transaksi membungkus semuanya.
Gagal dimanapun, semuanya dibatalkan
otomatis.
Microservices: database per layanan
## DBDBDB
Data terenkapitulasi, skema
bebas, skala mandiri,,
kegagalan terisolasi
## Manfaat
Jaminan ACID tidak berlaku
lintas penyimpanan data yang
dikelola terpisah
## Konsekuensi

## Page 07
## Sistem
## Terdistribusi
Mengapa kurang cocok untuk microservices
## TWO-PHASE COMMIT MENGUNCI SEMUA PESERTA SAMPAI
## KOORDINATOR MEMUTUSKAN
Fase 1: “siap commit?” - semua mengunci data
fase 2: semua “siap” - COMMIT; satu “tidak” - ABORT
## Koordinator
StokPesananPembayaran
Semua peserta harus aktif pada saat yang sama.
Data terkunci selama menunggu keputusan,
sehingga respons melambat
Koordinator yang mati membuat peserta
menggantung
Layanan jadi saling terikat erat, padahal tujuan
microservices justru longgar

## Page 08
## Sistem
## Terdistribusi
## SAGA MENCEGAH SATU PROSES MENJADI URUTAN TRANSAKSI
## LOKAL, DAN MEMBATALKANNYA DENGAN KOMPENSASI
Setiap transaksi lokal..
Menyelesaikan pekerjaannya secara atomik di satu
layanan
Memperbarui database milik layanan itu
Memicu langkah berikutnya lewat event atau pesan
T1 Buat pesananT2 Reservasi stokT3 Tagih pembayaranT4 Buat pengiriman
C1 Batalkan pesananC2 Lepas stok
Konsistensi tercapai pada akhirnya (eventual).
bukan seketika. Di antara langkah, data boleh
sementara berbeda antarlayanan
## Konsekuensinya

## Page 09
## Sistem
## Terdistribusi
## TIAP LANGKAH SAGA PUNYA PERAN: BISA DIKOMPENSASI,
## TITIK TANPA KEMBALI, ATAU BISA DIULANG
Buat PesananReservasi stokTagih PembayaranKirim & notifikasi
KompensabelKompensabelPivotBisa diulang
Transaksi kompensabelTransaksi pivotTransaksi yang bisa diulang
Bisa  dibatalkan  oleh  transaksi  lain
dengan   efek   berlawanan.   Contoh:
reservasi  stok  dibalik  dengan  lepas
reservasi.
Titik  tanpa  jalan  kembali.  Setelah
berhasil, kompensasi tidak lagi relevan;
langkah berikutnya wajib diselesaikan.
Datang  setelah  pivot  dan  bersifat
idempoten,   sehingga   saga   tetap
mencapai  status  akhir  walau  ada
gangguan sementara.

LangkahAksi (maju)Kompensasi (mundur)
PesananSimpan pesanan, status MENUNGGUUbah status menjadi DIBATALKAN (tidak dihapus)
StokReservasi 2 unitLepas reservasi 2 unit
Pembayaran (pivot)Tagih Rp150.000Tidak dikompensasi otomatis; setelah berhasil, saga wajib maju
PengirimanBuat resi pengirimanDiulang sampai berhasil (idempoten)
## Page 10
## KOMPENSASI BUKAN ROLLBACK: DATANYA SUDAH COMMIT, JADI KITA
## MENULIS TRANSAKSI BARU YANG MEMBALIK EFEK BISNISNYA
## Sistem
## Terdistribusi

## Page 11
## KOREOGRAFI:
## TIAP LAYANAN MENDENGAR EVENT LALU MEMUTUSKAN
## SENDIRI LANGKAH BERIKUTNYA
## Sistem
## Terdistribusi

## Page 12
## ORKESTRASI:
## SATU  ORKESTRATOR  MEMBERI  PERINTAH,  MENYIMPAN  STATUS,
## DAN MENJALANKAN KOMPENSASI
## Sistem
## Terdistribusi

AspekKoreografiOrkestrasi
PengendaliTidak ada; layanan saling bereaksi pada eventSatu orkestrator mengatur alur
Cocok untukAlur sederhana, sedikit layanan
Alur kompleks atau sering
ditambah layanan baru
Titik gagal tunggalTidak adaOrkestrator (perlu dibuat andal)
Melacak alurSulit; alur tersebar di banyak layananMudah; status ada di satu tempat
RisikoKetergantungan siklik, uji integrasi sulit
Logika koordinasi menambah
kompleksitas desain
## Page 13
## KOREOGRAFI COCOK UNTUK ALUR SEDERHANA;
## ORKESTRASI UNTUK ALUR KOMPLEKS YANG SERING BERTAMBAH
## Sistem
## Terdistribusi

## Page 14
## HAPPY  PATH:  APLIKASI  LANGSUNG  MENDAPAT  202,  SAGA
## BERJALAN DI BELAKANG SAMPAI PESANAN SELESAI
## Sistem
## Terdistribusi

## Page 15
## FAILURE   PATH:   PEMBAYARAN   DITOLAK,   ORKESTRATOR
## MENJALANKAN KOMPENSASI DENGAN URUTAN TERBALIK
## Sistem
## Terdistribusi

## Page 16
## TANPA  ISOLASI  ANTARLAYANAN,  SAGA  BISA  MEMUNCULKAN
## ANOMALI DATA YANG PERLU DIANTISIPASI
## Penangkal
## Sistem
## Terdistribusi

## Page 17
## MASALAH DUAL-WRITE:
## MENYIMPAN  KE  DATABASE  DAN  MENGIRIM  EVENT  ADALAH  DUA
## OPERASI YANG BISA GAGAL TERPISAH

Simpan  pesanan  dan  event  PesananDibuat  dalam
satu COMMIT.
Relay  mengirim  ke  broker,  lalu  menandai  baris
sebagai terkirim.
Relay membaca baris outbox yang belum terkirim,
urut sesuai nomor.
Broker   mengantar   event   ke   layanan   yang
berlangganan.
## Page 18
## TRANSACTIONAL  OUTBOX:  SIMPAN  DATA  DAN  EVENT  DI
## DATABASE YANG SAMA, DALAM SATU TRANSAKSI LOKAL
## Sistem
## Terdistribusi

## Page 19
## TABEL  OUTBOX  CUKUP  SEDERHANA:  SIAPA,  EVENT  APA,  ISINYA,
## DAN KAPAN TERKIRIM
## Sistem
## Terdistribusi

## Page 20
## 2 CARA MEMINDAHKAN EVENT KELUAR:
Change Data Capture (CDC)
Database  sendiri  menerbitkan  setiap  perubahan
data ke sebuah stream; relay membaca stream itu.
## Kelebihan
Tidak perlu tabel tambahan
Perubahan tertangkap hampir
seketika
## Kekurangan
Bergantung pada fitur
database (mis. DynamoDB
## Streams)
Lebih sulit disiapkan untuk
proyek kecil
Relay  membaca  tabel  outbox  secara  berkala,
misalnya  tiap  beberapa  detik,  lalu  mengirim  ke
antrean.
Tabel outbox + polling
## Kelebihan
Mudah dipahami dan
dipasang di
MySQL/PostgreSQL
Urutan terjaga lewat id dan
stempel waktu
Ada jeda sesuai interval
polling
Menambah beban baca ke
database
## Kekurangan
## Sistem
## Terdistribusi

## Page 21
## Pesan Duplikat
Relay  bisa  mengirim  ulang.  Consumer  harus
idempoten:  catat  id  pesan  yang  sudah  diproses
dan abaikan yang sama.
## OUTBOX MENJAMIN EVENT TIDAK HILANG, TETAPI
## EVENT BISA TERKIRIM LEBIH DARI SEKALI
Jangan pernah mengirim event dari transaksi yang
dibatalkan. Outbox otomatis mencegahnya.
Kirim event dalam urutan yang sama dengan urutan
perubahan  database.  Gunakan  nomor  urut  atau
antrean FIFO.
Outbox hanya menjaga satu layanan. Untuk proses
lintas layanan, gabungkan dengan saga.
## Transaksi Rollback
## Urutan Event
Lintas layanan
## Sistem
## Terdistribusi

## Page 22
## Transaksi Lokal:
cek pesanan sudah diproses
ubah data bisnis
tulis event ke outbox
## SAGA  MENGATUR  LANGKAH,  OUTBOX  MENJAMIN
## PESAN ANTARLANGKAH SAMPAI
## Layanan Pesanan
## Transaksi Lokal:
cek pesanan sudah diproses
ubah data bisnis
tulis event ke outbox
## Layanan Stok
## Transaksi Lokal:
cek pesanan sudah diproses
ubah data bisnis
tulis event ke outbox
## Layanan Pembayaran
Saga menjawabOutbox menjawabIdempotensi menjawab
Langkah   apa   yang   dijalankan
berikutnya,    dan    apa    yang
dibatalkan bila gagal.
Bagaimana   memastikan   event
setiap langkah pasti terkirim walau
layanan crash.
Bagaimana  tetap  aman  bila  event
yang sama datang dua kali.
## Sistem
## Terdistribusi

EndpointTujuan
POST /orders + Idempotency-Key
buat pesanan; dibalas 202 Accepted
## + Location
GET /orders/{id}
Baca  status:  MENUNGGU,  SELESAI,
## DIBATALKAN +
alasan
POST/orders/{id}/cancel
Pembeli  membatalkan;  memicu
kompensasi bila
masih bisa
Jangan tampilkan "Berhasil" sebelum saga selesai; tampilkan "Diproses".
Ulangi request dengan Idempotency-Key yang sama agar tidak ada pesanan ganda.
Tampilkan alasan batal yang jelas, bukan kode error.
## Page 23
## DI  APLIKASI  WEB/MOBILE,  SAGA  TERLIHAT  SEBAGAI  STATUS
## PESANAN YANG BERGERAK, BUKAN SEBAGAI "BERHASIL" INSTAN
## Pesanan #123
Saldo kurang? Status berubah menjadi
"Dibatalkan, stok dikembalikan".
Pesanan dibuat
Stok dipesan
## ...
Menunggu pembayaran
## Dikirim
## Sistem
## Terdistribusi

## Page 24
## DI  LARAVEL,  OUTBOX  CUKUP  SATU  DB::TRANSACTION,  SATU  RELAY
## TERJADWAL, DAN CONSUMER YANG MENCATAT PESAN
## Sistem
## Terdistribusi

## Page 25
## DEMO:  SIMULASI  SAGA  KOREOGRAFI  DAN  OUTBOX  DENGAN
## PYTHON DAN SQLITE
## Sistem
## Terdistribusi

## Page 26
## SKENARIO  GAGAL:  RELAY  OUTBOX  MATI  30  MENIT  SAAT  JAM
## RAMAI
## Sistem
## Terdistribusi

## Page 27
## SAGA  DAN  OUTBOX  MEMBELI  KONSISTENSI  LINTAS
## LAYANAN DENGAN HARGA KOMPLEKSITAS
## Sistem
## Terdistribusi

## Page 28
## BENANG MERAH P3 DAN P10: API GATEWAY MENERIMA PESANAN
## DENGAN CEPAT, SAGA DAN OUTBOX MENYELESAIKANNYA
## Sistem
## Terdistribusi

## Page 29
## TIGA HAL YANG PERLU DIINGAT DARI PERTEMUAN INI
## Sistem
## Terdistribusi

## Page 30
## KUIS: LIMA PERTANYAAN SINGKAT
## Sistem
## Terdistribusi

## Page 31
## Sistem
## Terdistribusi
## REFRENSI
[1] Microsoft Azure Architecture Center. Pola transaksi terdistribusi Saga.
https://learn.microsoft.com/id-id/azure/architecture/patterns/saga
[2] AWS Prescriptive Guidance. Pola kotak keluar transaksional (Transactional Outbox).
https://docs.aws.amazon.com/id_id/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html
[3] AWS Prescriptive Guidance. Pola orkestrasi saga.
https://docs.aws.amazon.com/id_id/prescriptive-guidance/latest/cloud-design-patterns/saga-orchestration.html
## [4] Microsoft Azure Architecture Center. Pola Konsumen Idempoten.
https://learn.microsoft.com/id-id/azure/architecture/patterns/idempotent-consumer
[5] aws-samples. transactional-outbox-pattern (GitHub).
https://github.com/aws-samples/transactional-outbox-pattern

## Page 32
## Sistem
## Terdistribusi
## Thank
## -
## You