import type { Component } from 'vue';

/*
 * The client half of `kit.toasts.driver` `inertiaui` (docs/toasts.md#inertia-notifications). The kit never imports @inertiaui/notifications-vue itself, so a
 * host on the kit's own Toaster builds without it; a host that moved hands the package's Toaster and toast() over once, in app.ts, with
 * `bridgeInertiaUIToasts({ Toaster, toast })` (both from @inertiaui/notifications-vue; docs/toasts.md has the setup).
 * The shell's <Toaster> then mounts the package's Toaster at the kit's resolved position and offsets, and keeps the trigger (pulse, fly-out, anchored),
 * the `kit:toast` history event, `kit:toast:show` and the new-version notice working on top of it.
 */
type ToastCall = (title: string, options?: Record<string, unknown>) => string;
export type InertiaUIToastFunction = ToastCall & {
    dismiss: (id?: string) => void;
};
export type InertiaUIToasts = {
    Toaster: Component;
    toast: InertiaUIToastFunction;
};

let bridged: InertiaUIToasts | null = null;

export function bridgeInertiaUIToasts(parts: InertiaUIToasts): void {
    bridged = parts;
}

export function inertiaUIToasts(): InertiaUIToasts {
    if (!bridged)
        throw new Error(
            "kit.toasts.driver is inertiaui, but the app has not called bridgeInertiaUIToasts({ Toaster, toast }) from '@inertiaui/notifications-vue' in app.ts.",
        );
    return bridged;
}
