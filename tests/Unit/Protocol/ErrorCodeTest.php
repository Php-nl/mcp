<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Protocol;

use Phpnl\Mcp\Protocol\ErrorCode;
use Phpnl\Mcp\Tests\TestCase;

final class ErrorCodeTest extends TestCase
{
    public function testValuesMatchJsonRpcSpec(): void
    {
        $this->assertSame(-32700, ErrorCode::ParseError->value);
        $this->assertSame(-32600, ErrorCode::InvalidRequest->value);
        $this->assertSame(-32601, ErrorCode::MethodNotFound->value);
        $this->assertSame(-32602, ErrorCode::InvalidParams->value);
        $this->assertSame(-32603, ErrorCode::InternalError->value);
        $this->assertSame(-32000, ErrorCode::ToolNotFound->value);
        $this->assertSame(-32001, ErrorCode::ResourceNotFound->value);
        $this->assertSame(-32002, ErrorCode::PromptNotFound->value);
    }

    public function testMessages(): void
    {
        $this->assertSame('Parse error', ErrorCode::ParseError->message());
        $this->assertSame('Invalid Request', ErrorCode::InvalidRequest->message());
        $this->assertSame('Method not found', ErrorCode::MethodNotFound->message());
        $this->assertSame('Invalid params', ErrorCode::InvalidParams->message());
        $this->assertSame('Internal error', ErrorCode::InternalError->message());
        $this->assertSame('Tool not found', ErrorCode::ToolNotFound->message());
        $this->assertSame('Resource not found', ErrorCode::ResourceNotFound->message());
        $this->assertSame('Prompt not found', ErrorCode::PromptNotFound->message());
    }
}
