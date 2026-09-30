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

.PARAMETER TesKoneksi
    Hanya mengecek apakah koneksi berhasil dan folder tujuan ada.
    Jalankan ini lebih dulu kalau belum pernah deploy - jauh lebih cepat
    daripada gagal di tengah upload dozens file.

.PARAMETER Config
    Lokasi file konfigurasi. Default: tools/deploy.config.json

.EXAMPLE
    .\tools\deploy.ps1 -TesKoneksi
    .\tools\deploy.ps1 -DryRun
    .\tools\deploy.ps1
#>

[CmdletBinding()]
param(
    [switch]$DryRun,
    [switch]$TesKoneksi,
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

# URL sengaja TANPA kredensial. Kredensial selalu lewat --user, supaya
# password dengan karakter khusus (@ : / # %) tidak merusak URL.
$kredensial = '{0}:{1}' -f $cfg.ftpUser, $cfg.ftpPass
$ftpUrl     = 'ftp://{0}/' -f $cfg.ftpHost
$root   = ([string]$cfg.ftpPath).TrimEnd('/')

# ---------------------------------------------------------------------------
#  2b. Tes koneksi (opsional, tapi sangat disarankan untuk deploy pertama)
# ---------------------------------------------------------------------------
#
#  Path folder adalah kesalahan paling sering terjadi: TinkerHost Free
#  memakai "htdocs", sedangkan "public_html" hanya untuk paket Pro.
#  Salah path => semua file gagal. Tes ini membuatnya jelas dalam detik.

if ($TesKoneksi) {
    Write-Host ''
    Write-Host '  Menguji koneksi FTP ...' -ForegroundColor Cyan

    # Daftar isi folder tujuan. Kalau path salah, server akan bilang 550.
    $uji = & curl.exe "--user" $kredensial `
                      --silent --show-error --fail `
                      --ssl --list-only "$ftpUrl$root/" 2>&1

    if ($LASTEXITCODE -eq 0) {
        $isi = @($uji | Where-Object { $_ -and $_ -notmatch '^\s*(Connected|220|226|150|221|230|331)\b' })

        Write-Host "  Koneksi  : berhasil ke $($cfg.ftpHost)" -ForegroundColor Green
        Write-Host "  Folder   : $root (ada)" -ForegroundColor Green

        if ($isi.Count) {
            Write-Host '  Isi folder:' -ForegroundColor DarkGray
            $isi | Select-Object -First 12 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
            if ($isi.Count -gt 12) {
                Write-Host "    ... dan $($isi.Count - 12) lagi" -ForegroundColor DarkGray
            }
        } else {
            Write-Host '  (folder kosong - normal untuk website yang baru)' -ForegroundColor DarkGray
        }

        Write-Host ''
        Write-Host '  Folder tujuan sudah benar. Jalankan tanpa -TesKoneksi untuk upload.' -ForegroundColor Green
        exit 0
    }

    $pesan = ($uji | Out-String).Trim()

    Write-Host "  Koneksi  : GAGAL" -ForegroundColor Red
    Write-Host "  Pesan    : $pesan" -ForegroundColor DarkRed
    Write-Host ''
    Write-Host '  Kemungkinan penyebab:' -ForegroundColor Yellow
    Write-Host '   - Password atau username salah' -ForegroundColor Yellow
    Write-Host '   - Host salah. TinkerHost Free memakai ftpupload.net,' -ForegroundColor Yellow
    Write-Host '     TinkerHost Pro memakai ftp.pro.tinkerhost.net' -ForegroundColor Yellow
    Write-Host '   - Folder tujuan tidak ada. TinkerHost Free memakai "htdocs",' -ForegroundColor Yellow
    Write-Host '     bukan "public_html". Coba: /htdocs' -ForegroundColor Yellow
    Write-Host ''
    Write-Host '  Cara cepat cek folder: login ke File Manager TinkerHost,' -ForegroundColor Cyan
    Write-Host '  lalu lihat folder mana yang sudah berisi website Anda.' -ForegroundColor Cyan
    exit 1
}

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
$setelahHapus = 0
$gagal    = @()

# Opsi bersama untuk setiap curl.
$opsiCurl = @(
    '--ftp-create-dirs',
    '--ssl',
    '--silent',
    '--show-error',
    '--fail',
    '--connect-timeout', '20',
    '--max-time', '300'
)

# Berkas sementara untuk menampung stderr curl.
$berkasGalat = [IO.Path]::GetTempFileName()

# PENTING: di sekitar pemanggilan curl, ErrorActionPreference diturunkan
# menjadi 'Continue'. PowerShell 5.1 membungkus stderr dari perintah
# native menjadi ErrorRecord, dan dengan 'Stop' MASIH melempar error -
# bahkan ketika stderr sudah dialihkan ke berkas. Akibatnya peringatan
# biasa dari curl (mis. "--ssl is an insecure option") akan menghentikan
# seluruh skrip. Pengalihan ke berkas saja tidak cukup.
$ErrorActionPreferenceAsli = $ErrorActionPreference
$ErrorActionPreference = 'Continue'

foreach ($f in $files) {
    $rel    = $f.FullName.Substring($workDir.Length + 1).Replace('\', '/')
    $tujuan = "$ftpUrl$root/$rel"

    # --ssl berarti "coba TLS, jatuh ke koneksi biasa bila server tidak
    # mendukungnya". Opsi --ftp-ssl-optional tidak pernah ada di curl.
    #
    # stderr Dialihkan ke BERKAS, bukan 2>&1. Kalau ditulis ke output,
    # PowerShell memakai $ErrorActionPreference='Stop' akan menganggap
    # peringatan biasa dari curl sebagai error fatal dan menghentikan skrip.
    & curl.exe "--user" $kredensial @opsiCurl `
               -T $f.FullName $tujuan 2>$berkasGalat
    $kode = $LASTEXITCODE

    if ($kode -eq 0) {
        $berhasil++
        Write-Host "    [ok]    $rel" -ForegroundColor Green
        continue
    }

    # Percobaan kedua: hapus berkas lama di server, lalu unggah ulang.
    # Sebagian server menolak menimpa berkas yang sudah ada (balasan 451).
    & curl.exe "--user" $kredensial "--ssl" `
               -Q "DELE $root/$rel" --list-only $ftpUrl 2>$null | Out-Null
    Start-Sleep -Seconds 1

    & curl.exe "--user" $kredensial @opsiCurl `
               -T $f.FullName $tujuan 2>$berkasGalat
    $kode = $LASTEXITCODE

    if ($kode -eq 0) {
        $berhasil++
        $setelahHapus++
        Write-Host "    [ok*]   $rel  (file lama dihapus dulu, lalu unggah ulang)" -ForegroundColor Green
        continue
    }

    $gagal += $rel
    Write-Host "    [GAGAL] $rel" -ForegroundColor Red

    # Buang peringatan --ssl, itu bukan penyebab kegagalan.
    $teks = (Get-Content $berkasGalat -Raw -ErrorAction SilentlyContinue)
    $bersih = ($teks -split "`r?`n") | Where-Object {
        $_.Trim() -and $_ -notmatch 'insecure option' -and $_ -notmatch '^Warning: instead'
    }
    if ($bersih) { $bersih | ForEach-Object { Write-Host "            $($_.Trim())" -ForegroundColor DarkRed } }
}

Remove-Item $workDir -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
Remove-Item $berkasGalat -Force -ErrorAction SilentlyContinue
$ErrorActionPreference = $ErrorActionPreferenceAsli

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
