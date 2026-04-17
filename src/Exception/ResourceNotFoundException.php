<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Exception;

use Phpnl\Mcp\Protocol\ErrorCode;

final class ResourceNotFoundException extends McpException
{
    public function __construct(string $uri, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCode::ResourceNotFound->message() . ": {$uri}",
            ErrorCode::ResourceNotFound,
            $previous,
        );
    }
}
