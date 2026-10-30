#!/bin/sh
# vue-tsc over the core feature's component stubs, the shell's Layout and partials, and session-ui's guest pages, as a host would compile them: copies them to
# typecheck/build with the `.stub` dropped, then type-checks with the versions pinned in typecheck/package.json, and again against @inertiajs/vue3 3.7.1 (the
# `@inertiajs/vue3-37` alias, tsconfig.inertia-3.7.json), the oldest Inertia 3 a host is known to run: its <Form> types differ (no onHttpException). The host's
# shadcn-vue components are typed `any` (typecheck/shims.d.ts). Run `npm ci` in typecheck/ first.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
build="$root/typecheck/build/components"
rm -rf "$root/typecheck/build"
mkdir -p "$build"
cp -R "$root/resources/features/core/components/Kit" "$build/Kit"
# The shell's Layout and partials, importing the kit's primitives through `@kit/ui` and their own barrel through `@kit/shell`, as a host aliases them.
mkdir -p "$root/typecheck/build/shell"
cp -R "$root/resources/features/shell/pages/Workspace/Layout.vue.stub" "$root/resources/features/shell/pages/Workspace/Partials" "$root/typecheck/build/shell/"
# session-ui's guest pages, which bind Inertia's <Form> options.
mkdir -p "$root/typecheck/build/pages/Auth"
for page in Login Register ForgotPassword ResetPassword ConfirmPassword VerifyEmail; do
    cp -R "$root/resources/features/session-ui/pages/Auth/$page" "$root/typecheck/build/pages/Auth/$page"
done
find "$root/typecheck/build" -name '*.stub' | while read -r file; do mv "$file" "${file%.stub}"; done
cd "$root/typecheck"
npx vue-tsc -p tsconfig.json
echo "Kit components, shell and session-ui guest pages type-check (@inertiajs/vue3 $(node -p "JSON.parse(require('fs').readFileSync('node_modules/@inertiajs/vue3/package.json', 'utf8')).version"))."
npx vue-tsc -p tsconfig.inertia-3.7.json
echo "Kit components, shell and session-ui guest pages type-check (@inertiajs/vue3 $(node -p "JSON.parse(require('fs').readFileSync('node_modules/@inertiajs/vue3-37/package.json', 'utf8')).version"))."
# Vue's SFC compiler resolves defineProps<Tey.Kit.…>() from the shipped types/kit.d.ts (a host's plugin-vue script.globalTypeFiles).
node sfc-kit-types.mjs
