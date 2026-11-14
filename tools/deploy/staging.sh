#!/usr/bin/env bash
set -euo pipefail

revision="${1:?A tested Git revision is required}"
[[ "$revision" =~ ^[a-f0-9]{40}$ ]] || exit 2
base=/home/u715355537/apps/hwnix-bill-staging
shared="$base/shared"
release="$base/releases/$revision"
web=/home/u715355537/domains/hwnix.com/public_html/staging-api
[[ "$(whoami)" == u715355537 ]] || exit 2
[[ "$(realpath "$base")" == "$base" ]] || exit 2
[[ "$(realpath "$shared")" == "$shared" ]] || exit 2
test -s "$shared/.env"
exec 9>"$base/deploy.lock"
flock -w 300 9

# Check the web root before changing the active release.
if [[ -L "$web" ]]; then
    [[ "$(readlink "$web")" == "$base/current" ]] || exit 2
elif [[ -d "$web" ]]; then
    unexpected="$(find "$web" -mindepth 1 ! -type d ! -name default.php -print -quit)"
    [[ -z "$unexpected" && ! -e "$base/initial-placeholder" ]] || {
        echo 'Staging document root contains unrecognized files; refusing replacement'; exit 2;
    }
else
    echo 'Expected staging web root is missing'; exit 2
fi

mkdir -p "$release"
[[ "$(realpath "$release")" == "$release" ]] || exit 2
tar -xzf "$base/incoming/$revision.tgz" -C "$release"
cd "$release"
for item in .env storage; do
    if [[ -L "$item" ]]; then
        [[ "$(readlink "$item")" == "$shared/$item" ]] || exit 2
    else
        [[ ! -e "$item" ]] || { echo 'Refusing an artifact containing runtime state'; exit 2; }
        ln -s "$shared/$item" "$item"
    fi
done
mkdir -p bootstrap/cache
php tools/deploy/staging-guard.php env
composer dump-autoload --no-dev --optimize --no-interaction
php tools/deploy/staging-guard.php assert

# Equivalent production preparation, strictly on the verified staging DB.
php artisan migrate --force
php artisan db:seed --class=PermissionsSeeder --force
php tools/deploy/staging-guard.php protect-bootstrap
php artisan db:seed --class=SystemUnitsSeeder --force
php artisan db:seed --class=CashBoxTypeSeeder --force
php tools/deploy/staging-guard.php legal
php artisan permission:cache-reset
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan config:cache
php artisan route:cache
# Hostinger disables PHP symlink/exec; create the configured link in SSH.
if [[ -L public/storage ]]; then
    [[ "$(readlink public/storage)" == "$shared/storage/app/public" ]] || exit 2
else
    [[ ! -e public/storage ]] || exit 2
    ln -s "$shared/storage/app/public" public/storage
fi
php tools/deploy/staging-guard.php assert
printf '%s\n' "$revision" > public/staging-revision.txt
printf 'User-agent: *\nDisallow: /\n' > public/robots.txt

previous="$(readlink "$base/current" || true)"
ln -sfn "$release" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"

if [[ -L "$web" ]]; then
    : # Verified above.
elif [[ -d "$web" ]]; then
    mv "$web" "$base/initial-placeholder"
    ln -s "$base/current" "$web"
else
    ln -s "$base/current" "$web"
fi

if ! curl --fail --silent --show-error --retry 3 --retry-delay 5 https://staging-api.hwnix.com/up; then
    if [[ -n "$previous" && "$previous" == "$base/releases/"* && -d "$previous" ]]; then
        ln -sfn "$previous" "$base/current.rollback"
        mv -Tf "$base/current.rollback" "$base/current"
        echo 'Restored previous staging code; database migrations are not reversed automatically.'
    fi
    exit 1
fi
echo "Activated staging revision $revision"
