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

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testCallValidatesRequiredArguments(): void
    {
        $this->registry->register('add', 'Adds', fn (int $a, int $b): string => (string) ($a + $b));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing required argument: b/');

        $this->registry->call('add', ['a' => 1]);
    }

    public function testCallValidatesArgumentTypes(): void
    {
        $this->registry->register('double', 'Doubles', fn (int $n): string => (string) ($n * 2));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Argument 'n' must be of type integer/");

        $this->registry->call('double', ['n' => 'not-a-number']);
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    public function testMiddlewareIsCalledOnToolInvocation(): void
    {
        $called = false;

        $this->registry->register('ping', 'Ping', fn (): string => 'pong');
        $this->registry->addMiddleware(function (string $name, array $args, callable $next) use (&$called): mixed {
            $called = true;

            return $next($name, $args);
        });

        $this->registry->call('ping', []);

        $this->assertTrue($called);
    }

    public function testMiddlewareReceivesToolNameAndArguments(): void
    {
        $capturedName = null;
        $capturedArgs = null;

        $this->registry->register('greet', 'Greets', fn (string $name): string => "Hello, {$name}!");
        $this->registry->addMiddleware(
            function (string $name, array $args, callable $next) use (&$capturedName, &$capturedArgs): mixed {
                $capturedName = $name;
                $capturedArgs = $args;

                return $next($name, $args);
            },
        );

        $this->registry->call('greet', ['name' => 'PHP']);

        $this->assertSame('greet', $capturedName);
        $this->assertSame(['name' => 'PHP'], $capturedArgs);
    }

    public function testMiddlewareCanInspectReturnValue(): void
    {
        $capturedResult = null;

        $this->registry->register('ping', 'Ping', fn (): string => 'pong');
        $this->registry->addMiddleware(
            function (string $name, array $args, callable $next) use (&$capturedResult): mixed {
                $result = $next($name, $args);
                $capturedResult = $result;

                return $result;
            },
        );

        $this->registry->call('ping', []);

        $this->assertSame('pong', $capturedResult);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $handlerCalled = false;

        $this->registry->register('ping', 'Ping', function () use (&$handlerCalled): string {
            $handlerCalled = true;

            return 'pong';
        });

        $this->registry->addMiddleware(
            fn (string $name, array $args, callable $next): mixed => 'short-circuited',
        );

        $result = $this->registry->call('ping', []);

        $this->assertSame('short-circuited', $result);
        $this->assertFalse($handlerCalled);
    }

    public function testMultipleMiddlewareRunInRegistrationOrder(): void
    {
        $order = [];

        $this->registry->register('ping', 'Ping', fn (): string => 'pong');

        $this->registry->addMiddleware(function (string $n, array $a, callable $next) use (&$order): mixed {
            $order[] = 'first-before';
            $result = $next($n, $a);
            $order[] = 'first-after';

            return $result;
        });

        $this->registry->addMiddleware(function (string $n, array $a, callable $next) use (&$order): mixed {
            $order[] = 'second-before';
            $result = $next($n, $a);
            $order[] = 'second-after';

            return $result;
        });

        $this->registry->call('ping', []);

        $this->assertSame(['first-before', 'second-before', 'second-after', 'first-after'], $order);
    }

    public function testMiddlewareOnlyRunsForCalledTool(): void
    {
        $invokedFor = [];

        $this->registry->register('ping', 'Ping', fn (): string => 'pong');
        $this->registry->register('echo', 'Echo', fn (string $msg): string => $msg);

        $this->registry->addMiddleware(
            function (string $name, array $args, callable $next) use (&$invokedFor): mixed {
                $invokedFor[] = $name;

                return $next($name, $args);
            },
        );

        $this->registry->call('ping', []);

        $this->assertSame(['ping'], $invokedFor);
    }
}
