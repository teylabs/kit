#!/bin/sh
# PHPStan over the PHP stubs, as a host that analyses the files kit:add or kit:update wrote would: copies each *.php.stub to a scratch tree with the `.stub`
# dropped and analyses it at level 8 against the kit's vendor (no Larastan, so `config()` and the facades stay loosely typed; level 9 needs Larastan's types).
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
cd "$root/resources/features"
find . -name '*.php.stub' | while read -r stub; do
    mkdir -p "$work/stubs/$(dirname "$stub")"
    cp "$stub" "$work/stubs/${stub%.stub}"
done
printf 'parameters:\n    level: 8\n    paths:\n        - %s/stubs\n' "$work" > "$work/phpstan.neon"
"$root/vendor/bin/phpstan" analyse -c "$work/phpstan.neon" --autoload-file="$root/vendor/autoload.php" --no-progress --memory-limit=1G
echo "PHP stubs pass PHPStan level 8."
