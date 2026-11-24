<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watchEffect,
} from 'vue';
import { Toaster as Sonner, toast } from 'vue-sonner';
import 'vue-sonner/style.css';
import { pageUnsaved } from '@/components/Kit/Form/formFrame';
import { useStickyBars } from '@/components/Kit/Layout/stickyBars';
import { useTopLayer } from '@/components/Kit/Overlay/layer';
import { inertiaUIToasts } from './inertiaui';
import { installNewVersion } from './newVersion';
import { fly, pulse } from './toastMotion';
import {
    flashedToast,
    fromInertiaUI,
    isAway,
    resolveTrigger,
    toastGate,
    toastPlacement,
    toastPlan,
    toastTiming,
    toInertiaUI,
    whenBack,
    type FlashedToast,
} from './toastPlacement';

/*
 * The app's one toast renderer, mounted once by the shell (Workspace Layout, GuestShell). It renders the `toast` a controller flashed (Tey\Kit\Features\Core\Toast) and decides
 * nothing: pages never call it, and there is no page-level toast API. Where it sits is the shell's (`kitToasts.position`, resolved by toastPlacement); below `sm`
 * it is bottom-center, above the registered sticky bars (useStickyBars) and the safe-area inset. An action is one link or request.
 * A toast `from` an action key meets its trigger (the rendered descriptor's `data-action`): fly-out animates from the trigger into the resolved position, anchored
 * shows a short "✓ message" bubble on the trigger instead of a shell toast, pulse rings the trigger beside a shell toast. No trigger found: a plain toast.
 * Reduced motion never flies: a fly-out is a toast and a pulse, and the bubble appears without animating.
 * How long it stays is the server's (`duration`, from kit.toasts.durations; null until dismissed). Shown while the viewer is away (tab hidden, window unfocused), a timed
 * toast waits: its duration starts when they are back.
 * It also installs, once, the reload after a deploy (newVersion.ts): a lazy chunk that fails to load reloads a page with nothing unsaved; otherwise, and on
 * an Inertia version change, it shows one persistent "A new version is available." toast with Reload.
 * With `kitToasts.driver` `inertiaui` (kit.toasts.driver), inertiaui/notifications delivers and renders the corner toasts: this mounts the package's Toaster
 * (handed over with bridgeInertiaUIToasts) at the same resolved position and offsets, and still meets each kit toast's trigger, finding the rendered toast by
 * its `data-toast-id`. Anchored toasts stay on the kit's `toast` key and render here as the bubble. The package times its own toasts while the tab is away.
 */
const page = usePage<{ kitToasts?: { position?: string; driver?: string } }>();
const iui =
    page.props.kitToasts?.driver === 'inertiaui' ? inertiaUIToasts() : null;
const bars = useStickyBars();
const small = ref(false);
const headerActions = ref(false);
const placement = computed(() =>
    toastPlacement(page.props.kitToasts?.position, {
        bars: { count: bars.count.value, height: bars.height.value },
        small: small.value,
        headerActions: headerActions.value,
    }),
);
const offset = computed(() =>
    placement.value.position.startsWith('top-')
        ? { top: `calc(var(--shell-top, 0px) + ${placement.value.offset}px)` }
        : {
              bottom: `calc(${placement.value.offset}px + env(safe-area-inset-bottom))`,
          },
);
const bubble = ref<{
    message: string;
    top: number;
    left: number;
    animate: boolean;
} | null>(null);
let bubbleTimer: ReturnType<typeof setTimeout> | undefined;
let lastTrigger: HTMLElement | null = null;
const waiting = new Set<() => void>();
const timers = new Set<ReturnType<typeof setTimeout>>();

function measureShell(): void {
    headerActions.value = !!document.querySelector('[data-shell-actions] > *');
}

function trigger(from: string | undefined): HTMLElement | null {
    return resolveTrigger(
        from,
        [...document.querySelectorAll<HTMLElement>('[data-action]')],
        lastTrigger,
        { width: window.innerWidth, height: window.innerHeight },
    );
}

