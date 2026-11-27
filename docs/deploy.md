# Deploy

After pulling a release that adds or changes features:

```sh
php artisan migrate --force
php artisan kit:permissions:sync
```

`kit:permissions:sync` creates the permissions the adopted features and the app declare and grants each its default Concierge roles. It is safe to run on every deploy: it changes nothing when the database already matches, never revokes a grant, and lists custom grants and undeclared permissions it left alone. It exits nonzero when a default role does not exist; run `php artisan concierge:sync-roles` first on a new app.

## A missing build file

A page loaded before a deploy can ask for a file the deploy removed, such as a lazy chunk under `public/build/`. The web server should answer that itself: serve the build directory as static files and don't fall back to `index.php` under it.

Where the request reaches Laravel anyway, the kit answers it early. A request under the Vite build directory that names no file in `public/` gets a plain 404 (`text/plain`, `Not Found`) from global middleware (`Tey\Kit\Http\MissingBuildAsset`, prepended by the kit's service provider), before routing and the `web` group. So no session starts, no XSRF cookie is set and no HTML error page renders. The directory is `build` by default, or whatever the app set with `Vite::useBuildDirectory()`, read on each request. A file that does exist, and any path outside the directory (`/buildings`, `/build` itself), goes through to the app as before. A route the app registers under the build directory is never reached for a path with no file behind it.

The browser side, a reload or a "new version" notice, is in [toasts.md](toasts.md#after-a-deploy-a-new-version). `composer test:build-miss` covers the 404, a normal route's cookies, an existing file, a path outside the directory and a custom build directory, on a synthetic host through the real HTTP kernel.

## Queues

Settings → Setup reads the queue's configuration and shows one row for each rule below. Each failing row links here.

- **Connection.** `sync`, `database` or `cloud`, with the profile (`APP_PROFILE`: `iterating` or `production`). `sync` in production is fine for a new app: jobs run inline, in the request that dispatches them. Move to `database` or `cloud` once a job is slow.
- **Timeout.** The worker's `--timeout` (`kit.setup.queue.worker_timeout`, env `KIT_QUEUE_WORKER_TIMEOUT`) must stay below the connection's `retry_after` (or SQS's `visibility_timeout`). At or above it, a job still running is handed to a second worker. On a Laravel Cloud Flex queue (`KIT_QUEUE_FLEX=true`) it must also be 85 seconds or less. Unset, the Setup page assumes the documented worker, `--timeout=85` on a Flex queue (`KIT_QUEUE_WORKER_TIMEOUT=85`, `KIT_QUEUE_FLEX=true`); set both to match yours. The Last job row is read from a cache mark each finished job leaves, which is not yet confirmed on Cloud's managed queue.
- **Failed jobs.** Failures in the last 24 hours, from `failed_jobs`, plus Cloud's store when `kit.setup.queue.failed_count` is a callable that counts them. Above 0 warns, above 25 fails. `kit.setup.queue.failed_url` is the list the row links to. Read each failure, fix the cause, then `php artisan queue:retry` or `queue:forget`.
- **Last job.** When the newest job finished (recorded on `JobProcessed`). With jobs waiting and none finished in 15 minutes, it warns: check a worker is running.
- **Pruning.** `queue:prune-failed` must be scheduled, or it fails: `Schedule::command('queue:prune-failed --hours=168')->daily();` in `routes/console.php`.

On a local or iterating app (`APP_ENV=local`, or `APP_PROFILE=iterating`) the production-only rules (the Flex limit, Cloud's store and pruning) show as not applicable.
