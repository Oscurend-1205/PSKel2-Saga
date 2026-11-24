<#
.SYNOPSIS
    Premium Laravel Split-Zip Packager for Shared Hosting (InfinityFree / cPanel)
.DESCRIPTION
    Script ini memaketkan aplikasi Laravel Shambat secara cerdas menjadi arsip ZIP terpisah di bawah 10MB:
    1. core.zip          : Kode aplikasi Laravel inti (kecuali vendor, node_modules, .git, .env).
    2. vendor-framework  : Laravel Framework, Symfony, dan root vendor loader.
    3. vendor-tools      : Midtrans SDK, Guzzle, Carbon, Spatie, dan dependensi produksi inti.
    4. vendor-testing    : FakerPHP, PHPUnit, Mockery, Sebastian (jika ada).
    5. vendor-misc       : Sisa package vendor lainnya yang dideteksi secara dinamis.
    Semua zip yang melebihi 9.5MB akan dipecah secara dinamis menjadi part .zip.001, .zip.002, dst.
#>

# Konfigurasi Utama
$projectName = "Shambat"
$sourceDir = (Get-Item .).FullName
$outputDir = Join-Path $sourceDir "build_infinityfree"
$tempBuildDir = Join-Path $sourceDir "build_temp_workspace"

Clear-Host
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   [SHAMBAT LARAVEL DEPLOYMENT - SPLIT-ZIP ARCHITECT]     " -ForegroundColor Cyan
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
        
        # Skip semua file arsip zip agar tidak ikut ter-deploy / terkompresi ulang
        if ($normalizedRelative -like '*.zip' -or $normalizedRelative -like '*.zip.*') { continue }

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
Write-Host "`n[1/5] Mengompresi File Utama Aplikasi (core.zip)..." -ForegroundColor Cyan
$coreZipTemp = Join-Path $tempBuildDir "core.zip"
$excludeList = @(
    "vendor", "node_modules", ".git", "build_infinityfree", "build_temp_workspace",
    ".env", "public/uploads", "storage/app/public",
    # File development & artefak lokal - tidak perlu ter-deploy ke production
    "unzip.php", "tests", "phpunit.xml", ".phpunit.result.cache",
    "build_split_zip.ps1", "prepare-deploy.md", "presentation.md", "PANDUAN-SAGA-BRIDGE.md",
    "presentasi.php", "presentasi.html", "presentasi-saga-outbox.html"
)
$coreCount = Compress-FolderToZip -sourcePath $sourceDir -zipPath $coreZipTemp -excludePaths $excludeList
Write-Host "   -> Berhasil memaketkan $coreCount file inti aplikasi." -ForegroundColor Gray

# B. Analisis dan Kompresi Vendor
if (-not (Test-Path "$sourceDir\vendor")) {
    Write-Host "[!] Folder 'vendor' tidak ditemukan! Silakan jalankan 'composer install' terlebih dahulu." -ForegroundColor Red
    Remove-Item $tempBuildDir -Recurse -Force
    exit
}

# Framework Group (laravel, symfony, composer, autoload)
Write-Host "`n[2/5] Mengompresi Vendor Framework (vendor-framework.zip)..." -ForegroundColor Cyan
$frameworkZipTemp = Join-Path $tempBuildDir "vendor-framework"
$frameworkCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $frameworkZipTemp -includePaths @("laravel", "symfony") -includeVendorRootFiles $true -entryPrefix "vendor"
Write-Host "   -> Berhasil memaketkan $frameworkCount file framework." -ForegroundColor Gray

# Tools & SDK Group (midtrans, guzzlehttp, nesbot, spatie, dll)
Write-Host "`n[3/5] Mengompresi Vendor Tools & Midtrans SDK (vendor-tools.zip)..." -ForegroundColor Cyan
$toolsDirs = @("midtrans", "guzzlehttp", "nesbot", "nikic", "nunomaduro", "psy", "league", "monolog", "voku", "filp", "nette", "egulias", "carbonphp", "ramsey", "brick", "spatie")
$existingToolsDirs = @()
foreach ($dir in $toolsDirs) {
    if (Test-Path "$sourceDir\vendor\$dir") { $existingToolsDirs += $dir }
}
$toolsZipTemp = Join-Path $tempBuildDir "vendor-tools"
$toolsCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $toolsZipTemp -includePaths $existingToolsDirs -entryPrefix "vendor"
Write-Host "   -> Berhasil memaketkan $toolsCount file tools & Midtrans ($($existingToolsDirs -join ', '))." -ForegroundColor Gray