// The first page's flash arrives both on the page and as the router's first `flash` event (an equal copy): one toast per server id, not two.
const fresh = toastGate();
async function show(value: unknown, added = false): Promise<void> {
    if (!fresh(value)) return;
    const flashed: FlashedToast | null = added
        ? fromInertiaUI(value)
        : flashedToast(value);
    if (!flashed) return;
    // Headless history: the app can record every shown toast (a notification bell, say) without the kit owning one.
    window.dispatchEvent(new CustomEvent('kit:toast', { detail: flashed }));
    measureShell();
    const el = trigger(flashed.from);
    const plan = toastPlan(
        flashed.style,
        el !== null,
        window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );
    if (plan.anchored && el) {
        const box = el.getBoundingClientRect();
        clearTimeout(bubbleTimer);
        bubble.value = {
            message: flashed.message,
            top: box.top - 8,
            left: box.left + box.width / 2,
            animate: plan.animate,
        };
        bubbleTimer = setTimeout(() => (bubble.value = null), 2500);
        return;
    }
    if (iui) {
        // A flashed toast is already in the package's list (`added`); one from `kit:toast:show` is added here.
        if (!added) {
            const { title, options } = toInertiaUI(flashed);
            iui.toast(title, options);
        }
        await nextTick();
        const sel = `[data-toast-id="${CSS.escape(flashed.id ?? '')}"]`;
        if (el && plan.flight)
            fly(el, () => document.querySelector<HTMLElement>(sel));
        if (el && plan.pulse) pulse(el);
        return;
    }
    const action = flashed.action;
    const timing = toastTiming(flashed.duration, isAway(document));
    const id = toast[flashed.type](flashed.message, {
        description: flashed.description,
        duration: timing.duration,
        action: action
            ? {
                  label: action.label,
                  onClick: () =>
                      router.visit(action.href, {
                          method: action.method ?? 'get',
                      }),
              }
            : undefined,
    });
    if (timing.deferred !== null) {
        const ms = timing.deferred;
        const cancel = whenBack(document, window, () => {
            waiting.delete(cancel);
            const timer = setTimeout(() => {
                timers.delete(timer);
                toast.dismiss(id);
            }, ms);
            timers.add(timer);
        });
        waiting.add(cancel);
    }
    await nextTick();
    if (el && plan.flight)
        fly(el, () =>
            document.querySelector<HTMLElement>(
                '[data-kit-toaster] [data-sonner-toast][data-front="true"]',
            ),
        );
    if (el && plan.pulse) pulse(el);
}

// The package's list sits `--toast-offset` from the edge, in a layer: one unlayered rule moves it to the kit's resolved offset (under the shell's pinned top,
// above the sticky bars and the safe area), read from custom properties this keeps current. Its Toaster renders into its own host on <body>, out of reach of a prop.
function placeInertiaUI(): void {
    if (!document.getElementById('kit-iui-toasts')) {
        const rule = document.createElement('style');
        rule.id = 'kit-iui-toasts';
        rule.textContent =
            ".iui-toast-list[data-position^='top']{top:var(--kit-iui-toast-top)}.iui-toast-list[data-position^='bottom']{bottom:var(--kit-iui-toast-bottom)}";
        document.head.append(rule);
    }
}
watchEffect(() => {
    if (!iui || typeof document === 'undefined') return;
    const root = document.documentElement.style;
    root.setProperty(
        '--kit-iui-toast-top',
        `calc(var(--shell-top, 0px) + ${placement.value.offset}px)`,
    );
    root.setProperty(
        '--kit-iui-toast-bottom',
        `calc(${placement.value.offset}px + env(safe-area-inset-bottom))`,
    );
});

