<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;

final class ReadCommand
{
    public function execute(string $serverScript, string $uri): int
    {
        $server = new ServerProcess($serverScript);
        $server->start();
        $server->handshake();

        $server->send([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'resources/read',
            'params' => ['uri' => $uri],
        ]);

        $response = $server->receive();
        $server->stop();

        if (isset($response['error'])) {
            echo "\033[31mError:\033[0m " . $response['error']['message'] . "\n";

            return 1;
        }

        $text = $response['result']['contents'][0]['text'] ?? '';

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo $text . "\n";
        }

        return 0;
    }
}
