# phpnl/mcp

> Connect your PHP application to AI models like Claude in minutes.

```bash
composer require phpnl/mcp
```

## Quick Start

Create a `server.php` file:

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Tool\Description;

McpServer::make()
    ->tool(
        name: 'get_user',
        description: 'Fetch a user from the database',
        handler: function (
            #[Description('The unique user ID')] int $id,
        ): string {
            return json_encode(['id' => $id, 'name' => 'Demo User']);
        },
    )
    ->resource('file://config', 'App Config', 'application/json', function (): string {
        return json_encode(['env' => 'production']);
    })
    ->prompt('summarize', 'Summarize a given topic', function (array $args): string {
        return "Please summarize the following: {$args['topic']}";
    })
    ->serve();
```

Add to your Claude Desktop `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "my-php-app": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

Restart Claude Desktop — your PHP tools appear automatically. ✅

## HTTP + SSE Transport

The default transport uses STDIN/STDOUT and is ideal for local CLI-based MCP clients like Claude Desktop. For web-based clients or browser integrations, use the `HttpSseTransport` instead.

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Transport\HttpSseTransport;

$transport = new HttpSseTransport(port: 8080);

echo "MCP server listening on http://localhost:8080/sse\n";

McpServer::make($transport)
    ->tool('hello', 'Returns a greeting', fn (): string => 'Hello from PHP!')
    ->serve();
```

Start the server:

```bash
php server.php
```

The transport exposes two endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/sse` | `GET` | Client connects here and receives a Server-Sent Events stream. The first event is an `endpoint` event pointing to the POST URL. |
| `/message` | `POST` | Client sends JSON-RPC messages here. Responses arrive via the SSE stream. |

### Configuration options

```php
new HttpSseTransport(
    host: '0.0.0.0',          // Interface to bind (default: 0.0.0.0)
    port: 8080,                // TCP port (default: 8080)
    ssePath: '/sse',           // SSE endpoint path (default: /sse)
    messagePath: '/message',   // POST endpoint path (default: /message)
    baseUrl: null,             // Override the public URL in the endpoint event.
                               // Set this when running behind a reverse proxy,
                               // e.g. 'https://my-server.example.com'
);
```

### CORS

All responses include `Access-Control-Allow-Origin: *` and CORS preflight (`OPTIONS`) requests are handled automatically, so browser-based MCP clients work out of the box.

### Reverse proxy

When deploying behind nginx or another reverse proxy, set `baseUrl` to the public-facing URL so clients receive the correct endpoint:

```php
$transport = new HttpSseTransport(
    host: '127.0.0.1',
    port: 9000,
    baseUrl: 'https://api.example.com',
);
```

## Rich Tool Results

By default a tool handler returns a plain string, which is sent to the client as a text content item. For richer responses — images, embedded resources, or multiple mixed items — return a `ToolResult` instead.

```php
use Phpnl\Mcp\Tool\ToolResult;

McpServer::make()
    // Plain text (most common — string handlers still work too)
    ->tool('summarize', 'Summarizes text', fn (string $text): ToolResult =>
        ToolResult::text("Summary: {$text}")
    )

    // Image (e.g. a generated chart)
    ->tool('chart', 'Renders a bar chart', function (array $data): ToolResult {
        $png = renderChart($data); // returns raw PNG bytes
        return ToolResult::image(base64_encode($png), 'image/png');
    })

    // Text + image combined
    ->tool('report', 'Full report with chart', function (): ToolResult {
        return ToolResult::text('Monthly revenue: €12,400')
            ->withImage(base64_encode(renderChart()), 'image/png');
    })

    // Embedded resource
    ->tool('get_config', 'Returns app config', function (): ToolResult {
        $json = file_get_contents('config.json');
        return ToolResult::resource('file://config.json', $json, 'application/json');
    })
    ->serve();
```

`ToolResult` is immutable. The `with*()` methods append a new content item and return a new instance, so you can chain as many items as needed.

## Input Validation

Arguments sent by the AI are automatically validated against the tool's JSON Schema before the handler is invoked. If a required argument is missing or has the wrong type, a `InvalidParams` error is returned to the client without ever calling your handler.

```php
McpServer::make()
    ->tool(
        name: 'send_email',
        description: 'Sends an email',
        handler: function (string $to, string $subject, string $body): string {
            // $to, $subject and $body are guaranteed to be strings here
            return "Email sent to {$to}";
        },
    )
    ->serve();
```

If the AI omits `subject`, the response will be:

```json
{"error": {"code": -32602, "message": "Invalid params", "data": "Missing required argument: subject"}}
```

## Exception Handling

All MCP-specific errors are thrown as typed exceptions that extend `McpException` (which itself extends `\RuntimeException`).

| Exception | Thrown when | Error code |
|---|---|---|
| `ToolNotFoundException` | A tool name is not registered | `ToolNotFound` (-32601) |
| `InvalidToolArgumentsException` | Required argument missing or wrong type | `InvalidParams` (-32602) |
| `ResourceNotFoundException` | A resource URI is not registered | `ResourceNotFound` (-32002) |
| `PromptNotFoundException` | A prompt name is not registered | `PromptNotFound` (-32003) |

You can catch them individually or as a group:

```php
use Phpnl\Mcp\Exception\McpException;
use Phpnl\Mcp\Exception\ToolNotFoundException;

try {
    $registry->call('missing_tool', []);
} catch (ToolNotFoundException $e) {
    // specific handling
} catch (McpException $e) {
    // catch any other MCP error
    echo $e->getErrorCode()->value; // JSON-RPC error code integer
}
```

All exceptions carry the correct JSON-RPC error code as both `getErrorCode(): ErrorCode` and the native `getCode(): int`.

## Middleware

Register middleware to run logic before or after every tool invocation — for logging, authentication, rate limiting, caching, and more.

```php
McpServer::make()
    ->middleware(function (string $name, array $args, callable $next): mixed {
        // Before: runs before the handler
        $start = microtime(true);

        $result = $next($name, $args);

        // After: runs after the handler returns
        $ms = round((microtime(true) - $start) * 1000);
        error_log("Tool '{$name}' took {$ms}ms");

        return $result;
    })
    ->tool('ping', 'Returns pong', fn (): string => 'pong')
    ->serve();
```

Multiple middleware are executed in registration order (first registered runs first). Each middleware must call `$next($name, $args)` to continue the chain, or return a value directly to short-circuit.

## Developer CLI

```bash
# Inspect all tools registered by a server
./vendor/bin/phpnl inspect server.php

# Debug: show full JSON-RPC traffic (tools, resources, prompts)
./vendor/bin/phpnl debug server.php

# Call a tool directly with arguments
./vendor/bin/phpnl call server.php get_user --id=1
./vendor/bin/phpnl call server.php price_check --amount=9.99
```

## Supported MCP Methods

| Method | Status |
|--------|--------|
| `initialize` | ✅ |
| `tools/list` | ✅ |
| `tools/call` | ✅ |
| `resources/list` | ✅ |
| `resources/read` | ✅ |
| `prompts/list` | ✅ |
| `prompts/get` | ✅ |
| `notifications/initialized` | ✅ |

## Transports

| Transport | Class | Use case |
|-----------|-------|----------|
| STDIN/STDOUT | `StdioTransport` *(default)* | Claude Desktop and other local CLI clients |
| HTTP + SSE | `HttpSseTransport` | Web-based clients, browser integrations, remote deployments |

## Requirements

- PHP 8.3+
- Zero runtime dependencies

## Testing

```bash
composer install
composer test
composer stan
composer lint
```

## License

MIT — made with ❤️ by [php.nl](https://php.nl)
