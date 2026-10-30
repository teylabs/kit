<?php

namespace Tey\Kit\Features\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use RuntimeException;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * A resource registered as a descriptor with `Registry::mcpResources()`: its uri, name, mime type and description as given, read by its closure, which receives the MCP
 * request and the connection's workspace (null outside one). With a `capability`, it is gated like a tool: absent while that capability is off here or out of plan.
 */
final class DescriptorResource extends Resource
{
    use GatedByCapability;

    /** @param  array{uri: string, name: string, read: callable, mimeType?: string, description?: string, title?: string, capability?: string}  $descriptor */
    public function __construct(private readonly array $descriptor)
    {
        $this->uri = $descriptor['uri'];
        $this->name = $descriptor['name'];
        $this->mimeType = (string) ($descriptor['mimeType'] ?? 'text/plain');
        $this->description = (string) ($descriptor['description'] ?? '');
        $this->title = (string) ($descriptor['title'] ?? $descriptor['name']);
    }

    /** The descriptor's words exactly: laravel/mcp would otherwise derive a title and description from this class's name. */
    public function name(): string
    {
        return $this->descriptor['name'];
    }

    public function title(): string
    {
        return (string) ($this->descriptor['title'] ?? $this->descriptor['name']);
    }

    public function description(): string
    {
        return (string) ($this->descriptor['description'] ?? '');
    }

    public function capabilityGate(): ?string
    {
        return $this->descriptor['capability'] ?? null;
    }

    public function handle(Request $request): Response
    {
        $content = ($this->descriptor['read'])($request, Workspaces::current());
        if ($content instanceof Response) {
            return $content;
        }
        if (! is_string($content)) {
            throw new RuntimeException('An MCP resource read returns a string or a Response: '.$this->uri);
        }

        return Response::text($content);
    }
}
