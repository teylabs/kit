<script setup lang="ts">
import Card from '@/components/Kit/Card/Card.vue';
import CardHeader from '@/components/Kit/Card/CardHeader.vue';
import CardBody from '@/components/Kit/Card/CardBody.vue';
import CardFooter from '@/components/Kit/Card/CardFooter.vue';
import CollectionCard from '@/components/Kit/Card/CollectionCard.vue';
import List from '@/components/Kit/Layout/List.vue';
import ListRow from '@/components/Kit/Layout/ListRow.vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';

// Card variants (?card-variants, docs/cards.md): `&theme=open` sets the open tokens on :root as a host's app.css would; `&dark`. Each case is a CollectionCard
// and a Card with header, body and footer: following the theme, and overridden to `ruled` and to `open`. Invented copy in a hub's words.
const query = new URLSearchParams(window.location.search);
if (query.has('dark')) document.documentElement.classList.add('dark');
if (query.get('theme') === 'open') {
    document.documentElement.style.setProperty('--kit-card-rule', '0px');
    document.documentElement.style.setProperty('--kit-card-title', '1rem');
}
const cases = [['theme', null], ['ruled', 'ruled'], ['open', 'open']] as const;
const seeAll = { label: 'All activity', url: '/@hub/activity' };
</script>
<template>
    <main class="bg-muted text-foreground grid min-h-screen content-start gap-4 p-4 sm:grid-cols-3 sm:p-8">
        <h1 class="text-xl font-semibold sm:col-span-3">Card variants</h1>
        <div v-for="[key, variant] in cases" :key="key" class="grid content-start gap-4" :data-case="key">
            <CollectionCard :section="{ title: `To review (${key})`, seeAll }" :level="2" :variant="variant" data-collection>
                <List label="To review">
                    <ListRow title="New enquiries"><template #meta><Badge tone="info">1</Badge></template></ListRow>
                    <ListRow title="Agreements unsigned"><template #meta><Badge tone="warning">2</Badge></template></ListRow>
                </List>
            </CollectionCard>
            <Card :variant="variant" data-plain>
                <CardHeader :title="`Money (${key})`" :level="2" />
                <CardBody class="py-4 text-sm">Invoices overdue: $819.25 owed</CardBody>
                <CardFooter>Updated today</CardFooter>
            </Card>
        </div>
    </main>
</template>
