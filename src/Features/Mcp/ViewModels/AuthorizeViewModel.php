<?php

namespace Tey\Kit\Features\Mcp\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Mcp/Authorize/Page.vue: Passport's consent screen for an AI assistant, with the workspace its connection is bound to. */
#[TypeScript]
final class AuthorizeViewModel extends PageViewModel
{
    /**
     * @param  list<array{key: string, label: string}>  $scopes  what the assistant asks for, in plain words; an unknown scope is listed as it was asked for
     * @param  list<array{id: int, name: string}>  $workspaces  the active workspaces the person may connect it to
     */
    public function __construct(private string $title, private string $client, private array $scopes, private array $workspaces, private ?int $workspace, private string $approve_url, private string $deny_url, private string $auth_token, private string $csrf_token, private string $redirect_host = '', private bool $registered = false, private array $grants = []) {}

    public function title(): string
    {
        return $this->title;
    }

    /** The OAuth client's name. */
    public function client(): string
    {
        return $this->client;
    }

    /** The host the assistant will be sent back to (the redirect URI's), shown so a person can tell a lookalike from the app they expect. */
    public function redirect_host(): string
    {
        return $this->redirect_host;
    }

    /** Whether the client registered itself (dynamic client registration): its name is its own claim, so the page calls it an unverified app. */
    public function registered(): bool
    {
        return $this->registered;
    }

    /**
     * The explicit grants the person may add, each an unticked checkbox: what the assistant could also do, in the person's words (`explains`). Off by default; a
     * blanket approval never includes them.
     *
     * @return list<array{scope: string, label: string, explains: string, checked: bool}>
     */
    public function grants(): array
    {
        return $this->grants;
    }

    /** @return list<array{key: string, label: string}> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /** @return list<array{id: int, name: string}> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

    /** The workspace chosen by default: the current one when it is in the list, else the first; null when there is none. */
    public function workspace(): ?int
    {
        return $this->workspace;
    }

    public function approve_url(): string
    {
        return $this->approve_url;
    }

    public function deny_url(): string
    {
        return $this->deny_url;
    }

    /** Passport's per-request token, posted back with approve or deny. */
    public function auth_token(): string
    {
        return $this->auth_token;
    }

    /** Approve and deny are plain form posts (they end in a redirect to the assistant), so they carry the session's CSRF token themselves. */
    public function csrf_token(): string
    {
        return $this->csrf_token;
    }
}
