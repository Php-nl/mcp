<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit;

use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Protocol\JsonRpcHandler;
use Phpnl\Mcp\Tests\TestCase;
use Phpnl\Mcp\Tool\ProgressReporter;
use Phpnl\Mcp\Transport\TransportInterface;

final class McpServerTest extends TestCase
{
    public function testMakeReturnsMcpServerInstance(): void
    {
        $transport = $this->makeFakeTransport([]);

        $server = McpServer::make($transport);

        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function testToolReturnsSelf(): void
    {
        $transport = $this->makeFakeTransport([]);

        $server = McpServer::make($transport);
        $result = $server->tool('ping', 'Returns pong', fn (): string => 'pong');

        $this->assertSame($server, $result);
    }

    public function testResourceReturnsSelf(): void
    {
        $transport = $this->makeFakeTransport([]);

        $server = McpServer::make($transport);
        $result = $server->resource('file://readme', 'README', 'text/plain', fn () => 'content');

        $this->assertSame($server, $result);
    }

    public function testPromptReturnsSelf(): void
    {
        $transport = $this->makeFakeTransport([]);

        $server = McpServer::make($transport);
        $result = $server->prompt('summarize', 'Summarizes text', fn (array $args) => 'summary');

        $this->assertSame($server, $result);
    }

    public function testServeSkipsNullAndEmptyLines(): void
    {
        $transport = $this->makeFakeTransport([null, '']);

        $server = McpServer::make($transport);

        try {
            $server->serve();
        } catch (\OverflowException) {
        }

        $this->assertEmpty($transport->getWritten());
    }

    public function testServeExitsCleanlyOnEof(): void
    {
        $transport = $this->makeFakeTransport([null]);

        $server = McpServer::make($transport);
        $server->serve();

        $this->assertEmpty($transport->getWritten());
    }

    public function testServeWritesResponseForValidRequest(): void
    {
        $initMessage = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $transport = $this->makeFakeTransport([$initMessage]);

        $server = McpServer::make($transport);

        try {
            $server->serve();
        } catch (\OverflowException) {
        }

        $this->assertCount(1, $transport->getWritten());
        $decoded = json_decode($transport->getWritten()[0], true);
        $this->assertSame(JsonRpcHandler::LATEST_PROTOCOL_VERSION, $decoded['result']['protocolVersion']);
    }

    public function testServeSkipsWriteWhenHandlerReturnsNull(): void
    {
        $notification = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $transport = $this->makeFakeTransport([$notification]);

        $server = McpServer::make($transport);

        try {
            $server->serve();
        } catch (\OverflowException) {
        }

        $this->assertEmpty($transport->getWritten());
    }

    public function testMiddlewareReturnsSelf(): void
    {
        $transport = $this->makeFakeTransport([]);
        $server = McpServer::make($transport);

        $result = $server->middleware(fn (string $name, array $args, callable $next): mixed => $next($name, $args));

        $this->assertSame($server, $result);
    }

    public function testMiddlewareIsInvokedDuringToolCall(): void
    {
        $called = false;

        $toolCallMessage = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'ping', 'arguments' => []],
        ]);

        $transport = $this->makeFakeTransport([$toolCallMessage]);

        $server = McpServer::make($transport);
        $server->tool('ping', 'Returns pong', fn (): string => 'pong');
        $server->middleware(function (string $name, array $args, callable $next) use (&$called): mixed {
            $called = true;

            return $next($name, $args);
        });

        try {
            $server->serve();
        } catch (\OverflowException) {
        }

        $this->assertTrue($called);
    }

    public function testServeProgressWriterClosureInvokesTransportWrite(): void
    {
        $toolCallMessage = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'work',
                'arguments' => [],
                '_meta' => ['progressToken' => 'tok-1'],
            ],
        ]);

        $transport = $this->makeFakeTransport([$toolCallMessage]);

        $server = McpServer::make($transport);
        $server->tool('work', 'Does work', function (ProgressReporter $progress): string {
            $progress->report(1, 1);

            return 'done';
        });

        try {
            $server->serve();
        } catch (\OverflowException) {
        }

        // At least 2 writes: the progress notification + the final tool response.
        // This exercises the writer closure on line 85 of McpServer.
        $this->assertGreaterThanOrEqual(2, count($transport->getWritten()));

        $notification = json_decode($transport->getWritten()[0], true);
        $this->assertSame('notifications/progress', $notification['method']);
    }

    private function makeFakeTransport(array $messages): object
    {
        return new class ($messages) implements TransportInterface {
            private int $index = 0;

            private array $written = [];

            public function __construct(private readonly array $messages)
            {
            }

            public function read(): ?string
            {
                if ($this->index >= count($this->messages)) {
                    throw new \OverflowException('Messages exhausted');
                }

                return $this->messages[$this->index++];
            }

            public function write(string $message): void
            {
                $this->written[] = $message;
            }

            public function getWritten(): array
            {
                return $this->written;
            }
        };
    }
}
