#!/usr/bin/env bash
set -Eeuo pipefail

backup_root="${FTC_BACKUP_DIR:?FTC_BACKUP_DIR is required}"
app_current="${FTC_APP_CURRENT:-/srv/fisch-trade-center/current}"
thumbnail_dir="${FTC_THUMBNAIL_DIR:-$app_current/storage/app/public/catalog-thumbnails}"

case "$backup_root" in
  /*) ;;
  *) echo "FTC_BACKUP_DIR must be absolute" >&2; exit 1 ;;
esac
[[ "$backup_root" != "/" ]] || { echo "Refusing root backup directory" >&2; exit 1; }

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$backup_root/hourly" "$backup_root/daily"
work_dir="$(mktemp -d "$backup_root/.backup.XXXXXX")"
trap 'rm -rf -- "$work_dir"' EXIT

PGPASSWORD="${DB_PASSWORD:-}" pg_dump \
  --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" \
  --username="${DB_USERNAME:?DB_USERNAME is required}" --dbname="${DB_DATABASE:?DB_DATABASE is required}" \
  --format=custom --no-owner --no-acl --file="$work_dir/database.dump"

row_counts_sql="select 'users', count(*) from users union all select 'catalog_items', count(*) from catalog_items union all select 'value_snapshots', count(*) from value_snapshots union all select 'listings', count(*) from listings union all select 'negotiations', count(*) from negotiations union all select 'deals', count(*) from deals order by 1;"
PGPASSWORD="${DB_PASSWORD:-}" psql \
  --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" \
  --username="$DB_USERNAME" --dbname="$DB_DATABASE" --tuples-only --no-align --field-separator=$'\t' \
  --command="$row_counts_sql" > "$work_dir/row-counts.tsv"

if [[ -d "$thumbnail_dir" ]]; then
  tar -C "$(dirname "$thumbnail_dir")" -czf "$work_dir/catalog-thumbnails.tar.gz" "$(basename "$thumbnail_dir")"
else
  tar -czf "$work_dir/catalog-thumbnails.tar.gz" --files-from /dev/null
fi

(
  cd "$work_dir"
  sha256sum database.dump catalog-thumbnails.tar.gz row-counts.tsv > manifest.sha256
)

hourly_path="$backup_root/hourly/$stamp"
mv -- "$work_dir" "$hourly_path"
trap - EXIT

if [[ "$(date -u +%H)" == "00" && ! -e "$backup_root/daily/${stamp:0:8}" ]]; then
  cp -a -- "$hourly_path" "$backup_root/daily/${stamp:0:8}"
fi

rotate() {
  local directory="$1" keep="$2" entries=() index target
  mapfile -t entries < <(find "$directory" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)
  for ((index=keep; index<${#entries[@]}; index++)); do
    target="$directory/${entries[$index]}"
    [[ "$target" == "$directory/"* ]] || { echo "Unsafe rotation target" >&2; exit 1; }
    rm -rf -- "$target"
  done
}

rotate "$backup_root/hourly" 48
rotate "$backup_root/daily" 14
echo "$hourly_path"
