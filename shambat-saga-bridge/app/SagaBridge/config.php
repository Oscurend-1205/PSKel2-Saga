<?php

return [

    /*
    | Saklar pengaman utama. Kalau false, rute /presentasi dan /saga-bridge/api
    | tidak didaftarkan sama sekali dan perintah "on" ditolak.
    | Default: hanya diizinkan di luar APP_ENV=production (misalnya InfinityFree).
    */
    'allowed' => (bool) env('SAGA_BRIDGE_ALLOWED', env('APP_ENV', 'production') !== 'production'),

    /*
    | Alamat toko yang ditampilkan sebagai QR untuk audiens.
    | Kosongkan agar dideteksi otomatis dari IP laptop di jaringan WiFi.
    */
    'audience_url' => env('SAGA_BRIDGE_AUDIENCE_URL'),

    /*
    | Berapa detik relay outbox tetap "ditahan" sejak sinyal terakhir dari
    | presentasi. Kalau browser presentasi tertutup, relay otomatis berjalan lagi.
    */
    'hold_lease_seconds' => 15,

    'state_file' => storage_path('app/saga-bridge.json'),

    'presentation_file' => resource_path('saga-bridge/presentasi.html'),

];