# Testing Group (fakerphp, phpunit, dll)
Write-Host "`n[4/5] Mengompresi Vendor Testing (vendor-testing.zip)..." -ForegroundColor Cyan
$testingDirs = @("fakerphp", "phpunit", "mockery", "hamcrest", "phar-io", "sebastian", "myclabs", "theseer", "staabm")
$existingTestingDirs = @()
foreach ($dir in $testingDirs) {
    if (Test-Path "$sourceDir\vendor\$dir") { $existingTestingDirs += $dir }
}
if ($existingTestingDirs.Count -gt 0) {
    $testingZipTemp = Join-Path $tempBuildDir "vendor-testing"
    $testingCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $testingZipTemp -includePaths $existingTestingDirs -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $testingCount file testing." -ForegroundColor Gray
}

# Misc Group (Sisanya)
Write-Host "`n[5/5] Memindai dan mengompresi sisa dependensi vendor (vendor-misc.zip)..." -ForegroundColor Cyan
$allGroupedDirs = @("laravel", "symfony", "composer") + $toolsDirs + $testingDirs
$vendorSubDirs = Get-ChildItem -Path "$sourceDir\vendor" -Directory
$miscDirs = @()
foreach ($dir in $vendorSubDirs) {
    if ($allGroupedDirs -notcontains $dir.Name) {
        $miscDirs += $dir.Name
    }
}
if ($miscDirs.Count -gt 0) {
    $miscZipTemp = Join-Path $tempBuildDir "vendor-misc"
    $miscCount = Compress-FolderToZip -sourcePath "$sourceDir\vendor" -zipPath $miscZipTemp -includePaths $miscDirs -entryPrefix "vendor"
    Write-Host "   -> Berhasil memaketkan $miscCount file misc ($($miscDirs -join ', '))." -ForegroundColor Gray
}

# --- PROSES SPLITTING ---
Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host "       [EVALUASI DAN PEMISAHAN UKURAN FILE ZIP - 10MB]    " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# Proses Core
Split-ZipFile -zipPath $coreZipTemp -outputFolder $outputDir

# Proses Vendor Framework
Split-ZipFile -zipPath $frameworkZipTemp -outputFolder $outputDir

# Proses Vendor Tools
Split-ZipFile -zipPath $toolsZipTemp -outputFolder $outputDir

# Proses Vendor Testing
if ($existingTestingDirs.Count -gt 0) {
    Split-ZipFile -zipPath $testingZipTemp -outputFolder $outputDir
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
    Write-Host "   -> unzip.php berhasil disalin." -ForegroundColor Green
} else {
    Write-Host "   [!] Warning: File unzip.php tidak ditemukan di root proyek!" -ForegroundColor Red
}

# Bersihkan temporary workspace kompresi
Write-Host "[*] Melakukan pembersihan file workspace sementara..." -ForegroundColor Yellow
Remove-Item $tempBuildDir -Recurse -Force | Out-Null

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host "  PROSES SELESAI DENGAN GEMILANG! PROYEK SIAP DI-UPLOAD   " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "Folder Output: $outputDir" -ForegroundColor White
Write-Host "1. Buka File Manager hosting (misal InfinityFree) -> masuk folder htdocs." -ForegroundColor White
Write-Host "2. Upload SEMUA file dari folder '$outputDir' ke htdocs." -ForegroundColor White
Write-Host "3. Akses http://domain-anda.com/unzip.php di browser." -ForegroundColor White
Write-Host "4. Masukkan password 'ShambatDeploy123!', lalu klik 'Ekstrak Semua Arsip'." -ForegroundColor White
Write-Host "5. Jalankan perintah Artisan (key:generate, migrate, db:seed) langsung dari web!" -ForegroundColor White
Write-Host "----------------------------------------------------------" -ForegroundColor Cyan

