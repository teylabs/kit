<?php

namespace Tey\Kit\Features\Mcp;

use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Mcp\Data\AssistantClient;

/**
 * The "add an AI assistant" cards for the MCP address, in the order `kit.mcp.assistants` lists them (an app trims or reorders it). Install links follow each tool's own format:
 * Cursor `cursor://anysphere.cursor-deeplink/mcp/install?name=…&config=<base64 JSON>` (its install-links docs) and VS Code `vscode:mcp/install?<URL-encoded JSON>` (its MCP developer
 * guide); Claude Code is `claude mcp add --transport http <name> <url>`. The steps for Claude and ChatGPT are plain words, because those connect from their own settings.
 */
final class AssistantClients
{
    /** @return list<AssistantClient> */
    public static function for(string $serverUrl, string $name): array
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'workspace';
        $cards = [
            'claude-ai' => fn () => new AssistantClient('claude-ai', 'Claude', 'In Claude on the web or the desktop app.', null, null, [
                'Open Claude, then Settings → Connectors → Add custom connector.',
                'Paste the address above and name it "'.$name.'".',
                'Sign in when Claude asks, then come back here to approve it.',
            ]),
            'chatgpt' => fn () => new AssistantClient('chatgpt', 'ChatGPT', 'In ChatGPT on the web.', null, null, [
                'Open ChatGPT, then Settings → Connectors, and turn on developer mode if it asks.',
                'Create a connector, paste the address above and name it "'.$name.'".',
                'Sign in when ChatGPT asks, then come back here to approve it.',
            ]),
            'claude-code' => fn () => new AssistantClient('claude-code', 'Claude Code', 'In your terminal.', null, 'claude mcp add --transport http '.$slug.' '.$serverUrl, [
                'Run the command in your terminal.',
                'Run /mcp in Claude Code and choose to sign in.',
                'Come back here to approve it.',
            ]),
            'cursor' => fn () => new AssistantClient('cursor', 'Cursor', 'One click, in the Cursor app.', new Action('Add to Cursor', 'cursor://anysphere.cursor-deeplink/mcp/install?name='.rawurlencode($slug).'&config='.base64_encode((string) json_encode(['url' => $serverUrl], JSON_UNESCAPED_SLASHES)), 'get'), null, [
                'Choose Add to Cursor and confirm in the app.',
                'Sign in when Cursor asks, then come back here to approve it.',
            ]),
            'vscode' => fn () => new AssistantClient('vscode', 'VS Code', 'One click, in VS Code.', new Action('Add to VS Code', 'vscode:mcp/install?'.rawurlencode((string) json_encode(['name' => $slug, 'type' => 'http', 'url' => $serverUrl], JSON_UNESCAPED_SLASHES)), 'get'), null, [
                'Choose Add to VS Code and confirm in the editor.',
                'Sign in when it asks, then come back here to approve it.',
            ]),
        ];

        return array_values(array_filter(array_map(fn (string $key) => isset($cards[$key]) ? $cards[$key]() : null, (array) config('kit.mcp.assistants', array_keys($cards)))));
    }
}
