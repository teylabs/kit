# Composer

`Composer` is a seamless composer: one quiet field on the page, with no card title and no boxed textarea inside a card. The notes feature's composer is built on it, so every app's note box looks the same.

- **Closed** it is one line: `#avatar`, then the placeholder.
- **Open** (focused, holding text, or `open` set by the host, say while files are chosen) the field grows to a few lines, `#below` shows under it (chosen files, an error), and a toolbar shows `#tools` on the left (attach, colour) and Cancel and the submit button on the right. The whole box draws the focus ring.
- **Keys:** Escape (or Cancel) closes it while empty; ⌘/Ctrl+Enter submits.

| Prop | Default | |
| --- | --- | --- |
| `v-model` | `''` | The text. |
| `placeholder`, `label` | `Write something…` | `label` names the field (the placeholder when unset). |
| `submitLabel` | `Post` | The submit button. |
| `busy` | `false` | Disables submit while saving. |
| `open` | `false` | Keeps it open. |
| `as` | `form` | `form` emits `submit` with the text. `div` sits inside the page's own `<Form>`: the textarea is the named field (`name`, default `body`) and the button submits that form. |
| `id`, `maxlength`, `invalid`, `describedby` | | Passed to the textarea. |

Wrap it in a compact `BulkDrop` to take files dropped or pasted onto it, and call the slot's `browse` from an "Attach a file" button in `#tools` (as the notes composer does).

Tests: the browser suite's notes composer checks (`notes.html`): closed then open on focus, the named colour radios and Attach a file by keyboard, Remove, drop and paste, one save with exactly the listed files, then cleared; axe in light and dark, closed, open and with files.
