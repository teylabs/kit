<?php

namespace Tey\Kit\Features\Mcp;

/** What a person sees an MCP client called: `kit.mcp.assistant_label`, [singular, plural], default "AI assistant" and "AI assistants". */
final class AssistantLabel
{
    public static function singular(): string
    {
        return (string) (((array) config('kit.mcp.assistant_label', []))[0] ?? 'AI assistant');
    }

    public static function plural(): string
    {
        return (string) (((array) config('kit.mcp.assistant_label', []))[1] ?? 'AI assistants');
    }

    /** "a" or "an" before the singular, by its first letter. */
    public static function article(): string
    {
        return preg_match('/^[aeiou]/i', self::singular()) === 1 ? 'an' : 'a';
    }
}
