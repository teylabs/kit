/**
 * Brings an item the person just added or changed into view and marks it for a moment: centred (or `block`), smooth unless they prefer
 * reduced motion, with a ring and `data-highlighted` (for tests and styling) that clear after `duration` ms (2000). Takes an element or a
 * selector and does nothing when there is no target. Not the `reveal` prop of FormCard and Workbench, which names a scroll trigger.
 */
export function reveal(
    target: Element | string | null | undefined,
    options: { duration?: number; block?: ScrollLogicalPosition } = {},
): HTMLElement | null {
    const element =
        typeof target === 'string'
            ? document.querySelector<HTMLElement>(target)
            : ((target as HTMLElement | null | undefined) ?? null);

    if (!element) {
        return null;
    }

    const reduced =
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
        false;
    element.scrollIntoView({
        block: options.block ?? 'center',
        behavior: reduced ? 'auto' : 'smooth',
    });
    element.dataset.highlighted = '';
    element.classList.add('ring-2', 'ring-primary', 'transition-shadow');
    window.setTimeout(() => {
        delete element.dataset.highlighted;
        element.classList.remove('ring-2', 'ring-primary');
    }, options.duration ?? 2000);

    return element;
}
