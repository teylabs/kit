# touched_at

`touched_at` answers "when did anything under this record last change?", for a list row such as **Touched 3 days ago** or a timeline ordered by recent activity. A child (a photo, a note, a line item) bumps its parents' `touched_at`; the parents' `updated_at` never moves.

## Why not `updated_at`

`updated_at` stays the record's own edit and version key. Forms and stale-write guards compare it (or `lock_version`) to detect a conflicting save. If a child edit bumped the parent's `updated_at` (Eloquent's `$touches`), someone editing the parent while a colleague added a photo would get a false save conflict, and "edited" would mean "something nearby changed". So the two stay apart:

| Column | Moves when | Use it for |
| --- | --- | --- |
| `updated_at` | this record is saved with changes | edit time, version and conflict checks |
| `touched_at` | this record or anything under it changes | "Touched 3 days ago", activity ordering |

## UI convention

Show it as **Touched {relative}**, e.g. "Touched 3 days ago", with the full date in the tooltip. Keep the label and the column the same word, so the meaning stays honest: don't label `touched_at` "Updated" or "Last worked".

## Setup

1. Add the column (opt-in by schema: only a table with a `touched_at` column is ever written):

   ```php
   Schema::table('cases', function (Blueprint $table) {
       $table->touchedAt(); // nullable timestamp, indexed
   });
   ```

2. Use the concern on the parent and on each child, and name the relations a child touches. This is separate from Eloquent's `$touches`:

   ```php
   use Tey\Kit\Features\Core\Concerns\Touches;

   class Photo extends Model
   {
       use Touches;

       protected array $touchesParents = ['assignment'];

       public function assignment(): BelongsTo { /* … */ }
   }
   ```

`BelongsTo`, `MorphTo` and other relations work; a relation that returns several records touches each.

## Behaviour

- A model with a `touched_at` column stamps its own `touched_at` when it is created, and when it is saved with changes, as part of that save.
- Its create, update or delete touches each parent in `$touchesParents`, and each parent touches its own parents (Task → Project → Client).
- A parent is touched by a quiet update of `touched_at` alone, a base query on its key: no model events, no `updated_at`, no `lock_version` bump. A parent whose table has no `touched_at` column is skipped, and the cascade carries on above it.
- Each record is touched once per request or queued job (keyed by class and key), so ten photo uploads make one update per ancestor. A cycle stops at the first record seen twice.
- A save with no changes touches nothing.

## Turning it off

```php
use Tey\Kit\Features\Core\Touch;

Touch::without(fn () => $import->run()); // imports, backfills, migrations: nothing is stamped or cascaded
```

`config('kit.touch.console')` (default `true`) turns touching off in artisan commands and queue workers when set to `false`.

## Actions that save nothing

A reorder or another action that writes no column on the record still counts as activity:

```php
$photo->touchTree(); // touches the photo (quietly) and its parents
```

## Denormalised copies

Override `alsoTouch()` to keep a copy in step, e.g. a user's pinned rows carrying the record's `touched_at`:

```php
public function alsoTouch(DateTimeInterface $at): void
{
    Pin::query()->where('case_id', $this->id)->toBase()->update(['touched_at' => $at]);
}
```

It runs whenever the record is stamped or touched.

## Tests

```php
use Tey\Kit\Features\Core\Testing\TouchesAssertions;

TouchesAssertions::assertTouchesParent(Photo::class, 'assignment');

// Every BelongsTo/MorphTo (declared with a return type) that leads to a touched parent but is not in $touchesParents.
TouchesAssertions::assertCovered([Photo::class, Note::class, Part::class], ignore: ['App\Models\Note::author']);
```

`uncovered()` returns the same list as `Class::relation` strings. A `MorphTo`'s parent type is unknown, so every undeclared one is listed: name the ones that should not touch in `ignore`. The helpers use PHPUnit's assertions when PHPUnit is loaded, and throw otherwise.

Coverage: `composer test:touched-at` (tests/TouchedAtCheck.php) runs synthetic models on SQLite memory: create, update and delete touching parents and a grandparent, `updated_at` unchanged and no events on the parents, a MorphTo parent, dedupe, a cycle, `without()`, the console switch, `touchTree()`, `alsoTouch()`, a parent without the column, the job flush, the macro and the helpers. Not covered: PostgreSQL, a real app's models.
