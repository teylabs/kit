# Alert actions

An `Alert`'s `#actions` take the alert's tone. A kit `Button` there is drawn in the alert's colour, never the host's grey secondary, and the caller passes no class:

```vue
<Alert variant="warning" title="Card expires soon">
    The card on file expires at the end of the month.
    <template #actions>
        <Button href="/billing">Update card</Button>
        <Button variant="secondary" @click="later">Remind me later</Button>
    </template>
</Alert>
```

## Styles

`actionsStyle` is `link` (the default) or `soft`.

- **`link`:** tone text, medium weight, underlined on hover, no background. It blends into the alert: the message stays the subject and the action reads as its next step. At least a 24px target.
- **`soft`:** a compact button (32px, `size="sm"`) tinted with the tone: `bg-current/8`, `bg-current/12` on hover. For an alert that exists to get one click (turn something on, retry).

The Button's variant says how loud the action is, not its colour. `primary`, `success` and `destructive` are the alert's main action in the chosen style; `secondary`, `ghost` and `link` are a quiet, normal-weight tone link in either style (Dismiss, Learn more). A plain `<a>` or `<button>` in `#actions` takes the tone's colour too. A Button in the alert's body keeps the host's look.

The focus ring is a 2px outline in the tone, 2px out.

## Tones and contrast

The tone is the pale tier's semantic text token (`--info-text`, `--success-text`, `--warning-text`, `--destructive-text`; the alert's own foreground for `muted`, `primary` and `brand`) and the solid tier's white text. Every pairing keeps 4.5:1 against what is painted behind it, in light and dark, at rest and on hover. The soft tint is capped at 12%: at 20%, light success text on its tint falls to 4.1:1.

| Light, pale | link | soft | soft, hover |
| --- | --- | --- | --- |
| info | 5.69 | 5.07 | 4.76 |
| success | 5.54 | 4.96 | 4.65 |
| warning | 5.82 | 5.21 | 4.87 |
| danger | 5.92 | 5.20 | 4.84 |
| muted | 18.16 | 15.28 | 13.89 |

Dark is higher throughout (the lowest is danger soft on hover, 5.54:1), and the solid tier's lowest is 5.47:1. Screenshots of every tone in both styles, light and dark, at 390 and 1280px are in [shots/alert-actions](shots/alert-actions).

## Coverage

The browser job (`browser/run.mjs`, `?alert-actions`, `=dark`) renders every tone and tier in both styles and reads the actions as painted (translucent backgrounds composited): each action is the alert's tone, a link has no background and underlines on hover, a soft action is tinted and deepens on hover, the quiet action is a normal-weight link, every ratio is at least 4.5:1 at rest and on hover, the focus outline is 2px in the tone, a plain link and a Button with `href` take the tone, a Button in the body keeps the host's secondary, nothing overflows at 390px, and axe with contrast passes. `KIT_ALERT_ACTIONS_SCREENSHOTS=<dir>` saves the screenshots; `KIT_ALERT_ACTIONS_CONTRAST=<file>` writes the ratios.
