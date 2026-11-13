# Domain tables and models

Since 0.19 the kit's domain data is the app's. When you inspect the database, the schema reads like the app: `deliveries`, `notes`, `security_events`. The kit ships the behaviour of each model (a trait and a contract), and the app owns the table and the model once it adopts the feature.

## Who owns what

| Tier | What | Who owns it | Examples |
| --- | --- | --- | --- |
| Infrastructure | Tables the kit reads and writes for itself. They load from the package (0.18). | The kit | `user_state`, `account_deletions`, `plan_entitlements`, `permission_defaults`, `capability_overrides`, `mcp_*`, `kit_meta`, `kit_adoptions`, the operators table |
| Domain | The app's own records, which the kit's features read and write | The app, once it adopts the feature | the tables below |
| Pages | The feature's pages and partials, written once by `kit:scaffold <feature>` (0.18) | The app | `resources/js/pages/...` |

The domain tables:

| Feature | Kit's table | App's table | Key (`Kit::model()`, `Kit::table()`) | Trait and contract |
| --- | --- | --- | --- | --- |
| deliver | `kit_deliveries` | `deliveries` | `delivery` | `Deliver\Concerns\IsDelivery`, `Deliver\Contracts\Delivery` |
| deliver | `kit_delivery_recipients` | `delivery_recipients` | `delivery_recipient` | `IsDeliveryRecipient`, `Contracts\DeliveryRecipient` |
| deliver | `kit_delivery_events` | `delivery_events` | `delivery_event` | `IsDeliveryEvent`, `Contracts\DeliveryEvent` |
| finalize | `kit_finalizations` | `finalizations` | `finalization` | `Finalize\Concerns\IsFinalization`, `Finalize\Contracts\Finalization` |
| finalize | `kit_finalize_confirmations` | `finalization_confirmations` | `finalization_confirmation` | `IsConfirmation`, `Contracts\Confirmation` |
| notes | `kit_notes` | `notes` | `note` | `Notes\Concerns\IsNote`, `Notes\Contracts\Note` |
| notes | `kit_note_intents` | `note_intents` | `note_intent` (a table, `kit.tables`) | none: read without a model |
| access | `entitlements` | `entitlements` (unchanged) | `entitlement` (a table) | none |
| access | `complimentary_grants` | `complimentary_grants` (unchanged) | `complimentary_grant` (a table) | none |
| session-ui | `kit_security_events` | `security_events` | `security_event` | `SessionUi\Concerns\IsSecurityEvent`, `SessionUi\Contracts\SecurityEvent` |
| workspaces | `workspaces` | unchanged | `workspace` | `IsKitWorkspace`, Concierge's `Workspace` (bound through `concierge.models.workspace`, as before) |
| media | `kit.media.uploads_table` | unchanged | `media_upload` | `Media\Concerns\IsMediaUpload`, `Media\Contracts\MediaUpload` |

The namespaces are under `Tey\Kit\Features\` (media under `Tey\Kit\`).

## The registry

`config/kit.php` names the models in `kit.models` and the tables the kit reads without a model in `kit.tables`. A key left unset or null keeps the kit's own class or table, so an app that does nothing keeps working on the old tables. Any other value that isn't a non-empty string, such as `false` or `''`, refuses where the kit reads it: it never falls back to the kit's table. Kit code reaches every one of them through `Tey\Kit\Features\Core\Kit`:

```php
use Tey\Kit\Features\Core\Kit;

