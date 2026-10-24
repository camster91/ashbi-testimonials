#!/usr/bin/env bash
# Build the install ZIP from an explicit runtime allowlist. Tests, CI and docs stay out.
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' ashbi-testimonials.php | tr -d '\r')
constant_version=$(sed -n "s/^define( 'ABT_VERSION', '\([^']*\)' );/\1/p" ashbi-testimonials.php)
stable_version=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '\r')
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][a-zA-Z0-9.-]+)?$ ]] || { echo 'Invalid plugin version' >&2; exit 1; }
[[ "$version" == "$constant_version" && "$version" == "$stable_version" ]] || { echo 'Plugin header, constant and stable tag must agree' >&2; exit 1; }
for metadata in blocks/*/block.json; do
  grep -q '"version": "'"$version"'"' "$metadata" || { echo "Block version mismatch: $metadata" >&2; exit 1; }
done
rm -rf release dist
mkdir -p release/ashbi-testimonials dist
cp -r ashbi-testimonials.php uninstall.php readme.txt LICENSE admin assets blocks includes languages release/ashbi-testimonials/
(cd release && zip -qr "../dist/ashbi-testimonials-${version}.zip" ashbi-testimonials)
echo "dist/ashbi-testimonials-${version}.zip"
