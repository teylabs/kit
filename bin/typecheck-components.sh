#!/bin/sh
# vue-tsc over the core feature's component stubs and the shell's Layout and partials, as a host would compile them: copies them to typecheck/build with the `.stub` dropped, then type-checks
# with the versions pinned in typecheck/package.json. The host's shadcn-vue components are typed `any` (typecheck/shims.d.ts). Run `npm ci` in typecheck/ first.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
build="$root/typecheck/build/components"
rm -rf "$root/typecheck/build"
mkdir -p "$build"
cp -R "$root/resources/features/core/components/Kit" "$build/Kit"
# The shell's Layout and partials, importing the kit's primitives through `@kit/ui` and their own barrel through `@kit/shell`, as a host aliases them.
mkdir -p "$root/typecheck/build/shell"
cp -R "$root/resources/features/shell/pages/Workspace/Layout.vue.stub" "$root/resources/features/shell/pages/Workspace/Partials" "$root/typecheck/build/shell/"
find "$root/typecheck/build" -name '*.stub' | while read -r file; do mv "$file" "${file%.stub}"; done
cd "$root/typecheck"
npx vue-tsc -p tsconfig.json
echo "Kit components type-check."
# Vue's SFC compiler resolves defineProps<Tey.Kit.…>() from the shipped types/kit.d.ts (a host's plugin-vue script.globalTypeFiles).
node sfc-kit-types.mjs
