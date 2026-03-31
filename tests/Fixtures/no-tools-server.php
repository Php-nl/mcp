<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Phpnl\Mcp\McpServer;

McpServer::make()->serve();
