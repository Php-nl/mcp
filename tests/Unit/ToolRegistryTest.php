<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit;

use Phpnl\Mcp\Protocol\ErrorCode;
use Phpnl\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ToolRegistry();
    }

    public function testRegistersAndListsTool(): void
    {
        $this->registry->register('ping', 'Returns pong', fn (): string => 'pong');

        $tools = $this->registry->all();

        $this->assertCount(1, $tools);
        $this->assertSame('ping', $tools[0]['name']);
        $this->assertSame('Returns pong', $tools[0]['description']);
    }

    public function testCallsRegisteredTool(): void
    {
        $this->registry->register('double', 'Doubles a number', fn (int $n): string => (string) ($n * 2));

        $result = $this->registry->call('double', ['n' => 5]);

        $this->assertSame('10', $result);
    }

    public function testCallMatchesArgumentsByName(): void
    {
        $this->registry->register(
            'subtract',
            'Subtracts b from a',
            fn (int $a, int $b): string => (string) ($a - $b),
        );

        $result = $this->registry->call('subtract', ['b' => 3, 'a' => 10]);

        $this->assertSame('7', $result);
    }

    public function testCallUsesDefaultValueForOptionalParameter(): void
    {
        $this->registry->register(
            'greet',
            'Greets',
            fn (string $name, string $greeting = 'Hello'): string => "{$greeting}, {$name}!",
        );

        $result = $this->registry->call('greet', ['name' => 'PHP']);

        $this->assertSame('Hello, PHP!', $result);
    }

    public function testThrowsWhenToolNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ToolNotFound->value);

        $this->registry->call('missing', []);
    }

    public function testHasReturnsTrueForRegisteredTool(): void
    {
        $this->registry->register('greet', 'Greets', fn (): string => 'Hello!');

        $this->assertTrue($this->registry->has('greet'));
        $this->assertFalse($this->registry->has('farewell'));
    }

    public function testSchemaReflectsHandlerParameters(): void
    {
        $this->registry->register('add', 'Adds numbers', fn (int $a, int $b): string => (string) ($a + $b));

        $tools = $this->registry->all();
        $schema = $tools[0]['inputSchema'];

        $this->assertArrayHasKey('a', $schema['properties']);
        $this->assertArrayHasKey('b', $schema['properties']);
        $this->assertContains('a', $schema['required']);
        $this->assertContains('b', $schema['required']);
    }
}
