<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Exception;

use Phpnl\Mcp\Protocol\ErrorCode;

final class ToolNotFoundException extends McpException
{
    public function __construct(string $toolName, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCode::ToolNotFound->message() . ": {$toolName}",
            ErrorCode::ToolNotFound,
            $previous,
        );
    }
}
