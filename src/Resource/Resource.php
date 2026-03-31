<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Resource;

use Closure;

final readonly class Resource
{
    public function __construct(
        public string $uri,
        public string $name,
        public string $mimeType,
        public Closure $handler,
    ) {}
}
