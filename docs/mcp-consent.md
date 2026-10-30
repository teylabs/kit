# MCP consent and connected assistants

An AI assistant connects through Passport's authorization code flow. The mcp feature makes Passport's consent screen its own Inertia page, `Mcp/Authorize/Page` (generated at `resources/js/pages/Mcp/Authorize/Page.vue`), and binds the connection to a workspace there.

## The consent page

`McpProvider` registers `Passport::authorizationView()` with `Tey\Kit\Features\Mcp\Http\AuthorizationView`. Passport checks the client, its registered `redirect_uri` and the requested scopes before the page renders. An unknown client or a `redirect_uri` that isn't registered for it is refused with `invalid_client` and never redirected to: a browser gets the kit's 400 page, a JSON caller Passport's answer (see [the OAuth error page](mcp-registration.md#the-oauth-error-page)). How an assistant gets its client in the first place, and the discovery documents, are in [MCP client registration](mcp-registration.md). The page needs a signed-in person (`auth:web`, `concierge.active`) and a server-side session (see [Sessions](mcp-tools.md#sessions)).

The page (`AuthorizeViewModel`) shows:

- the client's name;
- what it asks for, in plain words: each scope's label from the registered token scopes, or the scope id as it was asked for when it has no label;
- the workspace the connection is bound to. The choices are the person's active workspaces where Access lets them open a connection (`kit.mcp.connection_ability`), and the default is the current workspace when it is one of them;
- Approve and Deny.

Approve and Deny are plain form posts carrying the session's CSRF token (`csrf_token`) and Passport's `auth_token`, not Inertia visits: each ends in a redirect to the assistant's own address, which an XHR visit can't follow. The host's `web` group checks the CSRF token as for any form.

- **Deny** is Passport's own deny: a redirect to the client's `redirect_uri` with `error=access_denied` and its `state`. No connection, code or token is made.
- **Approve** posts `workspace`. `BindConsentWorkspace` runs around Passport's approve and refuses with 403, before Passport issues a code, a workspace the person isn't an active member of or may not open a connection in. A request for narrow scopes must include the connection ability, and Access checks each narrow scope in that workspace. When Passport answers with a code, the middleware makes the connection in that workspace for the person and client: acts-as-me (`mcp:act`) for a plain `mcp:use` request, or the narrow scopes asked for. It has no token yet and waits ten minutes, as long as the code lives.
- **The code exchange** at `/oauth/token` fires `AccessTokenCreated`, and `RebindConnection` gives the new token the newest waiting connection for that person and client, and no other. That connection then expires with the token.

## Tokens and refreshes

`RebindConnection` binds a new access token to the one connection it belongs to:

- **A refresh** moves only the connection bound to the refreshed token. League's refresh-token grant first revokes the previous access token through Passport's repository, with the id the refresh token names (`oauth_refresh_tokens.access_token_id`). That fires `AccessTokenRevoked` for exactly that token, and the listener records it for the request. When the new token's `AccessTokenCreated` follows for the same person and client, and a refresh token names the revoked token, it is a refresh. A revoked connection is never moved.
- **A code exchange** revokes no access token. It binds only the connection its consent made (the newest one waiting for this person and client) or, when consent was skipped, the connection chosen for reuse.
- **Anything else binds nothing.** A connection whose token expired without a refresh stays expired, so the assistant authorizes again. Another workspace's connection, or another person's or client's, never moves.

## Explicit grants

Under the kit, an acts-as-me connection covers every ability the person's role allows. Some tools must not ride on that blanket: publishing, say, which an app never meant an agent to do unless the person said so. Mark the tool explicit:

```php
#[Capability('workspace.publish', scope: 'presentations:publish', explicit: true, explains: 'Publish presentations to the public feed, where anyone can read them.')]
final class PublishPresentation extends CapabilityTool { … }
```

An explicit tool is listed and callable only under a connection whose `scopes` name its scope. Acts-as-me never covers it (`WorkspaceConnection::covers()` checks the explicit set first), so the tool is absent from `tools/list` and a call anyway is refused as `scope_required` (403), not as an unknown tool; the tool stays registered and in the catalog. `scope` must be a registered token scope and `explains` is required: the consent text is a disclosure to a person, while the tool's description is written for agents. Registration refuses an explicit tool without either.

The consent page offers each explicit scope the person may hold in the chosen workspace (its ability passes Access there) as an unticked checkbox under "Also allow", labelled with the scope's description and `explains`; `AuthorizeViewModel::grants()` carries them with `checked: false`. Only a ticked box grants: the OAuth `scope` parameter never does, even when the client asks for the scope by name, and a posted grant the person may not hold, or that is not an explicit scope, refuses the whole approval (403). The connection then holds `['mcp:act', …granted]`, and the approval's audit entry records the grants. An existing connection never gains a grant: a skipped consent reuses its scopes, and a refresh moves it onto the new token unchanged. A host running its own consent (`kit.mcp.consent=host`) writes its own connections; the admission rule reads the same `scopes` column, so it includes an explicit scope only when its own consent collected it.

