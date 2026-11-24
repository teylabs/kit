/*
 * The two motions a toast `from` an action key makes on its trigger, shared by the kit's Toaster and the inertiaui shim (inertiaui/Toaster.vue):
 * a brief ring on the trigger (pulse), and FLIP from the trigger into the toast's own place (fly).
 */
export function pulse(el: HTMLElement): void {
    el.animate?.(
        [
            {
                boxShadow:
                    '0 0 0 0 color-mix(in oklab, var(--color-primary) 55%, transparent)',
            },
            { boxShadow: '0 0 0 8px transparent' },
        ],
        { duration: 700, easing: 'ease-out' },
    );
}

// FLIP: the toast `find` returns starts over the trigger and settles where it is.
export function fly(from: HTMLElement, find: () => HTMLElement | null): void {
    requestAnimationFrame(() => {
        const to = find();
        if (!to) return;
        const a = from.getBoundingClientRect();
        const b = to.getBoundingClientRect();
        to.animate?.(
            [
                {
                    transform: `translate(${a.left - b.left}px, ${a.top - b.top}px) scale(${Math.max(0.2, a.width / b.width)}, ${Math.max(0.2, a.height / b.height)})`,
                    opacity: 0.4,
                },
                { transform: 'none', opacity: 1 },
            ],
            { duration: 300, easing: 'cubic-bezier(0.2, 0, 0, 1)' },
        );
    });
}
