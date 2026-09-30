#!/usr/bin/env bash
#
# Uji regresi untuk tools/deploy.ps1 dan .github/workflows/deploy.yml
#
# Latar belakang: versi pertama memakai opsi curl "--ftp-ssl-optional".
# Opsi itu tidak pernah ada di curl, sehingga SETIAP file gagal ter-upload
# dengan pesan "curl: option --ftp-ssl-optional: is unknown".
#
# Tes ini memeriksa hal yang paling rawan: nama opsi curl.
# Jalankan dari root project:
#
#     bash tools/uji-deploy.sh
#
# Git Bash (yang ikut terpasang bersama Git untuk Windows) juga bisa dipakai.

set -uo pipefail

cd "$(dirname "$0")/.."

# Host purpose-built yang tidak menyambung. Yang kita periksa hanyalah
# apakah curl LIVAK membaca opsi yang kita pakai - bukan koneksinya.
UJI="ftp://127.0.0.1:1/uji.txt"
GALAT="$(mktemp)"
GAGAL=0

cek() {
  nama="$1"; geser="$2"

  if curl $geser --silent --output /dev/null "$UJI" 2>"$GALAT"; then
    printf '  %-38s TIDAK TERUJI (tiba-tiba berhasil)\n' "$nama"
    return
  fi

  if grep -q 'is unknown' "$GALAT"; then
    printf '  %-38s SALAH - opsi tidak dikenal oleh curl\n' "$nama"
    GAGAL=$((GAGAL + 1))
  else
    printf '  %-38s benar\n' "$nama"
  fi
}

echo "== Opsi curl yang dipakai saat upload =="
cek "--user u:p"           "--user uji:uji"
cek "--ftp-create-dirs"    "--ftp-create-dirs"
cek "--ssl"                "--ssl"
cek "--silent"             "--silent"
cek "--show-error"         "--show-error"
cek "--fail"               "--fail"
cek "--connect-timeout"    "--connect-timeout 20"
cek "--max-time"           "--max-time 300"
cek "-T (upload)"          "-T /dev/null"

rm -f "$GALAT"

echo ""
echo "== Pemeriksaan tambahan =="

# Opsi usang yang pernah dipakai dan tidak boleh muncul lagi.
# Hanya baris perintah yang dihitung, bukan komentar yang menyebutnya,
# dan berkas tes ini sendiri dikecualikan supaya tidak cocok dengan dirinya.
ADA_USANG=$(grep -rn -- '--ftp-ssl-optional' tools/deploy.ps1 .github/workflows/ 2>/dev/null \
            | grep -vE '^[^:]+:[0-9]+:[[:space:]]*#' || true)

if [ -n "$ADA_USANG" ]; then
  echo "  DITEMUKAN opsi usang 'ftp-ssl-optional':"
  echo "$ADA_USANG" | sed 's/^/      /'
  GAGAL=$((GAGAL + 1))
else
  echo "  tidak ada pemakaian opsi usang             benar"
fi

# Berkas kredensial tidak boleh ada di dalam arsip deploy.
if git ls-files --error-unmatch config/config.php >/dev/null 2>&1; then
  echo "  config/config.php TER-TRACK di git              SALAH"
  GAGAL=$((GAGAL + 1))
else
  echo "  config/config.php tidak ikut ter-commit        benar"
fi

if git ls-files --error-unmatch tools/deploy.config.json >/dev/null 2>&1; then
  echo "  tools/deploy.config.json TER-TRACK di git      SALAH"
  GAGAL=$((GAGAL + 1))
else
  echo "  tools/deploy.config.json tidak ikut ter-commit benar"
fi

echo ""
if [ "$GAGAL" -gt 0 ]; then
  echo "GAGAL: $GAGAL masalah. Jangan jalankan deploy sebelum diperbaiki."
  exit 1
fi

echo "Semua pemeriksaan lolos. Opsi curl aman dipakai."
