# Prepare Deploy — Laravel Split-Zip untuk Shared Hosting (InfinityFree)

Dokumen ini menyalin **sistem deploy yang dipakai di proyek EunaGlow** supaya bisa dipakai ulang
di proyek Laravel lain. Isinya adalah panduan langkah + template skrip lengkap (siap salin-tempel).

Sistem ini dipilih karena shared hosting seperti InfinityFree punya 2 batasan keras:

1. **Maksimal 10MB per file** untuk upload via File Manager.
2. **Tidak ada SSH / Composer** — jadi `vendor/` harus ikut di-upload, dan `composer install`
   tidak mungkin dijalankan dari server.

Karena `vendor/` Laravel mudah melewati 30–60MB, aplikasi **tidak** dipaket jadi satu ZIP besar,
melainkan **dipecah per-komponen** lalu tiap pecahan yang masih > 9.5MB dipecah lagi menjadi
part `.zip.001`, `.zip.002`, dst. Di server, sebuah file PHP bernama `unzip.php` menggabungkan
part-part tersebut dan mengekstraknya ke `htdocs` tanpa perlu SSH.

---

## 1. Arsitektur & Struktur Output

`build_split_zip.ps1` menghasilkan folder `build_infinityfree/` berisi:

| File | Isi |
| --- | --- |
| `core.zip` | Seluruh kode aplikasi Laravel (app, config, routes, resources, database, public, bootstrap, dan file root) **kecuali** `vendor/`, `node_modules/`, `.git/`, `.env`, `public/uploads`, `storage/app/public` |
| `vendor-framework.zip` | `vendor/laravel/*`, `vendor/symfony/*` + file root vendor (`autoload.php`, `composer/*`) |
| `vendor-filament.zip` | `vendor/filament/*`, `vendor/spatie/*` — **hanya dibuat jika terdeteksi** |
| `vendor-testing.zip` | `fakerphp`, `phpunit`, `mockery`, `hamcrest`, `phar-io`, `sebastian`, `myclabs`, `theseer`, `staabm` |
| `vendor-tools.zip` | `nesbot`, `nikic`, `nunomaduro`, `psy`, `league`, `monolog`, `guzzlehttp`, `voku`, `filp`, `nette`, `egulias` |
| `vendor-misc.zip` | Sisa package vendor yang tidak masuk grup mana pun (dideteksi otomatis) |
| `unzip.php` | Deployer yang disalin dari root proyek |

Aturan pemecahan (`Split-ZipFile`):

- Jika file **≤ 9.5MB** → disalin apa adanya dengan nama `nama.zip`.
- Jika **> 9.5MB** → dipecah menjadi `nama.zip.001`, `nama.zip.002`, dst. (masing-masing ≤ 9.5MB).
  File aslinya **tidak** disertakan.

Pemecahan vendor jadi beberapa grup punya tujuan penting: kalau semua vendor dijadikan satu ZIP,
proses ekstraksi di server bisa **timeout 30 detik**. Dengan dipecah per grup, tiap ekstraksi
selesai dalam hitungan detik.

Struktur akhir di hosting (standard Laravel layout, tanpa restrukturisasi):

```
htdocs/
├── index.php          ← entry point (disertakan di core.zip)
├── .htaccess          ← rewrite semua request ke index.php
├── public/            ← aset publik + .htaccess Laravel
│   ├── index.php
│   ├── build/         ← hasil npm run build
│   └── ...
├── app/ config/ routes/ resources/ database/ bootstrap/ ...
├── vendor/            ← hasil ekstraksi vendor-*.zip
├── storage/           ← dibuat otomatis, harus writable
└── .env               ← TIDAK dipaket; dibuat/diisi lewat unzip.php
```

---

## 2. Prasyarat (Checklist Sebelum Paket)

Jalankan berurutan di mesin lokal (Windows + PowerShell):

```powershell
# 1. Dependensi PHP siap (vendor/ WAJIB ada, karena server tidak bisa composer install)
composer install --no-dev --optimize-autoloader

# 2. Build aset frontend (public/build + manifest.json)
npm ci
npm run build

# 3. Pastikan tidak ada file dev-server Vite yang tertinggal
Remove-Item .\public\hot -ErrorAction SilentlyContinue
```

Checklist manual:

- [ ] `vendor/` ada dan lengkap.
- [ ] `public/build/manifest.json` ada (kalau tidak, CSS/JS akan 404 di produksi).
- [ ] `public/hot` **tidak** ada. File ini membuat `@vite()` mengarah ke `localhost:5173`
      dan menjadi penyebab nomor satu tampilan polos tanpa CSS di produksi.
- [ ] `.env` sudah dikonfigurasi untuk server tujuan (script **tidak** memaket `.env`;
      `.env.example` tetap ikut di `core.zip` sebagai acuan).
- [ ] `unzip.php` sudah ada di root proyek (lihat Template B di bawah).
- [ ] Folder `build_infinityfree/` di-ignore dari git (sudah ada di `.gitignore` proyek ini:
      `build_infinityfree/*.zip*`).

