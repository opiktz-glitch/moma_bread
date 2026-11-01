#!/usr/bin/env bash
set -Eeuo pipefail

repo_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
web_root="/var/www/moma_bread"

command -v git >/dev/null 2>&1 || {
    echo "Git tidak ditemukan." >&2
    exit 1
}
command -v rsync >/dev/null 2>&1 || {
    echo "rsync tidak ditemukan." >&2
    exit 1
}

branch="$(git -C "$repo_dir" branch --show-current)"
if [[ "$branch" != "main" ]]; then
    echo "Deploy dibatalkan: branch aktif '$branch', seharusnya 'main'." >&2
    exit 1
fi

if [[ -n "$(git -C "$repo_dir" status --porcelain)" ]]; then
    echo "Deploy dibatalkan: repository memiliki perubahan lokal." >&2
    git -C "$repo_dir" status --short >&2
    exit 1
fi

if [[ ! -d "$web_root" ]]; then
    echo "Document root tidak ditemukan: $web_root" >&2
    exit 1
fi

if [[ ! -f "$web_root/config/config.php" ]]; then
    echo "Deploy dibatalkan: konfigurasi $web_root/config/config.php tidak ditemukan." >&2
    exit 1
fi

previous_commit="$(git -C "$repo_dir" rev-parse HEAD)"
git -C "$repo_dir" pull --ff-only origin main
current_commit="$(git -C "$repo_dir" rev-parse HEAD)"

if [[ "$previous_commit" == "$current_commit" ]]; then
    echo "Tidak ada commit baru; memastikan berkas situs tetap tersinkron."
else
    echo "Commit baru: $previous_commit -> $current_commit"
fi

sudo rsync -a --chown=www-data:www-data \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='config/config.php' \
    --exclude='assets/img/uploads/' \
    --exclude='backups/' \
    --exclude='README.md' \
    --exclude='DEPLOY-*.md' \
    --exclude='deploy-tzserver.sh' \
    "$repo_dir/" "$web_root/"

echo "Sinkronisasi situs selesai."
