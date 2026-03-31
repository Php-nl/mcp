<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;

final class PromptCommand
{
    /**
     * @param array<int, string> $rawArgs
     */
    public function execute(string $serverScript, string $promptName, array $rawArgs): int
    {
        $arguments = $this->parseArgs($rawArgs);

        $server = new ServerProcess($serverScript);
        $server->start();
        $server->handshake();

        $server->send([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'prompts/get',
            'params' => ['name' => $promptName, 'arguments' => $arguments],
        ]);

        $response = $server->receive();
        $server->stop();

        if (isset($response['error'])) {
            echo "\033[31mError:\033[0m " . $response['error']['message'] . "\n";

            return 1;
        }

        $messages = $response['result']['messages'] ?? [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = $message['content']['text'] ?? '';
            echo sprintf("\033[1m[%s]\033[0m %s\n", strtoupper($role), $text);
        }

        return 0;
    }

    /**
     * @param array<int, string> $args
     * @return array<string, string>
     */
    private function parseArgs(array $args): array
    {
        $result = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$key, $value] = explode('=', ltrim($arg, '-'), 2);
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
