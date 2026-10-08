#!/usr/bin/env bash
#
# Build the upload archive for cPanel / shared hosting.
#
#   bash deploy/build-package.sh [output.zip]
#
# The archive holds ONE folder:
#
#   almarah-platform/   upload it to your account, then move it into the folder
#                       your site is served from (public_html, or
#                       public_html/donate) and edit config.php. It is the
#                       website and the application at the same time.
#
# Everything the browser may reach (index.php, assets/, uploads/) is in that
# folder, and everything it may not reach (config.php, storage/, the code) is
# refused by .htaccess files — both the main one and one inside each private
# folder — so nothing has to be copied anywhere by hand.
#
# What is deliberately excluded, and why it matters:
#   tests/, phpunit.xml   only needed on a development machine
#   .env                  contains APP_KEY and database credentials
#   storage/app/*         contains this machine's app-key.txt and
#                         installed.lock - shipping them would make the new
#                         site claim it is already installed, and would give
#                         two sites the same encryption key
#   storage/logs/*        old logs
#   .phpunit.cache/       test run leftovers
#
# Run it from the project root, or from anywhere: the script finds the root
# from its own location.

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output="${1:-$project_root/../almarah-platform-cpanel.zip}"

composer_bin="$(command -v composer || true)"
if [ -z "$composer_bin" ]; then
    for candidate in /usr/local/bin/composer /tmp/composer "$HOME/composer.phar"; do
        [ -x "$candidate" ] && composer_bin="$candidate" && break
    done
fi

if [ -z "$composer_bin" ]; then
    echo "Composer was not found." >&2
    echo "Install it, or run:  composer install --no-dev --optimize-autoloader" >&2
    echo "inside a copy of this folder and zip that copy yourself." >&2
    exit 1
fi

staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT

echo "Staging in $staging/almarah-platform ..."
mkdir -p "$staging/almarah-platform"

rsync -a \
    --exclude '.git/' \
    --exclude 'tests/' \
    --exclude '.env' \
    --exclude 'storage/app/*' \
    --exclude 'storage/logs/*' \
    --exclude '.phpunit.cache/' \
    --exclude 'phpunit.xml' \
    --exclude '*.zip' \
    --exclude '*.DS_Store' \
    "$project_root/" "$staging/almarah-platform/"

echo "Installing production dependencies ..."
(cd "$staging/almarah-platform" && COMPOSER_ALLOW_SUPERUSER=1 "$composer_bin" install \
    --no-dev --optimize-autoloader --no-interaction --quiet)

# Belt and braces: whatever composer left behind, these must not be in the zip.
# (setup-key.txt is no longer created by anything -- the installer needs no key
# -- but a development tree that predates that change may still hold one.)
rm -rf "$staging/almarah-platform/tests" \
       "$staging/almarah-platform/.phpunit.cache" \
       "$staging/almarah-platform/phpunit.xml" \
       "$staging/almarah-platform/.env" \
       "$staging/almarah-platform/storage/app/app-key.txt" \
       "$staging/almarah-platform/storage/app/installed.lock" \
       "$staging/almarah-platform/storage/app/setup-key.txt" \
       "$staging/almarah-platform/vendor/phpunit" \
       "$staging/almarah-platform/vendor/phar-io" \
       "$staging/almarah-platform/vendor/sebastian" \
       "$staging/almarah-platform/vendor/myclabs" \
       "$staging/almarah-platform/vendor/nikic" \
       "$staging/almarah-platform/vendor/theseer"

# The application recreates these on the first page load; keeping the folders
# (with their .gitkeep) preserves the layout the guides describe.
mkdir -p "$staging/almarah-platform/storage/app" \
         "$staging/almarah-platform/storage/logs" \
         "$staging/almarah-platform/storage/cache" \
         "$staging/almarah-platform/storage/uploads" \
         "$staging/almarah-platform/uploads"

# The private folders must arrive closed. Their .htaccess is part of the
# project, so a missing one here is a build mistake in the repository — except
# for vendor/, which Composer writes and never adds a protection file to. That
# one used to be required as well, so this script stopped on every fresh
# checkout with "vendor/.htaccess is missing from the build" and never produced
# an archive; the folder was then uploaded as Git holds it, without vendor/,
# and the site answered every request with HTTP 500. It is copied in place of
# failing.
for folder in app bin bootstrap config database deploy resources routes storage uploads; do
    if [ ! -f "$staging/almarah-platform/$folder/.htaccess" ]; then
        echo "ERROR: $folder/.htaccess is missing from the build." >&2
        exit 1
    fi
done

if [ -d "$staging/almarah-platform/vendor" ] \
   && [ ! -f "$staging/almarah-platform/vendor/.htaccess" ]; then
    cp "$staging/almarah-platform/app/.htaccess" \
       "$staging/almarah-platform/vendor/.htaccess"
    echo "Added the missing vendor/.htaccess (Composer does not ship one)."
fi

# The instructions that ship beside it.
cp "$staging/almarah-platform/START-HERE.txt" "$staging/START-HERE.txt"

rm -f "$output"
(cd "$staging" && zip -q -r "$output" almarah-platform START-HERE.txt)

echo
echo "Archive: $output"
du -h "$output" | awk '{print "Size:    " $1}'
echo "Contents:"
echo "  files: $(unzip -l "$output" | tail -1 | awk '{print $2}')"
echo "  almarah-platform/  upload it into public_html (or public_html/donate)"
echo "  START-HERE.txt     the short instructions"

# '.env.example' is meant to ship; the real .env is not.
for forbidden in '/\.env$' 'phpunit' 'app-key\.txt' 'installed\.lock' 'setup-key\.txt'; do
    matches="$(unzip -l "$output" | grep -E "$forbidden" || true)"
    if [ -n "$matches" ]; then
        echo "  WARNING: the archive contains files that should not be there:" >&2
        echo "$matches" >&2
    fi
done
echo "  secret/development files: none found"
