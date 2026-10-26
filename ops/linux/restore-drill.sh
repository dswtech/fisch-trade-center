#!/usr/bin/env bash
set -Eeuo pipefail

backup_path="${1:?Usage: restore-drill.sh /absolute/backup/path}"
restore_db="${FTC_RESTORE_DATABASE:-fisch_trade_center_restore_drill}"
db_host="${DB_HOST:-127.0.0.1}"

[[ "$backup_path" = /* && -d "$backup_path" ]] || { echo "Backup path must be an existing absolute directory" >&2; exit 1; }
[[ "$restore_db" == *_restore_drill ]] || { echo "Restore database must end in _restore_drill" >&2; exit 1; }
[[ "$db_host" == "127.0.0.1" || "$db_host" == "localhost" ]] || { echo "Restore drill is restricted to a local disposable PostgreSQL host" >&2; exit 1; }

(
  cd "$backup_path"
  sha256sum --check manifest.sha256
)

media_dir="$(mktemp -d)"
cleanup() {
  rm -rf -- "$media_dir"
  PGPASSWORD="${DB_PASSWORD:-}" dropdb --if-exists --host="$db_host" --port="${DB_PORT:-5432}" --username="${DB_USERNAME:?}" "$restore_db" >/dev/null
}
trap cleanup EXIT

PGPASSWORD="${DB_PASSWORD:-}" dropdb --if-exists --host="$db_host" --port="${DB_PORT:-5432}" --username="${DB_USERNAME:?}" "$restore_db" >/dev/null
PGPASSWORD="${DB_PASSWORD:-}" createdb --host="$db_host" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" "$restore_db"
PGPASSWORD="${DB_PASSWORD:-}" pg_restore --exit-on-error --no-owner --no-acl --host="$db_host" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" --dbname="$restore_db" "$backup_path/database.dump"

table_count="$(PGPASSWORD="${DB_PASSWORD:-}" psql --host="$db_host" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" --dbname="$restore_db" --tuples-only --no-align --command="select count(*) from information_schema.tables where table_schema = 'public';")"
migration_count="$(PGPASSWORD="${DB_PASSWORD:-}" psql --host="$db_host" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" --dbname="$restore_db" --tuples-only --no-align --command="select count(*) from migrations;")"
[[ "$table_count" -gt 0 && "$migration_count" -gt 0 ]] || { echo "Restored database validation failed" >&2; exit 1; }
row_counts_sql="select 'users', count(*) from users union all select 'catalog_items', count(*) from catalog_items union all select 'value_snapshots', count(*) from value_snapshots union all select 'listings', count(*) from listings union all select 'negotiations', count(*) from negotiations union all select 'deals', count(*) from deals order by 1;"
PGPASSWORD="${DB_PASSWORD:-}" psql --host="$db_host" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" --dbname="$restore_db" --tuples-only --no-align --field-separator=$'\t' --command="$row_counts_sql" > "$media_dir/restored-row-counts.tsv"
diff --unified "$backup_path/row-counts.tsv" "$media_dir/restored-row-counts.tsv"

tar -xzf "$backup_path/catalog-thumbnails.tar.gz" -C "$media_dir"
tar -tzf "$backup_path/catalog-thumbnails.tar.gz" >/dev/null
echo "Restore drill passed: tables=$table_count migrations=$migration_count"
