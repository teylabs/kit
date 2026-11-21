<script setup lang="ts">
import ConnectionCard from '@/components/Kit/Layout/ConnectionCard.vue';
import DetailSection from '@/components/Kit/Layout/DetailSection.vue';
import List from '@/components/Kit/Layout/List.vue';
import ListRow from '@/components/Kit/Layout/ListRow.vue';
import SettingsSection from '@/components/Kit/Layout/SettingsSection.vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';

// The section shapes (?section-shapes, `&dark`; docs/settings-section.md, connection-card.md, detail-section.md, list.md), invented copy: a SettingsSection with
// a List inside and a footer action, ConnectionCards in each status (connected, not connected, checking, failed), a DetailSection with a List and one with an
// EmptyState, and a List straight on the page ground.
const disconnect = [{ label: 'Disconnect', url: '/@acme/settings/square', method: 'delete', key: 'disconnect' }];
const connect = [{ label: 'Connect', url: '/@acme/settings/square/connect', method: 'get', key: 'connect' }];
const save = [{ label: 'Save', url: '/@acme/settings/accounting', method: 'post', key: 'save' }];
</script>
<template>
    <main class="bg-background text-foreground mx-auto flex max-w-3xl flex-col gap-6 p-6">
        <h1 class="text-xl font-semibold">Section shapes</h1>
        <SettingsSection title="Accounts" description="Where each kind of sale is booked." :actions="save" data-case="settings">
            <List label="Accounts">
                <ListRow title="Room hire" description="4010 · Rental income" />
                <ListRow title="Front-desk sales" description="4020 · Sales" />
                <ListRow title="Memberships" description="4030 · Subscriptions" />
            </List>
        </SettingsSection>
        <ConnectionCard title="Square" description="Front-desk sales come in once each." status="connected" account="Hub café" connected-at="2026-11-02T10:00:00Z" last-checked-at="2026-11-21T09:30:00Z" :actions="disconnect" data-case="connected" />
        <ConnectionCard title="Xero" description="Book sales to your ledger." status="disconnected" :actions="connect" data-case="disconnected" />
        <ConnectionCard title="QuickBooks" status="checking" account="Acme Ltd" :actions="disconnect" data-case="checking" />
        <ConnectionCard title="Stripe" status="error" account="acct_test" last-checked-at="2026-11-21T09:00:00Z" error="The token was refused. Connect again to renew it." :actions="connect" data-case="error" />
        <DetailSection title="Bookings" description="This member's last three." data-case="detail">
            <List label="Bookings">
                <ListRow title="Meeting room A" description="Tue 18 Nov, 10:00–11:00" />
                <ListRow title="Hot desk" description="Wed 19 Nov, all day" />
            </List>
        </DetailSection>
        <DetailSection title="Invoices" description="An empty example: nothing raised yet." data-case="detail-empty">
            <EmptyState title="No invoices yet" body="Invoices show here once one is raised." compact />
        </DetailSection>
        <section aria-label="Recent imports" class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Recent imports (a List on the page ground)</h2>
            <List label="Recent imports" data-case="ground-list">
            <ListRow title="Import 14" description="12 sales" />
            <ListRow title="Import 13" description="9 sales" />
        </List>
        </section>
    </main>
</template>
