<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Exception;

use Phpnl\Mcp\Protocol\ErrorCode;

final class PromptNotFoundException extends McpException
{
    public function __construct(string $promptName, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCode::PromptNotFound->message() . ": {$promptName}",
            ErrorCode::PromptNotFound,
            $previous,
        );
    }
}
