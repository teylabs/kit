# Images in InputRichText

Images in the rich-text editor are opt-in. A field takes them only when it is given all three:

- `allow` naming `image`;
- `image-sources`, a pattern for the host's own image references (such as `/^media:[0-9a-f-]{36}$/`);
- `upload-image`, a callback that uploads a `File` and resolves to its `src` (or `{ src, alt }`).

```vue
<InputRichText
    v-model="form.body"
    label="Report"
    :allow="['image']"
    :image-sources="/^media:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/"
    :upload-image="async file => `media:${(await upload(file)).uuid}`"
    :image-url="src => route('media.show', src.slice(6))"
    @image-error="error => toast.error('That image could not be uploaded.')"
/>
```

With them:

- The toolbar shows an **Insert image** control. It opens a file picker (PNG, JPEG, GIF, WebP, AVIF; several at once), and each image goes in at the cursor, in order.
- Pasted or dropped image files are uploaded the same way and go in where they were dropped. Any other file is still emitted as `file-intent`.
- The document stores an `image` block, `{ type: 'image', attrs: { src, alt } }`. It sits between other blocks, or in a blockquote or list item, but never inside a paragraph.
- `image-url` maps a stored `src` to the address the editor shows. Without it, the `src` is used as is.
- While an upload runs, the field shows "Uploading image…" as a status message. A failed upload, or one whose `src` doesn't match `image-sources`, inserts nothing and emits `image-error`.

Failing closed:

- Without all three, there is no image control and no upload. A stored document holding an image locks the field with `content-error` unless images are allowed and its `src` matches.
- A `src` that doesn't match the pattern is refused, as are whitespace, quotes and angle brackets. So a pasted URL, a `data:` URL or `javascript:` never enters the document.
- SVG is not uploaded, because it can carry script.
- Only the editor's own images (`img[data-src]`) parse back from pasted HTML. An `<img>` copied from another page is dropped.

**The host's server must enforce the same rule:** every `image` block's `src` matches its pattern and names media the user may read. The kit does not validate on the server.

Tests: `tests/js/rich-text.test.mjs` covers the schema (round trip, every refusal), the file split and the editor's wiring by source. No test here clicks the toolbar or uploads in a real browser.
