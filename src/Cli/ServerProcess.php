<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli;

final class ServerProcess
{
    /** @var resource|false */
    private mixed $process = false;
    /** @var array<int, resource> */
    private array $pipes = [];

    public function __construct(private readonly string $serverScript) {}

    public function start(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open(
            ['php', $this->serverScript],
            $descriptors,
            $this->pipes,
        );

        if ($this->process === false) {
            throw new \RuntimeException("Failed to start server process: {$this->serverScript}");
        }

        stream_set_blocking($this->pipes[1], false);
    }

    /**
     * @param array<string, mixed> $message
     */
    public function send(array $message): void
    {
        fwrite($this->pipes[0], json_encode($message) . "\n");
        fflush($this->pipes[0]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function receive(float $timeout = 2.0): ?array
    {
        $start = microtime(true);

        while (microtime(true) - $start < $timeout) {
            $line = fgets($this->pipes[1]);

            if ($line !== false && trim($line) !== '') {
                return json_decode(trim($line), true);
            }

            usleep(10_000);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function handshake(): array
    {
        $this->send([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpnl-cli', 'version' => '1.0.0'],
            ],
        ]);

        $response = $this->receive();

        $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        return $response['result']['serverInfo'] ?? ['name' => 'unknown', 'version' => '?'];
    }

    public function stop(): void
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        if ($this->process !== false) {
            proc_terminate($this->process);
        }
    }
}
