<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Transport;

/**
 * HTTP + Server-Sent Events (SSE) transport for MCP.
 *
 * Starts a lightweight HTTP server on the given host and port.
 * Two endpoints are exposed:
 *
 *   GET  {ssePath}     — The client connects here and receives a stream of
 *                        Server-Sent Events. The first event is an "endpoint"
 *                        event containing the POST URL the client must use.
 *
 *   POST {messagePath} — The client sends JSON-RPC messages here. The server
 *                        processes them and streams responses back via SSE.
 *
 * Usage:
 *
 *   $transport = new HttpSseTransport(port: 8080);
 *
 *   McpServer::make($transport)
 *       ->tool('ping', 'Returns pong', fn () => 'pong')
 *       ->serve();
 *
 * @phpstan-type SocketResource resource
 */
final class HttpSseTransport implements TransportInterface
{
    /** @var resource|null */
    private $serverSocket = null;

    /** @var resource|null */
    private $sseSocket = null;

    /** @var list<string> */
    private array $inbox = [];

    public function __construct(
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 8080,
        private readonly string $ssePath = '/sse',
        private readonly string $messagePath = '/message',
        /**
         * Override the base URL reported in the SSE "endpoint" event.
         * Useful when the server sits behind a reverse proxy.
         * Defaults to http://<host>:<port> (replacing 0.0.0.0 with 127.0.0.1).
         */
        private readonly ?string $baseUrl = null,
    ) {
        $this->boot();
    }

    public function read(): ?string
    {
        if ($this->serverSocket === null) {
            $this->boot();
        }

        while (empty($this->inbox)) {
            /** @var list<resource> $read */
            $read = [$this->serverSocket]; // @phpstan-ignore-line

            if ($this->sseSocket !== null) {
                $read[] = $this->sseSocket;
            }

            $write = null;
            $except = null;

            /** @var int|false $changed */
            $changed = stream_select($read, $write, $except, 0, 100_000);

            if ($changed === false) {
                return null;
            }

            foreach ($read as $socket) {
                if ($socket === $this->serverSocket) {
                    $client = @stream_socket_accept($this->serverSocket, 0); // @phpstan-ignore-line

                    if ($client !== false) {
                        $this->dispatch($client);
                    }
                } elseif ($socket === $this->sseSocket) {
                    $peek = @fread($socket, 1);

                    if ($peek === false || $peek === '') {
                        @fclose($socket);
                        $this->sseSocket = null;
                    }
                }
            }
        }

        return array_shift($this->inbox);
    }

    public function write(string $message): void
    {
        if ($this->sseSocket === null) {
            return;
        }

        $result = @fwrite($this->sseSocket, "data: {$message}\n\n");

        if ($result === false) {
            @fclose($this->sseSocket);
            $this->sseSocket = null;

            return;
        }

        fflush($this->sseSocket);
    }

    public function __destruct()
    {
        if ($this->sseSocket !== null) {
            @fclose($this->sseSocket);
        }

        if ($this->serverSocket !== null) {
            @fclose($this->serverSocket);
        }
    }

    private function boot(): void
    {
        $context = stream_context_create([
            'socket' => ['so_reuseaddr' => true],
        ]);

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );

        if ($socket === false) {
            throw new \RuntimeException(
                "HttpSseTransport: failed to bind on {$this->host}:{$this->port} — {$errstr} (errno {$errno})",
            );
        }

        stream_set_blocking($socket, false);
        $this->serverSocket = $socket;
    }

    /**
     * @param resource $socket
     */
    private function dispatch(mixed $socket): void
    {
        stream_set_timeout($socket, 5);

        $requestLine = fgets($socket);

        if ($requestLine === false) {
            fclose($socket);

            return;
        }

        preg_match('/^(\w+)\s+(\S+)\s+HTTP/i', $requestLine, $m);
        $method = strtoupper($m[1] ?? '');
        $path = (string) (parse_url($m[2] ?? '/', PHP_URL_PATH) ?: '/');

        // Consume remaining headers
        $contentLength = 0;

        while (($line = fgets($socket)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                break;
            }

            if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $hm)) {
                $contentLength = (int) $hm[1];
            }
        }

        if ($method === 'OPTIONS') {
            $this->sendResponse($socket, 204, '');
            fclose($socket);

            return;
        }

        if ($method === 'GET' && $path === $this->ssePath) {
            $this->openSse($socket);

            return;
        }

        if ($method === 'POST' && $path === $this->messagePath) {
            $body = '';

            if ($contentLength > 0) {
                $body = (string) fread($socket, $contentLength);
            }

            $this->sendResponse($socket, 202, '');
            fclose($socket);

            $trimmed = trim($body);

            if ($trimmed !== '') {
                $this->inbox[] = $trimmed;
            }

            return;
        }

        $this->sendResponse($socket, 404, 'Not Found');
        fclose($socket);
    }

    /**
     * @param resource $socket
     */
    private function openSse(mixed $socket): void
    {
        if ($this->sseSocket !== null) {
            @fclose($this->sseSocket);
            $this->sseSocket = null;
        }

        $host = $this->host === '0.0.0.0' ? '127.0.0.1' : $this->host;
        $base = $this->baseUrl ?? "http://{$host}:{$this->port}";
        $messageUrl = $base . $this->messagePath;

        $headers = implode("\r\n", [
            'HTTP/1.1 200 OK',
            'Content-Type: text/event-stream',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Access-Control-Allow-Origin: *',
            'X-Accel-Buffering: no',
            '',
            '',
        ]);

        fwrite($socket, $headers);
        fwrite($socket, "event: endpoint\ndata: {$messageUrl}\n\n");
        fflush($socket);

        stream_set_blocking($socket, false);
        $this->sseSocket = $socket;
    }

    /**
     * @param resource $socket
     */
    private function sendResponse(mixed $socket, int $status, string $body): void
    {
        $reason = match ($status) {
            202 => 'Accepted',
            204 => 'No Content',
            404 => 'Not Found',
            default => '',
        };

        $headers = implode("\r\n", [
            "HTTP/1.1 {$status} {$reason}",
            'Access-Control-Allow-Origin: *',
            'Access-Control-Allow-Methods: GET, POST, OPTIONS',
            'Access-Control-Allow-Headers: Content-Type',
            'Content-Length: ' . strlen($body),
            '',
            '',
        ]);

        fwrite($socket, $headers . $body);
        fflush($socket);
    }
}
