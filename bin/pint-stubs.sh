#!/bin/sh
# Pint over the PHP stubs (resources/features and resources/adopt), as a host that runs Pint (Laravel's preset) on the files the kit generated would: copies each *.php.stub to a scratch tree
# with the `.stub` dropped and runs `pint --test`. A non-zero exit names the files. Pass --fix to rewrite the stubs in place instead.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
# The feature stubs, and the adopt files kit:scaffold --adopt writes (resources/adopt, under adopt/).
cd "$root/resources"
find features adopt -name '*.php.stub' | while read -r stub; do
    mkdir -p "$work/$(dirname "$stub")"
    cp "$stub" "$work/${stub%.stub}"
done
if [ "${1:-}" = "--fix" ]; then
    "$root/vendor/bin/pint" --preset laravel "$work" >/dev/null || true
    find "$work" -name '*.php' | while read -r file; do cp "$file" "$root/resources/${file#"$work"/}.stub"; done
    echo "Pint applied to the PHP stubs."
    exit 0
fi
"$root/vendor/bin/pint" --preset laravel --test "$work"
echo
echo "PHP stubs are Pint-clean (Laravel preset)."
