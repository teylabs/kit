#!/bin/sh
# vue-tsc over every Vue and TypeScript file the kit installs into a host's resources/js (each feature.json `files` entry, from core, shell, session-ui,
# workspace-ui, settings, mcp, notes…), as a host would compile them: writes each stub to typecheck/build/js at its installed path, so `@/components/Kit/*`,
# `@/pages/*`, `@kit/ui` and `@kit/shell` resolve as in a host, then type-checks with the versions pinned in typecheck/package.json, and again against
# @inertiajs/vue3 3.7.1 (the `@inertiajs/vue3-37` alias, tsconfig.inertia-3.7.json), the oldest Inertia 3 a host is known to run: its <Form> types differ (no
# onHttpException). Both runs repeat on vue-tsc 2.2.12 (the `vue-tsc-2` alias), which a host on vue-tsc 2 still runs: it can't parse an inline object type in a
# template expression (TS1005) where vue-tsc 3 can. The host's shadcn-vue components are typed `any` (typecheck/shims.d.ts). Run `npm ci` in typecheck/ first.
set -eu
root=$(cd "$(dirname "$0")/.." && pwd)
rm -rf "$root/typecheck/build"
mkdir -p "$root/typecheck/build/js"
php -r '
    $count = 0;
    foreach (glob($argv[1]."/resources/features/*/feature.json") as $manifest) {
        $feature = dirname($manifest);
        foreach (json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR)["files"] ?? [] as $target => $file) {
            if (! str_starts_with($target, "resources/js/") || ! preg_match("/\\.(vue|ts)\$/", $target)) {
                continue;
            }
            $to = $argv[2]."/".substr($target, strlen("resources/js/"));
            is_dir(dirname($to)) || mkdir(dirname($to), 0777, true);
            copy($feature."/".$file["stub"], $to) || exit(1);
            $count++;
        }
    }
    if ($count === 0) {
        fwrite(STDERR, "No Vue or TypeScript stubs found in the feature manifests.\n");
        exit(1);
    }
    echo "$count installed Vue and TypeScript files.\n";
' "$root" "$root/typecheck/build/js"
cd "$root/typecheck"
version() { node -p "JSON.parse(require('fs').readFileSync('node_modules/$1/package.json', 'utf8')).version"; }
# Each vue-tsc by its path: both packages name their bin `vue-tsc`, so node_modules/.bin holds only one of them.
for vue_tsc in vue-tsc vue-tsc-2; do
    node "node_modules/$vue_tsc/bin/vue-tsc.js" -p tsconfig.json
    echo "Every installed Vue and TypeScript file type-checks (vue-tsc $(version "$vue_tsc"), @inertiajs/vue3 $(version @inertiajs/vue3))."
    node "node_modules/$vue_tsc/bin/vue-tsc.js" -p tsconfig.inertia-3.7.json
    echo "Every installed Vue and TypeScript file type-checks (vue-tsc $(version "$vue_tsc"), @inertiajs/vue3 $(version @inertiajs/vue3-37))."
done
# Vue's SFC compiler resolves defineProps<Tey.Kit.…>() from the shipped types/kit.d.ts (a host's plugin-vue script.globalTypeFiles).
node sfc-kit-types.mjs