> **Catatan untuk repo EunaGlow ini:** `build_split_zip.ps1` sudah ada, tetapi file
> `unzip.php` yang dirujuknya **belum ada** di root proyek — jadi menjalankan script
> tersebut sekarang akan memunculkan `[!] Warning: File unzip.php tidak ditemukan di root proyek!`.
> Salin Template B ke root proyek terlebih dahulu agar paket output lengkap. (Di repo ini,
> mekanisme deploy yang sedang aktif dipakai adalah `build_infinityfree.ps1` + `app.php`.)

---

## 3. Cara Pakai

```powershell
# Jalankan dari root proyek
.\build_split_zip.ps1
```

Alur di server:

1. Buka File Manager InfinityFree → masuk ke `htdocs/`.
2. Upload **semua** file dari `build_infinityfree/` (semua `*.zip`, `*.zip.00x`, dan `unzip.php`)
   ke `htdocs/`. Upload per-grup jika kuota/sesi upload terbatas.
3. Akses `https://domain-anda.com/unzip.php`.
4. Masukkan password deployer → klik **Ekstrak Semua Arsip**. Deployer akan menggabungkan
   part, mengekstrak tiap grup, lalu menghapus arsipnya.
5. Buat/isi `.env` dari form di halaman yang sama (DB host, nama DB, user, password),
   lalu jalankan **`key:generate`** dan **`migrate`** dari menu Artisan.
6. Jalankan **Fix Permissions**, lalu **Diagnosa** untuk memastikan semuanya hijau.
7. **Hapus `unzip.php` dari hosting** setelah selesai.

---

## 4. Template A — `build_split_zip.ps1`

> Simpan sebagai `build_split_zip.ps1` di **root proyek**. Untuk proyek lain, cukup ganti
> `$projectName`. Grup vendor bisa disesuaikan dengan `composer.json` proyek Anda.

