# Query report

`php artisan kit:query-report` requests each of the app's pages in-process and prints what each one costs in queries. Run it on demand, on your own machine: after building or changing a page, before and after a change meant to save queries, or when a page feels slow. Its output is information, not a gate. Nothing fails on a count, and no suite checks an app's counts.

```sh
php artisan kit:query-report
php artisan kit:query-report --user=ada@example.test --workspace=acme --repeat=5
php artisan kit:query-report --route='reports.*' --only-duplicates
php artisan kit:query-report --json > queries.json
```

## What it requests

- **Every page the app declares.** That means `WorkspaceRoutes::page`'s full form (`/@{workspace}/...`, opened in the chosen workspace) and `PersonalPages::page` (or `WorkspaceRoutes::personalPage`). The short form is the same page under the session's workspace, so it is not requested a second time.
- **Any GET route `--route` matches**, by name or URI: `--route='reports.*'`, `--route='billing/*'`. The option repeats.
- **Skipped pages.** A page with a parameter other than `{workspace}` and no default (`/@{workspace}/notes/{note}`) is listed under Skipped, with the parameter it lacks. So is a matched route that answers no GET.

It signs in as `--user` (an id or an email). Without it, it picks the first active member (the oldest membership) of `--workspace` (an id or a slug), or of any workspace. It refuses a user who is not an active member of `--workspace`.

Each page is requested once to warm up (that request is not counted), then `--repeat` times (default 1), and the report shows the median. Requests go through the HTTP kernel as Inertia visits: the page's props come back as JSON, so the root view, built assets and an SSR server are not needed. Before each request the auth guards, the session store, scoped instances and `once()` memos are forgotten, as Octane forgets them. Anything else the app keeps in memory stays warm. The person is loaded inside each request, so the `users` query that a signed-in request makes is counted, even on a route that doesn't sign anyone in.

## It writes nothing

- **Database writes roll back.** Each request runs in a transaction on each connection it writes to, and the transaction is rolled back after the request, terminating callbacks included. The transaction opens just before the page's first statement that is not a plain `select`, or before the page starts a transaction of its own, which then nests inside it. Until then the page reads outside any transaction, as it does in production. Code that behaves differently inside a transaction is therefore measured as it really runs: the kit runs each dashboard widget in a savepoint inside one, and a refused widget's rollback empties the request memo.
- **Other side effects are swapped out.** The session and every cache store are array stores while the report runs. Mail, notifications and the queue are faked, so nothing is sent or pushed and no job runs.
- **Production needs `--force`.** It refuses to run in production without it.
- **Not a sandbox.** Only the database, the session and cache stores, mail, notifications and the queue are covered. A page that writes a file, or calls an external API or SDK directly (not through a faked facade), still does so, once per request. Mind that for the routes you add with `--route`, and above all with `--force`.
- **What it can't undo:** a write made through the PDO directly, or a `select` that calls a function that writes (both run before the transaction opens); a schema change on MySQL, which commits by itself; the rate limiter's counters (the limiter keeps the cache store it started with); and log lines. If a page commits the report's own transaction, the run stops with a non-zero exit.

## Reading it

An example:

```text
As user 1 in workspace acme: each page requested once to warm up, then once, every request rolled back.

GET /@acme  workspaces.dashboard.index
  200 · 15 queries · 2 duplicates · 22.4 ms (1 ms in queries)
  2×   select * from "account_statuses" where "account_statuses"."user_id" = ? limit 1  (1 exact)

GET /@acme/notes  notes.index
  200 · 31 queries · 12 duplicates · 40.1 ms (6.3 ms in queries)
  13×  select * from "media" where "model_type" = ? and "model_id" = ?  (12 exact)

Skipped
  GET /@{workspace}/notes/{note}  notes.show: no value for {note}

14 pages (13 answered 200), 1 skipped. Over the 200s: median 15 queries, worst 31 (/@acme/notes, notes.index), duplicate floor 1 per page.
```

- **An exact duplicate** is a query run again with the same SQL and the same bindings on the same connection. Reading one row three times is two duplicates; reading three rows once each is none. Under each page, its most repeated statements are listed with how often each ran and how many of those runs were exact duplicates. A statement that repeats with different bindings is the usual sign of an N+1.
- **The time** is the request's wall time through the kernel, with the part spent in queries in brackets. It is local and noisy: use `--repeat` before you compare two times.
- **The summary** covers the pages that answered 200. It gives their median and worst query counts, the worst page, and the duplicate floor. The floor is the fewest exact duplicates any page runs: what the shell and the middleware cost every page.
- **Not counted:** the session's and the cache's own reads (both are array stores here, where a database driver would cost a query or two per request), the root view, and deferred or lazy props, which come in a request of their own.
- **A failing page is reported, not fatal.** A non-200 status shows in yellow, and the run carries on. A page whose terminating callback throws counts as a 500. The exit code is 0 whenever the report ran, and 1 for a refusal: production without `--force`, an unknown user or workspace, or a bad `--repeat`.

`--only-duplicates` lists only the pages that run an exact duplicate, and the summary still covers every page. `--json` prints the same report as JSON: `pages` (each with `route`, `path`, `status`, `queries`, `duplicates`, `time_ms`, `query_ms` and every repeated statement), `skipped` (each with its `reason`) and `summary`.
