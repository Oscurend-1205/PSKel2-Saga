<?php
/**
 * Split-Zip Deployer untuk Shared Hosting (InfinityFree / cPanel)
 * Pasangan dari build_split_zip.ps1 untuk proyek Shambat E-Commerce.
 *
 * Letakkan file ini di root htdocs bersama seluruh arsip
 * (core.zip, vendor-*.zip, atau pecahannya *.zip.001, *.zip.002, ...).
 *
 * ⚠️ HAPUS file ini setelah proses deploy selesai demi keamanan.
 */

// ================== KONFIGURASI ==================
const DEPLOY_PASSWORD = 'ShambatDeploy123!';
// =================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$root    = __DIR__;
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$message = '';
$console = '';

// ================== AUTENTIKASI ==================
if ($action === 'login' && isset($_POST['password'])) {
    if (hash_equals(DEPLOY_PASSWORD, (string) $_POST['password'])) {
        $_SESSION['deploy_ok'] = true;
    } else {
        $message = alert('error', 'Password salah.');
    }
}
if ($action === 'logout') {
    unset($_SESSION['deploy_ok']);
    $action = '';
}
$authed = !empty($_SESSION['deploy_ok']);

// ================== UTILITAS ==================
function alert(string $type, string $text): string
{
    $icon = ['success' => '✅', 'error' => '❌', 'warn' => '⚠️', 'info' => 'ℹ️'][$type] ?? '';
    return "<div class=\"alert {$type}\">{$icon} {$text}</div>";
}

/** Kelompokkan arsip berdasarkan nama dasar: "core.zip" => [part1, part2, ...] */
function archiveGroups(string $root): array
{
    $groups = [];
    foreach (glob($root . DIRECTORY_SEPARATOR . '*.zip*') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match('/^(.*\.zip)(?:\.(\d{3}))?$/', $name, $m)) {
            continue;
        }
        $groups[$m[1]][] = $path;
    }
    foreach ($groups as $base => $parts) {
        sort($parts, SORT_STRING);
        $groups[$base] = $parts;
    }
    ksort($groups);
    return $groups;
}

/** Gabungkan part (bila ada) lalu ekstrak satu grup arsip ke $root. */
function extractGroup(string $root, string $base, array $parts): array
{
    $zipPath = $parts[0];
    $merged  = null;

    if (count($parts) > 1) {
        $merged = $root . DIRECTORY_SEPARATOR . $base . '.merged';
        $out    = @fopen($merged, 'wb');
        if (!$out) {
            return ['ok' => false, 'msg' => 'Gagal membuat file gabungan untuk ' . $base];
        }
        foreach ($parts as $part) {
            $in = @fopen($part, 'rb');
            if (!$in) {
                fclose($out);
                @unlink($merged);
                return ['ok' => false, 'msg' => 'Gagal membaca part: ' . basename($part)];
            }
            while (!feof($in)) {
                fwrite($out, fread($in, 1048576));
            }
            fclose($in);
        }
        fclose($out);
        $zipPath = $merged;
    }

    // Lindungi .env agar konfigurasi server tidak tertimpa saat re-deploy
    $envPath   = $root . DIRECTORY_SEPARATOR . '.env';
    $envBackup = $root . DIRECTORY_SEPARATOR . '.env.deploy.bak';
    $hadEnv    = file_exists($envPath);
    if ($hadEnv) {
        @copy($envPath, $envBackup);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        if ($merged) {
            @unlink($merged);
        }
        return ['ok' => false, 'msg' => 'Gagal membuka arsip: ' . basename($zipPath)];
    }

    $extracted = $zip->extractTo($root);
    $count     = $zip->numFiles;
    $zip->close();
    if ($merged) {
        @unlink($merged);
    }

    if ($hadEnv && file_exists($envBackup)) {
        @copy($envBackup, $envPath);
        @unlink($envBackup);
    }

    if (!$extracted) {
        return ['ok' => false, 'msg' => 'Gagal mengekstrak: ' . basename($zipPath)];
    }

    // Bersihkan view cache agar perubahan Blade langsung aktif
    foreach (glob($root . '/storage/framework/views/*.php') ?: [] as $vf) {
        @unlink($vf);
    }
    foreach (glob($root . '/bootstrap/cache/*.php') ?: [] as $bf) {
        @unlink($bf);
    }

    // Hapus arsip setelah sukses untuk menghemat kuota hosting
    foreach ($parts as $part) {
        @unlink($part);
    }

    return ['ok' => true, 'name' => $base, 'files' => $count];
}

function getEnvValue(string $root, string $key, string $default = ''): string
{
    $envPath = $root . '/.env';
    if (!file_exists($envPath)) {
        return $default;
    }
    $content = file_get_contents($envPath);
    if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $m)) {
        return trim($m[1], " \t\n\r\0\x0B\"'");
    }
    return $default;
}

function updateEnv(string $root, array $data)
{
    $envPath = $root . '/.env';
    if (!file_exists($envPath)) {
        return 'File .env tidak ditemukan. Klik "Buat .env" terlebih dahulu.';
    }
    $content = file_get_contents($envPath);
    foreach ($data as $key => $value) {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $value, $content);
        } else {
            $content .= "\n" . $key . '=' . $value;
        }
    }
    return file_put_contents($envPath, $content) !== false ? true : 'Gagal menulis file .env.';
}

