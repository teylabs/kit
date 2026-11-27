// The kit's named tones (one fixed set, chosen by name): the five status tones and five more for categories. Each has a pale background, a strong
// foreground (at least 4.5:1 on it, light and dark), a border and a dot, all `--kit-tone-*` tokens in kit.css that a host theme may override.
// Whole class strings, never built from a name (Tailwind finds classes by scanning the source).
export const TONES = [
    'neutral',
    'info',
    'success',
    'warning',
    'danger',
    'purple',
    'teal',
    'pink',
    'orange',
    'slate',
] as const;
export type Tone = (typeof TONES)[number];

export const TONE_BADGE: Record<Tone, string> = {
    neutral:
        'border-(--kit-tone-neutral-border) bg-(--kit-tone-neutral-bg) text-(--kit-tone-neutral-fg)',
    info: 'border-(--kit-tone-info-border) bg-(--kit-tone-info-bg) text-(--kit-tone-info-fg)',
    success:
        'border-(--kit-tone-success-border) bg-(--kit-tone-success-bg) text-(--kit-tone-success-fg)',
    warning:
        'border-(--kit-tone-warning-border) bg-(--kit-tone-warning-bg) text-(--kit-tone-warning-fg)',
    danger: 'border-(--kit-tone-error-border) bg-(--kit-tone-error-bg) text-(--kit-tone-error-fg)',
    purple: 'border-(--kit-tone-purple-border) bg-(--kit-tone-purple-bg) text-(--kit-tone-purple-fg)',
    teal: 'border-(--kit-tone-teal-border) bg-(--kit-tone-teal-bg) text-(--kit-tone-teal-fg)',
    pink: 'border-(--kit-tone-pink-border) bg-(--kit-tone-pink-bg) text-(--kit-tone-pink-fg)',
    orange: 'border-(--kit-tone-orange-border) bg-(--kit-tone-orange-bg) text-(--kit-tone-orange-fg)',
    slate: 'border-(--kit-tone-slate-border) bg-(--kit-tone-slate-bg) text-(--kit-tone-slate-fg)',
};

export const TONE_DOT: Record<Tone, string> = {
    neutral: 'bg-(--kit-tone-neutral-dot)',
    info: 'bg-(--kit-tone-info-dot)',
    success: 'bg-(--kit-tone-success-dot)',
    warning: 'bg-(--kit-tone-warning-dot)',
    danger: 'bg-(--kit-tone-error-dot)',
    purple: 'bg-(--kit-tone-purple-dot)',
    teal: 'bg-(--kit-tone-teal-dot)',
    pink: 'bg-(--kit-tone-pink-dot)',
    orange: 'bg-(--kit-tone-orange-dot)',
    slate: 'bg-(--kit-tone-slate-dot)',
};

/** An unknown name (from the server, say) falls back to neutral rather than rendering nothing. */
export const toneOf = (name: string | null | undefined): Tone => {
    if ((TONES as readonly string[]).includes(name ?? '')) return name as Tone;
    // In development, a misspelt tone says so instead of quietly turning neutral.
    if (name && import.meta.env?.DEV)
        console.warn(
            `Kit: unknown tone "${name}"; using neutral. Known tones: ${TONES.join(', ')}.`,
        );
    return 'neutral';
};