```powershell
<#
.SYNOPSIS
    Premium Laravel Split-Zip Packager for Shared Hosting (e.g., InfinityFree)
.DESCRIPTION
    Script ini memaketkan aplikasi Laravel secara cerdas menjadi arsip ZIP terpisah di bawah 10MB:
    1. core.zip          : Kode aplikasi Laravel inti (kecuali vendor, node_modules, .git).
    2. vendor-framework  : Laravel Framework, Symfony, dan root vendor loader.
    3. vendor-filament   : Filament dan Spatie (jika digunakan).
    4. vendor-others     : Sisa package vendor lainnya yang dideteksi secara dinamis.
    Semua zip yang melebihi 9.5MB akan dipecah secara dinamis menjadi part .zip.xxx.
#>

# Konfigurasi Utama
$projectName = "EunaGlow"
$sourceDir = (Get-Item .).FullName
$outputDir = Join-Path $sourceDir "build_infinityfree"
$tempBuildDir = Join-Path $sourceDir "build_temp_workspace"

Clear-Host
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   [LARAVEL DEPLOYMENT ARCHITECT - ZIP SPLITTER CORE]     " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "Direktori Proyek: $sourceDir" -ForegroundColor Gray
Write-Host "Direktori Output: $outputDir" -ForegroundColor Gray
Write-Host "----------------------------------------------------------" -ForegroundColor Cyan

# 1. Bersihkan Cache Laravel sebelum dipacking
Write-Host "[*] Melakukan optimasi dan pembersihan cache lokal..." -ForegroundColor Yellow
if (Get-Command "php" -ErrorAction SilentlyContinue) {
    php artisan optimize:clear
} else {
    Write-Host "[!] PHP tidak ditemukan di PATH lokal. Pembersihan cache Laravel dilewati." -ForegroundColor DarkYellow
}

# 2. Inisialisasi Folder Output dan Temp
Write-Host "[*] Menyiapkan lingkungan workspace kompresi..." -ForegroundColor Yellow
if (Test-Path $outputDir) { Remove-Item $outputDir -Recurse -Force | Out-Null }
if (Test-Path $tempBuildDir) { Remove-Item $tempBuildDir -Recurse -Force | Out-Null }

New-Item -ItemType Directory -Path $outputDir | Out-Null
New-Item -ItemType Directory -Path $tempBuildDir | Out-Null

# Fungsi Utama Kompresi dengan Path Unix (Forward Slash)
function Compress-FolderToZip {
    param (
        [string]$sourcePath,
        [string]$zipPath,
        [string[]]$excludePaths = @(),
        [string[]]$includePaths = @(),
        [bool]$includeVendorRootFiles = $false,
        [string]$entryPrefix = ""
    )
    
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $sourcePath = (Get-Item $sourcePath).FullName
    $zipDir = Split-Path -Parent $zipPath
    if (-not (Test-Path $zipDir)) { New-Item -ItemType Directory -Path $zipDir -Force | Out-Null }
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    
    $zipStream = [System.IO.File]::Create($zipPath)
    $archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    
    $files = Get-ChildItem -Path $sourcePath -Recurse -File
    $addedCount = 0
    
    foreach ($file in $files) {
        $fullPath = $file.FullName
        $relativePath = $fullPath.Substring($sourcePath.Length + 1)
        $normalizedRelative = $relativePath -replace '\\', '/'
        
        # Validasi pengecualian
        $isExcluded = $false
        foreach ($exclude in $excludePaths) {
            if ($normalizedRelative -like "$exclude/*" -or $normalizedRelative -eq $exclude -or $normalizedRelative.StartsWith("$exclude/")) {
                $isExcluded = $true
                break
            }
        }
        if ($isExcluded) { continue }
        
        # Validasi inklusi (jika ada daftar khusus)
        if ($includePaths.Count -gt 0) {
            $isIncluded = $false
            foreach ($include in $includePaths) {
                if ($normalizedRelative -like "$include/*" -or $normalizedRelative -eq $include -or $normalizedRelative.StartsWith("$include/")) {
                    $isIncluded = $true
                    break
                }
            }
            
            # Khusus vendor: Masukkan file root vendor (autoload.php, composer/*)
            if (-not $isIncluded -and $includeVendorRootFiles) {
                if ($normalizedRelative -notlike "*/*/*" -and ($normalizedRelative.StartsWith("composer/") -or $normalizedRelative -eq "autoload.php")) {
                    $isIncluded = $true
                }
            }
            
            if (-not $isIncluded) { continue }
        }
        
        # Tambahkan berkas ke arsip ZIP (dengan prefix direktori jika ada)
        $entryName = $normalizedRelative
        if ($entryPrefix -ne "") {
            $entryName = "$entryPrefix/$normalizedRelative"
        }
        try {
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($fullPath)
            $fileStream.CopyTo($entryStream)
            $fileStream.Close()
            $entryStream.Close()
            $addedCount++
        } catch {
            Write-Host "   [!] Gagal memasukkan file: $normalizedRelative" -ForegroundColor Red
        }
    }
    
    $archive.Dispose()
    $zipStream.Close()
    return $addedCount
}

# Fungsi Memecah File ZIP secara Dinamis jika > 9.5MB
function Split-ZipFile {
    param (
        [string]$zipPath,
        [string]$outputFolder,
        [long]$maxSize = 9.5MB
    )
    
    $fileInfo = Get-Item $zipPath
    $fileSize = $fileInfo.Length
    $baseName = $fileInfo.BaseName
    
    if ($fileSize -le $maxSize) {
        $destPath = Join-Path $outputFolder "$baseName.zip"
        Copy-Item $zipPath $destPath -Force
        Write-Host "   -> $($baseName).zip ($([Math]::Round($fileSize / 1MB, 2)) MB) [AMBIL ASLI - Di bawah 10MB]" -ForegroundColor Green
        return
    }
    
    Write-Host "   -> $($baseName).zip ($([Math]::Round($fileSize / 1MB, 2)) MB) melebihi 10MB. Memecah berkas..." -ForegroundColor Yellow
    
    $buffer = New-Object byte[] 65536
    $inFile = [System.IO.File]::OpenRead($zipPath)
    $partNumber = 1
    
    while ($inFile.Position -lt $inFile.Length) {
        $partName = "$baseName.zip.{0:D3}" -f $partNumber
        $outFilePath = Join-Path $outputFolder $partName
        $outFile = [System.IO.File]::Create($outFilePath)
        
        $bytesWritten = 0
        while ($bytesWritten -lt $maxSize -and $inFile.Position -lt $inFile.Length) {
            $readSize = [Math]::Min($buffer.Length, $maxSize - $bytesWritten)
            $readSize = [Math]::Min([long]$readSize, $inFile.Length - $inFile.Position)
            if ($readSize -le 0) { break }
            $bytesRead = $inFile.Read($buffer, 0, $readSize)
            $outFile.Write($buffer, 0, $bytesRead)
            $bytesWritten += $bytesRead
        }
        $outFile.Close()
        $partSize = (Get-Item $outFilePath).Length
        Write-Host "      [-] Dibuat part: $partName ($([Math]::Round($partSize / 1MB, 2)) MB)" -ForegroundColor Gray
        $partNumber++
    }
    $inFile.Close()
}

# --- PROSES KOMPRESI ---

# A. Kompresi Core Application
Write-Host "`n[1/4] Mengompresi File Utama Aplikasi (core.zip)..." -ForegroundColor Cyan
$coreZipTemp = Join-Path $tempBuildDir "core.zip"
$excludeList = @("vendor", "node_modules", ".git", "build_infinityfree", "build_temp_workspace", ".env", "public/uploads", "storage/app/public", "database/database.sqlite")
$coreCount = Compress-FolderToZip -sourcePath $sourceDir -zipPath $coreZipTemp -excludePaths $excludeList
Write-Host "   -> Berhasil memaketkan $coreCount file inti aplikasi." -ForegroundColor Gray

# B. Analisis dan Kompresi Vendor
if (-not (Test-Path "$sourceDir\vendor")) {
    Write-Host "[!] Folder 'vendor' tidak ditemukan! Silakan jalankan 'composer install' terlebih dahulu." -ForegroundColor Red
    Remove-Item $tempBuildDir -Recurse -Force
    exit
}

# Framework Group (laravel, symfony, composer, autoload)
Write-Host "`n[2/4] Mengompresi Vendor Framework (vendor-framework.zip)..." -ForegroundColor Cyan
$frameworkZipTemp = Join-Path $tempBuildDir "vendor-framework"
$frameworkCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $frameworkZipTemp -includePaths @("laravel", "symfony") -includeVendorRootFiles $true -entryPrefix "vendor"
Write-Host "   -> Berhasil memaketkan $frameworkCount file framework." -ForegroundColor Gray

