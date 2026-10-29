# Button

`<Button>` (`Kit/Form/Button.vue`) is the kit's button. Its variants are primary, secondary, destructive, ghost, link and `bar`, and on a Surface it takes the surface's shape and tone.

## Sizes

`size` is `default`, `sm`, `lg` or `icon`, which are the host button's own sizes, or `xl`. `xl` is the host's `lg` made a large tap target, about 96px tall, for a phone used as a remote.

```vue
<Button size="xl" @click="next">Next slide</Button>
```

## As a link

With `href`, the button is a link styled as a button. The host button styles its child (`as-child`), so there's one element, the link, and never a `<button>` inside an `<a>`. Variants and sizes are the same.

```vue
<Button :href="route('decks.show', deck)">Open</Button>
<Button :href="route('decks.publish', deck)" method="post" :data="{ notify: true }" preserve-scroll>Publish</Button>
<Button href="https://example.com/help" external target="_blank" variant="link">Help</Button>
```

- Without `external`, it's an Inertia `<Link>` that takes `method`, `data` (`VisitData`, the kit's own type) and `preserve-scroll`. A `method` other than `get` renders a `<button>`, since Inertia requires that for a non-GET visit, so it still never sits inside an `<a>`.
- `external` renders a plain `<a>`, with `rel="noopener noreferrer"` when `target` is `_blank`.
- `type` applies only to the button form.

## Coverage

`composer test:js` checks the sizes and the link forms by source. The components vue-tsc job compiles a fixture with `lg`, `xl`, an Inertia link, a POST link with `data`, and an external link.
