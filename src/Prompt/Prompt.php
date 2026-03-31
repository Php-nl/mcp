<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Prompt;

use Closure;

final readonly class Prompt
{
    public function __construct(
        public string $name,
        public string $description,
        public Closure $handler,
    ) {}
}