/**
 * Bersihkan seluruh htdocs kecuali data penting:
 * - File deployer unzip.php
 * - File arsip (*.zip, *.zip.*)
 * - File .env, .env.example, .env.backup
 * - Folder aset media (storage/app/public, public/uploads)
 * - File database SQLite (database/database.sqlite - opsional)
 */
function cleanHtdocs(string $root, bool $keepSqlite = true): array
{
    $deletedFiles = 0;
    $deletedDirs  = 0;
    $errors       = [];

    $deleteRecursive = function ($path, $isSubcall = false) use (&$deleteRecursive, &$deletedFiles, &$deletedDirs, &$errors, $root, $keepSqlite) {
        $name    = basename($path);
        $relPath = str_replace('\\', '/', substr($path, strlen($root) + 1));

        // Lindungi file penting di root
        if (!$isSubcall) {
            if ($name === 'unzip.php' || str_starts_with($name, '.env')) {
                return;
            }
            if (preg_match('/\.zip(\.\d{3})?$/i', $name)) {
                return;
            }
        }

        // Lindungi aset media & uploads
        if ($relPath === 'storage/app/public' || str_starts_with($relPath, 'storage/app/public/')) {
            return;
        }
        if ($relPath === 'public/uploads' || str_starts_with($relPath, 'public/uploads/')) {
            return;
        }

        // Lindungi SQLite jika diminta
        if ($keepSqlite) {
            if ($relPath === 'database/database.sqlite' || str_starts_with($relPath, 'database/database.sqlite')) {
                return;
            }
        }

        if (is_dir($path)) {
            $items = scandir($path);
            if ($items === false) {
                $errors[] = "Tidak dapat membaca direktori: {$relPath}";
                return;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $deleteRecursive($path . DIRECTORY_SEPARATOR . $item, true);
            }
            // Lindungi direktori dasar agar struktur upload/media tetap valid
            $protectedDirs = ['storage', 'storage/app', 'public'];
            if ($keepSqlite) {
                $protectedDirs[] = 'database';
            }
            if (!in_array($relPath, $protectedDirs, true)) {
                if (@rmdir($path)) {
                    $deletedDirs++;
                }
            }
        } else {
            if (@unlink($path)) {
                $deletedFiles++;
            } else {
                $errors[] = "Gagal menghapus file: {$relPath}";
            }
        }
    };

    foreach (scandir($root) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $deleteRecursive($root . DIRECTORY_SEPARATOR . $item, false);
    }

    return [
        'files'  => $deletedFiles,
        'dirs'   => $deletedDirs,
        'errors' => $errors,
    ];
}

function getLaravelAppInstance(string $root)
{
    global $globalLaravelApp;
    if ($globalLaravelApp !== null && is_object($globalLaravelApp)) {
        return $globalLaravelApp;
    }
    $autoload  = $root . '/vendor/autoload.php';
    $bootstrap = $root . '/bootstrap/app.php';
    if (!file_exists($autoload) || !file_exists($bootstrap)) {
        return null;
    }
    require_once $autoload;
    $globalLaravelApp = require $bootstrap;
    return $globalLaravelApp;
}

function runArtisan(string $root, string $command): array
{
    $app = getLaravelAppInstance($root);
    if (!$app || !is_object($app)) {
        return ['ok' => false, 'out' => 'vendor/autoload.php atau bootstrap/app.php tidak dapat dimuat.'];
    }
    try {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        ob_start();
        if (class_exists(\Illuminate\Support\Facades\Schema::class)) {
            \Illuminate\Support\Facades\Schema::defaultStringLength(191);
        }
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $forceCommands = ['migrate', 'migrate:fresh', 'db:seed', 'key:generate'];
        $params = in_array($command, $forceCommands, true) ? ['--force' => true] : [];
        if ($command === 'saga-bridge:on') {
            $command = 'saga-bridge';
            $params = ['action' => 'on'];
        } elseif ($command === 'saga-bridge:status') {
            $command = 'saga-bridge';
            $params = ['action' => 'status'];
        }
        $status = $kernel->call($command, $params);
        $out    = $kernel->output();
        ob_end_clean();
        return ['ok' => $status === 0, 'out' => $out];
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        return ['ok' => false, 'out' => $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()];
    }
}

