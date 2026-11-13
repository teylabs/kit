// Pure helpers for MiddleTruncate: where a long file name, path or URL is cut so its start and its tail stay visible. Width comes from the caller's
// `measure` (a canvas in the browser, a fixed width per character in tests), so there is no DOM here and the math is tested directly.

export type MiddleTruncateKind = 'file' | 'path' | 'url' | 'auto';

/**
 * What is shown: `head`, an ellipsis when `middle` is cut, `tail`, and an ellipsis when `end` is (a URL's query or fragment, past its `?` or `#`). The cut
 * parts stay in the text, hidden, so head + middle + tail + end is always the whole value.
 */
export interface MiddleTruncation {
    head: string;
    middle: string;
    tail: string;
    end: string;
    truncated: boolean;
}

export const MIDDLE_ELLIPSIS = '…';

/**
 * Where `Intl.Segmenter` is missing: a flag (two regional indicators), or a character with everything that cannot start a grapheme after it: combining
 * marks (variation selectors among them), skin tones, emoji tag characters, and a zero-width joiner with the character it joins. It may group more than
 * Intl.Segmenter does, never less, so a cut between two pieces is never inside a grapheme. The last branch takes anything else alone (a stray mark), so
 * the pieces always join back into the text.
 */
const GRAPHEME =
    /\p{Regional_Indicator}{2}|[^\p{M}\u200D](?:[\p{M}\u{1F3FB}-\u{1F3FF}\u{E0020}-\u{E007F}]|\u200D(?:\p{Regional_Indicator}{2}|[^\p{M}\u200D])?)*|[\s\S]/gu;

/** The value's user-perceived characters: an emoji sequence, a flag or a letter with its accents is one, never split. */
export function graphemes(text: string): string[] {
    if (typeof Intl !== 'undefined' && 'Segmenter' in Intl) {
        return Array.from(
            new Intl.Segmenter(undefined, {
                granularity: 'grapheme',
            }).segment(text),
            (part) => part.segment,
        );
    }

    return text.match(GRAPHEME) ?? [];
}