let media: MediaQueryList | undefined;
const onMedia = () => (small.value = !!media?.matches);
const onActivate = (event: Event) =>
    (lastTrigger =
        (event.target as Element | null)?.closest<HTMLElement>(
            '[data-action]',
        ) ?? lastTrigger);
let stop: (() => void) | undefined;
// A kit component that acts without a page visit (the Workbench's refused reorder) asks through `kit:toast:show`; it renders like a flash. Pages never send it.
const onClientToast = (event: Event) =>
    show((event as CustomEvent<unknown>).detail);
type UntypedOn = (
    type: string,
    callback: (event: { detail: { versionChange?: boolean } }) => void,
) => () => void;
onMounted(() => {
    installNewVersion({
        target: window,
        // `location` is Inertia 3.6's event: an older router's types don't name it, and its listener simply never fires there, so the call is untyped.
        onLocation: (listener) =>
            (router.on as unknown as UntypedOn)('location', listener),
        unsaved: pageUnsaved,
        reload: () => window.location.reload(),
        toast: iui
            ? (message, { id, action }) =>
                  iui.toast(message, {
                      id,
                      duration: null,
                      actions: [
                          { label: action.label, onClick: action.onClick },
                      ],
                  })
            : toast,
    });
    if (iui) placeInertiaUI();
    window.addEventListener('kit:toast:show', onClientToast);
    media = window.matchMedia('(max-width: 39.99rem)');
    onMedia();
    media.addEventListener('change', onMedia);
    document.addEventListener('pointerdown', onActivate, true);
    document.addEventListener('keydown', onActivate, true);
    measureShell();
    const flashed = (flash: Record<string, unknown> | undefined) => {
        show(flash?.toast);
        // The package's list (its default flash key): its own Toaster adds them; here each kit toast meets its trigger and reaches `kit:toast`.
        if (iui)
            for (const toast of (flash?.inertiaui as { toasts?: unknown[] })
                ?.toasts ?? [])
                show(toast, true);
    };
    flashed((page as unknown as { flash?: Record<string, unknown> }).flash);
    stop = router.on(
        'flash',
        (event: CustomEvent<{ flash?: Record<string, unknown> }>) =>
            flashed(event.detail.flash),
    );
});
onBeforeUnmount(() => {
    window.removeEventListener('kit:toast:show', onClientToast);
    media?.removeEventListener('change', onMedia);
    document.removeEventListener('pointerdown', onActivate, true);
    document.removeEventListener('keydown', onActivate, true);
    clearTimeout(bubbleTimer);
    waiting.forEach((cancel) => cancel());
    timers.forEach((timer) => clearTimeout(timer));
    stop?.();
});
// While a Modal is open (a native <dialog> in the top layer), the toasts move into it, or a toast raised from the Modal would show under it (layer.ts).
const layer = useTopLayer();
</script>
<template>
    <component
        :is="iui.Toaster"
        v-if="iui"
        :position="placement.position"
        data-kit-toaster
    />
    <Teleport :to="layer" :disabled="layer === 'body'">
        <Sonner
            v-if="!iui"
            :position="placement.position"
            :offset="offset"
            :mobile-offset="offset"
            close-button
            rich-colors
            data-kit-toaster
            :data-position="placement.position"
        />
        <div
            v-if="bubble"
            role="status"
            :class="[
                'bg-foreground text-background pointer-events-none fixed top-(--kit-toast-top) left-(--kit-toast-left) z-50 inline-flex -translate-x-1/2 -translate-y-full items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium shadow-(--kit-elevation-4)',
                bubble.animate &&
                    'motion-safe:animate-in motion-safe:fade-in motion-safe:zoom-in-95',
            ]"
            :style="{
                '--kit-toast-top': `${bubble.top}px`,
                '--kit-toast-left': `${bubble.left}px`,
            }"
            data-kit-toast-anchored
        >
            <Check class="size-3.5" aria-hidden="true" />{{ bubble.message }}
        </div>
    </Teleport>
</template>
