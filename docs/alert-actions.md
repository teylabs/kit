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

`actionsStyle` is `soft` (the default) or `link`, chosen per alert.

- **`soft`:** a compact button (32px, `size="sm"`) tinted with the tone: `bg-current/8`, `bg-current/12` on hover. A visible target that still reads as part of the alert, with no grey.
- **`link`:** tone text, medium weight, underlined on hover, no background. The action blends into the alert and reads as the message's next step: for a notice where the message is the subject, such as "View details →". At least a 24px target.

```vue
<Alert variant="info" title="Backups run nightly" actions-style="link">
    Scheduled backups start at 2:00 each night.
    <template #actions><Button href="/backups">View schedule</Button></template>
</Alert>
```

The Button's variant says how loud the action is, not its colour. `primary`, `success` and `destructive` are the alert's main action in the chosen style; `secondary`, `ghost` and `link` are a quiet, normal-weight tone link in either style (Dismiss, Learn more). A plain `<a>` or `<button>` in `#actions` takes the tone's colour too. A Button in the alert's body keeps the host's look.

The focus ring is a 2px outline in the tone, 2px out.

## Tones and contrast

The tone is the pale tier's semantic text token (`--info-text`, `--success-text`, `--warning-text`, `--destructive-text`; the alert's own foreground for `muted`, `primary` and `brand`) and the solid tier's white text. Every pairing keeps 4.5:1 against what is painted behind it, in light and dark, at rest and on hover. The soft tint is capped at 12%: at 20%, light success text on its tint falls to 4.1:1.

| Light, pale | soft | soft, hover | link |
| --- | --- | --- | --- |
| info | 5.07 | 4.76 | 5.69 |
| success | 4.96 | 4.65 | 5.54 |
| warning | 5.21 | 4.87 | 5.82 |
| danger | 5.20 | 4.84 | 5.92 |
| muted | 15.28 | 13.89 | 18.16 |

Dark is higher throughout (the lowest is danger soft on hover, 5.54:1), and the solid tier's lowest is 5.47:1. Screenshots of every tone in both styles, light and dark, at 390 and 1280px are in [shots/alert-actions](shots/alert-actions); the `soft` ones are the default, drawn with no `actionsStyle`.

## Coverage

The browser job (`browser/run.mjs`, `?alert-actions`, `=dark`) renders every tone and tier in both styles (soft by leaving `actionsStyle` out) and reads the actions as painted (translucent backgrounds composited): each action is the alert's tone, a soft action is tinted, 32px tall and deepens on hover, a link has no background and underlines on hover, the quiet action is a normal-weight link, every ratio is at least 4.5:1 at rest and on hover, the focus outline is 2px in the tone, a plain link and a Button with `href` take the tone (the Button soft by default, the plain link still a link), a Button in the body keeps the host's secondary, nothing overflows at 390px, and axe with contrast passes. `KIT_ALERT_ACTIONS_SCREENSHOTS=<dir>` saves the screenshots; `KIT_ALERT_ACTIONS_CONTRAST=<file>` writes the ratios.
