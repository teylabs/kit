/*
 * Where the shell's Toaster sits and how a flashed toast meets its trigger. INTERNAL to Toaster; pure, so it is tested without a browser.
 *
 * Position: the shell's `kit.toasts.position` (shared as `kitToasts`): auto, top-center, top-right, bottom-right or bottom-center; an unknown value is auto.
 * Below `sm` it is always bottom-center, lifted above the registered sticky bars (the safe-area inset is added in CSS). Otherwise `auto` is top-center when
 * sticky bottom bars are registered and the shell header has right-aligned actions, top-right with sticky bars only, else bottom-right. A top position's offset
 * is the gap below the shell's pinned stack: the Toaster adds the shell's live `--shell-top` in CSS, so it never sits over the bar and follows it as it resizes.
 *
 * Trigger: the server names it by action-descriptor key (`from`), and rendered descriptor buttons carry that key as `data-action`. The last activated
 * element with that key wins (a row's own button among many rows), else the only one on the page. Anything else, a gone or offscreen element, is no trigger.
 */
export type ToastPosition =
    'auto' | 'top-center' | 'top-right' | 'bottom-right' | 'bottom-center';
export type ResolvedPosition = Exclude<ToastPosition, 'auto'>;
export type ToastPlacement = { position: ResolvedPosition; offset: number };
export type ToastStyle = 'plain' | 'fly-out' | 'anchored' | 'pulse';
export type ShellState = {
    bars: { count: number; height: number };
    small: boolean;
    headerActions: boolean;
};

const GAP = 16;
const POSITIONS: ResolvedPosition[] = [
    'top-center',
    'top-right',
    'bottom-right',
    'bottom-center',
];

export function resolvePosition(
    position: string | null | undefined,
    shell: ShellState,
): ResolvedPosition {
    if (shell.small) return 'bottom-center';
    if (POSITIONS.includes(position as ResolvedPosition))
        return position as ResolvedPosition;
    if (shell.bars.count > 0)
        return shell.headerActions ? 'top-center' : 'top-right';
    return 'bottom-right';
}

export function toastPlacement(
    position: string | null | undefined,
    shell: ShellState,
): ToastPlacement {
    const resolved = resolvePosition(position, shell);
    if (resolved.startsWith('top-')) return { position: resolved, offset: GAP };
    return {
        position: resolved,
        offset:
            GAP +
            (shell.bars.count > 0
                ? Math.max(0, shell.bars.height) + GAP / 2
                : 0),
    };
}

export type FlashedToast = {
    id?: string;
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    description?: string;
    action?: {
        label: string;
        href: string;
        method?: 'get' | 'post' | 'put' | 'patch';
    };
    from?: string;
    style?: ToastStyle;
    duration?: number | null;
};

// A flashed `toast` the Toaster can render, or null: it renders what the server sent and invents nothing.
export function flashedToast(value: unknown): FlashedToast | null {
    if (!value || typeof value !== 'object') return null;
    const toast = value as Record<string, unknown>;
    if (
        !['success', 'info', 'warning', 'error'].includes(
            toast.type as string,
        ) ||
        typeof toast.message !== 'string' ||
        toast.message === ''
    )
        return null;
    return toast as FlashedToast;
}

// How long a toast stays: null (persistent) is never timed. A timed toast shown while the viewer is away (the tab hidden or the window unfocused) is opened
// untimed, and its own duration starts only once they are back, so nobody misses a toast they never saw.
export const DEFAULT_DURATION = 4000;
export type ToastTiming = { duration: number; deferred: number | null };
export function toastTiming(
    duration: number | null | undefined,
    away: boolean,
): ToastTiming {
    if (duration === null) return { duration: Infinity, deferred: null };
    const ms =
        typeof duration === 'number' && duration > 0
            ? duration
            : DEFAULT_DURATION;
    return away
        ? { duration: Infinity, deferred: ms }
        : { duration: ms, deferred: null };
}

export type FocusDocument = EventTarget & {
    hidden: boolean;
    hasFocus(): boolean;
};
export const isAway = (doc: FocusDocument): boolean =>
    doc.hidden || !doc.hasFocus();

// Runs `back` once, the first time the document is visible and focused again; the returned function cancels it.
export function whenBack(
    doc: FocusDocument,
    win: EventTarget,
    back: () => void,
): () => void {
    const check = () => {
        if (isAway(doc)) return;
        cancel();
        back();
    };
    const cancel = () => {
        doc.removeEventListener('visibilitychange', check);
        win.removeEventListener('focus', check);
    };
    doc.addEventListener('visibilitychange', check);
    win.addEventListener('focus', check);
    return cancel;
}

