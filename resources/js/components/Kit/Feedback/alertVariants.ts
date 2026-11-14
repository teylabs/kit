import { cva, type VariantProps } from 'class-variance-authority';

// The shared semantic variants, in two tiers: `pale` (a tinted surface, dark text, a coloured icon) and `solid` (full colour, white text) for the rare message that
// must outrank the page. Every pairing is chosen to keep 4.5:1 text contrast in light and dark. Components take colour from here and write none of their own.
// A pale tint takes the theme's foreground for its text and the semantic *-text token for its icon, never a raw palette text colour.
export const alertVariants = cva(
    'relative flex items-start gap-3 rounded-lg border p-4 text-sm',
    {
        variants: {
            variant: {
                info: '',
                success: '',
                warning: '',
                danger: '',
                primary: '',
                brand: '',
                muted: '',
            },
            tier: { pale: '', solid: '' },
        },
        compoundVariants: [
            {
                variant: 'info',
                tier: 'pale',
                class: 'text-foreground border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/60',
            },
            {
                variant: 'success',
                tier: 'pale',
                class: 'text-foreground border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/60',
            },
            {
                variant: 'warning',
                tier: 'pale',
                class: 'text-foreground border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60',
            },
            {
                variant: 'danger',
                tier: 'pale',
                class: 'text-foreground border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60',
            },
            {
                variant: 'primary',
                tier: 'pale',
                class: 'border-primary/30 bg-primary/10 text-foreground',
            },
            {
                variant: 'brand',
                tier: 'pale',
                class: 'border-accent-foreground/20 bg-accent text-accent-foreground',
            },
            {
                variant: 'muted',
                tier: 'pale',
                class: 'border-border bg-muted text-foreground',
            },
            {
                variant: 'info',
                tier: 'solid',
                class: 'border-sky-800 bg-sky-800 text-white',
            },
            {
                variant: 'success',
                tier: 'solid',
                class: 'border-green-800 bg-green-800 text-white',
            },
            {
                variant: 'warning',
                tier: 'solid',
                class: 'border-amber-800 bg-amber-800 text-white',
            },
            {
                variant: 'danger',
                tier: 'solid',
                class: 'border-red-800 bg-red-800 text-white',
            },
            {
                variant: 'primary',
                tier: 'solid',
                class: 'border-primary bg-primary text-primary-foreground',
            },
            {
                variant: 'brand',
                tier: 'solid',
                class: 'border-accent-foreground bg-accent-foreground text-accent',
            },
            {
                variant: 'muted',
                tier: 'solid',
                class: 'border-foreground bg-foreground text-background',
            },
        ],
        defaultVariants: { variant: 'info', tier: 'pale' },
    },
);

/** The leading icon's colour, by variant and tier. */
export const alertIconClass = (
    variant: AlertVariant,
    tier: AlertTier,
): string => {
    if (tier === 'solid') return 'text-current';
    return {
        info: 'text-info-text',
        success: 'text-success-text',
        warning: 'text-warning-text',
        danger: 'text-destructive-text',
        primary: 'text-primary',
        brand: 'text-accent-foreground',
        muted: 'text-muted-foreground',
    }[variant];
};

/*
 * An Alert's actions take its tone (AlertActions provides it as the surface; Button reads it): never the host's grey secondary. `style` is the alert's
 * `actionsStyle`: `link` (the default) is tone text, medium weight, underlined on hover; `soft` a compact button tinted with the tone, `bg-current/8` and 12%
 * on hover (at 20%, light success text falls to 4.1:1). A quiet Button (secondary, ghost, link) is a normal-weight tone link in either style, for a second
 * action such as Dismiss. Focus is a 2px outline in the tone, 2px out. The tone is the pale tier's semantic text token (the icon's; foreground for muted, whose
 * muted-foreground is too faint for a link) and the solid tier's own text. Each pairing keeps 4.5:1 in light and dark, at rest and on hover (browser/run.mjs,
 * ?alert-actions).
 */
export type AlertActionsStyle = 'link' | 'soft';
export type AlertActions = {
    variant: AlertVariant;
    tier: AlertTier;
    style: AlertActionsStyle;
};

/** The text colour of an Alert's actions row, which a plain link or button slotted there inherits. */
export const alertActionsText = (
    variant: AlertVariant,
    tier: AlertTier,
): string =>
    tier === 'solid'
        ? 'text-current hover:text-current'
        : {
              info: 'text-info-text hover:text-info-text',
              success: 'text-success-text hover:text-success-text',
              warning: 'text-warning-text hover:text-warning-text',
              danger: 'text-destructive-text hover:text-destructive-text',
              primary: 'text-primary hover:text-primary',
              brand: 'text-accent-foreground hover:text-accent-foreground',
              muted: 'text-foreground hover:text-foreground',
          }[variant];

const actionFocus =
    'focus-visible:ring-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-current';
const actionLink =
    'h-auto min-h-6 rounded-sm p-0 has-[>svg]:px-0 bg-transparent shadow-none underline-offset-4 hover:bg-transparent hover:underline dark:bg-transparent dark:hover:bg-transparent';
const actionSoft =
    'font-medium shadow-none bg-current/8 hover:bg-current/12 dark:bg-current/8 dark:hover:bg-current/12';

/** A kit Button's classes in an Alert's actions: `quiet` for a secondary, ghost or link Button. On the host's link (a link) or ghost (soft) button, size sm. */
export const alertActionClass = (
    actions: AlertActions,
    quiet: boolean,
): string =>
    [
        alertActionsText(actions.variant, actions.tier),
        actionFocus,
        actions.style === 'soft' && !quiet
            ? actionSoft
            : `${actionLink} ${quiet ? 'font-normal' : 'font-medium'}`,
    ].join(' ');

export type AlertVariant = NonNullable<
    VariantProps<typeof alertVariants>['variant']
>;
export type AlertTier = NonNullable<VariantProps<typeof alertVariants>['tier']>;
