# phpnl/mcp

[![CI](https://github.com/Php-nl/mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/Php-nl/mcp/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/Php-nl/mcp/branch/main/graph/badge.svg)](https://codecov.io/gh/Php-nl/mcp)
[![Latest Version](https://img.shields.io/packagist/v/phpnl/mcp.svg)](https://packagist.org/packages/phpnl/mcp)
[![License](https://img.shields.io/packagist/l/phpnl/mcp.svg)](LICENSE)

A framework-agnostic PHP SDK for the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). Connect your PHP application to AI models like Claude in minutes — zero runtime dependencies.

```bash
composer require phpnl/mcp
```

---

## Table of Contents

- [What is MCP?](#what-is-mcp)
- [Quick Start](#quick-start)
- [Tools](#tools)
  - [Registering a tool](#registering-a-tool)
  - [Type-safe parameters](#type-safe-parameters)
  - [Parameter descriptions](#parameter-descriptions)
  - [Optional parameters](#optional-parameters)
  - [Input validation](#input-validation)
  - [Rich tool results](#rich-tool-results)
  - [Progress notifications](#progress-notifications)
- [Resources](#resources)
- [Prompts](#prompts)
- [Middleware](#middleware)
- [Exception handling](#exception-handling)
- [Transports](#transports)
  - [STDIN/STDOUT (default)](#stdinstdout-default)
  - [HTTP + SSE](#http--sse)
- [Developer CLI](#developer-cli)
- [API reference](#api-reference)
- [Requirements](#requirements)
- [Testing](#testing)
- [License](#license)

---

## What is MCP?

The Model Context Protocol is an open standard that lets AI models interact with external tools, data sources, and services. An MCP server exposes:

- **Tools** — functions the AI can call (e.g. query a database, send an email)
- **Resources** — read-only data the AI can reference (e.g. config files, documentation)
- **Prompts** — reusable prompt templates the AI can retrieve and use

This library implements the MCP server side in PHP.

---

## Quick Start

Create `server.php`:

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Phpnl\Mcp\McpServer;

McpServer::make()
    ->tool(
        name: 'get_user',
        description: 'Fetch a user record from the database',
        handler: function (int $id): string {
            $user = fetchUserById($id); // your own code
            return json_encode($user);
        },
    )
    ->serve();
```

Add the server to your Claude Desktop `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "my-app": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

Restart Claude Desktop — your tools are available immediately.

---

## Tools

### Registering a tool

```php
McpServer::make()
    ->tool(
        name: 'send_email',
        description: 'Send an email to a recipient',
        handler: function (string $to, string $subject, string $body): string {
            mail($to, $subject, $body);
            return "Email sent to {$to}";
        },
    )
    ->serve();
```

The `handler` is a `Closure`. Its parameter names and type hints are automatically reflected into the tool's JSON Schema that is advertised to the AI.

### Type-safe parameters

PHP types map directly to JSON Schema types:

| PHP type | JSON Schema type |
|----------|-----------------|
| `string` | `"string"` |
| `int` | `"integer"` |
| `float` | `"number"` |
| `bool` | `"boolean"` |
| `array` | `"array"` |
| `?T` / `T\|null` | `["T", "null"]` |

Any other type hint falls back to `"string"`.

### Parameter descriptions

Use the `#[Description]` attribute to add human-readable descriptions to parameters. These are included in the JSON Schema so the AI understands what each argument is for.

```php
use Phpnl\Mcp\Tool\Description;

McpServer::make()
    ->tool(
        name: 'search_products',
        description: 'Search the product catalogue',
        handler: function (
            #[Description('Search term, e.g. "blue jeans"')] string $query,
            #[Description('Maximum number of results to return (1–100)')] int $limit = 10,
        ): string {
            return json_encode(searchProducts($query, $limit));
        },
    )
    ->serve();
```

### Optional parameters

Parameters with default values are optional — the AI may omit them, and the default is used automatically.

```php
handler: function (string $name, string $greeting = 'Hello'): string {
    return "{$greeting}, {$name}!";
}
```

Optional parameters are excluded from `inputSchema.required`.

### Input validation

Arguments are automatically validated against the generated JSON Schema before your handler is called. If a required argument is missing or has the wrong type, a `InvalidParams` error is returned to the client — your handler is never invoked.

```php
// If the AI omits 'to', the server responds with:
// {"error": {"code": -32602, "message": "Invalid params", "data": "Missing required argument: to"}}
```

### Rich tool results

By default a handler returns a plain `string`, which is sent as a single text content item. Return a `ToolResult` for richer responses.

```php
use Phpnl\Mcp\Tool\ToolResult;

// Single text item
return ToolResult::text('Here is the summary: ...');

// Image (base64-encoded)
return ToolResult::image(base64_encode($pngBytes), 'image/png');

// Embedded resource
return ToolResult::resource('file://report.json', $json, 'application/json');

// Multiple items chained together
return ToolResult::text('Monthly revenue: €12,400')
    ->withImage(base64_encode($chartPng), 'image/png')
    ->withText('Data as of 2024-01-01');
```

`ToolResult` is immutable. Every `with*()` call appends an item and returns a new instance.

| Factory / method | Content type | Purpose |
|---|---|---|
| `ToolResult::text(string $text)` | `text` | Plain or markdown text |
| `ToolResult::image(string $data, string $mimeType)` | `image` | Base64-encoded image |
| `ToolResult::resource(string $uri, string $text, string $mimeType)` | `resource` | Embedded resource |
| `->withText(string $text)` | `text` | Append text item |
| `->withImage(string $data, string $mimeType)` | `image` | Append image item |
| `->withResource(string $uri, string $text, string $mimeType)` | `resource` | Append resource item |

### Progress notifications

For long-running tools, inject a `ProgressReporter` parameter. The SDK injects it automatically and it does **not** appear in the tool's JSON Schema.

```php
use Phpnl\Mcp\Tool\ProgressReporter;

McpServer::make()
    ->tool(
        name: 'import_csv',
        description: 'Import a CSV file into the database',
        handler: function (
            string $path,
            ProgressReporter $progress,
        ): string {
            $rows = array_map('str_getcsv', file($path));
            $total = count($rows);

            foreach ($rows as $i => $row) {
                insertRow($row);
                $progress->report($i + 1, $total); // (current, total)
            }

            return "Imported {$total} rows from {$path}.";
        },
    )
    ->serve();
```

The client sends a `progressToken` in `_meta` to opt in to progress updates:

```json
{
  "method": "tools/call",
  "params": {
    "name": "import_csv",
    "arguments": {"path": "/tmp/data.csv"},
    "_meta": {"progressToken": "import-42"}
  }
}
```

While the handler runs, the server sends out-of-band notifications:

```json
{"jsonrpc": "2.0", "method": "notifications/progress", "params": {"progressToken": "import-42", "progress": 150, "total": 1000}}
```

When the client sends no `progressToken`, all `$progress->report()` calls are silently ignored — the same handler works for both streaming and non-streaming clients without any code changes.

---

## Resources

Resources expose read-only data that the AI can retrieve and reason about.

```php
McpServer::make()
    ->resource(
        uri: 'file://app-config',
        name: 'Application Config',
        mimeType: 'application/json',
        handler: function (): string {
            return file_get_contents(__DIR__ . '/config.json');
        },
    )
    ->resource(
        uri: 'db://schema',
        name: 'Database Schema',
        mimeType: 'text/plain',
        handler: function (): string {
            return generateSchemaDescription(); // your own code
        },
    )
    ->serve();
```

Resources appear in `resources/list` and are fetched with `resources/read`. The `capabilities.resources` key is only advertised in the `initialize` response when at least one resource is registered.

---

## Prompts

Prompts are reusable templates the AI can retrieve and populate.

```php
McpServer::make()
    ->prompt(
        name: 'code_review',
        description: 'Review a piece of code for bugs and style issues',
        handler: function (array $args): string {
            $language = $args['language'] ?? 'PHP';
            $code = $args['code'] ?? '';
            return "Please review the following {$language} code for correctness, "
                 . "potential bugs, and style:\n\n```{$language}\n{$code}\n```";
        },
    )
    ->serve();
```

Prompts appear in `prompts/list` and are fetched with `prompts/get`. Like resources, `capabilities.prompts` is only advertised when at least one prompt is registered.

---

## Middleware

Register middleware to wrap every tool invocation — for logging, authentication, rate limiting, caching, and more.

```php
McpServer::make()
    ->middleware(function (string $name, array $args, callable $next): mixed {
        // Before: log the call
        $start = microtime(true);
        error_log("→ tool:{$name} " . json_encode($args));

        $result = $next($name, $args);

        // After: log the duration
        $ms = round((microtime(true) - $start) * 1000);
        error_log("← tool:{$name} {$ms}ms");

        return $result;
    })
    ->tool('ping', 'Returns pong', fn (): string => 'pong')
    ->serve();
```

**Signature:** `function(string $name, array $arguments, callable $next): mixed`

Multiple middleware are executed in registration order — the first registered is the outermost wrapper. Each middleware must either call `$next($name, $args)` to continue the chain, or return a value directly to short-circuit the remaining middleware and the handler.

```php
// Authentication middleware
->middleware(function (string $name, array $args, callable $next): mixed {
    if (! isAuthenticated()) {
        throw new \RuntimeException('Unauthorized');
    }
    return $next($name, $args);
})

// Rate-limiting middleware
->middleware(function (string $name, array $args, callable $next): mixed {
    if (isRateLimited($name)) {
        throw new \RuntimeException('Rate limit exceeded');
    }
    return $next($name, $args);
})
```

---

## Exception handling

All MCP-specific errors are thrown as typed exceptions that extend `McpException` (which itself extends `\RuntimeException`). The JSON-RPC error code is available via `getErrorCode()`.

| Exception class | Thrown when | Error code |
|---|---|---|
| `ToolNotFoundException` | Tool name not registered | `ToolNotFound` (−32601) |
| `InvalidToolArgumentsException` | Required argument missing or wrong type | `InvalidParams` (−32602) |
| `ResourceNotFoundException` | Resource URI not registered | `ResourceNotFound` (−32002) |
| `PromptNotFoundException` | Prompt name not registered | `PromptNotFound` (−32003) |

Catching exceptions from the registries directly (e.g. in tests or CLI tooling):

```php
use Phpnl\Mcp\Exception\McpException;
use Phpnl\Mcp\Exception\ToolNotFoundException;

try {
    $result = $toolRegistry->call('missing', []);
} catch (ToolNotFoundException $e) {
    echo "No such tool: " . $e->getMessage();
} catch (McpException $e) {
    // Any other MCP error
    echo "MCP error " . $e->getErrorCode()->value . ": " . $e->getMessage();
}
```

The `JsonRpcHandler` catches all `McpException` subclasses automatically and converts them into well-formed JSON-RPC error responses. Unexpected `\Throwable` exceptions are caught and returned as `InternalError` (−32603).

---

## Transports

### STDIN/STDOUT (default)

The default transport reads JSON-RPC messages from `STDIN` and writes responses to `STDOUT`. This is the standard for Claude Desktop and other local CLI-based MCP clients.

```php
McpServer::make()     // StdioTransport is used automatically
    ->tool(...)
    ->serve();
```

### HTTP + SSE

Use `HttpSseTransport` for web-based clients, browser integrations, or remote deployments.

```php
use Phpnl\Mcp\Transport\HttpSseTransport;

$transport = new HttpSseTransport(port: 8080);

McpServer::make($transport)
    ->tool('hello', 'Returns a greeting', fn (): string => 'Hello from PHP!')
    ->serve();
```

```bash
php server.php
# MCP server now listening on http://localhost:8080
```

The transport exposes two endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/sse` | `GET` | Client connects and receives a Server-Sent Events stream. The first event is an `endpoint` event with the message URL. |
| `/message` | `POST` | Client sends JSON-RPC requests here. Responses arrive via the SSE stream. |

#### Configuration

```php
new HttpSseTransport(
    host: '0.0.0.0',           // Bind address (default: 0.0.0.0)
    port: 8080,                 // TCP port (default: 8080)
    ssePath: '/sse',            // SSE endpoint path (default: /sse)
    messagePath: '/message',    // POST endpoint path (default: /message)
    baseUrl: null,              // Public URL override — set when behind a reverse proxy
                                // e.g. 'https://api.example.com'
);
```

#### CORS

All responses include `Access-Control-Allow-Origin: *`. CORS preflight (`OPTIONS`) requests are handled automatically so browser-based clients work without additional configuration.

#### Reverse proxy

When running behind nginx or another proxy, set `baseUrl` so clients receive the correct public endpoint URL:

```php
$transport = new HttpSseTransport(
    host: '127.0.0.1',
    port: 9000,
    baseUrl: 'https://mcp.example.com',
);
```

---

## Developer CLI

The `phpnl` binary provides tooling for inspecting and debugging MCP servers during development.

```bash
# List all registered tools and their schemas
./vendor/bin/phpnl inspect server.php

# Stream full JSON-RPC traffic in real time (tools, resources, prompts)
./vendor/bin/phpnl debug server.php

# Call a specific tool and print the result
./vendor/bin/phpnl call server.php get_user --id=42
./vendor/bin/phpnl call server.php send_email --to=alice@example.com --subject=Hello --body=Hi

# Read a resource
./vendor/bin/phpnl read server.php file://app-config

# Retrieve a prompt
./vendor/bin/phpnl prompt server.php code_review --language=PHP --code="echo 'hi';"
```

Argument type casting follows the tool's JSON Schema:

| PHP type hint | CLI argument form | Example |
|---|---|---|
| `string` | `--name=value` | `--to=alice@example.com` |
| `int` | `--name=N` | `--id=42` |
| `float` | `--name=N.N` | `--price=9.99` |
| `bool` | `--name=true\|false` | `--active=true` |

---

## API reference

### `McpServer`

| Method | Description |
|--------|-------------|
| `McpServer::make(?TransportInterface $transport)` | Create a new server (static factory) |
| `->tool(string $name, string $description, Closure $handler): self` | Register a tool |
| `->resource(string $uri, string $name, string $mimeType, Closure $handler): self` | Register a resource |
| `->prompt(string $name, string $description, Closure $handler): self` | Register a prompt |
| `->middleware(Closure $fn): self` | Add a tool middleware |
| `->serve(): void` | Start the server loop |

### `ToolResult`

| Method | Description |
|--------|-------------|
| `ToolResult::text(string $text)` | Create result with a single text item |
| `ToolResult::image(string $data, string $mimeType)` | Create result with a single image item |
| `ToolResult::resource(string $uri, string $text, string $mimeType)` | Create result with a single resource item |
| `->withText(string $text)` | Append text item, return new instance |
| `->withImage(string $data, string $mimeType)` | Append image item, return new instance |
| `->withResource(string $uri, string $text, string $mimeType)` | Append resource item, return new instance |
| `->toContent()` | Return the raw MCP content array |

### `ProgressReporter`

| Method | Description |
|--------|-------------|
| `->report(int\|float $progress, int\|float\|null $total = null)` | Send a progress notification. No-op when no `progressToken` was provided by the client. |

### `#[Description]`

Apply to tool handler parameters to add a human-readable description to the JSON Schema:

```php
function (#[Description('The user ID')] int $id): string { ... }
```

### Supported MCP methods

| Method | Supported |
|--------|-----------|
| `initialize` | ✅ |
| `notifications/initialized` | ✅ |
| `ping` | ✅ |
| `tools/list` | ✅ |
| `tools/call` | ✅ |
| `resources/list` | ✅ |
| `resources/read` | ✅ |
| `prompts/list` | ✅ |
| `prompts/get` | ✅ |
| `notifications/progress` | ✅ (server → client) |

---

## Requirements

- PHP **8.3** or higher
- Zero runtime dependencies

---

## Testing

```bash
composer install
composer test    # PHPUnit (unit + integration)
composer stan    # PHPStan level 8
composer lint    # PHP CS Fixer (dry-run)
```

To auto-fix code style:

```bash
vendor/bin/php-cs-fixer fix
```

---

## License

MIT — made with ❤️ by [php.nl](https://php.nl)
