<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Phpnl\Mcp\McpServer;

McpServer::make()
    ->tool(
        name: 'hello_world',
        description: 'Returns a friendly greeting',
        handler: function (): string {
            return 'Hello from PHP! 🐘';
        },
    )
    ->resource(
        uri: 'file://config',
        name: 'App Config',
        mimeType: 'application/json',
        handler: function (): string {
            return json_encode(['env' => 'production', 'version' => '1.0.0']);
        },
    )
    ->prompt(
        name: 'summarize',
        description: 'Summarize a topic',
        handler: function (array $args): string {
            $topic = $args['topic'] ?? 'the given subject';

            return "Please provide a concise summary about: {$topic}";
        },
    )
    ->serve();
