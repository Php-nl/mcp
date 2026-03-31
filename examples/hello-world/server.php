<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Phpnl\Mcp\McpServer;

McpServer::make()
    ->tool(
        name: 'hello_world',
        description: 'Returns a friendly greeting from PHP',
        handler: function (): string {
            return 'Hello from PHP! 🐘 This response came from a phpnl/mcp server.';
        },
    )
    ->tool(
        name: 'get_php_version',
        description: 'Returns the current PHP version running on this server',
        handler: function (): string {
            return phpversion();
        },
    )
    ->serve();