// ================== AKSI: EKSTRAK SEMUA ==================
if ($authed && ($action === 'extract' || isset($_POST['extract']))) {
    $groups  = archiveGroups($root);
    $okCount = 0;
    $errors  = [];
    $detail  = [];

    foreach ($groups as $base => $parts) {
        $res = extractGroup($root, $base, $parts);
        if ($res['ok']) {
            $okCount += $res['files'];
            $detail[] = "✅ {$res['name']} — {$res['files']} entri";
        } else {
            $errors[] = $res['msg'];
        }
    }

    // Bersihkan sisa file dev-server Vite (penyebab CSS tidak termuat)
    foreach (['/hot', '/public/hot'] as $hot) {
        if (file_exists($root . $hot)) {
            @unlink($root . $hot);
            $detail[] = '✅ File hot dihapus' . $hot;
        }
    }

    // Bersihkan cache bootstrap Laravel otomatis (hapus route cache, config cache lama)
    $cacheFiles = [
        '/bootstrap/cache/routes-v7.php',
        '/bootstrap/cache/routes.php',
        '/bootstrap/cache/config.php',
        '/bootstrap/cache/events.php',
        '/bootstrap/cache/services.php',
        '/bootstrap/cache/packages.php',
    ];
    foreach ($cacheFiles as $cf) {
        if (file_exists($root . $cf)) {
            @unlink($root . $cf);
            $detail[] = '✅ Cache dibersihkan: ' . $cf;
        }
    }

    // Hapus seluruh file compiled blade view di storage/framework/views
    $viewFiles = glob($root . '/storage/framework/views/*.php') ?: [];
    foreach ($viewFiles as $vf) {
        @unlink($vf);
    }
    if (!empty($viewFiles)) {
        $detail[] = '✅ ' . count($viewFiles) . ' file cache blade views dibersihkan';
    }

    // Buat .env dari .env.example bila belum ada
    if (!file_exists($root . '/.env') && file_exists($root . '/.env.example')) {
        @copy($root . '/.env.example', $root . '/.env');
        $detail[] = '✅ .env dibuat dari .env.example';
    }

    $console = implode("\n", $detail);
    $message = $okCount > 0
        ? alert(empty($errors) ? 'success' : 'warn', "Berhasil mengekstrak {$okCount} entri." . ($errors ? '<br>' . implode('<br>', $errors) : ''))
        : alert('error', $errors ? implode('<br>', $errors) : 'Tidak ada arsip yang ditemukan.');
}

// ================== AKSI: .ENV ==================
if ($authed && $action === 'create_env') {
    if (file_exists($root . '/.env')) {
        $message = alert('info', 'File .env sudah ada.');
    } elseif (file_exists($root . '/.env.example')) {
        @copy($root . '/.env.example', $root . '/.env');
        $message = alert('success', 'File .env dibuat dari .env.example. Silakan isi konfigurasi di bawah.');
    } else {
        $message = alert('error', 'File .env.example tidak ditemukan.');
    }
}

if ($authed && $action === 'save_env' && isset($_POST['save_env'])) {
    $dbConn = $_POST['db_connection'] ?? 'mysql';
    $dbDatabase = $_POST['db_database'] ?? '';
    if ($dbConn === 'sqlite') {
        $dbDatabase = $root . '/database/database.sqlite';
        if (!is_dir($root . '/database')) {
            @mkdir($root . '/database', 0777, true);
        }
        @chmod($root . '/database', 0777);
        if (!file_exists($dbDatabase)) {
            @touch($dbDatabase);
        }
        @chmod($dbDatabase, 0666);
    }

    $envData = [
        'APP_NAME'         => 'Shambat',
        'APP_ENV'          => 'production',
        'APP_DEBUG'        => $_POST['app_debug'] ?? 'false',
        'APP_URL'          => rtrim('https://' . ($_SERVER['HTTP_HOST'] ?? 'domain-anda.com'), '/'),
        'DB_CONNECTION'    => $dbConn,
        'DB_HOST'          => $_POST['db_host'] ?? '127.0.0.1',
        'DB_PORT'          => $_POST['db_port'] ?? '3306',
        'DB_DATABASE'      => $dbDatabase,
        'DB_USERNAME'      => $_POST['db_username'] ?? '',
        'DB_PASSWORD'      => $_POST['db_password'] ?? '',
        'SESSION_DRIVER'   => $_POST['session_driver'] ?? 'file',
        'CACHE_STORE'      => $_POST['cache_store'] ?? 'file',
        'QUEUE_CONNECTION' => 'sync',
        'SAGA_BRIDGE_ALLOWED' => 'true',
    ];
    if (!empty($_POST['app_key'])) {
        $envData['APP_KEY'] = trim($_POST['app_key']);
    }
    $res = updateEnv($root, $envData);
    $message = $res === true
        ? alert('success', 'Konfigurasi .env disimpan. Database SQLite dan permission telah disiapkan.')
        : alert('error', $res);
}

