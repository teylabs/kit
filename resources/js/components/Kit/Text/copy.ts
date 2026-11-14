// The kit's one clipboard write, behind CopyButton, CopyField and TextCopy, with no Vue in it (tests/js/text.test.mjs loads it directly).

/**
 * Copies `text` to the clipboard and resolves once it is there. Uses the async Clipboard API; where the browser has none (a page served over plain http, an
 * older browser) it falls back to a hidden read-only textarea and `document.execCommand('copy')`, then puts back the focus and the selection it moved. A
 * refused write (the API is there and rejects) is not retried another way: the refusal is the answer. Rejects when the text did not reach the clipboard.
 */
export async function copyText(text: string): Promise<void> {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }
    if (!legacyCopy(text)) {
        throw new Error('clipboard unavailable');
    }
}

function legacyCopy(text: string): boolean {
    if (
        typeof document === 'undefined' ||
        typeof document.execCommand !== 'function' ||
        !document.body
    ) {
        return false;
    }
    const active = document.activeElement as HTMLElement | null;
    const focused = typeof active?.focus === 'function' ? active : null;
    const selection = document.getSelection();
    const ranges = selection
        ? Array.from({ length: selection.rangeCount }, (_, index) =>
              selection.getRangeAt(index),
          )
        : [];
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.setAttribute('aria-hidden', 'true');
    area.tabIndex = -1;
    area.style.cssText =
        'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;pointer-events:none';
    document.body.append(area);
    area.focus({ preventScroll: true });
    area.select();
    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }
    area.remove();
    focused?.focus({ preventScroll: true });
    if (selection) {
        selection.removeAllRanges();
        ranges.forEach((range) => selection.addRange(range));
    }

    return copied;
}
