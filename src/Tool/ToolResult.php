<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

/**
 * Represents the structured result of a tool invocation.
 *
 * MCP supports three content types in a tools/call response:
 *   - text    — plain or markdown text
 *   - image   — base64-encoded image with a MIME type
 *   - resource — an embedded resource (URI + text/blob + MIME type)
 *
 * A result can contain multiple content items of mixed types.
 * ToolResult is immutable; the with*() methods return new instances.
 *
 * Tool handlers may return a plain string for convenience — the handler's
 * return value is automatically wrapped in a text ToolResult by the server.
 *
 * Usage:
 *
 *   // Single text item (most common)
 *   return ToolResult::text('Hello from PHP!');
 *
 *   // Single image
 *   return ToolResult::image(base64_encode($png), 'image/png');
 *
 *   // Multiple items chained together
 *   return ToolResult::text('Here is the chart:')
 *       ->withImage(base64_encode($png), 'image/png');
 *
 *   // Embedded resource
 *   return ToolResult::resource('file://config.json', $json, 'application/json');
 */
final readonly class ToolResult
{
    /** @param list<array<string, mixed>> $content */
    private function __construct(
        private array $content,
    ) {}

    /**
     * Creates a result with a single text content item.
     */
    public static function text(string $text): self
    {
        return new self([
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Creates a result with a single image content item.
     *
     * @param string $data     Base64-encoded image data.
     * @param string $mimeType MIME type, e.g. 'image/png', 'image/jpeg'.
     */
    public static function image(string $data, string $mimeType): self
    {
        return new self([
            ['type' => 'image', 'data' => $data, 'mimeType' => $mimeType],
        ]);
    }

    /**
     * Creates a result with a single embedded resource content item.
     *
     * @param string $uri      Resource URI, e.g. 'file://report.json'.
     * @param string $text     Text representation of the resource.
     * @param string $mimeType MIME type of the resource, e.g. 'application/json'.
     */
    public static function resource(string $uri, string $text, string $mimeType): self
    {
        return new self([
            [
                'type' => 'resource',
                'resource' => ['uri' => $uri, 'text' => $text, 'mimeType' => $mimeType],
            ],
        ]);
    }

    /**
     * Appends a text content item and returns a new instance.
     */
    public function withText(string $text): self
    {
        return new self([
            ...$this->content,
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Appends an image content item and returns a new instance.
     *
     * @param string $data     Base64-encoded image data.
     * @param string $mimeType MIME type, e.g. 'image/png', 'image/jpeg'.
     */
    public function withImage(string $data, string $mimeType): self
    {
        return new self([
            ...$this->content,
            ['type' => 'image', 'data' => $data, 'mimeType' => $mimeType],
        ]);
    }

    /**
     * Appends an embedded resource content item and returns a new instance.
     *
     * @param string $uri      Resource URI, e.g. 'file://report.json'.
     * @param string $text     Text representation of the resource.
     * @param string $mimeType MIME type of the resource.
     */
    public function withResource(string $uri, string $text, string $mimeType): self
    {
        return new self([
            ...$this->content,
            [
                'type' => 'resource',
                'resource' => ['uri' => $uri, 'text' => $text, 'mimeType' => $mimeType],
            ],
        ]);
    }

    /**
     * Returns the MCP content array, ready for inclusion in a tools/call response.
     *
     * @return list<array<string, mixed>>
     */
    public function toContent(): array
    {
        return $this->content;
    }
}