// ================== AKSI: ARTISAN ==================
if ($authed && $action === 'artisan' && isset($_POST['artisan_command'])) {
    $allowed = ['key:generate', 'migrate', 'migrate:fresh', 'db:seed', 'fresh_seed', 'storage:link', 'optimize:clear', 'config:cache', 'route:cache', 'view:cache', 'outbox:process', 'saga-bridge:on', 'saga-bridge:status'];
    $command = $_POST['artisan_command'];
    if (!in_array($command, $allowed, true)) {
        $message = alert('error', 'Perintah tidak diizinkan.');
    } else {
        if ($command === 'fresh_seed') {
            // Reset seluruh tabel (membersihkan sisa migrasi gagal), lalu migrate + seed.
            $res1 = runArtisan($root, 'migrate:fresh');
            $res2 = runArtisan($root, 'db:seed');
            $console = "=== migrate:fresh ===\n" . $res1['out']
                     . "\n=== db:seed ===\n" . ($res2['out'] ?: '(tanpa output)');
            $message = ($res1['ok'] && $res2['ok'])
                ? alert('success', 'Database berhasil di-reset, dimigrasi, dan di-seed.')
                : alert('error', 'Sebagian perintah gagal. Periksa output di bawah.');
        } elseif ($command === 'optimize:clear') {
            // Bersihkan file cache fisik langsung agar tidak bergantung pada koneksi database
            $cleared = 0;
            foreach (glob($root . '/bootstrap/cache/*.php') ?: [] as $f) {
                if (basename($f) !== '.gitignore') { @unlink($f); $cleared++; }
            }
            foreach (glob($root . '/storage/framework/views/*.php') ?: [] as $f) {
                if (basename($f) !== '.gitignore') { @unlink($f); $cleared++; }
            }
            $res = runArtisan($root, 'optimize:clear');
            $console = "File cache bootstrap & views dibersihkan: {$cleared}\n" . ($res['out'] ?: 'Cache berhasil dibersihkan.');
            $message = alert('success', "Seluruh cache (views, routes, config) berhasil dibersihkan.");
        } elseif (in_array($command, ['migrate', 'migrate:fresh', 'db:seed', 'fresh_seed'], true)) {
            $dbConn = getEnvValue($root, 'DB_CONNECTION', 'mysql');
            if ($dbConn === 'sqlite') {
                if (!is_dir($root . '/database')) {
                    @mkdir($root . '/database', 0777, true);
                }
                @chmod($root . '/database', 0777);
                $sqlite = $root . '/database/database.sqlite';
                if (!file_exists($sqlite)) {
                    @touch($sqlite);
                }
                @chmod($sqlite, 0666);
            }
        }
        if ($command === 'fresh_seed') {
            // Sudah ditangani di atas.
        } elseif ($command === 'key:generate') {
            $newKey = 'base64:' . base64_encode(random_bytes(32));
            $envPath = $root . '/.env';
            if (!file_exists($envPath) && file_exists($root . '/.env.example')) {
                @copy($root . '/.env.example', $envPath);
            }
            $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
            if (preg_match('/^APP_KEY=.*/m', $envContent)) {
                $envContent = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $newKey, $envContent);
            } else {
                $envContent = "APP_KEY={$newKey}\n" . $envContent;
            }
            @chmod($envPath, 0666);
            $written = @file_put_contents($envPath, $envContent);
            if ($written !== false) {
                runArtisan($root, 'optimize:clear');
                $console = "Application key [{$newKey}] set successfully into .env file.";
                $message = alert('success', "Kunci aplikasi berhasil dibuat & disimpan ke .env:<br><code>{$newKey}</code>");
            } else {
                $res = runArtisan($root, 'key:generate');
                $console = $res['out'];
                $message = $res['ok']
                    ? alert('success', "Perintah <code>key:generate</code> berhasil dijalankan.")
                    : alert('error', "Gagal menulis file .env. Pastikan permission .env writable (chmod 0666).");
            }
        } else {
            $res     = runArtisan($root, $command);
            $console = $res['out'];
            $message = $res['ok']
                ? alert('success', "Perintah <code>{$command}</code> berhasil dijalankan.")
                : alert('error', "Gagal menjalankan <code>{$command}</code>.");
        }
    }
}

// ================== AKSI: BERSIHKAN HTDOCS ==================
if ($authed && $action === 'wipe_htdocs' && isset($_POST['confirm_wipe'])) {
    $keepSqlite = !isset($_POST['wipe_sqlite']);
    $res = cleanHtdocs($root, $keepSqlite);
    $console = "✅ Pembersihan htdocs selesai:\n"
             . "- File terhapus: {$res['files']}\n"
             . "- Folder terhapus: {$res['dirs']}\n"
             . "- Dilindungi: .env, *.zip*, unzip.php, storage/app/public, public/uploads" . ($keepSqlite ? ", database/database.sqlite" : "");
    if (!empty($res['errors'])) {
        $console .= "\n\nPeringatan:\n" . implode("\n", $res['errors']);
    }
    $message = alert('success', "Workspace <code>htdocs</code> berhasil dibersihkan ({$res['files']} file & {$res['dirs']} folder terhapus). File <code>.env</code>, arsip ZIP, dan folder media tetap aman terlindungi.");
}