# Filament Group (filament, spatie - jika ada)
$hasFilament = (Test-Path "$sourceDir\vendor\filament") -or (Test-Path "$sourceDir\vendor\spatie")
if ($hasFilament) {
    Write-Host "`n[3/4] Filament/Spatie terdeteksi. Mengompresi Vendor Filament (vendor-filament.zip)..." -ForegroundColor Cyan
    $filamentZipTemp = Join-Path $tempBuildDir "vendor-filament"
    $filamentCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $filamentZipTemp -includePaths @("filament", "spatie") -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $filamentCount file Filament/Spatie." -ForegroundColor Gray
} else {
    Write-Host "`n[3/4] Filament/Spatie tidak terdeteksi. Melewati vendor-filament.zip." -ForegroundColor DarkGray
}

# Other Vendors - Dipecah menjadi 3 grup agar tidak timeout saat ekstraksi di InfinityFree
Write-Host "`n[4/7] Memindai sisa dependensi vendor secara dinamis..." -ForegroundColor Cyan

# Grup A: Testing packages (fakerphp besar ~9MB, phpunit ~4MB)
$testingDirs = @("fakerphp", "phpunit", "mockery", "hamcrest", "phar-io", "sebastian", "myclabs", "theseer", "staabm")
$existingTestingDirs = @()
foreach ($dir in $testingDirs) {
    if (Test-Path "$sourceDir\vendor\$dir") { $existingTestingDirs += $dir }
}

if ($existingTestingDirs.Count -gt 0) {
    Write-Host "`n[5/7] Mengompresi Vendor Testing (vendor-testing.zip)..." -ForegroundColor Cyan
    Write-Host "   -> Package: $($existingTestingDirs -join ', ')" -ForegroundColor Gray
    $testingZipTemp = Join-Path $tempBuildDir "vendor-testing"
    $testingCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $testingZipTemp -includePaths $existingTestingDirs -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $testingCount file testing." -ForegroundColor Gray
}

# Grup B: Tool packages (nesbot/carbon, nikic, nunomaduro, psy, league, dll)
$toolsDirs = @("nesbot", "nikic", "nunomaduro", "psy", "league", "monolog", "guzzlehttp", "voku", "filp", "nette", "egulias")
$existingToolsDirs = @()
foreach ($dir in $toolsDirs) {
    if (Test-Path "$sourceDir\vendor\$dir") { $existingToolsDirs += $dir }
}

if ($existingToolsDirs.Count -gt 0) {
    Write-Host "`n[6/7] Mengompresi Vendor Tools (vendor-tools.zip)..." -ForegroundColor Cyan
    Write-Host "   -> Package: $($existingToolsDirs -join ', ')" -ForegroundColor Gray
    $toolsZipTemp = Join-Path $tempBuildDir "vendor-tools"
    $toolsCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $toolsZipTemp -includePaths $existingToolsDirs -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $toolsCount file tools." -ForegroundColor Gray
}

# Grup C: Sisanya (misc)
$allGroupedDirs = @("laravel", "symfony", "filament", "spatie", "composer") + $testingDirs + $toolsDirs
$vendorSubDirs = Get-ChildItem -Path "$sourceDir\vendor" -Directory
$miscDirs = @()
foreach ($dir in $vendorSubDirs) {
    if ($allGroupedDirs -notcontains $dir.Name) {
        $miscDirs += $dir.Name
    }
}

if ($miscDirs.Count -gt 0) {
    Write-Host "`n[7/7] Mengompresi Vendor Misc (vendor-misc.zip)..." -ForegroundColor Cyan
    Write-Host "   -> Package: $($miscDirs -join ', ')" -ForegroundColor Gray
    $miscZipTemp = Join-Path $tempBuildDir "vendor-misc"
    $miscCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $miscZipTemp -includePaths $miscDirs -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $miscCount file misc." -ForegroundColor Gray
}


# --- PROSES SPLITTING ---
Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host "       [EVALUASI DAN PEMISAHAN UKURAN FILE ZIP - 10MB]    " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# Proses Core
Split-ZipFile -zipPath $coreZipTemp -outputFolder $outputDir

# Proses Vendor Framework
Split-ZipFile -zipPath $frameworkZipTemp -outputFolder $outputDir

# Proses Vendor Filament (jika ada)
if ($hasFilament) {
    Split-ZipFile -zipPath $filamentZipTemp -outputFolder $outputDir
}

# Proses Vendor Testing
if ($existingTestingDirs.Count -gt 0) {
    Split-ZipFile -zipPath $testingZipTemp -outputFolder $outputDir
}

# Proses Vendor Tools
if ($existingToolsDirs.Count -gt 0) {
    Split-ZipFile -zipPath $toolsZipTemp -outputFolder $outputDir
}

# Proses Vendor Misc
if ($miscDirs.Count -gt 0) {
    Split-ZipFile -zipPath $miscZipTemp -outputFolder $outputDir
}


