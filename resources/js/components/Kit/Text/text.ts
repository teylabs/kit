// Pure helpers behind the Text family (docs/text.md): sizes, the prose styles, numbers, lines, the long-text cut and badge items. No DOM and no Vue, so
// tests/js/text.test.mjs loads it directly.
import { graphemes } from '../Data/middleTruncate';

export type TextSize = 'sm' | 'base' | 'lg';

// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
export const TEXT_SIZES: Record<TextSize, string> = {
    sm: 'text-sm/6',
    base: 'text-base/7',
    lg: 'text-lg/8',
};

/**
 * Reading styles for markup the app renders inside TextProse (paragraphs, lists, headings, links, quotes, code, tables), from the kit's tokens: no
 * typography plugin is needed, and the first and last child carry no outer margin, so the block sits on the page's rhythm.
 */
export const PROSE =
    'text-foreground [&>:first-child]:mt-0 [&>:last-child]:mb-0 [&_p]:my-3 [&_ul]:my-3 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:ps-6 [&_li]:my-1 [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-[1.25em] [&_h2]:font-semibold [&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:text-[1.1em] [&_h3]:font-semibold [&_h4]:mt-4 [&_h4]:mb-1 [&_h4]:font-semibold [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_strong]:font-semibold [&_blockquote]:my-3 [&_blockquote]:border-s-2 [&_blockquote]:border-border [&_blockquote]:ps-4 [&_blockquote]:text-muted-foreground [&_hr]:my-6 [&_hr]:border-border [&_code]:rounded-sm [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-[0.9em] [&_pre]:my-3 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_pre_code]:bg-transparent [&_pre_code]:p-0';

/** A table's styles for TextTable: full width, a rule under the header and between rows, cells padded, header cells in the muted colour. */
export const TABLE =
    '[&_table]:w-full [&_table]:border-collapse [&_th]:border-b [&_th]:border-border [&_th]:px-3 [&_th]:py-2 [&_th]:text-start [&_th]:font-medium [&_th]:text-muted-foreground [&_td]:border-b [&_td]:border-border [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_tr:last-child_td]:border-b-0';

// TextTruncate's line clamps: one line is `truncate` (an end ellipsis), more is `line-clamp-*` (its own display, so never with `block`).
export const CLAMP_LINES = {
    1: 'truncate',
    2: 'line-clamp-2',
    3: 'line-clamp-3',
    4: 'line-clamp-4',
    5: 'line-clamp-5',
    6: 'line-clamp-6',
} as const;
export type ClampLines = keyof typeof CLAMP_LINES;

const DECIMAL = /^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i;

/** A finite number, or a string that is one (`" 1,234"` is not: grouping is display, never input). */
export function isNumeric(value: unknown): value is number | string {
    if (typeof value === 'number') return Number.isFinite(value);
    return typeof value === 'string' && DECIMAL.test(value.trim());
}

export type NumericOptions = {
    locale?: string;
    /** Exactly this many decimal places, rounded half-expand; unset keeps up to three. */
    decimals?: number;
    /** `compact` writes 1.2K, 3.4M in the locale's own form. */
    notation?: 'standard' | 'compact';
    /** A negative with the locale's minus (the default), or in accounting parentheses: (1,234). */
    negative?: 'minus' | 'parentheses';
};

/**
 * A number's display parts: `text` without its sign, and `negative` when what is shown is below zero (a value that rounds to zero is not: -0.001 at no
 * places is "0", never "-0"). Null when the value is not a number. A numeric string is formatted as written, so a value past 2^53 keeps every digit.
 */
