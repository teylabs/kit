<script setup lang="ts">
import {
    Bell,
    Building2,
    Calendar,
    ChartColumn,
    CreditCard,
    FileSignature,
    HandHeart,
    KeyRound,
    Mail,
    Newspaper,
    Palette,
    Plug,
    Receipt,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    User,
    Users,
} from '@lucide/vue';

// A settings section's icon by its lucide name (kebab case), from the server's `icon`. The set is the kit's own explicit allow-list (one import per name keeps the bundle bounded; docs/settings.md lists it); an unknown name is the generic Settings icon.
const icons = {
    bell: Bell,
    'building-2': Building2,
    calendar: Calendar,
    'chart-column': ChartColumn,
    'credit-card': CreditCard,
    'file-signature': FileSignature,
    'hand-heart': HandHeart,
    'key-round': KeyRound,
    mail: Mail,
    newspaper: Newspaper,
    palette: Palette,
    plug: Plug,
    receipt: Receipt,
    settings: Settings,
    'shield-check': ShieldCheck,
    'sliders-horizontal': SlidersHorizontal,
    user: User,
    users: Users,
} as const;
const props = defineProps<{ name?: string }>();
const icon =
    (props.name && icons[props.name as keyof typeof icons]) || Settings;
</script>
<template>
    <component :is="icon" class="size-5" aria-hidden="true" />
</template>
