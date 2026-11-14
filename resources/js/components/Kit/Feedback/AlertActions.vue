<script setup lang="ts">
import { provideSurface } from '@/components/Kit/Surface/surface';
import { alertActionsText, type AlertActions } from './alertVariants';

// An Alert's actions row: it gives a kit Button inside the alert's tone and `actionsStyle` (provideSurface, as SurfaceReset gives an overlay the default
// surface), so no caller passes a class, and its text colour to a plain link or button there. Internal to Alert.
const props = defineProps<{ actions: AlertActions }>();
provideSurface(() => ({
    tone: 'default',
    shape: 'default',
    alert: props.actions,
}));
</script>
<template>
    <div
        :class="alertActionsText(actions.variant, actions.tier)"
        :data-actions-style="actions.style"
    >
        <slot />
    </div>
</template>
