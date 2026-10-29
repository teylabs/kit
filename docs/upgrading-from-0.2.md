# Upgrading from kit 0.2.x

kit 0.2.x requires tey/mod `^0.9`. kit 0.3 and later require tey/mod `^0.11`. So you can't upgrade tey/mod on its own: kit and mod move together.

kit 0.2.x has no `kit:adopt`. To bring files that an older install copied in under a lineage, use `kit:add --claim` at the version you have.

## The order

1. **At your installed 0.2.x, claim core.** Preview, then apply with the claims the preview lists:

   ```sh
   php artisan kit:add core
   php artisan kit:add core --apply --claim=<paths the preview lists> --expected-plan=<the plan the preview printed>
   ```

   `--claim` takes existing files that are identical to the kit's, after you review them. Repeat it or separate the paths with commas.

2. **Upgrade kit and mod together:**

   ```sh
   composer require tey/kit:^0.5 tey/mod:^0.11 -W
   ```

3. **Record the new runtime:**

   ```sh
   php artisan kit:update refresh-executor
   ```

   Until you do, every plan stops with `executor_stale`.

4. **Update core.** Plan, review, then run the apply command the plan prints:

   ```sh
   php artisan kit:update plan --lineage=core
   php artisan kit:update apply --lineage=core --expected-plan=<plan> --exclusive-checkout --intent=<intent>
   ```

   `apply` and `resume` refuse without `--exclusive-checkout` and an `--intent`. The printed command has both.

Then update each other adopted feature the same way (`--lineage=<feature>`), and read the CHANGELOG's Upgrading notes for each release you skipped.

## Checking status

tey/mod has no `mod:status`, in 0.9 or 0.11. Use `php artisan kit:status` (`--json` for a script). It lists the adopted features, each lineage's version and identity, local edits to owned files, and any unmet host step.

## Commit the lineage records

After the upgrade, make sure `.mod-maintenance/installed/` is committed, or a fresh clone and CI lose the adoption. See [docs/host-repository.md](host-repository.md).
