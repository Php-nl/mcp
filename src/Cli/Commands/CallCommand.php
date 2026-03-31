<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;

final class CallCommand
{
    /**
     * @param array<int, string> $rawArgs
     */
    public function execute(string $serverScript, string $toolName, array $rawArgs): int
    {
        $arguments = $this->parseArgs($rawArgs);

        $server = new ServerProcess($serverScript);
        $server->start();
        $server->handshake();

        $server->send([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => $toolName, 'arguments' => $arguments],
        ]);

        $response = $server->receive();
        $server->stop();

        if (isset($response['error'])) {
            echo "\033[31mError:\033[0m " . $response['error']['message'] . "\n";

            return 1;
        }

        $text = $response['result']['content'][0]['text'] ?? '';

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo $text . "\n";
        }

        return 0;
    }

    /**
     * @param array<int, string> $args
     * @return array<string, mixed>
     */
    private function parseArgs(array $args): array
    {
        $result = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$key, $value] = explode('=', ltrim($arg, '-'), 2);
                $result[$key] = $this->castValue($value);
            }
        }

        return $result;
    }

    private function castValue(string $value): string|int|float|bool
    {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}
