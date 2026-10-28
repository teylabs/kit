#!/bin/sh
# vue-tsc over the core feature's component stubs, as a host would compile them: copies them to typecheck/build with the `.stub` dropped, then type-checks
# with the versions pinned in typecheck/package.json. The host's shadcn-vue components are typed `any` (typecheck/shims.d.ts). Run `npm ci` in typecheck/ first.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
build="$root/typecheck/build/components"
rm -rf "$root/typecheck/build"
mkdir -p "$build"
cp -R "$root/resources/features/core/components/Kit" "$build/Kit"
find "$build" -name '*.stub' | while read -r file; do mv "$file" "${file%.stub}"; done
cd "$root/typecheck"
npx vue-tsc -p tsconfig.json
echo "Kit components type-check."
