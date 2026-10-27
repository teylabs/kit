# Global search

Global search (session-ui, with the header box in shell) finds things across a workspace from one box. The server asks each registered **provider** for the viewer's query and shows their results in groups. The search page, the header palette and the MCP `workspace_search` tool all read the same providers, with the same gating and the same limit.

## Turning it on

Search is opt-in. It sits behind the `workspace.search` capability, which is off until you switch it on:

```php
// config/kit.php
'capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true, 'workspace.search' => true],
```

An operator can also switch it on or off for one workspace, or for one person in a workspace, as with any capability. While it is off for a viewer:

- `/@{workspace}/search` is a 404;
- the header shows no search box;
- the MCP tool refuses.

Anyone who may read the dashboard (`dashboard.read`) may then search (`search.read`). Connections ask for the `search.read` token scope.

## Registering a provider

A provider implements `SearchProvider` and returns up to `$query->limit` `SearchResult`s, best first:

```php
use Tey\Kit\Features\SessionUi\Search\SearchProvider;
use Tey\Kit\Features\SessionUi\Search\SearchQuery;
use Tey\Kit\Features\SessionUi\Search\SearchResult;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

final class OrderSearch implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        return $query->matches(Order::query()->where('workspace_id', $query->workspace), ['reference', 'customer_name'])
            ->orderBy('reference')
            ->limit($query->limit)
            ->get()
            ->map(fn (Order $order) => new SearchResult(
                id: (string) $order->id,
                label: $order->reference,
                url: WorkspaceUrl::to($query->workspace, 'orders/'.$order->id, null, $query->request),
                description: $order->customer_name,
                meta: $order->status->label(),
            ))
            ->all();
    }
}
```

Register it in a service provider's `boot()`:

```php
app(Registry::class)->searchProvider('app.orders', OrderSearch::class, 'orders.read', 'Orders', order: 10);
```

- **`key`**: unique, dotted lower case. It is how a failure is reported.
- **`ability`**: the provider is asked only for a viewer who holds it, checked by `Access`, so its capability, its entitlement and the viewer's permission all apply. A viewer without it never sees the group, and nothing reveals that the group exists.
- **`label`**: heads the group of results.
- **`order`**: lower comes first; ties keep registration order. The kit's people provider is 100.

### What a provider must do

- **Scope to the workspace.** It runs inside the workspace's tenant context, but it builds its own query: filter on `$query->workspace` and never read another workspace. The kit's check puts a matching row in a second workspace and asserts that it never appears.
- **Scope to the viewer.** Return only rows the viewer may open. The ability gates the whole provider, not each row. `$query->principal` is the viewer.
- **Match with `$query->matches($builder, $columns)`.** It escapes `%` and `_`, ORs the columns, and ignores case on every database. A plain `like` is case-sensitive on PostgreSQL, where it would miss "Viewer" for "view". `$query->like()` gives the escaped pattern when you need it raw. The term is trimmed and at most 100 characters.
- **Keep it fast.** Search runs every provider on each palette request. Use an index, and never load more than `limit` rows.
- **Write plain text.** `label`, `description` and `meta` are shown as text, never as HTML. Set `url` to null for a result with no page of its own.

### When a provider fails

A provider that throws, or that returns anything but a list of `SearchResult`, is skipped. Search still answers with the other groups, marked `partial`; the page and the palette then say that some sources could not be searched. The failure is logged as `Search provider failed`, with the provider key, the exception class and its trace frames. The log never includes the message, the term or any arguments.

## The kit's provider

`kit.people` finds the workspace's active members by name or email, behind `dashboard.read`, the same people the People widget lists. Only someone who manages people (`members.manage`) gets a link: to People, filtered to that person. Set `kit.search.people` to `false` to leave it out.

## Rate limit

Each person gets `kit.search.per_minute` searches a minute (30 by default). The page, the palette and the MCP tool count together. Past the limit, the page and the palette answer 429, and the MCP tool refuses with `rate_limited`.

## The header and the page

- **Header (shell).** When the server sends `shell.search`, the header shows `GlobalSearch`: a search field from `lg` up, and a button that opens it in a sheet below `lg`. ⌘K or Ctrl+K focuses it from anywhere; `/` does too, when the person is not typing. Neither works while a dialog or menu is open.
- **Field (`SearchBox`, `@kit/ui`).** Results load 250ms after the person stops typing. Arrow keys move through every result. Enter opens the active result, or the full results page when none is active.
- **Results page (`Workspace/Search/Page`).** Each group with up to 25 results. The palette shows 5 per group.

Responses are `Cache-Control: private, no-store`.

## MCP

`workspace_search` (`q`, 1 to 100 characters) is registered when both mcp and session-ui are adopted. It returns `{ query, groups, partial }`, up to 25 results per group: the same groups and results the page shows the same person. It is read only.

## Coverage

`composer test:search` checks:

- registration;
- order;
- per-provider gating by ability and by capability;
- isolation between workspaces;
- a failing provider;
- that a failure's message never reaches the page or the log;
- the opt-in 404;
- the shell descriptor;
- the shared rate limit;
- that the MCP tool matches the page.

It runs on SQLite memory with real Concierge and Pennant. `composer test:search-js` checks the client rules: grouping, arrow keys and the ⌘K rule.

Not covered: the palette and the sheet are never rendered in a browser, and the MCP tool is called directly rather than over the MCP transport.