On the AI assistants page each grant a connection holds is shown ("Also allowed") with its own "Remove <label>" action, `DELETE …/settings/connections/{connection}/grants/{scope}`: the connection and its token stay, the scope goes, audited as `workspace.connection.grant_removed`. Your own with the connection ability, another member's only with the manage ability; a scope the connection does not hold, or one that is not an explicit scope, is not found.

`composer test:mcp-explicit-grants` covers the registry's explicit set, the three registration refusals, the cover rule, the consent page's unticked offer and stub, a plain approval leaving the tool absent and a call refused `scope_required`, a skipped consent and a refresh gaining nothing, a ticked approval listing and running the tool with its audit entry, single-grant removal (audited, idempotent, not for acts-as-me), forged and unknown grants refused, the OAuth scope parameter granting nothing, a person without the ability offered nothing and refused when posting it, and a host-written connection with and without the scope.

## When consent is skipped

Passport would skip the consent page when the client skips authorization, or the person already holds a live token for it with the scopes asked for. The kit binds Passport's `AuthorizationController` to `KitAuthorizationController`, whose `approveRequest` (the skip path) only goes ahead when the person has a live, unrevoked connection for this client **in the current workspace**: the remembered workspace in the session, or their only one. That connection is reused. The skip records it in the cache for ten minutes, and the code exchange moves it onto the new token. No new connection is made, and the old token no longer opens it.

Otherwise the consent page shows, as for a first connection, and the person picks the workspace. With `prompt=none` the answer is Passport's `consent_required` instead. A connection made before this release has no `oauth_client_id`, so it is never reused: the first re-authorization goes through the page.

The reuse is kept in the app's cache, so servers behind a load balancer need a shared cache store (database or redis).

## The server address

Assistants need one address: `kit.mcp.server_url`, by default this app's `/mcp`. `POST /mcp` (behind `auth:api`, `concierge.active` and `BindMcpConnection`) opens the connection the access token is bound to. It answers 401, so the assistant authorizes again, when the token is revoked, expired or another person's, or when no live connection (or more than one) runs on it.

A connection's own address, `/mcp/connections/{connection}`, still works. It must name the token's connection, else 403.

Passport sends the person straight back to the assistant after Approve, so there is no success screen. The address is on the AI assistants page, with a Copy address button (the `server_url` prop).

## Connected assistants and revoking

The AI assistants page (`Workspace/Settings/Connections/Page`, behind `kit.mcp.ui`) lists the workspace's live connections. A person sees their own. Someone Access grants `kit.mcp.manage_ability` sees every member's, with who connected each. That is the `connections.manage` ability by default, backed by the `workspace.connections.manage` permission, which owners and admins hold. It is its own permission, apart from `settings.write`. After an upgrade, run `php artisan kit:permissions:sync` so existing workspaces' roles get it.

Revoke (`DELETE settings/connections/{connection}`, `WorkspaceConnection::revoke()`):

- your own connection needs the connection ability;
- another member's needs `kit.mcp.manage_ability`, else `permission_denied` (403);
- a connection in another workspace is a 404.

Revoking marks the connection revoked and revokes its access token and that token's refresh token, unless another live connection still runs on the token. The connection's MCP address refuses at once (403), and so does the token.

## A host with its own consent

An app that already runs its own OAuth consent page and grants (it sets `Passport::authorizationView`, binds `AuthorizationViewResponse`, binds or extends Passport's `AuthorizationController` or `ApproveAuthorizationController`, or adds its own middleware to `passport.authorizations.approve`, anywhere in `app/`, `bootstrap/`, `routes/`, `config/` or its Composer PSR-4 roots such as `src/`) sets `kit.mcp.consent` to `host` (`KIT_MCP_CONSENT=host`). The kit then leaves Passport's authorize and approve as Passport ships them: no `BindConsentWorkspace` on approve, no `KitAuthorizationController` over Passport's `AuthorizationController`, no kit Authorize page as the authorization view, and a code exchange binds no kit connection (a refresh still moves a connection already bound to the refreshed token). The default, `kit`, is everything above. Any other value is refused when the mcp feature boots. `kit:adopt mcp plan` and `kit:add` show a `kit.mcp.consent` step when they find such a host file. `composer test:mcp-host-consent` covers host mode through Passport's real routes.

Kit plans point this out: `kit:add`, `kit:adopt` and `kit:update plan --lineage=mcp` all list a non-blocking `kit.mcp.consent` step recommending `host` when they find any of those signals.

**The Authorize page stays generated in host mode, and is inert.** The mcp lineage always writes `resources/js/pages/Mcp/Authorize/Page.vue`. With `consent=host` the kit never sets Passport's authorization view, so nothing renders it. It isn't generated conditionally, because a lineage's files are pinned: making one depend on a config value would change the plan's identity whenever that value changes. Leave it in place, since `kit:update` keeps it in step. Your own consent page is the one users see.

`composer test:mcp-authorize` covers this through Passport's real authorize, approve, deny and token routes, on SQLite memory. The consent page is checked as its Inertia JSON. There is no browser render and no consumer app.