// ================== AKSI: FIX PERMISSIONS ==================
if ($authed && $action === 'fix_perms') {
    $dirs = [
        '/storage', '/storage/app', '/storage/app/public', '/storage/framework',
        '/storage/framework/cache', '/storage/framework/cache/data', '/storage/framework/sessions',
        '/storage/framework/testing', '/storage/framework/views', '/storage/logs', '/bootstrap/cache',
        '/database',
    ];
    $fixed = 0;
    foreach ($dirs as $dir) {
        $path = $root . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        @chmod($path, 0777);
        $fixed++;
    }
    $sqlite = $root . '/database/database.sqlite';
    if (!file_exists($sqlite)) {
        @touch($sqlite);
    }
    @chmod($sqlite, 0666);
    if (file_exists($root . '/.env')) {
        @chmod($root . '/.env', 0666);
    }
    if (!file_exists($root . '/storage/logs/.gitignore')) {
        @file_put_contents($root . '/storage/logs/.gitignore', "*\n!.gitignore\n");
    }
    if (!file_exists($root . '/storage/framework/cache/data/.gitignore')) {
        @file_put_contents($root . '/storage/framework/cache/data/.gitignore', "*\n!.gitignore\n");
    }
    // Perbaiki root index.php agar tidak menyebabkan loop headers_sent 502
    $indexPath = $root . '/index.php';
    if (file_exists($indexPath)) {
        $indexContent = file_get_contents($indexPath);
        if (strpos($indexContent, 'http_response_code(500)') !== false) {
            $safeIndex = "<?php\n\n\$uri = urldecode(parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');\nif (\$uri !== '/' && file_exists(__DIR__ . '/public' . \$uri) && !is_dir(__DIR__ . '/public' . \$uri)) {\n    return false;\n}\n\nrequire_once __DIR__ . '/public/index.php';\n";
            @file_put_contents($indexPath, $safeIndex);
        }
    }

    // Hapus seluruh file compiled blade view lama
    $oldViews = glob($root . '/storage/framework/views/*.php') ?: [];
    foreach ($oldViews as $ov) {
        @unlink($ov);
    }
    // Hapus file route cache lama jika ada
    if (file_exists($root . '/bootstrap/cache/routes-v7.php')) {
        @unlink($root . '/bootstrap/cache/routes-v7.php');
    }

    $message = alert('success', "{$fixed} folder storage/bootstrap/database & file SQLite diperbaiki. Cache views lama (" . count($oldViews) . " file) dan route cache berhasil dibersihkan.");
}

