<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Resource;

use Closure;
use Phpnl\Mcp\Exception\ResourceNotFoundException;

final class ResourceRegistry
{
    /** @var array<string, Resource> */
    private array $resources = [];

    public function register(string $uri, string $name, string $mimeType, Closure $handler): void
    {
        $this->resources[$uri] = new Resource($uri, $name, $mimeType, $handler);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (Resource $resource) => [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'mimeType' => $resource->mimeType,
            ],
            array_values($this->resources),
        );
    }

    public function read(string $uri): string
    {
        if (! isset($this->resources[$uri])) {
            throw new ResourceNotFoundException($uri);
        }

        return (string) ($this->resources[$uri]->handler)();
    }

    public function has(string $uri): bool
    {
        return isset($this->resources[$uri]);
    }

    public function isEmpty(): bool
    {
        return $this->resources === [];
    }
}
