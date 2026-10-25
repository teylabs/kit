# Deploy

After pulling a release that adds or changes features:

```sh
php artisan migrate --force
php artisan kit:permissions:sync
```

`kit:permissions:sync` creates the permissions the adopted features and the app declare and grants each its default Concierge roles. It is safe to run on every deploy: it changes nothing when the database already matches, never revokes a grant, and lists custom grants and undeclared permissions it left alone. It exits nonzero when a default role does not exist; run `php artisan concierge:sync-roles` first on a new app.
