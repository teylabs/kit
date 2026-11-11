# Changelog

## Unreleased

A minor release (v0.16.0): it renames two public components.

### Breaking

- **`ActionCard` and `SummaryCard` swap names** to match the apps they came from. `ActionCard` is now the plain panel (title, `#description`, `#actions`, default slot below), which was `SummaryCard`. `SummaryCard` is now the header-band card (muted header, `#icon`, `#actions`, `#body`, sticky `#footer`), which was `ActionCard`. Each card keeps its props, slots and look under its new name.

  | Before v0.16 | From v0.16 |
  | --- | --- |
  | `ActionCard` (header band) | `SummaryCard` |
  | `SummaryCard` (panel) | `ActionCard` |

### Upgrading

- Run `kit:update plan|apply` for `core`, then `php artisan kit:status`. A new attention step, **card names**, lists each `.vue` file under `resources/js` that still uses an old name, with the rename to make. It flags an `<ActionCard>` with `#icon`, `#body`, `#footer` or an `editUrl`, and a `<SummaryCard>` with `#description`. It is never blocking. A card that uses only `title` and the default slot can't be told apart, so check those by eye. Steps: [docs/cards.md › Upgrading to 0.16](docs/cards.md#upgrading-to-016).
- An app that already called the panel ActionCard keeps its markup.

### Added

- **The panel (`ActionCard`) gains `tone="danger"`, `align` and a `description` prop.** `align="top"` pins the actions top-right, and `center` stays the default. On a phone the actions stack under the text. `tone="danger"` gives a red edge and a red title and keeps the card background (no tint), so the description and the actions keep their contrast in light and dark. Slots that render nothing (an empty `#actions`, a false `v-if`) leave no wrapper.
- **`CardHeader` gains `actionsAlign`** (`center`, the default and the old layout, or `start`) **and `stackActions`** (actions on their own line below `sm`; off by default).
- **ActionBar and ButtonGroup gain `itemClass?: string`.** It is added to every menu row after the row's own classes, so an adopter can set its own row height. Destructive and disabled rows keep their look. ActionBar passes it to its menus. ButtonGroup provides it to the rows in its `#menu` (ActionMenuItems, DropdownItem, DropdownLink and the components built on them). `ActionBarProps` and `ButtonGroupProps` gain the optional prop.

- **ActionBar `fillAll`: one primary pill.** On a pill surface, `fill-all` makes the whole group the primary fill with the primary text, rounded-full. Every segment is `py-2`, the first `pl-3.5` and the rest `px-3`, and a disabled segment still dims. It is a new optional boolean, so `filled` keeps its type. Off a pill surface it does nothing, and the default pill padding is unchanged. `ActionBarProps` gains `fillAll?: boolean`.

### Changed

- **`KIT_BRAND_*` env values are now removed in 0.17, not 0.16.** They still work, deprecated, in this release. The deprecation log, `kit:status`, the `config/brand.php` that `kit:brand:migrate` writes, the published brand stub and the docs now say 0.17.
- **The deprecated `welcome` card and `WelcomeCardData` are now removed in 0.17, not 0.16.** They still work in this release. The notes in the dashboard view model, the data class, the workspace-ui Dashboard page and docs/dashboard.md now say 0.17. Re-pins `workspace-ui` (comment only): `sha256:996b8585…04db` → `sha256:155ea22c…48f3`.
- **A GET action with intent `panel` is a button, not a link.** `isNavigation` and `rendersAsLink` now treat `panel` like `dialog`: the action renders as a button that emits `@action` (or, with no listener, runs as an operation), even with a GET href. Before, it rendered as an anchor that navigated.

### Fixed

- **The header-band card (now `SummaryCard`) no longer renders an empty body.** With no default slot, or one that renders nothing, and no `#body`, it leaves out its CardBody, so there is no empty band or rule under the header.

New internal helpers: `Card/slotContent.ts` (`hasSlotContent`) and `Action/menuItemClass.ts`. Neither is exported from the barrel. Covered by `tests/js/card-names.test.mjs`, `tests/js/menu-item-class.test.mjs`, `tests/js/actionbar-fill-all.test.mjs` and new `test:status` cases (the old-name check). There are also five new browser checks. `?card-names`, light and dark, at 1280 and 390px, checks the panel's layout, the phone stack, empty slots and SummaryCard's empty body, and runs axe with colour contrast on the danger cards' title, description and buttons, quiet and filled. `?item-class` checks 48px rows with Cancel still red and the disabled row dimmed. `?fill-all`, light and dark, checks the filled pill's colour, radius and padding, disabled dimming on it, the unchanged default pill, and a panel-intent GET rendering as a button that emits. The browser floor goes from 299 to 304. core is re-pinned (see the PR body).

## v0.15.88

### Added

- **ActionBar `wrap`: an opt-in wrapping mode for a phone header.** With `wrap`, the bar never compacts into a More menu: every action stays visible and the row wraps onto as many rows as it needs. Without declared groups each action is its own button (only the first takes `primary`/`filled`); declared groups and their `menu` actions are kept as declared. Nothing is measured and the compact copy is not rendered in wrap mode. The default (no `wrap`) is unchanged. `wrap?: boolean` is added to `ActionBarProps`.
- **Menu icon defaults for common intents.** In a menu, an action with no `icon` now gets one by key, else by label: `reopen`/`re-open` → `lock-open`, `cancel`/`decline` → `x`, `merge` → `merge`, `delete`/`remove` → `trash`, `download` → `download`, and `edit` → `pencil` by label too (by key it already had it). An explicit `icon` always wins; inline segments keep only the `plus` default. `lock-open`, `x`, `merge` and `download` are new known icon names in `actionIcons`.
- **A dev-only warning for menus that mix icons.** In a dev build (`import.meta.env.DEV`), an ActionBar menu or a ButtonGroup menu whose rows mix items with and without an icon logs one `console.warn` per menu. Production builds never warn.

### Fixed

- **ActionBar menu rows without an icon line up with rows that have one.** A row with no icon, in a menu where another row has one, now keeps an icon-sized spacer, so the labels share one left edge. The destructive (red) style of a Cancel-type row is unchanged and now pinned by a test.

Covered by `tests/js/actionbar-wrap.test.mjs` and two new browser checks (`?wrap-bar`, light and dark, at 390px: all six actions visible on more than one row, no compact copy or More menu in wrap mode; in the default mode's More menu, Cancel is in the destructive colour and labels line up with and without an icon; no mixed-icons warning in the production build). The browser floor goes from 297 to 299. core re-pinned (see the PR body).

## v0.15.87

### Added

- **ActionBar `filled`: an opt-in filled primary segment on the outline path.** Before, the leading segment on the ButtonGroup (outline) path was only semibold; a host wanting the primary fill there styled it itself. With `filled` the leading segment of each group takes `bg-primary` with the primary foreground (and `bg-primary/90` on hover); it implies `primary`, so a pill surface fills it as before. Without it nothing changes: `primary` alone stays semibold on the outline. `ActionBarProps` gains the optional `filled`.
- **Two more action icons, `history` and `mail`.** `actionIcons` (and so `actionIconName`'s names) now covers them; an unknown name still renders no icon. The key-based fallback (`edit` → pencil, `payment` → credit card, in a menu) and `plus` for an Add, New or Create label were already in `actionIconName` and are now pinned by tests.

### Fixed

- **A disabled ButtonGroup segment reads as disabled, hover included.** A segment with the `disabled` attribute (an ActionBar segment blocked while another action runs) had no dimming of its own, and a server-disabled one (`aria-disabled`) lost its dimming on hover, where the group's hover opacity outranked it. ButtonGroup now dims both (opacity 50%, not-allowed cursor) at rest and on hover, on either appearance, including a filled leading segment; the action in flight (`aria-busy`) is left undimmed.
  - **API.** Additive only: one optional prop (`filled`) on ActionBar and `ActionBarProps`, two icon names. No public signature or prop type changed. `core` is re-pinned (`ActionBar.vue`, `ButtonGroup.vue`, `actionIcons.ts`, `contract.ts`).
  - **Tests.** `tests/js/actionbar-filled.test.mjs` (the prop, its default and both ButtonGroup layouts; the disabled rule; the icon names) and new `actionIconName` cases in `tests/js/actions.test.mjs`. Browser: `?filled-bar`, light and dark, reads computed styles: `primary` alone unfilled, `filled` on the primary token, disabled segments at 50% at rest and on hover.

## v0.15.86

### Fixed

- **Multipart uploads are joined at a staging key, never at the content-addressed key.** Before, the parts were joined at the final key under the default `full` signing, so another upload's complete for the same SHA-256 could replace the object after the re-hash (before or after the record was ready). Now `RequestMultipartUpload`, `CompleteMultipartUpload` and `AbortMultipartUpload` use a per-record staging key (`<dir>/.staging/<record id>`, `MultipartUploads::stagingKey()`). Complete re-hashes the staged object and only then places it on the final key. If an object is already there it is re-hashed and reused when it matches (an upload from before this release may have left unverified bytes there). Otherwise it is copied server-side with `CopyObject` and `If-None-Match: *`, so it never overwrites. The staged object is deleted afterwards. A mismatch deletes only the staged object; the final key is never touched. The multipart id stays set until the object is in place, so no ordinary confirm runs first. `content_type` takes the same path to its random key.
  - **Fallback.** A store without the new `ConditionalCopyStore`, and S3 objects over the 5 GiB `CopyObject` limit, get HEAD-then-copy, which can't rule out a write landing between the two.
  - **Prune.** `kit:uploads:prune` deletes a stale row's staging object after aborting its upload; a delete that fails keeps the row. It also aborts the upload of a ready row that still holds a multipart id (a replay's upload whose abort failed during complete) and clears the id; a failed abort keeps it and fails the run.
  - **Upgrading.** A multipart upload still in flight at deploy was started at the final key, so its complete is refused (`media.upload.incomplete`) and the client starts again; `AbortMultipartUpload` (and so the pruner) falls back to aborting at the final key, so such uploads are still cleaned up.
  - **API.** New `ConditionalCopyStore` interface, implemented by `S3MultipartStore` (final); `MultipartStore` is unchanged. New `MultipartUploads::stagingKey()` (final class). No column and no public signature changed; `ConfirmUpload` and the single PUT are unchanged. No catalog stub changed, so no feature is re-pinned.
  - **Tests.** `test:media-multipart`: other bytes for a ready key refused at staging with the ready object untouched, the same bytes reusing the existing object, an object that appears first never overwritten, same-size wrong bytes at the key refused by the re-hash, both copy paths, and the CopyObject call over a mocked client. `test:media-prune`: stale staging objects deleted, recent ones kept.

## v0.15.85

### Added

- **Opt-in `kit.media.signing` for direct uploads.** `full` (the default, env `KIT_UPLOADS_SIGNING`) is today's behaviour, byte-identical: the same tickets, signed options, keys, dedupe query and confirm. `content_type`, for a bucket or client that rejects the extra signed headers, signs only Content-Type on the presigned PUT, uses a random key (`<prefix>/<ulid>.<ext>`, the extension from the collection's rules), keeps the declared SHA-256 as advisory metadata only, and never dedupes by it (no lookup, no reuse of rows or objects). Read with a `config()` default, so a published config without it is unaffected; an unknown value is refused.
  - **Integrity, fail closed.** With no signed checksum, `ConfirmUpload` re-hashes the whole object as a stream before marking it ready, on any disk; a mismatch deletes the object and leaves the record pending (`media.upload.mismatch`). The record's key decides (a key not named after its SHA-256 is always re-hashed), so a setting changed between request and confirm can't skip it. That includes the POST fallback: the stored object is re-hashed after it is written. An unknown value is refused before any replay lookup. Cost: one full read per confirmed upload in this mode.
  - **With multipart.** Independent of `multipart.part_checksums`: a multipart upload under `content_type` gets a random key and no dedupe, part checksums follow their own setting, and `CompleteMultipartUpload` re-hashes, and `ConfirmUpload` re-hashes again at the ready transition (two full reads in this mode, so a replacement in between is caught). For R2 or MinIO, `content_type` plus `part_checksums: false` leaves integrity to the re-hash. docs/media.md › Signing.
  - New final `Tey\Kit\Media\UploadSigning`; `MediaRules` (final) gains `randomKey()`. Additive: no public signature changed and no method added to a non-final class (`RequestUpload` and `ConfirmUpload` change only inside `handle()`/`ticket()`). No catalog stub changed, so no feature is re-pinned.
  - **Tests.** `test:media-signing` (new, in CI).

## v0.15.84

### Added

- **Opt-in S3 multipart for large direct uploads.** Off by default. With the flag off, uploads behave exactly as before: the same tickets, the same request body and the same keys. `kit.media.multipart` (`enabled`, env `KIT_UPLOADS_MULTIPART`, default false; `threshold_bytes`, default 100 MiB, as configured; `part_bytes`, default 16 MiB and never under 5 MiB; `part_checksums`, default true) is read with `config()` defaults, so a published config without it is unaffected. A file over the threshold uses multipart only when all of these hold: the disk is an `AwsS3V3Adapter`, the client offered it (`direct.complete`), and the uploads table has the new column. Otherwise it is the single PUT, and other disks ignore the flag.
  - **New final operations:** `RequestMultipartUpload` (it runs `RequestUpload` unchanged, then turns an eligible PUT ticket into `MULTIPART`), `CompleteMultipartUpload` and `AbortMultipartUpload`. Supporting classes: `MultipartUploads`, the `MultipartStore` interface and `S3MultipartStore`. `UploadTicket` (final) gains optional `uploadId` and `parts`, serialised only on a `MULTIPART` ticket. `MediaUploads::addMultipart()` adds a nullable `multipart_upload_id`. `schema()` includes it, and an existing table needs one additive migration. `league/flysystem-aws-s3-v3` is suggested, not required.
  - **`ConfirmUpload` refuses a record with an open multipart upload** (`media.upload.multipart_pending`). Only `CompleteMultipartUpload` confirms it, after the re-hash, and it keeps the id until the re-hash has run. With the flag off the id is always null, so nothing changes.
  - **Integrity, fail closed.** S3 has no whole-object SHA-256 for multipart, only a composite. So complete always re-hashes the joined object as a stream and compares its size and SHA-256 with the declaration before `ConfirmUpload` marks it ready. Per-part signed SHA-256 adds a transit check, and `part_checksums: false` turns it off for R2 or MinIO. Dedupe and content-addressed keys are unchanged. The cost is one full read of the object per completed upload. Failures:
    - Bad or missing parts, ETags or checksums are refused before S3 is called (`media.upload.bad_parts`).
    - If S3 refuses the complete, the upload is aborted (`media.upload.incomplete`).
    - If the re-hash doesn't match, the object is deleted unless a ready record shares it (`media.upload.mismatch`).
  - **Client.** `directUpload.ts`: `DirectRoutes` gains optional `complete` and `abort`, `DirectTransport` an optional `putPart`, and `directUpload()` an optional `signal`. The request body gains `multipart: true` and later `part_sha256`, and only when `complete` is given. The complete body carries `file_name` like confirm does. Parts upload four at a time with up to three tries each. A missing ETag, a part that keeps failing, or a cancel aborts, and no complete is sent. New exports: `partSlices`, `PART_CONCURRENCY` and `PART_ATTEMPTS`. The bucket's CORS must expose `ETag`.
  - **Docs.** docs/media.md › Large files: S3 multipart has the host's `store`, `complete` and `abort` routes and the migration.
  - **`kit:uploads:prune` aborts stale multipart uploads.** It aborts a stale pending row's open multipart upload before it deletes the row, so S3 drops the parts. An abort that fails (S3 refuses, or the disk has no store) keeps the key's rows and object, is reported and fails the run. `--dry-run` reports the count, and a run with none prints exactly as before. `AbortMultipartUpload` keeps the row's `updated_at`, so an aborted row is still pruned in the same run.
  - **Tests.** `test:media-multipart` (new, in CI), `tests/js/direct-upload-multipart.test.mjs`, and `test:media-prune` (it now also covers the dry-run count, an abort that fails, aborting then pruning, a recent upload left open, and a disk with no store). No real S3, R2 or MinIO is exercised.
  - Additive: no public signature changed, and no method added to a non-final class. core re-pinned: `sha256:205bf582…e26a` → `sha256:cb785640…f04b` (over v0.15.83's). In `history.json` only the `current` block changed: `Kit/Form/directUpload.ts` re-hashed.

## v0.15.83

### Added

- **Direct uploads keep the browser's file name.** `directUpload` now sends the File's own name as `file_name` in the request body and in every confirm body (JSON and multipart). New final `Tey\Kit\Media\ClientFileName`: `ClientFileName::attach($upload, $owner, $collection, $clientName, $name = null)` runs `AttachUpload` with the name for that one upload, cleared in a `finally`, so it never carries into another attach, a queued job or another request. The name is sanitised on the server: only the last path segment is kept (`../../x.pdf` is `x.pdf`), control and format characters are removed, invalid UTF-8 is refused, it is capped at 200 bytes, and its extension is replaced with the storage key's. A missing or unusable name falls back to the generated one. It is stored in the new `client_file_name` custom property, and `MediaDelivery` serves the original under it (`Content-Disposition` with an RFC 6266 `filename*` and an ASCII `filename` fallback, proxied or redirected; conversions keep theirs). `ClientFileName::disposition()` gives the same header to a route of your own. The storage key, the MIME type and Spatie's `file_name` stay the server's: medialibrary finds the object by `file_name`, so it remains the key's last part. Additive: `AttachUpload::handle()` keeps its signature and gains no method. The confirm example in docs/media.md passes `file_name`. Covered by `test:media-uploads` (round trip, path traversal, a 500-character name, control and bidi characters, fallback, a host name winning, a throwing attach leaving no name set) and `test:media-delivery` (the download name for a document and an image, proxied and redirected, and a stored name sanitised again).
- **`UploadLimit::message($kilobytes)`: the size limit in binary units.** "Files can be up to 10 MiB" for 10240 KB, "Files can be up to 1.5 MiB" for 1536 KB. MiB is 1024² bytes, rounded down to one decimal place where needed, so it never promises more. The wording is the new `kit::ui.upload.limit` key (`:size`), overridden in `lang/vendor/kit/{locale}/ui.php` (documented in docs/media.md). Covered by `test:upload-limits` (10240 KB, 1536 KB, rounding down, the kit's own lang file, an override).
- core re-pinned (Kit/Form/directUpload.ts, Kit/Form/captureFiles.ts): `sha256:f93a66d9…ecb8` → `sha256:205bf582…e26a`; in `history.json` only the `current` block changed. Run `kit:update plan|apply` for `core`.

### Changed

- **Behaviour change: `RequestUpload`'s size refusal is in MiB.** It reads "Files can be up to 1.5 MiB." (`UploadLimit::message()`), not "The file may be at most 1536 KB.".
- **Behaviour change: BulkDrop's limit is in MiB.** The fallback hint and the size refusal use 1024² bytes: "Up to 10 MiB each" and "plan.pdf is over 10 MiB", where the same limit read "Up to 10.5 MB each" before. Covered by `tests/js/split-drop.test.mjs`.
- **Behaviour change: the default `name` from `ClientFileName::attach()`.** With no `$name`, the item's `name` is the client file name's stem, not the key's. `AttachUpload::run()` alone is unchanged.

## v0.15.82

### Added

- **`kit:uploads:prune` clears abandoned direct uploads.** It deletes upload rows still `pending` after `--older-than` hours (default `kit.media.prune_older_than`, 24), and their objects only when no other upload row (any status or scope) and no Media item over the key remain; otherwise the object stays and only the stale rows go. Ready rows are never pruned. `--dry-run` reports the rows, objects, bytes and sample keys and changes nothing; `--chunk` (500) sets the read size. Fail closed: a key whose references can't be checked (a missing table, a disk error) keeps its rows and object, is reported and fails the run. References are matched across scopes because content-addressed keys aren't unique to a tenant; with separate tenant databases objects are always kept. Opt-in scheduling with `kit.media.prune_schedule` (`true` for daily, or a cron expression), off by default. Documented in `docs/media.md`. Additive: a new command and config keys, no signature changed. Covered by the new `test:media-prune`.

## v0.15.81

### Changed

- **Settings › Email sending recommends sending from the workspace's domain itself, with a live preview of what mail will show.** Every behaviour change:
  - **Root-domain sending.** `WorkspaceMailIdentity::update()` (and the page's PUT) accepts a new input value `from_mode: 'root'`: mail goes from `<local>@<domain>` with no `send.` prefix, and the domain is created at the provider as typed (lowercased). It is **stored** as `from_mode: 'domain'` with the bare domain, so the stored modes stay `platform` and `domain`, `MailIdentityView::$from_mode` keeps its `'platform' | 'domain'` type, and no data migration is needed: a row's choice is read from its domain's prefix. `root` with a `send.` domain is refused on `domain` (it would read back as the subdomain). Until the domain is verified, mail keeps the platform address, as before.
  - **Existing `send.` setups are unchanged.** No row is migrated and no default flips: a stored `send.<domain>` row shows as the send. subdomain choice and stays on it until someone explicitly picks another; `from_mode: 'domain'` posted with a bare domain still stores `send.<domain>` (the server-side default is unchanged); a workspace with no row still defaults to the platform address.
  - **The page now recommends root.** The From address choices are "Your own domain (recommended)", "Platform address", and, under a new "Advanced" disclosure (open when the send. subdomain is the saved choice), "A send. subdomain of your domain". The Domain and Address name fields sit inline under the chosen radio, and the custom reply-to field is indented under its radio; the form is a single column with no `:span` grid. The Domain field's label is now "Domain" (was "Your domain").
  - **"Mail will show as"**: the From line and Replies-to, computed from the form as it is typed (`fromPreview`, `fromLine`). Below it, "Right now, mail goes from …" or "Until <domain> is verified, mail goes from …", from the server-resolved `effective_from` only, never the form. The domain section no longer repeats its own "Mail goes from" paragraph.
  - **DNS records are a list, not a table.** Each record shows what it is for (`recordKind`: DKIM, SPF, MX, Return path), its status badge, a Name row with the full name in its title (`fullRecordName`) and a copy button, and a Value row with a copy button.
  - **"Last checked"** is a human time in the viewer's timezone ("Today 4:37 PM", "Yesterday …", "Nov 3, …"), via `checkedAtLabel` and `useTimezone`; a stored timestamp without an offset is read as UTC. v0.15.75's refusal Alert stays above Check now.
  - **Known gap:** switching back to a domain the workspace created earlier creates it at the provider again (no per-workspace memory of provider domains).

### Added

- **`CopyButton` and the Email sending helpers in `@/components/Kit`.** `CopyButton` (`Kit/Action/CopyButton.vue`, `CopyButtonProps` in `contract.ts`) copies `value` and claims success only after the clipboard write resolves, spoken through a polite status; a refused clipboard shows the value read-only to copy by hand. `fromLine`, `fromPreview`, `recordKind`, `fullRecordName` and `checkedAtLabel` (`Kit/Data/mailSending.ts`, with the `MailDnsRecord` type) are plain functions over strings and Intl, covered by `tests/js/mail-sending.test.mjs`. A host whose own `@kit/ui` does not `export * from '@/components/Kit'` must now also export `CopyButton`, `checkedAtLabel`, `fromLine`, `fromPreview`, `fullRecordName`, `recordKind` and `useTimezone` (the README's list). Additive: no prop, event, export or signature removed or changed, and no method added to a class hosts extend.

  Run `kit:update plan|apply` for `core` and `settings`. Covered by `test:mail-identity` (root stores the bare domain; an existing send. row is not migrated and a bare domain posted as `domain` still gets send.; root refuses a send. domain; `effective_from` is the platform address until verified, then `<local>@<domain>`; a member's or stale root save changes nothing; switching root, send. subdomain and platform), `test:form-payloads` (the recorded root payload saves as `domain` with no send.), and the browser checks (the summary updating live, the Advanced disclosure, the DNS list with its copy buttons and full names, Last checked in the viewer's timezone); the browser floor goes up by four (to 295 after the BulkDrop merge). Re-pinned: core `sha256:c347355a…de5a` → `sha256:f93a66d9…ecb8` (over the BulkDrop re-pin), settings `sha256:cf0b8c90…d271` → `sha256:3f397272…d5be`; in `history.json` only the `current` block changed (`Kit/Action/CopyButton.vue` and `Kit/Data/mailSending.ts` added; `Kit/index.ts`, `Kit/contract.ts` and `Workspace/Settings/Email/Page.vue` re-hashed).

## v0.15.80

### Added

- **BulkDrop can leave out its size limit.** New optional `showLimit` prop (default `true`): with no `hint`, BulkDrop shows `sizeHint(maxBytes)` as before; with `:show-limit="false"` and no `hint`, no hint element is rendered at all. A given `hint` always shows. `BulkDropProps` in the contract gains `showLimit?: boolean`. Covered by `tests/js/bulk-drop-hint.test.mjs` and a new browser check (`?bulk-drop`, the `no-limit` and `own-hint` states: no hint element and no `aria-describedby`, or the given hint). The browser floor goes up by 1. core re-pinned: `sha256:76ab8d94…d482` → `sha256:c347355a…de5a`; in `history.json` only the `current` block changed.
- **`KIT_NOTES_MAX_KB` sets the notes attachment cap.** `kit.notes.media.max_kb` is now `env('KIT_NOTES_MAX_KB', 10240)`, so an app raises it from `.env` instead of copying the whole `kit.notes` block (the config merge is shallow). Default unchanged. Covered by `test:upload-limits`.
- **`UploadLimit::fake()` and `PreflightCommand::uploadShortfalls()`.** A seam for checks to read faked `upload_max_filesize` / `post_max_size` instead of PHP's (null restores the real ini), and the configured caps kit:preflight warns about, as a public method. kit:preflight's warning is unchanged: each configured cap above PHP's or medialibrary's limit, naming the setting and both values. `cap()`'s return value is unchanged. Covered by `test:upload-limits` (through the seam, with the real ini checked untouched).

## v0.15.79

### Added

- **Deliver: copy every real send with `CopiesSends`.** A new opt-in interface `Tey\Kit\Features\Deliver\CopiesSends` (`copyTo(mixed $subject): list<string>`), registered with `Delivery::define(…)->copySends(…)`. DeliveryDesk Bccs those addresses on every send now, resend and scheduled send, never on a test send or a preview, and records each as a recipient with the new role `copy`, so the history shows it. They are deduped case-insensitively against the To, Cc and Bcc entered and against each other; an empty or invalid address is skipped and a `copyTo()` that throws copies no one, so a send never fails on it. A definition without it is unchanged. Documented in `docs/deliver.md`. Additive: `Delivery` (final) gains `copySends()` and `copiesFor()`; no public signature changed. `DeliveryRecipientView::$role` and the `deliver.ts` `DeliveryRecipient` type now include `'copy'`. Run `kit:update plan|apply` for `core` (re-pinned: Kit/Deliver/deliver.ts); core `sha256:ef996aa6…50d1` → `sha256:20e29a9e…a566`, and in `history.json` only the `current` block changed. Covered by `test:deliver` (a test send and a preview carry no copy; a send, a resend and a scheduled send Bcc and record them; dedupe against To, Cc, Bcc and each other; invalid and empty skipped; a throwing hook still sends; a definition without it records no copy).

## v0.15.78

### Fixed

- **A delivery from an app that finalizes through its own model no longer reads "Sent without finalizing."** The delivery desk only read `kit_finalizations`, so a host that finalizes through its own model (a `finalized_at` column) recorded `finalization_id = null` on every real send and the history labelled each one "Sent without finalizing.". New, opt-in interface `Tey\Kit\Features\Deliver\ResolvesFinalization` (`finalizationRef(Model $subject): ?string`, a stable token for what is final now, such as the id and `finalized_at`), passed to a new `Delivery::finalizationRef(ResolvesFinalization|callable)`. A send, a resend and a scheduled send store it in the new nullable `finalization_ref` column; the history's "no longer final" compares the stored ref with the current one, and "Sent without finalizing." shows only when a row has neither a `finalization_id` nor a `finalization_ref`. Additive: `Delivery` is final, so no host class gains a method, no public signature changes, and a definition without `finalizationRef()` keeps today's `kit_finalizations` behaviour exactly. Hosts may backfill `finalization_ref` to correct existing rows (docs/deliver.md, "Finalizing through your own model", has an example).
- **Migration:** `add_finalization_ref_to_kit_deliveries` adds the nullable `finalization_ref` string column to `kit_deliveries`. Run `php artisan migrate` after updating. Covered by `test:deliver` (the ref stored on send, resend and scheduled send; a changed ref is no longer final; a ref with no `kit_finalizations` row is not "without finalizing"; neither is; a backfilled ref corrects the label; a definition without the interface records no ref). deliver re-pinned: `sha256:5004ea8e…032d` → `sha256:261e3298…b6ea`; in `history.json` only the `current` block gains the new migration's hash.

## v0.15.77

### Added

- **`PageAside`, a page's aside column that sticks only while it fits.** An `<aside>` named by its required `label`, usually the last cell of a `main-aside` PageGrid. It puts the page's gap (`gap-6`) between its sections, so two stacked feeds never touch; each section takes its heading from a `PageSection` (docs/page-rhythm.md). From `md` it sticks below the shell header (`7.5rem`) while all of it fits the viewport, and stays in the flow and scrolls with the page when taller, re-measured on resize (the existing `useStickyWhenFits`); `:sticky="false"` never sticks, and the prop may change after mount. It renders `data-slot="page-aside"` with `data-sticky`, true only while it actually sticks (from `md`). Exported from `@/components/Kit`, with `PageAsideProps` in `contract.ts` for a host's own `@kit/ui`, and listed in the layout blocks surface. Additive: no prop, event, export or signature changes. Run `kit:update plan|apply` for `core`. Covered by a new browser test (`?page-aside`, `&tall`: two stacked sections 24px apart; short, it stays 7.5rem from the top as the page scrolls; taller than the viewport, it scrolls with the page and is not sticky; turning `sticky` off and on after mount unsticks and re-sticks it; at 390px it stacks under the main column with no sideways scroll); the browser floor goes from 289 to 290. core re-pinned: `sha256:ef996aa6…50d1` → `sha256:6c585acd…75d2`; in `history.json` only the `current` block changed (`Kit/Layout/PageAside.vue` added, `Kit/index.ts` and `Kit/contract.ts` re-hashed over v0.15.76).

## v0.15.76

### Added

- **Alert `actionsPlacement="inline"` and LockedForm `hideLockFooter`.** Alert gains an optional `actionsPlacement`: `below` (the default, today's layout and markup, unchanged) or `inline`, which puts the actions, from sm up, on the right, vertically centred beside the title and body, and stacks them below on a phone. LockedForm gains an optional `hideLockFooter` (default `false`): the area stays locked and read-only, but its notice (the FormCard footer such as "Read-only while … finalized", or a lone fieldset's notice) is not rendered and the root is not described by it, for a page that already shows a lock alert. Both are in `contract.ts` (`AlertProps`, `LockedFormProps`). Additive: no prop, event or export removed or changed. Run `kit:update plan|apply` for `core` (re-pinned: Kit/Feedback/Alert, Kit/Form/LockedForm, Kit/Form/useLocked, Kit/Form/FormCard, Kit/Form/FormFieldset, Kit/contract); core `sha256:ca8fe6ce…bb64` → `sha256:ef996aa6…50d1`, and in `history.json` only the `current` block changed. Covered by new browser tests (`?alert-layout`: the default actions under the body in the text column at 1280 and 390px, inline actions right of and centred on the text at 1280px and below it at 390px, the notice shown by default and hidden with `hideLockFooter` while still locked); the browser floor goes from 286 to 289.
## v0.15.75

### Fixed

- **Check now verifies a custom mail domain again, and shows the provider's refusal.** `ResendSendingDomains::check()` posted Resend's `POST /domains/{id}/verify` with Laravel's default empty data, which encodes as `[]`; Resend refuses it with 422 "expected object, received array", so a domain never left not_started. Verify now posts a `{}` JSON body, on a clone of the client so the follow-up `GET /domains/{id}` stays bodiless. Settings › Email sending already turned a refused verify or get into `errors.domain`, but the Check now form never rendered it, so the press looked like nothing happened; the page now shows the refusal (the provider's reason when it gave one) in a danger Alert above Check now, with no success toast and the status and Last checked left as they were. No public class or signature changed. Covered by `test:mail-identity` (verify's recorded body is `{}` with a JSON content type, and the old `[]` fails it; the GET after verify carries no body or JSON content type; a 422 from get after verify carries Resend's reason; Last checked moves forward after a check; an Inertia Check now refused by the provider goes back with the reason in `errors.domain`, claims no success and records nothing) and a new browser check (the refusal shows by Check now, no toast, still Waiting for DNS). The browser floor is raised by one (to 286 after the v0.15.74 merge). settings re-pinned: `sha256:27a85269…bfc4` → `sha256:cf0b8c90…d271`; in `history.json` only the `current` block's `Workspace/Settings/Email/Page.vue` hash changed.

## v0.15.74

### Added

- **`ScriptForm`, a form the page posts by script.** For a ceremony the page runs itself, such as Add a passkey (confirm the password with Fortify, then WebAuthn), which no Inertia visit can make. It renders a `<form>` marked `data-script-form` with the default always prevented, so Enter, the submit button and `required` work as in any form and the browser never posts it; `@submit` is emitted with the event and not at all while `busy` (no second ceremony on a double press), `busy` sets `aria-busy`, and the slot gets `busy`. Errors stay the page's own. Exported from `@/components/Kit`, with `ScriptFormProps` in `contract.ts` for a host's own `@kit/ui`, and listed in the form-frame surface; see docs/script-form.md. Security's Add a passkey and the post-sign-up `PasskeyOffer` now use it instead of a raw `<form>` (their markup, attributes and behaviour are unchanged), so a form guard that allows only Inertia's `<Form>` and `role="search"` filters no longer reads them as a plain post. Additive: no prop, event or export changes. Run `kit:update plan|apply` for `core` (re-pinned: Kit/Form/ScriptForm, Kit/Feedback/PasskeyOffer, Kit/index, Kit/contract) and `workspace-ui` (re-pinned: Workspace/Account/Security/Page). Covered by a new browser test (`?script-form`: Enter and the button submit with a prevented SubmitEvent, `required` is checked, no browser post, one ceremony at a time while busy); the browser floor goes from 284 to 285.

## v0.15.73

### Added

- **A finalize flow can declare extra fields for the certify request, on the web and over MCP.** New, optional `Flow::extras(rules, schema, apply)`: each field's validation rules, its JSON schema type, and an optional `apply(Model $subject, Finalization $finalization, array $extras, Principal $principal)` that runs inside the finalize transaction after the finalization is written (a failure rolls it back). The validated extras also ride on `Finalized::$extras` (a new trailing constructor argument, default `[]`). The kit stores none of them. Field names are top-level snake_case and never one the request already carries (`certified`, `revision`, `flow`, `subject`, `intent_key`, `confirm_token`, `extra`, `_token`, `_method`). `FinalizeGate::finalize()` gains a trailing `array $extra = []`: a name the flow does not declare, or any extra on a flow without extras, is `unknown_arguments` (fail closed), and the rules run after the gate's own workspace, permission, per-subject and finalized checks. The web route follows OperationForm: a page visit passes only the declared fields (a stray one is dropped) and shows a field's error inline, and a JSON caller passes its whole body, so an undeclared field is still a 422 `unknown_arguments`. The MCP `finalize_certify` tool gains an optional `extra` object, described by the declared schemas and validated by the same rules, only while some registered flow declares extras. `FinalizeControl` gains an `extra` prop (merged into its post; `certified` stays its own) and an `extra` slot between the checkbox and the button, given `errors` (the page's) and `disabled`. `Flows::all()` lists the registered flows. Registering a flow refuses an extras schema that does not describe exactly its declared fields, and an extra name another flow already declares with a different schema (the MCP tool publishes one `extra` object for all flows). Additive: `Flow` and `Flows` are final, so no host class gains a method, and a flow with no extras behaves exactly as before (the controller reads nothing beyond `certified`, and the MCP tool's arguments, rules and description are unchanged). Covered by `test:finalize` (declaration refusals, including a schema missing a field and a name declared twice with different schemas; an extra on a flow without extras refused; undeclared refused and invalid validated, with nothing written; the gate's checks before the rules; JSON strictness; a page visit's inline error and dropped stray field; `apply` inside the transaction; `Finalized::$extras` after the commit; the MCP tool's schema, rules and `call()` with valid, invalid and undeclared extras; finalizing without the optional extra) and the finalize browser check (the slot's inline error and the extra on the one certify post). The browser floor is unchanged. core re-pinned: `sha256:572eaf60…d0df` → `sha256:f30e7fa5…20b1`; in `history.json` only the `current` block's `Kit/Feedback/FinalizeControl.vue` hash changed.

## v0.15.72

### Fixed

- **A pending note thumbnail never falls back to the original.** Since v0.15.70, while a note image's queued `thumb` did not exist yet, its attachment's `thumb_url` was the original file's URL, so the wall, the dashboard feed and every other list showed and downloaded the full original. `thumb_url` is now null until the thumb exists, and a new `thumb_pending` is true for an image whose thumb is on its way (false once it exists, and always false for a file that never gets one, such as a PDF). The card keeps its existing placeholder icon while `thumb_url` is null. A replayed write (same `intent_key`) keeps its stored result but takes each attachment's `thumb_url` and `thumb_pending` from the file as it is now, so a retry shows a thumb made since, and a result stored by v0.15.70 never replays the original as `thumb_url`. Originals load only from the attachment's explicit open/download `url`. `NoteRecord`'s attachment shape and `types/kit.d.ts` gain `thumb_pending`. The media controller's auth, tenant and ETag logic is unchanged. No stub changed, so nothing is re-pinned. Covered by `test:notes` (with the conversion held on a null queue: no `thumb_url`, `thumb_pending` true, and no original URL in the listing or the dashboard feed outside `url`; after the conversion runs: the thumb URL, `thumb_pending` false, and the thumb served; a replay after the conversion, and a replay of a stored v0.15.70-style result).

## v0.15.71

### Changed

- **The shell's bars no longer composite a zero blur on every scroll frame.** `Workspace/Layout` applied `backdrop-blur-(--shell-bar-blur)` and `backdrop-blur-(--shell-subbar-blur)` unconditionally, so even an unset or `0` token gave the header and the breadcrumb bar a `backdrop-filter` layer that is repainted while scrolling (costly on older Macs). Each bar now reads its token off the element after mount, and again when the root's attributes change (a theme switch), and applies the blur class only when the token is a non-zero length (resolved on a hidden probe, so `calc(0px)` is zero too); unset, empty, `0`, `0px` or `calc(0px)` means no `backdrop-filter` at all. A configured blur renders as before (after mount). The bars carry `data-shell-bar-blur` / `data-shell-subbar-blur` while blurred. Covered by a new browser check (`shell.html?blur=…&subblur=…`: unset, `0`, `0px`/`0rem` and empty are `none`; `12px` and `6px` blur; each bar reads its own token; setting the token on the root later turns the blur on, and `0` off again). shell re-pinned: `sha256:d2a515a5…f2ea` → `sha256:4a3a03a3…9513`.

### Added

- **A dashboard widget can declare its loading height.** New, opt-in interface `Tey\Kit\Features\SessionUi\Dashboard\DeclaresLoadingHeight` (`loadingHeight(): ?string`); a widget that implements it sends `loadingHeight` in its frame (`WidgetFrame.loadingHeight?: string | null`, `types/kit.d.ts`). When set, `WidgetCard` gives the ready body that minimum height, so the space a deferred list or chart will fill is held and the cards below do not shift; an app no longer needs its own height table to avoid layout shift. A plain `px`, `rem` or `em` length only (`'18rem'`, `'.5rem'`, up to 32 characters); any other value turns the widget into an error card (fail closed, as an unknown region or kind does). Empty and error cards keep their natural height. Additive: the `Widget` base class gains no method, so existing host widgets are unaffected, and only a widget that implements the interface is asked for it; every other widget sends `loadingHeight: null`, even one with a `loadingHeight()` of its own. Covered by `test:dashboard` (declared, not opted in, a host's own `loadingHeight()` never read and sent as null, invalid, and no method on the base class) and a new browser check (`dashboard.html?tall`). The browser floor goes up by 2. workspace-ui re-pinned: `sha256:ce2456bc…2ca4` → `sha256:d1a8d048…202f`; in `history.json` only the `current` block's `Workspace/Layout.vue`, `Dashboard/Partials/WidgetCard.vue` and `Dashboard/Partials/types.ts` hashes changed.

## v0.15.70

### Fixed

- **Note thumbnails are cached, and made on the queue.** `NoteMediaController` sent every `?conversion=thumb` with `Cache-Control: private, no-store`, so each visit downloaded and decoded the thumbnails again. A thumb now goes out with `private, max-age=86400` and an ETag (a hash of the media id, its `updated_at` and the conversion name), and a matching `If-None-Match` (weak comparison, so `W/"…"` and `*` match too) is a 304 with no body. The ETag and the 304 come only after the reader is authorised and the note and file are found, so another workspace, a deleted note or an unknown file is still a 404 and carries no ETag. Originals keep `private, no-store`, with no ETag. The note `thumb` conversion (320×240) is now queued instead of `nonQueued`, and always dispatched after the note's transaction commits, whatever `media-library.queue_conversions_after_database_commit` says; hosts with an async queue need a worker running; on a sync queue it exists straight away, as before. Until it exists, a note's image attachment gets its original as `thumb_url` and anything else keeps the placeholder icon (`thumb_url` null), and the thumb URL is a 404. No resource changed, so nothing is re-pinned. Covered by `test:notes` (the thumb's and the original's headers, the 304, an outsider and another note sending the ETag, and the fallback with the conversion held on a null queue).
## v0.15.69

### Fixed

- **The post-sign-up passkey offer works on a default Fortify setup.** v0.15.66 (#829) taught Security's Add a passkey to confirm the password first, but the shared `PasskeyOffer` shown after an invited sign-up still went straight to `/user/passkeys/options`, which Fortify's `password.confirm` answers with a 423, so a newly invited person could not add the passkey they were offered. The offer now asks for the password and confirms it with Fortify before registering, as Security does; a wrong password is an error on that field and the browser is not asked for a passkey. With `fortify-options.passkeys.confirmPassword` false, no password is asked for. New, additive: `confirm_url` (null when the confirmation is off) on the shared `passkeyOffer`, from `PasskeyOffer::confirmUrl()`, which Security's `passkeys.confirm_url` now uses too.
- **`confirmPasskeyPassword()` fails closed on a client that resolves refusals.** It only read the status of a rejected request, so a client that resolves non-2xx answers (a host's own, through `@kit/ui`; Inertia v3's XHR client rejects 4xx and 5xx) carried a 422 on to the WebAuthn prompt. A resolved answer's status is now checked: a 2xx is confirmed, a 422 is the field's message, and anything else (or no status) throws, so registration never starts.
- Run `kit:update plan|apply` for `core` (re-pinned: Kit/Feedback/PasskeyOffer, Kit/Feedback/passkeyOffer). Covered by three new browser tests (the browser floor goes up by 3: the offer confirms then registers; a wrong password is inline with no WebAuthn call; with the confirmation off, no password), `test:onboarding` (the offer's `confirm_url`, and null with the confirmation off) and `test:onboarding-js` (resolved 422, other non-2xx and statusless answers).

## v0.15.68

### Fixed

- **A locked `InputSwitch` cancels Space as well as Enter.** Inside a locked `LockedForm`, its keydown guard let every key but Enter through, so Space reached Reka's `SwitchRoot`; Chromium's click guard happened to catch the click Space makes, but nothing stopped Space itself. The guard now cancels Space and Enter on keydown and keyup (`preventDefault`, no toggle), and clicks on the switch and its label as before; Tab and other keys pass. A new browser check presses a click, the label, Space and Enter one at a time, reading the value after each so two toggles cannot cancel out, and dispatches Space and Enter to confirm they are cancelled. The browser floor goes from 278 to 279. core re-pinned: `sha256:7c547459…123f` → `sha256:9fb53b6a…952f`; in `history.json` only the `current` block's `Kit/Form/InputSwitch.vue` hash changed.

## v0.15.67

### Fixed

- **A DataTable with row actions no longer adds blank scroll past the page on a phone.** The card (`[data-table-card]`) and its scroll box are now `position: relative`, so the row actions' absolutely positioned visually hidden text (a disabled action's reason, the menu label) is clipped inside them. Before, with eight such rows, the page scrolled about 700px past its footer. Core is re-pinned.

## v0.15.66

### Fixed

- **Security › Add a passkey works on a default Fortify setup.** Fortify puts its passkey registration behind `password.confirm` (`fortify-options.passkeys.confirmPassword`, on by default), and the Security page never asked for the password, so unless it had been confirmed in the last few hours the options request was a 423 and the page said "No passkey was added". Add a passkey now asks for the password inline, as Remove and two-factor do, confirms it with Fortify's own password confirmation, then registers; a wrong password is an error on that field, and the browser is not asked for a passkey. New, additive: `confirmPasskeyPassword()` in the kit barrel, and `confirm_url` (null when the confirmation is off) on Security's `passkeys` prop (`types/kit.d.ts`). Run `kit:update plan|apply` for `core` and `workspace-ui` (re-pinned: Kit/Feedback/passkeyOffer, the Kit barrel, Workspace/Account/Security). A host that turns that confirmation off (`fortify-options.passkeys.confirmPassword` false, as Fortify reads it) gets a null `confirm_url`, and the page adds the passkey without asking for the password. Covered by three new browser tests (the browser floor goes up by 3; the account-forms passkey test now enters the password and expects the confirmation first), `test:passkeys` (unconfirmed is still a 423; a wrong password is a 422 on `password`; confirmed, the options are served; with the confirmation off, no `confirm_url`) and `test:onboarding-js`.

## v0.15.65

### Changed

- **An expired or revoked customer link is now a 410, not a 404.** `kit.deliver.view` checks the signature apart from its expiry (`URL::hasCorrectSignature()`), so a correctly signed link with the right token that has expired (its signed `expires`, or the delivery's `token_expires_at`) or been revoked (`token_revoked_at`) answers 410 Gone with a plain page ("This link has expired. Ask the sender for a new one.", or "This link is no longer available…" when revoked) that names neither the subject nor the recipient. A bad or missing signature, a tampered expiry, an unknown delivery and a wrong token stay the same bare 404: each does the same lookup and constant-time hash comparison, and throws the same empty exception. Every answer, 404 and 410 included, is `Cache-Control: no-store, private`. A signed-in member who may read the subject (the `deliver.read` permission and the definition's `can()`, failing closed) is redirected to its `url()` instead. Apps that relied on a 404 for expired or revoked links now get a 410.

### Added

- **`DeliveryDesk::open()` takes optional `$signed`, `$signatureExpired` and `$user` arguments.** `$signed` defaults to false, so a call that doesn't vouch for the signature is refused, and existing callers still compile.
- **`Delivery::expiredPage(fn (Model $subject, DeliveryRecord $delivery, string $why): Response)`**: the definition's own page for an expired or revoked link (`$why` is `'expired'` or `'revoked'`), in place of the default 410.
- **`Delivery::previewAttachment(fn (Model $subject): ?DeliveryAttachment)`**: the document Preview shows (the panel's Preview, its document link and the MCP preview), in place of `attachment()`. Sends always use `attachment()`.

### Fixed

- **Loading the deliver panel no longer builds the document.** The panel's state called `attachment()` on every load to decide whether to offer the document link; it now offers the link whenever the definition has an `attachment()` or `previewAttachment()`, and the document is built only for Preview and Send. What is sent is unchanged.

Covered by new checks in `test:deliver` (every 404 branch with identical bodies; expiry by signature and by token, and revocation, with the default page and with `expiredPage()`; no-store on all of them; the member redirect allowed and refused; the preview document and page load, with call counters). Documented in [docs/deliver.md](docs/deliver.md).

## v0.15.64

### Tests

- **A pull request that changes what a kit form submits fails until it is released as a minor.** The new `bin/form-payload-check.php <base-ref>` compares the field names (not values) of every `tests/fixtures/form-payloads/` fixture at the base ref with the working tree, and reports added, removed and renamed fields per form. A removed or renamed field, or a removed fixture, fails it; an added field is reported and passes, since the server drops undeclared fields. `--allow`, or a line naming the form-payload break under `## Unreleased`, lets a minor release through. A new `Form payloads` job runs it on every pull request against the merge base with main, and `test:form-payload-check` covers it (identical, added, removed, renamed, deleted fixture, override). The rule is in `docs/releasing.md` and the new pull request template. No feature resource changed, so nothing to re-pin.

## v0.15.63

### Added

- **`InputSwitch`, an on/off switch for `@kit/ui`.** It takes InputCheckbox's API: `id` (required), `name`, `value`, `disabled`, `invalid`, `description` and `v-model` (a boolean), with the label in the default slot, caller attributes on the switch itself and `class` on the row; inside a `Field` the Field's description and error join its `aria-describedby` and the error sets `aria-invalid`, and inside a locked `LockedForm` it is read-only. It is a `role="switch"` button with `aria-checked`, built on Reka UI's `SwitchRoot` (the Laravel Vue starter kit ships no shadcn-vue Switch); clicking the label toggles it, and so do Space and Enter, with a visible focus ring. With `name` inside a form it submits as a checkbox does (`name=on`, or its `value`, while on; nothing while off or disabled); without `name` it submits nothing. Disabled keeps its label and description at full contrast: the track is muted and the cursor `not-allowed`, with no opacity. Exported from `@/components/Kit` and listed in the form-controls surface. Covered by seven new browser checks (`?switch`, light and dark: click, label and Space, `aria-checked`, the locked state, FormData named and unnamed, a required switch blocking the submit, disabled, the description and a Field's error, axe with contrast measured on the disabled text). The browser floor goes from 267 to 274. core re-pinned (see the PR body).

## v0.15.62

### Fixed

- **BulkDrop's disabled text meets AA contrast.** A disabled BulkDrop (no upload action) drew its whole surface at `opacity-60`, so the prompt ("Save first to add files here") and the size hint fell below 4.5:1 and axe reported a serious color-contrast violation. Its text is now at full contrast (the prompt and a disabled browse in `text-muted-foreground`, no opacity), and disabled shows instead as a muted background, `cursor-not-allowed` and `aria-disabled="true"` on the drop region, with the file input disabled as before. The other states and MediaCollection, FileList and the notes composer had no opacity on text. Covered by a new browser check (`?bulk-drop`, light and dark: idle, error, busy and disabled, axe with contrast measured on the disabled text). The browser floor goes from 266 to 267. core re-pinned (see the PR body).

## v0.15.61

### Fixed

- **A server-driven modal is named by its title again.** `ModalPanel` named its dialog by looking for a `role="dialog"` ancestor, but `@inertiaui/modal-vue` 3 renders a native `<dialog>` by default, so Change role, Invite member, Transfer ownership and every other server-driven modal had no accessible name. It now names the nearest `<dialog>` or `role="dialog"`. Covered by a new browser check on the real package. core re-pinned (see the PR body).
- **People's row controls are named by the person.** People gave its DataTable no `row-label`, so each control was named by the row's number ("Change role row 2", "Actions for row 2"). It now passes the member's name, or the invitation's address ("Change role Ana Lopez", "Actions for Ana Lopez", "Resend sam@example.com"). Covered by a browser check. workspace-ui re-pinned (see the PR body).

### Tests

- **The browser job sends People's forms and Delete workspace.** On a new build (`browser/people.html`, `vite.people.config.js`) with the real `@inertiajs/vue3` and the real `@inertiaui/modal-vue` 3.1.2 (a new pinned browser dev dependency), mounted as an app mounts it: Change role, Invite member and Transfer ownership open over People as server-driven modals; Suspend, Reinstate, Remove, Resend and Revoke are People's row actions (confirmed where Concierge asks); Delete workspace is its own page. Each test checks the posted fields, the success state (modal closed, toast, link shown or landing page), the values after a reload, and, for the forms with fields, one inline error (a refused role on Change role and Invite member, a wrong password on Transfer ownership, a mismatched name on Delete workspace), with refusals answered as inertiaui/modal's server does (back to the modal's URL as its base page with the modal in `_inertiaui_modal`). Each payload is a committed fixture under `tests/fixtures/form-payloads/`, and the new `test:people-form-payloads` posts every one to the real routes as an owner of a synthetic workspace (SQLite memory): each must redirect with no errors and store the change, and the two refused-role payloads must come back on `errors.role` (#823). The browser floor goes from 251 to 266.

## v0.15.60

### Fixed

- **Security › Passkeys: Rename sent the old name.** The name field was bound with `:value`, which the kit's `Input` re-applied on every keystroke, so it could not be edited and Rename posted the name it already had. It is now `:model-value`. Re-pinned `workspace-ui`.
- **Notes: Edit could not change a note's colour.** The editor's colour select was bound one way to the note's, so a pick never stuck and Save posted the old colour (the composer already kept its own). The editor now keeps its own colour, started from the note's on each Edit. Re-pinned `notes`.

### Tests

- **The browser job submits the account and session forms.** Account › Profile (name and email, the photo upload, the inline "Choose a photo." when none is chosen, Remove photo), Password, Security (two-factor on, confirmed and off, recovery codes regenerated, a passkey added, renamed and removed, other sessions signed out), Delete account, the welcome flow's timezone (saved as it changes, then Continue) and photo steps (upload, an inline refusal, Skip for now) and the notes wall (add, edit, delete) are each filled through the kit's own controls on the real page and submitted, and read back: the exact posted fields, the notice, toast or page that follows, the values after a reload, and one inline error per form where the page can be refused. Two browser APIs are stubbed and nothing else: `navigator.credentials.create` (no authenticator in CI) and `crypto.randomUUID` (stable intent keys). Each recorded payload is a fixture in `tests/fixtures/form-payloads/`, and the new `test:account-form-payloads` posts each one to the real route as the signed-in person (SQLite memory, real Fortify, laravel/passkeys, medialibrary on a temp disk, database sessions): each must redirect with no validation errors and store its values. The TOTP code is swapped for a real one computed from the stored secret; a passkey's credential is checked as far as laravel/passkeys' validation, since only a real authenticator's attestation can complete the ceremony. The browser floor goes from 224 to 251.

## v0.15.59


### Tests

- **The browser job presses Save on the settings forms.** General settings, Email sending (the RadioCardGroup "from" and "replies" choices included), Email sending's Check now and the MCP consent page's Approve are each filled through the kit's own controls, submitted, and read back: the toast or the page the browser lands on, the posted values, the same values after a reload (the test's stand-in server keeps what was posted), and no error page. General and Email sending also show a refused value's error inline on its field; the consent page keeps Approve disabled until a workspace is chosen (a refused workspace is a 403 by design). `browser/forms.html` on the real `@inertiajs/vue3`; Each Save test's exact payload is a committed fixture (`tests/fixtures/form-payloads/`; the browser job fails when a payload drifts from it, `KIT_FORM_PAYLOADS_WRITE=1` rewrites them), and the new `test:form-payloads` posts the General and Email sending fixtures to the real routes as an Inertia visit by an admin of a synthetic workspace (SQLite memory, a fake sending-domain provider): each must redirect to its page with no validation errors and store every value. `test:mcp-explicit-grants` posts the consent fixture to Passport's real approve, with only the server-issued values swapped in, and expects a code and a connection holding the ticked grant. The browser floor goes from 216 to 224. No feature resource changed, so nothing to re-pin.

## v0.15.58

### Security

- **Deleting your account no longer leaves the user row behind.** `/me/delete` signed the person out with `logout()` after the user row was deleted; `logout()` cycles the remember-me token and saves the user, and on a deleted model that save inserts the row again. On a host whose users table has `remember_token` (Laravel's default), the row came back with its email and password hash, while the memberships, roles and Concierge account were gone. It now signs out with `logoutCurrentDevice()`; the deletion has already cycled the token and ended the sessions. Deferred and anonymised deletions are unchanged. `test:lifecycle-pages` now gives its users table a `remember_token` column, as a host's users table does, and expects the row to stay deleted. A host that deleted accounts on an earlier version should check for user rows with no Concierge account or memberships left by a deletion. No feature resource changed, so nothing to re-pin.

## v0.15.57

### Fixed

- **A refused role or a missing photo is now an inline field error, not a full error page.** An audit of every other web endpoint that writes (see the PR's table) found that most already validate their own fields, so a stray browser field is dropped, and that two kinds of refusal still replaced the page on a page visit. People's Invite and Change role answered a role the person may not give, one that does not exist, or a scoped one (Concierge's `not_grantable`, the kit's `role_not_grantable`) with the 403 error page; they now come back on the role field with the refusal's own message. Profile's photo upload and the welcome flow's photo step answered a missing photo with the 422 error page; they now come back on the avatar field. `OperationForm::run()` maps a Concierge denial to a field only when the form names its code (`ManagePeople::ROLE_REFUSALS`), so every other denial (a member who may not manage, the owner, rank) keeps its 403, nothing is saved either way, and a JSON caller is unchanged: still a 403 for a refused role, and a 422 for a missing photo, whose body now also carries `errors.avatar`. New, additive: `ManagePeople::ROLE_REFUSALS`. Covered by the new `test:people-forms` (a stray field dropped and saved; each refused role inline on Invite and Change role; JSON still refused; other denials still 403; a missing photo inline on both upload forms). No feature resource changed, so nothing to re-pin.

## v0.15.56

### Fixed

- **Email sending says why Resend refused a domain.** `ResendSendingDomains` used to turn every failed Resend response into a bare `RuntimeException('mail_domain_unavailable')`, so the page said "Try again later" and nobody could tell why. It now throws `MailDomainUnavailable` (extends `RuntimeException`, same message, so existing catches and mappings still work) with `->reason`: Resend's error `name` and `message` only, never the key, headers or request. A connection failure (timeout, DNS, refused), which used to escape as Laravel's `ConnectionException`, now throws `MailDomainUnavailable` with the fixed reason `connection failed` and no host or transport details. The reason is logged at warning, and the domain field shows it when present, the generic text otherwise.

### Added

- **`FindsSendingDomains::find(string $id)`: read a sending domain with a GET only.** `check()` always POSTs verify first, so re-reading an existing domain failed whenever verify failed. `find()` is a new, separate interface (not added to `SendingDomains`, so apps' decorators of it keep working); `ResendSendingDomains` implements both. `SendingDomainLookup::read()` uses `find()` when the bound implementation supports it and falls back to `check()`. docs/mail.md. Covered by `test:mail-identity` on faked HTTP (reason carried, logged and shown with no secrets; no verify POST from `find()`; a `SendingDomains`-only decorator still works). No feature resource changed, so nothing to re-pin.

## v0.15.55

### Fixed

- **A stray form field no longer breaks a save with the 422 error page.** Workspace settings (General), Email sending, the AI assistants Approve and the notes wall passed the whole request to their operations, which refuse any argument they do not name (`unknown_arguments`, 422). A field the form did not mean to send, such as an unnamed RadioCardGroup's, then replaced the page with the full error page. On a page visit, each of these web endpoints now keeps only the fields its form sends (`OperationForm::input()`, presence only; the operation still owns the rules), so an extra browser field is dropped and the save goes through, and a bad value comes back inline on its field. A new shared mapper, `OperationForm::run()`, turns a field-level operation error on a page visit into a validation error on its field (`slug_invalid` and `slug_taken` on `slug`, plus any a form names); every other refusal (`permission_denied`, `workspace_not_found`, a 409 conflict) keeps its status, and a JSON request is unchanged. The operations keep their `unknown_arguments` strictness, so MCP and API callers are unchanged, and authentication, tenant and permission checks run in the same order as before. A JSON request still passes its whole input, so an API client sending an argument the operation does not name (or an `id` in a note's body) is still refused with `unknown_arguments`. New, additive: `Tey\Kit\Features\Core\Http\OperationForm`. Covered by `test:mail-identity`, `test:permissions-http`, `test:notes` and `test:mcp-explicit-grants` (a stray field dropped and saved, a bad value inline on its field, a JSON caller and the operation still refusing an unknown argument; the mapper's field, refusal and JSON cases). No feature resource changed, so nothing to re-pin.

## v0.15.54

### Fixed

- **A RadioCardGroup without `name` no longer submits a field.** Since v0.5.5, where RadioCardGroup was added, its radios took `name ?? id`, so a `v-model`-only group inside a plain or Inertia `<Form>` submitted an extra field named after its `id` (in `commit="click"`, its hidden input did the same). An endpoint that refuses unknown keys, such as the kit's own `WorkspaceMailIdentity::update` (422 `unknown_arguments`), then refused the save. Reported on v0.15.53, though not new in it. An unnamed group now contributes no form field: its native radios share a private generated name (`useId`), so the browser still groups them (one tab stop, the arrows move a single selection), and a `form` attribute naming no element detaches them from any enclosing form, so they are left out of its FormData and its native validation (`required` on an unnamed group sets `aria-required` only, in effect); the click mode renders no hidden input. With `name`, nothing changes. No other `@kit/ui` component falls back from `name` to `id`. Covered by the browser job (a FormData without the unnamed groups' keys in either mode, a named group's value, one tab stop and the arrows selecting). Run `kit:update plan|apply` for `core` (re-pinned `sha256:fc33f867…f9c4` → `sha256:df6bb342…3810`: Kit/Form/RadioCardGroup).

## v0.15.53

### Added

- **A dashboard widget can offer a Sort by control.** A widget declares its orders with `sortOptions()` (`key => label`) and a default with `defaultSort()`, and its query reads `chosenSort()`. The card shows a labelled, keyboard-operable Sort by select (the kit's `InputSelect`) at the right of its header. A choice is saved per person, per workspace and per widget key in `kit_user_state` through `PUT widgets/{key}/sort` (`WidgetSortController`, authorised by the new `SaveWidgetSort`: `dashboard.read`, then the widget's own ability). The page then partially reloads only the widget's props: its own lists, else `widgets`. An order the widget doesn't declare is refused on save and ignored when stored; the default applies instead (fail closed). New, additive: `Widget::sortBy()` (the `sortBy` frame prop, null without orders), `Widget::offersSort()`, `Widget::sortStateKey()`, `WidgetSortData`. docs/dashboard.md has an example. Covered by `test:dashboard` (options and fallback; per person, per workspace and per widget; the ability gate, an unknown widget and an outsider) and the browser job (the control at 1280 and 1600, light and dark: placement, label, keyboard, the partial PUT, a refused choice reverting, axe). Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:80acd1df…554e` → `sha256:e2c973a7…1362`: Dashboard WidgetCard, types).

## v0.15.52

### Fixed

- **v0.15.49 regression: an empty KIT_BRAND_* colour stopped every boot in local and testing.** Since 0.15.49 an env colour that loaded as `''` (KIT_BRAND_ACCENT and the other colour keys) threw on every artisan command and request in local, `kit:update plan` included, so an app could not even upgrade past it. An empty env colour means "use the theme" again, as before 0.15.49: no throw anywhere. Besides the per-key deprecation, it logs a hint once per key per process: the empty value may be an unquoted `#hex` read as a comment, and `kit:brand:migrate` writes it to config/brand.php as null with a comment. `kit:status` notes such a key as "empty in env (theme)", never as invalid (`kit.brand_env_empty`). Still strict in local and testing: `''` or a non-colour in config/brand.php, and a non-empty invalid env value such as `KIT_BRAND_ACCENT=red`. A config cache built by 0.15.49 or 0.15.50 keeps its accent at the kit default and listed as invalid until `php artisan config:cache` (or `config:clear`) is run again. docs/mail.md and docs/upgrading-brand-config.md say the same. Covered by `test:brand-config` and `test:status`. No feature resource changed, so nothing to re-pin.

## v0.15.51

### Fixed

- **`kit:brand:migrate` and `kit:status` start in local and testing with an invalid brand colour.** An empty colour (an unquoted `#hex` in .env, read as a comment) or any other invalid one no longer stops these two commands at boot: they warn and take the kit's default, as production does, so the command that fixes the value can run without `APP_ENV=production`. The command is read as artisan does, the first non-option argument (`php artisan --ansi kit:brand:migrate` counts; `php artisan list kit:brand:migrate` does not). `kit:status` prints a line per invalid key: an env value names `kit:brand:migrate`, a `config/brand.php` value says to fix it in that file. Every other boot stays strict, `kit:brand` included, since it would bake the default into the icons. `BrandConfig::lenientCommand()` and `BrandConfig::LENIENT_COMMANDS`. Covered by `test:brand-config`. No feature resource changed, so nothing to re-pin.

## v0.15.50

### Fixed

- **The You card's clock turns with the minute.** It ticked every 60 seconds from when the card mounted, so it could show the previous minute for up to a minute. Its first tick now lands on the next minute boundary, then one every 60 seconds; both timers are cleared on unmount. Covered by the browser job on a fake clock (the time is New York's, between a read just before the card and one just after, turns at the boundary and again a minute later). Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:b4a8faf9…bd14` → `sha256:80acd1df…554e`: Dashboard YouWidget).

## v0.15.49

### Added

- **The brand lives in the app's own `config/brand.php`.** Same keys and nesting as `kit.brand`; every reader keeps reading `config('kit.brand.*')`. Each key resolves at boot: `config/brand.php` when the key is present (an explicit `null` included), else its `KIT_BRAND_*` env value, else the kit's default, and `config:cache` keeps working (the env answer is read as config/kit.php loads, as `kit.brand_env`). `vendor:publish --tag=kit-brand` publishes a commented stub with the kit's defaults. Colour keys are validated at boot: a `#rgb` (widened) or `#rrggbb`, or `null` for "use the theme"; an empty string or anything else throws in local and testing, naming the key, and elsewhere logs a warning and takes the kit's default, never an empty value. `php artisan kit:brand:migrate` writes `config/brand.php` with only the keys that come from env, at their loaded values, writes a key that loaded empty (an unquoted `#` read as a comment) as `null` with a comment instead of guessing, refuses to overwrite without `--force`, and prints the .env lines to delete without editing .env. `kit:status` lists each brand key's source (`config`, `env (deprecated)` or `default`; `brand_sources` in `--json`). README, docs/mail.md, docs/upgrading-brand-config.md. Covered by `test:brand-config` and `test:status`. No feature resource changed, so nothing to re-pin.

### Deprecated

- **`KIT_BRAND_*` env values**, removed in 0.16. A key that still resolves from env logs one deprecation per key per process, naming the key and `kit:brand:migrate`. An empty `KIT_BRAND_ACCENT` no longer means "use the theme" without complaint: it is the `#`-truncation symptom and now fails the boot in local and testing; set `'accent' => null` in config/brand.php instead.

## v0.15.48

### Added

- **The mail button takes the app's colour from config.** `KIT_BRAND_MAIL_BUTTON` (`kit.brand.mail.button`) and `KIT_BRAND_MAIL_BUTTON_DARK` (`kit.brand.mail.button_dark`), each a `#rrggbb`, set the button's fill in light mode and under `prefers-color-scheme: dark` (and Outlook's dark mode), with no published Blade or CSS. The label is white or near-black, whichever holds 4.5:1 on each fill. A dark fill under 3:1 on the dark mail card, set or derived, is lightened until it holds 3:1, so a near-black button no longer vanishes in dark mode. An invalid or empty value falls back to the default. `Brand::mailButton()` also returns `background_dark` and `foreground_dark`; the layout takes a `brand` prop. docs/mail.md.

### Changed

- **The default mail button is the brand's own button.** An app that sets neither key gets the accent (the theme's `--primary` when `KIT_BRAND_ACCENT` is empty), or `ink` when its button role is ink (`KIT_BRAND_STYLE=mono` or `KIT_BRAND_BUTTON=ink`). Before, the fill was the accent only when its foreground was white enough to hold 4.5:1, else the strong accent, else ink, always with light text; a light accent now fills the button with near-black text, and `accent_foreground` no longer sets the label. The neutral default is unchanged in light mode (white on `#52525b`); in dark mode it is lifted to `#6c6c74` to hold 3:1 on the card. The browser/mail snapshots are refreshed. No feature resource changed, so nothing to re-pin.

## v0.15.47

### Changed

- **The dashboard's You card no longer reads "You".** It has no title bar: the viewer's avatar and name, their local time and timezone (`America/New York`, ticking each minute), and an Edit link to their profile, then the "Finish setting up" checklist only while one of their steps is still open. The card keeps "You" as its accessible name (`aria-label`), so it is still the "You" region. `YouWidget::person()` adds `timezone` (the viewer's zone, as `kitTimezone`); its header action is now labelled `Edit` (was `Edit profile`); `email`, `role` and `workspace` are still sent for an app's own card. `WidgetCard` takes an optional `headless` prop, which `WidgetGrid` sets for the `you` component. Covered by `test:dashboard` (the timezone, a forged `tz` cookie refused, the Edit action) and the browser job (the You card at 1280 and 375, light and dark: no visible "You", name, time in the viewer's zone and timezone, Edit, the checklist with open steps and none once all are done, axe, nothing overflowing). Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:f9207b81…5c6c` → `sha256:b4a8faf9…bd14`: Dashboard WidgetCard, WidgetGrid, YouWidget).

## v0.15.46

### Changed

- **A suspended member's refusal is worded on the 403 page.** When Concierge refuses with its `not_active` code (changing the role of a member who is not active), the kit's error page shows Concierge's own message ("Reinstate this member before changing their role.") with the usual "Return to your workspaces" link, in place of the generic copy. Only allowlisted Concierge codes are worded; every other 403 keeps the generic message and never shows an exception's text. Nothing changes who is admitted. No resource changed, so nothing to re-pin. Covered by `test:workspace-refusal` (the not_active 403 with its message and link, and no role change; another Concierge 403 and a permission 403 with the generic message and no exception detail).

## v0.15.45

### Added

- **`kit.brand.own`: output files the app made by hand, which `kit:brand` never overwrites.** List names in `public/` (`favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, `mail-mark.png`, `og-image.png`); kit:brand skips each one and prints `skipped public/<file>`, and `--check` skips them too. The default is an empty list, so nothing changes unless an app sets it. `BrandAssets::write()` takes the list as an optional second argument and reports `skipped`. README, brand.

### Changed

- **An empty `KIT_BRAND_ACCENT` makes `kit:brand` use the theme's accent.** The assets are drawn in the light `--primary` the host's stylesheets set (the app's own over a `kit.css` copy). With none it can read, kit:brand refuses, naming `KIT_BRAND_ACCENT`, and writes nothing; `BrandAssets` also refuses an empty colour outright. A set accent and the default are unchanged. New, additive: `BrandAssets::configuredAccent()`, `BrandAssets::owned()`, `ThemeAdvisory::primary()`, `HostRequirements::themeStylesheets()`. Covered by `test:brand-assets` (theme fallback, refusal, an owned file left untouched, defaults unchanged). No feature is re-pinned.

## v0.15.44

### Fixed

- **An app that still builds `WelcomeCardData` no longer gets a 500 on its dashboard.** 0.15.41 removed `WelcomeCardData`, the dashboard view model's `welcome` prop and the view model's old argument order, so an app constructing them failed for new members. They are back. `WorkspaceDashboardViewModel` takes both argument orders: up to 0.15.40's (…, `$greeting`, `?WelcomeCardData $welcome`, `$widgets`, `$lists`) and 0.15.41's (…, `$greeting`, `$widgets`, `$lists`, `$aside`); named arguments work with either. An app that passes `welcome` gets the old card above the widgets again, as before 0.15.41. The kit's own dashboard sends `welcome: null`; its steps stay in You and the setup card. Covered by `test:dashboard` (an app route passing `WelcomeCardData` in the old order answers 200 with its card, widgets and lists; the 0.15.41 order and named arguments still work) and the browser job (the dashboard Page with an app's welcome card: its checklist links, progress and Dismiss, above the widgets, axe, light and dark). Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:dce75ce1…b33f` → `sha256:f9207b81…5c6c`: Dashboard Page).

### Deprecated

- **`WelcomeCardData` and the dashboard's `welcome` prop**, to be removed in 0.16. Move an app's onboarding steps to `Onboarding::step()` (`scope: 'person'` shows in You, `scope: 'workspace'` in the setup card) and stop passing `welcome`. docs/dashboard.md, Onboarding steps.

## v0.15.43

### Fixed

- **The bar's search floor no longer outlives the lg breakpoint.** Since v0.15.39 the bar slot and global search got an inline 160px minimum at `lg` and up, which was removed only by the ResizeObserver a rendering step later. So in the frame right after a resize from desktop to phone width, the slot kept its minimum and the drawer's Menu button ran off the right edge (the page was about 30px too wide). The floor is now a custom property (`--shell-bar-gives-floor`) applied as `min-width` only under the `lg` media query, so crossing below `lg` drops it in the same frame. PrimaryNav also no longer writes the room's flex-basis or the floor inside its own ResizeObserver callback, which logged "ResizeObserver loop completed with undelivered notifications" when the pages' width changed (a font load, a text-size change): it measures and writes once in the next frame, as FloatingToolbar does. Covered in the browser job (resizes and text-size changes with no ResizeObserver loop error, then 1440 to 390 with the page width read right after the resize; ten runs). Run `kit:update plan|apply` for `shell` (re-pinned `sha256:f467b5ec…1470` → `sha256:d2a515a5…f2ea`: Layout, PrimaryNav).
## v0.15.42

### Fixed

- **A Modal's opening focus window no longer pulls focus back from scripts, tests or the page.** While a Modal opened, the panel moved any focus inside it that came without a key or pointer press back to the first field, for as long as 10 seconds when the modal package's `data-inertiaui-modal-entered` marker wasn't found, so a script's `focus()` (or a browser test's) followed by typing went to the wrong field. The panel now moves focus back only from the trap's own pick (the modal's first focusable, normally Close); any key or pointer press anywhere ends the window and removes its document listeners at once; the window ends after a second with no marker (five with one); and closing or unmounting the panel ends it. A viewer who moved focus before the trap fired still gets it back. Covered by the browser job (no listeners left after closing mid-opening, a script's focus and the page's own input typing right away with no marker, the window closing within a second with no marker). Run `kit:update plan|apply` for `core` (re-pinned `sha256:31864437…b704` → `sha256:fc33f867…f9c4`: Kit/Overlay/ModalPanel).

## v0.15.41

### Changed

- **The dashboard's welcome card is gone; its steps sit where they belong.** The standalone `max-w-xl` card above the widgets (with its greeting and Dismiss) is removed, and the page heading always greets by name. Each onboarding step now has a `scope` (`Onboarding::step(..., scope: 'person'|'workspace')`, default `person`; anything else is refused). Personal steps (your timezone, your photo) are a "Finish setting up" Checklist in the **You** card (its new `checklist` prop). Workspace steps (the workspace timezone) are a new **Set up this workspace** card, `kit.setup` (`SetupWidget`, kind `setup`, region `aside`, always the aside's last card), shown only to people with the existing `settings.write` ability (owner and admin; `members.manage` without the settings feature). Each is hidden once every one of its steps is done, and neither shows with the `welcome` route group off. When it is the only widget the setup card takes the full width. `Widget::visible()` (default true) lets a widget leave itself out after its ability passed. docs/dashboard.md, Onboarding steps. Covered by `test:dashboard` and the browser job (1280 and 375, light and dark, axe).

### Fixed

- **A dashboard checklist link to a workspace step opens it for that workspace.** The dashboard hands its workspace to the welcome flow, which before learned it only from an invitation, so the workspace timezone row answered 404 from the dashboard. A skipped workspace step is now skipped for that workspace only (`onboarding.skipped.{id}` under the workspace); a personal step's skip stays account-wide. Covered by `test:dashboard`.

### Added

- **PageGrid preset `main-aside-wide`**: the first cell 7 columns, the rest 5 (about 58/42), beside `main-aside` (8 and 4). **`kit.dashboard.aside`** (`narrow`, the default, or `wide`; env `KIT_DASHBOARD_ASIDE`) makes the dashboard's WidgetGrid use it; it reaches the page as the `aside` prop, and any other value is `narrow`. docs/page-rhythm.md, docs/dashboard.md. Covered by `test:dashboard` and the browser job (the wide preset measured at about 58/42 at 1280, stacked at 375).

### Upgrade

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:7ed4c081…4918` → `sha256:31864437…b704`: Layout/PageGrid, contract.ts) and `workspace-ui` (`sha256:666f8a85…5f58` → `sha256:dce75ce1…b33f`: Dashboard Page, WidgetGrid, YouWidget, and the new Partials/SetupWidget.vue). Copy the new `types/kit.d.ts` if you keep a copy.
- **`WorkspaceDashboardViewModel` drops `welcome` and adds `aside`; `WelcomeCardData` is removed.** An app's own dashboard component that read `welcome` should render `<WidgetGrid :widgets :aside>`, which now carries the steps. The `DELETE /me/welcome/{workspace}` dismiss route still answers but nothing on the kit's dashboard calls it.
- **A host that points `@kit/ui` at a module of its own must also export `Checklist`** (the You and setup cards import it); `export * from '@/components/Kit'` already does.
- **An app that lists `kit.dashboard.widgets` itself** gets no setup card until it adds `'setup'` to the list. Register workspace-level onboarding steps with `scope: 'workspace'`; a step registered without one stays personal and shows in You.
## v0.15.40

### Fixed

- **A Modal already marked entered when its panel mounts no longer holds focus for 10 seconds.** With a zero-length or reduced-motion enter, the package sets `data-inertiaui-modal-entered` before the panel starts watching the opening, so the panel kept its focus listeners until their 10-second cap and could pull later programmatic focus back to the first field. The panel now checks the marker as soon as it starts watching. Covered by the browser job (a modal entered at mount whose trap takes no focus keeps a later programmatic focus on Close). Run `kit:update plan|apply` for `core` (re-pinned `sha256:8d092a03…d156` → `sha256:7ed4c081…4918`: Kit/Overlay/ModalPanel).

## v0.15.39

### Fixed

- **Search shrinks first.** In the signed-in bar, an app's own search (the `bar` slot) or the kit's global search kept its full width while pages went into "More", so a bar a few pixels short at the `lg` edge (1024px) showed "More" for pages that fit once the field gave a little. Now the pages' room asks for every page's width and the search gives way first: it narrows inside its slot (its content is capped at the slot's width, so it never spills into the actions' gap) down to its minimum, and only then do pages move into More. The minimum is the control's own width or `--shell-bar-control-min` (10rem by default, room for a search field's placeholder), whichever is smaller, so a compact control such as a search opener never grows. Nothing changes when the pages already fit. Covered in the browser job (an app-style bar with four pages and its own 13rem field at 1024: fresh load, and resized from 1000 and 1280; and More once even the shrunk field can't make room). Run `kit:update plan|apply` for `shell` (re-pinned `sha256:189a3b40…bcdb` → `sha256:f467b5ec…1470`: Layout, PrimaryNav).
## v0.15.38

### Fixed

- **A Modal's first field gets focus however slowly the modal opens.** The panel focused its first field on fixed timers (60ms and 400ms), but the modal package's focus trap takes focus to the Close button a frame after the enter animation ends; on a slow machine or a busy CI runner that came after 400ms and focus stayed on Close. The panel now focuses its first field at once and, until the package marks the modal entered and the trap has had its frame, moves focus back once if it lands elsewhere without the viewer's doing. A viewer who moved focus with a key or the pointer before the trap fired keeps it; a modal with no field leaves focus where the trap put it. Covered by the browser job (a 2s enter under 6x CPU throttling, a 300ms enter, no field, and a Tab before the trap). Run `kit:update plan|apply` for `core` (re-pinned `sha256:1f4b20b5…12ad` → `sha256:8d092a03…d156`: Kit/Overlay/ModalPanel).

## v0.15.37

### Added

- **`kit.brand.icon` (`KIT_BRAND_ICON`): the source for the favicons, touch icon and mail mark.** A logomark cropped tight or wide for the lockup no longer has to double as the icon. `kit:brand` draws `favicon.svg`, `favicon.ico`, `apple-touch-icon.png` and `mail-mark.png` from the icon (falling back to `logomark` when unset), and `kit:brand --check` compares against it. `og-image.png` keeps `logomark`: it is a wide canvas with the mark beside the name, so a wide mark suits it. Whichever source is used, a mark whose viewBox isn't square is padded to a centred square before rasterising, so no asset is stretched; a square mark renders byte for byte as before. Covered by `test:brand-assets` (icon set, unset, a 4:1 mark kept at 4:1 and centred, and `--check` against the icon).

## v0.15.36

### Fixed

- **The lockup's mark is the wordmark's height.** With `kit.brand.lockup` on, the mark's box was sized apart from the wordmark, so a mark drawn on a square canvas with empty padding showed as a small square beside a large wordmark. The mark and the wordmark now share one height, `--shell-lockup-height` (falling back to `--shell-mark-height`, then 2rem), and a mark with a tight viewBox fills it. `kit.brand.lockup` (`KIT_BRAND_LOCKUP`) also takes a path to a full lockup image, which is shown as it is, with the product's name as its alt text, in place of the mark and the wordmark; the shell's `product` carries it as `lockup_image`. Covered by the browser job (a padded mark, a cropped mark and a lockup image, light and dark). Run `kit:update plan|apply` for `shell` (re-pinned `sha256:5ca9a2a3…370a` → `sha256:189a3b40…bcdb`: Workspace/Partials/Wordmark, GuestShell, PublicShell, focusShell).

## v0.15.35

### Changed

- **The workspaces feature accepts `tey/concierge` 0.11.1 and later** (`^0.8.4 || ^0.9 || ^0.11.1`, was `^0.8.4 || ^0.9`). 0.10.x and 0.11.0 stay excluded: they lack `MembersIndex::scopeMembersUsing()`, which `kit.people.hide_grants` needs. The boot refusal for hidden grants on such a Concierge now reads "kit.people.hide_grants needs tey/concierge 0.9.3+ or 0.11.1+". A role change on a suspended member, which Concierge 0.11 refuses with a 403 (`not_active`), is refused the same way through People; People offers no role change on a suspended row there.
- **The kit's Concierge defaults mark Concierge as set up** (`'setup' => true` in `config/defaults/concierge.php`), so an app without its own `config/concierge.php` keeps working on Concierge 0.11.1, which registers nothing until it is set up. As before, these defaults apply only when the app has no `config/concierge.php`; an app with the file counts as set up already. Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:56912cc3…eafd` → `sha256:bbf33a40…99f4`; the manifest only, no stub changes).

- **The kit configures Concierge whenever it is resolved**, not once in its own boot (`Tey\Kit\Features\Core\Resolved::configure()`): workspace provisioning, the invitation link and the tenant invitation link stay set whichever of the kit and Concierge boots first, since Concierge 0.11.1 binds itself at boot. Covered by `test:resolved` (resolved before, bound after, and re-bound).

### Upgrading

- **On Concierge 0.11, use 0.11.1 or later** (`composer update tey/concierge`). 0.10.x and 0.11.0 are not supported.

## v0.15.34

### Fixed

- **PageTabs no longer shows a vertical scrollbar.** On a wide window the tab row (from `sm` up) drew a scrollbar at its right end even when every tab fit: the links overlapped the row's bottom border with `-mb-px`, which overflowed it by a pixel, and `overflow-x: auto` makes the other axis scroll too. The baseline is now an inset shadow the underline paints over, the row is `overflow-y: hidden`, and the focus ring is drawn inset, so neither the underline nor the ring is clipped. The row scrolls sideways only when the tabs don't fit, with no visible scrollbar (touch, trackpad and keyboard still scroll it), and a tab focused by keyboard is scrolled wholly into view. Covered by the browser job (seven tabs at 1920, 1280 and 640, light and dark, axe). Run `kit:update plan|apply` for `core` (re-pinned `sha256:d37fa8b7…cfda` → `sha256:1f4b20b5…12ad`: Layout/PageTabs).

## v0.15.33

### Upgrading

- **`/me/workspaces` no longer redirects a person with no workspace to `/no-workspace`.** It renders its own EmptyState (with New workspace when offered, and their open invitations). `/no-workspace` is still where Concierge sends someone with no workspace; a host or test that expected the redirect from the list should expect a 200 with an empty `workspaces` prop.
- **Create a workspace / New workspace needs your route.** With `self_serve`, the action now shows only while a GET route is registered at `/me/workspaces/create`; register your creation page there to offer it.
- **A host's own `@kit/ui` module must also export `EmptyState`, `PageSection` and `Surface`**, which the workspace list now imports (README, the `@kit/ui` list).

### Changed

- **The workspace list (`/me/workspaces`) is a page of cards.** Each workspace you belong to sits on its own card with its initials mark, your role there, the active member count and a Current marker on the one you are in or used last; the whole card is the workspace's link, keyboard-focusable with a ring on the card. New workspace is a header action, sent only while `kit.workspaces.provisioning` is `self_serve` and the host registers a GET route at `/me/workspaces/create` (the kit ships no creation flow). The no-workspace page's Create a workspace follows the same rule, so it no longer links to a 404. Open invitations to your verified email get their own section above the list; they are still accepted from the link in the email, as the kit has no accept or decline by id. Role, member count and ownership are read in one query each for the whole list, only for your active memberships (the Leave action no longer reads one membership per workspace). `IndexWorkspaceViewModel` gains `invitations` and `create`, and each workspace `initials`, `role`, `members` and `current`. Covered by `composer test:workspace-chooser` and the browser job (1280 and 375, light and dark, axe). Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:c0952b1e…5bb9` → `sha256:666f8a85…5f58`: Workspace/Index/Page).

## v0.15.32

### Changed

- **The SubNav strip is a supported option, not deprecated.** `<Layout sub-nav="strip">` and `ShellData::$subNavigation`, marked deprecated in v0.15.28, stay supported. Nothing else changes: breadcrumbs default to the bar, a module's pages show as PageTabs from `layout().navigation`, and the strip is opt-in. Run `kit:update plan|apply` for `core` (Page) and `shell` (Layout); the stubs change only in comments.

## v0.15.31

### Fixed

- **A PageTabs that goes away gives the tab row back.** A `<PageTabs>` inside a `<Page>` claimed the page's tabs for good, so turning it off with `v-if` (or otherwise unmounting it) left the page with no tabs at all. The claim now lasts while the PageTabs is mounted, and `<Page>` draws `layout.navigation` again once it unmounts. A new Inertia page mounts a fresh `<Page>`, so no claim carries across visits. Covered in the browser job (record frame with its PageTabs turned off and on). Run `kit:update plan|apply` for `core` (re-pinned `sha256:2faa51a6…d869` → `sha256:850e46e0…dbe8`: Page, PageTabs).

## v0.15.30

### Upgrading

- **Pages must be `PageViewModel` subclasses, or the breadcrumb bar and module tabs don't appear.** Both read the page's `layout()`, which only `PageViewModel` provides; a page rendered from a plain `ViewModel` silently got neither (and, since v0.15.28, no SubNav strip either). Extend `Tey\Kit\Features\Core\ViewModels\PageViewModel` and keep its `title()`.

### Added

- **A plain-ViewModel page warns in development.** In local and testing, `Inertia::render()` with a ViewModel that isn't a `PageViewModel` logs a warning naming the class and saying to extend `PageViewModel`. Child, widget and component ViewModels, a modal's or JSON response's `toArray()`, and a page that sets `protected bool $standalone = true` (the kit's public markdown page) stay silent; production stays silent and nothing throws. Covered by `test:page-view-model-warning`. No feature is re-pinned: `ViewModel` ships in the package.

## v0.15.29

### Added

- **The brand can set a control's edge.** `kit.brand.input` (`KIT_BRAND_INPUT`) and `input_dark` (`KIT_BRAND_INPUT_DARK`) print `--input` next to `--border`, light and dark, so an app on a tinted ground no longer prints it in its own CSS to meet the 3:1 minimum. `Brand::contrastProblems()` reads a configured edge against the brand's card (else its page) at 3:1. Unset, the brand CSS is byte for byte what it was. README, Tinted page grounds. No feature is re-pinned: `Brand` and its config ship in the package.
## v0.15.28

### Upgrading

- **The navigation defaults changed.** Breadcrumbs now default to the full-width bar under the header (`breadcrumbs="bar"`), and a module's sibling pages (`Registry::modulePage()`) show as PageTabs at the top of the page instead of the SubNav strip. Apps register nothing new. To keep the old look, pass `breadcrumbs="page"` and `sub-nav="strip"` to the Workspace Layout from your layout wrapper. A record frame that already renders its own `<PageTabs>` inside its `<Page>` keeps one row: its PageTabs claims the page's tabs. Run `kit:update plan|apply` for `core` (re-pinned `sha256:49ebcb75…a8e4` → `sha256:2faa51a6…d869`: Page, PageTabs, SettingsFrame) and `shell` (`sha256:3cddc48c…ccea` → `sha256:9fa2de58…b02b`: Layout).

### Changed

- **Module tabs come from the page's typed layout.** `SessionUiPageLayouts` fills `PageLayout::$navigation` on a page outside a settings section with the active module's pages, one `NavGroup` named for the module, ability-filtered on the server (a page the viewer may not open is not in the props at all), while one of them is current. A top-level `<Page>` in the shell draws `layout.navigation` as PageTabs above its heading; it draws none when nested, inside SettingsFrame, when the page renders its own `<PageTabs>`, or under `sub-nav="strip"`. No component reads the Registry or the shell for them. A ViewModel that sets its own navigation (a record's sections) replaces them.
- **Breadcrumbs default to the bar.** The whole trail, current page last, so a module's top page shows a trail (home › module). A page with no trail gets no empty bar. `breadcrumbs="page"` stays as an opt-in.
- **PageTabs' row scrolls sideways** when its tabs are wider than the column (at `sm` and up; a phone keeps the picker).

- **Module URL nesting.** A module owns its prefix (its primary link's suffix) and its pages sit under it. In local and testing, `Registry::modulePage()` logs a warning for a page outside it, suggesting the nested suffix (`calendar` → `bookings/calendar`); production stays silent and nothing throws. Covered by `test:module-tabs`.

### Deprecated

- `ShellData::$subNavigation` and the SubNav strip: only the Layout's `sub-nav="strip"` reads them.

Covered by `test:module-tabs` (new: the tabs are `layout.navigation`, ability-filtered, the forbidden page absent from the props), `test:shell` and the browser checks on `shell.html?module=` at 1280 and 375 (trail bar on a module's top page and a sub-page, the right tab current, no strip by default, no overflow, axe) and `&record` / `&strip`.

## v0.15.27

### Added

- **Tinted page grounds.** A theme whose page ground is tinted rather than white declares it with one token in its own CSS, `--kit-ground: tinted` (on `:root`). On it the kit's content components draw their own card (`bg-card text-card-foreground border rounded-xl`, as a dashboard widget): DataTable (one card around its tabs, toolbar, filters, rows and pages), EmptyState, Workbench, and a PageSection's content, below its header (new `surface` prop: `card` by default, `none` opts out). FormCard and the form Section were cards already. A card is never drawn inside another: Surface, Card, FormCard, Section, a dashboard widget, Dialog and Modal provide a card context (`provideCard`), and a component inside one draws none; a DataTable's rows are flat inside a card on a tinted ground. Page, PageGrid and the page chrome (header, breadcrumbs, SubNav, tabs, section headings) stay on the ground. With no declaration (the default, a white ground) nothing renders differently. `test:token-contrast` now checks the grounds' minimums for every shipped theme and a sample tinted theme (ground `#f3f5e7`), light and dark; the browser check `?tinted` renders them at 1280px with axe. README, Tinted page grounds. Re-pins `core` and `workspace-ui`.

### Upgrade

- Nothing changes until a theme declares `--kit-ground: tinted`. Run `kit:update plan|apply` for `core` (re-pinned `sha256:ce66e2ff…315a` → `sha256:49ebcb75…a8e4`) and `workspace-ui` (`sha256:29abaeef…a370` → `sha256:c0952b1e…5bb9`). To opt in, add `--kit-ground: tinted` to your theme's `:root`, set the ground (`--background`, or `--shell-ground` for the shell alone) and keep `--card` lighter, then check your `--border`, `--input`, `--foreground` and `--muted-foreground` against the minimums in the README. A page whose own markup draws a card around a kit DataTable, EmptyState or PageSection content should use a kit Card (or `PageSection surface="none"`) so the kit doesn't draw a second one.
- A host that points `@kit/ui` at a module of its own must also export `provideCard` (WidgetCard imports it); `export * from '@/components/Kit'` already does.

### Fixed

- **The notes-composer keyboard browser check no longer flakes.** It waited for Playwright's `filechooser` event, which Playwright starts catching only once the first listener is set up, asynchronously; under CPU load the dropzone's click could land first, and the chooser went uncaught though the kit had opened it (reproduced at 6x CPU throttling). The check now asserts that Enter on the focused browse button calls the hidden file input's `click()` once, as a user gesture, and adds the files with `setInputFiles`. 30 of 30 runs pass at 6x throttling.

## v0.15.26

### Fixed

- **The branded mail works in dark mode.** In a dark-mode client the lockup's dark ink sat on a dark header, a long button label broke mid-word (the theme's `.inner-body a { word-break: break-all }`, meant for URLs, applied to the button), and a light accent with dark text lost contrast once the client recoloured it.
  - **The lockup goes on its own light plate**, baked into the PNG the message embeds (`Brand::plateMailLockup()`, GD): a white rounded rectangle with a margin of a quarter of the lockup's height, made once per lockup next to Laravel's cache. The Gmail apps ignore `prefers-color-scheme` and Outlook's `[data-ogsc]` and recolour backgrounds and text, never an image's pixels, so a plate inside the image is the one thing that reads in every client. The header image is a little shorter for it (a 1152x208 lockup shows at 220x55 with its plate). Without GD, or for a PNG GD can't read, the lockup goes as before.
  - **The button is light text on a fill that holds 4.5:1**, the same in light and dark (`Brand::mailButton()`): the accent with its foreground when that is the lighter colour, else the strong accent, else ink, with white. A brand whose accent carries dark text (a light orange, say) now gets its strong accent in mail. Its label wraps only between words (`word-break: keep-all`), and the button stays inside a 320px screen. Keep labels to a short verb phrase ("Sign in again") and name the account or provider in the body copy.
  - **The layout declares `light dark`**, the kit's own copy of Laravel's base layout: `prefers-color-scheme: dark` rules for Apple Mail and iOS Mail and the same colours for Outlook's `[data-ogsc]`/`[data-ogsb]`. The footer text is `#71717a`, which holds 4.5:1 on the light page where the theme's `#a1a1aa` did not.
  - **`test:mail-render`** renders the reset mail, a sign-in-again mail and a long label, checks the plate and the button colours, and compares the render with snapshots in `browser/mail/` (`--write` refreshes them). Three browser checks open the snapshots in Chromium: axe contrast in light and dark, no label broken inside a word from 320 to 640px, and the plated lockup's ink on the dark header. The browser floor rises by three. Not covered: real Gmail, Outlook or Apple Mail clients, whose dark modes are approximated by Chromium's `prefers-color-scheme` only.
  - No feature is re-pinned: the mail views and `Brand` ship in the package.

## v0.15.25

### Fixed

- **The primary nav no longer moves pages into More when they fit.** Deciding whether every page fits, it counted a gap after the last page as well as between them, and it read the room's width rounded to a whole pixel, so a bar whose pages fit by under 2px (four pages beside search at 1024) showed More. It now counts only the gaps between the pages and reads the room's fractional width; More still appears as soon as they really overflow. Re-pins `shell`. Covered by a browser check at 1024 with four pages and an app's own search in the `bar` slot, the room set 1px wider than the pages and then 2px narrower.
- **The primary nav keeps measuring when its pages arrive after it mounts.** Its resize observer was attached once, at mount, to elements that exist only while there are pages, so a bar that mounted with no navigation and got it later never re-split on a resize. The observer now follows those elements, attaching when they appear. Covered by a browser check that hands the bar its pages after mount, then narrows the window from 1920 to 1024 and widens it again.

## v0.15.24

### Fixed

- **ButtonGroup draws its divider between segments again.** The segments' `[&>*]:border-0` reset beat Tailwind v4's `divide-x`, whose width sits under `:where()`, so two grouped page actions ("+ New booking", "Repeat a booking") read as one pill. Each segment but the last now draws a 1px right border in the outline colour, after the reset; `appearance="text"` keeps no dividers and the segment heights are unchanged. Browser checks `ButtonGroup (light)` and `ButtonGroup (dark)` (`?button-group`). Re-pins core.

## v0.15.23

### Added

- **Page purpose first: `@purpose <who>: <main job>` on every kit page ViewModel.** Each page's class docblock now states who opens it and their main job, in one line (`@purpose Returning person: sign in.`), so the kit's pages are the example. The README documents the tag beside the ViewModel standard, and docs/dashboard.md starts from the dashboard's own purpose. Docblocks only: no props, types, stubs or runtime change.

## v0.15.22

### Added

- **`kitImports()`, a dev-server plugin that loads `@kit/ui` names one file at a time.** The barrel names every kit component, and Vite can't pre-bundle an app's own files, so any page that imported from `@kit/ui` made the browser fetch all of them. On the starter's dashboard that was 216 of 320 requests. `core` now installs `resources/js/components/Kit/kitImports.js` (with `kitImports.d.ts`). In dev it rewrites `import { Button, Page } from '@kit/ui'` into imports of the files the barrel names. It reads whichever module `@kit/ui` resolves to, follows `export *`, and leaves anything it can't place on the barrel. App code doesn't change. It applies to `vite serve` only, and the starter's `vite build` output is byte-identical with it. The starter dashboard went from 320 requests and 22.07 MB to 149 and 15.56 MB. A new check fails when a kit component imports the barrel, and a browser check (`kitImports`) serves the dashboard grid in dev over the real barrel without requesting `index.ts`. docs/kit-imports.md, README. Re-pins `core`.
  **Migration:** run `kit:update` for `core`, then add the plugin after `vue()` in `vite.config.ts`: `import { kitImports } from './resources/js/components/Kit/kitImports.js'` and `plugins: [..., vue(...), kitImports()]`. The exact steps are in docs/kit-imports.md, "Migrating an app".

## v0.15.21

### Changed

- **The dashboard is two columns, main and aside, instead of a 12-column grid of spans.** `Widget::region()` (`main` or `aside`, default `main`) places each card; `WidgetGrid` renders a PageGrid `main-aside` (8 and 4 of 12 from `md`, one column below it, main first) with a Stack of cards in each column, in order, every card its column's full width. Recent activity and Notes are in main; You and People are in aside; their orders are unchanged. A region other than `main` or `aside` makes the widget an error card. Re-pins `workspace-ui`. Covered by `test:dashboard` and the browser checks at 1440, 768 and 767.

### Added

- **`Widget::kind()`: `task`, `feed`, `status` (the default) or `setup`.** Carried in the frame and `WidgetFrame`. Recent activity is the feed; Notes is a task; You and People are status. Kind never sets the region: by convention task and feed go in main, status and setup in aside, setup last, and a dashboard has one feed. Any other kind makes the widget an error card. Covered by `test:dashboard`.

### Deprecated

- **`Widget::span()` and `WidgetSpanData`.** They no longer drive the layout and are still accepted and sent, so existing widgets compile. Migrating: drop `span()` and set `region()` instead: a wide widget (`lg: 8` or more) usually returns `main`, a narrow one (`lg: 4`) `aside`. docs/dashboard.md.

## v0.15.20

### Added

- **`kit:remove <feature>`.** Lists the feature's recorded files: unchanged from the kit's copy (would delete), changed by the app (kept) or already gone. With `--force` it drops the lineage record and its finished journals and deletes only the unchanged files. It refuses a feature `kit.features` still names, one another installed lineage requires, or any unfinished update. docs/catalog.md, "Removing a feature". Covered by `test:remove-feature`.

### Fixed

- **`kit:update` no longer re-creates a removed feature.** A lineage whose feature an app took out of its `kit.features` list kept its record, and `kit:update` re-created the feature's pages, widget and migration from it. Now `kit:update plan --lineage=<feature>` refuses it and the update order skips it with a warning, both naming `kit:remove`. Covered by `test:remove-feature`.
- **The notes-composer keyboard browser check no longer flakes.** It moved focus to the dropzone while the colour select could still hand focus back to its own trigger, so Enter sometimes missed the dropzone and the file chooser never opened. It now waits for the listbox to close and for the dropzone to hold focus before pressing Enter.

## v0.15.19

### Fixed

- **The signed-in bar's pages give way to a More menu.** With many top-level pages (ten, say) the nav never collapsed above `lg`, so global search pushed the appearance toggle and the account menu out of the bar at 1440px. PrimaryNav now measures the room it has and moves the pages that don't fit into a "More" menu (the kit's dropdown menu: keyboard and ARIA as in the account menu), in their order; the current page always stays in the bar. Below `lg` the drawer is unchanged. A browser check (`shell.html`) renders the Workspace Layout with ten pages, search, the appearance toggle and the account menu at 1440 and 1024: nothing overflows, the avatar opens its menu, every page is in the bar or under More, and axe passes. Re-pins shell.

## v0.15.18

### Added

- **`kit.brand.logomark_bar` (`KIT_BRAND_LOGOMARK_BAR`), the signed-in bar's own mark.** Set, the bar (the Wordmark's `bar` tone) shows that image unfiltered instead of painting the logomark with the brightness/invert filters; unset, the bar is unchanged. Carried as `product.logomark_bar` in the shell, guest and focus shell data. README, Brand. Re-pins `shell`. Covered by `test:shell`, `test:home` and the browser check `?logomark-bar`.

## v0.15.17

### Fixed

- **kit:status finds an app's own types file when the Vite config builds its path.** The own-types check (v0.15.16) now also reads `const globalTypeFiles = [...]` handed to plugin-vue as the shorthand, and takes the file name from a string literal inside an entry's expression, as in `[decodeURIComponent(new URL('./resources/js/types.generated.d.ts', import.meta.url).pathname)]`. The `vite_global_types` step for the kit's own `kit.d.ts` reads that shorthand const too, so a config that lists the kit's types there is no longer reported as having no `globalTypeFiles`. Covered by `test:status`.

## v0.15.16

### Fixed

- **kit:status no longer reports the shell's types steps as unmet when an app types the kit itself.** An app whose own generated `.d.ts`, listed in plugin-vue's `script.globalTypeFiles`, declares the `Tey.Kit` namespaces now meets both the tsconfig types step and the `vite_global_types` step; a listed file without them, or one that is missing, meets neither. README, kit's types. Covered by `test:status`.

## v0.15.15

### Added

- **`TableQuery::noun()`, the table's record noun.** A row action confirmation without a title of its own asks "<Label> this <noun>?" ("Remove this member?"); without a noun it stays "<Label> this item?". Carried as `TableData` `noun` and passed by DataTable to RowActions' new `noun` prop. Re-pins `core`.

## v0.15.14

### Added

- **Per-message mail identity: `FluentMailMessage::as(array $brand, ?Address $from, ?Address $replyTo)`.** A mail sent on behalf of a tenant renders with the tenant's brand (header lockup or name, button colour, HTML and text footers, inline lockup from its own file) and goes from its own sender, with no global config changed. Swapping `mail.from` or `kit.brand` per tenant leaked, as Laravel's mailer keeps the configured sender for the life of the process. Replies go only to the given or brand address; the configured `mail.reply_to`, which the mailer adds to every message, is removed from that message. `Brand::mailFor($brand)` builds the per-message `mail.brand`, and `mail.brand` now carries `name` (the lockup's alt text). The mail views take an optional `brand` prop and fall back to `mail.brand`. docs/mail.md, "One message's own identity". Covered by `test:mail-message-identity`.

### Fixed

- **A workspace's mail no longer also replies to the product's support address.** With an identity resolver (`FluentMailMessage::resolveIdentityUsing`, used by the settings feature's workspace mail identity), Laravel's mailer still added the configured `mail.reply_to` beside the workspace's reply-to. That message now replies only to the workspace's reply-to, or to no one when the workspace has none. Covered by `test:mail-message-identity`.

## v0.15.13

### Added

- **`kit.brand.lockup_scope`** (`KIT_BRAND_LOCKUP_SCOPE`): `guest` (the default) shows the lockup on guest and focus pages only, `everywhere` in the signed-in bar too. Covered by `test:home`.
- **`test:brand-assets`**: kit:brand's stroke detection and renderer choice, and a render with whatever renderer the machine has.

### Changed

- **The lockup no longer goes in the signed-in bar by default.** With `KIT_BRAND_LOCKUP` on, the bar showed the lockup at full width (`max-w-none`), so a long workspace name overflowed the row, the page and the breadcrumbs. The bar now keeps the mark unless `lockup_scope` is `everywhere`, and there the lockup is capped (16rem, 45vw on a narrow screen) and truncates, so the switcher still shrinks. The bar keeps `[data-shell-product-name]` with the lockup on (visually hidden), where it used to drop it. A browser check (`?lockup-bar`) holds a 120-character product and workspace name at 1440 and 768 with no overflow. Re-pins shell.
- **kit:brand handles a stroked logomark.** ImageMagick's own SVG renderer draws no strokes, so a stroked mark came out as a dot. kit:brand now detects strokes (attributes, `style` and `<style>`), renders a stroked mark with `rsvg-convert` when Imagick has no librsvg, and otherwise stops with how to outline the strokes or what to install. README, "kit:brand".

## v0.15.12

### Added

- **`Action::$description`, a line under a row action's label in its menu.** RowActions shows it under the item's label, muted, on one line truncated with an ellipsis, and makes it the item's accessible description (`aria-describedby`). For rows that share a label: mcp Connections' grant removals all read "Remove", each described by its grant. Also on the TypeScript `Action` and `ActionDescriptor` types. Re-pins `core`.

### Changed

- **Confirm titles are short.** A row action's confirmation title is "<Label> <noun>?" ("Remove permission?"); the record's name goes in the confirm body, where it wraps, never in the bold title. This reverses v0.15.10's "<Label> <record>?". A `confirm` without a `title` now asks "<Label> this item?" and RowActions names the record in the Dialog's body. mcp Connections' grant removal confirms "Remove permission?" with the grant and the assistant in its body. Re-pins `core`.

### Fixed

- **Split button: both segments line up.** The primary segment's label sat 2px above the menu segment's chevron (a small Button's fixed `h-8` against the trigger's padding). ButtonGroup's segments now share one height, `items-center`, a 20px line and `py-2`. Re-pins `core`.

- **PageEnd sits 32px below the content again.** Since v0.15.6 it took only the Page's 24px gap; inside a Page (not `flush`) it now adds 8px on top, as its old `mt-8`. Re-pins `core`.

## v0.15.11

### Added

- **`Registry::replaceWidget()` and `Registry::withoutWidget()`.** An app puts its own widget in a registered widget's slot by key (such as its own activity feed in place of `kit.activity`), keeping the slot's order and ability unless it sets them, or leaves a widget out. Either works whichever registers first; replacing a key nobody registers fails when the widgets are read. `kit.activity` doesn't step aside on its own when an activity-feed package is installed. See docs/dashboard.md. Covered by `test:dashboard`.
- **`kit.brand.copyright`** (`KIT_BRAND_COPYRIGHT`, default true). Both footers' identity line now reads `© {year} {identity}`, so the web footer reads "© 2026 Product · Built by Tey Labs Inc."; false leaves the © and year out. The mail header keeps the plain identity (`mail.brand.identity`); the mail footer reads the new `mail.brand.footer_identity`. Covered by `test:brand`.
- **`test:token-contrast`** computes every text/surface pair of the kit's default tokens in `kit.css` (light, `.kit-theme-light` and dark) and fails below 4.5:1.

### Changed

- **The default credit organisation is "Tey Labs Inc."** (`KIT_BRAND_CREDIT_ORG`), where it was "Tey Labs".
- **The kit's default tokens meet WCAG AA.** `--muted-foreground` is `hsl(0 0% 41%)` (was 45.1%) in the light defaults and `.kit-theme-light`: 5.07:1 on `--muted` (was 4.35:1), 5.53:1 on the background. `--destructive` is `hsl(0 74% 42%)` light (was `hsl(0 84.2% 60.2%)`, 3.8:1) and `hsl(0 72% 51%)` dark (was `hsl(0 84% 60%)`): 6.13:1 and 4.59:1 with the `--destructive-foreground` text. Re-pins core: an app's published `kit.css` reports as changed and needs re-publishing with `kit:update`.
- **The People widget's list is named "People list".** Its ScrollList region shared the card's name, "People", so the page had two landmarks with one name (axe `landmark-unique`). A new browser check (`dashboard.html`) runs axe (WCAG 2.2 AA, color-contrast) over the grid with every kit widget on, light and dark. Re-pins workspace-ui.
- **The Delete workspace warning mentions notes only with the notes feature installed.** `DeleteWorkspaceViewModel` carries `removes` (settings, notes when adopted, invitations), which the page joins. Covered by `test:lifecycle-pages`.
- **The dashboard's welcome card names the workspace.** It reads "Welcome to {workspace}, {first name}." and "Your workspace is ready.", where it said "Welcome to {APP_NAME}", so a white-label host no longer shows the platform's name. `APP_NAME` is the fallback when the workspace has no name.
- **The notes composer attaches files through BulkDrop.** The kit's dropzone (drop, paste or browse) replaces `FileInput`'s native file chooser, with the same per-file list, Remove buttons, file limit and request. Choosing files with browse replaces the selection rather than adding to it; drop and paste add. The colour select was already `InputSelect`, but it was bound one way to the default, so picking another colour (mouse or keyboard) never stuck; it now keeps the choice. A new browser check (`notes.html`) runs axe with WCAG 2.2 AA tags and color-contrast on the composer, light and dark, and drives the select and the dropzone by keyboard. Re-pins notes.

## v0.15.10

### Added

- **Row actions come in two variants, `split` and `overflow`** ([docs/data-table.md](docs/data-table.md)). `split` is the split button: the primary action joined to a chevron menu in a ButtonGroup. `overflow` is the previous layout: quiet inline actions plus a "…" menu. Choose per use with `<RowActions variant>`, `<DataTable :row-actions="{ variant }">` or `TableQuery::rowActions(variant: ...)` (`TableData` `rowActions`). Without a choice, a row with exactly one primary action plus menu items is split and any other row overflow; an explicit variant always wins. Both keep the trigger's "Actions for <row>" name, the in-flight guard and `aria-busy`, the disabled `reason` and the red-only-on-confirm rule. Browser tests (`?row-variants`) cover both. Re-pins `core`.
- **`Action::$ariaLabel`, an optional accessible name.** RowActions, ActionBar and BulkBar render it as `aria-label`. Without it, RowActions names each action "<Label> <row>" from the trigger's row label.

### Changed

- **Row action labels are verbs.** A confirmation without a `title` asks "<Label> <record>?", and the Dialog wraps the title and body in full (never truncated). A visible row action label over 24 characters or 3 words is clamped with an ellipsis and its full `title`, and warns once in development: "row action labels are verbs; put the record in the confirm". ScrollListRow's trigger now reads "Actions for <title>". Re-pins `core`.
- **mcp Connections: grant removals are labelled "Remove".** Each is named "Remove <grant>" and confirms "Remove the '<grant>' permission from <assistant>?". Revoke stays "Revoke". The controller is PHP, so no catalog byte changes.

### Fixed

- **Error page: no gap between the message and its recovery link.** v0.15.9's `Stack` used `gap-2`, 8px more than v0.15.4; it is `gap-0`, so the link sits directly under the message as before. Re-pins `workspace-ui`.

## v0.15.9

### Fixed

- **Error page: the recovery link sits under its message again.** Since v0.15.5 the Page gap put 24px between the message and its link; they are one group, now a `Stack` (`gap-2`, items at the start).

## v0.15.8

### Fixed

- **`kit:update claim` no longer refuses beside a sibling already at its new catalog bytes** (#717). The claim plan compared each owned file of the lineage with its recorded bytes, while the update plan compares it with the catalog, so a file the app had already moved to the new release blocked the claim of another, and that sibling then blocked the update. The claim now treats an owned file whose bytes equal its target catalog bytes as converged and records it at those bytes; a file that differs from both still blocks the claim. The adopt-in-place check covers both. No catalog bytes change, so nothing is re-pinned.

## v0.15.7

### Added

- **Row and bulk actions guard against a second activation while their request is in flight** (#720). In RowActions, once an action's visit starts, every action of the row (inline, lone or in the menu) does nothing until the visit finishes, and the running control is `aria-busy`. In BulkBar, a second activation (a click or a Confirm) during the visit sends nothing, and the bar is `aria-busy`. ActionBar already guarded its own visits.
- **A disabled action shows its `reason`** (#720). The descriptor's existing `reason` field is the "disabled reason" asked for in #720; no new field is added. In RowActions, ActionBar, BulkBar and their menus, a `disabled` action with a `reason` uses `aria-disabled` instead of the `disabled` attribute, so it keeps hover and keyboard focus. It shows the reason as a tooltip (`title`) and is described by it (a visually hidden element on `aria-describedby`). Choosing it does nothing, and its menu stays open. RowActions now honours `disabled`, which it ignored before. Browser tests (`?inflight`) cover both. Re-pins `core`.

## v0.15.6

### Changed

- **A destructive action is never a danger button in a row or action bar.** RowActions drew a lone destructive action (Revoke, when it is the row's only action) as a filled red button. It is now the same quiet ghost `sm` button as the other inline row actions. Red appears only on the confirm Dialog's commit and as the text of a destructive item in an overflow menu; ActionBar and BulkBar already followed this. A destructive action still asks in the danger Dialog. A browser test covers the lone Revoke and its Dialog. Re-pins `core`.
- **Compact auth cards keep their tight groupings under the page gap.** v0.15.5's `<Page>` gap (`gap-6`) moved a few blocks inside the guest and consent cards apart: a flashed status under the Login and Register heading (16px to 24px), the consent page's scopes under its request (16px to 24px), and Deny away from Approve (12px to 24px). Those pairs now group in a `<Stack>` (`gap-4`, and `gap-3` for Approve and Deny), so the cards render as in v0.15.4 with no margins back ([docs/page-rhythm.md](docs/page-rhythm.md), "Compact auth cards"). Re-pins `session-ui` and `mcp`.
- **A flashed status spans the Login and Register card again.** Since v0.15.5 `<Page>` is a flex column, and Alert's `self-start` (there so an Alert keeps its own height in a stretching row) made the status hug its text. The two auth statuses take `self-stretch`; Alert itself is unchanged. Re-pins `session-ui`.

## v0.15.5

### Added

- **Page rhythm: `<Page>` gaps its visible direct children (`gap-6`), and components carry no outer margins** ([docs/page-rhythm.md](docs/page-rhythm.md)). The trail and the header are one head block (`data-slot="page-head"`, `gap-2` inside it). Out-of-flow children (absolute, fixed, hidden, a closed dialog, a portal) take no gap. `<Page flush>` turns the gap off, for one full-bleed child (a Workbench, a SplitPane).
- **`Stack`**, the vertical rhythm primitive (`flex flex-col gap-4`), and **`PageSection`**, one titled region of a page (a `<section>` with a SectionHeader above its stacked content).
- **`PageGrid` and `PageGridItem`**: 12 columns from `md`, stacked below it, with the presets `main-aside` (8 and 4), `halves` and `thirds`, and a cell's `span` (1 to 12).
- A browser check (`?rhythm`) that guards the gap: equal gaps between a Page's in-flow children, none for absolute, fixed, hidden, dialog and teleported ones, none under `flush`, and the PageGrid's columns at 1280px and its single column at phone width.

### Changed

- **`BlockStack` is renamed `Stack`.** `BlockStack` stays for one release as a deprecated alias that renders a Stack (with a development warning); it is removed in the next release. The kit's pages import `Stack`.
- The header no longer has `mb-6`, the trail no longer has `mb-2`, PageEnd no longer has `mt-8` (its marker is out of flow while hidden, and it measures the content's end from the last in-flow sibling before it, past hidden, absolute and fixed helpers), and PasskeyOffer no longer has `mb-4`: the shell's content column (Workspace/Layout) now gaps the offer and the page (`flex flex-col gap-6`).
- The kit's own pages carry no outer margins on a Page's children (the guest and invitation pages, Search, the Dashboard's welcome card, settings Email, the mcp consent page), and the redundant `<Stack class="gap-6">` wrappers under a Page are gone.

### Migration

- Replace `BlockStack` with `Stack` (same props and markup): `import { Stack } from '@kit/ui'`. A host that points `@kit/ui` at its own module exports `Stack` (BlockStack is no longer imported by the kit's pages).
- Remove outer margins (`mt-*`, `mb-*`, `my-*`, `space-y-*`) from a Page's direct children: the Page's gap now adds to them. A wrapper `<Stack class="gap-6">` that was a Page's only child can go; keep it where it sets a different rhythm.
- A page whose content is one full-bleed child that must sit flush under the header (a Workbench, a SplitPane) passes `flush`.
- A child that is in flow but empty (a zero-height mount point) now takes a gap: render it with `v-if`, or position it out of flow.

## v0.15.4

### Fixed

- **Row actions are quiet in every DataTable and ScrollList.** RowActions drew inline actions as filled secondary buttons and its menu trigger as a "…" text button. Inline actions are now ghost `sm` buttons and the trigger is a ghost ellipsis icon, still named "Actions for <row>".
- **The danger tone follows `destructive` alone.** RowActions and BulkBar opened every confirmation in a danger Dialog, so a confirm-gated but harmless action (Suspend, Make owner) looked like a delete. They now use the neutral Dialog unless the action is destructive, as ActionBar already did.
- **People: Make owner is no longer destructive.** Concierge flags Transfer ownership as destructive, so the People menu coloured Make owner red beside Remove. The kit's PeopleTable now sends it as a plain action; it is a role change, and the page's own dialog still asks for the workspace's name and a password. `test:people-suspend` checks the flags, and three browser tests cover the quiet styling, the menu tones and the Dialog tones.

## v0.15.3

### Fixed

- **A failing dashboard widget no longer aborts the request under row-level security.** With RLS on (PostgreSQL), a workspace request is one transaction, and a failed statement aborts it. A widget whose query failed became its error card, but every later query in the request then failed with `25P02` (current transaction is aborted). Since 0.15.1 defines capabilities declared in an app provider's `boot()`, widgets that were silently hidden before now run, which exposed this. Each widget, and its visibility check, now runs in a savepoint (`Core\Savepoint::run`), which is rolled back on failure. Outside a transaction nothing changes. `test:rls` covers a caught failure mid-request with and without the savepoint.

## v0.15.2

### Fixed

- **`kit:preflight` runs `standards:preflight` without `--strict` again; `kit:preflight --strict` opts in.** 0.15.1 made every failed standards check fail the deploy, which promoted host-dependent advisories (`queue.failed-store` on Laravel Cloud's managed queue passes locally and fails only on the host) into deploy failures nobody could see before deploying. Blocking checks and row-level security problems still fail closed.

## v0.15.1

### Fixed

- **`kit:preflight` fails closed on any problem.** A row-level security problem, an RLS probe that throws, and any failed tey/standards check (it now runs `standards:preflight --strict`, so a non-blocking failed check fails the deploy too; groups not opted into enforcement still don't) each exit non-zero. New check: `test:preflight-exit`.
- **A capability declared in a provider's `boot()` is defined.** The kit defined Pennant features during its own boot, before an app's providers booted, so a capability an app declared in `boot()` stayed undefined (off) and every route behind it was a 404. Features are now defined once the app has booted. Declare in `register()` all the same.

### Documented

- Pennant stores each workspace's resolved capability default: after changing `kit.capabilities`, run `pennant:purge` (README, Capabilities and Pennant).

## v0.15.0

### Added

- **PostgreSQL row-level security as a second tenant lock (opt-in, minor).** `kit.tenancy.rls.enabled` (`KIT_RLS`) is off by default. It applies to PostgreSQL only; on other drivers it is a no-op and the database enforces nothing. See docs/rls.md.
  - `Rls::protect('table')` (in a migration, as the owner) enables and forces RLS and creates the `kit_workspace` policy. `Rls::unprotect()` reverses it.
  - `Workspaces::run()`, and so every workspace request through `ResolveWorkspace`, runs in one transaction that carries the workspace id with `set_config(..., true)` (SET LOCAL). The kit never issues a session-level SET, and a nested run restores the outer id.
  - Queued jobs run through `RlsQueuedHandler`, in one transaction carrying the workspace spatie restored. A job with no workspace fails closed (`rls_workspace_required`) before `handle()`. `NotTenantAware` jobs run with no workspace id.
  - `php artisan kit:rls:role <role>` creates or reshapes the runtime role on the admin connection: NOBYPASSRLS, NOSUPERUSER, NOINHERIT, DML grants (no TRUNCATE) and default privileges. The password comes from an environment variable. `Rls::grantRuntimeRole()` does the same from a migration.
  - `kit:preflight` fails when the runtime role is a superuser, has BYPASSRLS or owns a table, when it is the admin connection, or when a table with `workspace_id` lacks the policy and isn't in `kit.tenancy.rls.except`.
  - New check: `test:rls`, PostgreSQL only, in CI's PostgreSQL job. Not covered: a real pooler, Octane, Horizon, Laravel Cloud, and the kit's own feature tables under RLS.

## v0.14.1

### Fixed

- **`runs_read` is served exactly when it is listed.**
  - **Before:** `Registry::mcpTools()` always included it, so the kit's call path, consent and an app's own index (`/llms.txt`) disagreed with the server's tool list, which hid it while no queued tool was served.
  - **Now:** `mcpTools()` leaves out a tool that implements the new core `ServedBeside` contract unless its static `served()` says yes. `runs_read` is served only beside a queued tool, and everything reading `mcpTools()` agrees.
  - **New:** `configuredMcpTools()` gives the list before that step (what `kit.mcp.tools` leaves on).

## v0.14.0

### Added

- **Extendable General settings ViewModel (#679).** `EditSettingsViewModel` is no longer `final`.
  - An app subclasses it with its own prop methods and registers it with `EditSettingsViewModel::extendWith(AppSettingsViewModel::class)`. The settings page then gets the app's class.
  - The generated type is one type for the page: the kit's props plus the app's.
  - A class that isn't a subclass is refused, and `null` restores the kit's own.
  - The constructor is now `final`, so the kit can always build the subclass.
  - New check: `test:settings-view-model`.

## v0.13.0

### Added

- **Nested ViewModels (#676).** The new `ComponentViewModel` is a ViewModel for a child component rather than a page.
  - The parent returns it from a prop method, and the page passes it on with `v-bind`.
  - The component declares the child's generated type.
  - Children, nullable children and `list<Child>` are typed as references to the child type, and the payload nests them. `TypeScriptLiteralCheck` now pins this.

## v0.12.0

### Added

- **Marked tweaks: tweak in the app, graduate when proven.** An edited kit file whose first five lines carry `kit: tweaked <reason>` is a deliberate app tweak, not drift.
  - `kit:status` shows it as `tweaked` with its reason (`tweaks` in `--json`).
  - The new `kit:status --check` exits 1 on any unmarked edit and passes marked tweaks.
  - `kit:update` keeps a marked tweak the way `kit:update keep` does: it is released to the app untouched, flagged under `tweaked` in the plan, and later upstream changes are reported under `held`. Hand it back with `kit:update claim`.


## v0.11.2

### Fixed

- **No "your password was changed" mail for a first password.**
  - **What changed:** an account's first password is logged as `password_set` ("Password set") and mails nobody. Every later change is still mailed as `password_changed`.
  - **Before:** since v0.10.0, a brand-new owner or an invitee setting their first password through the reset link was told their password had changed.
  - **What counts as first:** an account with no password before (null or empty), or one marked with `SecurityLog::awaitFirstPassword($user)`. Use the mark for an owner provisioned with a random password and sent a reset link; the first set clears it.
- **No bare "#" in the fallback line.** The "If you're having trouble clicking…" line under a mail's button now appears only when the button has a real `http(s)` link. A Deliver preview or "Send me a test", whose button has no link yet, shows no fallback line instead of a bare `#`.


## v0.11.1

### Added

- **Deliver: history as headless data, for an aside feed.** `Kit/Deliver/deliveryEvents` turns `state.deliveries` into one feed event per delivery, newest first.
  - **Kind:** scheduled, sending, sent, bounced, failed, unconfirmed or cancelled. A delivery with a bounced or complained address is `bounced`, and its `bounced` field lists those addresses.
  - **`at`:** when it was sent, else when it is due or was created. No cancel or bounce time is invented.
  - **Also carried:** the entry itself and its actions.
  - **Feed library:** it renders nothing and depends on none, so an app composes it into its own feed (an activity feed, say).
  - **Resend from the aside:** `DeliverPanel` now exposes `resend(entry, action)`, which fills the form as the built-in Resend does.

### Changed

- **Deliver: the form is on a card, and the history is optional.** `DeliverPanel` draws its form on a kit `Card`. A new `history` prop (default `true`) leaves the built-in `DeliveryHistory` out (`:history="false"`) for an app that shows the history beside the form instead. `DeliveryHistory` stays as the fallback for apps without a feed.

## v0.11.0

### Added

- **Queued runs for slow MCP tools** (`mcp.queued`).
  - **`QueuedCapabilityTool`:** a `CapabilityTool` that, after the usual consent, arguments, confirmation and replay, stores a run and answers at once: `{status: "queued", run, poll_after_seconds, expires_at}`.
  - **The job:** `call()` runs on the queue in the run's workspace. It can report `progress(step, done, total)`.
  - **`runs_read`:** returns the run's status, progress, result, failure code or expiry. It reads only the person's, connection's and workspace's own runs (anything else is `not_found`) and is re-admitted under the run's ability.
  - **Re-check:** the job re-checks the live connection before it runs.
  - **Replay:** a retry with the same `intent_key` gets the same run.
  - **New:** the `kit_mcp_runs` table (migration stub), `kit.mcp.run_ttl` (24 hours), `kit.mcp.run_poll_after` (5 seconds), and the `ToolRunFinished` event. `runs_read` is listed only while a queued tool is registered. See docs/mcp-tools.md.

### Changed

- `CapabilityTool`'s admitted call is now the `admitted()` hook, which runs `call()` through `perform()`. Tools that only implement `call()` are unchanged.

## v0.10.0

### Added

- **Security mail** (session-ui). The account owner is mailed when their password changes, their email address changes (sent to the old address, naming the new one), two-factor is confirmed or turned off, recovery codes are regenerated, or a passkey is added or removed. It's one `SecurityNotice`, sent from `SecurityLog`. It's security mail with no manage link, says when and from where, and offers Reset password. `kit.security_mail` (`KIT_SECURITY_MAIL`) turns it all off, and a failed send never stops the change. New-device sign-in alerts aren't included (see docs/mail.md).

### Upgrading

- **Apps on session-ui start sending these mails.** Set `KIT_SECURITY_MAIL=false` to keep the old behaviour. An app that already mails these events itself should turn one of the two off.

## v0.9.2

### Fixed

- **Docblock literal types come out as literals, not `unknown`.** Spatie's transpiler writes `@return 'queued'|'running'`, `@param 1|2 $level` and an array shape's literal field as `unknown`. The kit's `ViewModelTransformer` and the new `AttributedDataTransformer` (in place of Spatie's `AttributedClassTransformer`) use `LiteralTypesTranspiler`, which emits the literal union. Register both in your app's `typescript-transformer` config to get the same. The kit's own `kit-types --check` now also fails on any property generated as bare `unknown`. The kit's shipped types are unchanged.

## v0.9.1

### Fixed

- **Public/Markdown has a typed ViewModel.** `PublicMarkdown::page()` renders `Tey\Kit\Features\Shell\ViewModels\PublicMarkdownViewModel`, and the page declares `defineProps<Tey.Kit.Features.Shell.ViewModels.PublicMarkdownViewModel>()`, so the standards rule `vm.typed-pages` no longer flags a kit file in every app. The props are unchanged. Re-publish the page (`kit:add` refreshes it) and regenerate your TypeScript types.

## v0.9.0

### Added

- **Workspace mail identity** (settings feature). The new Settings → Email sending section has these parts:
  - a sender name (by default the workspace's name);
  - the from address: the platform address, or the workspace's own domain on a `send.` subdomain, created at Resend with its DNS records shown, a Check now button and a pending/verified/failed status;
  - Reply-To: the sending member (`->sentBy($user)`), the platform's fixed address, or a custom address.

  Every `FluentMailMessage` built in a workspace resolves it in one place, `FluentMailMessage::resolveIdentityUsing()`, which the settings feature registers. Until a domain is verified, mail falls back to the platform address with the custom sender name. MCP gets `workspace_mail_identity_read`. See docs/mail.md.

### Upgrading

- **Workspace mail now goes out under the workspace's name.** With the settings feature, mail built inside a workspace is from "<workspace name>" at the platform address. Replies go to the member set with `->sentBy()`, or else to `mail.reply_to`. An explicit `->from()` or `->replyTo()` still wins.
- **Migration and page.** Run the new migration `0001_01_02_000403_create_workspace_mail_identities_table` and publish `Workspace/Settings/Email/Page`.
- **Custom domains** need `RESEND_DOMAINS_KEY` (or `services.resend.key`) set to a Resend key that can manage domains.

## v0.8.1

### Fixed

- **Mail with a button has Laravel's standard fallback line again.** `FluentMailMessage` now fills the layout's subcopy slot like Laravel's own notification template: "If you're having trouble clicking the "…" button, copy and paste the URL below into your web browser:" with the link, in both the HTML and text parts. The primary action is `action()`, or else the first filled button (secondary links don't count). It uses Laravel's lang key, so an app's translation applies. Unlike Laravel's `@lang` replacement, the button label is escaped like every other block.

## v0.8.0

### Changed

- **A conflict on an Inertia visit is a validation-style error, not JSON (breaking).** Any registered refusal code that answers 409 (`stale_write`, `stale_settings`, `deliver_stale`, `connection_*`, …) now answers an Inertia visit with a redirect back and `errors[<code>]` = its registered message, in the visit's error bag (`X-Inertia-Error-Bag` is respected). A `useForm` keeps the person's edits and reads the conflict from `form.errors.<code>`. Before, `stale_write` came back as raw JSON, which a plain `useForm` showed as Inertia's error modal, and every other 409 code replaced the page with the error page, losing the edits. JSON requests (`useHttp`, `fetch`, the API) and MCP still get the 409 JSON, so `useSubmitForm`, `httpSubmit`, row autosave and the Workbench layout and reorder endpoints are unchanged.
- **`FormActions`, `FormCard` and the Workbench read the conflict from `form.errors`.** A conflict code in a `useForm`'s errors blocks Save ("Reload to see the latest version before you save"), the status line shows its message, and the Workbench shows its conflict alert with the edits kept. New helpers in `formFrame`: `isConflictCode()` and `formConflict()`.

### Documentation

- **ViewModels are eager by design.** `only` trims what a partial reload sends, not what it computes. A prop that is expensive, or only needed on a partial reload, returns `Inertia::optional()`, `defer()`, `merge()` or `scroll()` from its method (the ViewModel docblock).
- **`useForm` first.** `docs/submit-form.md` and the `submitForm` header: your own UI saves through a visit; `useSubmitForm` and `httpSubmit` are for the allowed non-Inertia targets (a third-party or public API, upload signing, a socket or run transport, typeahead), with a one-line reason at the call.

### Upgrading

- Code that caught the stale write on an Inertia visit in `onHttpException` (reading `{ error: 'stale_write' }` with `errorJson`) moves to `onError` and reads `errors.stale_write`.
- A shared visit helper that turns `onHttpException`'s status into a failure (for example `failureFromStatus(response.status)`) no longer sees a 409 on a visit: it arrives in `onError` as `errors.<code>`. If the helper shows conflicts differently from field errors, check for a conflict code there (`isConflictCode()` / `formConflict()` from `formFrame`). Code that rendered `stale_settings` or another 409 code's error page now gets the error on the form.
## v0.7.66

### Fixed

- **A `Button` with a `mailto:`, `tel:` or `sms:` href is a plain link.** Without `external` it went through Inertia's Link, which turned `mailto:sam@example.test` into a broken relative URL. These schemes now always render a plain `<a>`, as `external` does.

## v0.7.65

### Changed

- **`DeliverPanel` confirms before a real send.** Send, Schedule and Resend open the kit Dialog: "Send to Robin Vale and Sam Lee?", with the recipient count (and how many are Bcc), the time when scheduled, and every address. Nothing posts until it is confirmed; Cancel sends nothing. "Send me a test" and Preview stay one click.

### Added

- **Typed To addresses for a delivery**, for a customer whose contact has no email on file. The panel has "Other recipients (To)", posted as `to_other`; `cc_other` is accepted too, over HTTP and MCP. A typed address must be a valid email. Contacts picked as To or Cc must still be live contacts, so a deactivated contact is still refused, not sent. Resend puts an earlier typed To back in the field.

### Fixed

- **`deliver_send` and `deliver_resend` over MCP failed every time** with `operation_failed`: they read `intent_key` from their arguments, which the gate strips before `call()`. Nothing was sent. They now read it from the new `CapabilityTool::intentKey()`, which returns the running call's intent_key (null without one) while the operation's arguments still never carry it. A check now refuses any tool that reads `$validated['intent_key']`. (#660)

## v0.7.64

### Added

- **Deliver's Vue pieces**, in core under `Kit/Deliver/` (they need the `deliver` feature's server):
  - `DeliverPanel`: pass it the `DeliverState` as `state`. It offers the subject's live contacts as To, Cc or "Not on this send"; the workspace's internal copies ticked as Bcc, plus other Bcc addresses; the default subject and message, editable for this send; and When: now, tomorrow at 8:00 (the person's time) or a chosen date and time, never in the past. It has Send or Schedule, Preview, and "Send me a test". While the server sends Send disabled, its reason shows instead. Every post carries the revision (in the action's URL) and one claim key per intended send.
  - `DeliveryPreview`: the email the server rendered, in a fully sandboxed iframe (no scripts, forms, navigation or same-origin access), with the subject and whether the document is attached or linked.
  - `DeliveryHistory`: each delivery's state ("Not confirmed" for `unknown`), who sent it and when, the channel, every address with its provider status, and its note. Cancel and Revoke link post directly; Resend fills the panel from that delivery and offers to turn off the earlier link.
  - `deliver.ts`: the types and the headless helpers (`buildDraft`, `tomorrowMorning`, `requestPreview`, `claimKey`).

## v0.7.63

### Added

- **`deliver`, an opt-in feature: email a finalized subject to its customer.** Server, routes and MCP tools; the Vue pieces follow in the next release. Requires `finalize`. Register a definition with `app(Deliveries::class)->register(Delivery::define('report.deliver')->…)`:
  - `notification(fn ($subject, DeliveryMail $mail) => new YourNotification($mail))`: your own Notification, whose `toMail()` must return a FluentMailMessage (anything else is refused). The preview and the send both call its `toMail()`, so what is previewed is what is sent; the kit adds Cc, Bcc, the attachment and the test marker.
  - `attachment(fn) => DeliveryAttachment`: the document. Over `maxAttachmentBytes()` (10 MB by default) it is linked, not attached.
  - `recipients(fn)`: the subject's LIVE contacts. To and Cc must be among them, so a contact who left is refused, not dropped. `internalCopies(fn)` and `defaults(fn)` are your workspace settings, editable per send.
  - `finalizeFlow('report.finalize')` gates sending on it and records the finalization sent. Or pass `gate(fn): true|string` for subjects that never finalize; a gate that throws refuses.
  - `version(fn)`, `can(fn)`, `url(fn)`, `linkDays(30)` and `customerPage(fn)` complete it.
- **`DeliveryDesk`** has the operations, the same for HTTP and MCP:
  - `state`, `preview` (nothing written; its button goes nowhere) and `attachment` (inline, never cached);
  - `send`, now or at `send_at`;
  - a test `send` to the sender only, marked `[Test]`, with no link;
  - `resend` (a new row; it may revoke the previous link), `cancel` and `revoke`;
  - `recordEvent` for your provider's webhooks (delivered, bounced or complained, per address; duplicates are ignored);
  - `open` for the customer link.
- **At most once.** Every send carries a claim key, unique per workspace; the same key again answers the same delivery. A send the provider never confirmed is `unknown` and is never sent again. Every write carries a revision, and a stale one is refused with `deliver_stale` (409).
- **Scheduled sends are rows**, swept every minute by `kit:deliver-sweep`, which the kit schedules; your scheduler must run. A due row is claimed atomically, so only one worker sends it. The sweep then re-checks the gate, the version, the sender's access and the recipients, and sends or cancels with a reason. A row stuck mid-send for 10 minutes becomes `unknown`. Un-finalizing a subject cancels its scheduled sends.
- **The customer link** is a temporary signed route with a per-delivery token, stored only as a sha256 and revocable. It answers 404 when expired, revoked, tampered or unsigned; it is never cached, never indexed and sends no referrer. The first open is recorded, and nothing tracks the email itself.
- **Events:** `DeliverySent`, `DeliveryCancelled` (reason, and whether the kit did it) and `DeliveryBounced`. Your app tells the owner, the creator or the sender from these.
- **Permissions:** `deliver.read`, `deliver.send` (send, schedule, cancel) and `deliver.resend` (resend, revoke a link) on `workspace.deliver`; owner and admin send, viewers read. MCP tools: `deliver_read`, `deliver_preview`, and the destructive `deliver_send`, `deliver_resend`, `deliver_cancel` and `deliver_revoke_link`.
- **Migration:** `create_kit_deliver_tables` (block 9). Run `php artisan migrate` after adopting `deliver`.

## v0.7.62

### Added

- **`Checklist` `#body="{ item }"` slot**: a step's own content, such as an inline editor, under its label and errors and above its fixes, in both variants. Map step keys to components in an object (`<component :is="editors[item.key]" v-if="editors[item.key]" v-bind="steps[item.key]" />`) rather than branching on the key. It renders for every step, done ones included; hide it there yourself if you want to.
- **Finalize step warnings**: `Step::…->warnings(fn ($subject) => ['No subject photos.'])` adds non-blocking notes, sent as `GateStep.warnings` and drawn by `Checklist` (`ChecklistItem.warnings`) in the warning colour. They never block Confirm or completion.

## v0.7.61

### Added

- **`Checklist variant="steps"`**: the same server rows drawn as a numbered vertical stepper for a page given over to a gate. Each step has a 32px marker (filled with a check when done, ringed in the primary colour when current, outlined with its number otherwise), joined by a rail that fills from the top while every step so far is settled. Titles are `text-lg` semibold with the detail beneath, and each step body has room for its fixes. The `actions` slot becomes the rail's last step, titled with the new `finalLabel` (a finalize gate's "Finalize", holding `FinalizeControl`). `variant="list"`, the compact list, stays the default.

### Fixed

- **`ButtonGroup` segments have the paper fill again.** Default-appearance segments were transparent, so over an off-white page header they looked unfilled beside the menu trigger. The segment wrapper now has `bg-paper`, like the trigger. `appearance="text"` stays transparent.

## v0.7.60

### Added

- **Finalize keeps the printed fields of related records.** A flow declares them with `->snapshot(fn ($subject) => ['customer.name' => …, 'customer.address' => …])`: named, normalised scalars (integers, strings, booleans, null; amounts in minor units), at most 50. Finalizing stores a frozen copy of exactly those values on the finalization, with a hash per key, so a finalized invoice prints what was certified after the customer is edited. Everything else about a finalization stays hashes only.
- **`FinalizeGate::snapshot($flow, $subject)`** returns those values while the subject is finalized (null otherwise) for the app to print, and **`FinalizeGate::drift($flow, $subject)`** names the keys whose live value has changed since, for "details changed since finalizing". Taking the new values means un-finalizing and certifying again; an un-finalized row keeps its snapshot as history.
- **Migration:** `add_snapshot_to_kit_finalizations` adds the nullable `snapshot` and `snapshot_hashes` JSON columns. Run `php artisan migrate` after updating. Flows without a snapshot are unchanged.

## v0.7.59

### Changed

- **`Checklist` fixes look the same and can be selected.** An item's `action` now renders as a small button, the same as its descriptor `actions`, instead of an underlined link. **If an app relied on the link look**, use the `#item` slot. Every fix sits in a wrapper with `data-step-action="<item key>:<action key>"`, so tests and pages can find "Confirm" without matching its text.

### Added

- **`Checklist active="turn"`** draws the active step as a still primary ring, not a spinner, for a step a person does next ("Summary reviewed", "Certify and finalize"). The default, `running`, still spins for work in progress.

## v0.7.58

### Added

- **The finalize gate's Vue pieces.** `Checklist` items may carry `errors` (each with an optional `field` and fix `url`, drawn under the label until the step is done) and `actions` (server action descriptors, drawn as small buttons). Pass the server's `progress` and it decides `complete`; without it the list computes its own, as before. `FinalizeControl` shows the statement, a checkbox to agree, and the server's Finalize action (disabled with its reason until the gate is complete), and posts `certified`. `FinalizedBanner` reads "Finalized by {by} on {date}" (adding "through an assistant" for the mcp channel) and offers Un-finalize, which asks for a reason, when the server sends the action.
- **Finalize descriptors carry the revision in their URL**, so the Confirm, Finalize and Un-finalize actions post as they are and a changed gate refuses them.

## v0.7.57

### Added

- **The `finalize` feature** (opt in with `kit:add finalize`): a checklist that ends in a certified, recorded commit. An app registers a `Flow` with `Step::check` and `Step::confirmation` steps, a statement resolver (so each workspace can edit its certify wording; its version and a hash of its text are recorded) and an optional per-subject check. The kit computes every step on the server; a confirmation holds only while the fingerprint of what was confirmed (normalised values, never timestamps) is unchanged. Finalize and every other write carry the gate's `revision` and are refused with 409 when it changed. Finalizing locks the subject, recomputes, records who, the channel (`web` or `mcp`), the statement and a snapshot hash, bumps `lock_version` if the flow names one, and dispatches `Finalized` after the commit. Un-finalize is its own ability, needs a reason, keeps the history, and can take an optional extra rule (a second person, say; off by default). `FinalizeGate::isFinalized()` lets the app's own policies lock what was certified and nothing else. JSON routes under `finalize/{flow}/{subject}` and four MCP tools (`finalize_read`, `finalize_confirm`, and `finalize_certify` and `finalize_unfinalize`, which ask in the chat first).
- **Not in this release:** the Vue side (`Checklist` taking the server's progress and errors, `FinalizedBanner`, the Finalize control). The `GateState` Data is shaped for them; they follow in a patch.

## v0.7.56

### Changed

- **`InertiaAction` defaults to Button's size** (`default`, 36px, the height of a ButtonGroup segment) instead of `sm` (32px), so it lines up beside a Button or ButtonGroup without a per-use prop. **If an app relied on the old default** (an InertiaAction beside a small Button, or in a dense row), pass `size="sm"`. The kit's own FileList row actions now say `size="sm"` and look the same.

## v0.7.55

### Fixed

- **An overlay opened from a pill or inverse container no longer inherits its look.** A Dialog, Popover or Modal opened from a FloatingToolbar (or any `Surface` with `shape="pill"` or `tone="inverse"`) rendered its own buttons as pills. The portalled content now resets to the default surface through the new `SurfaceReset`; the trigger keeps the container's look, and Modal (with Popover's phone sheet) is a default surface throughout.

## v0.7.54

### Fixed

- **`StickyBar` no longer hides the focused control (WCAG 2.4.11).** While pinned, a control that takes focus under the bar is scrolled just clear of it; focus never moves, and nothing happens in flow or for controls inside the bar.

## v0.7.53

### Fixed

- **`ButtonGroup` has its pre-0.7.41 look again**: segments are 40px tall (`py-2`), in the outline's text colour, with the label weight set by the container: semibold in an `ActionBar` (a header's actions), medium in a toolbar's own group (the new `weight` prop, `medium` by default); the menu trigger's label is normal weight, and dim on hover instead of filling. A one-segment group keeps its outline and shadow. The menu trigger is its own outlined segment beside the others, with the larger muted chevron, and the menu panel is borderless on the deeper shadow, with no extra inset. `ButtonDropdown`'s panel is the same. The menu lines up with the trigger's start again and swaps to its end when it would not fit (`flip-align`), as before 0.7.41, 10px below it (the default `offset` is `[0, 10]` again).  Everything still comes from the `paper` and `outline` tokens.

## v0.7.52

### Added

- **`CardHeader` `align`**: `center` (default, unchanged), `start` or `baseline` places `#leading`, `#avatar` and the title column against each other. With `start` or `baseline`, an index in `#avatar` (such as "5.") and a grip in `#leading` sit on the title's first line instead of being centred across the title and meta.

## v0.7.51

### Fixed

- **`InputNumber` no longer vanishes on a non-canonical `defaultValue`**: a value not in canonical form (money in integer minor units, a decimal at its scale), such as `'12250.00'` for money, made the field throw while rendering and disappear. It is now read as typed text: `'12250.00'` holds `1225000`, and a value that can't be read shows the field's usual error. Dev builds log a warning naming the field.

## v0.7.50

### Fixed

- **Focus moves into a menu reopened by click**: reopening a menu from a trigger that already had focus (for example after Escape) left focus on the trigger, so arrow keys did nothing. Focus now moves into the menu, with no item highlighted, as on the first open.

## v0.7.49

### Fixed

- **Menus stay open behind an item's dialog**: choosing a `DropdownConfirm` or `DropdownDelete` item no longer closes the menu before its dialog finishes. The item's after-close work waits for the dialog and runs when the menu closes, as MenuPanel.md describes.
- **PageUp and PageDown** in an open menu go to the first and last item, as Home and End do.

## v0.7.48

### Added

- **`FaintBackdrop`**: a mark as a faint ground behind content, a flat colour seen through a mask of single-colour artwork, so one asset serves light and dark. It has four placements (`bleed`, `corner`, `inset`, `tile`) and is decoration only: hidden from assistive tech, no pointer or selection, never printed. Its artwork, ink, opacity and tile size are CSS variables (`--faint-backdrop-image`, `-ink`, `-opacity`, `-tile`) that the app defines, so its contrast over text can be tested as arithmetic.

## v0.7.47

### Added

- **`FileList`**: a record's files as a read-only attachment list. Each row shows a thumbnail or an extension badge, the name as a link, and the size and type, followed by the server's named `actions` for that file (each an `InertiaAction`, so Delete confirms first). With no files it shows an `EmptyState`. `fileSize` puts a byte count into words, and `fileExtension` gives the badge text. Use `MediaCollection` for uploading, reordering or moving files.

## v0.7.46

### Added

- **`SectionHeader` takes `size="page"`**: the title at the page heading's scale (wrapping, not truncated), for a page that heads itself outside the shell's `Page`. Default `section`, unchanged.

## v0.7.45

### Changed

- **Component stubs are formatted with the house Prettier** (4-space indent, single quotes, 80 columns, `htmlWhitespaceSensitivity: css`, so inline text renders as written, Tailwind class order). Formatting only: no behaviour or class changes. Kit CI now fails an unformatted stub. Re-pins every feature whose stubs changed.

## v0.7.44

### Changed

- **`InputCheckbox`'s own description id is now `<id>-hint`** (was `<id>-description`). A described checkbox inside a described `Field` rendered two elements with the same id, so its `aria-describedby` could not tell them apart. A test or style that selects `#<id>-description` for the checkbox's own text should select `#<id>-hint`.

## v0.7.43

### Added

- **`SortableList` grid and row layouts, and a cut divider.** `layout="grid"` lays rows out as wrapping tiles and `layout="row"` as one scrolling strip; their move buttons point back and forward (the arrow keys already moved either way). `cut-after="N"` draws a labelled divider after N items (`cut-label`, else "Only the first N are shown"), for a set where only the first few are shown. The divider is a separator, not a row: moving items across it is what changes which ones are shown.
- **`MediaCollection` takes `layout`, `cut-after` and `cut-label`** and passes them to its SortableList; in a grid each photo is a square tile with its name and menu beneath.

## v0.7.42

### Added

- **`PageTabs`**: tabs that are pages. Each is a route link in a `<nav>` landmark, the current one marked `aria-current="page"`; phones get the kit's `InputSelect`, which visits the chosen page. `PaneTabs` stays for panes inside one page.
- **Menu items that run after the menu closes.** `DropdownItem` emits `@chosen` once the menu has closed, and the menu context (`useMenuPanel()`, now exported with `menuPanelKey`) gains `afterClose(fn)`. Use either for work that moves or re-renders the item's own row: running it in `@click` could leave the menu stuck open. App-built items can also `hold()`/`release()` the menu around their own confirm.

## v0.7.41

### Changed

- **`ButtonGroup` rebuilt clean-room** on the kit's paper and outline tokens; its old menu panel carried a class string flagged as derived from a commercial catalogue. Same API (default slot, `#menu`, `#label`, `#menuheader`, `label`, `offset`), plus `appearance="text"` for link-style actions in a quiet header and `menuLabel` to name the chevron trigger ("More actions" by default). Children sit flush (one outline, a divider between segments, no border or radius of their own), so a link and its chevron no longer look nested.

## v0.7.40

### Added

- **Cards** (docs/cards.md), written clean-room from a slot API (title, meta, avatar, actions, tone, unstyled) on the kit's tokens: `Card` (a Surface in parts), `CardHeader` (a real heading at `level`, `#meta`, `#avatar`, `#leading`, `#status`, `#actions`, `tone`, `unstyled`), `CardBody`, `CardFooter` (`sticky`), `CardBanner` (`tone`), and `RecordCard`, `SummaryCard` and `ActionCard` built from them. Actions go in `#actions` as a `ButtonGroup` of named actions.

### Fixed

- **The dashboard's People widget has no inline `style=`.** It set ScrollList's height with a static `style` attribute, which tripped the standards Tailwind-only check in every app that installed it; it's a Tailwind custom-property class now.

## v0.7.39

### Fixed

- **`InertiaAction` and `DropdownAction` take the kit's own `Action`.** They read only the older keyed-link shape (`confirmationMessage`, `variant`, `target`), so a kit `Action` lost its confirmation: a destructive action with `confirm` ran without asking. Both now take either shape: `confirm` asks first in the kit's Dialog (typed when it has `typed`, sent as `confirmation`), `destructive` styles it as danger, `disabled` shows it unusable with its `reason`, and `newTab` opens a plain link with `rel="noopener noreferrer"`. A typed confirmation can't be asked from a menu, so `DropdownAction` shows such an action disabled instead of running it.

## v0.7.38

### Added

- **`InertiaAction`, one server action as a button** (docs/single-actions.md), the twin of `DropdownAction`: it confirms in the kit's Dialog when the action has a `confirmationMessage`, else it is an Inertia link with the action's method; a `target` opens a plain link. The default slot replaces the label, and a null action renders nothing. `DropdownAction` gains the same label slot and null handling. Place them yourself in a `ButtonGroup` (default slot for buttons, `#menu` for items) when a toolbar is explicit markup rather than an `ActionBar`.

## v0.7.37

### Changed

- **Repeater `row-actions` is the host's toolbar.** A filled `row-actions` slot renders bare (no bordered wrapper), and the Repeater draws none of its own move, menu or remove controls beside it. The slot gets them as props: `moveUp`/`moveDown` (null when the row can't move), `first`/`last`, `remove` (asks first, as the kit's button does; null when the row can't be removed), `canRemove`, `row`, `index` and `label`. The grip still leads the row. Hosts that put plain buttons in `row-actions` and relied on the frame or the kit's ⋯ menu now draw those themselves.

## v0.7.36

### Fixed

- **No more "injection InertiaFormContext not found" warnings.** Kit fields, `Repeater`, `FormCard`, `FormActions` and `Button` called Inertia's `useFormContext()`, which injects without a default. Outside an Inertia `<Form>` they warned on every render: thousands per page on a bench of standalone rows. They now read the context through `useOptionalFormContext()`, which is null outside a Form, and they work standalone as before.

### Added

- **Repeater: grip only.** `:order-buttons="false"` hides Move up/down when the row has a drag grip (the grip still reorders with the arrow keys, announced); without `draggable` the buttons stay, so a row is never left unmovable. The `row-header` and `row-menu` slots get `moveUp`/`moveDown` (null when the row can't move) and `first`/`last`, so a host menu can offer the moves.
- **Card headers in a Repeater.** `Repeater` takes a `row-header` slot (`{ row, index, label }`) that replaces the row's title with the host's own header, such as a `SectionHeader` with title, meta and actions. The leading grip, Move up/down and remove stay Repeater's. `SectionHeader` takes a `#meta` slot for a meta line with links (a source and a seller), falling back to the `meta` prop. Re-pins core.

## v0.7.35

### Fixed

- **Repeater's drag grip leads the row,** as SortableList's does. It sat after the row title in cards and in the trailing actions cell in the table layout; it now comes first in a card's toolbar and is pinned to the leading edge in the table layout, with matching room on the rows and the header so the columns still line up. One grip per row, as before. Re-pins core.

## v0.7.34

### Added

- **A record's media collection.** `MediaCollection::for($owner, $collection, $url)` gives the page a record's items in order (delivery URLs only) with a version. `ReorderMedia`, `MoveMedia` (single-file targets, `replace`, type rules, no byte copy) and `RemoveMedia` act only on the owner's own items, fail closed on anything else, and refuse a stale version. `<MediaCollection>` renders a single file or an ordered set with BulkDrop direct uploads, SortableList reordering, and per-item move and remove. docs/media.md, "A record's collection". Re-pins core.

## v0.7.33

### Added

- **Workbench save state:** "Saved · 2 min ago" from a new `savedAt`; `syncSave(state)` and `rollupRowSaves()` roll row autosave up into the bar's state; and the actions bar goes `inert` while an InertiaUI modal is open, so its actions can't be clicked through the dialog.
- **Row autosave for editable tables.** `useRowAutosave({ url, version })` saves each row on its own as it's edited: debounced and on blur, one request per row at a time, with the row's version. A 409 or `stale_*` refusal marks the row stale and holds its edits ("Reload row"); a failure keeps them for "Retry"; unsaved rows hold the leave guard. The server's copy of a saved row comes back for server-owned totals. `<RowSaveStatus>` shows each row's state, and `createRowSaver` is the framework-free core. docs/row-autosave.md. Re-pins core.

## v0.7.32

### Added

- **Pending actions.** A kit action shows its request in flight and can't fire twice. A dialog action sends its `method` through `ModalLink` and shows its `loading` as a spinner with `aria-busy`, so a slow modal never stacks two dialogs. The action runner's visits mark the running action, and the bar blocks every action until the visit finishes. `useModalVisit()` does the same for a modal opened from code. docs/pending-actions.md covers it, and running work in a modal: one POST whose controller starts or reuses a background run and answers with the modal. Re-pins core.

## v0.7.31

### Fixed

- **A file dropped on BulkDrop without `handleDrops` no longer opens in the browser.** The zone accepted the drag (dragover) but only `handleDrops` prevented the drop's default, so a host that owned drops and missed one let the browser open the file and leave the page, unsaved work included. `drop()` now always prevents a file drop's default; propagation is unchanged, so the host's own handler still receives it. Re-pins core.

## v0.7.30

### Added

- **`Checklist`** ([docs/checklist.md](docs/checklist.md)): a server-computed checklist. The server sends `ChecklistItem` rows (`key`, `label`, `state` pending/active/done/failed/skipped, optional `detail`, `reason`, `required` and `action`), and the component draws them: a spinner on the active step with `aria-current`, a tick on done steps, a failed step's reason as an alert, and a polite status line. It has `item` and `actions` slots and an `action` event. Headless use goes through `checklistProgress()` and `useChecklist()`. An unknown state counts as pending, and an empty list is never complete. Re-pins core.

## v0.7.29

### Fixed

- **The role and permission syncs run after every `migrate` again.** The listener that forgets the cached workspace columns returned `Cache::forget()`'s result. With nothing cached that result is `false`, which stops the event, so `CatalogSync` (`concierge:sync-roles`, `kit:permissions:sync`) never ran, and a deploy on a cold cache silently skipped new roles and permissions. It is now a void closure, and `tests/HostBootCheck.php` checks that CatalogSync runs after a migrate with the key uncached.

### Added

- **Images in InputRichText** ([docs/rich-text-images.md](docs/rich-text-images.md)), opt-in: with `allow` naming `image`, an `image-sources` pattern and an `upload-image` callback, the toolbar gains an Insert image control and pasted or dropped image files are uploaded. Each becomes an `image` block whose `src` is the host's own reference. A `src` outside the pattern is refused both ways, SVG is never uploaded, and other files are still `file-intent`. `image` joins RICH_TEXT_TOOLS and RICH_TEXT_ALLOW; `isImageSource` and `splitImageFiles` are exported. Re-pins core.
- **A breadcrumb's tooltip** (`Breadcrumb::$title`): `SessionUiPageLayouts::crumbTitleUsing(fn ($crumb, $index, $request) => ?string)` lets a host give a crumb more on hover than its label, such as a case's reference under its subject. A crumb passed through `crumbs` can set `title` itself. Without one, or when it only repeats the label, the tooltip is the label as before. Re-pins core.
- **`listenerLiveTransport(connect, { events, onStatusChange })`** ([docs/live-props.md](docs/live-props.md)): a live props transport over a host's own realtime connector (`listen(channel, event, handler) => stop`), with the same lazy connect, allow-list and stop-before-connect guard. `echoLiveTransport` is now built on it, with unchanged behaviour. Re-pins core.

## v0.7.28

### Added

- **Live props** ([docs/live-props.md](docs/live-props.md)): opt-in helpers for Inertia's live props (inertia-laravel 3.7, Inertia client 3.11). `LiveProps::on($props, $keys, $notice, when:)` makes view-model props live on one notice, which must be `ShouldBroadcastNow`; with `when` false, or an older inertia-laravel, the props are unchanged. `echoLiveTransport()` is a lazy Echo transport for `configureLive`, sharing a channel between props and failing closed with no broadcaster or an unknown channel type, with a configurable event name. `useLiveFallbackPoll` is the slow-with-a-socket, fast-without, visible-tab poll. With `useIsLive(prop)`, a component's own realtime reader stands down while its prop is live. The kit never imports Inertia's live API, so hosts on older clients still build. Re-pins core.

## v0.7.27

### Added

- **`SwatchPicker`** ([docs/swatch-picker.md](docs/swatch-picker.md)): a colour picker with suggested swatches, a palette and a hex field in a Collapsible. It is one keyboard radiogroup (roving tabindex, the arrows, Home and End). A default swatch stands for an empty value, and the check mark is black or white by the swatch's luminance. A WCAG contrast hint shows against a background, and a hex that isn't a colour is posted as typed. The colour helpers `contrastRatio`, `luminance`, `markColour` and `normaliseHex` are exported too. Re-pins core.

## v0.7.26

### Added

- **`TenantInvites::landUsing()`** ([docs/onboarding.md](docs/onboarding.md#tenant-invitation-link)): the host decides where an accepted tenant invitation lands, given the new workspace and the user, for apps without workspace-ui that would otherwise land on `fortify.home`. Only a same-site path counts, and anything else keeps the default, which is unchanged.

## v0.7.25

### Added

- **Route invariants** ([docs/route-invariants.md](docs/route-invariants.md)): `Tey\Kit\Testing\RouteInvariants::all($namespaces)` checks the route table against four rules read from the routes themselves: short twins of workspace routes, the workspace middleware order, guarded workspace writes, and declared public routes. This replaces a stored copy of the route table. Two controller attributes back it: `#[AuthorizedBy(Operation::class)]` for a write its operation authorises, and `#[PublicRoute('why', open: false)]` for a route reached without signing in. The kit's own controllers now carry these declarations and keep all four rules (`composer test:route-invariants`).

## v0.7.24

### Added

- **Tenant invitation link** ([docs/onboarding.md](docs/onboarding.md#tenant-invitation-link)): `/invite/{token}`, behind a new opt-in session-ui route group, `tenant-invitations`. It shows the invitation with kit's invitation page, creates the invited account already verified, and accepts through `Concierge::provisionWorkspaceUsing()`. Responses are no-store and no-referrer, and Concierge's tenant invitation throttles apply, with the refusal in words. `TenantInvites::nameUsing()` shows the workspace's name before it exists.

### Security

- **Closing an account also revokes its unexchanged OAuth codes.** Passport authorization codes and approved device codes (`oauth_auth_codes`, `oauth_device_codes`, where the tables exist) are revoked with the access and refresh tokens, in both deletion modes. Before, a code issued just before the close could still be exchanged for a token within its lifetime.

## v0.7.23

### Added

- **`useSubmitForm().setError(field, message)`**, or `setError({ field: message, … })`, sets an error by hand, as Inertia's `useForm().setError` does. One example is a file picker refusing a photo before anything is sent. The message is plain text and capped like a server's. An empty message clears that field, and other fields keep theirs ([docs/submit-form.md](docs/submit-form.md#errors)).
- **Passkey sign-in** ([docs/onboarding.md](docs/onboarding.md#passkey-sign-in-and-management)). Where Fortify's passkeys feature is on, the sign-in page offers "Sign in with a passkey", on Fortify's own endpoints, with Remember me. The browser helpers `passkeySignInSupported` and `signInWithPasskey` are exported from `@kit/ui`, alongside `registerPasskey`, `passkeysSupported` and `passkeyName`.
- **Settings › Security › Passkeys** lists the person's own passkeys. They can add one, rename one, or remove one with their password. Another person's passkey is a 404. Adding and removing are recorded in the security log.

### Security

- **A passkey signs in only an active account.** Passkey login skips Laravel's `Validated` event, where Concierge refuses a deactivated account, so session-ui now registers the same refusal as passkeys' login check. An app with its own `Passkeys::authorizeLoginUsing` replaces it and must refuse inactive accounts too.

## v0.7.22

### Security

- **Immediate account deletion now closes the account like the deferred mode.** It revokes Passport tokens and their refresh tokens, deletes Sanctum tokens, ends database sessions and cycles the remember-me token. Before, only the deferred mode did this, so a hard-deleted user's Passport tokens stayed valid where `oauth_access_tokens` had no foreign key. When the user row is kept (`DeletesAccountRow`, or `KIT_ACCOUNT_USER_ROW=anonymise`), its Concierge account is kept deactivated instead of deleted, so the tombstone can never sign in.

## v0.7.21

### Added

- **Shared sortable lists** ([docs/sortable.md](docs/sortable.md)): `SortableList`, `SortableHandle` and `useSortable`, from `@kit/ui`.
  - A plain list, so rows may hold links and buttons. Each row has a drag grip (pointer drag; ↑/↓ and Home/End while it is focused) and Move up / Move down buttons.
  - A polite live region announces each move ("Slide Plan moved to position 2 of 4").
  - Either `update` with the new ids, or the app's `reorder` action with a `version` token: optimistic, rolled back on a 409 or stale code, with an `on-conflict` hook.
- **`<Repeater draggable>`** adds the grip to each reorderable row, through the Repeater's own move, so the announcement and `change` event are unchanged. Off by default.
- `Kit/Data/sortable` is the shared reorder model: it re-exports the Workbench's `moveItem`, `createReorder` and `isConflict`, and now holds `sendOrder` and `ReorderAction`, which `useWorkbench` re-exports, so existing imports keep working. The Workbench navigator's behaviour is unchanged.
- A `data.sortable` adoption surface (`has-sortable-lists`) reads lists reordered with `vuedraggable`, `sortablejs`, `vue-draggable-plus` or `@vueuse/integrations` `useSortable` as hand-rolled.

### Upgrading

- Run `kit:update plan|apply` for `core`: new `Kit/Data/sortable.ts`, `SortableList.vue` and `SortableHandle.vue`, and `Kit/Form/Repeater.vue` takes `draggable`.
- Core now requires the npm package `vue-draggable-plus` (`^0.6.1`, MIT). Run `npm install vue-draggable-plus@^0.6.1`; `kit:status` and `kit:preflight` flag it until you do.

## v0.7.20

### Added

- **Anonymise a deleted account instead of deleting it** ([docs/lifecycle-events.md › Keeping the user row](docs/lifecycle-events.md#keeping-the-user-row)). `KIT_ACCOUNT_USER_ROW=anonymise` (`kit.lifecycle.account_user_row`) keeps the user row as a "Deleted user" tombstone, so history on RESTRICT foreign keys survives.
  - Erased: the name and email (`deleted-{id}@deleted.invalid`), the password (a random hash), email verification, the remember-me token, the two-factor columns, passkeys, database sessions, and the avatar and other media on the user (files included). The Concierge account stays deactivated, so it never signs in.
  - Extend `Tey\Kit\Features\SessionUi\Account\AnonymiseAccountRow` and override `attributes()` to scrub your own columns, then bind it to `DeletesAccountRow`. A bound `DeletesAccountRow` wins over the setting.
  - The default stays `delete`, so nothing changes unless you opt in. It becomes `anonymise` in 0.8. An unknown value is a configuration error, and nothing is deleted.

## v0.7.19

### Fixed

- **People runs a fixed number of queries for the owner.** The workspace owner's People page asked Concierge who owns the workspace once per member, to decide where to offer Make owner. It now looks the owner up once per page.
  - `ManagePeople::transferable()` takes an optional owner membership id (from the new `ManagePeople::ownerMembershipId()`) for lists. Single checks leave it out, as before.
  - `composer test:people-suspend` now views People as a real Concierge owner, with Make owner offered, so the bounded-queries pin covers it.

## v0.7.18

### Added

- **Suspend and Reinstate on People.** A member's row offers Suspend while they're active and Reinstate while they're suspended. Each asks you to confirm first, and is offered only to someone who may manage that member under Concierge's rank rules (never on the owner).
  - Suspend posts to `settings/people/members/{id}/suspend` (optional `reason`, at most 500 characters). Their access ends and their roles are kept.
  - Reinstate posts to `.../reinstate`. Access returns with the roles they held.
  - Both go through Concierge's `SuspendMembership` and `ReinstateMembership`, so the rank, owner and removed-member rules stay Concierge's.
  - `composer test:people-suspend` also pins that People's members and invitations tabs run the same number of queries as rows are added.

## v0.7.17

### Added

- **Self-hosted fonts.** `KIT_FONTS_SOURCE=self` (`kit.fonts_source`) serves the font set from the app's own origin instead of fonts.bunny.net, so pages work offline and make no third-party request (README, "Self-hosted fonts").
  - The kit ships every set's faces as woff2 files, in bunny's subsets, with their SIL Open Font Licenses.
  - `php artisan vendor:publish --tag=kit-fonts --force` copies them to `public/vendor/kit/fonts`, and `<x-kit::fonts />` writes `@font-face` rules for the chosen set.
  - Files that haven't been published throw in local and testing; in production the page uses the system fonts, never the CDN, and `kit:preflight` warns.
  - The default stays `cdn`, so nothing changes unless you opt in.

## v0.7.16

### Fixed

- **A floating `FloatingToolbar` no longer paints over an open modal.** Float mode was z-40, the same layer as `@inertiaui/modal`'s container, and came later in the document, so it drew over a modal opened from its own slot.
  - It now floats at z-35: above `StickyBar` (z-30) and under the modal layer (z-40), sheets and the Toaster (z-50).
  - Run `kit:update plan|apply` for `core`; this updates `Kit/Layout/FloatingToolbar.vue`.
  - A host that hid the toolbar while a modal was open can drop that workaround.

## v0.7.15

### Added

- **`<FormLink>`, `submitHiddenForm()` and `useNewTabForm()`** (docs/form-link.md): downloads and new-tab submits sent through a native hidden form built on `<body>`. The form still goes out when the modal it was fired from unmounts.
  - GET, or POST with `_method` for PUT, PATCH and DELETE.
  - Nested data is sent with Laravel field names, and a GET keeps the URL's own query string.
  - `_self` is the default target for downloads; `_blank` opens a new tab and is submitted during the click, so popup blockers don't catch it.
  - `useNewTabForm` validates with an Inertia form first, then opens the result.
- Run `kit:update plan|apply` for `core`: this adds `Kit/Form/FormLink.vue`, `Kit/Form/hiddenForm.ts` and `Kit/Form/useNewTabForm.ts`, and updates `Kit/index.ts` and `Kit/contract.ts`.

## v0.7.14

### Upgrading

- **A page with a "Back to <parent>" strip no longer draws the breadcrumb trail** (`<Page>`'s line, or the `breadcrumbs="bar"` bar): the strip replaces it. This affects account pages (`/me/*`) and any page whose ViewModel returns a `navigationHeader()`. Nothing to change unless such a page relied on showing both.

### Added

- **Breakout pages.** A page that leaves its parent's shared layout (a full-screen bench, an editor) declares it with the route default `->defaults('kit.breakout', true)` or the ViewModel's `breaksOut()`. It then shows "Back to <parent>", derived from the trail's parent crumb, in place of the trail. A page's own `navigationHeader()` still wins.
  - Two mistakes, a breakout with no parent crumb and a breakout inside a settings section, throw in local and testing. In production they draw no strip and keep the trail.

## v0.7.13

### Fixed

- **An upload's limit is the lowest the stack accepts, on the page and in validation.** A note's attachment or an avatar passes the kit's cap (`kit.notes.media.max_kb`, `kit.avatar.max_kb`), PHP's `upload_max_filesize` and `post_max_size`, and medialibrary's `max_file_size`. When PHP's or medialibrary's limit was lower, the page still showed the kit's size, and a file between them failed when it was kept instead of up front.
  - Validation and the Notes composer's `media.max_kb` now use the lowest, so the hint is true and an oversized file is a 422.
  - Direct-to-bucket uploads (`MediaRules`) are unchanged: they never pass through PHP.

### Added

- **`Tey\Kit\Features\Core\UploadLimit`**: `cap($bytes)` and `capKb($kb)` give the effective limit, for a host's own upload rules and hints.
- **`kit:preflight` warns** about each kit upload cap that PHP or medialibrary would cut down, naming the setting to raise. It's a warning: the deploy isn't failed.

## v0.7.12

### Fixed

- **A signed-in person no longer sees "Sign in" on the error page for a URL that matches no route.** A 404 for an unknown URL, or a 405 for a method the URL doesn't take, fails during route matching, before the `web` group runs. So its error page had no session, the shared shell was a guest's, and `PublicShell` offered "Sign in" to someone already signed in. That page is now rendered through the host's `web` group, so the session and the shell know who is there.
  - Left out: the middleware that needs a matched route (`SubstituteBindings`) and the CSRF check. A POST with no token to a URL with no POST route is still its 405, never a 419.
  - Unchanged: a guest still gets the guest shell, a JSON request still gets its JSON error, and API, MCP and OAuth paths are untouched. If the `web` group itself fails, the page renders as before, without a session.

## v0.7.11

### Added

- **`FluentMailMessage::external()`** for mail to someone with no account here (an on-demand recipient, `Notification::route('mail', …)`): the footer leaves out the manage-notifications link, which points at an account page they can't open, and keeps the brand and the sender. It is the same switch as `security()`, so a host's published `message` and `footer` views that take the `manage` prop follow it too.

## v0.7.10

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b69301a2…a839` → `sha256:bc140f10…e266`) and `shell` (`sha256:c7036fbf…8b21` → `sha256:0f6d2811…7602`). Page, Breadcrumbs and the shell's Layout take the changes below; nothing changes until a Layout opts in.

### Added

- **The shell's full-width trail bar, as an opt-in:** `<Layout breadcrumbs="bar">` draws the trail in a bar under the header, the whole trail with the current page as its last crumb (a link to itself, marked `aria-current`, in the foreground colour), as the shell did before v0.7.7. `<Page>` then draws no trail line. The default, `page`, is unchanged. `Breadcrumbs` takes `bar` for a host's own shell.

### Fixed

- **A `<Page>` nested in another no longer draws a second trail.** A record page that wraps each tab's `<Page>` in a tabbed frame's `<Page>` showed the trail twice. The outer `<Page>` draws it and the inner one doesn't. Browser-tested with both trail modes.

### Docs

- **[docs/upgrading-0.5-to-0.7.md](docs/upgrading-0.5-to-0.7.md) starts at v0.5.97:** a new section lists what an app on v0.5.97 to v0.5.115 also checks (the PaneTabs look, `httpSubmit` over `useHttp`, two npm packages, published mail views, and the password on deletion).

## v0.7.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:71423bf5…6132` → `sha256:e7e1aea7…c8ca`), `settings` (`sha256:37ad7378…6eba` → `sha256:698d91e7…25ac`), `session-ui` (`sha256:3ef412e3…ba7e` → `sha256:bfed3000…9810`) and `workspace-ui` (`sha256:866d8158…510c` → `sha256:5fc8bb26…1a41`). The Form controls and Field take the fix below, and the kit's pages (Register included) drop their hard-coded `aria-describedby`. core adds `Kit/Form/fieldAria.ts`.
- **A page of your own** that writes `aria-describedby="<id>-error"` (or `-description`) on a control inside a `<Field>` can drop it. The control now gets those ids from the Field, only while they're rendered. Other ids you name are kept.
- **`TenantBoundary` is deprecated in favour of `Workspaces::run()`** (removed in 0.8). It still works, as a delegate to `Workspaces::run()`. Inside a workspace request (`/@{slug}` or its short form) drop it: `ResolveWorkspace` has already made the workspace current. Anywhere else, pass `Workspaces::run()` the workspace or its id and a block closure (an arrow function returns what `dispatch()` returns, so that job would be queued after the tenant is restored):

  ```php
  // before
  app(TenantBoundary::class)->run($id, fn () => $operation->handle());
  // after
  Workspaces::run($id, function () use ($operation) {
      return $operation->handle();
  });
  ```
- **`kit:capability` takes `--tenant=<id>`, and a change is guarded.** `kit:capability --tenant=<id> [capability] [on|off|clear]` runs in that workspace (repeat `--tenant` for several). The slug as the first argument is a deprecated alias (removed in 0.8) that still selects that one workspace and prints a deprecation line. With neither, the overview lists every workspace under a heading, but a change refuses: pass `--all-tenants` to change every workspace, which states the count and asks first. `--force` skips the question, and a non-interactive run without it refuses.
- **A non-member gets a 404 on a workspace page.** A `/@{slug}` request from someone who is not an active member is refused (`workspace_not_found`, a 404) by `ResolveWorkspace` before any controller runs, on the kit's pages and a host's `WorkspaceRoutes` pages alike. People, its invite and change-role modals and removing a member answered a non-member 403 before. An active member without the permission still gets a 403. An old `/workspaces/{id or slug}/...` address now redirects only a member: anyone else gets a 404 instead of a redirect that named the workspace's slug.
- **Workspace test helpers** (test environment only, not autoloaded into production): `uses(Tey\Kit\Testing\InWorkspace::class)` in a Pest file, or `withCurrentWorkspace()` after `require`-ing `vendor/tey/kit/src/Testing/pest.php` from `tests/Pest.php`, gives each test a fresh current workspace and forgets it after. `withoutCurrentWorkspace()` opts a test out. `CurrentWorkspace::createUsing()` builds the test workspace your own way. For the factory of a `BelongsToWorkspace` model, `use Tey\Kit\Testing\CreatesInWorkspace;`: the workspace column defaults to the current workspace (it throws `workspace_required` with none), and each row is created, with its afterCreating callbacks, inside its own workspace.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e7e1aea7…c8ca` → `sha256:b69301a2…a839`): FloatingToolbar, the Workbench's actions bar and navigator, and useWorkbench take the fixes below.
- **A host write that sends the Workbench's `version`** (New slide, a host-made duplicate) awaits `wb.whenIdle()` first, so it carries the token a reorder's reply set: `await wb.whenIdle(); send({ version: wb.version })`.

### Fixed

- **Concierge's context and the Pennant flush now come only from spatie's switch tasks.** `TenantBoundary` used to set Concierge's context and flush Pennant itself; `Workspaces::run()`, `ResolveWorkspace` and TenantAware jobs and commands rely on `ConciergeContextTask` and `FlushFeatureCacheTask` being listed in `multitenancy.switch_tenant_tasks`. The kit's default config lists both; a host that publishes its own `switch_tenant_tasks` must keep them.
- **Form fields no longer point screen readers at nodes that don't exist, and an error marks its control invalid.** `<Field>` now tells the control inside it which description and error it actually renders. Every Kit/Form control (`Input`, `PasswordInput`, `Textarea`, `InputSelect`, `TimezoneSelect`, `Select`, `InputCheckbox`, `FileInput`, `RadioCardGroup`, `InputRichText`) sets:
  - `aria-describedby` to only those ids, plus any the caller passes, with no attribute when there are none;
  - `aria-invalid="true"` while the field has an error (or the control's `invalid` is set), removed when it clears.

  `InputNumber` renders its own Field and already did both. Tested under Vue's server renderer with real provide/inject (`tests/js/field-aria.test.mjs`, run by the vue-tsc job).
- **A stage FloatingToolbar wider than its stage folds its overflow instead of running past the edge.** In `stage` mode the toolbar's absolute wrapper had no width, so it sized to its content and the pill's `max-w-full` and the item fit never applied (a toolbar 2px wider than its stage just overflowed). It's now capped at the stage's width less the anchor's 8px insets. Browser-tested at 1280 and 900px.
- **A write made while a reorder's reply is on its way no longer gets a stale-write refusal.** The reorder's in-flight flag wasn't reactive or exposed, so a click on another action mid-move sent the old version token. useWorkbench now has a reactive `reordering` and `whenIdle()`; the actions bar and the navigator's row menus are busy while a reorder is in flight, and `createReorder` takes a `busy` callback. Browser-tested with a slow reply.

### Added

- `Workspaces::run(Workspace|int $workspace, Closure $fn)`: runs `$fn` with the workspace as the current tenant through spatie's `execute()`, so the switch tasks run (Concierge's context, the permission team, the Pennant flush), and restores the tenant that was current before, also when `$fn` throws. An unknown id throws `ModelNotFoundException` before anything switches. If `$fn` returns a pending dispatch anyway (a `PendingDispatch`, `PendingChain` or `PendingBatch`, even from a block closure), the job is dispatched inside the workspace, `run()` returns null and one warning per call site names the job; it never throws there, as the job would still be queued while the exception unwinds.
- `Tey\Kit\Testing\InWorkspace`, `CurrentWorkspace`, `CreatesInWorkspace` and `src/Testing/pest.php` (`withCurrentWorkspace()`, `withoutCurrentWorkspace()`).
- `composer test:workspace-run`: `Workspaces::run()` restores the previous tenant on success, on a throw and when nested, and refuses an unknown id; a returned dispatch, chain or batch is queued inside the workspace on a database queue and run there; kit code passes it no arrow functions; the test helpers reset between tests and opt out cleanly; the factory concern refuses with no workspace and creates each row inside its own. Pest itself isn't installed in the kit, so the hooks are called the way Laravel's TestCase and Pest call them.
- `composer test:workspace-tenant`: a workspace request's tenant (current during the request, restored after a return or a throw, seen by Inertia's closure, optional and deferred props, terminating callbacks and queued jobs), a non-member refused before any switch, a TenantAware job with no tenant failing on a database queue, the deprecated `TenantBoundary` delegate, a guard that nothing under `src/` uses it, the `terminatingCallbacks` property guard and its logged fallback, and `kit:capability`'s `--tenant`, slug and `--all-tenants` forms. `composer test:workspace-route-scope`: every route to the 15 workspace controllers that no longer switch tenant themselves runs `ResolveWorkspace` and carries `{workspace}` (or is the short-form twin of one that does), and every `{workspace}` route to a kit controller runs it, bar three named personal and redirect controllers; a route registered without it is flagged. `composer test:workspace-refusal`: People, its two modals and removing a member answer a non-member 404 and an active member without the permission 403.

### Changed

- `ResolveWorkspace` runs the rest of a workspace request inside `Workspaces::run()` and restores the tenant that was current before, even when the request throws. Terminating callbacks the request registers run together with the workspace current again, and the tenant is restored after them even when one throws. This reaches Laravel's protected `Application::$terminatingCallbacks`: `composer test:workspace-tenant` fails if an upgrade renames it, and at runtime a missing property only leaves the callbacks to Laravel (outside the workspace) with one logged warning per process. The kit's workspace controllers no longer switch tenant themselves.
- Code that switches into a workspace other than the request's (the MCP connection binding and authorization view, the shell's and settings nav's per-workspace checks, onboarding, capability changes, moving a record between workspaces, the settings adapters and MCP tools) uses `Workspaces::run()`.
- `kit:capability` uses spatie's `TenantAware` command trait.

- **[docs/upgrading-0.5-to-0.7.md](docs/upgrading-0.5-to-0.7.md) takes in a first app's upgrade (0.5.116 → 0.7.8):** any late 0.5.x to the latest 0.7, the `order` line kit:update prints, how to judge a changed migration before `--accept-changed-migrations`, a host `@kit/ui` barrel, `kit.d.ts` from vendor, the Register page in lint and parity tests, CI timeouts, and the breadcrumbs change.

## v0.7.8

### Added

- **[docs/upgrading-0.5-to-0.7.md](docs/upgrading-0.5-to-0.7.md)**: one pass from v0.5.117 to v0.7.7, now that 0.5.x is frozen. It covers the order of the `--lineage` runs, the 0.6.0 ownership moves, and a checklist of the Upgrading steps that need a person.
### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:dc02fc76…f634` → `sha256:71423bf5…6132`): `Kit/Layout/StickyBar.vue` takes the fix below. Ported from 0.5.x, which is frozen.

### Fixed

- A request no longer queries the database schema to learn whether the workspaces table has `admitted` and `deleting_at`. The kit asked once per request, since PHP-FPM drops `static` memos between requests, and asked again each time a column was missing. A column found is now cached (`kit-workspace-columns`, in the default cache store) until the next `migrate` forgets it. One not found yet is still asked again, so a table migrated later is seen at once.
- **A StickyBar at the viewport's foot counts as pinned, however it got there.** Until now a bar counted as pinned only when it was scrolled to the foot or held there with `pinned`. A host that lays the Workbench out itself (`:fill="false"` in its own full-height column) put the actions bar at the foot without either, so bottom toasts weren't lifted above it. A bar whose bottom is within 12px of the viewport's (room for its container's gap) now counts as pinned, re-checked on scroll, window resize and layout resize.
- **`auth.passkeys` counts the kit's own passkey offer.** The shell's `pages/Workspace/Layout.vue` renders `<PasskeyOffer />` on every signed-in page through `@kit/ui`, but the row counted only a host import of it, so an app using the kit shell read `missing`. The row now also counts the installed Layout; importing the offer again in the host would show it twice.

## v0.7.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e238b630…7408` → `sha256:dc02fc76…f634`) and `shell` (`sha256:93780271…035d` → `sha256:c7036fbf…8b21`). The breadcrumb trail moves from the shell's row into `<Page>`, above the heading (see below). Three things for an app:
  - **Pages without `<Page>` lose their trail**, because the shell no longer draws one. Render `<Breadcrumbs />` (from `@kit/ui`) above the page's own heading.
  - **An edited `Partials/Breadcrumbs.vue`** conflicts on update. The shell's copy is now a thin wrapper around core's `Breadcrumbs`, kept for pages that import it; the trail itself is `Kit/Layout/Breadcrumbs.vue`, which you edit or replace through `@kit/ui`.
  - **Headings move up.** The 40px row under the header is gone, and a 20px trail line plus an 8px gap sits above the heading. So every page's H1 is about 30px higher than before, and at the same height on every page.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:c5b8919e…e94e` → `sha256:d96afd41…1b7f`) and `workspace-ui` (re-pinned `sha256:5d746402…9202` → `sha256:749a17ac…5112`), then `php artisan migrate`: the new `0001_01_02_000505_add_finished_at_to_kit_account_deletions_table` migration, and `Workspace/Settings/Delete/Page.vue` and `Workspace/Account/Delete/Page.vue`, which show the password field while `confirms_password` is true. A host that edited those pages adds the field itself. Clients that call `DELETE /@{workspace}/settings/delete` must now send `password`, unless `kit.lifecycle.confirm_password` is `false`.
- Run `kit:update plan|apply` for `access` (re-pinned `sha256:a179066f…6d98` → `sha256:98af2e26…2de6`) and `settings` (re-pinned `sha256:fb83eb1d…5f1e` → `sha256:37ad7378…6eba`): their `000300` and `000400` migrations now adopt a compatible host table in place. A host that has already run them is unaffected (migrate doesn't re-run them). A host with its own `entitlements`, `complimentary_grants` or spatie settings table should run `kit:adopt --json` (or `kit:add`'s plan) first: an incompatible table now blocks with its columns named instead of failing in `migrate`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:6e87fc26…fe56` → `sha256:e67b1978…efa5`): `kit.css` takes the change below. A host that defined its own `--container-2k` can drop it; its own value still wins if kept.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b7a9b9ba…5e9d` → `sha256:6e87fc26…fe56`): `Kit/Form/Slider.vue` takes the fix below.
- No feature is re-pinned: the mail views and account mail ship in the package. To show the brand's lockup in mail, add a PNG of it for a light background (2x the 220px it shows at, or more) and set `KIT_BRAND_MAIL_LOCKUP` (`kit.brand.mail_lockup`) to its path in `public/` or an absolute file path. Without it, the mail header is the product's name, centred.
- A host that published its own `resources/views/vendor/mail` keeps its views. To leave the manage-notifications link off security mail there too, give its `message` and `footer` views the `manage` prop (see [docs/mail.md › Account mail](docs/mail.md#account-mail)).
- A host that set its own `ResetPassword::toMailUsing()` or `VerifyEmail::toMailUsing()` keeps it, and doesn't get the kit's account mail.
- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:40fdd76c…81c4` → `sha256:56912cc3…eafd`) and `session-ui` (re-pinned `sha256:abbeb463…c64f` → `sha256:a4581c16…377d`), then `php artisan migrate`: the new `0001_01_02_000210_add_deleting_at_to_kit_workspaces_table` (nullable `deleting_at` and `deleting_by` on `workspaces`, recorded as kit-added on an adopted table) and `0001_01_02_000504_create_kit_account_deletions_table` migrations below. Existing workspaces stay open. Nothing changes for a host whose purge handler doesn't opt into deferral.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:a4581c16…377d` → `sha256:c5b8919e…f94e`): `0001_01_02_000501_add_user_foreign_key_to_kit_security_events_table.php` and `0001_01_02_000503_add_user_foreign_key_to_kit_user_state_table.php` take the type fix below. It's a type-only change, so there's nothing to migrate: a host that already ran them keeps its schema, and `kit:update plan`'s note that a changed migration won't reach the database needs no action here.
- Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:2f226d38…39e7` → `sha256:5d746402…9202`): `Workspace/Placeholder/Page.vue`, `Workspace/Settings/Placeholder/Page.vue` and `Workspace/Settings/People/Page.vue` take the type fix below. No behaviour changes.
- The capability fix below is in the package and re-pins nothing. A host that adopted `settings` only so People would open, or so owners could manage others' MCP connections, no longer needs it for that. Keep it if you use its General section or its MCP settings tools, and for the AI assistants page while `kit.mcp.connection_ability` is the default `settings.read` (`kit:status` flags this).
- The kit now refuses to boot when an ability is gated on a capability no adopted feature declares, naming the ability, the capability and the feature. A host whose own provider calls `Registry::abilities()` keeps booting: the check covers what the kit's features contribute.
- `kit:status` now flags declared permissions missing from the database as a blocking step. `php artisan migrate --force` puts them back.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e67b1978…efa5` → `sha256:145cf85d…412e`), `mcp` (re-pinned `sha256:e35e2879…7ae0` → `sha256:760d2e0a…d924`), `workspace-ui` (re-pinned `sha256:5d746402…9202` → `sha256:0e1a0d0e…3320`) and `notes` (re-pinned `sha256:1a0455e7…551b` → `sha256:cc6e2adb…bb95`): `DataTable.vue`, `ActivityEntryRow.vue`, the Connections, People and Security pages and `NoteCard.vue` take the table and date fixes below. A host that copies `types/kit.d.ts` re-copies it: `TableColumn` has `date`. Nothing to migrate.

### Tests

- The resources check (`composer test:resources`) no longer copies `vendor`, `node_modules`, `browser` or `typecheck` into each disposable package copy. It reads only `src` and `resources`. All 15 tamper cases still run, and the check goes from about 50s to about 6s locally. CI gives each test step its own `timeout-minutes` (4 for the composer checks, 6 for the browser run), so a hang fails its own step quickly instead of holding the job until its 10 to 15 minute limit.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:c5b8919e…f94e` → `sha256:fae5ea29…bcb6`): this adds `Auth/Register/Page.vue`; hosts with their own register page can drop it. `Auth/Login/Page.vue` takes the "Create an account" link below. A host that copies `types/kit.d.ts` re-copies it: `LoginViewModel` has `register`, and `RegisterViewModel` is new. Registration stays closed by default, so nothing changes until an app opens it.

### Added

- **Deleting a workspace asks for your password** ([docs/lifecycle-events.md › Delete a workspace](docs/lifecycle-events.md#delete-a-workspace)). Settings › Delete workspace now requires the current `password` with the typed name. A missing or wrong one is a 422 and nothing changes. Account deletion already asked for it. Both are on by default. `kit.lifecycle.confirm_password` (`KIT_DELETE_CONFIRM_PASSWORD`) turns them off, but only when it is `false` exactly: any other value keeps the password required.
- **A host can keep the user row when an account is deleted** ([docs/lifecycle-events.md › Keeping the user row](docs/lifecycle-events.md#keeping-the-user-row)). Bind `Tey\Kit\Features\SessionUi\Contracts\DeletesAccountRow::delete(Model $user)` to anonymise the row instead of deleting it, for tables that keep history on RESTRICT foreign keys to users. Unbound, the user is hard-deleted as before. After the kit's rows go, an anonymised row keeps its Concierge account deactivated, so it never signs in. Its `kit_account_deletions` record is marked `finished_at`, and `FinishAccountDeletion` returns `false` on a retry instead of failing.
- **Adopt-in-place for the access and settings tables.** A host's own `entitlements` and `complimentary_grants` tables, and spatie/laravel-settings' table, are adopted when compatible (the columns the kit reads and writes in a compatible type, nullable where the kit writes null, an auto-incrementing `id`, the kit's unique index, and no other required host column on a table the kit inserts into): nothing is created or changed on them, and each is recorded in `kit_adoptions`. Settings then creates only `settings_intents`. Plans list each under `adoptions`, and `kit:adopt --json` with no feature lists each such table under `tables` as `adopted`, `adoptable` or `conflict`. See [Adopting existing access and settings tables](README.md#adopting-existing-access-and-settings-tables). `composer test:table-adopt` covers it on SQLite and PostgreSQL.
- **`max-w-2k` (160rem)**, the wide page measure the apps share, as a `--container-2k` token in `kit.css` under `@theme default`, so a host's own value wins.
- **/mcp storm guards** (`kit.mcp.limits`, `kit.mcp.list_cache_seconds`). An assistant that reconnects in a burst no longer fills every PHP worker. Each access token may have 4 requests in flight and 120 a minute; over either, the request gets a 429 with `Retry-After` (a JSON-RPC error body) before it does any work. An app-wide in-flight cap (`total`) is off by default: set it below your PHP worker count. Slots live in the cache store and free when the request ends, or after `hold_seconds` if the worker died. `tools/list` answers are reused per connection for 60 seconds; a call still checks its tool's gate, so a list stale by a permission change only lists. A host that published `config/kit.php` gets these defaults without adding the keys. `composer test:mcp-limits` covers both.

- **Deferred workspace and account deletion** ([docs/lifecycle-events.md › Deferred deletion](docs/lifecycle-events.md#deferred-deletion)). A purge handler that also implements `Tey\Kit\Features\Workspaces\Contracts\DefersWorkspacePurge` (or `Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge`) is no longer run inside the request's transaction. The kit closes the workspace at once (`deleting_at`), dispatches `WorkspaceDeleting`, writes a `workspace.deletion_started` audit row, and after the commit calls the handler's `begin()` to queue the host's job. That job purges in its own chunks and ends with `app(FinishWorkspaceDeletion::class)($workspaceId)`, which deletes the kit's and Concierge's rows in one short transaction and dispatches `WorkspaceDeleted`. The hook is idempotent (a second call returns false) and refuses a workspace that isn't being deleted (`workspace_not_deleting`). A closed workspace refuses every member's admission (`workspace_deleting`, 403), takes no invitation or people change, is hidden from the switcher and lists, and sends its members from its pages to the workspace list with a "being deleted" notice. It's never reopened. Accounts work the same way: the account is closed at once (workspaces left, Concierge account deactivated so sign-in is refused, database sessions ended, Passport tokens revoked and Sanctum tokens deleted), then `app(FinishAccountDeletion::class)($userId)` deletes it. The synchronous mode stays the default and is unchanged. `composer test:lifecycle-pages` covers both modes.
- **A Register page** (`Auth/Register`, session-ui; [README › session-ui](README.md)). While an app turns on Fortify's `registration` feature and Concierge's registration mode isn't closed, `/register` renders it (Fortify's `registerView`) and posts to Fortify, so the account is still the app's `CreatesNewUsers` action. It matches the other guest cards: `novalidate` with field errors in the card, name focused on load, FormStatus, both passwords cleared after every attempt, and a 429, an expired session (419) or a network failure shown in the card instead of Inertia's error modal. Sign in shows a "Create an account" link in Concierge's open mode, and Register links back to sign in. An app's own sign-up (an invited account, say) renders the same page from its own route with a `RegisterViewModel` of its own, so its copy stays the kit's: `action`, `heading`, `lead` and `submitLabel`, an address shown as text instead of the email input (`email`, `emailHint`), values posted that aren't inputs (`submit`), and its own 429 copy (`throttleMessage`). An error on a key with no input (`invitation`, or `email` shown as text) is one alert at the top of the card. `composer test:register` covers the page, its props, the gating, a registration and its validation errors; the browser job covers the card on the real `@inertiajs/vue3`; `bin/typecheck-components.sh` type-checks it on Inertia 3.7.1 and current.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b7a9b9ba…5e9d` → `sha256:a8184211…bf3e`): `Kit/Form/submitForm.ts`, `Kit/Form/formFrame.ts`, `Kit/Form/FormActions.vue`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/workbench.ts` take the Save gate and the status fix below, and the kit's `ui` lang file gains `form.submit_blocked.*`. A host that disabled Save by hand during an upload, or watched its form to clear "Not saved", can drop that.

### Added

- **Save is disabled while it can't succeed** ([docs/submit-form.md › When Save is disabled](docs/submit-form.md#when-save-is-disabled)). `FormActions` and a Workbench with `:form` disable Save, and ignore Enter and ⌘S, while pending work is in flight (an upload), a 409 stale-write conflict is open, or the host's `canSubmit` is false. `canSubmit` is a new prop on both (`can-submit`) and a `useSubmitForm` option, a boolean, `Ref` or getter, `true` by default; while it's false `submit()` resolves `false` without calling the handler. The button carries `data-blocked` and an `aria-describedby` with the reason, from `kit::ui` `form.submit_blocked.*` ("Waiting for uploads to finish"). `submitBlock()` and `submitBlockMessages()` are exported. `tests/js/submit-form.test.mjs` and `workbench-form.test.mjs` cover each reason; the browser job checks a pending upload, `can-submit` false and an open 409 on the Workbench.

### Changed

- **The breadcrumb trail is a small line above the page's heading, not a row in the shell** (as GOV.UK and Polaris place it). `<Page>` draws it in the shell: the page's ancestors, with the current page left out because its heading names it. It's drawn whether or not the page has a heading.
  - **Home page.** With no ancestors, the line keeps its 20px as an `aria-hidden` spacer, never an empty landmark, so the heading sits at the same height on every page. Measured in a headless render: the H1 is at 113px at 1280 wide and 109px at 390, on both the dashboard and Settings › People.
  - **Pages without `<Page>`.** `Breadcrumbs` is exported from `@kit/ui` (with a `crumbs` prop) for pages that don't use `<Page>`.
- The web footer's docs now match the brand standard (2026-10-29): a © line is optional and never flagged. The accepted form is `© {year} {Product} · Built by Tey Labs Inc.`; kit's default footer still adds none. No behaviour change.
- **The mail header is the brand lockup, centred** ([docs/mail.md › The header: the brand lockup](docs/mail.md#the-header-the-brand-lockup)). The branded layout is now Laravel's base markdown layout with the lockup image centred above the message, linked to `app.url`: 220px wide, its height kept to the image's ratio, and the product's name as alt text. It's a local PNG embedded inline as a CID attachment, never a remote image. With no lockup, a missing file or a non-PNG, the header is the product's name, centred, and never a broken image. The left-aligned mark, bold name and accent rule are gone. Buttons and links keep the brand accent. `kit:status` reports a configured lockup that isn't a PNG as attention, not blocking.
- **Password reset and email check mail are the kit's** ([docs/mail.md › Account mail](docs/mail.md#account-mail)). With session-ui, Fortify's reset and verification mail use the kit's copy instead of Laravel's stock "Hello!", with sentence-case buttons ("Reset password", "Verify email address"). As security mail, they have no "Manage notifications" link. `FluentMailMessage::security()` does the same for a host's own security mail.

### Fixed

- **An existing access or settings table is never created over.** A plan meeting an incompatible one blocks with a conflict naming the missing, mistyped or non-nullable columns, the host's required columns and the missing unique index, and the migration refuses it before creating anything. A table whose schema can't be read is a refusal, not a create. Before, an existing `complimentary_grants` or `entitlements` was a bare collision, and a host's spatie settings table wasn't detected at all, so `migrate` failed on it.
- **DataTable sits on the card surface.** The table and its two empty states had no background, so rows showed the page ground. They now use `bg-card text-card-foreground`, the sticky first column matches, and column headers no longer wrap ("Connected by").
- **Dates show through DateTime, not as raw ISO values.** A `TableColumn` (and `TableQuery::column()`) takes `date`, a DateTime format, and DataTable renders that column through `DateTime`: the viewer's zone, the full value in `title`. AI assistants' Connected (a raw `2026-…T…+00:00`) and Expires, People's joined, sent and expiry dates, Security's sessions and log, Notes and activity entries now all use it.
- **Removing one MCP grant is no longer styled destructive.** On AI assistants only Revoke stays destructive; "Remove <grant>" is a plain row action.
- **Avatar upload and removal work on PostgreSQL with string or uuid morphs.** Avatars compared `media.model_id` (varchar there) with an integer, a 500 ("operator does not exist: character varying = integer"). The lookups and the avatar holder's media relation now bind the id as a string.
- **People opens, and owners manage MCP connections, without the `settings` feature.** workspace-ui gates `members.manage` and mcp gates managing connections on `workspace.settings`, but only `settings` declared that capability. A host without `settings` never defined it, so it read as off: People was a 404 for every owner, and so was managing another member's MCP connection, whatever `kit.capabilities` said. workspace-ui and mcp now declare `workspace.settings` themselves. A capability declared by more than one feature is kept once.
- **Workspace invites offer member roles only.** People, its Invite modal and Change role never offer a scoped Concierge role (such as `guest`), even when a host's `GrantPolicy` lists one, and an invite or role change posted with a scoped role is refused (403, `role_not_grantable`).
- **A missing declared permission comes back on the next migrate.** The deploy sync skipped whenever the catalog fingerprint was unchanged, even if the database had lost a declared permission. It now syncs again while any declared permission is missing.
- **`Slider` type-checks under vue-tsc.** `:aria-valuetext` tested the `valueText` function prop itself, which TypeScript reports as always true (TS2774) and fails a host's `vue-tsc`; it now tests whether the prop was given.
- **A failed save no longer stays "Not saved" after further edits** ([docs/workbench.md › Form frame](docs/workbench.md#form-frame)). A Workbench with `:form` whose form was already dirty kept "Not saved" through every later edit. The next edit now puts the bar back to "Unsaved changes" (`editedSaveState`), for a `useSubmitForm` and a `useForm` alike. The browser job checks both.
- **Security: asking for a reset link no longer tells whether an account exists** ([docs/onboarding.md › Password reset](docs/onboarding.md#password-reset)). Fortify answered an unknown address (or a throttled one) with an `email` error, a 422 for JSON, so the form could test addresses. session-ui now answers both of Fortify's reset-link responses the same: back with the `status` flash `kit::ui.auth.reset_link_sent` ("If an account exists for that email, we've sent a password reset link."), or `200 {"message"}` for JSON. A malformed address is still a 422 and throttling a 429. Only Fortify's defaults are replaced, so a host's own binding still wins; a host with its own neutral binding can drop it. `composer test:onboarding` checks an existing account, an unknown one and a well-formed address nobody has get byte-identical redirects, flashes and JSON.
- **Mail links are https on an https app.** The manage-notifications link and the password reset link were built from the current request, so behind a TLS-terminating proxy they could read `http://`. They're now built on `app.url`.
- **session-ui's user foreign key migrations pass a host's PHPStan.** `000501` and `000503` typed `user()` as a bare `array` (`missingType.iterableValue`) and built the user model with `new (config(...))`, an `object` without `getTable()` or `getKeyName()` (`method.notFound`): six errors at level 7 in a host that analyses `database/`. `user()` now returns `array{string, string}`, and the configured class is read as `class-string<Model>`. The model is built and read exactly as before. A new `composer test:phpstan-stubs` (`bin/phpstan-stubs.sh`, a CI step) analyses every PHP stub, with `.stub` dropped, at PHPStan level 8 against the kit's vendor.
- **workspace-ui's pages type-check on vue-tsc 2.** The Placeholder pages and Settings › People cast to an inline object type in the template (`as { label: string; url: string }[]`), which vue-tsc 2 can't parse (TS1005). Each page now names the type in `<script setup>` and casts to that name. The components type-check (`bin/typecheck-components.sh`, CI's vue-tsc job) now covers every Vue and TypeScript file a feature installs into `resources/js` (each `feature.json` `files` entry, at its installed path), not only core's components, the shell and the guest pages, and runs on vue-tsc 2.2.12 (installed as `vue-tsc-2`) as well as 3, each against Inertia 3.9.1 and 3.7.1.

## v0.7.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:5b4af9a6…b7df` → `sha256:b7a9b9ba…5e9d`): this adds `Kit/Form/pendingWork.ts`, and `Kit/Form/formFrame.ts`, `Kit/Form/FormActions.vue`, `Kit/Form/BulkDrop.vue`, `Kit/Workbench/Workbench.vue` and `Kit/index.ts` take the leave-guard changes below. A host with a hand-rolled Back/Forward or pending-upload guard beside a kit form frame can drop it, or keep it with `warnOnLeave: false`.

### Added

- **Pending work counts as unsaved** ([docs/submit-form.md › Pending work](docs/submit-form.md#pending-work)). `usePendingWork()` is the page's registry of work the form doesn't see yet: `track(promise)` holds it until it settles, `begin()` returns an idempotent `end()`, and `pending` and `count` are readable. While any is registered, the leave guard of `FormActions`, `FormCard` and a Workbench with `:form` treats the page as unsaved, even with a clean form. BulkDrop's `direct` uploads register themselves. `createPendingWork()` makes a separate registry. Both are exported from the kit barrel. `tests/js/pending-work.test.mjs` covers the registry.

### Fixed

- **The leave guard holds Back and Forward** ([docs/submit-form.md › Leaving the page](docs/submit-form.md#leaving-the-page)). It caught only Inertia GET visits and closing the tab, so Back from a dirty form left it without asking. Where the browser has the Navigation API, a same-document traverse is now cancelled and asked about, and made again on Leave. Elsewhere the guard keeps one extra history entry for the page while it's unsaved, so Back lands on the page itself and asks; the entry is taken off when the page is clean, when a save starts and before a confirmed visit, so no duplicate entry is left behind. Stay keeps the page and its edits; Leave always goes.
- **A save's own follow-up visit is no longer held.** The guard let a visit through only in the same task as the form's submit event, so a `router.visit()` in `onSuccess`, where Inertia's `useForm()` is still dirty, asked "Leave this page?" after a successful save. The guard is now saving from the submit (or `processing` turning on) until `processing` is off and every visit started meanwhile has finished. The browser job checks all of this on the real `@inertiajs/vue3` (`leave.html`): Back dirty and clean, with and without `window.navigation`, an upload in flight, and a save whose `onSuccess` visits another page.

## v0.7.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9a290690…555b` → `sha256:bda1ecaa…c43e`) and `session-ui` (`sha256:c59375ef…dd74` → `sha256:287b46fe…d2ed`): `Kit/Form/PasswordInput.vue` follows its form's reset, and `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` take the two fixes below.
- **tey/standards ^0.11.54 is required.** Earlier releases reject the adoption inventory's new `features.<id>.composer` key (exit 2), so kit raises its floor with it.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:bda1ecaa…c43e` → `sha256:87f1548e…8cde`): `Kit/Form/submitForm.ts` adds `httpSubmit`, and `Kit/index.ts` exports it. A handler that awaits Inertia's `useHttp` directly should move to `httpSubmit` (below).
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:87f1548e…8cde` → `sha256:5b4af9a6…b7df`): `Kit/Form/Slider.vue` is new, `Kit/Form/RadioCardGroup.vue` gains the `option` slot, image tiles and `columns`, and `Kit/contract.ts` and `Kit/index.ts` export them. `RadioCardOption` now needs a `label` or an `image` (with `alt`); an option with a label is unchanged.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:287b46fe…d2ed` → `sha256:abbeb463…c64f`): `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` take the type fix below. No behaviour changes.

### Fixed

- **A rate-limited auth submit no longer opens Inertia's error modal.** The guest auth pages handed their 429 handler to `<Form>` as `@http-exception`, but `<Form>` before Inertia 3.8 has no `onHttpException` prop: the listener was never called, so Laravel's 429 page (from `throttle:`) still opened in Inertia's modal. The pages now pass it as the visit option (`:options="{ onHttpException }"`), which every Inertia 3 `<Form>` honours, and the in-card "Too many requests" alert shows. The browser checks now run these pages on the real `@inertiajs/vue3` (3.7.1) and shadcn-vue Input, answering each submit with Laravel's 429 HTML or an Inertia response with errors.
- **A failed sign-in, reset or confirmation really clears the password.** `reset-on-error` resets the input in the DOM only, and `PasswordInput` kept its own value, so the next re-render (showing the password, an error appearing) wrote the old password back. The pages now hold their password fields (`v-model`) and clear them on an error or a 429, and `PasswordInput` follows its form's `reset` event (a native reset, or Inertia's whole-form reset), so a host form's reset clears it too.
- **The adoption inventory lists each feature's composer dependencies.** `resources/adoption/surfaces.json` `features.<id>.composer` now carries every feature's `feature.json` `requires.packages` keys (less `php` and `laravel/framework`), and the schema allows the key. tey/standards read `spatie/laravel-multitenancy` in an app's composer.json as a hand-rolled `workspaces.tenancy` signal, because kit carries it only in `require-dev`; tey/standards 0.11.54 exempts a package an installed feature declares here. The drift test rebuilds the list from the feature.json files.
- **A `useSubmitForm` handler over Inertia's `useHttp` no longer reads a 422 as a success.** `useHttp` doesn't reject on a validation failure: it calls `onError` and resolves `undefined`, so a handler that awaited it returned normally and the form showed Saved for a refused save. The docs said a `useHttp` error is read from what it throws, which is only true of the other statuses; they now say to use `httpSubmit` ([docs/submit-form.md › With Inertia's useHttp](docs/submit-form.md#with-inertias-usehttp)).
- **The guest auth pages type-check on every Inertia 3.** They bound `:options="{ onHttpException: tooMany }"` inline, and `<Form>`'s `options` type (`FormComponentOptions`, a `Pick` of `VisitOptions` without `onHttpException`) failed vue-tsc's excess-property check (TS2353) in a host. Each page now types the handler as the visit's `onHttpException` and widens it to `<Form>`'s `options` type (`:options="formOptions"`), both read off `@inertiajs/vue3`'s own `Form` and `router.visit`, so no stub imports `@inertiajs/core`; at runtime `<Form>` still spreads it into the visit. The components type-check (`bin/typecheck-components.sh`, CI's vue-tsc job) now covers these five pages and runs a second time against `@inertiajs/vue3` 3.7.1 (installed as `@inertiajs/vue3-37`, `typecheck/tsconfig.inertia-3.7.json`) as well as the pinned current version.

### Added

- **`httpSubmit((data, hooks) => request)`**, a `useSubmitForm` handler over a `useHttp` request: pass it the hooks it gives you (`http.post(url, hooks)`). A 422 rejects with a `SubmitError` of the field errors; a 409 `stale_write` rejects as the conflict (`form.conflict`); any other status, a network failure, and a resolve of `undefined` with no `onError` reject with the generic message. Only a 2xx resolves, with the response data. Exported from the kit barrel. `node --test tests/js/submit-form.test.mjs` covers each outcome over a stand-in for `useHttp` 3.9.1, including a bare `useHttp` handler that reads a 422 as success.
- **`<RadioCardGroup>` tiles** ([docs/radio-card.md](docs/radio-card.md)). The `option` slot (`{ option, checked, disabled }`) replaces a card's body while the native radio, its label and the focus ring stay the kit's. An option with `image: { src, alt }` is an image tile: a lazy, async image in a square `object-cover` box, so ~100 tiles load without layout shift, named by its label or its image's `alt`. A checked image or slot tile shows the primary ring and an `aria-hidden` check mark, so the state isn't colour alone. `columns` (`2`, `3`, `4`, `6` or `auto`, at least 7rem a tile) lays the cards in a grid. A disabled option stays a native disabled radio the arrow keys skip.
- **`<Slider>`** ([docs/slider.md](docs/slider.md)), a native range input: `v-model` number, `min`, `max`, `step`, `disabled`, `invalid`, `valueText` for `aria-valuetext`, `input` emitted live and `change` on release, and `show-value` for a visible `<output>` linked by `for`. Caller attributes land on the input; in a Field it is described by the Field's description and error; in a locked LockedForm it is read-only.
- **The adoption inventory counts `Slider`** under `form.inputs`, and a raw `<input type="range">` in a Vue or Blade template as hand-rolled (it also marks the app `has-forms`).

## v0.7.4

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:592e21c4…7353` → `sha256:c59375ef…dd74`): `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` take the auth-state fixes below. `ResetPasswordViewModel` gains `status`; a host that hand-copies `types/kit.d.ts` copies it again. A host overriding `lang/vendor/kit/{locale}/ui.php` can add `form.throttled` and `form.throttled_retry`.
- **`kit.people.hide_grants` needs `tey/concierge` 0.9.3 or later**, for `MembersIndex::scopeMembersUsing()`. With no hidden grants nothing changes on any Concierge the workspaces feature allows (`^0.8.4 || ^0.9`); with some on an older one, the app refuses to boot ("kit.people.hide_grants needs tey/concierge 0.9.3 or later"), never a page. The kit's own checks now resolve Concierge `^0.8.4 || ^0.9` (was ^0.8.4), the range the workspaces feature already allows. No stub changes, no re-pin.

### Added

- **`kit.people.hide_grants`**: a list of Concierge grants (roles) whose holders the people lists leave out, such as a support role ([docs/dashboard.md › Hiding people by grant](docs/dashboard.md#hiding-people-by-grant)). A member holding one of them in the workspace is absent from Settings › People (rows and the Members count alike), the People widget and people search, and their own Change role and Transfer ownership pages, role change and removal by id are a 404. The default is `[]`; a value that isn't a list of names is refused at boot. `composer test:people-hide-grants` (new, in CI) covers each surface.

### Changed

- **The auth forms validate in the card, not in the browser.** `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword` and `Auth/ConfirmPassword` carry `novalidate`, so the server's field errors render in the card (an address like "nobody" gets the "valid email address" error) instead of the browser's own bubble. The fields keep `type="email"`, `required` and `autocomplete`.

### Fixed

- **The guest auth pages keep a rate-limited submit in the card.** A 429 from a limiter (Fortify's login limiter, the verification resend, a reset or reset-link request) opened Inertia's error modal over the page. `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` now refuse the modal for a 429 only (`onHttpException` returns false), clear the stale field errors and show an in-card alert (`role="alert"`): "Too many requests. Try again in N seconds." when the response carries `Retry-After`, else "Too many requests. Try again in a moment." The copy is in the kit's `ui` lang file (`form.throttled`, `form.throttled_retry`). Any other HTTP error still gets Inertia's modal; nothing the server allows or limits changes.
- **A failed sign-in, reset or confirmation clears the password and marks the field.** `Auth/Login`, `Auth/ResetPassword` and `Auth/ConfirmPassword` reset their password fields on error, set `aria-invalid` on the fields with an error, and move focus to the first one (the email after bad credentials).
- **A bad or expired reset token is visible.** Fortify reports it on `email`, a hidden field on `Auth/ResetPassword`, so nothing showed. It now shows as an in-card alert with a "Request a new link" link to `/forgot-password`.
- **The reset-password page's status comes from its view model.** `ResetPasswordViewModel` takes `status` (the flashed session status), so the `FormStatus` on that page shows the server's status without the host supplying it.
- **The sign-in page focuses the email field on load**, as `Auth/ConfirmPassword` already focuses the password.

## v0.7.3

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:909d632f…822e` → `sha256:9a290690…555b`): `feature.json` adds `class-variance-authority` and `tw-animate-css` to `requires.npm`. No stub changes.
- The dev floor for tey/standards is now `^0.11.52`, the first release that reads an inventory's `features` block.

### Added

- **CI guards the adoption inventory's completeness** (contributor-facing). `composer test:adoption-completeness` (`tests/AdoptionCompletenessCheck.php`, in the same CI job as the other adoption checks) fails when a page, layout or shell component a feature installs under `resources/js/pages` is named by no `surfaces.json` row's `uses.files`, `uses.components` or `uses.imports` and has no entry in the new `resources/adoption/internal.json`, and when a row with `handRolled` signals has no adoption fixture expecting it `hand-rolled` (or an `unproven` entry there). Stale `internal.json` entries fail too, and every entry needs a reason. Rows `adopted` in no fixture are listed as a note. To pass, `uses.files` now also names the shell partials (`UserMenu`, `WorkspaceSwitcher`, `Breadcrumbs`, `GlobalSearch`, `UserAvatar`, `Wordmark`, `FocusShell` under `shell.workspace`; `GuestShell` and `Public/Markdown` under `shell.public`), the remaining auth screens (`ResetPassword`, `ConfirmPassword`, `VerifyEmail`), the dashboard partials, `Sections/Index`, `Search`, the placeholder, error and no-workspace pages (`workspace.pages`), `Settings/Delete` (`workspace.settings`) and the People pages and invitation page (`access.members`); `internal.json` lists the sample notes pages and the welcome steps. New fixtures pin a hand-rolled outcome for the 19 rows that had none: `public-shell-hand-rolled`, `split-hand-rolled`, `search-hand-rolled`, `action-bar-hand-rolled`, `menu-hand-rolled`, `modal-hand-rolled`, `rich-text-hand-rolled`, `list-hand-rolled`, `badge-hand-rolled`, `avatar-hand-rolled`, `alert-hand-rolled`, `activity-hand-rolled`, `timezone-hand-rolled`, `auth-hand-rolled`, `passkeys-hand-rolled`, `teams-hand-rolled` (`workspace.pages`, `access.members`, `workspaces.tenancy`) and `mcp-hand-rolled`. No stub changes, no re-pin.

### Changed

- **A workspace without the dashboard opens its first navigation page.** With `workspace.dashboard` off, the workspace root (`/@{workspace}`, and `/` in the short form) was a 404. It now redirects (302) to the first primary navigation page the viewer may open, or to `/me/workspaces` when there is none. At the kit's home, a person with several workspaces still goes to the root of the one they last used, so with the dashboard off they land on its first page instead of the workspace list ([docs/dashboard.md › Without a dashboard](docs/dashboard.md#without-a-dashboard)). Give your page `order` < -10 to make it first. Only the capability being off redirects: a role that lacks `dashboard.read`, a non-member and a JSON client are refused as before. An accepted invitation's landing uses the same lookup, unchanged. No stub changes, no re-pin. `composer test:workspace-root` (new, in CI) covers it; `test:capability-guard` now expects the root's redirect.

### Fixed

- **standards:adoption no longer reads the kit's own dependencies and host UI as gaps.** `resources/adoption/surfaces.json` now carries a top-level `features` object: for each feature with `requires.npm` or `requires.host_ui`, its npm package names and its host UI primitives, exactly as its `feature.json` lists them. tey/standards 0.11.52 treats those packages as the kit's (never a package.json gap or a third-party import) and those `components/ui` files as kit-owned (never an app component, gap or hand-rolled hit) for each installed feature. The schema allows the field, and the inventory test fails when it drifts from the `feature.json` files.
- **core declares every npm package its components need.** `Kit/Feedback/alertVariants.ts` (like the shadcn `button` it pairs with) imports `class-variance-authority`, and the kit's menus, toasts and select animate with `tw-animate-css` utilities (`animate-in`, `fade-in-0`, `zoom-in-95`); neither was in `requires.npm`, so a host without them got no plan attention for it. Both are now required, at `^0.7.1` and `^1.4.0`.

- **The adoption inventory reads hand-rolled account and settings pages wherever they live.** `account.pages` and `workspace.settings` applied only when a route URI said `settings`, so an app serving its own account page at `/account`, or workspace settings at `/w/{workspace}/deletion`, read `n/a`. Two facts computed from file names close that: `has-account-pages` (pages under `Account/`, `Me/`, `Profile/`, `Security/`, `Preferences/`, `Settings/{Account,Profile,Security,Password,Preferences}/`, and `*AccountSettings*`, `*ProfileSettings*`, `*UserSettings*`, `*UserPreferences*` pages) and `has-workspace-settings-pages` (`Teams/Show.vue`, `Team*/Settings/`, `Workspace*/Edit`, `Workspace*/Deletion/`, `Organisation`/`Organization` settings, `Settings/Workspace*`, `Settings/Team*`, and `*TeamSettings*`, `*WorkspaceSettings*`, `*OrganizationSettings*` pages). Both read inside tey/mod module page folders too, and never flag the kit's own `Workspace/Account/**` and `Workspace/Settings/**` pages or `components/ui/**`. A domain component such as `SlideSettings.vue` matches none of them. `account.pages` also counts the kit's Password and Delete pages as adoption.
- **New surfaces: `settings.frame` and `mcp.connections`.** `settings.frame` is the account and workspace settings frame (`Workspace/Account/Layout.vue`, `Workspace/Settings/Layout.vue`); an app's own `Account/Layout.vue`, `Settings/Layout.vue`, `Settings/Index` or `layouts/settings/**` reads hand-rolled. `mcp.connections` is the AI assistants page (`Workspace/Settings/Connections/Page.vue`); `*Connection*`, `*ConnectedApp*`, `*Assistant*` and `*Token*` pages under `Settings/`, `Account/`, `Profile/` or `Me/`, `ConnectedApp*/`, `ApiTokens/` and Jetstream's `API/` pages, and an app controller, view model, action or Livewire class referencing Passport's `Token`/`TokenRepository` or Sanctum's `PersonalAccessToken` read hand-rolled.
- **`platform.timezone` flags an app's own timezone picker.** A `*Timezone*.vue` outside the kit's components and Welcome timezone pages reads hand-rolled. The kit ships no passkey management page (only the `PasskeyOffer`), so `auth.passkeys` is unchanged.

The fixtures `account-hand-rolled`, `account-kit-installed` and `slide-settings-domain` pin these outcomes. No stub changes, no re-pin.

## v0.7.2

### Changed

- **A person with several workspaces lands on the one they last used.** The kit's home (`kit.home`) sends a signed-in person with several workspaces to the workspace they last opened (the session's current one), while they are still an active member and may open its dashboard; with none, or a stale one (removed, suspended, not admitted, deleted), it is the workspace list as before. For a signed-in person the logo (`shell.product.url`) and `shell.links.home` now link to `kit.home` too, not `/me/workspaces`; the switcher and a guest's shell are unchanged. One workspace still shows its dashboard there, and a guest still signs in first. Sign-in lands on `kit.home` with workspace-ui too: the kit's `fortify.home` default is now `kit.home` with or without workspace-ui (it was `/workspaces`, the list). A host with its own `config/fortify.php` keeps its `home`; set it to your `kit.home` (`/` by default) to land on the last workspace. No stub changes, no re-pin.

## v0.7.1

### Upgrading

- `StartKitRequest` moved from `Tey\Kit\Features\SessionUi\Http\Middleware` to `Tey\Kit\Features\Core\Http\Middleware`, and core now registers it (see Fixed). The old name is a deprecated alias of the new class, to be removed in a later release: a host that names it (a route's `withoutMiddleware`) keeps working, but should switch to the new namespace. No stub changes, no re-pin.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:6537c1e1…3fa7` → `sha256:909d632f…822e`): `Kit/Form/submitForm.ts` and `Kit/Form/formFrame.ts` are new, and `Kit/Form/FormActions.vue`, `Kit/Form/FormCard.vue`, `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchActions.vue`, `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/workbench.ts`, `Kit/contract.ts` and `Kit/index.ts` change for `useSubmitForm` and the Workbench's `:form` (Added). Nothing changes for a page that doesn't use them: a Workbench without `:form` renders as before.
- **A stale write on an Inertia request is now a JSON 409, not the error page** (Changed). A host whose page or test expected the `Workspace/Error` page (or its own error page) when `updateIfCurrent()` refused a stale save from an Inertia visit now gets `{"error":"stale_write"}` with status 409, which Inertia hands to `onHttpException`. Handle it there (the Workbench's `:form` and `useSubmitForm` already do), or update the test. Every other error code still renders the error page.

### Added

- **`useSubmitForm(initial, handler, options?)`, a form saved by the app's own handler** ([docs/submit-form.md](docs/submit-form.md)): a `fetch`, `useHttp` or a socket, never an Inertia visit. It returns what `FormActions` reads from a `useForm()`, plus `data`, `errors`, `submit()`, `setDefaults()` and `conflict`. A `SubmitError`, a thrown fetch `Response` or `useHttp` error (422 Laravel errors to the fields, 409 `stale_write` to `conflict`, any other status a generic message) and a thrown Laravel 422 object are read in that order; anything else is the generic message. Messages are plain text capped at 300 characters. Options: `timeout`, `resetOnSuccess` (`true` or a list of fields), `transform` and `warnOnLeave`. A double submit is ignored.
- **`FormCard` with a `useSubmitForm()` is the `<form>` itself** (novalidate): Enter and Save submit with no visit, a failure focuses the first invalid field, and the `_form` message shows above the bar. `submit` stays the action descriptor.
- **The Workbench's form frame, `:form`** ([docs/workbench.md › Form frame](docs/workbench.md#form-frame)): an Inertia `useForm()` with `save` (the action descriptor it submits to), or a `useSubmitForm()`. One `<form novalidate>` around the grid, Save as the bar's primary submit (the app's actions go secondary), ⌘S / Ctrl+S, Saving… and Saved from the form, an errors summary that reveals and focuses the first invalid field, the conflict alert on a 409 with the edits kept, and the leave-page guard. `@submit` replaces the kit's submit.
- **`formFrame.ts`**: the form status and the leave-page guard, taken out of `FormActions` and shared with the Workbench. A frame inside one that is guarding registers no second guard.

### Changed

- **A stale write answers JSON on an Inertia request.** When kit's stale-write guard refuses a save (`RuntimeException('stale_write')`), an Inertia request now gets the same JSON 409 `{"error":"stale_write"}` as a JSON one, so the page keeps the person's edits and shows the conflict instead of being replaced by the error page. Only `stale_write`: every other error code, and a 404, on an Inertia request still renders the error page. A PHP check sends a stale save through the real kernel and compares the body to `tests/fixtures/stale-write-409.json`, the file the JS test feeds `useSubmitForm`.

### Fixed

- **The kit's props survive a flush on a host without session-ui.** The per-request start (`StartKitRequest`: the registry's `startRequestUsing` hooks, the request memo and access's memos, and `KitShares::restore()`) was session-ui's, so a core and shell host without it lost `kitUi`, `kitToasts` and `shell` after `Inertia::flushShared()` (pest-plugin-browser's, before each request, or an Octane-style reset), and its own early middleware read around the request memo. Core now prepends it to `web` and to the middleware priority list on every host that adopts core; session-ui keeps `PrepareKitRequest` (the late restore and the session-dependent brand switch) and no longer adds `StartKitRequest`, so it is registered once. The host still wins: restore only fills keys Inertia's factory doesn't hold. `composer test:core-request-start` (new, in CI) runs a core and shell host through the real HTTP kernel; `test:request-order` now counts `StartKitRequest` once in `web` and in the priority list with session-ui, under either name.
- **`kit:status` flags a request start that is off.** Core joins `StartKitRequest` to `web` only when the app defines that group as core boots, so an app that defines `web` later, or not at all, quietly ran without request memos and lost the kit's Inertia props after a flush. With core adopted, status now carries a `web StartKitRequest` host step: met when `web` holds it (the deprecated session-ui name counts), else attention with the fix. `test:status` covers all three cases.
- **Enter in the Workbench's navigator filter no longer submits a form around it.**

## v0.7.0

### Upgrading

- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:29d14952…750e` → `sha256:e35e2879…7ae0`): `Mcp/Authorize/Page.vue` takes the "Also allow" grant checkboxes below. Nothing to do for an app with no explicit tool: the fieldset renders only when there is a grant to offer.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:6b2a9dac…5077` → `sha256:93780271…035d`): the manifest gains the `vite_global_types` host step below; no stub changes.
- The kit's runtime floors are now `tey/mod ^0.11.6`, `tey/standards ^0.11.51`, `spatie/image ^3.9` and `laravel/mcp ^1.0.2` (see Fixed). An app on an older version of any of them gets the new one on `composer update`; nothing else to do.
- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:7e095959…f01b` → `sha256:29d14952…750e`): `Mcp/Authorize/Page.vue` shows the redirect host and the unverified-app note below, and the migration `0001_01_02_000603_create_kit_mcp_registrations_table` is new. Then `php artisan migrate`.
- The `mcp` feature now serves its own `/.well-known/oauth-authorization-server` and `/.well-known/oauth-protected-resource` documents (root and path-suffixed) and no longer calls `Mcp::oauthRoutes()`. A host that re-declared those routes over laravel/mcp's (to advertise its scopes) can drop them: `scopes_supported` is the kit's token scopes, which include the host's. A host that re-declared `POST /oauth/register` over laravel/mcp's controller can drop that too and set `kit.mcp.registration` to true; its existing clients keep working. `token_endpoint_auth_methods_supported` is now `["none"]` only.
- A host's unregistered-`redirect_uri` test that expected Passport's 401 `invalid_client` JSON for a browser request now gets the kit's 400 page; JSON requests keep the 401. The `laravel/mcp` requirement is `^1.0.2`.
- **A client that registered `http://localhost/...` (or `http://127.0.0.1/...`, `http://[::1]/...`) now matches that redirect on any port** at authorize and token time (RFC 8252 §7.3), as Claude Code's per-session port needs. Only the port is free: the scheme, host, path and query still match exactly, and nothing changes for `https` redirects.
- New migration for `mcp`, `0001_01_02_000603_create_kit_mcp_registrations_table` (the `kit_mcp_registrations` table: which OAuth clients came from `POST /oauth/register`). Run `kit:update plan|apply` for `mcp`, then `php artisan migrate`. Until it is migrated, registration answers 500 `server_error` and `kit:mcp:clients` refuses. A client registered before (laravel/mcp's registration, or the kit's before this table) is not in it: `php artisan kit:mcp:clients --adopt=<client id>` records one by hand, so that `--revoke` and `--prune-unused` can touch it; the kit never guesses.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ea1d2e42…3b8c` → `sha256:652e0756…7ac3`): `Kit/Form/Amount.vue` is new, and `Kit/Form/InputNumber.vue`, `Kit/Form/number.ts`, `Kit/contract.ts` and `Kit/index.ts` change for percent, symbols and the shared `kitNumber` defaults.
- **Money inputs no longer default to CAD.** Set `KIT_CURRENCY=CAD` (config `kit.currency`) to keep the old symbol, or pass `currency` per input.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:652e0756…7ac3` → `sha256:ca87cd7a…7242`): `Kit/Form/AddressFieldset.vue`, `Kit/Form/AddressText.vue`, `Kit/Form/RegionSelect.vue` and `Kit/Form/address.ts` are new, and `Kit/contract.ts` and `Kit/index.ts` export them.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ca87cd7a…7242` → `sha256:bc488ba6…e150`): `Kit/Form/InputTags.vue` and `Kit/Form/tags.ts` are new, and `Kit/contract.ts` and `Kit/index.ts` export them. InputTags puts a caller's `aria-describedby`, `aria-invalid` and `required` on its input, as InputSelect does.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:bc488ba6…e150` → `sha256:a3439714…0e66`): this adds `Kit/Data/DateTime.vue`, `dateTime.ts` and `useTimezone.ts`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a3439714…0e66` → `sha256:962f4983…6ecb`): this adds the pickers (`Kit/Form/InputDate.vue`, `InputDateRange.vue`, `InputMonth.vue`, `InputYear.vue`, `InputTime.vue`, `InputTimeRange.vue`, `PeriodPicker.vue` and `dateInput.ts`), and `css/kit.css` gains their phone-sheet rule. `core` now declares `@internationalized/date` (`^3.5.0`, already installed with reka-ui) in its npm requirements.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:962f4983…6ecb` → `sha256:a04c162c…2dd6`): `Kit/Layout/stickyBars.ts` takes the fix below. A bar registered with `register(px)` keeps working: `px` now means its inset from the viewport's bottom, which for a bar flush with the bottom is its height.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a04c162c…2dd6` → `sha256:81fceb81…95fd`) and `notes` (`sha256:d373d438…f1b2` → `sha256:1a0455e7…551b`): `css/kit.css` adds the `--success-text`, `--warning-text` and `--info-text` tokens, `Kit/Form/FormActions.vue` shows "Saved" in `text-success-text`, `Kit/Feedback/alertVariants.ts` gives a pale Alert the theme's foreground for its text and a `*-text` token for its icon, and the Notes page's `colours.ts` gives a coloured note the theme's foreground. No kit stub colours text with a raw palette colour any more.
- A host with its own theme (not importing `kit.css`) must define `--success-text`, `--warning-text` and `--info-text` for light and dark; the kit's AA values are a starting point: `hsl(142 76% 26%)`, `hsl(26 90% 33%)` and `hsl(201 90% 32%)` light, `hsl(142 69% 58%)`, `hsl(43 96% 56%)` and `hsl(199 89% 64%)` dark. Until it does, the kit's theme host step reads unmet. A host theme's value wins over the kit's, as for the other tokens.
- session-ui's confirm-password Cancel is now resolved by the bindable `Tey\Kit\Features\SessionUi\Contracts\ConfirmPasswordCancelUrl` (default `ConfirmPasswordCancel`). Nothing to do unless you want your own: bind it. No stub changes, no re-pin.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:81fceb81…95fd` → `sha256:4a94fc7c…105e`): `Kit/Appearance/useAppearance.ts` takes the storage fix below. No other stub changes.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4a94fc7c…105e` → `sha256:6537c1e1…3fa7`): `Kit/Workbench/Workbench.vue` takes the phone-tabs fix below.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:71617f5d…3108` → `sha256:592e21c4…7353`): `Auth/ResetPassword/Page.vue` gains a `FormStatus` (below).

### Added

- **`kit:status` and `kit:add` flag an undefined connection ability.** `kit.mcp.connection_ability` defaults to `settings.read`, which the settings feature declares; a host without it (or with its own value) had every MCP connection refused as `permission_denied`. With the `mcp` feature, the host requirements now carry a `kit.mcp.connection_ability` config step: met when a feature declares the ability through the Registry or the Gate defines it, else attention naming the key, the effect and the three fixes. `test:status` covers the default without settings, an undefined value, a Gate-defined one and a Registry-declared one.
- **Explicit grants for MCP tools** ([docs/mcp-consent.md › Explicit grants](docs/mcp-consent.md#explicit-grants)). `#[Capability(…, scope: 'x', explicit: true, explains: '…')]` marks a tool that a blanket acts-as-me connection never covers: it is listed and callable only under a connection whose consent named its scope, and a call anyway is refused `scope_required` (403), never reported as unknown. The kit's consent page offers each such scope the person may hold as an unticked "Also allow" checkbox showing the scope's label and `explains`; only the tick grants (the OAuth `scope` parameter never does), an existing connection never gains one (skipped consent and refresh keep its scopes), and the approval's audit entry records the grants. `scope` must be a registered token scope and `explains` is required; registration refuses otherwise. On the AI assistants page each grant has its own Remove action (`DELETE …/connections/{id}/grants/{scope}`, audited `workspace.connection.grant_removed`), leaving the connection and its token in place. `Registry::explicitScopes()`, `AuthorizeViewModel::grants()`, `WorkspaceConnection::explicit()` and `removeGrant()` are new. `composer test:mcp-explicit-grants` (34 checks).
- **`kit:status` and `kit:add` check that the Vite config gives @vitejs/plugin-vue the kit's types** (the `shell` feature's `vite_global_types` host step; [README › Types](README.md)). A page typed with `defineProps<Tey.Kit.…>()` is resolved at build time from `script.globalTypeFiles`, so a production build fails without `vendor/tey/kit/types/kit.d.ts` there; a tsconfig include alone serves the editor and vue-tsc. The Vite config (`vite.config.ts`, `.js` or `.mjs`) is read as text, never run: the step is met when an array literal under `globalTypeFiles` names the kit's types (a plain path or a `path.resolve(...)` of it), unmet with the exact snippet `vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })` when the key is missing or the list lacks it, and "can't verify" (unmet, never passed) when there is no Vite config, it can't be read, or `globalTypeFiles` is not an array literal. Attention, not blocking, like the other host steps. For a config the kit cannot read, `kit.host.vite_global_types = 'manual'` (`KIT_HOST_VITE_GLOBAL_TYPES=manual`) records that a person verified it; the step then reads "verified manually". `composer test:status` covers each case.
- **`kit.mcp.server.page_size`** ([docs/mcp-tools.md › The server's name and instructions](docs/mcp-tools.md#the-servers-name-and-instructions)). How many tools, resources or prompts one MCP list answer carries, 50 by default (laravel/mcp's maximum; larger values are clamped, a non-number falls back to 50). laravel/mcp's default of 15 hid every tool past the fifteenth from assistants that never follow `nextCursor`. `composer test:mcp-server-config` lists thirty tools in one page by default, pages at a host's 10, and clamps 500 to 50.
- **MCP resources from the host** ([docs/mcp-tools.md › Resources](docs/mcp-tools.md#resources)). `Registry::mcpResources([...])` takes laravel/mcp `Resource` classes or descriptors (`uri`, `name`, `read`, and optionally `mimeType`, `description`, `title`, `capability`), and the workspace server lists and serves them under the same admission as tools: a resource with `#[Capability]` (using `GatedByCapability`) or a descriptor's `capability` is absent while that capability is off or out of plan, and a read of it anyway is refused with the reason (the kit's `resources/read` method), not as unknown; a resource without one (docs) is always listed. A read runs inside the connection's workspace, and a descriptor's closure receives it. Refused at registration: a non-`Resource` class, a gated class without the trait, an undeclared capability, a descriptor missing its uri, name or read, a relative uri, a uri registered twice, or any of it without the `mcp` feature. `composer test:mcp-resources` covers the list, reads, the gate off and on, an unknown URI and each refusal.
- **MCP client registration and discovery, the kit's own** ([docs/mcp-registration.md](docs/mcp-registration.md)). Opt-in OAuth 2.0 Dynamic Client Registration (RFC 7591) at `POST /oauth/register`, `kit.mcp.registration` (`KIT_MCP_REGISTRATION`, off by default: the route does not exist), for public clients only: an assistant such as Claude registers itself with no secret issued and no management credentials. Redirect URIs are checked at registration: `https` with a host, `http` to a loopback host on any port (Claude Code's per-session port, RFC 8252), or a private-use scheme the host lists in `kit.mcp.redirect_schemes` (empty by default); never a wildcard, fragment, userinfo, plain `http` elsewhere, or `javascript`, `data`, `blob`, `file`. A per-IP rate limit (`kit.mcp.registration_per_minute`, 10) and an optional `kit.mcp.registration_policy` (a `RegistrationPolicy` class refusing with a reason, 403 `access_denied`). The client is the Passport row laravel/mcp's registration stored, so a client registered before keeps working, with no column added. The discovery documents (RFC 8414 and RFC 9728, root and `/mcp`) are now the kit's, under laravel/mcp's route names, advertising only what the kit implements: `code`, `authorization_code` and `refresh_token`, S256, public clients, the kit's token scopes, and `registration_endpoint` only when registration is on; `Mcp::oauthRoutes()` is no longer called. The 401 on `/mcp` adds `scope="mcp:use"` to laravel/mcp's challenge. Passport's client bridge matches a loopback `localhost` redirect without its port, as league already does for `127.0.0.1`. `kit:mcp:clients` lists registered clients, `--revoke=<id>` revokes one with its tokens, `--prune-unused=<days>` revokes those with no token in that time (`--dry-run` to look first). `composer test:mcp-registration` covers each RFC requirement, the refusals, the limit, the policy, the metadata shape, a pre-existing client and registration off.
- **The consent page names where the assistant sends you.** It shows the redirect host Passport verified for the request, and for a self-registered client (dynamic client registration, or an owner-less public client shaped like one) adds "Unverified app": the name is the client's own claim, so a person can tell a lookalike from the app they expect. `AuthorizeViewModel` gains `redirect_host` and `registered`; the `Mcp/Authorize/Page.vue` stub shows them.
- **PKCE S256 required at authorize.** Every authorization request must carry a `code_challenge` (43 to 128 unreserved characters) with `code_challenge_method=S256`; a missing challenge or the `plain` method is refused with 400 before league sees it, never redirected. Claude and every MCP client send S256 already.
- **The OAuth error page, brandable.** When an authorization request cannot go back to its app (unknown client, unregistered `redirect_uri`, bad PKCE), a browser now gets a readable 400 page instead of Passport's bare `invalid_client` JSON; JSON callers and errors sent back to a verified redirect URI are unchanged. The kit's `kit::oauth-error` view by default; a host sets `kit.mcp.oauth_error_view` to its own view, or `OAuthErrorPage::using(fn (Request, string $error, ?string $description): Response)` for its own page (an Inertia error page, say). A host that subclassed Passport's authorization controller for this can drop that.
- **Money and percent: `<InputNumber kind="percent">` and `<Amount>`** ([docs/money-percent.md](docs/money-percent.md)). Percentages are canonical in percentage points ("12.5" is 12.5 %). Money and percent inputs show the locale's symbol outside the editable text and name the unit in the label; `negative="parentheses"` gives the accounting form. `<Amount>` displays the same canonical strings in tabular figures. Locale and currency default to the new shared `kitNumber` (config `kit.currency`, the app locale), then the browser.
- **`<AddressFieldset>`, `<RegionSelect>` and `<AddressText>`** ([docs/address.md](docs/address.md)). A postal address as one named group, submitted as `address[line1]` … `address[country]`, with autocomplete tokens, labels that follow the country (Province or territory and Postal code, State and ZIP code), Canadian postal codes written A1A 1A1 on blur, and non-blocking hints for unlikely CA and US codes. Regions are the Canadian provinces and territories and the US states and DC, named in English or French; other countries take free text. `<AddressText>` shows an address in an `<address>` element, with a map link only when the app passes its own `mapUrl`. Labels come from the new `address.*` keys in the kit's `ui` lang file.
- **`<InputTags>`, free-text tags as chips** ([docs/input-tags.md](docs/input-tags.md)). Built on `InputSelect` (multiple and creatable), so the combobox, chips and announcements are the same. A separator (`,` or `;`) or Enter adds the typed tag, and Enter takes a suggestion only after the arrow keys move to it. A paste with separators or line breaks adds each piece. Duplicates (ignoring case in the locale), tags over `maxLength`, tags not matching `pattern` and tags past `max` are refused with the reason under the field, never trimmed. Submitted as `name[]`. Reasons come from the new `tags.*` keys in the kit's `ui` lang file.
- **`DateTime`, dates and times for the viewer** ([docs/date-time.md](docs/date-time.md)). It renders a `<time>` with the machine value and the full date in its `title`, in the formats `relative`, `local`, `date`, `time`, `ymd` and `datetime`. A **calendar date** (`YYYY-MM-DD`) is never converted between zones, so it never shifts across midnight or DST. An **instant** (RFC 3339 with its offset) shows in the viewer's timezone, and one without an offset is shown as given, never guessed. `live` re-renders relative times on one shared minute ticker. `formatDateTime()`, `fullDateTime()` and `useTimezone()` are exported too. It uses `Intl` only, with no date library.
- **session-ui shares `kitTimezone`**, the viewer's zone (the `tz` cookie, then the person's saved zone, then the workspace's, then UTC), through KitShares, so it survives a flush. `DateTime`, and the pickers to come, read it.
- **The adoption inventory knows `data.date-display`** (a new `shows-dates` fact). Hand-rolled timeago.js and moment components, and `TimeAgo`/`DateRelative`-style files, count against it.
- **Date and time pickers** ([docs/date-pickers.md](docs/date-pickers.md)): `InputDate`, `InputDateRange`, `InputMonth`, `InputYear`, `InputTime` and `InputTimeRange`, on reka-ui. Each submits plain ISO strings (`2026-11-01`, `2026-11`, `2026`, `09:30`; a range as `name[start]` and `name[end]`). A date, month, year or time is a wall value, never converted between zones; `kitTimezone` only decides today. The calendars use the APG date picker keys and the month and year grids the APG grid keys; on phones they open as a bottom sheet, and in a locked LockedForm they stay shut. A half-filled or reversed range blocks the native submit. `toInstant()` and `fromInstant()` join a date and time into an RFC 3339 instant with the viewer zone's offset (DST gaps move forward, repeated hours take the earlier moment).
- **`Tey\Kit\Validation\IsoDate` and `IsoRange`**, the server rules for those values: `IsoDate::date()`, `month()`, `year()`, `time()` and `instant()` (an offset required) with inclusive bounds, and `IsoRange::dates()` and `times()` for `{start, end}`.
- **Adoption surfaces `form.date-pickers` and `form.time-input`**, with the facts `takes-dates` and `takes-times`.
- **The adoption inventory reads Blade, Livewire and Filament apps** (tey/standards 0.11.51 or later). Every template-tag signal reads Blade views as well as Vue (`"in": ["vue", "blade"]`). Filament resources under `app/Filament/**` count as hand-rolled through class references: `TextInput`, `Select` and `Textarea` for `form.inputs`, `FileUpload` for `form.files`, `Table` and `TextColumn` for `data.table`, `DeleteAction` for `action.confirm` and `Notification` for `feedback.toast`, mirrored into the `has-forms`, `has-file-uploads` and `has-index-pages` facts. A new `has-filament-destructive-actions` fact lets `action.confirm` apply to a Filament app with no delete route. Blade views count for `shell.workspace` (the conventional app layout files), `layout.page` (`<x-app-layout>`, `<x-layouts.app>`), `action.button` (`<button>`, `<x-button>` and the Breeze button components), `feedback.toast` (toast, flash and action-message views) and `appearance.theme` (theme-toggle, dark-mode and appearance views). `platform.timezone` gains nothing: no signal kind expresses hand-rolled timezone handling in PHP or Blade without guessing.

### Changed

- **Money inputs no longer default to CAD.** Without `currency` or `kit.currency`, `<InputNumber kind="money">` shows no symbol (two decimals, the same canonical minor units) and warns in development. Set `KIT_CURRENCY=CAD` to keep the old symbol.

### Fixed

- **A workspace created in the same request is admitted.** `IsKitWorkspace` read `admitted` as null until the model was read back, so a workspace made and used in one request was refused `admission_required`. The trait now mirrors the column's default (true in the kit's migration) on an unsaved or just-created model whose table has the column, so it answers the way its row will; an explicit `admitted => false` stays false, and admission stays distinct from creation (a host whose adopted column defaults to false sets `admitted` to false in its model's `$attributes`). `test:workspace-guard` covers create-then-check.
- **Leaving a tenant no longer wipes Pennant's array store.** `TenantBoundary` and the `FlushFeatureCacheTask` switch task flushed Pennant's cache on every switch; under the `array` store (the kit's default, and tests) that cache is the store itself, so a flag deactivated earlier in the request came back on, and a test that deactivated a capability then called `/mcp` saw it on. Both now flush only for a store with a backend (`TenantBoundary::flushes()`: the default store's driver is not `array`), where the cache can go stale across tenants. `test:capability-guard` and `test:tenant-tasks` cover both stores.
- **A narrow connection no longer sees tools it cannot call.** `GatedByCapability` listed tools by capability and role ability only, so a connection whose scopes covered `settings.read` alone still saw the write tools and got `connection_denied` on the call. Presence now also requires that the calling connection covers the ability the tool is admitted under, the same `WorkspaceConnection::covers()` the call path applies, so `tools/list` exactly matches what is callable; the refusal codes for a call made anyway are unchanged (`connection_denied`, or `scope_required` for an explicit tool). Covered by `test:mcp-explicit-grants`.
- **A long footer or inspector scrolls inside its phone tab.** Below lg the Workbench's footer and inspector tabs rendered their slot straight into the tab's row, with no scroll container, so a long speaker note ran on behind the pinned actions bar and its end could not be reached. Both now sit in a `PaneScroll`, as their panes do at lg and up. The browser suite checks each at 390×844: the tab scrolls, and after scrolling to the end the last line is fully visible above the bar.
- **The reset-password page shows a form status like its siblings.** `Auth/ResetPassword` had no `FormStatus`, where `Auth/ForgotPassword`, `Auth/Login` and `Auth/VerifyEmail` do. It now has one, first inside the form as on ForgotPassword, showing the `status` page prop when a host app supplies one.
- **Dependency floors are tested.** CI's `tests` job gains a `lowest` entry (PHP 8.4, `composer update --prefer-lowest --prefer-stable`) running every check against the oldest versions `composer.json` allows, so a requirement whose floor predates an API the kit uses fails in CI rather than on a host. Its first run raises `tey/mod` to `^0.11.6` (0.11.0 to 0.11.5 lack the rollback fix `test:adopt` needs) alongside `laravel/mcp: ^1.0.2`, and adds `spatie/typescript-transformer: ^3.3` to require-dev (`bin/kit-types.php` uses `withoutManifest()`, new in 3.3.0, which laravel-typescript-transformer ^3.3 alone does not guarantee), `symfony/event-dispatcher-contracts: ^3.5` and `symfony/polyfill-php83: ^1.32` (older ones emit PHP 8.4 implicit-nullable deprecations at boot, which the checks that require a silent boot read as failures), and `spatie/image: ^3.9` in require (laravel-medialibrary ^11.13 allows 3.3, whose WebP fit does not keep the declared width `test:media-conversions` expects; 3.9.0 is the first that does).
- **FormActions' "Saved" meets WCAG AA.** It was `text-green-600`, about 3.3:1 on a white card (AA is 4.5:1), so axe's `color-contrast` failed on every FormCard just after a save. The new `--success-text` token, with `--warning-text` and `--info-text` beside it, is status text on a surface, as `--destructive-text` is: `hsl(142 76% 26%)` light (5.80:1 on background, card and popover, 5.32:1 on accent and muted) and `hsl(142 69% 58%)` dark (11.36:1, 8.68:1 on accent, 8.35:1 on muted); warning is 6.04:1 and 11.70:1, info 6.06:1 and 9.71:1, and at least 5.32:1 light and 7.13:1 dark on accent and muted. A pale Alert's icon takes these tokens (and `--destructive-text` for danger) instead of raw palette colours, and its text, like a coloured note's, takes the theme's foreground. `tests/js/semantic-text.test.mjs` computes the ratios from `kit.css` and refuses a raw palette text colour (`text-green-600`, `dark:text-sky-300`) in any kit resource, and the browser job's `?form-saved` page runs axe's `color-contrast` over a saved FormCard and the pale Alerts, light and dark.
- **A bottom toast clears a pinned bar that the layout holds above the viewport's bottom.** The sticky-bar registry recorded each bar's height, and the Toaster lifted bottom toasts by that much. A filling Workbench's actions bar sits wherever the layout puts it, which under a shell shorter than its 8rem allowance is above the bottom edge, so a bottom-right toast landed on its right-hand buttons (Present, Save). Each bar now registers its inset (the distance from the viewport's bottom to its top), re-measured on scroll and window resize, and toasts clear the largest one. A browser test asserts that no bar button is under a toast, checking each button's left, centre and right, at 1440×900 with bottom-right toasts and at a phone width.
- **`browser/` and `typecheck/` ignore a symlinked `node_modules`** (the pattern no longer ends in `/`, which matched directories only), so a contributor's linked install can't be committed. The resource checks refuse a tracked symlink.
- **session-ui's login page now shows Concierge's one-click local sign-in.** The login view passed no `localLogin`, so the button never appeared; it now carries `LocalLogin::offer()`, which stays the only gate (null outside `local`, and without Concierge).
- **Confirm-password Cancel falls through instead of jumping home.** A refused `cancel` query (off the app, protocol-relative, backslash, `javascript:`, credentials, encoded) now falls to the referer, then to the intended URL one level up when a real non-guest route answers there, then `kit.home`.
- **`kit:status` flags a missing `kit.home` route.** With session-ui and no workspace-ui, sign-in and Concierge's no-workspace redirect land on `kit.home`; with no route answering GET there it now reports attention instead of letting sign-in 404.
- **Blocked browser storage no longer leaves the page blank.** `useAppearance` read and wrote `localStorage` unguarded (in `initializeTheme`, on mount and in `updateAppearance`), so where storage throws on access (site data blocked, some private modes) the app never mounted. Every read and write is now guarded: a failed or unrecognised read falls back to `system`, and a failed write is ignored (the `appearance` cookie still carries the choice). The other core stubs that use storage (`ComputedValueScope.vue`, `SplitPane.vue`/`splitState.ts`, `DataTable.vue`, `Workbench.vue`/`workbenchLayout.ts`) were already guarded. The browser suite mounts the app with `localStorage` and `sessionStorage` throwing.

## v0.6.13

### Upgrading

- **kit v0.6.10–v0.6.12 shipped unresolved conflict markers in `Kit/Layout/Page.vue`; re-run `kit:update apply` for `core`** (re-pinned `sha256:ce15e8de…e8dd` → `sha256:c97858e5…41da`). The shipped file is a known prior version (history.json records it), so an untouched copy is replaced cleanly. A host that hand-resolved the markers sees its copy as edited: take the release bytes, or keep its resolution if it matches the one below.
- A host that re-shares the kit's props in its browser test case after pest-plugin-browser's flush (a `BrowserCase` workaround) can drop that re-share: the kit puts its props back on every web request.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f3932382…7dbe` → `sha256:6111274d…1cc8`): `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/workbench.ts` take the item menus below. An item without `actions` renders as before.
- Run `kit:update plan|apply` for `shell` (`sha256:56acbf30…3bb0` → `sha256:efd6116b…ba5e`): the Workspace Layout and its partials take the changes below, and FocusShell is new.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:6111274d…1cc8` → `sha256:2646246d…77fe`), `shell` (`sha256:efd6116b…ba5e` → `sha256:6b2a9dac…5077`), `session-ui` (`sha256:be81f458…6f4f` → `sha256:71617f5d…3108`) and `workspace-ui` (`sha256:93e9b61a…7bed` → `sha256:2f226d38…39e7`): `css/kit.css` adds the `--destructive-text` token, and every kit stub that coloured text with `text-destructive` now uses `text-destructive-text` (the menus' destructive items, `Kit/Form/Repeater.vue`'s Remove item, `Kit/Overlay/Dialog.vue`'s danger icon, the form and page error messages, `Kit/Action/HeaderStatus.vue`, `Kit/Search/SearchBox.vue`, `Kit/Workbench/WorkbenchActions.vue`, the login error, the workspace switcher's Leave item and the People page's expired date).
- A host with its own theme (not importing `kit.css`) must define `--destructive-text` for light and dark; the kit's AA values are a starting point: `hsl(0 72% 42%)` light and `hsl(0 91% 71%)` dark. Until it does, the kit's theme host step reads unmet. A host theme's value wins over the kit's, as for the other tokens.
- A host that builds navigator items on the server: send `actions` on the PHP `WorkbenchItem` (a list of `Action`) to get row menus, and regenerate or re-copy `types/kit.d.ts`, whose `WorkbenchItem` now has `actions`. A host that sends plain arrays can switch to `WorkbenchItem` unchanged.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:2646246d…77fe` → `sha256:e0090454…0845`): `Kit/Form/FormStatus.vue` and `Kit/contract.ts` take the FormStatus fix below, and `FormStatus` takes an optional `flashKey`. Pages that pass no `flashKey` keep reading the `status` page prop.
- **An app that flashes its own confirmation with `Inertia::flash()`** can show it with `<FormStatus flash-key="notice" />`: Inertia v3 keeps flash data at `usePage().flash`, not in `page.props`. Fortify's `status` stays a shared prop and needs no change.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e0090454…0845` → `sha256:93b89e36…51e0`): `Kit/Workbench/Workbench.vue` takes the slot and the phone layout fix below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:93b89e36…51e0` → `sha256:ca1f9248…7179`): `Kit/http.ts` is new, and `Kit/Workbench/useWorkbench.ts`, `Kit/Workbench/workbenchLayout.ts` and `Kit/Feedback/passkeyOffer.ts` take the fix below. A host whose own code reads `response.data.x` from Inertia v3's `http` client can read it through `responseJson(response)` from `@/components/Kit/http` instead.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ca1f9248…7179` → `sha256:ce15e8de…e8dd`): `Kit/Action/actionPlacement.ts`, `Kit/Action/ActionMenuItems.vue`, `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchNavigator.vue` and `Kit/Workbench/workbench.ts` take the checkbox menu items and `#navigator-item` below. ActionMenuItems now imports `DropdownMenuCheckboxItem` from the host's shadcn-vue `dropdown-menu` (DataTable already did). A descriptor without `checked` and a Workbench without `#navigator-item` render as before.
- No changes needed. A host that relied on its `HandleInertiaRequests`' position for the request memo, or that wraps a kit prop in its own share, keeps working. A workaround that moved kit middleware earlier (such as `prependToPriorityList` for `PrepareKitRequest`) can be removed.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c97858e5…41da` → `sha256:12a237c5…e9f8`): `Kit/Form/InputCheckbox.vue`, `Kit/Form/Select.vue`, `Kit/Form/FileInput.vue`, `Kit/Form/RadioCardGroup.vue`, `Kit/Form/InputRichText.vue`, `Kit/Form/InputSelect.vue`, `Kit/Form/InputNumber.vue` and `Kit/contract.ts` take the attribute fix below. InputCheckbox's row is now wrapped in a grid (for its `description`), so a host that styled its row by structure should check it.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:12a237c5…e9f8` → `sha256:da818a98…6ef1`): `Kit/Layout/StickyBar.vue`, `Kit/Layout/stickyBars.ts`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/WorkbenchActions.vue` take the fix below. `StickyBar` takes an optional `pinned`.
- **A host with its own `home` route or its own invitation flow** can now keep it. Set `kit.home_name` (for example `app.home`) to rename the kit's home route, and set `kit.routes.session-ui.invitations => false` to leave out the kit's invitation link (transitional; see [docs/catalog.md](docs/catalog.md)). Nothing changes by default.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:da818a98…6ef1` → `sha256:fa5d0296…4815`): `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/workbench.ts` take the thumbnails and filmstrip layout below. An item without `thumbnail` in the default `list` layout renders as before. A host that copies `types/kit.d.ts` re-copies it: `WorkbenchItem` has `thumbnail`, and `WorkbenchThumbnail` is new.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:fa5d0296…4815` → `sha256:ea1d2e42…3b8c`): `Kit/Form/SegmentedToggle.vue` is new, and `Kit/index.ts`, `Kit/contract.ts` and `Kit/Layout/PaneTabs.vue` take the changes below. **PaneTabs looks different:** its pressed pane is no longer a primary-filled button but a neutral raised segment in a muted track. Its semantics, props and events are unchanged. A host that styled around the old primary button, or asserted its classes, should check its phone tabs.

### Added

- **Workbench navigator thumbnails and a filmstrip layout** ([docs/workbench.md › Thumbnails and filmstrip](docs/workbench.md#thumbnails-and-filmstrip)). A navigator item takes an optional `thumbnail` (`{ src, alt }`, exported as `WorkbenchThumbnail`; `alt` is required, `''` for a decorative image that repeats the label), rendered as a lazy, async-decoded `<img>` inside the item's `role="option"` row in a fixed-aspect box, so the row doesn't shift when it loads. `navigator-layout="filmstrip"` on the Workbench (`layout` on WorkbenchNavigator) lists the items as a Slides-like editor's left rail: the item's number, a 16:9 thumbnail (an empty placeholder box without one) and the label; `list` stays the default. `#navigator-thumbnail` (`{ item, index, selected }`, typed as `WorkbenchNavigatorThumbnailSlotProps`; `thumbnail` on WorkbenchNavigator) fills each filmstrip box with the host's own render, such as a scaled live slide, ahead of `thumbnail.src` and the placeholder. Its wrapper is `inert` and `aria-hidden`, and the box has `contain: strict` with overflow hidden, so the render can't take focus, change the option's name or move the layout. List rows take no thumbnail slot. The listbox semantics, keys, Alt+↑/↓ reorder, drag, filter, F6, `#navigator-item` (the label part) and the row menus beside the listbox are unchanged. The PHP `WorkbenchItem` takes `thumbnail` (a new `WorkbenchThumbnail` data class, default null) and `types/kit.d.ts` is regenerated; the MCP `WorkbenchReadTool` still gives exactly `{ id, label, type, description }`, leaving the thumbnail out. `composer test:workbench-item-parity` covers the new key through the Inertia round trip, and the browser job checks a filmstrip: images inside the options with their alt, nothing focusable in the listbox, Alt+↓ reorder, filter and F6, axe clean with color-contrast, each thumbnail box sized before its image loads, and a live `#navigator-thumbnail` holding a button and a link: inside the box at the image box's size, never focused or tabbed to, the option named by its label only, axe clean, and Alt+↓ working.
- **Checkbox menu items** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). An action descriptor with `checked` (true or false) renders in ActionMenuItems, and so in every kit menu built on it including the Workbench row menu, as `role="menuitemcheckbox"` with `aria-checked` and the host's check indicator (shadcn-vue's DropdownMenuCheckboxItem). Choosing it emits the action as today; the host flips `checked`. Without `checked`, or with `null`, the row stays a plain menuitem. The PHP `Action` takes `checked` (nullable bool, default null), and `types/kit.d.ts` is regenerated. A row menu opened from the item focuses its first row, checkbox rows included.
- **Workbench `#navigator-item` slot** ([docs/workbench.md › Custom rows](docs/workbench.md#custom-rows)). The Workbench forwards WorkbenchNavigator's `item` slot as `#navigator-item` (`{ item, selected, index }`, typed as `WorkbenchNavigatorItemSlotProps`) in every layout: columns, the lg drawer layout, phone tabs and the overlay navigator. A host customises row content without replacing the navigator. The kit keeps the `role="option"` row, its keys and the row menus outside the listbox; the slot holds content only. WorkbenchNavigator now declares its `item` slot with `defineSlots`. The browser job checks a row menu with a checked and an unchecked item and a Workbench using `#navigator-item`, with axe clean on both.
- **SegmentedToggle, a neutral view switch** ([docs/segmented-toggle.md](docs/segmented-toggle.md)). `Kit/Form/SegmentedToggle.vue` picks one of a few views ("Slides | Outline", grid or list) with `v-model`, `options` (`{ value, label, icon?, disabled? }`), `label` and `size`. Its selected segment is a bordered `bg-background` surface in a `bg-muted` track, never brand-filled. It's a `role="radiogroup"` of `role="radio"` buttons with one tab stop: the arrows move and select, Home and End jump, and disabled options are skipped. The selected border keeps 3:1 against the track and the segment in light and dark. `tests/js/segmented-toggle.test.mjs` checks the source and the ratios; the browser job drives the keys, compares the selected background with the primary colour and runs axe with colour contrast, light and dark.
- **Workbench navigator item menus** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). A navigator item can carry its own kit action descriptors in `actions` (Move up, Move down, Duplicate, Delete, as the host decides per item), and gets a row menu: a button named "Actions for <label>", shown on hover and focus and always on a touch pointer, plus right-click, Shift+F10 or the context-menu key on the item, which open the same menu anchored to the row. A chosen descriptor is emitted as the Workbench's `action` event with the item as its second argument. Escape returns focus to the item, or to the button the menu opened from. The buttons sit in a column beside the listbox, never inside it, since a listbox may own only options. The listbox's arrows, filter and F6 are unchanged, and so is Alt+↑/↓ reordering. An item without actions has no button and keeps the browser's context menu. The browser job (Playwright with axe-core) checks a three-item Workbench: no axe violations, no focusable element inside the listbox, and the Shift+F10, right-click and Escape paths.
- **Server-side item menus: `actions` on the PHP `WorkbenchItem`** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). `WorkbenchItem` takes `actions`, a list of the kit's `Action` (default empty, no menu), and a WorkbenchViewModel sends them to the page unchanged; the generated type has `actions: Action[]`. The MCP `WorkbenchReadTool` still gives each item exactly `{ id, label, type, description }`: row-menu actions are UI affordances, and no MCP tool exposes action descriptors. `composer test:workbench-item-parity` pins the PHP keys to the TS `WorkbenchItem`, the generated type, the read tool's keys and the Inertia round trip of Move up, Duplicate and Delete.
- **`#navigator-actions` on the Workbench** ([docs/workbench.md › Navigator actions](docs/workbench.md#navigator-actions)). The app's own controls, such as "+ New slide", render first in the navigator, before its filter and list, at every width (columns, lg, phone tabs and the overlay sheet). So the visual and tab order match without `order-first`. With the slot empty, the navigator renders as before.
- **session-ui route groups and `kit.home_name`.** `kit.routes.session-ui.<group> => false` switches off one group of session-ui's routes, and their names, at a time: `invitations`, `welcome`, `passkey-offer` or `demo-login`. Every group is on by default, and the kit's own redirects and props for a group that is off are guarded. An unknown key or a non-boolean value stops the boot with an error that names the groups. `kit.home_name` (`KIT_HOME_NAME`, default `home`) names the kit's home route, so an app that already owns `home` keeps it; the kit's breadcrumbs follow the new name. Checked by `composer test:route-groups`.

### Fixed

- **`laravel/mcp` 1.0.0 and 1.0.1 broke every route load.** The kit calls `Mcp::oauthRoutes(registration: false)`, whose `registration` parameter arrived in laravel/mcp 1.0.2; on an older install every request failed with "Unknown named parameter $registration". The requirement is now `laravel/mcp: ^1.0.2`, the first release with every laravel/mcp API the kit uses.
- **The Workbench reorder kept a null version token after Inertia v3 returned a string body, so the next write got a 409.** Inertia v3's HTTP client resolves `data` as the raw response text, and `sendOrder` read `response.data.version` from it, so after every reorder the token was lost and the next stale-safe write (another move, an add, a duplicate, a delete) was refused as `stale_write`. Bodies now go through core's new `responseJson`, which returns an object `data` as it is and parses a string as JSON, and throws on an empty or non-JSON body instead of handing back null (`allowEmpty` returns null where a caller expects no body); `errorJson` reads a refused request's body, as null when it isn't JSON, since the status has already decided. The same string-body bug lost the stored layout and version on a 409 from the Workbench layout endpoint (`sendLayout`), and the creation options in the passkey offer (`registerPasskey`); both read through it now. A 2xx reorder answer that isn't JSON is a failed save, so the old order comes back. `tests/js/response-json.test.mjs` covers the helper, `workbench.test.mjs` and `onboarding.test.mjs` string bodies, and a browser check makes two moves and a Duplicate against a stand-in server that refuses a stale or null version.
- **The kit's Inertia props survive `Inertia::flushShared()`.** `kitUi`, `kitToasts`, `shell`, `environment`, `kitPassword` and `passkeyOffer` were shared once at provider boot, so anything that flushed Inertia's shared props afterwards dropped them: pest-plugin-browser flushes before each request, so a host's browser tests rendered blank pages, and an Octane-style reset or a host's own flush would too. Each provider now hands its closures to `KitShares`, which shares them at boot and again at the start of every web request (now first in `web`, see below). They stay lazy closures, one producer each. The host still wins: the kit puts back only the keys Inertia's factory doesn't already hold, and the host's own `HandleInertiaRequests` merges over them, so a key the host shares (in its middleware, or on the factory before the request) is never overwritten. The kit still never flushes. `test:inertia-shares` and `test:request-hooks` (through the real HTTP kernel) flush between requests and check every kit prop, the host's own `shell` and `environment`, and two people in turn.
- **`Kit/Layout/Page.vue` is a valid component again.** A merge in v0.6.10 left `<<<<<<<`, `=======` and `>>>>>>>` lines around the page header, and they shipped in v0.6.10, v0.6.11 and v0.6.12. The header is now the status and actions version (HeaderStatus, `status`, `actions`) with the `font-display` heading face, as both sides intended. A new CI check, `composer test:conflict-markers`, fails on any conflict marker (indented ones included) under `resources/` or `src/`.
- **The kit's request start now runs first in `web`.** `PrepareKitRequest` was appended, so it ran last: a host's own middleware earlier in `web` (a tenant resolver) read memberships before the request memo began, and repeated their queries; after an `Inertia::flushShared()` a host share wrapping a kit prop (`Inertia::getShared('shell')`) captured null, so the page fell back to the guest layout; and a route-model-binding 404 never reached it, so the 404 page had no kit props. session-ui now prepends a new `StartKitRequest` to `web` and to the middleware priority list. It begins the request's memos (the registry's new `startRequestUsing` hooks: the request memo and access's permission, capability and entitlement memos) and puts the kit's props back (`KitShares::restore()`), neither of which needs the session or the signed-in person. The session-dependent hook (the local brand switch) stays late in `PrepareKitRequest`, which also restores once more. The host still wins: restore only fills keys Inertia's factory doesn't hold, and the host's own `HandleInertiaRequests` merges over them. Each memo is scoped to its request, so nothing carries over. `composer test:request-order` (new, in CI) runs a host through the real HTTP kernel and checks that the host's second membership lookup adds no query, that the wrapped shell and a binding 404 after a flush both have the kit's shell, that a host `shell` share still wins, and that a membership removed between two requests isn't answered from the first's memo.
- **Kit form controls put your attributes on the control itself, not a wrapper.** `aria-describedby`, `aria-invalid`, `required`, `aria-label` and `data-*` passed to InputCheckbox landed on its wrapper `<div>`; on Select they went nowhere (Reka's SelectRoot renders no element); FileInput dropped them; RadioCardGroup and InputNumber replaced or ignored your `aria-describedby`; InputRichText put them on its frame; and InputSelect dropped a passed `required`. Each now forwards them to its focusable control (the checkbox button, the select trigger, the file input, the radiogroup, the editable textbox, the combobox input), joins your `aria-describedby` with its own ids, de-duplicated, and sets `aria-required` where a native `required` doesn't apply. `class` and a style's custom properties stay on the wrapper. InputCheckbox, FileInput, RadioCardGroup and InputRichText take `invalid` (`aria-invalid`), and InputCheckbox takes `description`, shown under the label as `<id>-description` and joined to its `aria-describedby`.
- **A toast no longer covers a filling Workbench's actions bar.** The bar is a StickyBar at the viewport's foot by layout, not by scroll, so it never counted as pinned and never registered with useStickyBars: a bottom toast sat on top of its buttons (Save) and took their clicks. `StickyBar` takes `pinned` for a bar the layout holds at the foot, and the Workbench passes it while it fills. `useStickyBars().track()` also re-measures when its `enabled` state changes, not only on resize, so a bar that becomes pinned by scrolling registers at once. FloatingToolbar (and BulkBar on it) already registered while floating.
- **Mail text is escaped exactly once.** `FluentMailMessage` HTML-encoded user text and the markdown render encoded it again, so an apostrophe showed as a literal `&#039;` ("Ada&#039;s talk") in the HTML part, and the text part showed the entities and markdown backslashes (`\*x\*`). `escape()` now only backslash-escapes markdown punctuation and leaves HTML encoding to the render, and each block carries its text as given for the text part. `<script>` and `<b>` still show as inert text in every builder (headline, paragraph, facts, table, panel, callout, note, bullets, quote, section, button labels) in both layouts. **A host that passed `FluentMailMessage::escape()` output to `markdown()`** gets the same HTML; its text part shows that block's backslashes, as before.

### Changed

- **`layout.page` reads hand-rolled pages in apps on the kit shell again.** The `ui.kit-shell` rule is back beside the PageHeader file-name signal: the rule reads an app that adopted the kit shell, the file name a legacy app. Where the rule can't run (no Pest beside standards), plain `standards adoption` reports it as `skipped` and the other signals decide the row; `--ci` and `--compliance` still fail closed. Kit's fixture check now asserts each case's expected skips, and fails on one it doesn't list.
- **PaneTabs' pressed pane is neutral.** The Workbench's phone tabs (`Kit/Layout/PaneTabs.vue`) took the primary button for the pressed pane, so it read as a second primary action. They now take SegmentedToggle's look: a bordered raised segment in a muted track. They stay a single-choice toggle group (`role="group"`, `aria-pressed`).
- **The adoption inventory is calibrated against legacy apps.** A `*Panel`, `*History` or `*Chip` file, a drag-and-drop library on its own, and a `<table>` with no header row no longer count as hand-rolled; cards count only outside public pages. `layout.page` reads hand-rolled from an app's own page header without the kit shell, `shell.workspace` from a starter-kit `AppLayout`, and page actions in an app layout's `#header` slot count. `overlay.modal` replaces `@inertiaui/modal-vue`. Each change has an adoption fixture, and the fixtures run on tey/standards 0.11.47.
- **The adoption inventory no longer reads the app's shadcn primitives or content renderers as hand-rolled.** Every `resources/js/**` file-name signal skips `resources/js/components/ui/**`, the primitives the kit builds on, so `ui/dropdown-menu/*` no longer marks `action.menu` hand-rolled. The `*Card.vue` signal for `layout.blocks` and `has-content-panels` also skips `body/`, `content/`, `render/` and `renderers/` folders, where a card draws content rather than framing a page. New fixtures `cards-content-renderer` and `ui-primitives-only` cover both. A `ui/button/Button.vue` still reads hand-rolled through standards' generic-name scan of `replaces.components`, which has no path filter yet.

### Fixed

- **The adoption schema accepts what tey/standards 0.11.50 accepts.** `resources/adoption/surfaces.schema.json` gains the `class-reference` signal kind (`classes`, optional `paths`, `min` and `scope`), file-name `except` and template-tag `in` (`vue`, `blade` or both; default `vue`), so packages that copy the schema can use them. A new CI check, `composer test:adoption-schema`, compares the schema with the installed standards' signal kinds, fields, required fields, groups and analyses, and fails on any drift. The kit's tey/standards dev dependency is now ^0.11.50.
From teylabs.com's adoption of the Workspace Layout:
- **A host without workspaces gets its navigation.** PageShell sent an empty navigation and account menu on a core and shell host. It now lists the pages registered with `Registry::navigation()` at root-relative URLs, current by the same `active` rules, each shown while the viewer passes its ability as a Laravel Gate ability. An undefined or denied ability hides the link (fail closed). `user-menu` links, such as a Settings item, join the account menu.
- **The Layout no longer crashes on a focus shell, and FocusShell ships.** For a `kind: 'focus'` shell (welcome steps, no workspace yet), the Workspace Layout renders the new FocusShell: the product, an optional "Step n of m", and the account cluster with Switch account and Sign out. It's also exported from `@kit/shell`, and takes `:toaster`.
- **The home crumb is recognised by path.** gretel's breadcrumb URLs are absolute (`https://host/@slug`), so the root crumb never matched and showed as text. It now shows as the house icon.
- **The `extra` slot says where it's rendered.** It's rendered in both the account dropdown and the phone drawer, now with a `place` slot prop (`menu` or `drawer`), so an app can render a DropdownMenuItem in one and a plain link in the other. A new `drawer-extra` slot replaces it in the drawer.
- **The current page stands out in PrimaryNav.** It now gets a tinted pill and a 3px marker beside full-strength text, not only an underline in the brand marker's colour.
- **Destructive menu items and error text meet WCAG AA.** They used `--destructive` as text, 3.76:1 on the white popover in light mode and 4.00:1 on a focused item in dark mode (AA is 4.5:1). The new `--destructive-text` token is destructive text on a surface; `--destructive-foreground` keeps its meaning, text on a destructive background, and solid destructive backgrounds are unchanged. Its defaults are `hsl(0 72% 42%)` light (6.48:1 on background, card and popover, 5.94:1 on accent and muted) and `hsl(0 91% 71%)` dark (7.20:1 on background, card and popover, 5.51:1 on accent, 5.29:1 on muted). `tests/js/destructive-text.test.mjs` computes those ratios from `kit.css` and refuses a raw `text-destructive` in any stub, and the browser job's open Workbench row menu now runs axe's `color-contrast` with the destructive Delete item showing ([browser/README.md](browser/README.md) says why contrast runs use a non-modal surface).
- **`FormStatus` mounted before a visit now shows the message the visit brings back.** It kept a `hidden` flag set by the router's `start` and by a watcher on `form.processing`, and cleared only by `finish`, so a form whose `processing` changed without a router visit left it hidden for good. Visibility is now derived from state: it shows when there is a message and no visit is in flight (the router between `start` and `finish`, or `form.processing`), so after each visit it shows exactly when the new page carries a message. The browser job mounts it before the visit and drives visits through the Inertia stub in v3's order (`start`, the page swap, `flash`, `success`, `finish`).
- **The Workbench's phone navigator is a flex column**, as on wider screens. It was only `min-h-0`, so a host's `order-first` child sat under the actions bar on phones.

## v0.6.12

### Changed

- **The adoption inventory's expected outcomes run in kit CI.** `composer test:adoption-fixtures` runs tey/standards' `standards adoption --json` (now a dev dependency, ^0.11.46) on each fixture app under `tests/fixtures/adoption` with this checkout's `surfaces.json`, and fails when a row or fact differs from `expected.json`.

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:703d8cdc…a15c` → `sha256:f3932382…7dbe`): `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/useWorkbench.ts` and `Kit/Workbench/workbench.ts` take the empty state below.

### Added

- **A Workbench empty state** ([docs/workbench.md › Empty workspace](docs/workbench.md#empty-workspace)). While a workspace has no items (`useWorkbench(key).isEmpty`), the stage renders the host's new `#empty` slot in place of `#stage`, with `{ actions, act, label }`, so a host can offer "Create draft · Import · + New slide". It's opt-in: without an `#empty` slot the stage renders `#stage` as before and the kit adds no default, so a stage-only Workbench with no items (such as a presenter console) keeps its stage. The navigator shows a plain, focusable "Nothing here yet." message in place of an empty listbox, which fixes axe's `aria-required-children` and `scrollable-region-focusable` with zero items (or a filter that matches nothing); F6 and `focusRegion('navigator')` land on it.

## v0.6.11

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:debbdb0f…e62d` → `sha256:e95f0424…5afc`): `Kit/Overlay/ModalPanel.vue`, `Kit/Overlay/Dialog.vue`, `Kit/Overlay/Popover.vue`, `Kit/Form/Select.vue`, `Kit/Form/InputSelect.vue`, `Kit/Feedback/Toaster.vue` and the kit's menus (`Kit/Menu/MenuPanel.vue`, `Kit/Action/ActionBar.vue`, `Kit/Action/HeaderActions.vue`, `Kit/Table/RowActions.vue`, `Kit/Table/DataTable.vue`, `Kit/Form/Repeater.vue`, `Kit/Layout/FloatingToolbar.vue`) take the fix below; `Kit/Overlay/layer.ts` and `Kit/Menu/MenuContent.vue` are new.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:04c61611…7379` → `sha256:554cfe94…56eb`): `Workspace/Layout.vue` takes the props below. Every default is unchanged.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:5e32938b…3328` → `sha256:debbdb0f…e62d`): `css/kit.css` defines the stacking scale below.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:554cfe94…56eb` → `sha256:56acbf30…3bb0`): `Workspace/Partials/WorkspaceSwitcher.vue` takes the change below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e95f0424…5afc` → `sha256:703d8cdc…a15c`) and `session-ui` (`sha256:02200ec2…49b4` → `sha256:be81f458…6f4f`): `Kit/Form/FormStatus.vue` is new, and the ForgotPassword, VerifyEmail and Login pages use it. Hosts with their own `@kit/ui` module must export `FormStatus`.

### Added

- **The Workspace Layout can be used whole by a host with its own root shell** ([README › The shell](README.md#the-shell)). Three new props, each defaulting to today's behaviour:
  - **`:toaster="false"`** leaves the toasts to the host's single Toaster, as `GuestShell` and `PublicShell` allow, so toasts don't double and sticky ones survive a layout swap.
  - **`:document-title="false"`** leaves the `<title>` to the pages' own `<Head>`.
  - **`fill`** is for editor pages such as a Workbench. The shell takes the viewport's height with no page scroll, and the content area fills what the header leaves: unpadded, `min-h-0` and overflow hidden, and still capped by `widthClass` (the host's measure). The page scrolls its own panes.
- **Adoption inventory.** `resources/adoption/surfaces.json` lists what the kit offers an app (UI and backend surfaces, with the app facts that decide when each applies, the evidence of adoption, the signals of a hand-rolled equivalent and the libraries it replaces), in the format `resources/adoption/surfaces.schema.json` defines, for `standards:adoption`. Each fact says how standards computes it (one of its route and model analyses, or signals in app code), and a surface such as Dialog or Avatar applies only to an app that shows one. A test fails when a barrel export or feature has no entry.
- **`<FormStatus>`, a form's persistent confirmation** ([docs/form-status.md](docs/form-status.md)). It composes `Alert` with `live="status"` (polite) for a status that must stay visible after a submit, such as a reset link sent or a waitlist sign-up; it isn't a toast and never times out. It shows the flashed `status` page prop unless `message` is given, maps `tone` (`success` by default, `info`, `warning`) to Alert's variants, and hides on the next Inertia visit's start or while `form.processing`, showing again only if the next response still carries a status. session-ui's ForgotPassword, VerifyEmail and Login pages use it in place of their own markup.

### Changed

- **Passkeys are optional per app in the adoption inventory.** `auth.passkeys` applies only when the app opts in (the new `uses-passkeys` fact: its user model implements Fortify's `PasskeyUser`, or a page uses `PasskeyOffer`), so an app without passkeys reads n/a and never fails the zero-exemption gate for them.

### Fixed

- **Overlays stack above a host's sticky bars.** `Dialog` uses `z-(--z-dialog)` and the workspace header `z-(--z-header)`, but neither variable was defined, so their z-index was `auto` and a dialog could sit under a host's sticky bar. `kit.css` now defines the scale on `:where(:root)`, lowest first: `--z-sticky` 20, `--z-header` 30, `--z-dropdown` 40, `--z-dialog` 50, `--z-toast` 60. A host `:root` overrides any of them. `tests/js/z-scale.test.mjs` fails on any `--z-*` a stub uses that `kit.css` doesn't define.
- **Overlays inside a Modal open over it and take clicks.** The package opens a Modal as a native `<dialog>` in the browser's top layer, and the kit teleported the unsaved-edits confirmation, selects, popovers and menus to `<body>`, under it: "Discard your changes?" painted behind the Modal and could not be clicked. Inside a Modal they now render into its `<dialog>`; outside one, into `<body>` as before. Escape in one of them closes only that overlay, and the Modal stays open (the browser's own close of the `<dialog>` is held back). A toast raised while a Modal is open shows over it. A real-browser check (Playwright, `browser/`) covers the confirmation, a select and a menu inside a native `<dialog>`, including the Escape order.
- **The workspace switcher no longer repeats "Workspaces".** With no current workspace (the chooser, for someone with two or more), its label was "Workspaces", the same as the nav link beside it. It's now "Choose a workspace", and a host can word it with the switcher's new optional `placeholder` prop (through the Layout's `workspace-switcher` slot).

## v0.6.10

### Upgrading

- Hosts that own `config/multitenancy.php` must list `ConciergeContextTask` and `FlushFeatureCacheTask` in `switch_tenant_tasks`; `kit:status` now fails without them.
- `kit:status` now exits 1 when a host requirement fails; CI scripts that run it will fail on a misconfigured host.
- Run `kit:update plan|apply` for `core` (`sha256:35010308…2c02` → `sha256:5e32938b…3328`), `shell` (`sha256:93849b84…4a0d` → `sha256:04c61611…7379`): kit.css, the Page heading, GuestShell and the public Markdown page take `font-display`.

### Added

- **The theming policy, documented and advised** ([README › Theming](README.md#theming)).
  - **Surfaces.** An app may remap the surface tokens (`background`, `card`, `popover`, `muted`, `sidebar`, and `--shell-ground`) in `:root` and `.dark`, each with its foreground.
  - **Kit tokens.** The kit owns its `--kit-*` tokens, which an app sets only on the named shell surfaces.
  - **Advisory.** `kit:status` and every plan add "Theming (advisory)" lines for a surface without its foreground, a pair below 4.5:1 (hex, `rgb()` or `oklch()`), or a `--kit-*` override elsewhere. They are attention only and never block or fail.
- **`--font-display`, the kit's heading face.** The Page heading and the GuestShell and public Markdown page titles were hard-coded to `font-serif`; they now use `font-display`. Through `@theme default` in kit.css, it's the serif stack unless the host sets `--font-display` (a sans app: `--font-display: var(--font-sans)`), so nothing changes by default. It's listed in the README's Theming section.

### Fixed

- **A host-owned `config/multitenancy.php` without the workspaces switch tasks is caught.** The kit's `switch_tenant_tasks` default applies only when the app has no `config/multitenancy.php` of its own, and without `ConciergeContextTask` a tenant switch never enters the Concierge context. Every workspaces plan (`kit:add`, `kit:adopt`, `kit:update plan`) now carries a blocking `multitenancy.switch_tenant_tasks` host step naming each missing task and the exact `\Tey\Kit\Features\Workspaces\Tasks\…::class,` lines to add, and their applies refuse until both are listed, in any order beside the app's own tasks. `kit:status` fails too (exit 1, `FAILED:` and `requirements.failures` in JSON). A value that is unset, unreadable or not an array counts as missing. Apps that keep the kit's multitenancy defaults are unaffected.
- `bin/catalog-history.php` no longer records a newer line's releases on a maintenance branch, even when their tags are fetched: it lists the tags that are ancestors of `HEAD` and any older than the newest of them. Main still lists a maintenance line's patches, such as v0.5.56, so `kit:adopt` identifies an app installed from one. `composer test:catalog-history` checks both on a throwaway repository.

## v0.6.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c2c5d46e…325f` → `sha256:35010308…2c02`): `Kit/Layout/Page.vue`, `Kit/Layout/FloatingToolbar.vue`, `Kit/Workbench/WorkbenchActions.vue`, `Kit/Action/actionPlacement.ts` and `Kit/Action/ActionBar.vue` take the page header actions, and `Kit/Action/headerSplit.ts`, `Kit/Action/actionRunner.ts`, `Kit/Action/HeaderActions.vue` and `Kit/Action/HeaderStatus.vue` are new. Copy `types/kit.d.ts` again if you keep a copy (`Action` gains `priority`).
- Pages with more than 3 header actions passed through `actions` now fold the extra ones into More; set `priority` to keep one visible. The same goes for a Workbench with more than 3 `actions`. Three or fewer with no `priority` render as before.

### Added

- **Page header actions.** `<Page :actions>` splits server action descriptors into one filled primary, up to two outline secondaries and a "More" menu, with one rule (`headerSplit`) shared by the FloatingToolbar's new `actions` prop and the Workbench's actions row. `Action` gains a trailing `priority` argument (`primary`, `secondary`, `overflow` or null): a declared priority wins; otherwise, past three actions, the first non-destructive one is primary and the next two are secondaries, and a destructive one is never promoted. Below `sm` only the primary stays and the rest move into More. The `#actions` slot stays as an escape hatch with no rule applied. See the README's "Page header actions".
- **Page header status.** A `#status` slot and a `status` prop (`{ state, label }`) on `<Page>` and FloatingToolbar, before the actions, in a muted polite live region (`role="status"`).

## v0.6.8

### Upgrading

- session-ui no longer needs an Inertia middleware of its own. A host that shared `kitPassword`, `passkeyOffer` or `environment` from its own `HandleInertiaRequests` to work around the fix below can drop them. A host that listed `Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests` in `web` can keep it (it's now a plain Inertia middleware) or remove it when it has its own. Gretel's `breadcrumbs` prop is no longer cleared, so it now reaches pages, which don't read it.

### Fixed

- **session-ui's Inertia props reach the page, and the host's own shares survive them.** session-ui pushed its `HandleInertiaRequests` onto the router's `web` group, and the next `appendMiddlewareToGroup` rebuilt the router's groups from the kernel's and dropped it, so a host never got `kitPassword`, `passkeyOffer` or `environment`, nor the registry's request hooks (the local `?brand=` switch, and forgetting the last request's memo). Had it run after the host's own middleware, its `flushShared()` would have dropped the host's shares. session-ui now shares those props from its provider as closures, evaluated when a page renders, so they sit beside the host's `HandleInertiaRequests` in any order and nothing outlives its request under Octane. Nothing in the kit flushes shared props, and `shell` keeps its one producer. The request hooks run in a new `PrepareKitRequest`, added to `web` through the HTTP kernel so a re-sync can't drop it. The kit's `HandleInertiaRequests` is now a plain Inertia middleware, added only when the host's `web` group has no Inertia middleware. `composer test:inertia-shares` covers the props, a host share, order, the re-sync, a host with no Inertia middleware, no `flushShared` in src, and sequential requests as two people. `composer test:request-hooks` sends two requests through the real HTTP kernel after a host's late `appendMiddlewareToGroup` and checks the hooks ran on each and the request, permission, capability and entitlement memos started afresh. Both run in CI.

## v0.6.7

### Upgrading

- A host that relied on session-ui branding mail with placeholders still unresolved: fill in `kit.brand.*`, or set `kit.brand_mail` to `true` to brand regardless (and keep the `/up` placeholder check).

### Fixed

- **`kit.brand_mail` no longer turns on silently with session-ui, and a default never takes `/up` red.** Unset (`null`), session-ui now brands mail only once the brand is filled in (no `brand.*` placeholders left), and `/up` fails on unresolved placeholders only when the host set `brand_mail` to `true`. Before, adopting session-ui turned branded mail and the production placeholder health check on by default, so a host with its own branding got a red `/up`.

## v0.6.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b1715a1c…7496` → `sha256:c2c5d46e…325f`): `css/kit.css`, `Kit/Overlay/Dialog.vue`, the `Kit/Menu` dropdowns and `Kit/Action/ButtonDropdown.vue` take the theming below. In light mode, the Modal and drawer backdrop goes from 75% to 50% black.

### Fixed

- **Overlays take the host's theme and dark mode.** Every scrim is now one kit token, `--kit-scrim` (50% black, 70% in dark mode, mapped to `bg-scrim`). That covers the kit's Dialog, the modal package's backdrop behind Modal sheets and drawers (the package hard-codes black, so kit.css restyles it; a stacked modal's transparent backdrop is kept), and the host's shadcn Sheet overlay (`[data-slot='sheet-overlay']`). The panels, headers and footers were already on the semantic tokens (`bg-background`, `text-foreground`, `border-border`), and a test now keeps them there.
- **Menus follow the theme too, so a dropdown opened inside a themed or dark sheet matches it.** The Dropdown family (`Dropdown`, `DropdownMenu`, `DropdownItem`, `DropdownLink`, `DropdownModalLink`, `MenuPanel`) and `ButtonDropdown` hard-coded white and grey in light mode, with `dark:` patches. They're now on `bg-popover`, `text-popover-foreground`, `text-muted-foreground`, `divide-border` and `ring-border`, with `bg-accent` for the highlighted item.

## v0.6.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e45e114a…6eba` → `sha256:b1715a1c…7496`): `Kit/Form/Button.vue` takes the fix below.

### Fixed

- **The destructive Button's hover now passes WCAG AA.** The host button's `hover:bg-destructive/90` is translucent, so on a light page white text fell to about 4.3:1. Kit's Button now sets a solid hover, `--color-destructive-hover` or red-700 (6.5:1), in light and dark, as the `success` variant does.

## v0.6.4

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e7f64947…bac5` → `sha256:e45e114a…6eba`): `Kit/Overlay/Dialog.vue`, `Kit/Feedback/Alert.vue` and `Kit/contract.ts` take the changes below. An app that styled the alert's corner dismiss button with its own classes should check it.

### Added

- **Dialog `icon` prop** (boolean, default `true`). `:icon="false"` hides the icon circle on any variant, and the title, description, typed confirmation, `body` fields and buttons sit flush, with no gutter. `variant="danger"` keeps its destructive action. By default the Dialog renders as before. `DialogProps` in `contract.ts` declares it.

### Fixed

- **An Alert's icon and dismiss × line up with its first line of text.** Each sits in a box one line tall, centred in it, on an `items-start` row, so both stay level with the first line in every variant and tier, for one line or many, with a title, with actions, and in a slim banner with its own padding. They were 2px (icon) and 4px (×) off. The × is in the row now, not pinned to the corner, and keeps its 44px target. The title, body and actions share the text column.
- **An Alert holds its own height in a stretching row.** The root is `self-start`, so an alert in a host's grid or flex row beside a taller one no longer grows and leaves empty space below its text.

## v0.6.3

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:a6a3ea4f…9739` → `sha256:93849b84…4a0d`): `GuestShell.vue` takes the change below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e18eab84…3f90` → `sha256:e7f64947…bac5`): `Kit/Form/Button.vue` takes the `success` variant below.

### Added

- **Button `variant="success"`**, a positive or go action (Start, Publish): filled green on the host's default button. A host themes it with `--color-success`, `--color-success-foreground` and `--color-success-hover`; the fallbacks are green-700 on white (5.0:1, AA) and green-800 on hover (7.1:1), in light and dark.
- **`GuestShell` takes `:toaster="false"`**, as `PublicShell` does. A host whose own app shell already mounts the persistent Toaster keeps one, so toasts aren't shown twice. Defaults to `true`, so nothing changes without it.

## v0.6.2

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:5caaf5d8…ca94` → `sha256:a6a3ea4f…9739`).

### Added

- **Shell Layout opt-ins** ([README](README.md#the-shell)). Every default is unchanged.
  - `wordmarkLabel` names the wordmark link in place of the product's name.
  - `guestHeader` draws the header for a guest, with no user menu.
  - The page ground is the `--shell-ground` token, which falls back to the theme's background.

### Fixed

- **No empty drawer.** For a signed-in user with no pages to list (no `nav` or `drawer-nav` slot and no navigation), the Layout draws no drawer or Menu button. The account menu stays in the bar at every width, so Sign out is still reachable on a phone.
- **No false @inertiajs/core warning when core is pinned to exactly vue3's version.** Before, a checkout without `node_modules` (a production `kit:status`) warned if `@inertiajs/vue3` was declared as a range. Now the check reads vue3's pin from `package-lock.json`, or from the base of vue3's declared range. It warns only when core is a range or a different version.

## v0.6.1

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:d9875358…0a11` → `sha256:e18eab84…3f90`): `Kit/Form/richText.ts` takes the fix below.

### Fixed

- **InputRichText no longer locks a document with a unitless font size like 2.03.** `isSafeFontSize` checked three decimals with `Math.round(value * 1000) === value * 1000`, which floating point breaks (2.03 × 1000 is 2029.9999999999998), so a valid mirrored-deck size refused the whole field as an unsafe text style. It now checks `Number(value.toFixed(3)) === value`. A test sweeps 28 real deck sizes.

## v0.6.0

### Upgrading

- **0.6.0: session-ui needs only core and shell.** It keeps the sign-in: `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/TwoFactor`, `Auth/VerifyEmail` and `Auth/ConfirmPassword`, the `Welcome/Timezone` and `Welcome/Photo` steps, and its security-events and user-state migrations. It no longer requires workspaces or access, and loads no workspace, access or Concierge class. Re-pinned: `session-ui` `sha256:bfeb3751…b94c` → `sha256:02200ec2…49b4`, `workspace-ui` `sha256:ff1f114d…1c06` → `sha256:93e9b61a…5bed`.
- **Moved to workspace-ui:** the invitation page `Auth/Invitation/Show` (with its routes `invitations.show` and the acceptance and account posts, the branded invitation email and Concierge's invitation URL), the owner's `Welcome/WorkspaceTimezone` step, and the first-run demo's sign-in route `demo.login`. Each file moves byte for byte. Route names and `kit.routes.session-ui` options stay as they were.
- **An app with session-ui and workspace-ui both installed re-pins once, and nothing is deleted.** The two files change owner, not disk:
  1. `php artisan kit:update plan --lineage=session-ui`, then its `apply`. The plan shows the move: both files are `release`, and none is written or deleted.
  2. `php artisan kit:update plan --lineage=workspace-ui`, then its `apply`. workspace-ui claims both files, without a write, when they are exactly the bytes session-ui released. A file you have edited, even by one byte, is not claimed: the plan shows it as a conflict and isn't executable until you review it.
- **An app with session-ui alone (no workspace-ui) updates in one run, and nothing is deleted or rewritten.** `php artisan kit:update plan --lineage=session-ui` reports each moved file as "no longer owned by session-ui; kept as the host's" (state `release`, listed under `kept`), then its `apply`. Afterwards both files are byte-identical to before and session-ui's installed record no longer lists them: they're yours to keep or remove. No `--lineage=workspace-ui` step follows. workspace-ui is needed only if the app uses invitations: then add it with `kit:add workspace-ui`. Only the 0.5 split's workspace pages carry workspace-ui on for an older install; these two don't.
- **Workspace and account deletion come with workspace-ui.** Their routes (and `kit.lifecycle.workspace_delete` / `kit.lifecycle.account_delete`, off by default, with their purge handlers) are workspace-ui's; account deletion needs Concierge to refuse while you own a workspace. An app with session-ui alone has no deletion route.
- **The shell feature shares the `shell` Inertia prop itself.** A core and shell host gets it without session-ui, so the PublicShell's footer and CTA have their data. It's built by the bound `Tey\Kit\Features\Shell\ShellSource`: `PlainShell` by default, session-ui's `PageShell` when that is adopted. session-ui's middleware keeps the shell's share across its flush, so the prop has one producer and is never shared twice. A host that shared its own `shell` prop for a core and shell site can drop it, or bind its own `ShellSource`.

### Added

- `composer test:session-ui-core`: a core and shell host with no workspaces, access, Concierge, Pennant or multitenancy runs `kit:add session-ui`, boots from its lineages, renders `Auth/Login`, signs a user in with an authenticated session, and loads no workspace, access or Concierge class.
- `Tey\Kit\Features\Shell\ShellSource` and `PlainShell`, and `Tey\Kit\Features\Core\PersonalPages` (a personal page's breadcrumb, which `WorkspaceRoutes::personalPage()` now calls).

### Changed

- `docs/public-site.md` adopts core, shell and session-ui only: no workspaces or access.
- `kit:update --lineage=<receiver>` claims, with no write, a file another installed feature's `moved` entry released to it while both were installed, only when the bytes on disk are exactly the target's. Before, the receiver's plan listed such a file as `claimable` and stopped.
- A `moved` file whose receiver the app neither has nor carries is released and kept as the host's (never deleted), and the update waits on no receiver. `kit:update plan --lineage=<id>` lists such files under `kept`, with `kept_note`.
- Without workspaces, `PageShell` builds the app shell with no workspace, switcher or navigation, and `PageTitle` adds no workspace name.

## v0.5.52

### Upgrading

- **Workspace and account deletion are now off by default.** If your app has relied on either since 0.5.40, it must now do two things: set `kit.lifecycle.workspace_delete` / `kit.lifecycle.account_delete` (`KIT_WORKSPACE_DELETE=true` / `KIT_ACCOUNT_DELETE=true`), AND bind its purge handler, `Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData` / `Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData`. A handler that does nothing must be bound explicitly. Without the flag, the pages are 404 and hidden. With the flag but no handler, the deletion refuses and deletes nothing ([docs/lifecycle-events.md](docs/lifecycle-events.md)).

### Fixed

- **Deletion no longer runs without the app's own data purged first.** The bound purge handler runs inside the deletion's transaction, before the kit's own rows go. If it throws, everything rolls back. While deletion is off, its routes return 404 and the settings pages hide it. On without a handler, it refuses with a configuration error. `kit:status` and the workspace-ui / session-ui plans report both flags and their contracts: off, on, or on without a handler. Tested with foreign keys enforced, on SQLite and PostgreSQL.

## v0.5.51

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:265de09b…fa4b` → `sha256:5caaf5d8…ca94`): the kit-types host step is also met by `vendor/tey/kit/types/kit.d.ts` in plugin-vue's `script.globalTypeFiles` (your Vite config), not only a `tsconfig.json` include.

### Fixed

- **Plans recommend `kit.mcp.consent=host` for more hosts that run their own consent.** The check now also reads the app's Composer PSR-4 roots (`src/`, say) beside `app/`, `bootstrap/`, `routes/` and `config/`. It recognises `Passport::authorizationView(…)` and a bind or subclass of Passport's `AuthorizationController` or `ApproveAuthorizationController`, as well as `AuthorizationViewResponse` and approve middleware. `kit:update plan --lineage=mcp` carries the same non-blocking step as `kit:add` and `kit:adopt` (tested).
- **`kit:permissions:sync` says when the app has no role a default names.** It printed "Default role not configured, skipped: admin", which read like a misconfiguration. It now says "No 'admin' role in this app (concierge.roles does not name it), so nothing to grant it: …" and lists the grants that role would have had. Owners still get theirs. The report gains `skipped_grants` (role → permissions).
- **A `mention` host step may name an `or_file`**, a second place the same text may be. The shell's kit-types step uses it to accept the Vite config.
- **Documented: in `consent=host` mode, the mcp lineage's `Mcp/Authorize/Page.vue` stays generated and inert** ([docs/mcp-consent.md](docs/mcp-consent.md)). The kit sets no authorization view, so nothing renders it. It isn't conditional on config, because that would change the plan's identity with the config value.

## v0.5.50

### Added

- **`MoveToWorkspace` honours a model's own, row-aware move ability** ([docs/models.md](docs/models.md)). A model may define `workspaceMoveAbility(): string`, a Gate ability or policy method checked in each workspace with the row, `Gate::allows($ability, [$row, $workspace])`. That lets an app's policy allow an owner to self-move their own row without `members.manage`. The actor must still be an active member of both workspaces, and an ability nobody defines is refused (`ability_unknown`). Models without the method keep `kit.workspaces.move_ability`.

## v0.5.49

### Added

- **`MoveToWorkspace`** (`Tey\Kit\Features\Access\Operations`, [docs/models.md](docs/models.md)) moves one `BelongsToWorkspace` row from the current workspace to another and keeps its id, so rows that reference it stay attached. Use it, for example, to re-bind an app to a new workspace instead of deleting and re-creating it. It's refused for:
  - another workspace's row, or a row changed concurrently (`workspace_mismatch`);
  - the same or a missing target (`workspace_same`, `workspace_not_found`);
  - an actor without the move ability in both workspaces (a 403). The ability is `kit.workspaces.move_ability`, by default `members.manage`.

  It rewrites the column through one guarded query (`where` the source, so a concurrent move changes nothing), with no bypass of the updating guard, and audits `workspace.row.moved`, in one transaction. CI runs this check on PostgreSQL too.

## v0.5.48

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:3286f349…6f3e` → `sha256:d9875358…0a11`): `kit.css` gains `.kit-theme-light` (below).
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:cb8d242c…3409` → `sha256:265de09b…fa4b`): `Partials/PublicShell.vue` takes the opt-in branded-site props below. Its defaults are unchanged.

### Added

- **PublicShell can host a branded site** ([docs/public-shell.md](docs/public-shell.md#a-branded-site)). Every option is opt-in:
  - `width="site"` (`max-w-6xl`), with `align-bars` putting the header and footer rows on the same measure;
  - `tone="bar"`, a header in the shell's `--shell-bar` tokens rather than `--primary`;
  - `:pin-footer="false"` for a footer that flows after the content, and `:padded="false"` for an unpadded main;
  - `footer-links` and `footer-note`, with a `footer-mark` slot, for a footer without session-ui's shared shell;
  - a `mark` slot that links to `home-url` (named by `home-label`) when there's no product;
  - `theme="light"`, which keeps a page with no dark styling light under `.dark` by restating the neutral surface tokens, not the brand's primary (kit.css's `.kit-theme-light`).

## v0.5.47

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b45f675f…5b7b` → `sha256:3286f349…6f3e`): `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts` take `families` (below).

### Added

- **InputRichText `families`** ([docs/input-rich-text.md](docs/input-rich-text.md)): with `allow` naming `textStyle`, `:families="['sans', 'display']"` limits a style's font family to the app's own names. Every name must be in the list, or the document gives `content-error`. Without it, the character-class rule stands, unchanged. `isSafeFontFamily(value, allowed?)` takes the same list.

## v0.5.46

### Added

- **`BelongsToWorkspace::SCOPE`** (`'workspace'`), the global scope's name, pinned by a test: `withoutGlobalScope(BelongsToWorkspace::SCOPE)` is the stable opt-out for code that must cross workspaces.

### Fixed

- **A route that binds a workspace model outside any workspace is a 404, not a 500.** `BelongsToWorkspace` now answers `resolveRouteBinding` and `resolveChildRouteBinding` with nothing when there's no current workspace, instead of throwing `workspace_required`. Inside a workspace, another workspace's row is still a 404 through the scope, and every other query outside a workspace still throws, so it stays fail-closed.

## v0.5.45

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:371e614a…b004` → `sha256:b45f675f…5b7b`): `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts` take `allow` (below). Core now also lists `@tiptap/extension-text-align` and `@tiptap/extension-text-style` (`^3.32.0`) under `requires.npm`, so add them to `package.json`.

### Added

- **InputRichText `allow`, for mirrored content** ([docs/input-rich-text.md](docs/input-rich-text.md)): `:allow="['textAlign', 'textStyle']"` preserves and round-trips a paragraph's or heading's alignment (`left`, `center`, `right`, `justify`) and the `textStyle` mark (`color`, `fontFamily`, `fontSize`). Each value is checked strictly (`isSafeColor`, `isSafeFontSize`, `isSafeFontFamily`) and never passed through as CSS. A font size may also be a bare number, a scale the app resolves (kept a number), and a family name may be quoted. Anything else is still refused with `content-error`. The default is none, so the strict subset is unchanged, and there are no new toolbar controls.

## v0.5.44

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:0e3e40c2…b16c` → `sha256:cb8d242c…3409`): new host steps for the `@kit/ui` alias (`vite.config.ts`, `tsconfig.json`) and for the kit's types. The types step asks for `"vendor/tey/kit/types/kit.d.ts"` in `tsconfig.json`'s `include` and replaces the step that asked your transformer to read `vendor/tey/kit/src`. If your transformer already writes the `Tey.Kit` namespaces, leave the include out: both would declare each type twice.
- Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:715b4511…8b49` → `sha256:ff1f114d…1c06`): the dashboard page is typed `Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel`. **A page of your own typed with the kit's `DashboardViewModel` TypeScript name moves to `WorkspaceDashboardViewModel`.** The PHP class is unchanged.
- **Defining kit page props from the shipped types (`defineProps<Tey.Kit.…>()`):** list `vendor/tey/kit/types/kit.d.ts` in `@vitejs/plugin-vue`'s `script.globalTypeFiles` as well as in `tsconfig.json`, so Vue's compiler resolves the props.
- **A host that points `@kit/ui` at its own module** must export every name the kit's pages import from it. The README now lists them all, including `InputSelect`, which mcp's Authorize page uses.

### Fixed

- **The kit's `Tey.Kit.*` types have one supported source: `vendor/tey/kit/types/kit.d.ts`** ([README](README.md)). A host whose transformer writes one flat module couldn't produce the global namespaces the kit's pages use. Transforming all of the kit's `src` also collided on `DashboardViewModel`. Including the shipped file in `tsconfig.json` covers every kit page, and stays in step with the installed kit.
- **The shell's host steps include the `@kit/ui` alias** (in `vite.config.ts` and `tsconfig.json`), which its pages import through.
- **The dashboard view model's TypeScript name is `WorkspaceDashboardViewModel`**, so it no longer collides with an app's own `DashboardViewModel` in one generated module.
- **`types/kit.d.ts` resolves in Vue's SFC compiler.** Its nested namespaces had no `export`. TypeScript accepts that in an ambient declaration, but `defineProps<Tey.Kit.Features…>()` failed to compile ("Unresolvable type reference"). Every nested level is now `export namespace`. `bin/typecheck-components.sh` compiles two pages' `defineProps` against it (`typecheck/sfc-kit-types.mjs`), and a vue-tsc fixture types a page with it.
- **`types/kit.d.ts` includes the kit's enums.** `ShellKind` was missing, so `FocusShellData.kind`, `GuestShellData.kind` and `ShellData.kind` were typed `undefined`. They're now `'app' | 'guest' | 'focus'`.

## v0.5.43

### Upgrading

- **A host with its own OAuth consent sets `kit.mcp.consent` to `host`** (`KIT_MCP_CONSENT=host`). Since v0.5.40 the kit has run Passport's consent itself, so an app whose consent page posts another field (`workspace_id`, say) and keeps its own grants got 403 on every approve. Nothing changes for a host on the default, `kit`. Any value other than `kit` or `host` is refused when the mcp feature boots. See [docs/mcp-consent.md](docs/mcp-consent.md#a-host-with-its-own-consent).

### Fixed

- **A host with its own consent can set kit.mcp.consent=host.** The kit then attaches no `BindConsentWorkspace` to `passport.authorizations.approve`, doesn't bind `KitAuthorizationController` over Passport's `AuthorizationController` or set its Authorize page as the authorization view, and a code exchange binds no kit connection, so Passport's approve behaves as it did before v0.5.40 and no second connection row is written. `kit:adopt mcp plan` and `kit:add` show a `kit.mcp.consent` step (attention, not blocking) for a host that binds `AuthorizationViewResponse` or adds its own approve middleware. `composer test:mcp-host-consent` covers it.

## v0.5.42

### Upgrading

- **Transfer ownership asks for the workspace's name.** `PUT /@{workspace}/settings/ownership` now requires `confirmation` (the workspace's name, typed) alongside `password`, and only the owner gets past it: an admin gets 403 and a non-member 404 before anything else is checked. Settings › People's Make owner now opens the new Transfer ownership modal instead of a password dialog, so `Workspace/Settings/People/Page.vue` changed. A client that sends the transfer itself must add `confirmation`. `ManagePeople::transferOwnership()` takes it as a fourth argument. Re-pin: workspace-ui `sha256:28486124a95a6a6ea04e5b20229460de4a98a268d36786514e56447c9af88da0` to `sha256:715b451103ce91ee3a3bd4e34b98e4f27f00e8e5188ca42c0a8896546bd48b49`. `kit:update --lineage=workspace-ui` brings the changed People page and the three new pages.
- **Settings has a Danger zone group.** Delete workspace (listed to whoever manages members, opened only by the owner) and Delete account (under Account) are registered settings sections. A host that asserts its exact section list sees two more entries.
- **Purge in a plain queued listener.** `WorkspaceDeleted` and `AccountDeleted` are dispatched after the commit, once the workspace or user is gone, so a `TenantAware` listener can't make the workspace current. See [docs/lifecycle-events.md](docs/lifecycle-events.md#a-queued-purge-listener).

### Added

- **Transfer ownership modal** ([docs/lifecycle-events.md](docs/lifecycle-events.md#transfer-ownership)): `Workspace/Settings/People/Transfer/Page`, at `GET /@{workspace}/settings/people/members/{membership}/transfer`, is for the owner only. The workspace's name is typed to confirm and the password asked for again, and both are checked on the server. A scoped guest is never offered and is refused on the server, whenever Concierge has `isScopedGuest()` (0.9). The kit still requires `tey/concierge` ^0.8.4.
- **Delete workspace** ([docs/lifecycle-events.md](docs/lifecycle-events.md#delete-a-workspace)): Settings › Delete workspace (`Workspace/Settings/Delete/Page`, `DELETE /@{workspace}/settings/delete`) is for the owner only, with the workspace's name typed. `DeleteWorkspace` deletes the workspace in one transaction, with its Concierge, spatie, settings, access, MCP and notes rows. `audit_events` rows are never deleted.
- **Delete account** ([docs/lifecycle-events.md](docs/lifecycle-events.md#delete-your-account)): `/me/delete` (`Workspace/Account/Delete/Page`) takes your current password. It's refused while you own any workspace, and the refusal names them. Otherwise you leave every workspace through Concierge's `LeaveWorkspace`, your account and its rows are deleted, and the session is signed out and invalidated.
- **Lifecycle events** ([docs/lifecycle-events.md](docs/lifecycle-events.md#lifecycle-events)): `WorkspaceDeleting` and `AccountDeleting` are dispatched inside the transaction and are informational, with no veto. `WorkspaceDeleted` (`workspaceId`, `slug`, `memberUserIds`, `actorId`) and `AccountDeleted` (`userId`, `workspaceIds`) implement `ShouldDispatchAfterCommit`: they're dispatched only after the commit and never on a rollback. `composer test:lifecycle-pages` covers it.

## v0.5.41

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:58df39ce…6562` → `sha256:0e3e40c2…b16c`): its PublicShell host step now looks for the kit's import path. A layout callback that imports `@/pages/Workspace/Partials/PublicShell.vue` still meets it. One that uses a PublicShell of your own no longer does, and is pointed out as attention.
- **Adopting hosts, before a full rollback:** in your `create_workspaces` migration's `down()`, drop any `audit_events` foreign key on `workspace_id` before dropping the table (the snippet is in the README, under Rolling back). The kit's audit key points at your table, and `migrate:reset` runs your `down()` before the kit's.

### Fixed

- **The shell's PublicShell host step no longer reports "met" for a PublicShell of the host's own.** It matched the bare word, so `import PublicShell from '@/layouts/PublicShell.vue'` met it. It now matches the kit's import path, `pages/Workspace/Partials/PublicShell`, in the `@/` or relative form, with or without `.vue`.
- **`kit:adopt` with no arguments lists what can be adopted** instead of failing on a missing feature and action. It names the features already adopted and, for each other feature, how many of its files are on disk and the plan command to run. `--json` gives `{status: "list", adopted, adoptable}`. A feature without an action asks for one.
- **Rolling back an adopting host is documented, and the plan says it.** The README's Rolling back section gives the `create_workspaces` `down()` that drops the kit's `audit_events` key first. A plan that adopts the host's `workspaces` table lists it as a host step.
- **CI runs the audit-key check on PostgreSQL.** `composer test:audit-workspace-fk` sat in the PostgreSQL job with no database settings, so it ran on SQLite. It now gets the job's PostgreSQL connection, covering 000204 to 000209 and the rollback `down()` on PostgreSQL.

## v0.5.40

### Upgrading

- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:d1a28359…36f3` → `sha256:7e095959…f01b`): new page `Mcp/Authorize/Page.vue` and migration `0001_01_02_000602_add_oauth_client_to_kit_mcp_connections` (a nullable `connections.oauth_client_id`). Run the migration.
- **The consent screen is now an Inertia page, not the `kit::mcp.authorize` Blade view**, which is removed. An app that overrode it in `resources/views/vendor/kit/mcp` should move its changes to the generated page. Approving now needs a workspace: the page posts `workspace`, and an approve without one is refused (403).
- **Revoking another member's connection** is new and needs `kit.mcp.manage_ability`, by default the new `connections.manage` ability (permission `workspace.connections.manage`, held by owners and admins). Run `php artisan kit:permissions:sync` so existing workspaces' roles get it. Revoking now also revokes the connection's access and refresh tokens, unless another live connection runs on the same token.
- **Skipped consent is narrower.** Passport's `AuthorizationController` is bound to the kit's `KitAuthorizationController`. A live token alone no longer skips the consent page: consent is skipped only when a live connection for the client exists in the current workspace, and that connection is reused. A connection made before this release is never reused, so its first re-authorization shows the page. Reuse goes through the cache, so use a shared store across servers.
- **A new access token binds only its own connection.** A refresh moves only the refreshed token's connection, recognised by the access token Passport's refresh grant revokes. A code exchange binds only its consent's connection. A connection whose token expired without a refresh is no longer picked up by the person's next token for the client: the assistant authorizes again. Before, every spent connection for the person and client moved onto any new token.
- **`kit.mcp.server_url` (default `/mcp`) is now a real MCP endpoint.** Point assistants at it. `/mcp/connections/{connection}` keeps working.

### Added

- **MCP consent page** ([docs/mcp-consent.md](docs/mcp-consent.md)): Passport's authorization view is the kit's `Mcp/Authorize/Page`. It shows the client's name, the requested scopes in plain words (an unlabelled scope verbatim), and a choice among the person's active workspaces, defaulting to the current one, with Approve and Deny as CSRF-protected posts. Deny is Passport's `access_denied` redirect. Approve refuses (403) a workspace the person can't open a connection in, then makes the connection in the chosen workspace, and the code exchange binds it to the new token.
- **One MCP address**: `POST /mcp` opens the connection the access token is bound to, and answers 401 when there is none or the token is revoked, expired or another person's. A connection's own address must match the token's connection (403). The AI assistants page shows the address with a copy button.
- **Revoking from the AI assistants page**: your own connection with the connection ability, any member's with `kit.mcp.manage_ability` (owners and admins), who also see every member's connection; others see only their own. `composer test:mcp-authorize` covers consent and revoke through Passport's real routes.

## v0.5.39

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:642ca1e4…60e9` → `sha256:40fdd76c…81c4`), then `php artisan migrate`: the new `0001_01_02_000209_add_missing_audit_events_workspace_id_index` migration below. It does nothing where the index exists.

### Fixed

- **`audit_events.workspace_id` is indexed on every host.** Core creates `audit_events_workspace_id_index`, but a database that reached the column another way could lack it, and each workspace's audit query then scanned the table. The additive migration `000209` creates it only when no index covers `workspace_id` alone (a MySQL foreign key brings its own), never a second one. Its `down()` leaves the index. The impact is performance only. `composer test:audit-workspace-fk` covers a database with it and one without, on SQLite and, in CI, PostgreSQL.

## v0.5.38

### Fixed

- **`kit:add` preview no longer crashes on a host without a feature's packages.** On a single-tenant app without `spatie/laravel-multitenancy`, `kit:add session-ui --json` died with `Trait "Spatie\Multitenancy\Models\Concerns\ImplementsTenant" not found`, because the preview loaded the workspace model to check it. The plan is now what it always was for missing packages: the `composer require` among its host steps, not executable until they're installed. The host model checks (`user_model`, `workspace_model`) for that feature are skipped and listed under `deferred`, with a step to re-run the preview after `composer require`. Every preview (kit:adopt's plan too) also carries a `status`, `ready` or `blocked`. A genuine refusal under `kit:add --json` (an unknown feature, say) prints `{status: "refused", error, hint}`, the shape kit:adopt prints.

## v0.5.37

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:59db0c23…6c64` → `sha256:371e614a…b004`): `Kit/Form/InputSelect.vue` takes the fix below.

### Fixed

- **InputSelect no longer leaves `aria-controls` and `aria-activedescendant` pointing into a closed list** (axe critical, `aria-valid-attr-value`). After open→close, or a search then Escape, Reka kept the unmounted list's id and the last highlighted item's id on the input. Both are now absent while the list is closed, and Reka's own values stand while it's open. A caller's own `aria-controls` still wins. A test that runs axe only before any select opens can now run it after.

## v0.5.36

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:4ebd502f…3e95` → `sha256:bfeb3751…b94c`): new `Auth/ConfirmPassword/Page.vue`. Fortify's password confirmation needs no feature flag (its `password.confirm` routes are always registered), so the kit's fortify defaults are unchanged. The kit now binds Fortify's `PasswordConfirmedResponse`: after confirming, an intended URL off the app goes to `kit.home` instead.

### Added

- **Password confirmation page** ([docs/onboarding.md](docs/onboarding.md#password-confirmation)): `Auth/ConfirmPassword` for Fortify's `password.confirm`, with a Cancel link back to a same-origin `cancel` query or referer (else `kit.home`), and a return to the intended URL only when it is on the app. Both use `SafeRedirect::sameOrigin()`. `composer test:onboarding` and `composer test:auth-only` cover it.

## v0.5.35

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9c6bdc8c…922d` → `sha256:59db0c23…6c64`): new `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts`, exported from `@kit/ui`. **Core now lists `@tiptap/core`, `@tiptap/extension-code`, `@tiptap/extension-placeholder`, `@tiptap/pm`, `@tiptap/starter-kit` and `@tiptap/vue-3` (`^3.32.0`) under `requires.npm`**: add them to `package.json`, or your build and `vue-tsc` fail on the new field. The plan flags any that are missing, as attention.
- **Moving to InputRichText from an app's own tiptap field:** private links are a pattern the host passes, `privateLinks` (a RegExp, matched by `isSafeLink`), not an `allowPrivateLinks` boolean. A boolean can't say which links are the app's own. An app that passed `allow-private-links` passes its own scheme instead, as in `:private-links="/^app:\/\/record\/[0-9]+$/"`, and its server enforces the same pattern. `isSafeLink(value, privateLinks?)` takes the pattern too.

### Added

- **`InputRichText`** ([docs/input-rich-text.md](docs/input-rich-text.md)): a rich-text field on tiptap. Its `v-model` is a strict, portable ProseMirror JSON subset (`RichDocument`: paragraphs, H2 and H3, blockquotes, lists, and bold, italic, strike, inline code and links), validated both ways, so a document outside the subset locks the field and emits `content-error` instead of being silently dropped. It has a configurable toolbar (`tools`) with a Link settings popover that refuses unsafe links (`isSafeLink`, with an optional host `privateLinks` pattern), a placeholder, `read-only` and `disabled`. Pasted or dropped files become a `file-intent` for the host to upload as its own image blocks, so no URL enters the document.

## v0.5.34

### Upgrading

- **On adoption, seed operators before deploy or the platform owner loses tenant admin.** Workspaces gates `concierge.manage-tenants` on the `operators` table, not Concierge's `concierge.tenant_invitations.platform_owners`. Run `php artisan kit:operators:seed --from-platform-owners` (`--dry-run` first) after migrating. Seeding between a `kit:add` preview and `--apply` changes the plan identity: re-run the preview and apply with the new plan.

### Added

- **`kit:operators:seed --from-platform-owners`**: seeds `operators` from the configured platform owners, matching verified, active users by trimmed, lower-cased email, with the Gate's own verified-and-active test (`Operators::refusal`). Idempotent; `--dry-run` writes nothing; reports matched, already-operator, skipped (with why) and unmatched emails.
- **Empty-operators attention**: when platform owners are configured and `operators` is empty, `kit:add`/`kit:adopt` plans for workspaces carry a non-blocking `operators` step naming the users that would be seeded and the command, `kit:status` warns, and boot logs a warning in `local`/`testing` only. Addresses are masked (`j***@example.com`) with counts everywhere except an interactive `--dry-run`'s unmatched emails. Both read `config('concierge.tenant_invitations.platform_owners')`, never `env()`. `composer test:operators-seed` covers it.

## v0.5.33

### Upgrading

- `workspaces` is re-pinned (`sha256:ba061f45…4212` → `sha256:642ca1e4…60e9`) for its manifest only: no file changes. Run `kit:update plan|apply` to record the new pin.

### Fixed

- The `workspaces` feature now accepts `tey/concierge` 0.9 (`^0.8.4 || ^0.9`). Before, a host on concierge 0.9 failed the compatibility check for `workspaces` and every feature that requires it (`access`, `session-ui`, `workspace-ui`).

## v0.5.32

### Upgrading

- core's identity changes (`.env`/`.env.example` are now host-owned in Mod): run `kit:update core apply`; no files change, only core's installed record.

### Fixed

- Every declared `host_owned` path, dot-prefixed ones such as `.env` and `.env.example` included, now reaches Mod's `hostOwned`, so Mod itself refuses another lineage that owns or claims them (`host_owned:tey/kit:core`). The kit-side filter for Mod 0.9's dot-prefix limit is gone (#32).

## v0.5.31

### Added

- **`types/kit.d.ts` for hosts without a TypeScript generator**: the kit ships its generated `Tey.Kit.*` types. A hand copy at `resources/js/types/kit.d.ts` that a release has outdated is pointed out in the plan and `kit:status`, naming the types it lacks (a new view model). A host whose own `spatie/laravel-typescript-transformer` writes its types is never told to copy. `composer test:kit-types` keeps the file in step with the kit's classes.
- **Every PHP stub is Pint-clean** under Laravel's preset, so a host that runs Pint on the files an update wrote passes. `composer test:pint-stubs` checks it in CI (`bin/pint-stubs.sh --fix` applies it).

### Fixed

- **A schedule host step no longer reports `model:prune` as unscheduled when it is.** Laravel quotes option values when it compiles an event, so `Schedule::command('model:prune', ['--model' => [McpIntent::class]])` (or a string value, or several models) never matched the step. The command's name and options are now matched as tokens, wherever the schedule is defined.
- **The `@inertiajs/core` advice keeps a declaration your code imports.** A host that imports its types (`Method`, `PageProps`) must declare it (the standards' `js-imports` rule), so the advice is now "keep it declared at `@inertiajs/vue3`'s version (exactly X)", not "remove it". Hosts that don't import it still get the old advice.
- **Host steps read `vite.config.js` and `.mjs`, and the app's real entries.** The shell's alias check reads whichever Vite config exists. The layout-callback check (`resources/js/app.ts`) also reads every script entry the Vite config names (`next.ts`), and the message names the file it read.

## v0.5.30

### Added

- **CI checks the changelog on every pull request** (`bin/changelog-check.php`, the `changelog` job): `## Unreleased` must be the top section and appear once, and every released `## vX.Y.Z` section must match the base branch byte for byte, so a merge that splices lines under a released heading fails. A release (a new version at the top in place of `## Unreleased`) and a pull request that leaves the changelog alone pass. `composer test:changelog-guard` covers the rules.

## v0.5.29

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:6c30a7c8…85de` → `sha256:ba061f45…4212`): `0001_01_02_000201_create_permission_tables.php` and `0001_01_02_000203_add_slug_to_kit_workspaces_table.php` take the changes below. An app that already made these edits by hand matches the new stubs, so the update reconciles it.

### Fixed

- The workspaces slug migration stub sorts its `use` imports, so a host running Pint (`ordered_imports`) passes on the generated file. No schema change.
- The permission tables migration stub checks that spatie/laravel-permission's published migration has `up()` and `down()` before calling them, so a host's PHPStan (paths including `database/`) no longer reports `method.notFound` on the generated file. If either method is missing, the migration throws instead of failing silently.

## v0.5.28

### Upgrading

- **An app with stale journals:** if `kit:update` or `kit:add` refuses with `pending:tey/kit:<feature>` although every update completed, run `php artisan kit:update status`. A journal it lists as `stale, the target identity is already installed` is safe to clear: run `php artisan kit:update status --clear-stale`. A `Pending` one is a genuinely unfinished update: resume it with the printed command, and never move it aside.

### Added

- **`kit:update status`** lists each journal Mod reads as unfinished, as `Pending` (the target identity is not installed: resume it) or `Stale` (the target identity is already installed), and exits nonzero while any remains. **`--clear-stale`** removes the stale ones only, printing each one it cleared; a pending journal is never touched. `composer test:adopt` covers both.

### Fixed

- **`kit:adopt` no longer reports an older release's files as identical.** A file whose bytes match an older known release, not this one, is now `behind` (listed in `behind` with that release, and an `UPDATE:` line in the text plan). The adoption still claims it as it is, without writing, and the `kit:update plan --lineage=<feature>` that follows brings it up to date. Before, files 0.5.19 had changed (`index.ts`, `RadioCardGroup`, the Workbench) read as identical on a host installed at 0.5.13.
- **A no-op update no longer leaves an earlier no-op update's journal pending.** Mod reads a journal as unfinished while the installed record has not committed its plan and is still at its baseline. A no-op update's baseline is its target, so once a later update of the lineage committed a different plan (a changed input, such as the dependency lock, gives it a new identity), the earlier journal read as unfinished forever, and every plan was refused with `pending:<lineage>`. A completed `kit:update apply --lineage` now clears its lineage's stale journals, and `kit:update resume --lineage --intent` on a journal whose target identity is already installed clears it and reports `completed` with `cleared` and a message, instead of leaving it. The `pending:` hint no longer offers a stale journal to resume. A journal whose target is not installed is never removed.

## v0.5.27

### Upgrading

- **Laravel starter-kit hosts** (auth pages in `resources/js/pages/auth`): `kit:add session-ui` now reports the case conflict instead of failing. Run `php artisan kit:add session-ui --adopt-case` to review the rename, then `--adopt-case --apply` to make it, then preview and apply `kit:add session-ui` as usual. Search for `auth/` page names it does not rewrite (see [docs/catalog.md](docs/catalog.md)).
- **If `.gitignore` ignores `/.mod-maintenance`, or `.mod-maintenance/installed/` was never committed**, plans now point it out: commit `.mod-maintenance/installed/` and `installed.json`, and ignore only `/.mod-maintenance/journal-*.json`, `/.mod-maintenance/lock` and `/.mod-maintenance/owner.json`.

### Added

- **Case conflicts are named in the plan.** A declared path the host has under another case is listed in `case_conflicts` (declared and existing path), its feature's row is not executable, and a blocking host step says how to fix it. `kit:add --json` returns that plan; before, the plan threw Mod's "Case alias or collision at declared path."
- **`kit:add --adopt-case`** renames each conflicting host directory to the declared case (`git mv` in a git checkout, in two steps for case-insensitive filesystems) and rewrites the Inertia render names under it in `routes/` and `app/Http/`, then stops: it installs nothing and prints the next commands (the `kit:add` preview and its apply with `--expected-plan`). Without `--apply` it lists the changes and writes nothing. It refuses file conflicts and a target that already exists under the other case.
- **`kit:adopt plan --json`** prints only JSON, in `kit:add`'s plan schema (one `adopt` row), with the adoption's own keys beside it (`current`, `from`, `adopted`, `kept`, `orphans`, `removals`, `apply`). `apply`/`rollback --json` print no text lines, and a refusal is JSON too. Without `--json` the output is unchanged.
- **An attention host step when `.gitignore` ignores `.mod-maintenance/installed/`**, with the replacement lines; never blocking. In a git checkout it also fires when the installed records are untracked, and says to commit them. `kit:add`, `kit:adopt` and `kit:status` show it.
- **`kit:update` with kit files but no lifecycle records** names uncommitted records from another checkout as the first cause, then the `kit:adopt` route. `composer test:starter-case` covers all four.

## v0.5.26

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:bc8a9ad6…fa87` → `sha256:9c6bdc8c…922d`): `Kit/Feed/ActivityEntryRow.vue`, `Kit/Feed/activity.ts`, `Kit/Layout/Collapsible.vue` and `Kit/index.ts` take the changes below.

### Added

- **ActivityStream `effectiveOn`**: an entry's optional `effectiveOn` (ISO date) and `effectiveOnLabel` render "effective <label> · recorded <timestamp>" (`ActivityEntryData` takes both).
- **ActivityStream accepts a host's own intents and a null headline.** An `intent` outside the kit tones shows as neutral (`activityTone`), with a warning in development, instead of being refused. `ActivityEntryData` now accepts any intent. A `null` headline renders the row with its time and meta only.

### Fixed

- **The theme host step reads every CSS entry.** It reported missing colour tokens when `kit.css` was imported from a stylesheet other than `resources/css/app.css`. It now also reads each CSS entry named in `vite.config.{ts,js,mjs}` and follows local `@import`s transitively (inside the app, never through a link, at most 50 files). The Vite config is read as text, never run.
- **The Collapsible chevron no longer shows as a half-turned "┘" when open.** It rotated one ChevronDown, which a host's transition setup could leave stalled mid-turn. It now swaps ChevronRight (closed) for ChevronDown (open), with no rotation, and follows the trigger's colour. An ActivityStream group's toggle reads "Show fewer" while open, instead of "Show all 31".

## v0.5.25

### Added

- **Host guides**: a public-site recipe (docs/public-site.md), upgrading from kit 0.2.x (docs/upgrading-from-0.2.md), and which `.mod-maintenance` files a host commits, with fresh-checkout setup (docs/host-repository.md).

## v0.5.24

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:09e9f8af…8824` → `sha256:bc8a9ad6…fa87`): `Kit/Layout/FloatingToolbar.vue`, `Kit/Layout/toolbarAction.ts` and `Kit/Action/actionIcons.ts` take the changes below. **A FloatingToolbar with `items` now keeps one row at every width**: items that don't fit go into its ⌄ menu instead of wrapping to a second row.

### Added

- **FloatingToolbar client items overflow, group and go icon-only** ([docs/workbench.md](docs/workbench.md)). When `items` don't fit the width the pill has, they move into a ⌄ "More actions" menu: whole trailing groups first, then trailing items, keeping the first N. Measuring is a ResizeObserver with hysteresis (`fitToolbarItems`), and a menu row reaches `onAction` after the menu closes. A `group` per item draws a rule between groups, inline and in the menu. `iconOnly` makes the label the accessible name and tooltip.
- **`actionIcons` covers the common editor set**: `align-left`, `align-center`, `align-right`, `arrow-up`, `arrow-down`, `trash`, `image`, `eye`, `eye-off`, `chart` and `poll` (with `plus` and `copy`), each imported by name.

## v0.5.23

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:3b960b2a…e0ee` → `sha256:6c30a7c8…85de`): the workspaces and permission migrations work under `migrate --pretend`, and `000204` to `000207` say they are forward-only. No schema change.
- Rolling back leaves two things in place, on purpose. `audit_events.workspace_id` stays nullable with its `ON DELETE SET NULL` key, since `000204` to `000207` are forward-only. The access seed (the permissions `kit:permissions:sync` created and their owner and admin grants) also survives; only `kit_permission_defaults` is dropped. The README's "Rolling back" explains why and how to remove the permissions by hand.

### Fixed

- `php artisan migrate --pretend` no longer throws at `0001_01_02_000200` ("kit.workspaces.adopt is on … does not exist yet") on an adopting host. Pretend runs no query, so `Schema::hasTable()` can't see the host's tables. With `kit.workspaces.adopt` or `kit.access.adopt` on, the workspaces and permission migrations now take the adoption path and list its statements. `composer test:adopt-fresh` covers it.

### Documentation

- `docs/models.md` lists what each kit table with a `workspace_id` key does when the workspace is deleted. `entitlements` and `complimentary_grants` stay restrict as commercial records, so a workspace with them is deleted deliberately, after removing or archiving them.

## v0.5.22

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:3f155ade…11fd` → `sha256:58df39ce…6562`): `Partials/PublicShell.vue` takes the `toaster` prop below.

### Added

- **`<PublicShell :toaster="false">`**: the PublicShell mounts the kit Toaster by default (as it has since v0.5.17), so a public form's flashed toast shows after its redirect. `toaster` false leaves it out, for a page that mounts its own.

## v0.5.21

### Fixed

- **A table search or filter with a comma no longer returns a 500.** TableQuery registered search and filters with Spatie's default `,` delimiter, so `?filter[search]=Find, Member` arrived as an array and was cast to a string. Search and filters now take one value whole (`delimiter('')`; no kit filter is multi-value), and an array smuggled in as a search, filter or sort value is ignored instead of cast. A choice filter given `admin,viewer` refuses it as one unknown value (`InvalidFilterValue`), as before.

## v0.5.20

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:86dec1df…8ce7` → `sha256:09e9f8af…8824`): new `Kit/Feed/ActivityStream.vue`, `Kit/Feed/ActivityEntryRow.vue`, `Kit/Feed/activity.ts` and `Kit/Feed/useCursorFeed.ts`, exported from `@kit/ui`.
- Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:e5f861f9…6950` → `sha256:28486124…8da0`): the dashboard's `Partials/ActivityWidget.vue` draws through ActivityStream. **The activity widget's `items` are now `ActivityEntry` objects** (`id`, `kind`, `headline: { html }`, `occurredAt`…), not `{ id, actor, event, at }`. A host that edited that partial or reads the widget's items moves to the new shape.

### Added

- **`ActivityStream`** ([docs/activity.md](docs/activity.md)): a read-only, source-agnostic stream of activity entries, newest first, cut into days (`days`), with an `empty` state, `dense` rows and an opt-in `live` region. Entries carry a headline that is exactly one of `{ text }` or `{ html }`. HTML is rendered only through the stream's trusted path, and the host's server must have escaped it. Entries also carry a `<time datetime>`, a kit tone, `{ label, value }` meta, and groups that open in a Collapsible and say when they were cut short.
- **`useCursorFeed`** pages a stream over one Inertia prop: partial reloads with the cursor, pages that accumulate, an empty page with a cursor followed, a changed sync token reading the head page again, and Load more keeping focus. `createCursorFeed` is the same rules without Vue.
- **`ActivityEntryData`** (`Tey\Kit\Features\Core\Data`): the PHP shape hosts map their records into, with `text()`, `trustedHtml()` and `group()`. It refuses a headline with both kinds or neither, an unknown tone, and malformed meta.

## v0.5.19

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:31138a46…dc77` → `sha256:86dec1df…8ce7`): `Kit/Form/RadioCardGroup.vue` and the `Kit/Workbench/` files take the changes below.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:0f324262…f54c` → `sha256:3f155ade…11fd`): three new host steps (below). `kit:update` and `kit:status` point out any the host lacks, as attention.

### Added

- **`RadioCardGroup` cards carry `data-value`**: each card's option value, as a hook for tests, automation and styling.
- **`<Workbench :actions-bar="false">`** renders no actions bar, for a dense surface that drives the panes with `useWorkbench().togglePane()` and `focusRegion()`. With `headings` off too, the panes have no toggle of their own, so in development the Workbench warns if no pane has been toggled a few seconds after mount.
- **The shell's host prerequisites are host steps**: the `@kit/shell` alias in `vite.config.ts` and `tsconfig.json`, and the kit's classes in the TypeScript transformer (`base_path('vendor/tey/kit/src')` in `app/Providers/TypeScriptTransformerServiceProvider.php`) for the generated data types. Each is checked and gives the exact snippet.
- **`kit:status` reports the adopted features' host steps and npm requirements**, checked now, under an ATTENTION block when one is unmet. `--json` adds them as `requirements`, only when an adopted feature declares any.
- **The host contract is typed for PHPStan**: `LifecycleHost::inputPaths()` and the `Lifecycle` methods a host calls declare their array shapes, so a host at level 8 or max gets no iterable-type errors. `composer test:host-contract` runs PHPStan at level max on the contract and a fixture host.

### Fixed

- **`TableQuery` redirects an empty result past page 1 to page 1.** The last-page redirect skipped empty results, so a filter that matched nothing at `?page=9` rendered a blank page 9. It now keeps the query and the flashed data, as for a non-empty result.

## v0.5.18

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:a1adff6f…3c22` → `sha256:3b960b2a…e0ee`), then `php artisan migrate`. Audit rows whose workspace_id names a workspace that no longer exists are set to null, and the migration prints and logs how many (a warning with `orphaned_audit_events`). Core alone is unchanged.

### Fixed

- **Audit events outlive their workspace.** A host with orphaned audit rows no longer fails `0001_01_02_000204` at deploy (it now leaves the key to the new migrations when orphans exist), and deleting a workspace that has audit events no longer fails on the foreign key. New workspaces migrations make `audit_events.workspace_id` nullable (000205), null orphaned ids with a count (000206), and replace the key with one that sets null on delete, converting 000204's key where it already ran (000207). A new nullable `audit_events.workspace_name` (000208) is backfilled from `workspaces` and filled by `Audit::write`, so a row still says which workspace it was. All are idempotent. `composer test:audit-workspace-fk` covers it on SQLite and, in CI, PostgreSQL.

## v0.5.17

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:8adcf2e2…2024` → `sha256:0f324262…f54c`): new `Partials/PublicShell.vue`, `Partials/PublicMeta.vue` and `pages/Public/Markdown/Page.vue`. Shell now owns `resources/js/pages/Public/Markdown`.
- **Optional:** route the public pages to the PublicShell in your layout callback (`Public/*`, and `Workspace/Error/Page` for a guest); see [docs/public-shell.md](docs/public-shell.md). `kit:update` points this out as attention (the shell's new `mention` host step), never blocking. Until then those pages keep the GuestShell.

### Added

- **`PublicShell`** ([docs/public-shell.md](docs/public-shell.md)) for sessionless public pages (landing, help, legal, a guest error), built like the GuestShell:
  - a skip link to `#content`;
  - a header with the lockup, a `nav` slot and an auth-aware CTA ("Sign in" / "Open your workspace" from the shared shell's kind; the host passes the URLs);
  - `main` at `width` prose, wide or full;
  - a footer from the shared `shell.footer`;
  - `tone` default or brand, dark-mode aware, with no tracking.
- **`PublicMeta`**: a page's title, description, canonical link, Open Graph and Twitter card.
- **`Public/Markdown/Page`** with `PublicMarkdown::page()`: legal or help markdown rendered and sanitized on the server (raw HTML stripped, unsafe links refused).
- **A `mention` host step kind**: attention when a host file doesn't mention something (the shell's: `resources/js/app.ts` mentions `PublicShell`). `composer test:public-shell` covers the step and the sanitizing.

## v0.5.16

### Added

- **The workspace MCP server can be named** (#103): `kit.mcp.server.name` (or `KIT_MCP_SERVER_NAME`) and `kit.mcp.server.instructions` set what assistants see. Unset, the kit's "Workspace operations" and its instructions stand. `composer test:mcp-server-config` covers it.
- **`<x-kit::fonts />` supports a CSP nonce** (#45): a `nonce` attribute, else `Vite::cspNonce()`, on both links and the style tag. Without one, the output is unchanged.

## v0.5.15

### Added

- **`TolerantImage`**, a raster generator for PNGs with malformed metadata ([docs/media.md](docs/media.md#conversions)): before GD loads a PNG, it removes the `iCCP`, `iTXt`, `zTXt` and `tEXt` chunks from a temporary copy (the original is untouched). Where the host's libpng treats a bad colour profile as an error, the conversion then succeeds. A corrupt PNG (a bad critical chunk, a truncated file) is passed on unchanged and still fails loudly. It replaces `Image::class` in `media-library.image_generators`; `kit.media.tolerant_png` false opts out.
- [docs/media.md](docs/media.md#where-failed-jobs-live): where failed jobs live. On Laravel Cloud's managed queue they're in the queue's own store, not the `failed_jobs` table.

### Known limitations

- **TolerantImage's "fails without" half isn't proven in CI.** CI's GD builds print libpng's warnings to stderr instead of raising them in PHP, so under Laravel's error handler the malformed-iCCP PNG can't fail there and the check prints SKIP. That half is proven only on GD builds that raise the warning in PHP: verified locally on PHP 8.4 and 8.5, and by the client's own repro. CI proves the conversion succeeds.

## v0.5.14

### Fixed

- **Adopting your own workspaces table on a fresh database.** The kit's migrations (`0001_01_02_*`) sort before a host's own (real dates), so on an empty database (CI, a new developer, tests) the kit created `workspaces` and the permission tables first and the host's own migration then failed with "already exists"; the access, settings, mcp and notes tables' foreign keys to `workspaces` ran too early as well. Adopt-in-place only worked where the host's tables already existed at migrate time. Now, when `kit:add` plans an adoption, it records it in the workspaces lineage's installed record (`adopt`: the stamp and the adopted tables) and publishes every migration of each feature it adds that reaches workspaces as `<stamp>_<order>_<name>.php`. The stamp is one second after the host's latest migration under `database/migrations` (and no earlier than today, UTC), so these run after the host's own. `kit.workspaces.adopt` and `kit.access.adopt` (null by default, which reads the record; `KIT_WORKSPACES_ADOPT` and `KIT_ACCESS_ADOPT` override it) make the workspaces and permission migrations refuse, rather than create, a table the host owns that doesn't exist yet. A host that adopts nothing keeps the catalog's file names. An existing install keeps its file names on `kit:update`, so nothing it already ran is renamed or re-run. A migration a later release adds takes the install's stamp. `composer test:adopt-fresh` covers it (SQLite, and PostgreSQL in CI).

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:0c427f2f…92f8` → `sha256:a1adff6f…3c22`): the workspaces and permission migrations read `kit.workspaces.adopt` and `kit.access.adopt`.
- **A host that already adopted its own tables (installed before this release):** production is unaffected, and its migrations keep their names. Set `KIT_WORKSPACES_ADOPT=true` (and `KIT_ACCESS_ADOPT=true` when you own spatie's tables) so the kit never creates those tables. A fresh database still runs the kit's migrations before your own: there, they now refuse with a clear message instead of creating a second table. Load a schema dump (`php artisan schema:dump`) for fresh databases. Or, if production has not run the kit's migrations yet, revert the `kit:add` (its files and its `.mod-maintenance` record) and run `kit:add` again with this release, so it publishes stamped migrations. With the flags set, `kit:add` of a further feature also publishes stamped migrations.
- **A host adopting for the first time on a fresh database:** commit your own `create_workspaces` (and spatie permission) migrations first, then run `kit:add`. The stamp follows the latest migration in your codebase. If the database you plan against doesn't have your tables yet, set `KIT_WORKSPACES_ADOPT=true` / `KIT_ACCESS_ADOPT=true` before the plan.

## v0.5.13

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:88a4470f…0348` → `sha256:31138a46…dc77`): DataTable, RowActions, ActionSegment, ActionMenuItems, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Feedback/Badge.vue` and `Table/rowLabel.ts`.
- **A `get` action descriptor (without a `confirm`) is now a link** in RowActions, ActionBar and the ⌄ menus, not a button. A test or stylesheet that looked for a `<button>` there needs updating.

### Added

- **`<Badge>`** ([docs/data-table.md](docs/data-table.md#status-cells-badge)): a status label with tones neutral, info, success, warning and danger on the kit tone tokens (AA in light and dark), and sizes sm and md.
- **DataTable `row-label`** (a column key or a function): row controls read "Actions for Ana Lopez" and "Select Ana Lopez", falling back to the row number.
- **`get` descriptors render as links** in RowActions (a kit Button `href` inline; the ⌄ item is the link), ActionBar segments and its menu rows (`rendersAsLink()`): an Inertia Link, or an `<a target="_blank" rel="noopener noreferrer">` with `newTab`. Non-GET, confirm and dialog actions stay buttons.
- [docs/data-table.md](docs/data-table.md): TableQuery, row labels, links, Badge, and that several lists on one page means tabs.

### Fixed

- **TableData's generated TypeScript is precise:** a row is `{ id: string | number; actions?: Action[] } & Record<string, unknown>` (not `Record<string, any>`), and `emptyState` is `'first-use' | 'no-results' | null` (not `unknown | null`).
- **A GET action that asks a confirmation now asks before it navigates.** ActionBar segments rendered any GET descriptor as a bare Link, so a GET with a `confirm` navigated without asking. Such an action is now a button that opens the confirmation and visits once confirmed, in RowActions, ActionBar and the ⌄ menus alike.

## v0.5.12

### Fixed

- **PerformConversions fails loudly instead of finishing with nothing made** (found in production: 2 of 407 images).
  - An original it can't read (Media Library writes 0 bytes and returns on a refused read when the disk doesn't throw) throws `media.conversions.unreadable_original`.
  - An image that ends with none of its requested conversions throws `media.conversions.none_generated`.
  - Both are retried. A media row deleted before the job runs still drops the job, now with a `media.conversions.missing_model` warning naming the media id.

## v0.5.11

### Fixed

- **TableQuery is generic over its model** (`@template TModel of Model`): `for(Builder<TModel>)`, `search`/`filter` closures over `Builder<TModel>`, `build(Closure(TModel))`, and the fluent methods return `$this`. So a typed caller passes PHPStan for any model.
- **`TableQuery::defaultSort($key)` sorts by the key's declared SQL column.** It passed the key itself to spatie, so a key that differs from its column (`joinedAt` → `created_at`) sorted by a column that doesn't exist.
- **A page past the last redirects to the last page**, keeping the rest of the query and the session's flashed data (a toast). Before, it showed an empty page.

## v0.5.10

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:046a13b2…10c0` → `sha256:88a4470f…0348`): `css/kit.css` changes.

### Fixed

- **Toasts meet WCAG AA.** The Toaster used Sonner's own rich colours, and its success toast failed AA (4.26:1, axe color-contrast). kit.css now maps every Sonner rich-colour variable (`--success-*`, `--info-*`, `--warning-*` and `--error-*` for bg, border and text) onto new kit tone tokens (`--kit-tone-<tone>-bg|border|fg`), light and dark. The text contrast is now at least 6.8:1 (light) and 10.3:1 (dark). A host theme overrides the tokens. `toaster-contrast.test.mjs` checks the mapping and the ratios.

## v0.5.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ff8ac070…63ae` → `sha256:046a13b2…10c0`): Workbench and `workbenchLayout.ts` change.

### Added

- **Dense Workbench surfaces** ([docs/workbench.md](docs/workbench.md#dense-surfaces-default-collapsed-panes-no-headings-an-overlay-navigator)):
  - `:default-collapsed="{ navigator: true }"` collapses panes the first time a layout shows, only without a remembered layout (a saved one wins).
  - `:headings="false"` drops the region headings; the landmarks keep their names, and the pane toggles move to the actions bar.
  - `navigator-mode="overlay"` opens the navigator as a sheet over the stage (a kit Modal: focus trap, Esc, focus back), so the stage never resizes; `close-on-select` closes it on a choice.

## v0.5.8

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9223364e…f59e` → `sha256:ff8ac070…63ae`): RadioCardGroup and `contract.ts` change; new `Form/radioCommit.ts`.

### Added

- **RadioCardGroup `commit="click"`** ([docs/radio-card.md](docs/radio-card.md#commit-on-click-commitclick)) for live polls: the arrow keys only move focus (a roving tabindex over `role="radio"` buttons), and Space, Enter, a click or a tap commits, setting `v-model` and emitting `commit` once. Re-selecting the current option emits nothing, and the choice is announced politely. This is a documented deviation from the APG's auto-select. The default (`change`) is unchanged.

## v0.5.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4585ea42…da76` → `sha256:9223364e…f59e`): Dialog, ActionBar, RowActions, BulkBar, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Overlay/confirmText.ts`.
- **ActionBar now runs a descriptor itself when the host does not listen to `@action`**: its `confirm` first, then an Inertia visit. Before, such an action did nothing. A host that listens is unchanged.

### Added

- **Typed confirmation** ([docs/typed-confirmation.md](docs/typed-confirmation.md)) for destructive actions:
  - Dialog `confirm-text` (with `case-sensitive` and `confirm-text-label`): a labelled input, and Confirm disabled until the text matches, announced politely; paste allowed. The typed text is emitted with `confirm` and sent as `confirmation` with the `action` visit.
  - A descriptor's `confirm.typed` (`TypedConfirmation::confirm()`) is rendered by RowActions, BulkBar and ActionBar, which send `confirmation`.
  - The server re-checks with `TypedConfirmation::require($request, $text)`; a missing or mismatched `confirmation` is a validation error.
  - `composer test:typed-confirmation` and `typed-confirmation.test.mjs` cover it.

## v0.5.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2bf2341…fcb2` → `sha256:4585ea42…da76`): Workbench, `workbench.ts`, `useWorkbench.ts`, `workbenchStage.ts`, FloatingToolbar and `index.ts` change.

### Added

- **Workbench element attribute:** the host names the attribute its stage elements carry their id in, with `<Workbench element-attribute="data-block-id">` or `useWorkbench(key, { elementSelector })` (a name or `[name]`). The stage toolbar's selection rects (`selectedRects`, which now takes the attribute), FloatingToolbar `mode="stage"` and `reposition()` use it. The default stays `data-workbench-element`. `elementAttributeName()` and `DEFAULT_ELEMENT_ATTRIBUTE` are exported, and an invalid name falls back to the default.
- **Workbench `:keys="false"`** for live sessions and transports (the app owns the keyboard): none of the Workbench's bindings fire (↑/↓, j/k, Alt+↑/↓, `/`, F6, Esc, ⌘[ / ⌘], zoom). Focus management and the region landmarks stay. `enabledCommand()` is the pure gate.

## v0.5.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f1e58788…5cf7` → `sha256:a2bf2341…fcb2`): new `Form/RadioCardGroup.vue`, `Form/EmojiTray.vue` and `Form/emojiTray.ts`; Button, Field, `index.ts` and `contract.ts` change.
- Field's label now has the id `<id>-label`. A host test that selected the label by an exact attribute list may need updating.

### Added

- **Media delivery** ([docs/media.md](docs/media.md#delivery)): `MediaDelivery::respond($request, $resolve, $conversion, $public, $mode)` serves an item behind the app's own route, and the app's resolver decides what's a 404. `proxy` streams the bytes: public items are immutable with an ETag and a 304, private ones are `private, no-store`, everything is nosniff, and anything that isn't an image downloads under a sandbox CSP. `redirect` answers a 302 to a signed bucket URL, and the redirect's own max-age is at most half the TTL (`kit.media.redirect_ttl`). `kit.media.delivery` sets the default mode. `composer test:media-delivery` covers it.
- **Adopting existing objects** ([docs/media.md](docs/media.md#adopting-existing-objects)): `AdoptObject` makes an object already on a disk a Media item over its key (via `KeyPathGenerator`), probed by size and its first bytes, with no copy and idempotent. `AdoptMediaCommand` is the command pattern: an app subclass names the owners and each one's key. It's tenant-aware through spatie's `TenantAware` command trait (every tenant, or `--tenant=`) and refuses to run with no tenant. It runs in id chunks (`--chunk`), one `TenantAware` queued job per chunk on `kit.media.queue` that fails loudly with no tenant (`--sync` runs them inline). `--dry-run` prints per-tenant counts only, and a tenant whose source has rows but no owners is a failing warning. Re-runs are no-ops. `ConfirmUpload`'s header read is now `ObjectProbe`. `composer test:media-adopt` covers it.
- **`<RadioCardGroup>`** ([docs/radio-card.md](docs/radio-card.md)): large tappable choices (a poll's options) as native radios in cards, so it has one tab stop and arrow keys and submits with a form. A `radiogroup` named by its Field's label, with `v-model`, selected and disabled states, and an optional result bar and percentage (`show-results`, or the `result` slot).
- **`<EmojiTray>`** ([docs/emoji-tray.md](docs/emoji-tray.md)): a compact reaction picker. A labelled `toolbar` with a roving tabindex, a spoken label per emoji, and a `react` event throttled by `throttle` ms (presses inside the window are dropped). It has `DEFAULT_REACTIONS` and reduced motion.
- **Button `size="lg"` and `size="xl"`** ([docs/button.md](docs/button.md)): `lg` is the host button's; `xl` is the host's lg made a ~96px tap target with kit classes.
- **Button `href`:** a link styled as the button, never a `<button>` inside an `<a>`. It's an Inertia `<Link>` (`method`, `data`, `preserve-scroll`; a non-GET method renders as a `<button>`), or a plain `<a>` with `external` (`rel="noopener noreferrer"` for `target="_blank"`). `VisitData` is exported.

## v0.5.4

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:598510bf…b128` → `sha256:f1e58788…5cf7`): new `Layout/DescriptionList.vue` and `Feedback/EmptyState.vue`; `index.ts` and `contract.ts` change.

### Added

- **`<DescriptionList>`** ([docs/description-list.md](docs/description-list.md)): term and description pairs as a real `<dl>`, from `items` or slots. Inline (side by side from `sm`, stacked on a phone) or stacked, with an optional copy-to-clipboard button per value and a polite status.
- **`<EmptyState>`** ([docs/empty-state.md](docs/empty-state.md)): an icon, a title, a body and the next step, as an `action` slot or server action descriptors. Has a `compact` form for panels and lists, and is a plain labelled region, never a live region. Both components are exported from the barrel, with `DescriptionListProps` and `EmptyStateProps` in the contract.

## v0.5.3

### Upgrading

- **Upgrading from v0.4.7 or earlier, with your own dashboard widgets:** since v0.4.8, `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an app override that injected its own widgets breaks. Register them with `Registry::widget()`, or set `kit.dashboard.widgets` to `[]` (now also noted under v0.4.8 and in [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets)).
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2d9f179…bf85` → `sha256:598510bf…b128`): `Form/FormActions.vue` changes.

### Added

- **`kit.mcp.tools`** turns off individual kit MCP tools by name, for an app that serves its own: `['workspace_dashboard' => false, 'workspace_search' => false]`. A tool that is off is neither listed nor callable. The default (`[]`) leaves every tool on. `composer test:dashboard` covers it.
- **Responsive conversions** ([docs/media.md](docs/media.md#conversions)): `HasResponsiveImages::responsiveWebp($collection, $widths)` declares `w<width>` WebP conversions, fit within the width and never upscaled. `PerformConversions` runs them one image per job on `kit.media.queue` (default `media`), with tries, backoff, a timeout and a memory limit; a retry does only what's missing. It implements spatie's `TenantAware` (it carries and runs in its tenant) and fails loudly with no current tenant. `Conversions::regenerate()` is idempotent. `Srcset::for()` lists only generated widths, with the original as the fallback, over the app's own URLs. `AttachUpload` queues an item's conversions. `composer test:media-conversions` covers it.

### Fixed

- **ViewModel props come in the same order on every PHP version.** `propMethods()` (and so `toArray()` and the generated TypeScript) kept reflection's order, which PHP 8.4 and 8.5 give differently for trait and inherited methods. The order is now ancestors first (root to child), then within each class its traits' methods in `use` order and its own by line; an override keeps its parent's slot. `composer test:viewmodel-order` covers it on both versions.
- **A typed `useForm<T>()` passes `vue-tsc` on FormActions and FormCard.** `FormActions`' `FormLike` declared `reset` and `clearErrors` as function-typed properties, so a form whose `reset` takes only `T`'s keys failed with TS2322. They are now methods (bivariant). The components type-check job compiles a fixture passing `useForm<{ a: string; b: { c: number } }>()` to both.

## v0.5.2

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:35504c18…cd67` → `sha256:ceb00b2e…ce1e`): BulkDrop changes; new `Form/directUpload.ts`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ceb00b2e…ce1e` → `sha256:a2d9f179…bf85`): ActionBar and `Action/actionFit.ts` change.

### Added

- **BulkDrop `direct`:** with `request` and `confirm` routes, BulkDrop uploads each file straight to the bucket with progress and emits `uploaded`. `directUpload()` is the same flow without the dropzone. A POST ticket falls back to multipart, and retries reuse each File's mutation id.

### Fixed

- **ActionBar no longer triggers a ResizeObserver loop.** Its observer measured and re-laid out the bar inside its own callback, so flipping between the compact and full layouts resized the observed root in the same frame ("ResizeObserver loop completed with undelivered notifications", which fails browser tests). It now measures in one coalesced animation frame, skips a measure that hasn't changed, and cancels the frame and disconnects on unmount. The fit has hysteresis: a compact bar returns to the full layout only with `ACTION_FIT_HYSTERESIS` (8px) to spare, so a width at the boundary can't oscillate. `fitActions()` takes the previous state as an optional third argument, and `sameMeasure()` is new.

## v0.5.1

### Upgrading

- Run `kit:update plan|apply` for `workspaces` again (`sha256:9d17553b…b75f` → `sha256:9243696e…5a0f`): its workspaces, permission and slug migrations now adopt an existing table in place, and it declares the `workspace_model` host step. Nothing changes for an existing kit host: the migrations already ran, the kit's `Models\Workspace` stays the default binding, and the step is met by the defaults.
- **Binding your own workspace model** (optional): set `concierge.models.workspace` and `multitenancy.tenant_model` to the same class, which extends spatie's `Tenant`, implements `Tey\Concierge\Contracts\Workspace` and uses `Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace`. See the README's "Adopting into an existing workspaces table".
- Code that type-hinted `Tey\Kit\Features\Workspaces\Models\Workspace` against a kit method's return value now gets the Concierge contract type; the object is still the bound model.
- Kit signatures that took the kit model now take the contract, so an extender overriding one widens its parameter to match: `public function handle(Models\Workspace $workspace)` → `public function handle(\Tey\Concierge\Contracts\Workspace $workspace)` (likewise `Workspace::query()` → `Workspaces::query()`, `Workspace::required()` → `Workspaces::required()`).
- Run `composer update tey/concierge`: the kit now requires `tey/concierge` ^0.8.4, for the workspace context's `enter()`/`leave()`. Then run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:9243696e…5a0f` → `sha256:0c427f2f…92f8`). An app with its own `WorkspaceContext` implementation must add `enter()` and `leave()` (see Concierge's upgrade notes).
- **A host with its own `config/multitenancy.php`** (or its own `switch_tenant_tasks` list) must add the kit's two tasks, or a TenantAware job or command runs without the Concierge context and with a stale Pennant cache:

  ```php
  'switch_tenant_tasks' => [Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask::class, Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask::class],
  ```

### Added

- **Adopt the workspaces feature into an existing workspaces table.** A host with its own `workspaces` table and spatie/laravel-permission's tables (with teams) gets planned adoptions (`adoptions`, `HostRequirements::adoptions()`) instead of collisions. The workspaces migration adds only `admitted` (existing rows set to true), `settings_version` (set to 1), `provisioning_invitation_id` and `slug` where missing, and never drops or alters a host column. The permission migration creates nothing when the five tables exist, and refuses tables without the configured teams column. Each adoption is recorded in a kit-owned `kit_adoptions` table (table, columns added, when); `down()` reads it, never drops an adopted table, and drops only the columns the kit recorded adding.
- `Tey\Kit\Features\Workspaces\Workspaces` resolves the bound workspace model (`model()`, `query()`, `current()`, `required()`, `refusal()`). Every kit reference goes through it, and `composer test:workspace-adopt` fails if anything under `src/` names `Models\Workspace` again.
- `IsKitWorkspace` carries the kit model's behaviour (slugs, `memberOf`, Concierge naming and admission, `required()`) for a host model to use. The kit model keeps only its no-timestamps overrides.
- The `workspace_model` host step: a plan refuses a binding where `concierge.models.workspace` and `multitenancy.tenant_model` differ, or the model misses the contract, `IsTenant` or the trait.
- `composer test:workspace-adopt` (SQLite memory, synthetic host schema), and a CI job running it against a PostgreSQL service container (`DB_CONNECTION=pgsql`). Not covered: MySQL, and an end-to-end `kit:add` against a real host app.
- **Switch tenant tasks:** making a workspace the current tenant (spatie's `makeCurrent`, `Tenant::execute`, a TenantAware job or command) now does what TenantBoundary did:
  - `ConciergeContextTask` enters the workspace in Concierge's context (the current workspace and the permission team), and `forgetCurrent` leaves it, restoring what was there before;
  - `FlushFeatureCacheTask` flushes Pennant's cache both ways.

  They're registered in the kit's multitenancy defaults. `composer test:tenant-tasks` covers them, including that a TenantAware job with no tenant fails loudly.

## v0.5.0

### Upgrading

- **`SurfaceContext`:** the barrel's type export for a surface's tone and shape (`Surface` from `Surface/surface`) is now `SurfaceContext`. Exporting it as `Surface` as well as the `<Surface>` component was a duplicate identifier (TS2300) under vue-tsc. Change `import type { Surface } from '@kit/ui'` to `import type { SurfaceContext } from '@kit/ui'`. `provideSurface`/`useSurface` and `Surface/surface` are unchanged.
- Run `kit:update plan|apply` for `core` (index.ts, Layout/Collapsible.vue, Surface/Surface.vue, contract.ts).
- **Surface clips by default** (`clip`, default `true`, is `overflow-hidden`). If something inside a Surface must overflow it or stick (a sticky header, an absolutely positioned popover), pass `:clip="false"`.
- **session-ui splits into session-ui (the sign-in) and workspace-ui (the workspace pages).** Re-pinned: `session-ui` `sha256:c0730ecd…9e0d` → `sha256:cc31f07b…ae1f`, `notes` `sha256:1d5fe0eb…9370` → `sha256:d373d438…f1b2` (it now requires workspace-ui); new `workspace-ui` `sha256:e5f861f9…6950`. No file's bytes change and nothing is deleted: 22 files change owner, from session-ui to workspace-ui. After `composer update`, and before any of the steps below, the app keeps its workspace pages on: workspace-ui is carried while session-ui's installed lineage still owns them. Move them over with these runs, in this order. Each `plan` prints the `apply` command to run (with its `--expected-plan`, `--exclusive-checkout` and `--intent`), and each result's `next` names the following run:
  1. `php artisan kit:update plan --lineage=session-ui`, then its `apply`. session-ui releases the 22 workspace pages (each file's state is `release`; none is written or deleted) and goes to an interim version. This is also the update that makes it own only its three welcome-step folders under `pages/Welcome`, not the whole folder.
  2. `php artisan kit:update plan --lineage=workspace-ui`, then its `apply`. workspace-ui is installed at exactly the released bytes: it claims the 22 files and writes nothing.
  3. `php artisan kit:update plan --lineage=workspace-ui` again, then its `apply`: workspace-ui goes to its target (nothing to write).
  4. `php artisan kit:update plan --lineage=session-ui` again, then its `apply`: session-ui finishes.
  5. If the app has notes: `php artisan kit:update plan --lineage=notes`, then its `apply`. It requires workspace-ui, so it runs after step 2.
  6. If `config/kit.php` sets `kit.features`, add `'workspace-ui'` after `'session-ui'` (the app works without it, as workspace-ui is carried once its lineage is installed).

  If you have edited one of the moved pages (under `pages/Workspace/` or `pages/Provision/`), step 1 writes nothing and prints the diff from the installed file to yours: put your changes aside, restore the installed bytes, run the steps, then reapply your changes. Needs tey/mod with removals (main, 0.11), as the shell split did.
- Nothing else changes unless you set `kit.home`: the kit's home stays at `/`.

### Added

- **Surface `clip` and `translucent`** ([docs/surface.md](docs/surface.md)). `clip` (default `true`) is `overflow-hidden`; set it `false` for a sticky child. `translucent` is an 80% fill with a backdrop blur, solid without `backdrop-filter` support and under `prefers-contrast: more` or `prefers-reduced-transparency`, and unblurred under `prefers-reduced-motion`. Surface now clips by default: a host relying on a Surface's content overflowing it (a popover inside, a sticky child) passes `:clip="false"`.
- **CI type-checks the core components with vue-tsc** (`bin/typecheck-components.sh`, versions pinned in `typecheck/package.json`). The host's shadcn-vue components are typed `any` there (`typecheck/shims.d.ts`), so the check covers the kit's own code, not its use of them.
- **Auth-only session-ui.** A host can adopt only the sign-in: `kit:add session-ui` brings `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/TwoFactor`, `Auth/VerifyEmail` and `Auth/Invitation/Show`, the welcome steps and the routes around them, and none of the workspace pages. Without workspace-ui the kit serves nothing at `kit.home`, and Fortify's `home` (where sign-in lands) defaults to `kit.home`, the app's own page; an accepted invitation and the last welcome step land there too. `composer test:auth-only` and `composer test:host-boot` cover it.
- **`workspace-ui`**, a new feature requiring session-ui: the kit's home, the dashboard and its widgets, search, the settings and account landing pages, Settings › People, the account pages under `/me`, the placeholders, the error page, `Workspace/Index` and `Provision/NoWorkspace`, moved from session-ui byte for byte. Its routes keep their names and `kit.routes.session-ui` options, and its classes keep their `SessionUi` names. `composer test:workspace-ui` covers the split and the move.
- **`kit.home` (`KIT_HOME`):** where workspace-ui serves the kit's home, `/` by default. An app with its own public landing at `/` sets it elsewhere (`/app`); the kit's home and the breadcrumbs' root move there, and the app's `/` is untouched.

### Fixed

- **vue-tsc failed inside the kit's core components** (TS2300 in `index.ts`; TS2769 and TS2322 in `Layout/Collapsible.vue`). Collapsible's `open` model is now `defineModel<boolean | undefined>('open')`: bound, the host's value wins; unbound, it starts from `defaultOpen`, and nothing is emitted until the user toggles (it used to write the model while setting up).
- **An auth-only app has no links to workspace pages.** Without workspace-ui, `/me/workspaces` (and the old `/workspaces`) redirects to `kit.home` instead of rendering a chooser page the app does not have; Concierge's `no_workspace_url` defaults to `kit.home` (an app's own `config/concierge.php` decides for itself); and the shared shell's home, switcher, product, account and "All workspaces" links point at `kit.home`, with no "Account settings" item. The workspace guard's and leave-workspace redirects use the same `WorkspaceUrl::chooser()`. With workspace-ui nothing changes. `composer test:auth-only` and `composer test:host-boot` cover it.
- **session-ui no longer owns a host's landing page.** It owned all of `resources/js/pages/Welcome`, so an app whose landing lives at `pages/Welcome/Page.vue` (with `Partials/`) collided with the kit there. It now owns only `Welcome/Timezone`, `Welcome/Photo` and `Welcome/WorkspaceTimezone`. `composer test:host-boot` covers the ownership and `kit.home`.

## v0.4.33

### Added

- **Direct-to-bucket uploads** (`Tey\Kit\Media`, [docs/media.md](docs/media.md)): `RequestUpload` reserves a pending upload from a declared type, size and SHA-256 and returns a presigned PUT that binds them (or a multipart `POST` ticket when the disk cannot presign); `ConfirmUpload` checks the object by size and its header bytes (a hash on a local disk), and `AttachUpload` makes it a Media item over the existing key with no byte copy. Mutation replay, dedupe by SHA-256 within a scope. `KeyPathGenerator` and `KeptFileRemover`: deleting a Media item over a key never deletes the object. A library, not a catalog feature, so no re-pin. `composer test:media-uploads` covers it.

## v0.4.32

### Upgrading

- Run `kit:update plan|apply` for `session-ui` again (`sha256:f2cc2fd1…3670` → `sha256:cd013c29…abae`): it adds `Auth/ForgotPassword` and `Auth/ResetPassword`, and the sign-in page links to them.
- **Password reset is on:** the kit's fortify defaults now include `Features::resetPasswords()`. The host needs Laravel's `password_reset_tokens` table and mail that can send the reset notification. An app that publishes its own `config/fortify.php` keeps its own feature list.
- Run `kit:update plan|apply` for `core` (Button, FormActions, FormCard, ActionBar, ActionMenuItems, InputCheckbox; new `Surface/Surface.vue`, `Form/formSubmit.ts` and `Action/menuSelect.ts`) and `session-ui` (its form buttons now say `type="submit"`).
- **Behaviour change: a kit `Button` is `type="button"` by default.** It's a submit only inside Inertia's `<Form>` or a FormCard, or when you pass `type="submit"`. Give any of your own kit Buttons that submit a plain `<form>` (or a form outside FormCard and `<Form>`) an explicit `type="submit"`. A toolbar or menu Button inside a form no longer submits it by accident.
- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:8aa53c04…717f` → `sha256:9d17553b…b75f`): it now requires `tey/concierge` ^0.8.3. Run `composer update tey/concierge`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4e8ab9c4…960a` → `sha256:519f5f64…afb0`): ActionBar, FloatingToolbar, WorkbenchStage, `index.ts` and `contract.ts` change; new `Action/actionFit.ts`, `Layout/toolbarAnchor.ts`, `Layout/toolbarAction.ts`, `Layout/Collapsible.vue` and `Workbench/workbenchStage.ts`.
- ActionBar's root is now a wrapper `div` carrying `data-action-bar` (around the Toolbar), and it no longer uses `sm:`/`max-sm:` classes to compact. A host stylesheet or test that targeted the old structure needs updating.

### Added

- **Forgot your password?** Kit pages for Fortify's password reset: `Auth/ForgotPassword` asks for the emailed link, and `Auth/ResetPassword` chooses the new password with the rules shown, under the same policy and breach message (`Fortify\ResetPassword`). The sign-in page links to it. With Concierge's reset-verifies rule, this is also how the first owner from `concierge:owners` sets a password and lands verified. `composer test:onboarding` covers it.
- **`<Collapsible>`** (`Kit/Layout/Collapsible.vue`, [docs/collapsible.md](docs/collapsible.md)): a disclosure section for inspector sections. Its toggle is a real `<button>` with `aria-expanded` and `aria-controls` naming the content region, the content is hidden while closed, it has `v-model:open` (else `defaultOpen`), a `title` prop or `summary` slot, an `actions` slot and `level`, and its transition is off under reduced motion. `CollapsibleProps` is in the contract.
- **FloatingToolbar client actions:** `items` (`ToolbarAction`: `key`, `label`, optional `icon`, `disabled`, `reason`; no URL) handed to the host's `onAction` (`@action`) by the toolbar's own handler (`runToolbarAction`). Server descriptors through a slotted ActionBar work as before.
- **FloatingToolbar `mode="stage"` and `mode="docked"`** inside a Workbench: `stage` floats over the bounding box of the selected stage elements (`wb.elements`, found by `data-workbench-element`), above them, flipping below and clamped inside the stage (`anchorToolbar`, `unionRect`); `docked` renders in the stage toolbar row. A `reposition()` is exposed.
- **The Workbench stage context:** WorkbenchStage provides `provideWorkbenchStage({ workbenchKey, viewport, toolbar })`; `useWorkbenchStage()` and `selectedRects()` are exported ([docs/workbench.md](docs/workbench.md#editing-toolbars-and-narrow-actions)).
- `fitActions`, `ToolbarAction`, `AnchorRect`, `ToolbarAnchor` and `WorkbenchStageContext` are exported from the barrel.
- **`<Surface>`:** a card-like container (padding, border, radius, `bg-card`/`text-card-foreground`) that provides its tone and shape to what's inside (`provideSurface`), so a kit Button or ActionBar on it blends in. Props: `tone` (default, inverse), `shape` (default, pill), `padding` (none, sm, md), `as`. Exported from the barrel; `SurfaceProps` is in the contract.
- **Button:**
  - `ghost` and `link` variants (the host button's own, so the host needs no new variant);
  - a `size` prop (default, sm, icon; the host button's sizes).
- **FormActions (and FormCard):** `dirtyLabel`, `errorsLabel` and `upToDateLabel` override the status lines, alongside `savedLabel`.

### Fixed

- **ActionBar didn't compact inside a Workbench at narrow widths.** It compacted on the viewport's `sm` breakpoint, so a narrow Workbench actions bar on a wide viewport (or a 390px one whose bar is narrower still) wrapped instead. It now measures its own width with a ResizeObserver and falls back to its main action and a ⌄ menu whenever its full layout doesn't fit (`fitActions`), for the pill and the default surfaces alike.
- `composer test:js` (`tests/js/workbench-editor-gaps.test.mjs`) covers each item: the pure fit, anchor and action logic, and the components' sources. There's no browser test.
- **A menu action chosen from ActionBar's ⌄ menu reached nothing.** `ActionMenuItems` emitted the action on a timer after the menu closed, but the menu had already unmounted it, so Vue dropped the emit: every non-dialog, non-document menu action (click or Enter) was lost. The rows now call an `onAction` handler captured when the row is chosen; ActionBar passes its own, which stays mounted. A host's `@action` on ActionMenuItems still works.
- Documented:
  - `warnOnLeave` false registers no leave guard at all (no router `before` listener, no `beforeunload`), so a host can own its own guard;
  - InputCheckbox requires an `id`.
- `composer test:js` (`tests/js/editor-primitives.test.mjs`) covers each item.

### Changed

- **Concierge verifies the invited address.** Concierge 0.8.3 verifies an unverified account whose email is the invited one inside the accept transaction, for member and tenant invitations, and shows such an invitation as ready to accept. The kit's own stopgap (`Workspaces\InvitedEmail`, v0.4.31) is removed. Opening an invitation no longer verifies on its own; accepting it does. `composer test:onboarding` still proves every kit invite path verifies.

## v0.4.31

### Upgrading

- Run `kit:update plan|apply` for `core` (`sha256:7c1ae722…8b43` → `sha256:4e8ab9c4…960a`), `shell` (`sha256:cf7ec863…ded3` → `sha256:8adcf2e2…2024`), `session-ui` (`sha256:30fb141d…8a8e` → `sha256:f2cc2fd1…3670`). It adds `Kit/Form/PasswordRules.vue`, `Kit/Feedback/PasskeyOffer.vue` and `passkeyOffer.ts`; `PasswordInput` gains `rules`; the invitation sign-up and change-password pages show the rules; and the shell layout renders the passkey offer.
- **New password policy:** `Password::defaults()` is now `Password::min(8)->uncompromised()` with no composition rules (NIST 800-63B), set by session-ui. Existing users aren't asked to change their password; the rule applies the next time a password is set. An app that sets its own `Password::defaults()` keeps it, and the rules under the field follow it.
- Passkeys after sign-up are optional: see `docs/onboarding.md` (Fortify's `passkeys` feature, laravel/passkeys' migration, and `PasskeyUser` on the User). Without them nothing changes.

### Added

- **One onboarding path, one email** (docs/onboarding.md): invitation email → invitation page → create the account → welcome → workspace, all on kit screens.
  - An invited sign-up sends exactly one email, the invitation. The account is created verified, so a host's `Registered` → `SendEmailVerificationNotification` sends nothing.
  - An unverified account whose email is the invited one is verified when it opens or accepts a member invitation, or accepts a workspace (tenant) invitation. This is a stopgap until Concierge does it upstream.
- **Password rules up front:** `<PasswordInput rules />` (`PasswordRules`) shows "At least 8 characters", ticked once met, and "Not found in a known data breach (checked when you submit)". The values come from `Password::defaults()`, shared as `kitPassword`.
- **A plain breach message:** "This password has appeared in a data breach, so it isn't safe. Pick another.", from `kit::validation.password.uncompromised`.
- **"Add a passkey for next time"** after an invited sign-up, on Fortify's passkey registration. It's dismissible and shown only where the app has passkeys.
- `composer test:onboarding` and `composer test:onboarding-js`, both in CI.

## v0.4.30

### Upgrading

- Run `kit:update plan|apply` for `core`: it adds `Kit/Feedback/StageNotice.vue` and `stageNotice.ts`.

### Added

- **`<StageNotice>`** (docs/stage-notice.md): an announcement laid over a stage or slide, for example "Live demo: <label>" on a projector.
  - It's positioned inside its container, sized in container-query units, and `pointer-events: none`; its content is the default slot.
  - It has Alert's tones (info, success, warning, danger; solid or pale) and a top, bottom or corner placement.
  - Its enter and leave transition only moves when motion is allowed.
  - It's exported from the kit barrel, with `StageNoticeProps` in the contract. `composer test:js` covers it.
## v0.4.29

### Upgrading

- Run `kit:update plan|apply` for `core`. `resources/css/kit.css` gains the default colour tokens, and core declares a `theme` host step. A host whose stylesheet already imports kit.css, or defines the tokens, sees no change: its theme overrides the defaults.

### Added

- **Default colour tokens in kit.css.** The kit's components are coloured with shadcn-vue's semantic tokens, so a host theme without them (a host without the Laravel starter kit's shadcn layer) rendered them uncoloured. kit.css now defines all 18 at the lowest precedence:
  - an `@theme default inline` mapping (`--color-primary: var(--primary)` and so on), which any host `@theme` replaces;
  - the starter kit's neutral light and dark values on `:where(:root)` and `:where(.dark)`, which any host `:root` or `.dark` overrides.
- **A `theme` host step (core: `resources/css/app.css`).** `kit:add` and `kit:update` plans check that the host's stylesheet imports kit.css or defines every token. If not, attention names the missing tokens and the import; it never blocks.
- `composer test:plan-steps` covers the step on a host fixture without the tokens, with kit.css, with a partial theme and with a full one. It also checks that kit.css maps every token with light and dark values, and that every semantic token a kit stub uses has a default.

## v0.4.28

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:8050a73e…65d3` → `sha256:f902dba8…a247`): it adds `Kit/Workbench/WorkbenchCollapsedBar.vue`, and `Workbench.vue` and `WorkbenchStage.vue` render it.

### Fixed

- **A collapsed Workbench pane keeps a way back.** Collapsing the footer (or the navigator or inspector) also hid its header, which held the only Expand toggle, leaving just a hairline resize handle. A collapsed pane now keeps a slim 2rem bar with its label and an Expand button. The button is in the tab order, with `aria-expanded="false"` and `aria-controls` naming the pane's region. It sits beside a side pane at xl and lg, or under the stage for the footer. Expanding goes through the panel's own `expand()`, which restores its remembered size, and ⌘[ / ⌘] work as before. `composer test:workbench-js` checks each region's bar, its wiring and its aria.

## v0.4.27

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:3aed4d27…db5f` → `sha256:8050a73e…65d3`): the `Kit/Workbench/` files take the four fixes below, and `@kit/ui` exports `InspectorScope`.
- An app that reset the Workbench selection itself on each visit, or added its own document Esc listener, can drop that workaround.

### Fixed

- **Esc after a stage click.** A click on a plain stage element left focus on `<body>`, so the Workbench never saw the Esc. The document listener now handles Esc when focus is on `<body>` or inside the Workbench. It never acts from a field that takes typing, nor while another dialog or a menu is open.
- **Item and element inspectors no longer collide.** `defineInspector(type, component, { for: 'elements' | 'items' | 'any' })`: element selections consider only `elements` and `any` inspectors, item selections only `items` and `any`, so a slide layout and a block type of the same name each get their own. `any` stays the default.
- **No duplicate save status.** `<Workbench save-status="none">` renders no kit status region or conflict alert, for an app whose own form bar in `#actions` announces the save state. `wb.saveState` stays readable, and `kit` stays the default.
- **The selection doesn't outlive an Inertia visit.** The per-key store outlives a visit, so arriving at another item by back or a link kept stale elements. Each Workbench mount, and every change of the server's `current` prop, now selects `current` again and clears the stage elements. Pane prefs and zoom stay per key.
- `composer test:workbench-js` covers each fix.

## v0.4.26

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:38d62d72…7573` → `sha256:3aed4d27…db5f`): the `Kit/Workbench/` files gain stage element selection, and `@kit/ui` exports `WorkbenchElement`.

### Added

- **Workbench stage elements** (docs/workbench.md): the stage can select elements inside the current item (blocks on a slide), and they pick the inspector, so the inspector follows the stage's selection and not only the navigator's.
  - `wb.selectElement(id, type, mode | event)`, with ⌘, Ctrl or Shift to multi-select; `wb.selectElements([{ id, type }])`; `wb.clearElements()`; and a read-only `wb.elements`.
  - Elements aren't pruned by `items`, and they're cleared when the current item changes.
  - **Inspector resolution:** selected elements decide first, by element type (one type → that inspector; mixed types → a common inspector or `@fallback`). Otherwise the item selection decides, as before, and with neither it's `@document`.
  - **Inspector props:** inspectors now receive `{ element, elements, ids, items, types, current }`. The existing props are unchanged.
  - **Esc** closes the drawer, otherwise clears the elements, otherwise clears the item selection (not while typing). F6 is unchanged.
  - `composer test:workbench-js` covers it.

## v0.4.25

### Fixed

- **No Mod warning on every boot under another layout.** Under a layout other than `laravel`, Mod logged `Scaffold [tey/kit] makes a starter-policy, which the … layout doesn't have, so mod:tey/kit is disabled.` each time it resolved commands, because the kit registered its legacy v0.1 `kit:foundation` scaffold anyway. The kit now registers that scaffold only under `laravel`, or when `kit.scaffold_features` is configured. Otherwise a stand-in `kit:foundation` refuses with the same message as before and writes nothing. Hosts don't need a fake `starter-policy` generator. No stub changed, so there is nothing to re-pin. `composer test:layout` now checks that boot and command resolution log nothing under both layouts.

## v0.4.24

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:30386b0a…ee06` → `sha256:38d62d72…7573`) and `notes` (`sha256:640b093e…53d2` → `sha256:1d5fe0eb…9370`).
  - core: `Kit/Form/InputSelect.vue` and `inputSelect.ts` keep only the custom properties of a caller's `style`, and `InputSelectProps` gains `popoverClass`.
  - core adds `Kit/utils.ts` (the kit's own `cn`, which BlockStack and Toolbar import instead of `@/lib/utils`) and `Kit/types.ts`.
  - `Kit/types.ts` holds the two Inertia types the kit uses (`Method`, and a minimal `InfiniteScrollRef`). splitState, useScrollList and the Workbench import them from there instead of `@inertiajs/core`. The notes composer's form transform is generic over Inertia's own form-data type, so it names none.
- **The host needs the UI primitives core imports.** `kit:add`, `kit:update plan` and `kit:adopt plan` now block without them and print the command, for example `npx shadcn-vue@latest add button checkbox dropdown-menu input label sheet spinner`. An app built from the Laravel starter kit already has them.
- core's npm requirements now name `@inertiajs/vue3` (not `@inertiajs/core`), `clsx` and `tailwind-merge`. If your `package.json` declares `@inertiajs/core` beside `@inertiajs/vue3`, remove it (the plan points it out).
- A caller that styled an InputSelect with ordinary properties through `style` should move them to `popoverClass` (for the list) or `class` (for the field). `--*` custom properties still pass through.

### Changed

- **InputSelect forwards only custom properties.** It forwarded a caller's whole `style` to its popover wrapper, the one inline-style exemption left after v0.4.23. It now keeps only the `--*` custom properties, from a string, an object or an array (the new `customProperties()` in `inputSelect.ts`), and sets them on the field's wrapper and on the portaled list, so a caller can theme the list through variables. The new `popoverClass` prop styles the list otherwise. The style-conventions check in `composer test:stubs` now has no exemptions: a caller's style may only reach a stub through `customProperties(...)`. `composer test:input-select` checks that a caller's `--x` is kept and `color: red` is dropped.

### Fixed

- **`kit:add core` no longer previews clean on a host without shadcn-vue's primitives.** core imports `button`, `checkbox`, `dropdown-menu`, `input`, `label`, `sheet` and `spinner/Spinner.vue` from `@/components/ui`, but declared only `spinner`, and nothing checked `host_ui`. So such a host got a clean preview, then 17 vue-tsc errors and a broken build.
  - core now declares every primitive, and the library check fails when a stub imports one its feature doesn't declare.
  - Each missing primitive (`<name>/index.ts`, or the declared file) is a blocking `host_ui` step with the exact command, in kit:add and in update and adopt plans (from the primitives the kit's files import).
  - Each primitive's outcome is bound into the plan identity and reported under `host_ui`.
- **No dependency on the host's `@/lib/utils`.** The kit ships its own `cn` (clsx, then tailwind-merge) at `resources/js/components/Kit/utils.ts`.
- **No second copy of `@inertiajs/core`.** `@inertiajs/vue3` pins `@inertiajs/core` exactly, so a host told to add `@inertiajs/core ^3.0` could install a newer copy beside vue3's own, which broke PageProps typing. core now requires `@inertiajs/vue3 ^3.0` instead, and no stub imports `@inertiajs/core`: it would be an undeclared import, which standards' js-imports rule flags and pnpm can't resolve. A declared `@inertiajs/core` at another version than vue3 pins, or two installed copies, raises attention with the fix (an exact match, like the starter's, is one copy and passes). The library check fails on any `@inertiajs/core` import in a stub.
- `composer test:plan-steps` and `composer test:library` cover all of this.

## v0.4.23

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:0e1405b6…593b` → `sha256:30386b0a…ee06`), `shell` (`sha256:03039592…65da` → `sha256:cf7ec863…ded3`) and `session-ui` (`sha256:76d9be3d…be1f` → `sha256:30fb141d…a8e`): `Kit/Feedback/Toaster.vue`, `Kit/Form/FormActions.vue`, `Workspace/Layout.vue` and `Workspace/Dashboard/Page.vue` set their geometry and colour through custom properties.

### Changed

- **No inline property styles in kit stubs.** The anchored toast's position, the upload progress bar's width, the dashboard meter's width and the environment edge's colour were set with inline `style` properties. Each binding now sets only a custom property (`--kit-toast-top`, `--kit-toast-left`, `--kit-form-progress`, `--kit-meter`, `--kit-environment`), and a utility class reads it (`top-(--kit-toast-top)`, `w-(--kit-form-progress)`, `bg-(--kit-environment)`). What renders is the same. `composer test:stubs` now also fails on any `style=` or `:style` in a kit stub that sets anything but `--*` custom properties. InputSelect's forwarding of the caller's own `style` is the one exception.

## v0.4.22

### Fixed

- **The kit boots under any tey/mod layout.** `KitServiceProvider::boot()` threw `Blueprint requires the unmodified laravel layout.` on every request when `mod.layout` wasn't `laravel`, so a host with its own layout (a layout extending `modules`, say) couldn't boot with the kit at all. Kit-owned files always use their canonical paths, never the host's layout, so the boot guard is gone. Only the legacy v0.1 `kit:foundation` scaffold still refuses another layout, when it is run, with a message that says kit features work under any layout. No stub changed, so there is nothing to re-pin. The new `composer test:layout` runs `kit:install`, `kit:add core`, `kit:status`, `kit:update plan` and `kit:adopt plan` under a layout extending `modules` (with an `app/UI` mount), and checks the files and the lineage identity match a `laravel` host. `docs/catalog.md` says which paths kit files use.

## v0.4.21

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c3a4a8af…d8dd` → `sha256:0e1405b6…593b`): `Kit/Form/TimezoneSelect.vue` is now an InputSelect, and `TimezoneSelectProps` gains `autofocus`.
- Run `kit:update plan|apply` for `settings` (re-pinned `sha256:d3afc44b…a193` → `sha256:fb83eb1d…f1e`): it adds the migration `0001_01_02_000402_adopt_display_name_as_workspace_name.php`.
- An app that overrode `Settings\Operations\WorkspaceProfile` to read and rename the workspace's own name can drop that override and its binding: the kit now does the same.
- A workspace's General display name now becomes its workspace name: `php artisan migrate` runs the settings migration `adopt_display_name_as_workspace_name`, which copies a non-empty `display_name` that differs from `workspaces.name` into it, once, and moves `settings_version`. Empty or equal values are left alone, and a rerun does nothing.

### Fixed

- **One workspace name.** General read and saved a `display_name` setting, while the shell and its workspace switcher showed the workspace's own name, so the two could disagree. General now reads the workspace's name and renames the workspace inside the same version-checked transaction (row lock, `settings_version`, intent replay, `stale_settings`). `display_name` stays as a compatibility mirror, and the settings cache is republished after a rollback. The save redirects, and the `shell` share is computed per request, so the next page's switcher shows the new name. `composer test:permissions-http` covers the rename, a newer rename surviving a replay, a stale rename, and a stale mirror.
- **Searchable timezone picker.** `TimezoneSelect` was on the deprecated Reka `Select`, with no search over about 400 zones. It now renders the searchable InputSelect, with the current zone first, then the browser's zone, then the rest A to Z. It is used on General and the two welcome timezone steps. `composer test:stubs` now also runs a conventions check that fails on a native `<select>` or a timezone on the deprecated `Select` in any kit stub.

## v0.4.20

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:fbca3794…c3f4` → `sha256:c3a4a8af…d8dd`): it adds `Kit/Workbench/` and the Toaster's `kit:toast:show` listener. session-ui and mcp change in `src` only.

### Added

- **Workbench** (docs/workbench.md): `<Workbench workbench-key>`, the kit's multi-pane editing surface for a sequence of items with a live preview.
  - **Regions:** `navigator`, `stage` (with an optional sticky `toolbar`), an optional resizable and collapsible `footer`, `inspector`, and an actions bar on StickyBar.
  - **Layout per width:** three resizable, collapsible columns from xl; navigator and stage with an inspector drawer at lg; tabs below lg (the footer's tab only when provided).
  - **Remembered layout:** pane sizes, collapsed panes, zoom and the phone tab, per person and key.
  - **`useWorkbench(key)`:** single and multi-select with the current item and next/prev; dirty and save state; `onRecalculate` with a stale guard; `defineInspector` (selection-driven: `@document`, per type, a common inspector for mixed types, `@fallback`); optimistic reorder through the app's action with a version token, rolled back with a toast on a conflict; stage zoom and fit.
  - **Keyboard:** ↑/↓ and j/k, Alt+↑/↓, ⌘[ / ⌘], `/`, Esc, F6, ⌘+ / ⌘- / ⌘0, with ARIA landmarks.
  - **Server:**
    - `WorkbenchViewModel`, with `WorkbenchItem`, `WorkbenchInspector` and `WorkbenchLayout` data.
    - session-ui's `PUT /me/workbenches/{key}`: versioned, with 409 `stale_write`, kept in `kit_user_state`, so no migration.
    - mcp's optional, read-only `WorkbenchReadTool` base.
  - **Checks:** `composer test:workbench` and `composer test:workbench-js`, both in CI.
- **`kit:toast:show`:** the Toaster renders a toast sent by a kit component that acts without a page visit. Pages still never toast.

## v0.4.19

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:d35bf35a…1248` → `sha256:fbca3794…c3f4`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` deduplicate by toast id.

### Fixed

- **A toast on a full page load shows once.** On a non-Inertia redirect that carries a flash (an OAuth return, say), the Toaster showed the flash on mount and again from the router's first `flash` event, because that event carries an equal but different object and the check was identity-only. Each flashed toast now carries a unique `id` (a ULID, kept across its own re-flashes), and the Toaster shows each id once, remembering a few recent ones. It doesn't compare content, so two identical saves in a row still both toast. A toast without an id falls back to the identity check. `composer test:toast` and `composer test:js` cover it.

## v0.4.18

### Upgrading

- **Warning and error toasts now stay until dismissed.** They used to close after 4 seconds (8 with an action). To keep the old timing, set `kit.toasts.durations` to `['success' => 4000, 'info' => 4000, 'warning' => 4000, 'error' => 4000]` in `config/kit.php`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f19d1c3a…9e8f` → `sha256:d35bf35a…1248`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` gain the duration and away handling.

### Added

- **Toast durations per type:** `kit.toasts.durations` maps each type to milliseconds, or `null` to stay until dismissed. The server resolves it at flash time and sends it as `duration`. Defaults: success and info 4000, warning and error `null`. A timed toast with an action stays at least 8000. An unusable value falls back to the type's default.
- **Away rule:** a timed toast shown while the tab is hidden or the window is unfocused starts its timer only once the window is visible and focused again. Persistent toasts are unaffected.
- `composer test:toast` covers config resolution per type and the action rule; `composer test:js` covers the away logic.

## v0.4.17

### Fixed

- **Saving workspace settings confirms with a toast.** `SettingsController::update` flashed `status`, which nothing renders, so saving General gave no feedback. It now flashes `Toast::success('Workspace settings saved.')->from('save')`, which pulses the Save button. JSON responses are unchanged. `composer test:library` covers it.
- **Revoking an invitation confirms with a toast.** `PeopleInvitationController::destroy` redirected back with no feedback. It now flashes a plain `Toast::success('Invitation revoked.')`, with no `from`, since the row it came from is gone. JSON responses are unchanged.

## v0.4.16

### Fixed

- **Expected refusals are no longer reported.** Core already answered a `RuntimeException` whose message is a registered error code with that code's status, but the handler still reported it, so a refusal such as `bench_unavailable` (404) showed in error trackers as unhandled. A code registered with a status below 500 is now never reported. An unregistered message, or a code registered as 5xx, still is. `composer test:host-boot` covers it, and CI now runs that check.

## v0.4.15

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:73daee1d…3df9` → `sha256:03039592…65da`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `drawer-nav` slot.

### Added

- **Shell `drawer-nav` slot:** `<Layout>` forwards it into the phone drawer, which renders it in place of the kit's pages (props `navigation` and `label`, as for `nav`). An app that uses `nav` can keep a page list in the drawer by passing `drawer-nav` too. Without it, nothing changes: the kit's pages, or none when `nav` is used. `composer test:shell` checks both cases.
- **Workspace-owned model concerns (workspaces).**
  - `Concerns\BelongsToWorkspace` scopes every query to the current workspace, and fails closed with `workspace_required` outside one. A new row takes the current workspace. Saving, moving or deleting another workspace's row throws `workspace_mismatch`.
  - `Concerns\GuardsStaleWrites` adds `updateIfCurrent($expected, $attributes)`: one conditional update on `lock_version` that bumps it, and throws `stale_write` on an old version without writing anything.
  - `stale_write` is registered as a 409 and as an MCP refusal code.
  - See `docs/models.md`. Covered by `composer test:workspace-models`.
  - No stub changed, so there is nothing to re-pin.

## v0.4.14

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:e39f0c88…aa00` → `sha256:73daee1d…3df9`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `nav` slot.

### Added

- **Shell `nav` slot:** `<Layout>` takes a `nav` slot (props `navigation` and `label`) that replaces PrimaryNav at every width, rendered once, and the drawer then lists no pages. An app with its own module switcher can stop keeping its own `Layout.vue` (then `kit:update claim` it back). This is not the existing `bar` slot, which holds compact controls beside the nav. `composer test:shell` checks the stub.

### Fixed

- **`kit:update keep` reports a pending upstream change on the first plan.** Keep recorded the kit's current bytes as already seen, so a change that landed before the keep never showed: a `Layout.vue` kept since adoption planned `held: []` after the v0.4.10 search mount. Keep now records what the host last saw: the release a kept-since-adoption edit was made against, or an owned file's installed bytes. The next plan lists the change under `held` (with the stub to diff and the claim command), once. `composer test:adopt-in-place` covers it.
## v0.4.13

### Fixed

- **Global search ignores case on PostgreSQL.** The people provider matched with `like`, which PostgreSQL treats as case-sensitive, so "view" missed "Viewer" there (SQLite hid it). The new `SearchQuery::matches($builder, $columns)` ORs the columns through Laravel's case-insensitive `whereLike` (`ilike` on PostgreSQL), and `PeopleSearch` uses it. `docs/search.md` now tells providers to use it. No stub changed, so there is nothing to re-pin.

## v0.4.12

### Added

- **`kit:update keep <path>` and `kit:update claim <path>`:** a file the app keeps no longer blocks the lineage's updates. Before, a file kept since `kit:adopt` (an app's own `Layout.vue`) had no original in the lineage, so every later re-pin that changed it was a blocked conflict.
  - `keep` records the file as host-kept (`held` in the lineage record), releasing it if the lineage owned it. Later updates skip it and still apply the rest (new partials are written). Each plan lists, under `held`, an attention line naming what the kit changed upstream and the stub to diff against.
  - `claim` records the file's current bytes as the lineage's original, so later updates replace it while it's unchanged and conflict once it's edited.
  - Both are a reviewed plan, then an apply that writes no file.
  - `--lineage` is optional when only one adopted feature delivers the path.
  - `composer test:adopt-in-place` covers a kept file, keeping an owned edit, a re-pin that adds a partial, the attention, and claiming back. The lineage-record format is in `docs/catalog.md`.

## v0.4.11

### Upgrading

- After upgrading tey/mod, run `php artisan kit:update refresh-executor` once. An app with an explicit `kit.features` list gets each newly adopted feature added to it.

### Fixed

- **`kit:adopt` switches the feature on, as `kit:add` now does:** with an explicit `kit.features` list in `config/kit.php` (which overrides the lineages), an adopted feature stayed off. Both commands now add each adopted feature to that list, in canonical order. They edit only a literal list of quoted ids, verify by reading the file back, and undo otherwise; a computed list is left alone and named as attention. With `kit.features` null, nothing changes: the lineage switches the feature on. `composer test:install` and `test:adopt-in-place` cover it.
- **A stale `config/kit-executor.json` is named, not opaque:** after a tey/mod upgrade, every plan failed with "Executor provenance input must match the currently verified runtime". It now stops once with `executor_stale`, naming the file, the recorded and the running tey/mod, and the fix. The new `php artisan kit:update refresh-executor` rewrites only that file, and only when it holds a recorded runtime. `composer test:install` covers it.

## v0.4.10

### Upgrading

- Run `kit:update plan|apply` for `notes` (re-pinned `sha256:f174d87e…167a` → `sha256:640b093e…53d2`): the composer and the card editor are now Inertia `<Form>`s ending in `FormActions`.
- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (re-pinned). They add:
  - core: `Kit/Layout/StickyBar.vue`, `Kit/Layout/PageEnd.vue`, `Kit/Search/SearchBox.vue` and `Kit/Search/search.ts`, and update `index.ts` and `contract.ts`;
  - shell: `Workspace/Partials/GlobalSearch.vue`, and update `Workspace/Layout.vue`;
  - session-ui: `Workspace/Search/Page.vue`.
- Regenerate TypeScript types afterwards: `ShellData` gains `search`, and there are new `SearchViewModel` and `SearchResult` types.
- Global search is off until you switch on `workspace.search` in `kit.capabilities`. Until then nothing changes for anyone.

### Added

- **Global search (session-ui, opt-in):**
  - `Registry::searchProvider($key, $class, $ability, $label, $order)` registers a provider. Each one returns typed `SearchResult`s for the query in the current workspace, and is asked only for a viewer who holds its ability (through `Access`).
  - A provider that throws, or returns anything else, is skipped and logged without its message or the term. The others still answer, marked `partial`.
  - Surfaces: a results page at `/@{workspace}/search`, and a header field (`GlobalSearch`, ⌘K, Ctrl+K or `/`) with a live palette, in a sheet below `lg`.
  - A read-only MCP `workspace_search` tool returns the same results for the same person, with the new `search.read` scope.
  - Each person has a shared limit, `kit.search.per_minute` (30 by default): a 429 on the page and palette, `rate_limited` over MCP.
  - The kit ships one provider, `kit.people`: the workspace's members.
  - See `docs/search.md`. Covered by `composer test:search` and `test:search-js`.
- **StickyBar (core):** a full-width bar pinned to its container's foot until the end of the content. While pinned it registers with `useStickyBars`, so the Toaster and BulkBar stay clear of it. It is the page-controls sibling of FloatingToolbar, which stays the place for page actions.
- **PageEnd (core):** the end of a long page: Back to top, an optional jump list of sections, and `pagination` and `actions` slots. It shows only past one and a half screens, and hides under a pinned form bar.
### Fixed

- **Notes forms follow the page-form standard:** the composer and the card's editor were `useForm` with a raw `<form>`, so a host's form standard (every page form is Inertia's `<Form>` ending in `FormCard` or `FormActions`) failed. Both are now uncontrolled `<Form>`s with named controls and `FormActions`. The composer's files and intent key join the request through the form's `transform`, so a fresh intent key after a save doesn't read as an unsaved edit, and the reset composer is clean again (`set-defaults-on-success`). Proven in the starter with its form tests and a browser spec.

## v0.4.9

### Upgrading

- `notes` is a new opt-in feature: `php artisan kit:add notes`, then `migrate` and `kit:permissions:sync`. It creates `kit_notes` and `kit_note_intents` and never touches a `notes` or `app_notes` table you already have. To copy those, run `php artisan kit:notes:import --from=<table>` (see `docs/notes.md`).
- Re-pinned: base `sha256:9e6ee91d…cc75cf` → `sha256:c513b68d…f079` (`blueprint.json` names the notes migration block). New pin: `notes` `sha256:f174d87e…167a`. No other feature's pin moves.

### Added

- **Notes (`notes`):**
  - Workspace notes with an author, a colour, a pin, an optional subject and medialibrary attachments, and a stale-write guard and intent replay.
  - Three abilities: `notes.read`, `notes.write`, and `notes.moderate` for changing other people's notes. Moderation is its own permission, never implied by writing; the owner always has it.
  - One principal-first operation (`WorkspaceNotes`), used by the wall page (composer, colour filter, a ScrollList of cards with edit, pin and delete with a confirm, and a save toast), the `notes.recent` dashboard widget, and four MCP capability tools (`notes_list`, `notes_read`, `notes_write`, `notes_delete`).
  - Extension points: `Registry::noteSubject()` (notes about an app's records; visibility follows the record) and `Registry::noteComposer()` (app-side composer options and a record search).
  - `kit:notes:import --from=<table>` copies a legacy notes table idempotently, reports what it skipped, and never changes the source.
  - `composer test:notes` covers it. See `docs/notes.md`.
- **`legacy_table` host step:** a feature can name a table an earlier release or the app kept its data in. When the table exists, `kit:add` and `kit:adopt` show it as attention that doesn't block, with the step's `why` (the notes import). `kit:adopt <feature> plan` now prints a feature's unmet host steps as attention too.

## v0.4.8

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:59b0fc72…74c1` → `sha256:20c52d96…69f8`): it adds `resources/js/pages/Workspace/Dashboard/Partials/` and updates the dashboard page. Regenerate TypeScript types afterwards.
- The dashboard now shows the kit's three widgets (You, Recent activity, People). To keep fewer, set `kit.dashboard.widgets` in `config/kit.php` (for example `['you']`, or `[]` for none).
- Run `kit:update plan|apply` for `core` (re-pinned): it adds `Kit/Table/IndexToolbar.vue`, `Kit/Table/BulkBar.vue` and `Kit/Table/selection.ts`, and changes `DataTable.vue`, `index.ts` and `contract.ts`.
- **An app that overrode the dashboard controller to inject its own widgets** (noted after the release): from this version `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an override that added its own widget props, or wrapped the response, no longer works. Register the app's widgets with `Registry::widget()` instead, or set `kit.dashboard.widgets` to `[]` to drop the kit's own. See [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets).

### Added

- **Dashboard widgets (session-ui):** `Registry::widget($key, $class, $order, $ability)` adds a card to the dashboard, and `Registry::widgets()` lists them in order. A widget extends `Dashboard\Widget`, a typed ViewModel with a frame (`title`, `component`, `span`, `state`, `empty`, `error`, `actions`) and its own props. Each widget is gated by its ability through `Access` (so its capability, entitlement and permission apply), and one that throws becomes an error card while the dashboard still answers 200. `DashboardViewModel` gains `widgets`. The kit ships `kit.you`, `kit.activity` (behind `members.manage`) and `kit.people` (a ScrollList). The page renders them in a responsive, span-aware grid (`WidgetGrid`, `WidgetCard`), and an app's widget body lives in `resources/js/widgets/`. With mcp adopted, the read-only `workspace_dashboard` tool returns the same frames, and session-ui declares the narrow token scope `dashboard.read` (now grantable to a connection). See `docs/dashboard.md`. `composer test:dashboard` covers it; there is no browser test.
- **IndexToolbar:** the DataTable's toolbar is now its own component: search plus one choice per filter the server declares with a label and options (`TableQuery::filter($name, $apply, $label, $options)`, sent as TableData `choices`). A value outside a filter's options is a 400 (`InvalidFilterValue`), like an undeclared filter.
- **Bulk actions and selection:** `TableQuery::bulk(Action)` makes the rows selectable (a checkbox column and a page checkbox, `selection.ts`). While rows are selected, `BulkBar` floats at the viewport's foot (a FloatingToolbar, so it registers with useStickyBars) with the count, the actions and Clear; an action posts `ids[]`, after its `confirm`. The selection survives pages, sorts and filters until it is cleared or acted on.
- **`TableSelection::from($request, $scope)`:** resolves a bulk action's `ids[]` inside the handler's own scope and fails closed: no ids, more than 100, a malformed id or any id outside the scope refuses the whole request (422).

## v0.4.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned).

### Added

- **`kit:toast` event:** the Toaster dispatches a `kit:toast` window event with the toast as `detail` whenever it shows one, so an app can keep its own history (a notification bell) of server-flashed toasts.

### Fixed

- **Form saves are a toast trigger:** `FormActions` renders `data-action` on its submit button (the submit descriptor's `key`, else `save`), so `->from('save')` pulses the Save button instead of falling back to a plain toast.

## v0.4.6

### Changed

- **Saves pulse by default:** a `success` toast without an explicit `style()` is now `pulse` (a shell toast plus a brief ring on the triggering button) instead of `fly-out`. Fly-out stays available with `->style('fly-out')`. Server-side only; no stub changed, so no `kit:update` is needed.

## v0.4.5

### Upgrading

- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (all three are re-pinned), then `npm install`: core now requires `vue-sonner` and declares `@inertiajs/core`.
- If your app renders its own toaster from the `toast` flash, remove it: the kit shell now mounts one. A FocusShell or custom layout mounts `<Toaster />` itself.

### Added

- **Headless toasts:** `Tey\Kit\Features\Core\Toast` and the `Toastable` concern flash Inertia's `toast`; the controller decides, the frontend only renders. One non-destructive action at most (8s with an action, 4s without). See `docs/toasts.md` for when a controller toasts.
- **Anchored toasts:** `->from('<action id>')` links a toast to the descriptor button that triggered it, with `->style('fly-out'|'anchored'|'pulse')`; fly-out is the default for saves. Falls back to a plain toast when the trigger is gone; `prefers-reduced-motion` gets no flight.
- **Shell-level placement:** `kit.toasts.position` is `auto`, `top-center`, `top-right`, `bottom-right` or `bottom-center`. `auto` picks bottom-center on phones, top-center when sticky bars and right-aligned header actions are present, top-right with sticky bars only, else bottom-right.
- **Sticky bar registry:** `useStickyBars()`; `FloatingToolbar` registers, and toasts keep clear of registered bars.

### Fixed

- The People settings page reads role labels from the server-provided cell value, so it passes tey/standards' descriptors rule.

### Known limitations

- The fly-out and anchored animations are covered by unit tests only; the kit has no browser harness yet.

## v0.4.4

### Fixed

- **`WorkspaceUrl` resolves an id over MCP:** `WorkspaceUrl::slug()` (and so `to()`, `full()`, `shareable()`) read only the web guard, so a bearer-authenticated MCP call (Passport) saw no one and refused a member's workspace id as `workspace_not_found`. It now falls back to the request's authenticated user, still scoped to that person's active memberships; a non-member and a guest are still refused. Web and console behaviour is unchanged.

## v0.4.3

### Added

- **Per-call destructive:** `CapabilityTool::destructive(array $validated): bool` is overridable and defaults to the tool-level flag, and `ConsentGate::confirm()` takes it, so one tool can confirm only some calls. Keep the tool-level flag for the MCP `destructiveHint`.
- **`notFoundCode()`:** an overridable `CapabilityTool::notFoundCode(): string` (default `not_found`) names the code an uncoded `ModelNotFoundException` is refused with, for example `workspace_not_found`. `ConsentGate::refusal()` takes it as its second argument.

### Fixed

- **`operation_failed` is reported:** an unexpected exception that `ConsentGate::refusal()` maps to `operation_failed` is now passed to `report()`, so the host's exception handler logs it. Coded refusals, validation and not-found are not reported.
- **Control arguments no longer reach `call()` (behaviour fix):** `intent_key` and `confirm_token` are stripped from the arguments passed to `call()`, `summary()` and `ToolCalled`; they stay available to the gate. A host that handed arguments whole to an operation no longer needs to strip them.

## v0.4.2

### Added

- **Host MCP refusal codes:** `Registry::mcpRefusalCodes([...])` registers a host's domain codes (`stale_write`, `report_blocked`), which a `CapabilityTool` refusal now passes through as they are, alongside `ConsentGate::CODES`. Unregistered codes are still `operation_failed`.
- **Validation detail:** `validation_failed` carries the field errors as structured content, `{error: 'validation_failed', errors: {field: [messages]}}`. The error text is still the bare code; `ConsentGate::refusal()` returns an array that may carry `errors`.
- **`Tey\Kit\Features\Mcp\Events\ToolCalled`:** dispatched once after a `CapabilityTool`'s `call()` succeeds, with the tool name, class, principal, workspace id, validated arguments and result, inside the intent's transaction. Not dispatched on a refusal, `confirmation_required` or an intent replay.

### Fixed

- **Coded refusals win over `not_found`:** a `RuntimeException` whose message is a known code, including a `ModelNotFoundException` subclass carrying `workspace_not_found`, passes through instead of becoming `not_found`. v0.4.0 checked `ModelNotFoundException` first; v0.4.1 already reordered it, and a regression check now pins the order.

## v0.4.1

### Upgrading

- Run `kit:update` for `core` (the changed `Layout/splitState.ts`) and `mcp`. If your app commits a route snapshot, update it: the `oauth/authorize` routes gain the `RefuseCookieSessions` middleware.
- Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment.

### Fixed

- **MCP consent on cookie sessions (#108):** Passport keeps the consent request in the session, which outgrows a cookie, so consent failed silently on `SESSION_DRIVER=cookie`. The consent routes now refuse there with a 503 page naming the fix and an error log line. A new `config` host step kind (`{key, not, why}`, attention only) lets the mcp plan flag a cookie session driver. Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment. Re-pins `mcp`.
- **`kit:adopt <feature> plan` exit code (#114):** a blocked plan exits non-zero with its output unchanged. This shipped in v0.4.0; v0.4.1 adds no change beyond the check that covers it.
- **SplitPane storage import (#117):** `splitState` imports Inertia's `http` statically, so Vite no longer warns INEFFECTIVE_DYNAMIC_IMPORT on every build. The localStorage fallback is unchanged. Re-pins `core`.

## v0.4.0

### Upgrading

- **Behaviour change for hand-written gated tools:** a tool using `GatedByCapability` is now refused by its `ABILITY` before its operation runs. When the person's roles do not allow the `ABILITY`, the call returns `permission_denied` and `handle()` is not reached, so an operation that refused the same call with its own error code no longer gets to. This is intended. A host that relied on its own code for that case should check for `permission_denied` instead.
- **Regenerate TypeScript types** after updating with the host's typescript-transformer command, so the kit's changed view models and props reach the frontend.
- **Migration needed for `mcp`:** run `php artisan kit:update plan --lineage=mcp`, then `apply`, then `php artisan migrate`. This adds `database/migrations/kit/mcp/0001_01_02_000601_create_kit_mcp_intents_table.php` (the `kit_mcp_intents` table). Until it is migrated, a tool call carrying `intent_key` fails as `operation_failed`. Calls without one are unaffected. Then schedule `model:prune` for `Tey\Kit\Features\Mcp\Models\McpIntent` daily, or expired intents are never deleted (the snippet is in `docs/mcp-tools.md`). Set `kit.mcp.intent_ttl` in `config/kit.php` to change the 24-hour lifetime.
- **Connections (#86) need no migration.** Existing connections keep the narrow scopes they were approved with and behave as before; nothing re-prompts. To give an assistant acts-as-me, the person approves `mcp:act` for the workspace; an assistant whose token asked for narrow scopes must reconnect without them first. The Passport consent screen's wording for `mcp:use` changes to "Act as you in the workspaces you approve…". An app whose own MCP tools caught `RuntimeException` and called `Context::principal()` keeps working; moving them to `ConsentGate` adds the confirmation and the refusal mapping. Intent replay for create/copy tools is not in this release.

- **Apps installed before feature lineages** adopt each feature first with `kit:adopt <feature> plan|apply`, in the order `kit:update` prints, then update. `kit:adopt` plans also run the existing-table check and print their apply command.
- **The `mcp:use` scope label changed** to "Act as you in the workspaces you approve…", and `mcp:act` was added. Update any test that asserts the scope labels, and regenerate any committed MCP capabilities file.
- **Optional:** register `Tey\Kit\TypeScript\ExplanationTypesExtension` in the typescript-transformer config, so page props carrying an Explanation are typed instead of `undefined`.

### Added

- Plans spell out what the host must do beyond the files the kit writes (#8).
  - **What `kit:add` and `kit:update plan` report:**
    - the features' host steps;
    - their npm requirements, checked against the host's `package.json` and `node_modules`;
    - their optional integrations;
    - per feature, which migrations are new, changed or already there.
  - **New `host_steps` kinds:**
    - `env`, checked against `.env.example` only: the kit never reads `.env`. An optional `or_file` also meets it when that file exists; the kit checks existence only and never reads it;
    - `provider`, checked against the registered providers;
    - `schedule`, checked against the scheduled events.
  - **New `optional` list:** integrations a host may add, printed as manual steps.
  - **What blocks:** only `user_model` still blocks a plan. Every other unmet step, and an npm requirement that's missing, outdated or unverifiable, keeps the plan executable, opens the text preview with an ATTENTION block and sets `attention` and `warnings` in JSON.
  - **JSON:** the output is additive: `host_steps`, `unmet`, `missing_packages` and `changed_migrations` keep their shape.
  - **Plan identity:** a `kit:add` plan identity now binds the step statuses and each npm requirement's constraint, declared range and status, but not the installed version.
  - **Declared steps:**
    - mcp: `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` (env; each is also met by its `storage/oauth-*.key` from `passport:keys`), and `model:prune --model=Tey\Kit\Features\Mcp\Models\McpIntent` (schedule);
    - core: `ExplanationTypesExtension` as an optional integration.
  - **Check:** `composer test:plan-steps` covers it and runs in CI.
  - Re-pinned: package `core` `sha256:7aa65789…daae5` → `sha256:d3f0c696…3ebfc`, `mcp` `sha256:9077a379…aa9a` → `sha256:d2135421…bc07` (manifests only; no stub changed).

- `Registry::navigation()` and `modulePage()` take `active` as a list of path patterns, and an entry `route:<name pattern>` matches the current route's name (#93). A module that spans several route prefixes, or one found by route name (`route:trips.entries.*`), lists them all; a link is current when any entry matches. A single string stays valid, and an empty entry still means the workspace root only. An empty list or a bare `route:` refuses at registration. The match is `PageShell::matches()`, covered by `composer test:shell` and `test:library`. No stub changed, so no pin moves.

- `Tey\Kit\TypeScript\ExplanationTypesExtension` types a page's Explanation props as the kit's own (#83). A view model with a `list<ExplanationNode>` or an `ExplanationDocument` prop was emitted by typescript-transformer as `undefined[]` or `undefined`, so the page cast it. Register the extension in the host's transformer config (`$config->extension(new ExplanationTypesExtension)`) and those props become `import('@kit/ui').ExplanationNode[]` and `import('@kit/ui').ExplanationDocument`. It maps every Explanation data class and enum to the type `explanation.generated.ts` exports. `composer test:typescript` generates types for a fixture view model, proves the gap without the extension and the fix with it, and fails if the mapped names drift from the generated file; it runs in CI. `spatie/laravel-typescript-transformer` is now a dev dependency (hosts install it already). No stub changed, so no pin moves.

- `Popover` is exported from `@kit/ui` (#82): contextual detail anchored to a trigger, on Reka's Popover and never modal on desktop. Props `title` or `label` (one names the panel; with neither the trigger does), `side`, `align` (`start`), `offset` (8), `width` (`auto`, `sm`, `md`, `lg`, `trigger`), `closeButton`, `collisionPadding`, `collisionBoundary` and `sheet`; slots `trigger`, `anchor`, `title`, `default` and `footer`; `v-model:open` and a `dismiss` event (`escape`, `outside`, `close`, `navigate`). Collision handling is Reka's alone, so it no longer measures a host shell element. An Inertia navigation closes it, Tab past the last control closes it and moves on, and Popovers nest through Reka's dismissable layers. Below 640px it renders in `Modal` as a bottom sheet, through Modal's public props; it will move onto the shared modal base when that exists. The close button's name is `popover.close` in the kit `ui` lang file ("Close"), overridable per use with `messages`. `composer test:popover` covers the helpers. `ComputedValue`'s overlay is a consumer (width `lg`, close label "Close working") and behaves as before. Re-pinned: package `core` `sha256:0f1835ad…7c2b` → `sha256:0f2545ad…3ff2`.
- MCP tools declared by capability (#85). `#[Capability('<capability>', destructive: false, scope: null)]` names the workspace capability a laravel/mcp tool belongs to, and `Registry::capabilityTools([...])` registers it.
  - A registered tool is absent from the MCP list while its capability is off or out of plan. A call to it anyway is refused as `capability_unavailable` / `entitlement_unavailable` instead of "tool not found".
  - `capabilityTools()` fails closed at boot: no attribute, no `GatedByCapability`, an undeclared capability, no `ABILITY` or `scope` to admit the call under, an `ABILITY` another capability gates, or no `mcp` feature.
  - `CapabilityTool` is a base class for one principal-first operation. It admits the call through `ConsentGate`, refuses unknown arguments, validates, asks for confirmation and maps refusals to codes. Its name defaults to the capability with `.` as `_`.
  - `GatedByCapability` reads the attribute before `ABILITY`. A `CAPABILITY` constant is never read: hosts use that name for their own MCP tool ids.
  - A tool's `ABILITY` stays its permission: the tool is also hidden from a person whose role lacks it, and refused as `permission_denied`. This applies to the kit's own settings tools too, so `workspace_settings_update` is no longer listed for a member who cannot update settings. `Access::permits()` is the role rule `authorize()` uses.
  - `composer test:capability-guard`, `test:entitlements` and the new `test:capability-tool` cover attribute-registered tools and the base class's main path.
  - See `docs/mcp-tools.md`, which includes moving a hand-written tool onto the attribute. No stub changed.
- **MCP consent: one "acts as me" grant, optional narrow grants, and in-chat confirmation (#86).** The new `mcp:act` scope is the default grant: a connection that holds it may call any tool, and Access's role, policy, capability and entitlement checks decide each call, as in the app, so a new tool never needs re-approval. The per-ability scopes (`settings.read`, …) stay as optional narrow grants and only cover their own ability. Assistants request the one scope laravel/mcp advertises, `mcp:use`, so a token with no narrow scope acts as the person (the consent screen now says so), and a token that asked for narrow scopes is held to them. Both the token and the connection must cover the ability, or the call is refused with `connection_denied`.
- `Tey\Kit\Features\Mcp\ConsentGate`, the one gate MCP tools go through: `admit($ability)` returns the delegated principal and the bound workspace; `confirm($tool, $principal, $request, $summary)` is the two-step for destructive tools (a tool is destructive when its `#[Capability]` says so, else by laravel/mcp's `#[IsDestructive]`): the first call returns `confirmation_required` with a single-use `confirm_token` bound to the person, the connection, the tool and the exact arguments (5 minutes, in the cache); the same call carrying it runs, and a reused, expired, forged or mismatched token is `confirmation_invalid`; `refusal($e)` passes known codes through, maps validation to `validation_failed` and a missing model to `not_found`, and hides anything else as `operation_failed`. The settings tools use it.
- Audit entries made through an MCP connection carry `via: {kind: assistant, connection_id, client}`, so they read as "<person> via <assistant>". A `via` the caller already put in the details is kept as given.
- `composer test:mcp-consent` covers the grants, the confirmation, the refusal codes and the audit tag on SQLite memory with real Passport tables, and runs in CI with `composer test:mcp-refresh`, which now runs there too.
- **MCP intent replay** (follow-up to #86). A `CapabilityTool` whose `rules()` accept `intent_key` stores each call's result under it. A retry with the same person, connection, `intent_key`, tool and arguments replays the stored result instead of running again, including a destructive call that was already confirmed, which is not asked again. The same key with other arguments or another tool is `intent_conflict`. A call without `intent_key` runs every time, and a call that fails stores nothing. The operation and its stored row commit in one transaction, so two racing calls with one key run the operation once: the second's work rolls back and it returns the first's result. The new `ConsentGate::recall()` and `remember()` do this for a hand-written tool. The results live in the new `kit_mcp_intents` table for `kit.mcp.intent_ttl` seconds (24 hours by default). After that a key is treated as new, and `model:prune` on `McpIntent` deletes expired rows; see `docs/mcp-tools.md`. `composer test:capability-tool` covers it. Re-pinned: package `mcp` `sha256:f507a2bb…117b` → `sha256:9077a379…aa9a`.

### Changed

- `php bin/catalog-pins.php` prints each catalog feature's current identity (and the base's) next to its pin in `src/Catalog.php`, marked `ok` or `changed`, so re-pinning after a stub change no longer means reading `Blueprint` internals. `--check` exits 1 while a pin is stale, and runs in CI.
- CI runs every `composer test:*` script. `test:tables`, `test:lists`, `test:explanation`, `test:input-select`, `test:js` and `test:split-drop` existed but were never run there. `php bin/ci-scripts-check.php`, itself a CI step, fails when a test script is in neither `tests.yml` nor its `NOT_IN_CI` list (script => reason; empty today).
- Core states `reka-ui` `>=2.11.0` in `requires.npm` (#84). `InputSelect` needs 2.11.0 or later to typecheck and relies on its non-modal behaviour. Still informational: `kit:add` does not install it, so the host's `package.json` must carry it. Re-pinned: package `core` `sha256:c0635fd3…f65a` → `sha256:0f1835ad…7c2b`.

### Fixed

- `Audit::write()` keeps a `via` the caller supplied. Through an MCP connection it overwrote any host `details['via']` (for example `'assistant'`) with the kit's `{kind, connection_id, client}`, so the host's value was lost. The kit now writes its `via` only when the details have none. `composer test:mcp-consent` covers both.
- The `ComputedValue` overlay trigger has `aria-controls` again. The fix in this release still bound `id` and `aria-controls` as `undefined`, which won over Reka's, so the trigger pointed at nothing. `ComputedValueRoot` now exposes `popoverTriggerAttrs`, the trigger attributes without `id`, `aria-controls` and the click handler, and the Popover trigger binds that, so Reka's `aria-controls` points at the panel. `composer test:explanation` checks the source; the browser behaviour is proven in a host. Re-pinned: package `core` `sha256:6c4487fb…342f` → `sha256:93462cac…6fbe`.
- `kit:adopt <feature> plan`, the path a pre-lifecycle host takes:
  - a new migration creating a table the host database already has makes the plan not executable and is listed in `unmet`, as in `kit:add` and `kit:update plan`, including the skip for a migration the host already ran;
  - the plan prints the full apply command (`kit:adopt <feature> apply --intent=… --exclusive-checkout --expected-plan=…`) above its JSON and carries it as `apply`;
  - `composer test:plan-steps` covers both.
- The `kit:add` ATTENTION banner said "Nothing here blocks the apply" above a plan that was not executable. It now says the plan is not executable when it is not. `composer test:plan-steps` covers both banners.
- The plan's existing-table check no longer blocks a migration the host already ran. Re-adding or restoring a kit migration whose tables exist because that same migration was applied before is skipped by `migrate`, so it is not a collision. The check reads the migrations table; when it cannot, it checks as before.

- MCP tools are gated by the `#[Capability]` attribute only. `Capability::of()` and `GatedByCapability` also read a tool's `CAPABILITY` constant, so a host whose hand-written tools keep their MCP tool id there (`assignments.list`) had every such tool hidden and refused as `capability_unavailable`. The constant fallback is gone: a tool without the attribute is gated by its `ABILITY` as before 0.4, and `CapabilityTool`'s default name comes from the attribute alone. A tool moving onto the attribute adds it (see `docs/mcp-tools.md`). `composer test:capability-tool` covers a tool whose constant names an undeclared id.
- The MCP intents table is `kit_mcp_intents`, kit-prefixed like the kit's other tables, so it no longer collides with a host's own `mcp_intents` (migrate failed with 42P07). The migration is unreleased, so it is changed in place. Re-pinned: package `mcp` `sha256:d2135421…bc07` → `sha256:e1bfc73a…ac43`.
- A plan whose new migration creates a table the host database already has is not executable: `kit:add` and `kit:update plan` list it in `unmet`, naming the table and the migration, instead of leaving migrate to fail. The table is read from `Schema::create('<name>'` in the migration and checked with `Schema::hasTable`; a database the kit cannot reach is not checked.
- Plan output:
  - the `kit:add` text plan prints the exact apply command, with `--expected-plan` and `--intent`; `kit:update plan` adds `apply`, the exact command with `--expected-plan`, `--exclusive-checkout` and `--intent`;
  - a `schedule` step names `routes/console.php or bootstrap/app.php (withSchedule)`;
  - `kit:update plan --lineage=<feature>` whose requirement has an update still pending lists it in `depends_on` and gives the order in `order`.
  - `composer test:plan-steps` covers each.

- The desktop `Popover` has an accessible name and takes focus on open. Reka 2.11 merges `aria-labelledby = triggerId` after forwarded props, so the kit's heading id never applied; the panel is now named after mount (by its heading, else `label`, else the trigger), which Reka does not patch again. Open auto-focus moves focus to the panel (`tabindex="-1"`) on the next tick, found by its element rather than the content ref, whose `$el` left focus on the trigger. `ComputedValue` no longer overrides Reka's trigger `id` and `aria-controls` in its Popover, so `aria-controls` points at the panel. Restored for hosts, both removed in #82: the `data-kit-popover` attribute on the desktop panel, and `overlayKey` from `Explanation/scope`, now a deprecated alias of `sheetKey` (removed in 0.5). `composer test:explanation` checks the source; the kit has no browser test, and the browser behaviour is proven in a host. Core re-pinned: `sha256:d3f0c696…bfc` → `sha256:6c4487fb…342f`.

- The MCP authorization screen shows that it is working: Authorize and Deny each show a busy state on submit (the button is disabled, with a spinner, "Connecting…" or "Cancelling…", and `aria-busy`), a second submit is refused, and both reset when the browser restores the page from the back-forward cache. The approve and deny posts end in a redirect to the external client, so the page gets no response to react to. The view is package code, so no pin moves and no `kit:update` is needed. `composer test:mcp-consent` checks the view's source; there is no browser test.

- A `SplitPane` aside never has a scrollbar of its own (#105). With `stickyAside` it used a fixed `100dvh` height and an inner `PaneScroll`, so the page and the aside each scrolled. Now it is `sticky` only while its content fits the viewport below the shell header (measured by a ResizeObserver and the window resize, in the new `Layout/useStickyWhenFits.ts`, fit rule in `Layout/stickyFit.ts`); taller, it sits in the flow and scrolls with the page. `mainScroll` and the phone `sheet` are unchanged. Audit of every stub for an aside or sidebar with its own `overflow-y-auto` and a viewport height: only this one; `SettingsFrame`'s sidebar and the shell header have none. `composer test:js` checks the fit rule and that the aside source has no fixed viewport height or inner overflow. Core re-pinned : package `core` `sha256:6d8a05fb…b1db` → `sha256:7aa65789…daae5`.

- The Modal's `role="dialog"` element is named by the modal's title (follow-up to #82). The package renders that element unlabelled; `ModalPanel` now sets `aria-labelledby` on it, so every Modal, drawer and the Popover's phone sheet is a named dialog. Re-pinned: package `core` `sha256:ee389558…7791` → `sha256:6d8a05fb…b1db`.

- The Popover's phone sheet starts focus on the top of its content again (follow-up to #82). `Modal` takes `initialFocus` (`field`, the default, or `body`); `body` focuses the scrolled-to-top body instead of the first field or button, so a text-only sheet no longer leaves focus on the package's Close button. The Popover passes `body`. Re-pinned: package `core` `sha256:0f2545ad…3ff2` → `sha256:ee389558…7791`.

## v0.3.2

### Fixed

- The dashboard greeting follows the workspace's timezone until the browser has sent its own (#87). With no `tz` cookie and no zone saved for the person, `Good morning, …` used the UTC hour even when the workspace had a timezone set; `DashboardController` now passes the workspace's zone as the fallback (UTC when Settings isn't installed). `composer test:greeting` covers it over HTTP with a frozen clock and runs in CI.
- `DataTable` shows its row count on a single page (#88). The footer's `1–5 of 5` line showed only past one page; it now shows whenever there are rows, and only the page links wait for a second page. `composer test:table-count` checks the stub's template and runs in CI. Re-pinned: package `core` `sha256:42c9c901…ddd9` → `sha256:c0635fd3…f65a` (`kit:update` delivers the fixed stub).

## v0.3.1

### Fixed

- `contract.ts` in v0.3.0 does not parse: the SplitPane props comment lost its opening `/**`, so a host's `vue-tsc` fails on the generated `resources/js/components/Kit/contract.ts`. The comment is restored. `composer test:stubs` now parses every TypeScript stub with Node's type stripper and runs in CI. Re-pinned: package `core` `sha256:4ac9554d…1d1a` → `sha256:42c9c901…ddd9` (`kit:update` delivers the fixed stub).

## v0.3.0

### Upgrading

- BulkDrop's DOM hooks are renamed. A host that copied the earlier dropzone and selects on its attributes (tests, styles, scripts) moves from the `data-capture-*` hooks to `data-dropzone` (the surface), `data-dropzone-overlay`, `data-dropzone-prompt`, `data-dropzone-hint`, `data-dropzone-browse` and `data-dropzone-input`, plus `data-dragging` while a drag is over it. `data-paste-target` is removed and not kept as an alias: paste is handled on the focusable `data-dropzone` surface itself, so focus that element and paste (a page-level paste adapter is not part of the kit).
- **Requires tey/mod ^0.11 and tey/concierge ^0.8.2.** Update with `composer require tey/kit tey/mod:^0.11 tey/concierge:^0.8.2 -W` (the `-W` lets Composer move tey/mod past a locked 0.9 or 0.10).
- **The shell moves out of session-ui.** An app that installed session-ui before 0.3 moves the shell's files onto the new `shell` feature with `kit:update`, one reviewed run per step: `kit:update plan --lineage=session-ui` (releases `pages/Workspace/Layout.vue` and `Partials/` at an interim version), then `--lineage=shell` (installs shell at exactly those bytes, with no write), then `--lineage=session-ui` again to finish, and `--lineage=shell` once more to take shell's own changes. Each run prints the next. An edited moved file stops the first run before any write and prints its diff; edit it back or keep it as the app's. Don't retry the first session-ui run after shell has taken the files: on tey/mod 0.11.0 that retry fails recovery (`Third file value blocks maintenance recovery.`); the fix ships in tey/mod 0.11.1.
- **Replace the user menu and workspace switcher with slots, not aliases (#59).** The shell's `user-menu` and `workspace-switcher` slots default to the kit's partials with the same props. An app that shadowed `./Partials/UserMenu.vue` or `WorkspaceSwitcher.vue` with Vite aliases can fill the slot instead and drop the aliases.
- **`resources/css/kit.css` changed** (the gutter rule, see Fixed). Run `kit:update` to take it; an app that edited the file keeps its edits through the usual three-way plan.
- **Migrate for entitlements:** the `access` feature has a new migration, `kit_entitlements`. Run `kit:update` for `access`, then `php artisan migrate`. Nothing is gated until a feature declares an entitlement, so existing workspaces are unchanged. The old `entitlements` table and `Access::authorize(..., paid: true)` still work for now; move a "paid" check to an entitlement on the capability it gates.
- **People is absent while `workspace.settings` is off.** Settings › People, its modals and its writes now return 404 when the capability behind `members.manage` is off for the workspace or person, like every other surface of an off capability. Before, the section was hidden but the page still opened for anyone holding `workspace.members.manage`.
- **Apps installed before feature lineages** can now update. `kit:update` on such an app refuses and prints the commands to run first. Adopt each feature in place, in the order printed: `php artisan kit:adopt <feature> plan`, then `kit:adopt <feature> apply --intent=<name> --exclusive-checkout --expected-plan=<plan>`. After that, update each feature as an adopted app does: `kit:update plan|apply --lineage=<feature>`. This replaces hand-copying release changes, such as the core `0001_01_02_000100` migration change in v0.2.0.
- **Changed migrations need review:** when a `kit:update` plan would replace a file under `database/migrations/`, the plan lists it in `changed_migrations` with a host step. The apply is refused until you pass `--accept-changed-migrations`. A migration that already ran is not run again, so for each one add a new migration that makes the same schema change.
- A staging deploy now shows the indicator by default. Set `kit.environment_indicator` to `[]` to turn it off, or add an entry for another environment name (`'uat' => ['label' => 'UAT', 'colour' => '#0e7490']`). `session-ui`'s `Workspace/Layout.vue` changed: run `kit:update`.
- Nothing changes for an app that sets none of the new keys. `session-ui`'s `Workspace/Layout.vue` changed (dark logomark and lockup): run `kit:update`. A host that overrides the header's mark through the `mark` slot is unaffected.

### Added

- `Tey\Kit\Mail\FluentMailMessage`: the fluent mail composer (all mail = a Notification composed in PHP as ordered blocks, never a template). Escaped by default; `markdown()`, `markdownLines()` and `markdownView()` are the explicit raw escape hatches; `preheader()`, `headline()`, `facts()`, `table()`, `panel()`, `callout()`, `note()`, `bullets()`, `quote()`, `divider()`, `button()` and `section()` compose the body, and stock `greeting()`, `line()` and `action()` land in order. It needs no service provider: the constructor registers the `kit-fluent-mail` view namespace and appends `resources/views/fluent-mail` to `mail.markdown.paths` (the host's paths keep priority), so it renders through the kit's branded layout when `mail.brand` is applied and Laravel's layout otherwise. `Tey\Kit\Mail\MailConformance` scans a directory for a Mailable, the Mail facade or a `toMail` that skips the composer; `docs/mail.md` shows the host's architecture test. `composer test:mail` covers rendering, the text part, macros and the scanner; run in CI. No change to a feature pin. Graduated from a client app.
- A `shell` feature that needs only core (the Shell spec). It owns the generated `pages/Workspace/Layout.vue` and `Partials/`, which move out of session-ui unchanged: every selection with session-ui generates the same files with the same bytes, and session-ui now requires shell, so `kit:add session-ui` adds it. A host with its own tenancy adopts `core` and `shell` without Concierge, Pennant or Fortify. A selection made before the split (a session-ui lineage or a `kit.features` list without shell) boots with shell carried. Mod keeps a lineage's files, so a host that already installed session-ui cannot hand them to shell: `kit:update --lineage=session-ui` refuses and names the files and their new owner, with nothing written. Pinned: package `shell` `sha256:1b05c57a…9f97` (new); re-pinned `session-ui` `sha256:e4181ca3…df85` → `sha256:0e9134ce…3bdc` (`kit:update` delivers the move).
- ShellData and ShellKind move to `Tey\Kit\Features\Shell\Data`. The `SessionUi\Data` names stay as PHP class aliases, and shell generates `resources/js/types/kit-shell.d.ts` aliasing the old TypeScript names.
- The shell's `bar` slot (the Shell spec): a control that stays in the bar at every width, such as a search opener. It renders once and never in the drawer, so it replaces teleporting into the shell's markup.
- SubNav (the SubNav spec): `ShellData::$subNavigation` lists the active module's pages as a strip of links under the header, with the server's current page, sideways scrolling on a phone and no tab roles. Features register pages with `Registry::modulePage()`; `PageShell` fills it with the pages the viewer may open.
- GuestShell (the Shell spec, GuestShell section): the guest/auth layout, in the core-only shell feature. It has the bar's lockup (the new shared `Partials/Wordmark.vue`), a card, a narrow or wide measure, a title and description, a labelled way back from the host and the appearance switch. It needs no ShellData, user or auth route.
- Capability guard (#54). `composer test:capability-guard` walks every capability in the Registry, kit and app alike. It switches each one off for a workspace and checks that every page from its navigation links, settings sections and placeholders is a 404. It checks that its links, sections and MCP tools leave the shared shell, page props, settings listings, the capability listing and the MCP tool list, and that its abilities are refused as `capability_unavailable`. Switched back on, everything returns. A capability with nothing the guard can see fails it. The kit has no dashboard-widget, command-palette or per-feature action-descriptor registry yet, so those are not checked, and a route served without a navigation link, settings section or placeholder is not discovered. Runs in CI.
- Page actions: `FloatingToolbar` (with `FloatingToolbarDot` and the `provideSurface` / `useSurface` surface context), `ActionBar` (with `ActionLabel`, `layoutActions`, `placeActions` and `isNavigation`) and `SectionHeader`, the one header for sections, widgets, cards and panes (built to the FloatingToolbar, ActionBar and SectionHeader specs). FloatingToolbar takes its actions and status through its slot and reads no store. `Button` takes the pill shape and inverse tone inside a FloatingToolbar and is unchanged elsewhere. `Section` renders its title through SectionHeader; its props are unchanged. `Tey\Kit\Features\Core\Data\Action` gains optional `intent`, `group`, `menu`, `icon` and `newTab` (defaults keep existing payloads valid). `composer test:js` runs the placement and number tests. Graduated from a client app. Re-pinned: package `core` `sha256:234e79df…38b4` → `sha256:d82d47dd…966f` (new stubs; `kit:update` delivers them).
- Page-action follow-ups (spec amendments). FloatingToolbar's slot is ActionBar and status only (every action is a descriptor, so it overflows into the ⌄ menu on phones); a button or link slotted directly warns in development. `ButtonGroup` now takes its look from `paper` and neutral outline tokens in `kit.css` (`bg-paper`, `ring-outline`, `text-outline-foreground`, `text-outline-muted`, `bg-outline-wash`), defaulting to its old gray and white values so the default theme is unchanged; a theme or a surface overrides the `--kit-*` variables, and the FloatingToolbar pill does. ActionBar keeps its own segments on the pill (ButtonGroup has no hook for the trigger's name, focus hold, pill shape or a filled segment).
- ButtonGroup's menu panel now uses `bg-popover` and `text-popover-foreground` in every theme (it was `bg-white`; same pixels in default light). The `--kit-paper` / `--kit-outline*` aliases stay: none of the shadcn surface tokens (popover, border, popover-foreground, muted-foreground, accent) equals the group's old gray/white values in the neutral sheet, so mapping them would change the default look. Hosts must load kit.css. Core re-pinned (see the todo).
- Menu family (built to the Dropdown, DropdownItem, DropdownLink, DropdownConfirm, DropdownAction, DropdownDelete, DropdownModalLink and ButtonDropdown specs, over the shared MenuPanel spec). `@kit/ui` exports `Dropdown`, `DropdownItem`, `DropdownLink`, `DropdownConfirm`, `DropdownAction`, `DropdownDelete`, `DropdownModalLink` and `ButtonDropdown`. The menu is not modal; it opens by pointer or keyboard (ArrowUp opens on the last item), never wraps, closes on Escape, Tab and outside presses with the focus rules the spec sets, and an item's confirmation dialog holds the menu open behind it. Enter and Space on an item do what a click does. As specced, `active` (DropdownItem, DropdownLink) and `noun` (DropdownConfirm) do nothing, DropdownLink's button form is always `type="submit"`, and an item with no `href` is an Inertia link to `#`. `Kit/Menu/DropdownMenu.vue` (an auto-wrapping icon menu) is shipped but not exported, because `DropdownMenu` is the shadcn primitive's name in `@kit/ui`. `ButtonGroup`'s menu now runs on the same MenuPanel (the default theme renders the same pixels; the panel is non-modal, so its trigger keeps its hover look while open). The confirmation dialogs use the kit's `Dialog`; the delete and danger icons use `text-destructive`. `composer test:js` runs the edge-placement test. Graduated from a client app's clean-room rebuild. Re-pinned: package `core` `sha256:b71a9bba…41e5` → `sha256:0d00abfe…c64b` (new stubs; `kit:update` delivers them).
- **Form inputs take the `Input*` names (0.3 rename).** `NumberInput` is now `InputNumber` and `Checkbox` is now `InputCheckbox`, in `@kit/ui` and as `Form/InputNumber.vue` and `Form/InputCheckbox.vue`. The old names stay for one minor as deprecated aliases and are removed in 0.4: the `@kit/ui` exports, the `NumberInputProps` type, and the `Form/NumberInput.vue` and `Form/Checkbox.vue` files, which are now the same component re-exported. `Select` is deprecated in favour of `InputSelect`, the kit's rich select, and stays unchanged until 0.4. It isn't an alias, because the props don't map one to one: InputSelect has no `autofocus`, and it turns search on past 8 options. The kit's own Login and Connections pages now use `InputCheckbox`. Run `kit:update` for `core`, `session-ui` and `mcp`, then rename your own imports before 0.4.
- **FormFieldset's title now sits inside its `<legend>`, as a heading.** FormFieldset is a native `<fieldset>`: the title is a real heading at `level` (default 2) inside the legend, and the description follows the legend, tied to the fieldset by `aria-describedby`. That is a UX change for screen readers: what they announce changes. The title is now the group's name as well as a heading, so moving focus into any field in the group announces the group's name, and the description is read as the group's description, not as part of its name. The prop is `title`; `legend` keeps working as a deprecated alias until 0.4. The title and description move from raw gray steps to the `foreground` and `muted-foreground` tokens, the grid gaps grow to 6 across and 8 between rows, and groups after the first are set apart by a rule (`separator="space"` for space only), decided by position among sibling fieldsets. Run `kit:update` for `core`.
- **LockedForm, EditOnly and `useLocked()`** (`Form/LockedForm`, `Form/EditOnly`, `Form/useLocked`, exported from `@kit/ui`; built to the LockedForm spec). `<LockedForm :save="save">` wraps one or more FormCards, or a lone FormFieldset, and locks them when the page's save descriptor is `null`: the server decides, never the client. Locked, every kit control turns read-only through the context, never `disabled`: native `readonly` on Input, PasswordInput, Textarea and InputNumber, `aria-readonly` on InputCheckbox, InputSelect (still a combobox, showing the chosen label and opening nothing) and the deprecated Select, with the `muted` fill and the value at full contrast. Edit actions are hidden, not disabled: Repeater's add, remove and move, FileInput's picker (it shows the current file's name, size and link from the new `current` prop, or "No file"), ActionBar descriptors with `intent: 'edit'`, and anything inside `<EditOnly>`. Each FormCard shows the notice in place of FormActions ("You can view these settings but not change them.", `quantity="one"` for the singular, `notice` to replace it, `reason` for a second muted line), nothing submits (Enter included), a nested LockedForm inside a locked one stays locked, and a FloatingToolbar inside the area shows the reason or "View only" as status text. `Tey\Kit\Features\Core\Data\Action` documents `intent: 'edit'`. `composer test:js` runs the new `tests/js/locked.test.mjs`.
- **FormFieldset rework** (built to the FormFieldset spec): a native fieldset and legend, `title` (`legend` stays as an alias until 0.4), `description`, `level` (2 to 6), `legendHidden`, `separator` (`rule` or `space`), `fullwidth`, the `title` and `description` slots, attributes on the fieldset, and a development warning for a fieldset with no title. Locking comes from LockedForm only: there is no `locked` or `disabled` prop and `<fieldset disabled>` is never used. A lone fieldset in its own LockedForm shows the notice under its legend and description, before its grid. Re-pinned: package `core` `sha256:20b806a1…71a6` → `sha256:4ac9554d…1d1a` (`kit:update` delivers the new and changed stubs).

- Entitlements per feature (#53). A feature or app declares that a capability is part of a plan: `$registry->entitlement('workspace.projects', limits: ['projects'], upgrade: ['label' => 'Upgrade to add projects', 'suffix' => 'settings/billing'])`. `Entitlements` resolves it per workspace: the workspace's row in `kit_entitlements`, then the app default (`kit.entitlements`), then not included. It is read once per request like capabilities. `Entitlements::enforce()` checks a limit on the server and refuses with `limit_reached` (403, with a plain message). A billing integration only calls `Entitlements::set()`; the kit has no billing provider. Entitlements are a separate gate from capabilities and permissions: `Access::authorize()` checks the entitlement after the capability. A capability left out of the plan is absent by default (`entitlement_unavailable`, 404, gone from the shell, settings and the MCP tool list). An entitlement that opts into `upgrade` refuses with `entitlement_required` (403) instead, and `Entitlements::upgrade()` gives the page a server-built upgrade action. There is no plan check in Vue or TypeScript. `composer test:entitlements` covers two features with different plans on one workspace, a limit over HTTP, and the separation from capabilities and permissions. Runs in CI. Re-pinned: package `access` `sha256:25d5b8be…38548` → `sha256:a179066f…26d98` (a new migration stub; `kit:update` delivers it).

- `kit:adopt <feature> plan|apply [--from=<release>]` (#3) records a feature that is already on disk as its own lineage. It overwrites nothing. Each file is handled by what it holds:
  - **Identical** to a known release: claimed with no write.
  - **Missing:** created.
  - **Edited:** kept by the app, against the release it was edited from. That is the `--from` release, or by default the closest one (the release most files match). A later `kit:update` leaves a kept file alone until a release changes it. It is then a conflict, as an edited owned file is. Once the app's bytes match the release, the file is claimed.

  A failed apply rolls back, as `kit:update` does. If an apply is interrupted (a crash), every plan refuses with the pending hint, which names `kit:adopt <feature> rollback --intent=<intent> --exclusive-checkout`. The rollback removes the files the adoption created, and refuses, removing nothing, if one has changed since. `resources/features/history.json` holds the file hashes of each tagged release; regenerate it with `php bin/catalog-history.php` before tagging a release. Its `current` entry must match the live catalog, and the check fails otherwise, so a stub change regenerates it in the same PR. `composer test:adopt-in-place` covers it: a clean-room pre-lifecycle host, adopt, update and a follow-up update, with the edit kept throughout.

- **Environment indicator (#50).** Outside production the shell shows where you are without a badge: `[Staging] ` before the document title and a 2px edge across the top of the signed-in shell, in the environment's colour (with its name for a screen reader). It is configured per environment in `kit.environment_indicator` (`label`, `colour`), ships with a `staging` entry only (`#b45309`, 5.0:1 on white and 3.9:1 on the dark shell), carries no product name, and is never shown in production whatever the config says. A label that is not plain text, or a colour under 3:1 on the light or dark shell (the brand's own page colours when set), is not shown. `PageTitle` applies the prefix; the server shares `environment` (null where there is none) for a host's own layouts, such as a guest page, to draw the same edge.
- **Brand theming (#49).** `kit.brand` now expresses a whole theme, and every key is optional: unset, the CSS is byte-for-byte what v0.2.2 printed (a check asserts it).
  - **Surfaces:** `background`, `card`, `border`, `muted` and their `_dark` twins print `--background`, `--card`, `--border` and `--muted`.
  - **Roles:** `button` (`accent` or `ink`), `button_dark` (`accent` or `theme`), `header` (`theme` or `ink`) and `marker` (`strong` or `accent`) split what `style` bundled. `style=mono` is the same as `button=ink, button_dark=theme, header=ink, marker=accent`, so ink buttons in light mode and accent buttons in dark mode on the theme's own header is `button=ink, button_dark=accent, header=theme`.
  - **Links:** `link` and `link_dark` set `--brand-link`; nothing is printed unless set.
  - **Header rule:** `header_rule` (`accent` or a `#rrggbb`) draws a 2px line under the header (`accent_dark` in dark mode).
  - **Logomark and lockup:** `logomark_dark` is shown under `.dark`; `lockup` shows the `wordmark` image (`wordmark_dark` in dark mode), or the product's name when there is none, beside the mark from the `sm` breakpoint up. The shell's `product` gains `logomark_dark`, `wordmark`, `wordmark_dark` and `lockup`; a host's own guest layout reads them from `shell.product`.
  - **Fonts:** `KIT_FONTS=inter-serif` pairs Inter (text) with Instrument Serif (display).
  - **Contrast:** `contrastProblems()` reads the accent and the links against the configured page, card and dark surfaces instead of `#ffffff` and `#09090b`, and checks `link` and `link_dark`.

### Changed

- Renamed `NumberInput` to `InputNumber` and `Checkbox` to `InputCheckbox`, with deprecated aliases until 0.4, and deprecated `Select` in favour of `InputSelect` (see Upgrading). Re-pinned: package `core` `sha256:0d00abfe…c64b` → `sha256:2507f221…b510`, `session-ui` `sha256:0e9134ce…3bdc` → `sha256:241edd9b…c934`, `mcp` `sha256:e422e9e4…daa4` → `sha256:f507a2bb…117b` (`kit:update` delivers them).

- SplitPane, PaneScroll, PaneTabs, PaneToggle and BulkDrop in the core Kit stubs (`resources/js/components/Kit/Layout/`, `Kit/Form/BulkDrop.vue`). SplitPane is work beside its context on Reka's Splitter, with the aside in a drawer below the breakpoint; its layout state is server-first through an optional preference descriptor, with a guarded localStorage fallback. The preference write goes through Inertia's HTTP client (`http` from `@inertiajs/vue3`), so a host's `http.onRequest`/`onResponse`/`onError` interceptors apply and the client sends the XSRF token; a failed write falls back to localStorage. BulkDrop is the one dropzone (drop, paste on the focused surface, or browse) and emits files only: no upload transport, FileViewer or preference endpoint ships. `composer test:split-drop` covers the storage, the sender and the selection rules. Graduated from a client app. Re-pinned: package `core` `sha256:2507f221…b510` → `sha256:20b806a1…71a6` (new stubs; `kit:update` delivers them).

### Fixed

- `FluentMailMessage` no longer fails with "View [preheader] not found" when `Illuminate\Mail\Markdown` was resolved before the first message (a queue worker, an earlier mail). The kit's mail paths now also reach an already-resolved renderer. `composer test:mail` resolves the renderer first, then renders a message with a preheader.
- The kit's branded mail views are appended to `mail.markdown.paths` instead of prepended, so a host's published `resources/views/vendor/mail` (header, footer, themes) wins over them; the kit's views still apply where the host has none. `composer test:mail` covers both.
- `mail.brand` carries the `accent_foreground`, `accent_strong` and `ink` tokens; the branded button's text uses `accent_foreground`. `docs/mail.md` lists the keys and how a host adds its own.
- The kit's invitation email (`InviteNotification`) is composed with `FluentMailMessage`; the `kit-mail::invitation` view and the `kit-mail` view namespace are gone. `composer test:mail` runs `MailConformance` on the kit's own `src/`.
- Generated TypeScript types no longer read `undefined` for actions in a host that has laravel-actions. Its global `Action` alias won over the docblocks' imported `Action`, so the transformer typed the shell's workspace `settings`, `all` and `leave`, list items' `actions` and the workspace index's `leave` as `undefined`. The kit's docblocks now name `Tey\Kit\Features\Core\Data\Action` fully qualified, and `composer test:library` refuses a bare one. Regenerate the app's types (`composer types:generate` in the starter).
- Settings › People stayed reachable by URL with `workspace.settings` off (found by the capability guard). `WorkspacePolicy::manageMembers` now denies as not found while the capability is off.
- **ComputedValue's phone sheet spans the viewport.** On a 390px phone the sheet stopped at 375px: the modal package's scroll lock reserves a scrollbar gutter on `<html>`, which narrowed the sheet's fixed container by the scrollbar's width. `kit.css` now releases the gutter below 640px while a modal is open, so every phone-width sheet and modal is edge to edge (verified at 360, 390 and 430px).

## v0.2.2

### Added

- **InputSelect**, the kit's rich select (`Form/InputSelect`, exported from `@kit/ui`), built to the InputSelect spec on Reka's Combobox. It picks one value or many (as chips, or a count past `maxChips`) and stays non-modal while open. Search turns on past 8 options. It handles groups, remote search (debounced, from 2 characters), clear, opt-in select all, chosen first and create, read-only, and the phone sizes. With `name` it submits native `name` / `name[]` inputs. Its strings ship in the kit's new `lang/en/ui.php` (`kit::ui`, shared with pages as `kitUi`). Override them for the app in `lang/vendor/kit/en/ui.php`, or for one use with `messages`. The existing `Select` is unchanged.
- **Known limitation (InputSelect):** Reka's combobox has no supported way to stay non-modal, so InputSelect relies on an undocumented Reka behaviour (no page hiding inside a closed popover), verified on reka-ui 2.11.0. A Reka update could make the open list hide the rest of the page from screen readers again; re-check on each Reka bump.

## v0.2.1

### Fixed

- **MCP connections survive OAuth token refresh.** A connection was bound to one access token, so every MCP client lost its connection (and its workspace) at the first refresh, about an hour in. The `mcp` feature now moves a live connection to the new access token when Passport issues it. It moves only when the connection's old token was spent (revoked by the refresh, or expired) and belonged to the same user and client. A revoked connection stays revoked, and the old token is refused.

## v0.2.0

v0.2.0 is the kit proven in a mature production app. It adds the signed-in shell, people, settings and account pages, workspace permissions and per-workspace capabilities, and graduates Repeater, NumberInput, ScrollList and ComputedValue into the core Kit. It also makes `kit:update` safe on an app that adopted features one by one, and closes a workspace-name disclosure.

### Upgrading

- **Repeater row errors:** the kit's `rowErrorsKey` is its own `Symbol`. If your app has a local `repeaterState` (or a local copy of the key), import the kit's instead: `import { rowErrorsKey } from '@/components/Kit/Form/repeaterState'`. Otherwise fields inside kit Repeater rows (NumberInput included) stop receiving their row errors. The key is not exported from `@kit/ui`.
- **ScrollList:** the default row is slimmer (title, description, meta, badges). Pass the `row` slot (`{ item }`) for a richer row. The row hook `data-queue-row` is now `data-list-row` (rows also carry `data-list-item`). `useScrollList` no longer returns a total, because the list is count-free (cursor only).
- **ComputedValue:** the `data-candidate-*` hooks are now `data-kit-*` (`data-kit-popover`, `data-kit-sheet`). Update selectors and tests.
- **Repeater:** drag-to-reorder is removed. Rows move with the move buttons, which are always visible.
- **kit:update on an app that adopted features:** update each feature with `kit:update plan|apply --lineage=<feature>`, in dependency order, giving each lineage its own `--intent`. Plain `kit:update` and `--target` (release fixtures) are refused there and print the commands. A failed `kit:update apply` now rolls back: no files or journal stay behind. Fix the cause, then plan and apply again.
- **Run `kit:add` and `kit:update` from the app's main checkout,** not from a git worktree.
- **Apps installed before feature lineages** cannot `kit:update` yet (#3).
- **Workspace reads are member-only:** `MemberWorkspaces::workspace()` returns null for a workspace the person is not in. Use `WorkspaceUrl::currentMemberId()` for anything rendered, and pass a `Workspace` model (not an id) when building a link outside a session, such as mail or a queued job.
- **Brand mail is opt-in:** mail carries the brand only with the session-ui feature or when `kit.brand_mail` is `true`.
- **Migrate before `/up` is healthy:** the health checks need the tables of the features you adopted (new migrations: `kit_capability_overrides`, `kit_permission_defaults`, `constrain_audit_events_workspace_id`). Run `php artisan migrate` and `kit:permissions:sync` on deploy.
- **Dialog and Modal** replace `ConfirmDialog` and `FormModal`; **Modal** now returns focus to its opener, and Dialog gains `@closed`.

### Added

- Repeater and NumberInput graduate into the core Kit (`Form/Repeater`, `Form/NumberInput`): repeating rows with add, remove and move buttons and per-row errors, and a number field that keeps its text while typed. `kit:update` delivers them with `core`. Graduated from a client app.
- ScrollList graduates into the core Kit: a cursor-only list with an `Load more` button, client-side or server-driven (`useScrollList`), a slim default row and a `row` slot (`{ item }`) for a richer one. Graduated from a client app.
- ComputedValue: a figure that opens its working (the ComputedValue spec and conformance rules 17 and 70). `@kit/ui` exports `ComputedValue`, `ComputedValueRoot` (headless), `ComputedValueScope` (show or hide all, optionally remembered) and `ComputedValueToggle`, plus the `Explanation*` types. `core` generates them into `resources/js/components/Kit/Explanation/`. The server sends a `Tey\Kit\Explanation\ExplanationDocument` (Laravel Data). It refuses JSON floats, unsupported versions, dangling operands, unsafe links, malformed decimals, cycles and documents deeper than 16, larger than 1000 nodes or 256 KiB. The browser only displays the strings it is given and does no arithmetic. `php bin/generate-explanation-ts.php` writes `explanation.generated.ts` from the PHP types, and `--check` fails when it is stale. The desktop popover and phone sheet that ComputedValue opens are internal and not exported. `composer test:explanation` covers the contracts, the generator and the working text. Graduated from a client app. Re-pinned: package `core` `sha256:748e41c2…fe0d6` → `sha256:f3c7a801…81a2d` (new stubs; `kit:update` delivers them).
- Permissions. A feature (or an app) declares a permission and its default Concierge roles on the registry: `$registry->permission('workspace.settings.update', ['owner', 'admin'], capability: 'workspace.settings', abilities: ['settings.write'])`. The kit declares `workspace.settings.update` and `workspace.members.manage`. `php artisan kit:permissions:sync` creates the missing permissions and grants the defaults, re-runs change nothing, never revokes, and reports custom grants, undeclared permissions and default roles the app does not configure (skipped), and default roles it configures but has not created (exits nonzero). Roles stay Concierge's, per workspace; the kit adds no role system. `WorkspacePolicy` (`view`, `update`, `manageMembers`) checks active membership, the role grant in the workspace's team context and the permission's Pennant capability, all three. `Permissions::flags()` gives a page its `can` map from those checks. `Access::authorize()` decides an ability a permission replaces (`settings.write`) by the permission, not by ownership; other `.write` abilities stay owner-only. The settings page's `canWrite` comes from the policy. Run the sync after `migrate` on deploy. A default is granted once: the `access` feature's new `kit_permission_defaults` migration records which defaults the sync has applied, so a default someone removed is not granted again, and one that is new since the last sync is. `HandleInertiaRequests` shares the current workspace's `can` map. `composer test:permissions` (registry, sync, policy, flags) and `composer test:permissions-http` (settings update over HTTP on real Concierge tables and spatie teams: admin allowed after the sync, member 403, capability off 403) run on SQLite memory. Run in CI. Re-pinned: package `access` `sha256:5030011a…e38b0` → `sha256:26bbe931…86528` (a new migration stub; `kit:update` delivers it).
- Capabilities per workspace and person (#51, #52). A capability can be turned on or off for one workspace, or for one person in it, over the global switch. `php artisan kit:capability <workspace> [capability] [on|off|clear] [--user=] [--operator=] [--reason=]` lists or changes the overrides; the `access` feature's new `kit_capability_overrides` migration stores them. A capability that is off is absent everywhere, never shown as forbidden, and a registry guard checks the permissions that name one. Membership, permissions and overrides are read once per request.
- The shell: a dark bar with ghost controls, a split workspace switcher (one list, with leave in its menu), a back strip and breadcrumbs as a sub-bar (gretel, one crumb per page route in both URL forms), page titles as `{Page} · {Product}`, `FocusShell` for signed-in pages outside a workspace, `GuestShell` for guest pages and `FocusPage` for the page measure. The mark is the platform identity (`APP_NAME` and a configurable logomark), never a workspace name.
- Workspace URLs: tenants are marked with `@` (`/@{workspace}/...`, short paths, `/me/workspaces`), with one URL helper. The dashboard is the workspace root (`/@{workspace}`, short form `/`); `/dashboard` redirects (301).
- Pages as typed ViewModels: one shell share, `layout()` with navigation, titles and breadcrumbs, persistent section layouts, action descriptors in place of `can` maps and flags, and the `Page` container (boolean widths, states).
- Settings: a landing page of cards with a section sidebar, sections registered by providers, a `Placeholder` component and `Registry::placeholder` for a planned section, and a save that is always shown. Connections and the AI assistants page (approval descriptors and assistant cards) are Inertia pages.
- People: members and invitations in Settings with invite, change role, remove, resend and revoke through Concierge; transfer ownership (owner only, password confirmed again, the old owner becomes an admin); leave workspace; a transfer and leave in the General danger zone.
- Invitations: a branded email with the inviter's personal message, a specific subject and a preheader; an accept page that shows the workspace and inviter, signs in or creates the invited account, joins as the current account and says who invited you.
- Account pages under `/me`: profile with email re-verification, password, security with two-factor, devices and a security log. Deleting an account deletes what the kit remembers about the person.
- Avatars: a Media Library collection on the user, upload, replace and remove on `/me/profile`, served from the private media disk by a cached `/avatars/{token}` route; initials are the fallback.
- Brand: brand config, `kit:brand` assets, head links, branded mail with a footer from the registry, a private brand mode, accent companions, fill and mono styles, config-driven presets, a local-only `?brand=` switch and a 4.5:1 contrast guard. Mail address roles: replies go to support, with a privacy contact in the footer.
- First run: welcome steps, a dashboard welcome card, invitation forks and a one-click demo; the timezone is captured with a `tz` cookie, an auto-saving wizard step and the owner's workspace timezone; the no-workspace page names the product in the bar.
- Deploy: `kit:preflight` (with `--env-file` to rehearse a deploy, and Passport keys in any form a dashboard can hold), `/up` health checks, optimize registrations, and roles and permissions synced when migrations end.
- `Dialog` and `Modal` replace `ConfirmDialog` and `FormModal`; Invite and Change role are server modals. `Dialog` gains `@closed`, which fires once it has closed and returned focus. Also new: `Alert`, `Toolbar`, `BlockStack` settings cards, read-only input styling and an `autofocus` prop, a kit stylesheet with the pointer cursor, and release blueprint E for tey/concierge 0.8.

### Changed

- The workspaces feature requires tey/concierge `^0.8.1`; the default Concierge local sign-in is limited to the local environment.
- Forms are `FormActions` and `FormCard` on Inertia `Form` pages with a sticky status bar, a leave warning whenever a form holds unsaved input and confirmations on destructive actions; tables are `DataTable` over Concierge's `MembersIndex`; listings use the `QueryBuilder` and resource controllers, and invitations live under `/me`. Controller input-limit errors map to 400.
- Workspaces are ordered by their key, and a layout can name its own icon links.
- `kit:update` on an app that adopted features works per feature. Update each with `kit:update plan|apply --lineage=<feature>`, in dependency order; plain `kit:update` and `--target` are refused there and print the commands.
- `Modal` returns focus to its opener when it closes, and its Close button is 44px on phones. The phone sheet behind ComputedValue is labelled by its title, takes focus once it has opened and returns it on close.

### Fixed

- `kit:update apply` fails closed. A fresh apply that does not complete restores every planned file (created files and their empty directories are removed, changed files get their prior bytes back) and drops the intent's journal, so no files stay behind. A reopened intent still resumes.
- ScrollList keeps focus while it loads more: `Load more` uses `aria-disabled` and ignores clicks while busy instead of becoming `disabled`, so keyboard focus is not dropped to the page.
- Modal and Discard & close: the confirmation closes first and the modal closes after it, so focus returns to the opener rather than the page body (a plain Escape close did the same before).
- DataTable phone cards show their field labels (#55). The label is chosen by visible column position instead of being the first child of every cell; the title column and the action labels stay hidden.
- A security log that cannot be written never blocks a sign-in; a scrubbed security-log failure is reported. Input-limit errors register only on the framework handler, so artisan boots under Collision.
- Fixes to the first release of these pages: modal layering, the People member sort, bad table queries and modal redirects; the home crumb is the workspace root; breadcrumb links, the personal chooser and settings sections.
- A host that adopted only some features boots only those. `FeaturesServiceProvider` registered the `/up` health checks, the mail brand and the post-migrate `CatalogSync` whatever was adopted, so a core-only host answered `/up` with 500 ("the table workspaces is missing"), had `mail.from` and `mail.reply_to` rewritten and ran a sync it had no catalog for. Now the health checks require `workspaces` and `concierge_memberships` only with the workspaces feature, `roles` only with access and `kit_meta` only with core, and skip the brand placeholders on an unbranded host; the brand reaches mail (and is judged by `/up`) only with session-ui, or when `kit.brand_mail` is `true` (`false` turns it off for session-ui); `CatalogSync` is registered only with access. Nothing adopted registers no health listener. `php tests/HostBootCheck.php` boots core-only, nothing-adopted and full hosts on SQLite memory. Re-pinned: package `core` `sha256:77383e5f…ac422` → `sha256:07be2974…4983c`.
- Core migrates alone. `audit_events.workspace_id` was `foreignId(...)->constrained()` in the core migration, so a core-only host failed on Postgres (no `workspaces` table; SQLite let it through). Core now creates it as an indexed `unsignedBigInteger`, and the `workspaces` feature's new migration `0001_01_02_000204_constrain_audit_events_workspace_id` adds the foreign key to `workspaces` when it is missing. A database that already has the key (every host that ran the old core migration with workspaces) is left as it is; `down` changes nothing. `Audit::write` is unchanged. Checked on Postgres: core alone migrates with no key, adding workspaces adds it (an unknown workspace is refused), a re-run is a no-op, and an existing full host's keys are unchanged.
- `Overlay/Modal` types its package instance by the one method it calls (`close`), so `vue-tsc` no longer fails where `@inertiaui/modal-vue` 3.1.2 ships no declaration for its Modal component; unused refs and optional-prop defaults that tripped stricter compiler options are gone.
- `Form/Button` no longer needs a `bar` variant in the host's `components/ui/button`: `bar` renders the standard `ghost` variant with the shell bar's foreground colour classes. Props and `ButtonProps` are unchanged.
- Core's `requires.npm` carries real ranges instead of `*`: `@inertiaui/modal-vue` `^3.1.2`, `@lucide/vue` `^1.0.0`, `@tanstack/vue-table` `^8.0.0` (9 breaks `DataTable`). Still informational: `kit:add` does not install them. Re-pinned: package `core` `sha256:e84aceae…` → `sha256:8354c2cf…`, `workspaces` `sha256:ed0a9139…` → `sha256:8aa53c04…` (a new migration stub; `kit:update` delivers it).
- Core declares what its components import. `requires.npm` lists `@lucide/vue` and `@tanstack/vue-table` (informational; `kit:add` does not install them) and `requires.host_ui` lists the host-owned `spinner` component (`@/components/ui/spinner`). `DataTable` stays exported from `@kit/ui`, so existing imports keep working.

### Security

- A workspace the person is not a member of is no longer read for display. `MemberWorkspaces::workspace()` used to fall back to any workspace by id, so a page could show the name or slug of a workspace the signed-in person does not belong to. It now returns only the person's own active workspaces (null otherwise, and for no person). `WorkspaceUrl::currentId()` is documented as not authorization; the new `WorkspaceUrl::currentMemberId()` is the same id only for an active member, and the title, crumbs, shell, settings nav and home redirect use it. `WorkspaceUrl::slug()` (and so `full`, `to` and `shareable` with an int) refuses an id the signed-in person is not in with `workspace_not_found` instead of building a link with an empty slug; in the console with no one signed in (mail, queue, commands) the id resolves unscoped and is refused only when unknown, while a guest HTTP request, or any matched route in a long-lived worker, is refused. Pass a `Workspace` for a link outside a session. Operations still authorize, and a non-member and an unknown slug are refused alike (404). `composer test:workspace-guard` runs on SQLite memory. Run in CI.

## v0.1.5

### Added

- Font sets (#43). `kit.fonts` (`KIT_FONTS`, default `instrument`) names one of three sets: `instrument`, `instrument-inter` or `hanken`. `<x-kit::fonts />` in the layout head emits that set's fonts.bunny.net links and a `:root` block with `--font-sans`, `--font-serif` (display), `--font-mono` and `--font-display-weight`, with system fallbacks. An unknown set name is refused. `composer test:fonts` renders each set, checks the refusal and checks that pages name no font family but those variables. Run in CI.

## v0.1.4

### Fixed

- The `user_model` host step also checks the trait (#40). A user model that implements `Tey\Concierge\Contracts\Member` without using `HasWorkspaces` (through `class_uses_recursive`) is unmet, and the step says which half is missing. `composer test:install` covers a User with the contract only: the preview lists the step as unmet and the apply refuses.
- Host step validation accepts the `implements` and `uses` keys in any order (#41), and still refuses a missing, extra or non-string key. `composer test:catalog` covers both orders and each refusal.

## v0.1.3

### Fixed

- `kit:add workspaces` names the user model host step (#36). The `workspaces` manifest declares it in a new optional `host_steps` field: the user model must implement `Tey\Concierge\Contracts\Member`, through the kit's `HasWorkspaces` trait. `kit:add` checks it by reflection. It checks that `kit.models.user` (else the `users` auth provider's model) and the `concierge.models.user` the app will run with name the same model, and that the model implements the contract. Until then the preview lists the step as unmet (`host step (unmet)`, and `unmet` in `--json`) with the exact lines to add, and `--apply` and `--resume` refuse. Kit never writes `app/Models/User.php`. Re-pinned: package `workspaces` `sha256:7d57c61a…87926` → `sha256:e3b16619…3976a`.
- Adopted features register in an app set up by `kit:install`. Package providers register before the app's own, so `KitHostServiceProvider` was not yet bound when the kit read the adopted features, and nothing registered. Concierge then failed `php artisan migrate`. `FeaturesServiceProvider` now falls back to `kit-lifecycle.app_id`.
- `composer test:install` covers both: on a fresh app the step is listed and the apply refused. With the lines added, the apply completes and `php artisan migrate` succeeds on SQLite memory. The fresh-app fixture gains Laravel's users and cache migrations. The AdoptCheck host app's `User` now implements `Member`.

## v0.1.2

### Added

- `php artisan kit:install` sets up the host binding, so `kit:add` works in a fresh app (#34). It writes app-owned files from stubs in `resources/install`: `app/Kit/KitHost.php` (a `LifecycleHost` and `CatalogHost` on the package catalog), a fail-closed operation check in `app/Kit/operations.php`, `config/kit-lifecycle.php` (verification policy; app id from `app.name`), `config/kit-executor.json` from `Lifecycle::runtime()`, and `app/Providers/KitHostServiceProvider.php`. It appends the provider to `bootstrap/providers.php` only when missing, prints what it wrote and what to review, refuses to overwrite any existing file, and a second run changes nothing.
- `composer test:install` on a fresh-Laravel-shaped fixture with no host binding: `kit:install` then `kit:add workspaces` previews, applies on SQLite memory, a second install is a no-op, an existing `app/Kit/KitHost.php` is refused, and `kit:add` still refuses without it. Run in CI.
- README: a quick start at the top.

## v0.1.1

### Changed

- `kit:add` claims existing files only from an explicit, reviewed list (#26). v0.1.0 claimed every identical pre-existing file implicitly. Now the preview lists them as `claimable`, and the apply is refused until each one is listed with `--claim=<path>` (repeatable or comma-separated). Only listed paths reach Mod's `claim:`. The claim list is part of the plan identity, so a reviewed plan can't change silently. A listed file with different bytes stays a conflict, and a claim no feature being added delivers is refused.
- `kit:add --resume --intent=<id>` finishes the interrupted feature, then every remaining feature of the original request in requires order (#28). It prints which features are done and which remain, and exits nonzero unless every one completed. A resume whose intent has no pending record for the next feature is refused plainly.

### Fixed

- A `pending:<lineage>` refusal from `kit:add` or `kit:update` now prints the pending lineage, its intent and the exact `--resume --intent=<id>` command to run, or where to find the intent, and exits nonzero (#27).
- Tests cover a differing unowned pre-existing file (refused, no write), `--resume` without `--intent` (nonzero, clear refusal), explicit claims, the pending hint and multi-feature resume (#29).

## v0.1.0

### Add starter features to an existing app

The first tey/kit release. `composer require tey/kit`, then `php artisan kit:add <features>` previews and applies the starter's features (core, workspaces, access, settings, session-ui, mcp) one Mod 0.9 lineage per feature, claiming every identical existing file without asking (explicit from v0.1.1) and refusing to overwrite edits. `kit:update` upgrades each feature after `composer update tey/kit`; `kit:status` and `kit:list` report.

### Changed

- Requires released `tey/mod` ^0.9 and `tey/concierge` ^0.7.3. Release fixtures A–D and the v0.1 feature fixture keep the single-lineage `Lifecycle` path, which now reads Mod 0.9's per-lineage installed records.
- Re-pinned for Mod 0.9: the A–D manifests and the v0.1 `foundation` feature now require `tey/mod` ^0.9 instead of ^0.8. The package `workspaces` feature requires `tey/concierge` ^0.7.3 instead of exactly 0.7.2. Old to new: A `sha256:686b417b…5c74b` → `sha256:a59a38d4…c8260`, B `sha256:857fc187…2b3b` → `sha256:53e31b60…74d59`, C `sha256:18e53f09…7bdb` → `sha256:8699fd47…caf73`, D `sha256:777333b1…dd0f` → `sha256:0bab54b8…83672`; v0.1 `foundation` `sha256:40bbc9be…3541` → `sha256:a8f47c15…21312`, v0.1 all features `sha256:db4358fe…956675` → `sha256:40df4082…c9b98`; package `workspaces` `sha256:068735ea…45d5ba` → `sha256:7d57c61a…87926`.
- Mod 0.9 rejects dot-leading `hostOwned` prefixes (`.env`, `.env.example`). Until Mod 0.9.1, those are left out of the Mod artifact and enforced kit-side only.
- Renamed from `tey/starter-blueprint` to `tey/kit`. This package has never been published and nothing depends on it, so there are no aliases. Namespace `Tey\StarterBlueprint` is now `Tey\Kit`, `BlueprintServiceProvider` is `KitServiceProvider`, the config key `starter-blueprint` is `kit`, `starter:maintain` is `kit:update` (same arguments and options), and the scaffold `tey/starter-foundation` (command `starter:foundation`) is `tey/kit` (command `kit:foundation`). Other class names are unchanged.
- Pinned identities changed with the scaffold id: A `sha256:21bd1704…45e2` → `sha256:686b417b…5c74b`, B `sha256:70af6427…726c` → `sha256:857fc187…2b3b`, C `sha256:85405850…a3ff` → `sha256:18e53f09…7bdb`, D `sha256:a85ea6fe…7aaf` → `sha256:777333b1…dd0f`; feature `foundation` `sha256:8e2e55ee…4054` → `sha256:40bbc9be…3541`, `defaults` `sha256:fb252b8b…2e43` → `sha256:71662b1b…ec7f6`; all features `sha256:a7324f25…475b9e` → `sha256:db4358fe…956675`.
- CI runs every `composer test:*` script on PHP 8.4 and 8.5, and a Privacy job.

### Added

- Per-feature lineages on Mod 0.9 (`Lineages`): each feature installs as its own lineage, `tey/kit:<feature>`. Feature requires are checked kit-side against the installed versions and passed to Mod as `requires:<id>` outcomes; `host_owned` becomes Mod's `hostOwned`; `conflicts` stay kit-side.
- `kit:add <features...>`: previews by default (plan identity, per-file create/claim/conflict, missing packages, host steps) and writes nothing. `--apply --expected-plan=<id> [--intent=<id>]` adds the features in requires order, one lineage each, and stops at the first failure. `--resume --intent=<id>` finishes it. Every identical pre-existing file is claimed implicitly, without a write; v0.1.1 claims only listed files. Re-adopting is a no-op, and nothing is ever removed.
- `kit:update --lineage=<feature>`: plan, apply or resume one adopted feature's lineage. Refused while any lineage has an unfinished update. The original is rebuilt from the installed record, so an app upgrades to a new catalog version after `composer update tey/kit` without the old catalog.
- `FeaturesServiceProvider` adopts the installed lineages (read for `kit.app_id`, else the bound host's app id); `kit.features` stays an explicit override.
- `composer test:adopt` on a minimal non-starter host app fixture, run in CI.
- `kit:status` reports every installed lineage (`lineages`: name, version, identity, features, file states) instead of one `artifact`; `blueprint_features` is the union.
- `kit:list`: prints the catalog's features with what each requires and owns. Read-only.
- `Blueprint::entries()`: the same listing as data.
- `bin/privacy-check.sh`, `AGENTS.md` and `CLAUDE.md`.
- `Catalog`: a feature catalog outside the package, with its own pinned identities. `Catalog::at($root, $pins, $basePin)` builds one, `Catalog::package()` is this package's own. `Blueprint::features()`, `Blueprint::all()` and `Blueprint::featureIds()` take an optional catalog and default to the package catalog. An external catalog's identities are checked as strictly as the package's.
- `files_from` in a feature manifest: a tree of stubs expands into generated files, sorted, with symlinks, escapes and duplicate targets refused. The feature identity covers every expanded stub.
- `CatalogHost`: a `LifecycleHost` that also implements it supplies the catalog `kit:update` builds its blueprints from.
- A seven-feature fixture catalog and `tests/CatalogCheck.php`, run in CI.

## starter-blueprint v0.1.0 (predecessor package)

### Added

- Per-feature manifests, `foundation` and `defaults`, each declaring its owned files, requirements, conflicts and host-owned paths. Every feature is content-addressed into the blueprint's source reference, and the whole app is all features.
- First adoption with no existing baseline, and add-only upgrades as the feature selection grows. Removing a feature is refused with a clear message.
- `starter:maintain {plan|apply|resume}` with `--original`, `--target`, `--features`, `--original-features`, `--intent`, `--expected-plan` and `--exclusive-checkout`. Every refusal or incomplete run exits nonzero.
- The `tey/starter-foundation` scaffold for previewing and generating the files through Mod's public scaffolds.
- The `LifecycleHost` contract an app implements for its app ID, bindings, input files and operation check, plus executor provenance and input fingerprints bound into each run.
- An upgrade's original must equal the installed artifact exactly, metadata included, or it is refused before Mod runs.
- Pinned release fixtures A–D: A `sha256:21bd1704…45e2`, B `sha256:70af6427…726c`, C `sha256:85405850…a3ff`, D `sha256:a85ea6fe…7aaf`; feature `foundation` `sha256:8e2e55ee…4054`, `defaults` `sha256:fb252b8b…2e43`.
- Requires `tey/mod` ^0.8 and `tey/concierge` ^0.7.2. CI proves the lifecycle on PHP 8.4 and 8.5 against the released packages: first adoption, plan and apply, additive upgrade, refused removal, an unowned path, resume and a cold retry. Adopting into an app that already has files the blueprint would own is refused until Mod can claim existing files.
