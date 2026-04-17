<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Exception;

use Phpnl\Mcp\Protocol\ErrorCode;

/**
 * Base class for all MCP protocol exceptions.
 *
 * Extends RuntimeException so existing catch (\RuntimeException) blocks
 * remain backward compatible. Use getErrorCode() to retrieve the MCP
 * error code for use in JSON-RPC error responses.
 */
abstract class McpException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ErrorCode $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode->value, $previous);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
