<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Description
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
