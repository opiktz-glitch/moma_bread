<#
.SYNOPSIS
    Upload website Moma Bread ke hosting lewat FTP, sumbernya langsung dari git.

.DESCRIPTION
    TinkerHost tidak menyediakan terminal/SSH, jadi "git pull di server"
    tidak bisa dilakukan. Sebagai gantinya, file yang di-upload diambil
    dari `git archive`, sehingga isinya PERSIS sama dengan isi repository:

      - config/config.php  TIDAK ikut (sengaja, berisi password database)
      - backups/           TIDAK ikut (arsip lokal)
      - .git/              TIDAK ikut
      - .htaccess          tetap ikut (wajib untuk URL bersih & keamanan)

    Tidak ada berkas yang dihapus di server. Jadi kalau sebuah file dihapus
    dari git, hapus manual lewat File Manager.

.PARAMETER DryRun
    Hanya menampilkan daftar file yang akan di-upload, tanpa mengirim.

.PARAMETER Config
    Lokasi file konfigurasi. Default: tools/deploy.config.json

.EXAMPLE
    .\tools\deploy.ps1 -DryRun
    .\tools\deploy.ps1
#>

[CmdletBinding()]
param(
    [switch]$DryRun,
    [string]$Config
)

$ErrorActionPreference = 'Stop'

# Matikan progress bar Expand-Archive supaya output deploy mudah dibaca.
$ProgressPreference = 'SilentlyContinue'

Set-Location (Join-Path $PSScriptRoot '..')

# ---------------------------------------------------------------------------
#  1. Cek kondisi repository
# ---------------------------------------------------------------------------

$commit  = (& git rev-parse --short HEAD).Trim()
$subject = (& git log -1 --pretty=format:%s).Trim()
$dirty   = & git status --porcelain

Write-Host ''
Write-Host '  Deploy Moma Bread -> FTP' -ForegroundColor Cyan
Write-Host "  Commit : $commit - $subject" -ForegroundColor DarkGray

if ($dirty) {
    Write-Host ''
    Write-Warning 'Ada perubahan yang BELUM di-commit:'
    $dirty | Select-Object -First 10 | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    Write-Host '  File yang diupload diambil dari commit terakhir, bukan dari file'
    Write-Host '  yang baru saja Anda ubah. Jalankan git add/commit dulu.'
    Write-Host ''
}

# ---------------------------------------------------------------------------
#  2. Baca konfigurasi
# ---------------------------------------------------------------------------

$ConfigFile = if ($Config) { $Config } else { Join-Path $PSScriptRoot 'deploy.config.json' }

if (-not (Test-Path $ConfigFile)) {
    Write-Host ''
    Write-Host "  Konfigurasi belum ada: $ConfigFile" -ForegroundColor Red
    Write-Host '  Salin deploy.config.example.json menjadi deploy.config.json, lalu'
    Write-Host '  isi username, password, dan host FTP dari cPanel TinkerHost.'
    exit 1
}

$cfg = Get-Content $ConfigFile -Raw | ConvertFrom-Json

foreach ($k in @('ftpHost', 'ftpUser', 'ftpPass', 'ftpPath')) {
    if (-not $cfg.$k -or ([string]$cfg.$k) -match 'GANTI|xxxx|example') {
        Write-Host ''
        Write-Host "  Nilai '$k' di $ConfigFile masih contoh / kosong." -ForegroundColor Red
        Write-Host '  Isi dulu dengan data asli dari cPanel TinkerHost.'
        exit 1
    }
}

$ftpUrl = 'ftp://{0}:{1}@{2}/' -f $cfg.ftpUser, $cfg.ftpPass, $cfg.ftpHost
$root   = ([string]$cfg.ftpPath).TrimEnd('/')

# ---------------------------------------------------------------------------
#  3. Siapkan isi dari git
# ---------------------------------------------------------------------------

