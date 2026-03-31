<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Protocol;

enum ErrorCode: int
{
    case ParseError = -32700;
    case InvalidRequest = -32600;
    case MethodNotFound = -32601;
    case InvalidParams = -32602;
    case InternalError = -32603;
    case ToolNotFound = -32000;
    case ResourceNotFound = -32001;
    case PromptNotFound = -32002;

    public function message(): string
    {
        return match ($this) {
            self::ParseError => 'Parse error',
            self::InvalidRequest => 'Invalid Request',
            self::MethodNotFound => 'Method not found',
            self::InvalidParams => 'Invalid params',
            self::InternalError => 'Internal error',
            self::ToolNotFound => 'Tool not found',
            self::ResourceNotFound => 'Resource not found',
            self::PromptNotFound => 'Prompt not found',
        };
    }
}