# --- PENYELESAIAN ---
Write-Host "`n[+] Menyalin instrumen deployment unzip.php ke output..." -ForegroundColor Yellow
$unzipSource = Join-Path $sourceDir "unzip.php"
$unzipDest = Join-Path $outputDir "unzip.php"
if (Test-Path $unzipSource) {
    Copy-Item $unzipSource -Destination $unzipDest -Force
    Write-Host "   -> unzip.php berhasil disalin." -ForegroundColor Gray
} else {
    Write-Host "   [!] Warning: File unzip.php tidak ditemukan di root proyek!" -ForegroundColor Red
}

# Bersihkan temporary workspace kompresi
Write-Host "[*] Melakukan pembersihan file workspace sementara..." -ForegroundColor Yellow
Remove-Item $tempBuildDir -Recurse -Force | Out-Null

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host "  PROSES SELESAI DENGAN GEMILANG! PROYEK SIAP DI-UPLOAD   " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "Silakan buka folder: $outputDir" -ForegroundColor White
Write-Host "1. Upload SEMUA file dari folder tersebut ke root folder hosting (htdocs / public_html)." -ForegroundColor White
Write-Host "2. Akses http://domain-anda.com/unzip.php untuk mengaktifkan dashboard." -ForegroundColor White
Write-Host "3. Masukkan password keamanan Anda, lalu lakukan unzip dengan satu klik!" -ForegroundColor White
Write-Host "----------------------------------------------------------" -ForegroundColor Cyan
```

---

## 5. Template B — `unzip.php` (deployer di server)

> Simpan sebagai `unzip.php` di **root proyek** (sebelum menjalankan `build_split_zip.ps1`) supaya
> ikut tersalin ke folder output. Ganti `DEPLOY_PASSWORD`.
>
> Deployer ini melengkapi `build_split_zip.ps1`: ia mengenali `core.zip` / `vendor-*.zip`
> beserta pecahannya (`*.zip.001`, `.002`, …), menggabungkan part, mengekstrak ke folder tempat
> file ini berada, mem-backup `/.env` agar tidak tertimpa saat re-deploy, dan menyediakan
> editor `.env`, runner Artisan, serta diagnostik.

```php
<?php
/**
 * Split-Zip Deployer untuk Shared Hosting (InfinityFree)
 * Pasangan dari build_split_zip.ps1.
 *
 * Letakkan file ini di root htdocs bersama seluruh arsip
 * (core.zip, vendor-*.zip, atau pecahannya *.zip.001, *.zip.002, ...).
 *
 * ⚠️  HAPUS file ini setelah proses deploy selesai.
 */

