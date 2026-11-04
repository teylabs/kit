# Route invariants

Guard the route table with rules read from the routes themselves, not a stored copy of the table. A new route that follows the rules needs no test edit; one that breaks them fails with its own name.

```php
use Tey\Kit\Testing\RouteInvariants;

test('every route keeps the route rules', function () {
    expect(RouteInvariants::all(['App\\']))->toBe([]);
});
```

`all()` returns each broken rule's offending routes ("METHODS uri", with a reason), and an empty array when all is well. Each rule is also a method of its own:

| Rule | What it checks |
| --- | --- |
| `shortTwins()` | Every workspace route is reachable both as `@{workspace}/x` and as the short `x`, with the same methods and middleware. The workspace root `@{workspace}` alone has no short form. |
| `workspaceStack()` | A workspace route signs in (`auth:web`), refuses an inactive account (`concierge.active`) and resolves the workspace, in that order. Any `workspace.ability:*` comes after the workspace is resolved. |
| `guardedWrites($namespaces)` | A workspace write whose controller is yours carries `workspace.ability:*`, or its controller declares `#[AuthorizedBy(Operation::class)]` naming an operation class that exists. |
| `declaredPublic($namespaces)` | A route with no auth middleware whose controller is yours declares `#[PublicRoute('why')]` and has a `signed` or `throttle` middleware, or declares itself an open read (`open: true`, GET and HEAD only). |

The last two take the namespaces (or exact controller classes) you own: a package's controllers are its own to check. The kit's own routes keep all four rules, with every feature on.

## Declarations

Both attributes live in `Tey\Kit\Features\Core\Routing` and go on the controller class:

```php
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Routing\PublicRoute;

#[AuthorizedBy(ArchiveNote::class)]   // the operation authorises the request itself, the same one an MCP tool calls
final class NoteArchiveController {}

#[PublicRoute('A share link: the token in the link is the credential')]
final class SharedReportController {}

#[PublicRoute('The status page', open: true)]
final class StatusController {}
```

`#[AuthorizedBy]` is a declaration that the operation authorises, not an authorisation in itself: keep the operation's own refusal tests. tey/standards' optional `guards.declared` rule flags a test that still compares the route table with a stored copy.
