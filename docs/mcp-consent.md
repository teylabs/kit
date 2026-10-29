# MCP consent and connected assistants

An AI assistant connects through Passport's authorization code flow. The mcp feature makes Passport's consent screen its own Inertia page, `Mcp/Authorize/Page` (generated at `resources/js/pages/Mcp/Authorize/Page.vue`), and binds the connection to a workspace there.

## The consent page

`McpProvider` registers `Passport::authorizationView()` with `Tey\Kit\Features\Mcp\Http\AuthorizationView`. Passport checks the client, its registered `redirect_uri` and the requested scopes before the page renders. An unknown client or a `redirect_uri` that isn't registered for it is refused with `invalid_client` and never redirected to. The page needs a signed-in person (`auth:web`, `concierge.active`) and a server-side session (see [Sessions](mcp-tools.md#sessions)).

The page (`AuthorizeViewModel`) shows:

- the client's name;
- what it asks for, in plain words: each scope's label from the registered token scopes, or the scope id as it was asked for when it has no label;
- the workspace the connection is bound to. The choices are the person's active workspaces where Access lets them open a connection (`kit.mcp.connection_ability`), and the default is the current workspace when it is one of them;
- Approve and Deny.

Approve and Deny are plain form posts carrying the session's CSRF token (`csrf_token`) and Passport's `auth_token`, not Inertia visits: each ends in a redirect to the assistant's own address, which an XHR visit can't follow. The host's `web` group checks the CSRF token as for any form.

- **Deny** is Passport's own deny: a redirect to the client's `redirect_uri` with `error=access_denied` and its `state`. No connection, code or token is made.
- **Approve** posts `workspace`. `BindConsentWorkspace` runs around Passport's approve and refuses with 403, before Passport issues a code, a workspace the person isn't an active member of or may not open a connection in. A request for narrow scopes must include the connection ability, and Access checks each narrow scope in that workspace. When Passport answers with a code, the middleware makes the connection in that workspace for the person and client: acts-as-me (`mcp:act`) for a plain `mcp:use` request, or the narrow scopes asked for. It has no token yet and waits ten minutes, as long as the code lives.
- **The code exchange** at `/oauth/token` fires `AccessTokenCreated`, and `RebindConnection` gives the new token the newest waiting connection for that person and client. That connection then expires with the token and follows its refreshes as before.

Passport skips the consent page when the person already holds a live token for the client with the requested scopes (or the client skips authorization). No new connection is made then; the existing one keeps following the token.

## Connected assistants and revoking

The AI assistants page (`Workspace/Settings/Connections/Page`, behind `kit.mcp.ui`) lists the workspace's live connections. A person sees their own. Someone Access grants `kit.mcp.manage_ability` (default `settings.write`) sees every member's, with who connected each.

Revoke (`DELETE settings/connections/{connection}`, `WorkspaceConnection::revoke()`):

- your own connection needs the connection ability;
- another member's needs `kit.mcp.manage_ability`, else `permission_denied` (403);
- a connection in another workspace is a 404.

Revoking marks the connection revoked and revokes its access token and that token's refresh token, unless another live connection still runs on the token. The connection's MCP address refuses at once (403), and so does the token.

`composer test:mcp-authorize` covers this through Passport's real authorize, approve, deny and token routes, on SQLite memory. The consent page is checked as its Inertia JSON. There is no browser render and no consumer app.
