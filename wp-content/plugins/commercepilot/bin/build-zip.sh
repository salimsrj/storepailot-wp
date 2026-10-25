#!/usr/bin/env bash
# Builds an installable commercepilot-<version>.zip in ./build.
#
# Usage: bin/build-zip.sh
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="commercepilot"
VERSION="$(sed -n "s/.*define( 'COMMERCEPILOT_VERSION', '\([^']*\)' );.*/\1/p" "$PLUGIN_DIR/commercepilot.php")"
STABLE="$(sed -n 's/^Stable tag: *//p' "$PLUGIN_DIR/readme.txt" | tr -d '[:space:]')"
HEADER="$(sed -n 's/^ \* Version: *//p' "$PLUGIN_DIR/commercepilot.php" | tr -d '[:space:]')"

if [ -z "$VERSION" ] || [ "$VERSION" != "$STABLE" ] || [ "$VERSION" != "$HEADER" ]; then
	echo "Version mismatch: constant=$VERSION header=$HEADER readme=$STABLE" >&2
	exit 1
fi

for file in $(find "$PLUGIN_DIR" -name '*.php' -not -path '*/vendor/*'); do
	php -l "$file" > /dev/null
done
php "$PLUGIN_DIR/tests/test-security.php" > /dev/null
php "$PLUGIN_DIR/tests/test-settings.php" > /dev/null

BUILD_DIR="$PLUGIN_DIR/build"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$STAGE/$SLUG/"

mkdir -p "$BUILD_DIR"
ZIP="$BUILD_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"
(cd "$STAGE" && zip -rq "$ZIP" "$SLUG")

echo "Built $ZIP"
