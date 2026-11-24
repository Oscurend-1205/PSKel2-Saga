<?php

/**
 * Shared Hosting Laravel Entry Point (Shambat E-Commerce)
 * Menjalankan Laravel secara langsung tanpa risiko FastCGI 502 Bad Gateway.
 */

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require_once __DIR__ . '/vendor/autoload.php';

(require_once __DIR__ . '/bootstrap/app.php')
    ->handleRequest(\Illuminate\Http\Request::capture());