Kit::model('delivery')::query()->where('state', 'scheduled');   // App\Models\Delivery once adopted, else the kit's DeliveryRecord
DB::table(Kit::table('note_intent'));                           // note_intents once adopted, else kit_note_intents
```

The kit's own classes (`DeliveryRecord`, `Finalization`, `Note`, and so on) stay, as the default for an app that has not adopted. They are deprecated for new code: reach the model through `Kit::model()`.

**The boot check.** In `local` and `testing`, the app refuses to boot when a class in `kit.models` doesn't exist, isn't an Eloquent model or doesn't implement its key's contract, or when a feature is adopted in part (its delivery, say, without its recipients). Elsewhere the problem is logged.

**Morph aliases.** Each model has a stable alias, `kit-` and its key (`kit-note`, `kit-delivery`, `kit-delivery-recipient`, …), registered against whichever class config names. Stored rows that name a model by alias (media attachments, an app's own morphs) follow it to the app's model. Once adopted, a row stored under the kit's class name, from before the alias existed, resolves to the app's model too.

## Adopting a feature

```sh
php artisan kit:scaffold deliver --adopt
```

It writes:

- the app's models, one per domain model, in `app/Models` (`Delivery`, `DeliveryRecipient`, `DeliveryEvent`). Each extends `Model`, uses the kit's trait, implements its contract and sets `$table` to the app's name;
- one migration, `database/migrations/<date>_adopt_deliver_tables.php`;
- the `kit.models` (and `kit.tables`) entries in `config/kit.php`. When the app has no `config/kit.php`, the kit's defaults are published first. Every other line stays as it was.

Review the migration, then run `php artisan migrate`. On a database where the kit created the tables, the migration renames each one, with its rows. It also renames the indexes named after it, and on PostgreSQL its foreign keys and id sequence. On a fresh database it creates the tables under the app's names, and the kit's own migrations for them don't run. It runs on the migration's connection (`--database` applies). Before renaming anything it checks every table: when both names of any table exist, it refuses, naming both, and changes nothing, so a database without transactional DDL is never left half renamed. The same goes for every index, foreign key and sequence name it would give: one already taken stops it before any table moves. On PostgreSQL `up()` and `down()` also run in one transaction with their `kit_meta` records. `up()` records in `kit_meta`, keyed by the migration and the table (`adopt:<migration>:<table>`), what it did to each table: `renamed`, `created` or `left`. `down()` reads that record. A renamed table is renamed back, after checking every pair; a created one is dropped; a left one stays. A table with no record stays as it is too: a rollback never drops a table the migration didn't create.

`kit:scaffold <feature> --adopt` refuses, writing nothing, when:

- the feature has no domain tables (workspaces: bind your own workspace model as before);
- the feature is already adopted, or its adopt migration is there;
- a model file it would write exists (`--force=app/Models/Note.php` overwrites it). An app that already has a `Note` of its own writes the model by hand instead, with the kit's trait and contract;
- the database has both names of a table (a legacy `notes` table beside `kit_notes`, say). Keep the one that holds the data first: `kit:notes:import` copies a legacy notes table's rows in;
- it cannot set the entries in `config/kit.php` exactly (a computed `models` entry, say). It prints them for you to set.

The features: `deliver`, `finalize`, `notes`, `access` and `session-ui`. Adopt each whole: the boot check refuses a part.

### Code of your own

- **Queries on the old names.** Replace `kit_deliveries` and the rest with `Kit::table('delivery')` (or the app's name).
- **Finalize hooks.** The gate passes the model `kit.models.finalization` names. A subject implementing `FinalizesWithinTransaction`, or a flow's `extras(apply: ...)`, typed against the kit's `Models\Finalization`, takes `Contracts\Finalization` once finalize is adopted: PHP allows the wider parameter type in your implementation.
- **Deliver closures and listeners.** `customerPage`, `expiredPage` and the `Deliver\Events` pass the model `kit.models.delivery` names. Type them as `Contracts\Delivery` (or your own model).
- **A subclass of `WorkspaceNotes`** that overrides `canChange()` takes `Model&Contracts\Note`.

## Checking a table

```sh
php artisan kit:scaffold deliver --check
```

Read-only. For each table the kit reads for the feature (the app's once adopted, the kit's before), it lists any column the kit relies on that the table lacks, and exits 1 when one is missing. Extra columns of your own are fine.

## Coverage

`composer test:domain-adopt` runs on SQLite files and, in CI, on PostgreSQL 17:

- an app that does not adopt keeps the kit's tables and classes;
- adopting every feature on a database with rows keeps every row, column for column, and keeps the foreign keys and morph rows resolving;
- rollback restores the names, and migrating again renames them again;
- rollback follows each table's record: it keeps a table the app already had (`left`) and one with no record, and refuses before renaming back when a pair is in the way;
- a fresh database gets the tables under the app's names, with the kit's columns exactly;
- both names present refuses, in the command and in the migration, and so does an index name the rename needs that another table already has;
- `--adopt` writes nothing through a linked file or folder, even with `--force`;
- `--adopt` writes all of its files and `config/kit.php` or none: a write that fails puts back the ones before it;
- a malformed `kit.models` or `kit.tables` entry refuses, and a feature adopted in part still runs the kit's migrations;
- an uneditable config refuses;
- a class that doesn't implement its contract, and a part-adopted feature, stop the boot.

`composer test:lifecycle-pages` checks that account and workspace deletion fail closed on a missing table of a feature that is on, and workspace deletion also on a feature's table that lacks `workspace_id` (#967).

**Not covered:** MySQL and SQL Server (the migration renames indexes there, but keeps foreign key names), a consumer app, and the kit's pages running on adopted tables.
