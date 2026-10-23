#!/usr/bin/env bash
# Build the install ZIP from an explicit runtime allowlist. Tests, CI and docs stay out.
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' ashbi-testimonials.php | tr -d '\r')
rm -rf release dist
mkdir -p release/ashbi-testimonials dist
cp -r ashbi-testimonials.php uninstall.php readme.txt LICENSE admin assets includes languages release/ashbi-testimonials/
(cd release && zip -qr "../dist/ashbi-testimonials-${version}.zip" ashbi-testimonials)
echo "dist/ashbi-testimonials-${version}.zip"
