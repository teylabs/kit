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
