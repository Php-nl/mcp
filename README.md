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

## Requirements

- PHP 8.2+
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