// Whether a flashed value is a toast not yet shown. A toast with an `id` (the server's, unique per toast) shows once per id, so a full page load's flash, read on mount
// and again from the router's first `flash` event as an equal but different object, shows once, and two identical saves (two ids) both show. A toast without an id
// (an older server) falls back to identity. A few recent ids are kept.
export function toastGate(keep = 20): (value: unknown) => boolean {
    const seen: string[] = [];
    let last: unknown;
    return (value) => {
        if (value === undefined || value === null) return false;
        const id =
            typeof value === 'object'
                ? (value as { id?: unknown }).id
                : undefined;
        if (typeof id === 'string' && id !== '') {
            if (seen.includes(id)) return false;
            seen.push(id);
            if (seen.length > keep) seen.shift();
            last = value;
            return true;
        }
        if (value === last) return false;
        last = value;
        return true;
    };
}

type Box = {
    top: number;
    left: number;
    bottom: number;
    right: number;
    width: number;
    height: number;
};
export type TriggerLike = {
    isConnected: boolean;
    getAttribute(name: string): string | null;
    getBoundingClientRect(): Box;
};

// The trigger element for `from`, or null for a plain toast.
export function resolveTrigger<T extends TriggerLike>(
    from: string | undefined,
    candidates: T[],
    last: T | null,
    viewport: { width: number; height: number },
): T | null {
    if (!from) return null;
    const live = candidates.filter(
        (el) => el.isConnected && el.getAttribute('data-action') === from,
    );
    const chosen =
        last && last.isConnected && last.getAttribute('data-action') === from
            ? last
            : live.length === 1
              ? live[0]
              : null;
    if (!chosen) return null;
    const box = chosen.getBoundingClientRect();
    const visible =
        box.width > 0 &&
        box.height > 0 &&
        box.bottom > 0 &&
        box.right > 0 &&
        box.top < viewport.height &&
        box.left < viewport.width;
    return visible ? chosen : null;
}

export type ToastPlan = {
    shell: boolean;
    flight: boolean;
    anchored: boolean;
    pulse: boolean;
    animate: boolean;
};

// What one toast does, from its style, whether its trigger resolved and the viewer's motion preference. No trigger is always a plain shell toast; reduced
// motion never flies (a fly-out becomes a toast and a pulse) and the anchored bubble appears without animating.
export function toastPlan(
    style: ToastStyle | undefined,
    resolved: boolean,
    reducedMotion: boolean,
): ToastPlan {
    const plain = {
        shell: true,
        flight: false,
        anchored: false,
        pulse: false,
        animate: !reducedMotion,
    };
    if (!resolved) return plain;
    switch (style) {
        case 'fly-out':
            return reducedMotion
                ? { ...plain, pulse: true }
                : { ...plain, flight: true };
        case 'anchored':
            return {
                shell: false,
                flight: false,
                anchored: true,
                pulse: false,
                animate: !reducedMotion,
            };
        case 'pulse':
            return { ...plain, pulse: true };
        default:
            return plain;
    }
}

// inertiaui/notifications' toast as the kit's shim flashes it (InertiaUIToasts::payload): only a toast carrying `data.kit` is the kit's own, so a toast the
// package made itself is left to the package. Returns the kit's FlashedToast, so trigger, style and history work the same under either driver.
export function fromInertiaUI(value: unknown): FlashedToast | null {
    if (!value || typeof value !== 'object') return null;
    const toast = value as Record<string, unknown>;
    const kit = (
        toast.data as { kit?: { from?: unknown; style?: unknown } } | undefined
    )?.kit;
    if (!kit || typeof toast.uuid !== 'string') return null;
    const action = Array.isArray(toast.actions)
        ? (toast.actions[0] as
              { label?: unknown; href?: unknown; method?: unknown } | undefined)
        : undefined;
    return flashedToast({
        id: toast.uuid,
        type: toast.type,
        message: toast.title,
        ...(typeof toast.description === 'string'
            ? { description: toast.description }
            : {}),
        ...(action &&
        typeof action.label === 'string' &&
        typeof action.href === 'string'
            ? {
                  action: {
                      label: action.label,
                      href: action.href,
                      method:
                          typeof action.method === 'string'
                              ? action.method
                              : 'get',
                  },
              }
            : {}),
        ...(typeof kit.from === 'string' ? { from: kit.from } : {}),
        style: typeof kit.style === 'string' ? kit.style : 'plain',
        duration: toast.duration === undefined ? null : toast.duration,
    });
}

// The package's `toast()` options for a kit toast raised in the browser (`kit:toast:show`): its id (so it shows once and the shim finds it by
// `data-toast-id`), the kit's duration as is (null stays), and its one action as a visit.
export function toInertiaUI(toast: FlashedToast): {
    title: string;
    options: Record<string, unknown>;
} {
    return {
        title: toast.message,
        options: {
            id: toast.id,
            type: toast.type,
            ...(toast.description ? { description: toast.description } : {}),
            duration: toast.duration ?? null,
            ...(toast.action
                ? {
                      actions: [
                          {
                              label: toast.action.label,
                              href: toast.action.href,
                              method: toast.action.method ?? 'get',
                          },
                      ],
                  }
                : {}),
        },
    };
}
