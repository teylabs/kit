# Surface

`<Surface>` (`@kit/ui`) is a card-like container: an inspector panel, a toolbar group, a slide's frame. It draws the padding, a border, the radius and the card tokens (`bg-card`, `text-card-foreground`), and provides its tone and shape to what is inside (`provideSurface`), so a kit Button or ActionBar on it blends in.

```vue
<Surface padding="sm" translucent :clip="false">
    <InspectorSection />
</Surface>
```

| Prop | Default | |
|---|---|---|
| `tone` | `default` | `inverse` is a dark surface with light controls. |
| `shape` | `default` | `pill` is fully rounded. |
| `padding` | `md` | `none`, `sm` or `md`. |
| `as` | `div` | Its element. |
| `clip` | `true` | Clips what is inside to the radius (`overflow-hidden`). Set `false` when a child is `sticky`: an `overflow-hidden` ancestor becomes its scroll container and pins it in place. |
| `translucent` | `false` | Lets what is behind show through: an 80% fill (`bg-card/80`, or `bg-foreground/80` when inverse) and a backdrop blur. |

`translucent` stays legible and calm under every preference:

- Without `backdrop-filter` support the fill stays solid, so text never sits on an unblurred background.
- Under `prefers-contrast: more` and `prefers-reduced-transparency: reduce` the fill is solid and there is no blur.
- Under `prefers-reduced-motion: reduce` there is no blur: a blur over moving content (a playing slide, a scrolling list) reads as motion.

The rendered element carries `data-surface`, `data-tone`, `data-shape` and, when translucent, `data-translucent`.

The type of a surface's tone and shape is exported as `SurfaceContext` (`provideSurface`, `useSurface`).