$stamp   = Get-Date -Format 'yyyyMMdd-HHmmss'
$workDir = Join-Path $env:TEMP "moma-deploy-$stamp"
$zipPath = "$workDir.zip"

New-Item -ItemType Directory -Path $workDir -Force | Out-Null

& git archive --format=zip --output=$zipPath HEAD

if (-not (Test-Path $zipPath)) {
    Write-Host '  Gagal membuat arsip dari git.' -ForegroundColor Red
    exit 1
}

Expand-Archive -Path $zipPath -DestinationPath $workDir -Force

# Pengaman: file kredensial tidak boleh ikut terupload.
$bocor = @(
    (Join-Path $workDir 'config/config.php')
    (Join-Path $workDir 'install.php')
) | Where-Object { Test-Path $_ }

if ($bocor) {
    Write-Host '  BERHENTI: arsip berisi berkas terlarang:' -ForegroundColor Red
    $bocor | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
    exit 1
}

$files = Get-ChildItem $workDir -Recurse -File
$total = ($files | Measure-Object Length -Sum).Sum

Write-Host "  Isi    : $($files.Count) file, $([math]::Round($total / 1MB, 2)) MB" -ForegroundColor DarkGray
Write-Host "  Tujuan : $($cfg.ftpUser)@$($cfg.ftpHost)$root" -ForegroundColor DarkGray
Write-Host ''

if ($DryRun) {
    Write-Host '  MODE LATIHAN - tidak ada file yang dikirim.' -ForegroundColor Yellow
    Write-Host ''
    $files | Sort-Object FullName | ForEach-Object {
        $rel = $_.FullName.Substring($workDir.Length + 1).Replace('\', '/')
        Write-Host "    $rel" -ForegroundColor DarkGray
    }
    Write-Host ''
    Write-Host "  Total $($files.Count) file akan dikirim." -ForegroundColor Yellow
    Write-Host ''
    Remove-Item $workDir -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
    exit 0
}

# ---------------------------------------------------------------------------
#  4. Upload
# ---------------------------------------------------------------------------

$berhasil = 0
$gagal    = @()

foreach ($f in $files) {
    $rel    = $f.FullName.Substring($workDir.Length + 1).Replace('\', '/')
    $tujuan = "$ftpUrl$root/$rel"

    # -T = upload file, --ftp-create-dirs = buat folder di server otomatis
    $out = & curl.exe --silent --show-error --fail `
                       --ftp-create-dirs `
                       --ftp-ssl-optional `
                       --connect-timeout 20 `
                       --max-time 300 `
                       -T $f.FullName $tujuan 2>&1

    if ($LASTEXITCODE -eq 0) {
        $berhasil++
        Write-Host "    [ok]    $rel" -ForegroundColor Green
    } else {
        $gagal += $rel
        Write-Host "    [GAGAL] $rel" -ForegroundColor Red
        if ($out) { Write-Host "            $out" -ForegroundColor DarkRed }
    }
}

Remove-Item $workDir -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item $zipPath -Force -ErrorAction SilentlyContinue

Write-Host ''
Write-Host "  Selesai: $berhasil berhasil, $($gagal.Count) gagal" -ForegroundColor Cyan
Write-Host ''

if ($gagal.Count) {
    Write-Host '  Berkas yang gagal:' -ForegroundColor Red
    $gagal | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }
    Write-Host '  Periksa quota disk dan izin folder di cPanel.' -ForegroundColor Red
    exit 1
}

Write-Host '  Langkah berikutnya di server:' -ForegroundColor Cyan
Write-Host '   1. Pastikan config/config.php sudah dibuat dan diisi kredensial DB'
Write-Host '   2. Import deploy-moma_bread.sql lewat phpMyAdmin (hanya sekali)'
Write-Host '   3. Set izin 755 untuk assets/img/uploads dan backups'
Write-Host '   4. Login ke /admin lalu buka menu Status Server'
Write-Host ''
