<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;

final class DebugCommand
{
    public function execute(string $serverScript): int
    {
        $server = new ServerProcess($serverScript);
        $server->start();

        echo "\n\033[1mphpnl/mcp Debug Session\033[0m\n";
        echo str_repeat('─', 42) . "\n";
        echo "Streaming \033[33m{$serverScript}\033[0m — Ctrl+C to stop\n\n";

        $serverInfo = $server->handshake();
        $this->printLine('← initialize', ['serverInfo' => $serverInfo], 'green');

        $this->sendAndPrint($server, 2, '→ tools/list', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ]);

        $this->sendAndPrint($server, 3, '→ resources/list', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'resources/list',
            'params' => new \stdClass(),
        ]);

        $this->sendAndPrint($server, 4, '→ prompts/list', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'prompts/list',
            'params' => new \stdClass(),
        ]);

        echo "\n\033[33mDebug session complete.\033[0m\n\n";

        $server->stop();

        return 0;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function sendAndPrint(ServerProcess $server, int $id, string $label, array $message): void
    {
        $this->printLine($label, $message, 'blue');
        $server->send($message);
        $response = $server->receive();
        $this->printLine('← result', $response, 'green');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function printLine(string $label, ?array $payload, string $color): void
    {
        $colors = ['blue' => '34', 'green' => '32', 'cyan' => '36'];
        $code = $colors[$color] ?? '37';
        $time = date('H:i:s');
        $json = $payload !== null ? (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $truncated = strlen($json) > 80 ? substr($json, 0, 77) . '...' : $json;

        echo sprintf(
            "[\033[90m%s\033[0m] \033[{$code}m%-28s\033[0m %s\n",
            $time,
            $label,
            $truncated,
        );
    }
}
