<script setup lang="ts">
// A page typed with the kit's shipped Tey.Kit.* types (types/kit.d.ts, as a host includes vendor/tey/kit/types/kit.d.ts), nested namespaces and all. Compiled by
// bin/typecheck-components.sh; never rendered.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel & { shell: Tey.Kit.Features.Shell.Data.ShellData }>();
const kind: Tey.Kit.Features.Shell.Data.ShellKind = props.shell.kind;
</script>
<template>
    <p>{{ props.title }} {{ kind }}</p>
</template>