// ================== KONFIGURASI ==================
const DEPLOY_PASSWORD = 'UbahPasswordIni123!';
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
    $zipPath   = $parts[0];
    $merged    = null;

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

    // extractTo (C-level) menyelesaikan ekstraksi dalam hitungan detik -> anti timeout
    $extracted = $zip->extractTo($root);
    $count     = $zip->numFiles;
    $zip->close();
    if ($merged) {
        @unlink($merged);
    }

    if ($hasEnv && file_exists($envBackup)) {
        @copy($envBackup, $envPath);
        @unlink($envBackup);
    }

    if (!$extracted) {
        return ['ok' => false, 'msg' => 'Gagal mengekstrak: ' . basename($zipPath)];
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

function runArtisan(string $root, string $command): array
{
    $autoload  = $root . '/vendor/autoload.php';
    $bootstrap = $root . '/bootstrap/app.php';
    if (!file_exists($autoload)) {
        return ['ok' => false, 'out' => 'vendor/autoload.php tidak ditemukan. Ekstrak arsip terlebih dahulu.'];
    }
    try {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        ob_start();
        require_once $autoload;
        $app    = require_once $bootstrap;
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $status = $kernel->call($command, ['--force' => true]);
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
    $res     = updateEnv($root, [
        'APP_ENV'        => 'production',
        'APP_DEBUG'      => $_POST['app_debug'] ?? 'false',
        'APP_URL'        => rtrim('https://' . ($_SERVER['HTTP_HOST'] ?? 'domain-anda.com'), '/'),
        'DB_HOST'        => $_POST['db_host'] ?? '127.0.0.1',
        'DB_PORT'        => $_POST['db_port'] ?? '3306',
        'DB_DATABASE'    => $_POST['db_database'] ?? '',
        'DB_USERNAME'    => $_POST['db_username'] ?? '',
        'DB_PASSWORD'    => $_POST['db_password'] ?? '',
        'SESSION_DRIVER' => $_POST['session_driver'] ?? 'file',
        'CACHE_STORE'    => $_POST['cache_store'] ?? 'file',
        'QUEUE_CONNECTION' => 'sync',
    ]);
    $message = $res === true
        ? alert('success', 'Konfigurasi .env disimpan.')
        : alert('error', $res);
}

// ================== AKSI: ARTISAN ==================
if ($authed && $action === 'artisan' && isset($_POST['artisan_command'])) {
    $allowed = ['key:generate', 'migrate', 'migrate:fresh', 'db:seed', 'storage:link', 'optimize:clear', 'config:cache', 'route:cache', 'view:cache'];
    $command = $_POST['artisan_command'];
    if (!in_array($command, $allowed, true)) {
        $message = alert('error', 'Perintah tidak diizinkan.');
    } else {
        $res     = runArtisan($root, $command);
        $console = $res['out'];
        $message = $res['ok']
            ? alert('success', "Perintah <code>{$command}</code> berhasil dijalankan.")
            : alert('error', "Gagal menjalankan <code>{$command}</code>.");
    }
}

// ================== AKSI: FIX PERMISSIONS ==================
if ($authed && $action === 'fix_perms') {
    $dirs = [
        '/storage', '/storage/app', '/storage/app/public', '/storage/framework',
        '/storage/framework/cache', '/storage/framework/cache/data', '/storage/framework/sessions',
        '/storage/framework/testing', '/storage/framework/views', '/storage/logs', '/bootstrap/cache',
    ];
    $fixed = 0;
    foreach ($dirs as $dir) {
        $path = $root . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        @chmod($path, 0755);
        $fixed++;
    }
    if (!file_exists($root . '/storage/logs/.gitignore')) {
        @file_put_contents($root . '/storage/logs/.gitignore', "*\n!.gitignore\n");
    }
    if (!file_exists($root . '/storage/framework/cache/data/.gitignore')) {
        @file_put_contents($root . '/storage/framework/cache/data/.gitignore', "*\n!.gitignore\n");
    }
    $message = alert('success', "{$fixed} folder storage/bootstrap diperbaiki & dibuat writable.");
}

// ================== AKSI: DIAGNOSA ==================
$diag = null;
if ($authed && $action === 'diagnose') {
    $diag = [];
    $diag['php'] = ['value' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.1', '>=')];

    $critical = [
        'index.php'                 => $root . '/index.php',
        '.htaccess'                 => $root . '/.htaccess',
        'public/index.php'          => $root . '/public/index.php',
        'public/build/manifest.json' => $root . '/public/build/manifest.json',
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
    foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'zip', 'curl', 'fileinfo', 'gd'] as $ext) {
        $diag['extensions'][$ext] = extension_loaded($ext);
    }

    $diag['env'] = ['exists' => file_exists($root . '/.env')];
    if ($diag['env']['exists']) {
        $envContent = file_get_contents($root . '/.env');
        $diag['env']['has_app_key']  = (bool) preg_match('/^APP_KEY=base64:.+/m', $envContent);
        $diag['env']['app_debug']    = (bool) preg_match('/^APP_DEBUG=true/m', $envContent);
        $diag['env']['db_connection'] = preg_match('/^DB_CONNECTION=(\w+)/m', $envContent, $m) ? $m[1] : '-';
    }

    $diag['boot'] = null;
    if (file_exists($root . '/vendor/autoload.php')) {
        try {
            ob_start();
            require_once $root . '/vendor/autoload.php';
            $app = require_once $root . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Http\Kernel::class);
            ob_end_clean();
            $diag['boot'] = ['ok' => true, 'msg' => 'Laravel berhasil di-boot.'];
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

$groups  = archiveGroups($root);
$hasFiles = !empty($groups);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Split-Zip Deployer</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',system-ui,sans-serif; background:linear-gradient(135deg,#0f0c29,#302b63,#24243e); color:#e2e8f0; min-height:100vh; padding:30px 15px; }
  .wrap { max-width:760px; margin:0 auto; }
  h1 { text-align:center; font-size:26px; margin-bottom:6px; background:linear-gradient(90deg,#f472b6,#c084fc,#60a5fa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .sub { text-align:center; color:#94a3b8; font-size:13px; margin-bottom:24px; }
  .card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:22px; margin-bottom:18px; }
  .card h2 { font-size:16px; margin-bottom:14px; color:#f1f5f9; }
  .alert { padding:12px 16px; border-radius:9px; margin-bottom:16px; font-size:14px; }
  .alert.success { background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3); color:#86efac; }
  .alert.error { background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#fca5a5; }
  .alert.warn { background:rgba(250,204,21,.12); border:1px solid rgba(250,204,21,.3); color:#fde68a; }
  .alert.info { background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }
  .file { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid rgba(255,255,255,.06); font-size:13px; color:#cbd5e1; }
  .btn { padding:12px 18px; border:none; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; color:#fff; background:linear-gradient(135deg,#3b82f6,#2563eb); }
  .btn:hover { filter:brightness(1.1); }
  .btn.green { background:linear-gradient(135deg,#10b981,#059669); }
  .btn.amber { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1c1917; }
  .btn.violet { background:linear-gradient(135deg,#8b5cf6,#7c3aed); }
  .btn.red { background:linear-gradient(135deg,#ef4444,#dc2626); }
  .btn.dark { background:linear-gradient(135deg,#334155,#1e293b); }
  .row { display:flex; gap:10px; flex-wrap:wrap; }
  .row > * { flex:1; min-width:150px; }
  label { font-size:12px; color:#a5b4fc; font-weight:600; text-transform:uppercase; display:block; margin-bottom:5px; }
  input, select { width:100%; background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:10px 12px; color:#fff; font-size:14px; margin-bottom:12px; }
  pre { background:#1e1e1e; border:1px solid rgba(255,255,255,.15); border-radius:9px; padding:14px; font-size:12px; color:#86efac; white-space:pre-wrap; max-height:300px; overflow:auto; }
  .check { font-size:13px; padding:4px 0; }
  .ok { color:#86efac; } .bad { color:#fca5a5; font-weight:600; } .warn { color:#fde68a; }
  .foot { text-align:center; font-size:12px; color:#475569; margin-top:20px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🚀 Split-Zip Deployer</h1>
  <p class="sub">Deployment &amp; diagnostik untuk shared hosting</p>

  <?= $message ?>

  <?php if (!$authed): ?>
    <div class="card">
      <h2>🔐 Masuk</h2>
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <label>Password Deployer</label>
        <input type="password" name="password" required autofocus>
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
          <button type="submit" name="extract" class="btn green" style="width:100%">📦 Ekstrak Semua Arsip</button>
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
        <div class="row">
          <div><label>DB Host</label><input type="text" name="db_host" value="<?= htmlspecialchars(getEnvValue($root, 'DB_HOST', '127.0.0.1')) ?>" required></div>
          <div style="max-width:120px"><label>Port</label><input type="text" name="db_port" value="<?= htmlspecialchars(getEnvValue($root, 'DB_PORT', '3306')) ?>" required></div>
        </div>
        <div class="row">
          <div><label>Database</label><input type="text" name="db_database" value="<?= htmlspecialchars(getEnvValue($root, 'DB_DATABASE')) ?>" required></div>
          <div><label>Username</label><input type="text" name="db_username" value="<?= htmlspecialchars(getEnvValue($root, 'DB_USERNAME')) ?>" required></div>
        </div>
        <label>Password Database</label>
        <input type="password" name="db_password" value="<?= htmlspecialchars(getEnvValue($root, 'DB_PASSWORD')) ?>">
        <div class="row">
          <div>
            <label>Session Driver</label>
            <?php $s = getEnvValue($root, 'SESSION_DRIVER', 'file'); ?>
            <select name="session_driver">
              <option value="file" <?= $s === 'file' ? 'selected' : '' ?>>file (aman untuk awal)</option>
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
          <option value="false" <?= $d === 'false' ? 'selected' : '' ?>>false (produksi)</option>
          <option value="true" <?= $d === 'true' ? 'selected' : '' ?>>true (debugging)</option>
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
          <option value="key:generate">key:generate (wajib jika APP_KEY kosong)</option>
          <option value="migrate">migrate (buat tabel baru)</option>
          <option value="db:seed">db:seed (isi data awal)</option>
          <option value="storage:link">storage:link</option>
          <option value="optimize:clear">optimize:clear</option>
          <option value="config:cache">config:cache</option>
          <option value="route:cache">route:cache</option>
          <option value="view:cache">view:cache</option>
          <option value="migrate:fresh">migrate:fresh (⚠️ RESET DATABASE)</option>
        </select>
        <button type="submit" class="btn green" style="width:100%">Jalankan</button>
      </form>
    </div>

    <div class="card">
      <h2>🛠️ Tools</h2>
      <div class="row">
        <form method="POST"><input type="hidden" name="action" value="fix_perms"><button class="btn" style="width:100%">🔧 Fix Permissions</button></form>
        <a href="?action=diagnose" class="btn amber" style="text-align:center; text-decoration:none; display:block">🔍 Diagnosa</a>
      </div>
      <?php if ($console): ?><pre style="margin-top:14px"><?= htmlspecialchars($console) ?></pre><?php endif; ?>
    </div>

    <?php if ($diag): ?>
      <div class="card">
        <h2>🔍 Hasil Diagnosa</h2>
        <div class="check <?= $diag['php']['ok'] ? 'ok' : 'bad' ?>"><?= $diag['php']['ok'] ? '✅' : '❌' ?> PHP <?= htmlspecialchars($diag['php']['value']) ?> (butuh ≥ 8.1)</div>
        <h2 style="margin-top:14px">File Kritis</h2>
        <?php foreach ($diag['files'] as $label => $exists): ?>
          <div class="check <?= $exists ? 'ok' : 'bad' ?>"><?= $exists ? '✅' : '❌' ?> <?= htmlspecialchars($label) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">Writable</h2>
        <?php foreach ($diag['writable'] as $label => $w): ?>
          <div class="check <?= $w === true ? 'ok' : ($w === null ? 'warn' : 'bad') ?>"><?= $w === true ? '✅' : ($w === null ? '⚠️ tidak ada' : '❌ tidak writable') ?> <?= htmlspecialchars($label) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">Ekstensi PHP</h2>
        <?php foreach ($diag['extensions'] as $ext => $loaded): ?>
          <div class="check <?= $loaded ? 'ok' : 'bad' ?>"><?= $loaded ? '✅' : '❌' ?> <?= htmlspecialchars($ext) ?></div>
        <?php endforeach; ?>
        <h2 style="margin-top:14px">.env</h2>
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

  <div class="foot">⚠️ Hapus <code>unzip.php</code> dari hosting setelah deploy selesai.</div>
</div>
<script>
function confirmCommand(form) {
  var v = form.artisan_command.value;
  if (v === 'migrate:fresh') {
    return confirm('PERINGATAN: migrate:fresh akan MENGHAPUS SEMUA TABEL dan seluruh data di database. Lanjutkan?');
  }
  if (v === 'migrate' || v === 'db:seed') {
    return confirm('Jalankan perintah "' + v + '"?');
  }
  return true;
}
</script>
</body>
</html>
```

---

## 6. Template C — File Pendukung di Root Proyek

Ketiga file berikut ikut terpaket ke `core.zip` dan wajib ada di root proyek. Tanpa ini, Laravel
tidak bisa diakses dari `htdocs` karena web root berada di root, bukan di `public/`.

### 6.1 `index.php` (entry point htdocs)

```php
<?php

/**
 * Shared Hosting Laravel Entry Point
 * Meneruskan semua request ke Laravel public/index.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Jika yang diminta adalah file statis di dalam public/, biarkan Apache melayaninya
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

try {
    require_once __DIR__ . '/public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Application Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
```

### 6.2 `.htaccess` (rewrite root)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
```

### 6.3 `public/.htaccess`

Gunakan file bawaan Laravel (front controller ke `public/index.php`), lalu tambahkan blok
performa di bawahnya: `mod_deflate` (Gzip), `mod_brotli`, `mod_expires` (cache 1 tahun untuk
asset statis, `no-cache` untuk HTML/PHP), `mod_headers` (`X-Content-Type-Options: nosniff`,
`Access-Control-Allow-Origin: *` untuk font/CSS lintas domain), dan `FileETag None`.
Anda bisa menyalin blok lengkapnya dari `public/.htaccess` proyek ini.

> **Catatan penting:** Jangan simpan file `.env` versi lokal ke hosting, dan pastikan
> `APP_URL` / `ASSET_URL` menunjuk ke `https://domain-anda.com` supaya URL aset Vite
> (`/build/...`) resolve dengan benar. Nilai ini diisi otomatis oleh `unzip.php`.

---

## 7. Menyesuaikan untuk Proyek Lain

1. **Ganti nama proyek.** Ubah `$projectName = "EunaGlow"` di `build_split_zip.ps1`.
   Perlu diketahui: pada script ini `$projectName` hanya dideklarasikan dan **tidak dipakai**
   untuk penamaan file — nama file output diambil dari nama grup (`core`, `vendor-*`).
   Jadi penggantian nama bersifat kosmetik, bukan keharusan.
2. **Sesuaikan grup vendor.** Cocokkan `$testingDirs` dan `$toolsDirs` dengan `composer.json`
   proyek baru. Package yang tidak terdaftar di grup mana pun otomatis masuk `vendor-misc.zip`,
   jadi script tidak akan "kehilangan" dependency — hanya pembagiannya yang berbeda.
3. **Ganti `DEPLOY_PASSWORD`** di `unzip.php`.
4. **Sesuaikan `$excludeList`** di bagian `core.zip` bila proyek punya folder khas
   (mis. `public/files`, `storage/app/private`).
5. **Pastikan layout entry point sama.** Jika proyek baru belum punya `index.php` +
   `.htaccess` di root yang meneruskan request ke `public/index.php`, salin dari Template C.

---

## 8. Troubleshooting

| Gejala | Penyebab & Solusi |
| --- | --- |
| `[!] Warning: File unzip.php tidak ditemukan di root proyek!` | `unzip.php` belum disimpan di root proyek. Salin Template B ke root proyek, lalu jalankan ulang script. |
| `composer install` gagal / `vendor` tidak ditemukan | Script menghentikan proses. Jalankan `composer install` (lokal) dulu. |
| Halaman tampil polos, CSS/JS 404 | Sisa file `public/hot` ikut terpaket, atau `public/build/` belum dibuild. Hapus `public/hot` lalu `npm run build`, paket ulang. `unzip.php` juga otomatis menghapus `hot` saat ekstrak. |
| `Script timeout` saat ekstrak | Vendor tidak terpecah. Pastikan `vendor-*.zip` dipisah (bukan satu `vendor.zip` besar) dan ekstrak satu grup per aksi. |
| Error 500 setelah ekstrak | Buka **Diagnosa** di `unzip.php`: cek `vendor/autoload.php`, `.env` + `APP_KEY`, folder writable, lalu uji **Laravel Boot Test**. |
| `SQLSTATE: Access denied` | Kredensial DB salah. Perbaiki lewat form `.env` di `unzip.php`, lalu `optimize:clear`. |
| Perubahan kode tidak muncul | Jalankan `optimize:clear` (dan `view:cache`/`config:cache` setelah stabil). |
| ZIP gagal dibuka di server | Salah satu part rusak/kurang. Pastikan **semua** `.zip.001`, `.002`, … terupload lengkap dan namanya tidak berubah. |

---

## 9. Checklist Keamanan Pasca-Deploy

- [ ] `unzip.php` **sudah dihapus** dari hosting.
- [ ] `APP_DEBUG=false` dan `APP_ENV=production`.
- [ ] `APP_KEY` sudah terisi (`key:generate`).
- [ ] Semua file `*.zip*` sudah terhapus dari hosting (otomatis oleh deployer).
- [ ] Password database tidak dipakai ulang sebagai `DEPLOY_PASSWORD`.
- [ ] `.env` tidak dapat diakses publik (berada di luar `public/`, sudah benar pada layout ini).
