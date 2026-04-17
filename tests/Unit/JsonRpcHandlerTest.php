<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit;

use Phpnl\Mcp\Protocol\ErrorCode;
use Phpnl\Mcp\Protocol\JsonRpcHandler;
use Phpnl\Mcp\Prompt\PromptRegistry;
use Phpnl\Mcp\Resource\ResourceRegistry;
use Phpnl\Mcp\Tool\ProgressReporter;
use Phpnl\Mcp\Tool\ToolRegistry;
use Phpnl\Mcp\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

final class JsonRpcHandlerTest extends TestCase
{
    private ToolRegistry $toolRegistry;
    private ResourceRegistry $resourceRegistry;
    private PromptRegistry $promptRegistry;
    private JsonRpcHandler $handler;

    protected function setUp(): void
    {
        $this->toolRegistry = new ToolRegistry();
        $this->resourceRegistry = new ResourceRegistry();
        $this->promptRegistry = new PromptRegistry();
        $this->handler = new JsonRpcHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
        );
    }

    public function testReturnsNullForBlankLine(): void
    {
        $this->assertNull($this->handler->handle(''));
    }

    public function testReturnsParseErrorForInvalidJson(): void
    {
        $response = json_decode($this->handler->handle('not json'), true);

        $this->assertSame(ErrorCode::ParseError->value, $response['error']['code']);
    }

    public function testHandlesInitialize(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2024-11-05'],
        ])), true);

        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertSame('phpnl/mcp', $response['result']['serverInfo']['name']);
    }

    public function testInitializeReturnsErrorForUnsupportedVersion(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2020-01-01'],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
        $this->assertStringContainsString('2020-01-01', $response['error']['data']);
    }

    public function testInitializeCapabilitiesOnlyIncludeToolsByDefault(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ])), true);

        $capabilities = $response['result']['capabilities'];
        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayNotHasKey('resources', $capabilities);
        $this->assertArrayNotHasKey('prompts', $capabilities);
    }

    public function testInitializeIncludesResourceCapabilityWhenRegistered(): void
    {
        $this->resourceRegistry->register('file://readme', 'README', 'text/plain', fn () => 'content');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ])), true);

        $this->assertArrayHasKey('resources', $response['result']['capabilities']);
    }

    public function testInitializeIncludesPromptCapabilityWhenRegistered(): void
    {
        $this->promptRegistry->register('summarize', 'Summarizes text', fn (array $args) => 'summary');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ])), true);

        $this->assertArrayHasKey('prompts', $response['result']['capabilities']);
    }

    public function testHandlesToolsList(): void
    {
        $this->toolRegistry->register('greet', 'Greets someone', fn (string $name): string => "Hello, $name!");

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ])), true);

        $this->assertCount(1, $response['result']['tools']);
        $this->assertSame('greet', $response['result']['tools'][0]['name']);
    }

    public function testHandlesToolsCall(): void
    {
        $this->toolRegistry->register('add', 'Adds two numbers', fn (int $a, int $b): string => (string) ($a + $b));

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'add', 'arguments' => ['a' => 2, 'b' => 3]],
        ])), true);

        $this->assertSame('5', $response['result']['content'][0]['text']);
    }

    public function testReturnsInvalidParamsWhenToolNameMissing(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['arguments' => []],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
    }

    public function testReturnsToolNotFoundError(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'non_existent', 'arguments' => []],
        ])), true);

        $this->assertSame(ErrorCode::ToolNotFound->value, $response['error']['code']);
    }

    public function testReturnsInternalErrorWhenToolThrowsUnexpectedException(): void
    {
        $this->toolRegistry->register('boom', 'Always fails', function (): string {
            throw new \LogicException('Unexpected failure');
        });

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'boom', 'arguments' => []],
        ])), true);

        $this->assertSame(ErrorCode::InternalError->value, $response['error']['code']);
        $this->assertStringContainsString('Unexpected failure', $response['error']['data']);
    }

    public function testReturnsMethodNotFoundError(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'unknown/method',
            'params' => [],
        ])), true);

        $this->assertSame(ErrorCode::MethodNotFound->value, $response['error']['code']);
    }

    public function testIgnoresInitializedNotification(): void
    {
        $response = $this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]));

        $this->assertNull($response);
    }

    public function testHandlesPing(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 99,
            'method' => 'ping',
            'params' => [],
        ])), true);

        $this->assertArrayHasKey('result', $response);
        $this->assertSame(99, $response['id']);
    }

    public function testResourcesReadWithoutUriReturnsInvalidParams(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 50,
            'method' => 'resources/read',
            'params' => [],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
    }

    public function testPromptsGetWithoutNameReturnsInvalidParams(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 51,
            'method' => 'prompts/get',
            'params' => [],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
    }

    public function testHandlesResourcesList(): void
    {
        $this->resourceRegistry->register('file://readme', 'README', 'text/plain', fn () => 'content');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'resources/list',
            'params' => [],
        ])), true);

        $this->assertCount(1, $response['result']['resources']);
        $this->assertSame('file://readme', $response['result']['resources'][0]['uri']);
    }

    public function testHandlesResourcesListEmpty(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'resources/list',
            'params' => [],
        ])), true);

        $this->assertEmpty($response['result']['resources']);
    }

    public function testHandlesResourcesRead(): void
    {
        $this->resourceRegistry->register('file://readme', 'README', 'text/plain', fn () => 'Hello World');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'resources/read',
            'params' => ['uri' => 'file://readme'],
        ])), true);

        $this->assertSame('Hello World', $response['result']['contents'][0]['text']);
    }

    public function testResourcesReadReturnsErrorForUnknownUri(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'resources/read',
            'params' => ['uri' => 'file://missing'],
        ])), true);

        $this->assertSame(ErrorCode::ResourceNotFound->value, $response['error']['code']);
    }

    public function testHandlesPromptsList(): void
    {
        $this->promptRegistry->register('summarize', 'Summarizes text', fn (array $args) => 'summary');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'prompts/list',
            'params' => [],
        ])), true);

        $this->assertCount(1, $response['result']['prompts']);
        $this->assertSame('summarize', $response['result']['prompts'][0]['name']);
    }

    public function testHandlesPromptsListEmpty(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'prompts/list',
            'params' => [],
        ])), true);

        $this->assertEmpty($response['result']['prompts']);
    }

    public function testHandlesPromptsGet(): void
    {
        $this->promptRegistry->register(
            'greet',
            'Greets a user',
            fn (array $args) => "Hello, {$args['name']}!",
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'prompts/get',
            'params' => ['name' => 'greet', 'arguments' => ['name' => 'PHP']],
        ])), true);

        $this->assertSame('Hello, PHP!', $response['result']['messages'][0]['content']['text']);
    }

    public function testPromptsGetReturnsErrorForUnknownPrompt(): void
    {
        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'prompts/get',
            'params' => ['name' => 'missing'],
        ])), true);

        $this->assertSame(ErrorCode::PromptNotFound->value, $response['error']['code']);
    }

    public function testReturnsInvalidParamsWhenRequiredArgumentMissing(): void
    {
        $this->toolRegistry->register('add', 'Adds two numbers', fn (int $a, int $b): string => (string) ($a + $b));

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 20,
            'method' => 'tools/call',
            'params' => ['name' => 'add', 'arguments' => ['a' => 1]],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
        $this->assertStringContainsString('Missing required argument: b', $response['error']['data']);
    }

    public function testReturnsInvalidParamsWhenArgumentTypeIsWrong(): void
    {
        $this->toolRegistry->register('double', 'Doubles', fn (int $n): string => (string) ($n * 2));

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'tools/call',
            'params' => ['name' => 'double', 'arguments' => ['n' => 'not-a-number']],
        ])), true);

        $this->assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
    }

    public function testHandlesToolsCallWithToolResultText(): void
    {
        $this->toolRegistry->register(
            'greet',
            'Greets',
            fn (string $name): ToolResult => ToolResult::text("Hello, {$name}!"),
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 30,
            'method' => 'tools/call',
            'params' => ['name' => 'greet', 'arguments' => ['name' => 'PHP']],
        ])), true);

        $content = $response['result']['content'];
        $this->assertCount(1, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('Hello, PHP!', $content[0]['text']);
    }

    public function testHandlesToolsCallWithToolResultImage(): void
    {
        $this->toolRegistry->register(
            'chart',
            'Returns a chart',
            fn (): ToolResult => ToolResult::image('abc123==', 'image/png'),
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 31,
            'method' => 'tools/call',
            'params' => ['name' => 'chart', 'arguments' => []],
        ])), true);

        $content = $response['result']['content'];
        $this->assertCount(1, $content);
        $this->assertSame('image', $content[0]['type']);
        $this->assertSame('abc123==', $content[0]['data']);
        $this->assertSame('image/png', $content[0]['mimeType']);
    }

    public function testHandlesToolsCallWithToolResultResource(): void
    {
        $this->toolRegistry->register(
            'config',
            'Returns config',
            fn (): ToolResult => ToolResult::resource('file://app.json', '{"env":"prod"}', 'application/json'),
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 32,
            'method' => 'tools/call',
            'params' => ['name' => 'config', 'arguments' => []],
        ])), true);

        $content = $response['result']['content'];
        $this->assertCount(1, $content);
        $this->assertSame('resource', $content[0]['type']);
        $this->assertSame('file://app.json', $content[0]['resource']['uri']);
        $this->assertSame('{"env":"prod"}', $content[0]['resource']['text']);
    }

    public function testHandlesToolsCallWithMultipleContentItems(): void
    {
        $this->toolRegistry->register(
            'report',
            'Returns a report with image',
            fn (): ToolResult => ToolResult::text('See chart below:')->withImage('data==', 'image/jpeg'),
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 33,
            'method' => 'tools/call',
            'params' => ['name' => 'report', 'arguments' => []],
        ])), true);

        $content = $response['result']['content'];
        $this->assertCount(2, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image', $content[1]['type']);
    }

    public function testStringResultStillWrappedAsTextForBackwardCompatibility(): void
    {
        $this->toolRegistry->register('ping', 'Ping', fn (): string => 'pong');

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 34,
            'method' => 'tools/call',
            'params' => ['name' => 'ping', 'arguments' => []],
        ])), true);

        $content = $response['result']['content'];
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('pong', $content[0]['text']);
    }

    // -------------------------------------------------------------------------
    // Progress notifications
    // -------------------------------------------------------------------------

    public function testProgressNotificationsAreSentWhenTokenPresent(): void
    {
        $written = [];
        $handler = new JsonRpcHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            function (string $msg) use (&$written): void {
                $written[] = json_decode($msg, true);
            },
        );

        $this->toolRegistry->register(
            'process',
            'Processes items',
            function (ProgressReporter $progress): string {
                $progress->report(1, 3);
                $progress->report(2, 3);
                $progress->report(3, 3);

                return 'done';
            },
        );

        $response = json_decode($handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 40,
            'method' => 'tools/call',
            'params' => [
                'name' => 'process',
                'arguments' => [],
                '_meta' => ['progressToken' => 'token-xyz'],
            ],
        ])), true);

        // Final response contains the result
        $this->assertSame('done', $response['result']['content'][0]['text']);

        // Three progress notifications were sent out-of-band
        $this->assertCount(3, $written);
        $this->assertSame('notifications/progress', $written[0]['method']);
        $this->assertSame('token-xyz', $written[0]['params']['progressToken']);
        $this->assertSame(1, $written[0]['params']['progress']);
        $this->assertSame(3, $written[0]['params']['total']);
        $this->assertSame(3, $written[2]['params']['progress']);
    }

    public function testNoProgressNotificationsWhenTokenAbsent(): void
    {
        $written = [];
        $handler = new JsonRpcHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            function (string $msg) use (&$written): void {
                $written[] = $msg;
            },
        );

        $this->toolRegistry->register(
            'silent',
            'Runs silently',
            function (ProgressReporter $progress): string {
                $progress->report(1, 1); // should be a no-op
                return 'ok';
            },
        );

        $handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 41,
            'method' => 'tools/call',
            'params' => ['name' => 'silent', 'arguments' => []],
        ]));

        $this->assertEmpty($written);
    }

    public function testProgressReporterParameterNotVisibleInToolsList(): void
    {
        $this->toolRegistry->register(
            'search',
            'Searches data',
            fn (string $query, ProgressReporter $progress): string => $query,
        );

        $response = json_decode($this->handler->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'tools/list',
            'params' => [],
        ])), true);

        $schema = $response['result']['tools'][0]['inputSchema'];
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayNotHasKey('progress', $schema['properties']);
    }
}