export function numericParts(
    value: unknown,
    options: NumericOptions = {},
): { text: string; negative: boolean; minus: string } | null {
    if (!isNumeric(value)) return null;
    const decimals =
        options.decimals === undefined
            ? undefined
            : Math.min(20, Math.max(0, Math.trunc(options.decimals)));
    const format = new Intl.NumberFormat(options.locale ?? 'en-CA', {
        notation: options.notation ?? 'standard',
        minimumFractionDigits: decimals,
        maximumFractionDigits:
            decimals ?? (options.notation === 'compact' ? 1 : 3),
    });
    const input = typeof value === 'string' ? value.trim() : value;
    // Intl takes a decimal string exactly (ES2023); its type still says number.
    const parts = format.formatToParts(input as number);
    const sign = parts.find((part) => part.type === 'minusSign');
    const text = parts
        .filter((part) => part.type !== 'minusSign')
        .map((part) => part.value)
        .join('');

    return {
        text,
        negative: Boolean(sign) && /[1-9]/.test(text),
        minus: sign?.value ?? '-',
    };
}

/**
 * A number as display text in the locale's grouping and decimal separator (Intl, no library), or null when the value is not a number (empty, `null`, `"n/a"`):
 * nothing is shown rather than `NaN`. `negative` is the locale's minus or accounting parentheses.
 */
export function formatNumeric(
    value: unknown,
    options: NumericOptions = {},
): string | null {
    const parts = numericParts(value, options);
    if (!parts) return null;
    if (!parts.negative) return parts.text;

    return options.negative === 'parentheses'
        ? `(${parts.text})`
        : `${parts.minus}${parts.text}`;
}

/** Display lines from an array (empties dropped) or a string, split on line breaks, or on commas when it has none (`"1 Main St, Ottawa ON"`). */
export function textLines(
    lines: readonly unknown[] | string | null | undefined,
): string[] {
    if (typeof lines === 'string') {
        return lines
            .split(/\r?\n/.test(lines) ? /\r?\n/ : ',')
            .map((line) => line.trim())
            .filter(Boolean);
    }

    return (lines ?? [])
        .map((line) =>
            line === null || line === undefined ? '' : String(line).trim(),
        )
        .filter(Boolean);
}

/**
 * TextLong's cut: at most `limit` characters (graphemes, so an emoji or an accented letter is never split), with trailing space dropped and an ellipsis
 * added. `truncated` is false when the text is no longer than the limit: it is shown whole, with no Show more.
 */
export function longTextCut(
    text: string,
    limit: number,
): { shown: string; truncated: boolean } {
    const characters = graphemes(text);
    const size = Math.max(1, Math.trunc(limit) || 1);
    if (characters.length <= size) return { shown: text, truncated: false };

    return {
        shown: `${characters.slice(0, size).join('').trimEnd()}…`,
        truncated: true,
    };
}

export type BadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';
export type TextChip =
    | string
    | { label: string; value?: string | number | null; tone?: BadgeTone };
export type TextBadgeItem = {
    key: string;
    label: string;
    value: string | null;
    tone: BadgeTone;
};

/**
 * TextChips' and TextTags' items as badges: a string is a label; an object is a label with an optional value ("Status" and "Active") and tone. Blank labels
 * are dropped, and a repeated item keeps a key of its own, so Vue never sees two of one.
 */
export function badgeItems(
    items: readonly TextChip[] | null | undefined,
): TextBadgeItem[] {
    const seen = new Map<string, number>();

    return (items ?? []).flatMap((item) => {
        const chip = typeof item === 'string' ? { label: item } : item;
        const label = (chip?.label ?? '').trim();
        if (!label) return [];
        const value =
            'value' in chip &&
            chip.value !== null &&
            chip.value !== undefined &&
            String(chip.value).trim() !== ''
                ? String(chip.value).trim()
                : null;
        const base = value === null ? label : `${label}\u0000${value}`;
        const count = seen.get(base) ?? 0;
        seen.set(base, count + 1);

        return [
            {
                key: count ? `${base}\u0000${count}` : base,
                label,
                value,
                tone: ('tone' in chip && chip.tone) || 'neutral',
            },
        ];
    });
}