/** A canvas font matching an element's computed one, from the longhands: Firefox may serialise the computed `font` shorthand as ''. */
export function canvasFont(style: CSSStyleDeclaration): string {
    return `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

/**
 * An element's unrounded content box: clientWidth and scrollWidth round, and a fraction of a pixel over is enough for the CSS ellipsis. MiddleTruncate fits
 * its cut to it, and TextTruncate compares its text with it to know whether the end was cut.
 */
export function contentWidth(
    el: HTMLElement,
    style: CSSStyleDeclaration,
): number {
    return (
        el.getBoundingClientRect().width -
        parseFloat(style.paddingLeft) -
        parseFloat(style.paddingRight) -
        parseFloat(style.borderLeftWidth) -
        parseFloat(style.borderRightWidth)
    );
}

/** `auto` reads a scheme (`https://`) or a leading `www.` as a URL, a value with a `/` or `\` as a path, and anything else as a file name. */
export function truncationKind(
    value: string,
    kind: MiddleTruncateKind = 'auto',
): Exclude<MiddleTruncateKind, 'auto'> {
    if (kind !== 'auto') {
        return kind;
    }
    if (/^[a-z][a-z\d+.-]*:\/\//i.test(value) || /^www\./i.test(value)) {
        return 'url';
    }

    return /[/\\]/.test(value) ? 'path' : 'file';
}

/**
 * Where a file name's extension starts (its dot), or -1: the last dot followed by 1 to 16 letters and digits (`.pdf`, `.webmanifest`). `.tar.gz` is one
 * extension; a leading dot (`.env`) is a name, not an extension, and so is a suffix with a space or punctuation in it, or a longer one.
 */
export function extensionStart(name: string): number {
    const match = /(?:\.tar)?\.[\p{L}\p{N}]{1,16}$/u.exec(name);

    return match && match.index > 0 && !/[/\\]/.test(name[match.index - 1])
        ? match.index
        : -1;
}

/** The start of the last path segment's separator in `value[from, to)`, ignoring trailing separators, or -1. */
function lastSeparator(value: string, from: number, to: number): number {
    let end = to;
    while (end > from + 1 && /[/\\]/.test(value[end - 1])) {
        end--;
    }
    for (let i = end - 1; i >= from; i--) {
        if (value[i] === '/' || value[i] === '\\') {
            return i;
        }
    }

    return -1;
}

/**
 * The anchors, as code-unit offsets: the head prefers to keep `head` (the URL's scheme and the start of its host) and never gives up `lead` (one
 * character, or a path's root: `C:\` or `\\server\share\`); the tail prefers to keep from `tail` (a file's extension, a path's or URL's last segment) and
 * never gives up what is from `floor` (the extension the tail ends with).
 */
function anchors(
    value: string,
    kind: Exclude<MiddleTruncateKind, 'auto'>,
): { head: number; lead: number; tail: number; floor: number } {
    const length = value.length;
    const extension = (segmentStart: number) => {
        const dot = extensionStart(value.slice(segmentStart));

        return dot === -1 ? length : segmentStart + dot;
    };
    if (kind === 'url') {
        const scheme =
            /^(?:[a-z][a-z\d+.-]*:\/\/)?/i.exec(value)?.[0].length ?? 0;
        const hostEnd = value.slice(scheme).search(/[/?#]/);
        const pathEnd = value.slice(scheme).search(/[?#]/);
        const host = hostEnd === -1 ? length : scheme + hostEnd;
        const query = pathEnd === -1 ? length : scheme + pathEnd;
        const separator = host < query ? lastSeparator(value, host, query) : -1;
        const tail =
            separator > host || (separator === host && query - host > 1)
                ? separator
                : query;
        const dot =
            tail < query ? extensionStart(value.slice(tail + 1, query)) : -1;

        return {
            head: scheme + Math.min(8, host - scheme),
            lead: 1,
            tail,
            floor: dot === -1 ? length : tail + 1 + dot,
        };
    }
    if (kind === 'path') {
        const separator = lastSeparator(value, 0, length);
        if (separator > 0) {
            // A drive's root (`C:\`, `C:/`) or a UNC share (`\\server\share\`) is one unit of the head, never cut.
            const root = Math.max(
                1,
                /^(?:[a-z]:[/\\]?|\\\\[^/\\]+\\[^/\\]+\\?)/i.exec(value)?.[0]
                    .length ?? 0,
            );

            return {
                head: root,
                lead: root,
                tail: separator,
                floor: /[/\\]$/.test(value) ? length : extension(separator + 1),
            };
        }
    }
    const dot = extensionStart(value);

    return {
        head: 1,
        lead: 1,
        tail: dot === -1 ? length : dot,
        floor: dot === -1 ? length : dot,
    };
}

/**
 * Cuts `value` in the middle to fit `width`, as `measure` (the width of a string) reports it. A value that fits is returned whole. Otherwise the tail keeps
 * the whole extension of a file name, and the last path segment of a path or URL when it fits, with at least `minTail` characters; the head keeps the start
 * (for a URL, its scheme and the start of its host). A URL's query or fragment is cut first, down to its `?` or `#`. Leftover room goes two thirds to the
 * head, one third to the tail, then to whichever side still fits one more character. Characters are graphemes, so an emoji or an accented letter is never
 * split. At a width too small for even the first character, an ellipsis and the extension, that floor is returned and the element clips it.
 */
export function middleTruncate(
    value: string,
    width: number,
    measure: (text: string) => number,
    options: { kind?: MiddleTruncateKind; minTail?: number } = {},
): MiddleTruncation {
    const kind = truncationKind(value, options.kind);
    const minTail = Math.max(0, Math.floor(Number(options.minTail) || 0));
    const query = kind === 'url' ? value.search(/[?#]/) : -1;
    if (query > 0 && query < value.length - 1 && measure(value) > width) {
        const body = cut(
            value.slice(0, query + 1),
            width,
            (text) => measure(text + MIDDLE_ELLIPSIS),
            kind,
            minTail,
        );

        return { ...body, end: value.slice(query + 1), truncated: true };
    }

    return { ...cut(value, width, measure, kind, minTail), end: '' };
}

function cut(
    value: string,
    width: number,
    measure: (text: string) => number,
    kind: Exclude<MiddleTruncateKind, 'auto'>,
    minTail: number,
): Omit<MiddleTruncation, 'end'> {
    const parts = graphemes(value);
    const count = parts.length;
    if (count < 3 || measure(value) <= width) {
        return { head: value, middle: '', tail: '', truncated: false };
    }
    // Code-unit offsets to grapheme counts: graphemes kept before `offset`, and from it to the end.
    const starts: number[] = [];
    parts.reduce(
        (offset, part) => (starts.push(offset), offset + part.length),
        0,
    );
    const before = (offset: number) =>
        starts.filter((start) => start < offset).length;
    const from = (offset: number) => count - before(offset);
    const anchor = anchors(value, kind);
    // The most the anchors may take: one character always goes to the middle, and `lead` (at least one) always stays in the head.
    const lead = Math.max(1, Math.min(before(anchor.lead), count - 2));
    const floor = Math.min(
        count - 1 - lead,
        Math.max(1, from(anchor.floor), minTail),
    );
    const tail = Math.min(count - 1 - lead, Math.max(floor, from(anchor.tail)));
    const head = Math.max(
        lead,
        Math.min(before(anchor.head), count - 1 - tail),
    );
    const text = (h: number, t: number) =>
        parts.slice(0, h).join('') +
        MIDDLE_ELLIPSIS +
        parts.slice(count - t).join('');
    const fits = (h: number, t: number) => measure(text(h, t)) <= width;
    // The largest step in [0, steps] that fits, for a split that grows with its step.
    const largest = (
        steps: number,
        split: (step: number) => [number, number],
    ) => {
        let low = 0;
        let high = steps;
        while (low < high) {
            const mid = Math.ceil((low + high) / 2);
            if (fits(...split(mid))) {
                low = mid;
            } else {
                high = mid - 1;
            }
        }

        return split(low);
    };
    let h: number;
    let t: number;
    if (fits(head, tail)) {
        const spare = count - 1 - head - tail;
        [h, t] = largest(spare, (step) => [
            head + Math.ceil((step * 2) / 3),
            tail + step - Math.ceil((step * 2) / 3),
        ]);
    } else {
        // Too narrow for the anchors: give up the head beyond its lead and the tail beyond its floor together, in proportion.
        const steps = Math.max(head - lead, tail - floor);
        [h, t] = largest(steps, (step) => [
            lead + Math.round((step * (head - lead)) / (steps || 1)),
            floor + Math.round((step * (tail - floor)) / (steps || 1)),
        ]);
    }
    // Whatever room is left: to the tail while it is short of its anchor, else to the head first.
    for (let grew = true; grew && h + t < count - 1;) {
        const tailFirst = t < tail;
        grew = true;
        if (tailFirst && fits(h, t + 1)) {
            t++;
        } else if (fits(h + 1, t)) {
            h++;
        } else if (!tailFirst && fits(h, t + 1)) {
            t++;
        } else {
            grew = false;
        }
    }

    return {
        head: parts.slice(0, h).join(''),
        middle: parts.slice(h, count - t).join(''),
        tail: parts.slice(count - t).join(''),
        truncated: true,
    };
}
