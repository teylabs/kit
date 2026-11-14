<script setup lang="ts">
// The scaffolded dashboard's WidgetFrame (Partials/types.ts, at the path a host has it) against CollectionSectionData: a frame's `state` is exactly the
// collection's state union, so WidgetCard hands it to CollectionCard with no cast, and a state outside it is a type error. The server refuses any other
// (`dashboard_widget_state`). Its `sortBy` declares the same optional `token`. Compiled by bin/typecheck-components.sh; never rendered.
import type { CollectionSectionData } from '@kit/ui';
import type { WidgetFrame } from '@/pages/Workspace/Dashboard/Partials/types';

type Same<A, B> = [A] extends [B] ? ([B] extends [A] ? true : false) : false;
const same: Same<WidgetFrame['state'], NonNullable<CollectionSectionData['state']>> = true;
// @ts-expect-error a state the collection does not take
const loading: WidgetFrame['state'] = 'loading';
// The Sort by token the server adds, which CollectionSection sends back: the frame declares it, as CollectionSectionData's sortBy does.
type Token = NonNullable<WidgetFrame['sortBy']>['token'];
const token: Same<Token, NonNullable<CollectionSectionData['sortBy']>['token']> = true;
</script>
<template>
    <p>{{ same }} {{ loading }} {{ token }}</p>
</template>
