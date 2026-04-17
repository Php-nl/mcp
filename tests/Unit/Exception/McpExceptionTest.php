<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Exception;

use Phpnl\Mcp\Exception\InvalidToolArgumentsException;
use Phpnl\Mcp\Exception\McpException;
use Phpnl\Mcp\Exception\PromptNotFoundException;
use Phpnl\Mcp\Exception\ResourceNotFoundException;
use Phpnl\Mcp\Exception\ToolNotFoundException;
use Phpnl\Mcp\Protocol\ErrorCode;
use PHPUnit\Framework\TestCase;

final class McpExceptionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Inheritance
    // -------------------------------------------------------------------------

    public function testToolNotFoundExceptionExtendsMcpException(): void
    {
        $this->assertInstanceOf(McpException::class, new ToolNotFoundException('ping'));
    }

    public function testToolNotFoundExceptionExtendsRuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ToolNotFoundException('ping'));
    }

    public function testInvalidToolArgumentsExceptionExtendsMcpException(): void
    {
        $this->assertInstanceOf(McpException::class, new InvalidToolArgumentsException('bad arg'));
    }

    public function testResourceNotFoundExceptionExtendsMcpException(): void
    {
        $this->assertInstanceOf(McpException::class, new ResourceNotFoundException('file://x'));
    }

    public function testPromptNotFoundExceptionExtendsMcpException(): void
    {
        $this->assertInstanceOf(McpException::class, new PromptNotFoundException('summarize'));
    }

    // -------------------------------------------------------------------------
    // Error codes
    // -------------------------------------------------------------------------

    public function testToolNotFoundExceptionHasCorrectErrorCode(): void
    {
        $e = new ToolNotFoundException('ping');

        $this->assertSame(ErrorCode::ToolNotFound, $e->getErrorCode());
        $this->assertSame(ErrorCode::ToolNotFound->value, $e->getCode());
    }

    public function testInvalidToolArgumentsExceptionHasCorrectErrorCode(): void
    {
        $e = new InvalidToolArgumentsException('Missing required argument: id');

        $this->assertSame(ErrorCode::InvalidParams, $e->getErrorCode());
        $this->assertSame(ErrorCode::InvalidParams->value, $e->getCode());
    }

    public function testResourceNotFoundExceptionHasCorrectErrorCode(): void
    {
        $e = new ResourceNotFoundException('file://missing.txt');

        $this->assertSame(ErrorCode::ResourceNotFound, $e->getErrorCode());
        $this->assertSame(ErrorCode::ResourceNotFound->value, $e->getCode());
    }

    public function testPromptNotFoundExceptionHasCorrectErrorCode(): void
    {
        $e = new PromptNotFoundException('summarize');

        $this->assertSame(ErrorCode::PromptNotFound, $e->getErrorCode());
        $this->assertSame(ErrorCode::PromptNotFound->value, $e->getCode());
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    public function testToolNotFoundExceptionMessageContainsToolName(): void
    {
        $e = new ToolNotFoundException('my_tool');

        $this->assertStringContainsString('my_tool', $e->getMessage());
    }

    public function testResourceNotFoundExceptionMessageContainsUri(): void
    {
        $e = new ResourceNotFoundException('file://config.json');

        $this->assertStringContainsString('file://config.json', $e->getMessage());
    }

    public function testPromptNotFoundExceptionMessageContainsPromptName(): void
    {
        $e = new PromptNotFoundException('my_prompt');

        $this->assertStringContainsString('my_prompt', $e->getMessage());
    }

    public function testInvalidToolArgumentsExceptionPreservesMessage(): void
    {
        $e = new InvalidToolArgumentsException("Argument 'id' must be of type integer, got string");

        $this->assertSame("Argument 'id' must be of type integer, got string", $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // Previous exception chaining
    // -------------------------------------------------------------------------

    public function testToolNotFoundExceptionAcceptsPreviousException(): void
    {
        $previous = new \LogicException('root cause');
        $e = new ToolNotFoundException('ping', $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testResourceNotFoundExceptionAcceptsPreviousException(): void
    {
        $previous = new \LogicException('root cause');
        $e = new ResourceNotFoundException('file://x', $previous);

        $this->assertSame($previous, $e->getPrevious());
    }
}
