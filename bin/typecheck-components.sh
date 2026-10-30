#!/bin/sh
# vue-tsc over the core feature's component stubs, session-ui's guest pages and workspace-ui's Workspace pages, as a host would compile them: copies them to
# typecheck/build with the `.stub` dropped, then type-checks with the versions pinned in typecheck/package.json, and again against @inertiajs/vue3 3.7.1 (the
# `@inertiajs/vue3-37` alias, tsconfig.inertia-3.7.json), the oldest Inertia 3 a host is known to run: its <Form> types differ (no onHttpException). Both runs
# repeat on vue-tsc 2.2.12 (the `vue-tsc-2` alias), which a host on vue-tsc 2 still runs: it can't parse an inline object type in a template expression (TS1005)
# where vue-tsc 3 can. The host's shadcn-vue components are typed `any` (typecheck/shims.d.ts). Run `npm ci` in typecheck/ first.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
build="$root/typecheck/build/components"
rm -rf "$root/typecheck/build"
mkdir -p "$build"
cp -R "$root/resources/features/core/components/Kit" "$build/Kit"
mkdir -p "$root/typecheck/build/pages/Auth"
for page in Login ForgotPassword ResetPassword ConfirmPassword VerifyEmail; do
    cp -R "$root/resources/features/session-ui/pages/Auth/$page" "$root/typecheck/build/pages/Auth/$page"
done
cp -R "$root/resources/features/workspace-ui/pages/Workspace" "$root/typecheck/build/pages/Workspace"
find "$root/typecheck/build" -name '*.stub' | while read -r file; do mv "$file" "${file%.stub}"; done
cd "$root/typecheck"
# Each vue-tsc by its path: both packages name their bin `vue-tsc`, so node_modules/.bin holds only one of them.
vue_tsc=node_modules/vue-tsc/bin/vue-tsc.js
node "$vue_tsc" -p tsconfig.json
echo "Kit components, session-ui guest pages and workspace-ui pages type-check (@inertiajs/vue3 $(node -p "JSON.parse(require('fs').readFileSync('node_modules/@inertiajs/vue3/package.json', 'utf8')).version"))."
node "$vue_tsc" -p tsconfig.inertia-3.7.json
echo "Kit components, session-ui guest pages and workspace-ui pages type-check (@inertiajs/vue3 $(node -p "JSON.parse(require('fs').readFileSync('node_modules/@inertiajs/vue3-37/package.json', 'utf8')).version"))."
vue_tsc_2=node_modules/vue-tsc-2/bin/vue-tsc.js
node "$vue_tsc_2" -p tsconfig.json
node "$vue_tsc_2" -p tsconfig.inertia-3.7.json
echo "Kit components, session-ui guest pages and workspace-ui pages type-check on vue-tsc $(node -p "JSON.parse(require('fs').readFileSync('node_modules/vue-tsc-2/package.json', 'utf8')).version") too."
# Vue's SFC compiler resolves defineProps<Tey.Kit.…>() from the shipped types/kit.d.ts (a host's plugin-vue script.globalTypeFiles).
node sfc-kit-types.mjs
