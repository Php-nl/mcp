<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Exception;

use Phpnl\Mcp\Protocol\ErrorCode;

final class InvalidToolArgumentsException extends McpException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, ErrorCode::InvalidParams, $previous);
    }
}
