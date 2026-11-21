# Flags and tags

A record's **flags** are facts the system raises on it ("Possible duplicate", "Unpaid", "Stalled"). Its **tags** are labels people give it ("VIP", "Rush"). Both are spatie/laravel-tags tags, on the kit's own tables, so a new flag is one call: no column, scope or migration. Flags are tags of type `flag`; people's tags have no type.

## Raising and removing flags

Add `HasFlags` to the model (`Tey\Kit\Features\Workspaces\Concerns\HasFlags`, with workspaces on). Raise a flag where the state it describes changes, in the same action:

```php
// At intake, when a match is stored
$case->flag('possible-duplicate', reason: 'Looks like A-1042', link: WorkspaceUrl::to($workspace, 'assignments/1042'));

// Where payment changes: true raises it, false removes it
$case->flag('unpaid', when: $case->paid_at === null);

// Where a run is retried
$case->unflag('stalled');
```

- Raising a flag again updates its reason and link in place.
- `$case->flagged('unpaid')` says whether it is on. `$case->flags()` lists them with their `pivot->meta` (`reason`, `link`).
- Query with `Assignment::flagged('unpaid', 'stalled')` (any of them) and `Assignment::notFlagged('possible-duplicate')`.
- A flag carries its record's `workspace_id` (`taggables.workspace_id`), so it goes when the workspace is deleted and RLS can cover it.

## Showing flags

`Flags::define()` (once, in a service provider) sets how a flag looks. A flag nobody defined still works: it shows neutral, labelled from its key (`ad-hoc-thing` → "Ad Hoc Thing").

```php
Flags::define('possible-duplicate', 'Possible duplicate', tone: 'warning', dismissible: true);
Flags::define('unpaid', 'Unpaid', tone: 'info');
Flags::define('stalled', 'Stalled', tone: 'danger');
```

Pass the page's tags with `Tags::for($records)`: record key => `list<TagItemData>` (`key`, `label`, `kind` flag or tag, `tone`, `tooltip`, `href`). It loads every record's tags in one query. Flags come first, the most severe tone first and then in the order they were defined, followed by people's tags.

```vue
<TagList :tags="row.tags" :label="`${row.ref} tags`" />
```

`TagList` (`@kit/ui`) wraps its tags freely. Only past `max` (8 by default; `:max="null"` never folds) do the rest fold into "+N more", which opens them in a popover. Flags never fold before tags. A flag with a link is a link. Its reason is its hover text and is read out after its label. It is a real list named by `label`, and the words carry the meaning, never the tone alone. Use it for a record's flags and tags everywhere: table rows, page headers, cards and menus.

## Dismissing a flag

A flag defined `dismissible: true` can be dismissed ("Not a duplicate"): `$case->dismissFlag('possible-duplicate', by: $user->id)` removes it and records who and when (`flag_dismissals`). A dismissed flag is not raised again by `flag()`. Pass `force: true` when there is a new and different reason, such as another match. That raises it and clears the dismissal. A flag that is not dismissible throws `flag_not_dismissible`.

## Filtering and actions

- `Flags::filter($table)` adds a "Flags" filter to a `TableQuery` (`filter[flags]=unpaid,stalled`), with every defined flag as an option (or the keys you pass). FilterPopover shows it as checkboxes.
- Gate a row action on a flag where you build the row: `$case->flagged('possible-duplicate') ? new Action('Merge…', …) : null`.

## People's tags

Every spatie `HasTags` method works for people's tags (`attachTag('VIP')`, `syncTags([...])`, `withAnyTags()`). A person's tag belongs to the workspace it was made in: each workspace has and sees only its own, while flags are one vocabulary shared by all. Don't write `flag`-typed tags with spatie's methods. Use `flag()` and `unflag()`, which respect dismissals and keep the reason and link. tey/standards' `flags.via-api` warns on direct writes.

## Testing

```php
expect($case->fresh()->flagged('possible-duplicate'))->toBeTrue();
expect(Assignment::query()->flagged('unpaid')->pluck('id')->all())->toBe([$case->id]);
```

## Tables

The kit's workspaces migration creates `tags` (a flag has no `workspace_id`; a person's tag has its workspace's), `taggables` (with `workspace_id` and `meta`) and `flag_dismissals`. With RLS on, protect `taggables`, `flag_dismissals` and `tags` (`Rls::protect`), or list them in `kit.tenancy.rls.except`. `HasFlags` always uses these tables and the kit's models, whatever an app's `config/tags.php` says.

**Not covered:** translated tag names (spatie's translations are stored, but TagList shows the current locale's), per-workspace flag definitions, and RLS policies on the tag tables (the app's to add).
