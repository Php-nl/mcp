<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\ServerProcess;
use Phpnl\Mcp\Tests\TestCase;

final class ServerProcessTest extends TestCase
{
    public function testStartSendAndReceive(): void
    {
        $server = new ServerProcess(self::exampleServerPath());
        $server->start();

        $server->send([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2024-11-05', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ]);

        $response = $server->receive();
        $server->stop();

        $this->assertIsArray($response);
        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
    }

    public function testReceiveReturnsNullOnTimeout(): void
    {
        $server = new ServerProcess(self::fixturePath('slow-server.php'));
        $server->start();

        $result = $server->receive(0.1);
        $server->stop();

        $this->assertNull($result);
    }

    public function testReceiveSkipsNonJsonLinesAndReturnsNullOnEof(): void
    {
        // noisy-server.php outputs a non-JSON line then exits immediately.
        // receive() must skip the non-JSON line, detect EOF (fgets === false), and return null.
        $server = new ServerProcess(self::fixturePath('noisy-server.php'));
        $server->start();

        $result = $server->receive(2.0);
        $server->stop();

        $this->assertNull($result);
    }
}