// ================== AKSI: DIAGNOSA ==================
$diag = null;
if ($authed && $action === 'diagnose') {
    $diag = [];
    $diag['php'] = ['value' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.2', '>=')];

    $critical = [
        'index.php'                 => $root . '/index.php',
        '.htaccess'                 => $root . '/.htaccess',
        'public/index.php'          => $root . '/public/index.php',
        'vendor/autoload.php'       => $root . '/vendor/autoload.php',
        'bootstrap/app.php'         => $root . '/bootstrap/app.php',
        'artisan'                   => $root . '/artisan',
        '.env'                      => $root . '/.env',
    ];
    $diag['files'] = [];
    foreach ($critical as $label => $path) {
        $diag['files'][$label] = file_exists($path);
    }

    $writables = ['storage', 'storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'];
    $diag['writable'] = [];
    foreach ($writables as $dir) {
        $path = $root . '/' . $dir;
        $diag['writable'][$dir] = is_dir($path) ? is_writable($path) : null;
    }

    $diag['extensions'] = [];
    foreach (['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'tokenizer', 'json', 'zip', 'curl', 'fileinfo'] as $ext) {
        $diag['extensions'][$ext] = extension_loaded($ext);
    }

    $diag['env'] = ['exists' => file_exists($root . '/.env')];
    if ($diag['env']['exists']) {
        $envContent = file_get_contents($root . '/.env');
        $diag['env']['has_app_key']   = (bool) (preg_match('/^APP_KEY=([^\r\n]+)/m', $envContent, $km) && strlen(trim($km[1], " \t\n\r\0\x0B\"'")) >= 20);
        $diag['env']['app_debug']     = (bool) preg_match('/^APP_DEBUG=true/m', $envContent);
        $diag['env']['db_connection'] = preg_match('/^DB_CONNECTION=(\w+)/m', $envContent, $m) ? $m[1] : '-';
    }

    $diag['boot'] = null;
    if (file_exists($root . '/vendor/autoload.php')) {
        try {
            ob_start();
            $app = getLaravelAppInstance($root);
            if ($app && is_object($app)) {
                $app->make(\Illuminate\Contracts\Http\Kernel::class);
            }
            ob_end_clean();
            $diag['boot'] = ['ok' => true, 'msg' => 'Laravel Shambat berhasil di-booting.'];
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $diag['boot'] = ['ok' => false, 'msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
        }
    }

    $logPath = $root . '/storage/logs/laravel.log';
    $diag['log'] = file_exists($logPath) ? array_slice(file($logPath), -20) : null;
}

$groups   = archiveGroups($root);
$hasFiles = !empty($groups);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shambat Split-Zip Deployer</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',system-ui,sans-serif; background:linear-gradient(135deg,#0f0c29,#302b63,#24243e); color:#e2e8f0; min-height:100vh; padding:30px 15px; }
  .wrap { max-width:760px; margin:0 auto; }
  h1 { text-align:center; font-size:26px; margin-bottom:6px; background:linear-gradient(90deg,#ff7337,#ee4d2d,#f59e0b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .sub { text-align:center; color:#94a3b8; font-size:13px; margin-bottom:24px; }
  .card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:22px; margin-bottom:18px; }
  .card h2 { font-size:16px; margin-bottom:14px; color:#f1f5f9; }
  .alert { padding:12px 16px; border-radius:9px; margin-bottom:16px; font-size:14px; }
  .alert.success { background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3); color:#86efac; }
  .alert.error { background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#fca5a5; }
  .alert.warn { background:rgba(250,204,21,.12); border:1px solid rgba(250,204,21,.3); color:#fde68a; }
  .alert.info { background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }
  .file { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid rgba(255,255,255,.06); font-size:13px; color:#cbd5e1; }
  .btn { padding:12px 18px; border:none; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; color:#fff; background:linear-gradient(135deg,#ee4d2d,#d73211); }
  .btn:hover { filter:brightness(1.1); }
  .btn.green { background:linear-gradient(135deg,#10b981,#059669); }
  .btn.amber { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1917; }
  .btn.violet { background:linear-gradient(135deg,#8b5cf6,#7c3aed); }
  .btn.dark { background:linear-gradient(135deg,#334155,#1e293b); }
  .row { display:flex; gap:10px; flex-wrap:wrap; }
  .row > * { flex:1; min-width:150px; }
  label { font-size:12px; color:#fdba74; font-weight:600; text-transform:uppercase; display:block; margin-bottom:5px; }
  input, select { width:100%; background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:10px 12px; color:#fff; font-size:14px; margin-bottom:12px; }
  pre { background:#1e1e1e; border:1px solid rgba(255,255,255,.15); border-radius:9px; padding:14px; font-size:12px; color:#86efac; white-space:pre-wrap; max-height:300px; overflow:auto; }
  .check { font-size:13px; padding:4px 0; }
  .ok { color:#86efac; } .bad { color:#fca5a5; font-weight:600; } .warn { color:#fde68a; }
  .foot { text-align:center; font-size:12px; color:#94a3b8; margin-top:20px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🚀 Shambat Split-Zip Deployer</h1>
  <p class="sub">Deployment &amp; diagnostik Laravel Saga E-Commerce untuk shared hosting (InfinityFree / cPanel)</p>

  <?= $message ?>

  <?php if (!$authed): ?>
    <div class="card">
      <h2>🔐 Masuk Deployer</h2>
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <label>Password Deployer</label>
        <input type="password" name="password" placeholder="Masukkan password deployer..." required autofocus>
        <button type="submit" class="btn" style="width:100%">Masuk</button>
      </form>
    </div>
  <?php else: ?>

    <div class="card">
      <h2>📦 Arsip Terdeteksi</h2>
      <?php if ($hasFiles): ?>
        <?php foreach ($groups as $base => $parts): ?>
          <div class="file">
            <span>📄 <?= htmlspecialchars(basename($base)) ?><?= count($parts) > 1 ? ' (' . count($parts) . ' part)' : '' ?></span>
            <span><?= round(array_sum(array_map('filesize', $parts)) / 1048576, 2) ?> MB</span>
          </div>
        <?php endforeach; ?>
        <form method="POST" style="margin-top:16px">
          <input type="hidden" name="action" value="extract">
          <button type="submit" name="extract" class="btn green" style="width:100%">📦 Ekstrak Semua Arsip Sekarang</button>
        </form>
      <?php else: ?>
        <p style="color:#64748b; font-size:14px">Tidak ada arsip ditemukan. Upload file <code>*.zip</code> / <code>*.zip.00x</code> ke folder ini lalu segarkan halaman.</p>
      <?php endif; ?>
      <div class="row" style="margin-top:12px">
        <a href="?action=refresh" class="btn dark" style="text-align:center; text-decoration:none; display:block">🔄 Segarkan</a>
        <a href="?action=logout" class="btn dark" style="text-align:center; text-decoration:none; display:block">🚪 Keluar</a>
      </div>
    </div>

    <div class="card">
      <h2>📝 Konfigurasi .env</h2>
      <?php if (!file_exists($root . '/.env')): ?>
        <form method="POST" style="margin-bottom:14px">
          <input type="hidden" name="action" value="create_env">
          <button type="submit" class="btn violet" style="width:100%">📝 Buat .env dari .env.example</button>
        </form>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="save_env">
        <label>APP_KEY (Application Encryption Key)</label>
        <div style="display:flex; gap:8px; margin-bottom:12px;">
          <input type="text" id="app_key_input" name="app_key" value="<?= htmlspecialchars(getEnvValue($root, 'APP_KEY')) ?>" placeholder="base64:..." style="margin-bottom:0; font-family:monospace; font-size:12px;">
          <button type="button" class="btn violet" style="white-space:nowrap; padding:0 14px;" onclick="generateKeyClient()">🔑 Random Key</button>
        </div>

        <label>DB Connection</label>
        <?php $dbConn = getEnvValue($root, 'DB_CONNECTION', 'mysql'); ?>
        <select name="db_connection" onchange="toggleDbFields(this.value)">
          <option value="mysql" <?= $dbConn === 'mysql' ? 'selected' : '' ?>>MySQL (Shared Hosting Database)</option>
          <option value="sqlite" <?= $dbConn === 'sqlite' ? 'selected' : '' ?>>SQLite (Bawaan File - Sangat Praktis)</option>
        </select>

        <div id="mysqlFields" style="<?= $dbConn === 'sqlite' ? 'display:none;' : '' ?>">
          <div class="row">
            <div><label>DB Host</label><input type="text" name="db_host" value="<?= htmlspecialchars(getEnvValue($root, 'DB_HOST', 'sqlxxx.infinityfree.com')) ?>"></div>
            <div style="max-width:120px"><label>Port</label><input type="text" name="db_port" value="<?= htmlspecialchars(getEnvValue($root, 'DB_PORT', '3306')) ?>"></div>
          </div>
          <div class="row">
            <div><label>Database</label><input type="text" name="db_database" value="<?= htmlspecialchars(getEnvValue($root, 'DB_DATABASE')) ?>"></div>
            <div><label>Username</label><input type="text" name="db_username" value="<?= htmlspecialchars(getEnvValue($root, 'DB_USERNAME')) ?>"></div>
          </div>
          <label>Password Database</label>
          <input type="password" name="db_password" value="<?= htmlspecialchars(getEnvValue($root, 'DB_PASSWORD')) ?>">
        </div>

        <div class="row">
          <div>
            <label>Session Driver</label>
            <?php $s = getEnvValue($root, 'SESSION_DRIVER', 'file'); ?>
            <select name="session_driver">
              <option value="file" <?= $s === 'file' ? 'selected' : '' ?>>file</option>
              <option value="database" <?= $s === 'database' ? 'selected' : '' ?>>database</option>
              <option value="cookie" <?= $s === 'cookie' ? 'selected' : '' ?>>cookie</option>
            </select>
          </div>
          <div>
            <label>Cache Store</label>
            <?php $c = getEnvValue($root, 'CACHE_STORE', 'file'); ?>
            <select name="cache_store">
              <option value="file" <?= $c === 'file' ? 'selected' : '' ?>>file</option>
              <option value="database" <?= $c === 'database' ? 'selected' : '' ?>>database</option>
              <option value="array" <?= $c === 'array' ? 'selected' : '' ?>>array</option>
            </select>
          </div>
        </div>

        <label>APP_DEBUG</label>
        <?php $d = getEnvValue($root, 'APP_DEBUG', 'false'); ?>
        <select name="app_debug">
          <option value="false" <?= $d === 'false' ? 'selected' : '' ?>>false (Production)</option>
          <option value="true" <?= $d === 'true' ? 'selected' : '' ?>>true (Debugging)</option>
        </select>
        <button type="submit" name="save_env" class="btn green" style="width:100%">💾 Simpan Konfigurasi</button>
      </form>
    </div>

    <div class="card">
      <h2>🚀 Perintah Artisan</h2>
      <form method="POST" onsubmit="return confirmCommand(this)">
        <input type="hidden" name="action" value="artisan">
        <label>Pilih Perintah</label>
        <select name="artisan_command">
          <option value="key:generate">key:generate (Wajib dijalankan)</option>
          <option value="migrate">migrate (Jalankan migrasi tabel domain)</option>
          <option value="db:seed">db:seed (Isi produk & demo data Shambat)</option>
          <option value="outbox:process">outbox:process (Proses antrean pesan Outbox)</option>
          <option value="saga-bridge:status">saga-bridge:status (Cek Status & Kunci Presentasi)</option>
          <option value="saga-bridge:on">saga-bridge:on (Aktifkan / Buat Kunci Baru Presentasi)</option>
          <option value="optimize:clear">optimize:clear (Bersihkan seluruh cache)</option>
          <option value="config:cache">config:cache</option>
          <option value="route:cache">route:cache</option>
          <option value="view:cache">view:cache</option>
          <option value="migrate:fresh">migrate:fresh (⚠️ Reset Database Ulang)</option>
          <option value="fresh_seed">migrate:fresh + db:seed (⚠️ Reset &amp; Isi Ulang Database)</option>
        </select>
        <button type="submit" class="btn green" style="width:100%">Jalankan Perintah Artisan</button>
      </form>
    </div>

    <div class="card">
      <h2>🛠️ Tools &amp; Diagnosa</h2>
      <div class="row">
        <form method="POST"><input type="hidden" name="action" value="fix_perms"><button class="btn" style="width:100%">🔧 Fix Permissions</button></form>
        <a href="?action=diagnose" class="btn amber" style="text-align:center; text-decoration:none; display:block">🔍 Diagnosa Server</a>
      </div>
      <?php if ($console): ?><pre style="margin-top:14px"><?= htmlspecialchars($console) ?></pre><?php endif; ?>
    </div>

    <div class="card" style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.06)">
      <h2 style="color:#fca5a5">🧹 Bersihkan htdocs (Reset / Fresh Install)</h2>
      <p style="font-size:13px; color:#cbd5e1; margin-bottom:12px; line-height:1.5">
        Hapus seluruh file &amp; folder Laravel di <code>htdocs</code> (vendor, app, bootstrap, cache, dll) agar bisa melakukan ekstrak ulang yang bersih.<br>
        <strong style="color:#86efac">🛡️ Data penting yang AMAN &amp; TIDAK AKAN DIHAPUS:</strong><br>
        • File <code>.env*</code> (konfigurasi &amp; APP_KEY)<br>
        • File arsip <code>*.zip*</code> (paket zip yang sudah diupload)<br>
        • File script <code>unzip.php</code><br>
        • Folder media upload (<code>storage/app/public</code> &amp; <code>public/uploads</code>)<br>
        • File database <code>database.sqlite</code> (kecuali dicentang opsi hapus di bawah)
      </p>
      <form method="POST" onsubmit="return confirmWipe(this)">
        <input type="hidden" name="action" value="wipe_htdocs">
        <input type="hidden" name="confirm_wipe" value="1">
        <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px; text-transform:none; font-size:13px; color:#fde68a; cursor:pointer;">
          <input type="checkbox" name="wipe_sqlite" value="1" style="width:auto; margin-bottom:0">
          Centang jika ingin menghapus file database SQLite juga (Reset Database)
        </label>
        <button type="submit" class="btn" style="width:100%; background:linear-gradient(135deg,#dc2626,#991b1b)">
          🗑️ Bersihkan Seluruh htdocs Sekarang
        </button>
      </form>
    </div>

    <?php if ($diag): ?>
      <div class="card">
        <h2>🔍 Hasil Diagnosa Server</h2>
        <div class="check <?= $diag['php']['ok'] ? 'ok' : 'bad' ?>"><?= $diag['php']['ok'] ? '✅' : '❌' ?> PHP <?= htmlspecialchars($diag['php']['value']) ?> (disarankan ≥ 8.2)</div>
        <h2 style="margin-top:14px">File Kritis</h2>
        <?php foreach ($diag['files'] as $label => $exists): ?>
          <div class="check <?= $exists ? 'ok' : 'bad' ?>"><?= $exists ? '✅' : '❌' ?> <?= htmlspecialchars($label) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">Folder Writable</h2>
        <?php foreach ($diag['writable'] as $label => $w): ?>
          <div class="check <?= $w === true ? 'ok' : ($w === null ? 'warn' : 'bad') ?>"><?= $w === true ? '✅' : ($w === null ? '⚠️ tidak ada' : '❌ tidak writable') ?> <?= htmlspecialchars($label) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">Ekstensi PHP</h2>
        <?php foreach ($diag['extensions'] as $ext => $loaded): ?>
          <div class="check <?= $loaded ? 'ok' : 'bad' ?>"><?= $loaded ? '✅' : '❌' ?> <?= htmlspecialchars($ext) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">Konfigurasi .env</h2>
        <?php if ($diag['env']['exists']): ?>
          <div class="check <?= $diag['env']['has_app_key'] ? 'ok' : 'bad' ?>"><?= $diag['env']['has_app_key'] ? '✅' : '❌' ?> APP_KEY</div>
          <div class="check ok">ℹ️ APP_DEBUG: <?= $diag['env']['app_debug'] ? 'true' : 'false' ?></div>
          <div class="check ok">ℹ️ DB_CONNECTION: <?= htmlspecialchars($diag['env']['db_connection'] ?? '-') ?></div>
        <?php else: ?>
          <div class="check bad">❌ .env tidak ditemukan</div>
        <?php endif; ?>
        <?php if ($diag['boot']): ?>
          <h2 style="margin-top:14px">Laravel Boot Test</h2>
          <?php if ($diag['boot']['ok']): ?>
            <div class="check ok">✅ <?= htmlspecialchars($diag['boot']['msg']) ?></div>
          <?php else: ?>
            <div class="check bad">❌ <?= htmlspecialchars($diag['boot']['msg']) ?></div>
            <pre><?= htmlspecialchars(($diag['boot']['file'] ?? '') . ':' . ($diag['boot']['line'] ?? '')) ?></pre>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($diag['log']): ?>
          <h2 style="margin-top:14px">Laravel Log (20 baris terakhir)</h2>
          <pre><?= htmlspecialchars(implode('', $diag['log'])) ?></pre>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

  <div class="foot">⚠️ Hapus file <code>unzip.php</code> setelah deployment selesai demi keamanan aplikasi.</div>
</div>
<script>
function generateKeyClient() {
  var bytes = new Uint8Array(32);
  window.crypto.getRandomValues(bytes);
  var binary = '';
  for (var i = 0; i < bytes.length; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  document.getElementById('app_key_input').value = 'base64:' + btoa(binary);
}
function toggleDbFields(val) {
  var el = document.getElementById('mysqlFields');
  if (el) el.style.display = (val === 'mysql') ? 'block' : 'none';
}
function confirmCommand(form) {
  var v = form.artisan_command.value;
  if (v === 'migrate:fresh' || v === 'fresh_seed') {
    return confirm('PERINGATAN: migrate:fresh akan MENGHAPUS SEMUA TABEL di database. Lanjutkan?');
  }
  return true;
}
function confirmWipe(form) {
  var msg = 'PERINGATAN:\nSeluruh kode Laravel, vendor, dan cache di htdocs akan dihapus bersih.\n\nFile .env, file arsip ZIP, dan folder media upload akan tetap aman.\n\nApakah Anda yakin ingin membersihkan htdocs?';
  if (form.wipe_sqlite && form.wipe_sqlite.checked) {
    msg += '\n\n⚠️ PERHATIAN: Anda juga mencentang penghapusan database.sqlite!';
  }
  return confirm(msg);
}
</script>
</body>
</html>

