#!/usr/bin/env bash
set -Eeuo pipefail

artifact="${1:?Usage: deploy.sh /absolute/release-artifact}"
app_root="${FTC_APP_ROOT:-/srv/fisch-trade-center}"
health_url="${FTC_HEALTH_URL:-https://127.0.0.1}"

[[ "$artifact" = /* && -d "$artifact" ]] || { echo "Artifact must be an existing absolute directory" >&2; exit 1; }
[[ "$app_root" = /* && "$app_root" != "/" ]] || { echo "FTC_APP_ROOT must be a safe absolute directory" >&2; exit 1; }
[[ "${FTC_OFF_HOST_BACKUP_READY:-no}" == "yes" ]] || { echo "Launch blocked: encrypted off-host backup is not ready" >&2; exit 1; }
[[ -f "$artifact/artisan" && -f "$artifact/composer.lock" && -f "$artifact/public/build/manifest.json" ]] || { echo "Incomplete release artifact" >&2; exit 1; }
[[ -f "$app_root/shared/.env" ]] || { echo "Missing shared .env" >&2; exit 1; }

release="$app_root/releases/$(date -u +%Y%m%dT%H%M%SZ)"
previous="$(readlink -f "$app_root/current" 2>/dev/null || true)"
mkdir -p "$app_root/releases" "$app_root/shared/storage"
mkdir -p "$app_root/shared/storage/app/public/catalog-thumbnails" "$app_root/shared/storage/framework/cache" "$app_root/shared/storage/framework/sessions" "$app_root/shared/storage/framework/views" "$app_root/shared/storage/logs"
cp -a -- "$artifact" "$release"
ln -sfn "$app_root/shared/.env" "$release/.env"
rm -rf -- "$release/storage"
ln -s "$app_root/shared/storage" "$release/storage"

FTC_APP_CURRENT="$app_root/current" "$(dirname "$0")/backup.sh" >/dev/null
pretend_output="$(cd "$release" && php artisan migrate --pretend --force)"
printf '%s\n' "$pretend_output"
has_migrations=true
grep -q "Nothing to migrate" <<<"$pretend_output" && has_migrations=false
if $has_migrations && [[ "${FTC_ALLOW_MIGRATE:-no}" != "yes" ]]; then
  echo "Persistent migration requires explicit FTC_ALLOW_MIGRATE=yes" >&2
  exit 1
fi

rollback() {
  if [[ -n "$previous" && -d "$previous" ]]; then
    ln -sfn "$previous" "$app_root/current.next"
    mv -Tf "$app_root/current.next" "$app_root/current"
    (cd "$previous" && php artisan up) || true
    systemctl reload php8.4-fpm || true
    systemctl restart fisch-trade-scheduler.service || true
  fi
}
trap rollback ERR

if $has_migrations && [[ -n "$previous" ]]; then
  (cd "$previous" && php artisan down --retry=60)
fi
if $has_migrations; then
  (cd "$release" && php artisan migrate --force)
fi
(cd "$release" && php artisan config:cache && php artisan route:cache && php artisan view:cache)
ln -sfn "$release" "$app_root/current.next"
mv -Tf "$app_root/current.next" "$app_root/current"
systemctl reload php8.4-fpm
systemctl restart fisch-trade-scheduler.service
(cd "$release" && php artisan ops:heartbeat)
(cd "$release" && php artisan up)
curl --fail --silent --show-error "$health_url/up" >/dev/null
curl --fail --silent --show-error "$health_url/ready" >/dev/null
trap - ERR
echo "Activated $release"
