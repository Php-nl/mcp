<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;

final class InspectCommand
{
    public function execute(string $serverScript): int
    {
        $server = new ServerProcess($serverScript);
        $server->start();

        $serverInfo = $server->handshake();

        $server->send([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ]);

        $toolsResponse = $server->receive();
        $tools = $toolsResponse['result']['tools'] ?? [];

        $server->stop();

        $this->printHeader($serverInfo);
        $this->printTools($tools);

        return 0;
    }

    /**
     * @param array<string, mixed> $serverInfo
     */
    private function printHeader(array $serverInfo): void
    {
        echo "\n\033[1mphpnl/mcp Server Inspector\033[0m\n";
        echo str_repeat('─', 42) . "\n";
        echo sprintf("Server:   \033[32m%s\033[0m v%s\n", $serverInfo['name'], $serverInfo['version']);
        echo "Protocol: 2024-11-05\n\n";
    }

    /**
     * @param list<array<string, mixed>> $tools
     */
    private function printTools(array $tools): void
    {
        if (empty($tools)) {
            echo "\033[33mNo tools registered.\033[0m\n\n";
            return;
        }

        echo sprintf("\033[1mTools (%d):\033[0m\n", count($tools));

        foreach ($tools as $tool) {
            $params = $this->describeParams($tool['inputSchema'] ?? []);
            echo sprintf("  \033[32m✔\033[0m %-22s %s\n", $tool['name'], $tool['description']);
            echo sprintf("    %sParams: %s\n\n", str_repeat(' ', 22), $params);
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function describeParams(array $schema): string
    {
        $properties = $schema['properties'] ?? [];

        if (empty($properties)) {
            return 'none';
        }

        $parts = [];

        foreach ($properties as $name => $def) {
            $type = is_array($def) ? (string) $def['type'] : 'string';
            $parts[] = "{$name} ({$type})";
        }

        return implode(', ', $parts);
    }
}
