<?php
/**
 * Direct Presentation Entry Point
 * Bisa diakses langsung di: https://domain-anda.com/presentasi.php?key=...
 * Melewati router Laravel sehingga tidak akan terkena masalah 404 cache rute.
 */

$candidates = [
    __DIR__ . '/resources/saga-bridge/presentasi.html',
    __DIR__ . '/public/presentasi.html',
    __DIR__ . '/presentasi.html',
    __DIR__ . '/presentasi-saga-outbox.html',
];

$file = null;
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $file = $c;
        break;
    }
}

if (!$file) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>File Tidak Ditemukan</title></head><body>';
    echo '<h2>File presentasi belum ditemukan di server.</h2>';
    echo '<p>Pastikan file <code>resources/saga-bridge/presentasi.html</code> telah diekstrak ke server hosting.</p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($file);

