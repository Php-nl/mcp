<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Transport\HttpSseTransport;

/**
 * HTTP + SSE example
 *
 * Start this server:
 *   php examples/http-sse/server.php
 *
 * Then configure your MCP client to connect via:
 *   http://localhost:8080/sse
 *
 * The client will receive an "endpoint" event pointing to:
 *   http://localhost:8080/message
 *
 * Optional: override the public base URL when running behind a proxy:
 *   $transport = new HttpSseTransport(baseUrl: 'https://my-server.example.com');
 */
$transport = new HttpSseTransport(
    host: '0.0.0.0',
    port: 8080,
    ssePath: '/sse',
    messagePath: '/message',
);

echo "MCP HTTP/SSE server listening on http://localhost:8080/sse" . PHP_EOL;

McpServer::make($transport)
    ->tool(
        name: 'hello_world',
        description: 'Returns a friendly greeting from PHP over HTTP/SSE',
        handler: function (): string {
            return 'Hello from PHP over HTTP + SSE! 🐘';
        },
    )
    ->tool(
        name: 'get_php_version',
        description: 'Returns the current PHP version running on this server',
        handler: function (): string {
            return phpversion();
        },
    )
    ->tool(
        name: 'get_server_time',
        description: 'Returns the current server time',
        handler: function (): string {
            return date('Y-m-d H:i:s T');
        },
    )
    ->serve();
