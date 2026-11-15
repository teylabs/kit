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

Both actions above are soft buttons tinted with the warning tone.

## Styles

Every action in an alert is a soft button by default, whatever its variant: `primary`, `secondary`, `ghost`, `success` and `destructive` all draw the same. An action is a link only when the caller asks for one, for the whole alert or for that action.

- **`soft`:** a compact button (32px, `size="sm"`), medium weight, tinted with the tone: `bg-current/8`, `bg-current/12` on hover. A visible target that still reads as part of the alert, with no grey.
- **`link`:** tone text, underlined on hover, no background. The action blends into the alert and reads as the message's next step: for a notice where the message is the subject, such as "View details →". At least a 24px target. A `primary`, `success` or `destructive` Button is medium weight as a link; the rest are normal weight.

### One action as a link

Give that Button `variant="link"`. It is a link in either style; the alert's other actions stay soft:

```vue
<Alert variant="danger" title="Sync failed">
    The last sync stopped after three errors.
    <template #actions>
        <Button @click="retry">Retry sync</Button>
        <Button variant="secondary" href="/sync/errors">View errors</Button>
        <Button variant="link" href="/status">Status page</Button>
    </template>
</Alert>
```

### Every action as a link

`actions-style="link"` on the alert (`actionsStyle` is `soft`, the default, or `link`) makes every action in it a link:

```vue
<Alert variant="info" title="Backups run nightly" actions-style="link">
    Scheduled backups start at 2:00 each night.
    <template #actions><Button href="/backups">View schedule</Button></template>
</Alert>
```

The Button's variant never picks the action's colour: the alert's tone does. A plain `<a>` or `<button>` in `#actions` takes the tone's colour too and keeps its own shape. A Button in the alert's body keeps the host's look.

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

Dark is higher throughout (the lowest is danger soft on hover, 5.54:1), and the solid tier's lowest is 5.47:1. A secondary action in a soft alert is drawn exactly as the primary one, so it has the same ratios, and a `variant="link"` action in a soft alert has the link column's. Screenshots of every tone in both styles, light and dark, at 390 and 1280px are in [shots/alert-actions](shots/alert-actions). Each holds a primary, a `secondary` and a `variant="link"` Button. The `soft` ones are the default, drawn with no `actionsStyle`.

## Coverage

The browser job (`browser/run.mjs`, `?alert-actions`, `=dark`) renders every tone and tier in both styles (soft by leaving `actionsStyle` out) and reads the actions as painted (translucent backgrounds composited): each action is the alert's tone; in a soft alert the primary and the `secondary` Button are both soft (tinted, 32px tall, medium weight, deeper on hover) and the `variant="link"` Button is a normal-weight link; in a link alert all three are links (no background, underlined on hover, the primary medium weight and the others normal); every ratio is at least 4.5:1 at rest and on hover; the focus outline is 2px in the tone on each action; a plain link, a Button with `href` and a `ghost` Button take the tone (both Buttons soft by default, the plain link still a link); a Button in the body keeps the host's secondary; nothing overflows at 390px; and axe with contrast passes. `KIT_ALERT_ACTIONS_SCREENSHOTS=<dir>` saves the screenshots; `KIT_ALERT_ACTIONS_CONTRAST=<file>` writes the ratios.
