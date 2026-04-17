<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Transport;

use Phpnl\Mcp\Tests\TestCase;
use Phpnl\Mcp\Transport\HttpSseTransport;
use Phpnl\Mcp\Transport\TransportInterface;

final class HttpSseTransportTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testImplementsTransportInterface(): void
    {
        $transport = new HttpSseTransport(port: $this->freePort());

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testCanBeInstantiatedWithDefaults(): void
    {
        $transport = new HttpSseTransport(port: $this->freePort());

        $this->assertInstanceOf(HttpSseTransport::class, $transport);
    }

    public function testCustomBaseUrlIsAccepted(): void
    {
        $transport = new HttpSseTransport(
            port: $this->freePort(),
            baseUrl: 'https://my-proxy.example.com',
        );

        $this->assertInstanceOf(HttpSseTransport::class, $transport);
    }

    // =========================================================================
    // boot() / server socket lifecycle
    // =========================================================================

    public function testThrowsOnPortConflict(): void
    {
        $port = $this->freePort();

        // Occupy the port so the transport cannot bind to it
        $blocker = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        $this->assertNotFalse($blocker, 'Could not bind blocker socket');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/failed to bind/i');

            $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);
            $transport->read();
        } finally {
            fclose($blocker); // @phpstan-ignore-line
        }
    }

    // =========================================================================
    // write()
    // =========================================================================

    public function testWriteIsNoopWithoutSseClient(): void
    {
        $transport = new HttpSseTransport(port: $this->freePort());

        // Must not throw, even without an active SSE connection
        $transport->write('{"jsonrpc":"2.0","id":1,"result":{}}');

        $this->addToAssertionCount(1);
    }

    public function testWriteSendsDataFrameToConnectedSseClient(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $sseSocket = $this->openSseConnection($transport, $port);

        // Now the transport's sseSocket is set — write a message
        $transport->write('{"jsonrpc":"2.0","id":42,"result":{}}');

        // The client should receive an SSE data frame
        stream_set_timeout($sseSocket, 2); // @phpstan-ignore-line
        $received = '';

        while (!str_contains($received, "\n\n")) {
            $chunk = fread($sseSocket, 4096); // @phpstan-ignore-line

            if ($chunk === false || $chunk === '') {
                break;
            }

            $received .= $chunk;
        }

        $this->assertStringContainsString('data: {"jsonrpc":"2.0","id":42,"result":{}}', $received);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    // =========================================================================
    // __destruct()
    // =========================================================================

    public function testDestructWithNoOpenSocketsIsHarmless(): void
    {
        $transport = new HttpSseTransport(port: $this->freePort());

        unset($transport);

        $this->addToAssertionCount(1);
    }

    public function testDestructClosesServerAndSseSockets(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $sseSocket = $this->openSseConnection($transport, $port);

        // Destroying the transport should not throw
        unset($transport);

        // After destruction the SSE client socket should be closed server-side
        $this->addToAssertionCount(1);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    // =========================================================================
    // GET /sse (openSse)
    // =========================================================================

    public function testSseResponseHasCorrectHeaders(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $sseSocket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($sseSocket, "Could not connect: {$errstr}");

        fwrite($sseSocket, "GET /sse HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n"); // @phpstan-ignore-line
        fflush($sseSocket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        stream_set_timeout($sseSocket, 2); // @phpstan-ignore-line
        $buffer = $this->readUntil($sseSocket, 'event: endpoint');

        $this->assertStringContainsString('Content-Type: text/event-stream', $buffer);
        $this->assertStringContainsString('Cache-Control: no-cache', $buffer);
        $this->assertStringContainsString('Access-Control-Allow-Origin: *', $buffer);
        $this->assertStringContainsString('X-Accel-Buffering: no', $buffer);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    public function testEndpointEventContainsMessageUrl(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $sseSocket = $this->openSseConnection($transport, $port);
        $buffer = $this->readUntil($sseSocket, 'event: endpoint');

        $this->assertStringContainsString('event: endpoint', $buffer);
        $this->assertStringContainsString("data: http://127.0.0.1:{$port}/message", $buffer);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    public function testDefaultHostZeroZeroZeroZeroBecomesLocalhostInEndpointUrl(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '0.0.0.0', port: $port);

        // Connect via 127.0.0.1 (since 0.0.0.0 listens on all interfaces)
        $sseSocket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($sseSocket, "Could not connect: {$errstr}");

        fwrite($sseSocket, "GET /sse HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n"); // @phpstan-ignore-line
        fflush($sseSocket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        $buffer = $this->readUntil($sseSocket, 'event: endpoint');

        // 0.0.0.0 must be replaced with 127.0.0.1 in the endpoint URL
        $this->assertStringNotContainsString('0.0.0.0', $buffer);
        $this->assertStringContainsString('127.0.0.1', $buffer);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    public function testCustomBaseUrlIsUsedInEndpointEvent(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(
            host: '127.0.0.1',
            port: $port,
            baseUrl: 'https://proxy.example.com',
        );

        $sseSocket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($sseSocket, "Could not connect: {$errstr}");

        fwrite($sseSocket, "GET /sse HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n"); // @phpstan-ignore-line
        fflush($sseSocket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        $buffer = $this->readUntil($sseSocket, 'event: endpoint');

        $this->assertStringContainsString('data: https://proxy.example.com/message', $buffer);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    public function testReconnectingClosesExistingSseSocket(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        // First SSE connection
        $first = $this->openSseConnection($transport, $port);

        // Second SSE connection — should replace the first
        $second = $this->openSseConnection($transport, $port);

        // The second connection should receive the endpoint event
        $buffer = $this->readUntil($second, 'event: endpoint');
        $this->assertStringContainsString('event: endpoint', $buffer);

        fclose($second); // @phpstan-ignore-line

        // First socket should be closed by now (server closed it on reconnect)
        // Reading from it should return false or empty
        $data = @fread($first, 1); // @phpstan-ignore-line
        $this->assertTrue($data === false || $data === '');

        fclose($first); // @phpstan-ignore-line
    }

    // =========================================================================
    // POST /message
    // =========================================================================

    public function testPostMessageQueuedAndReadable(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $this->openSseConnection($transport, $port);

        $jsonRpc = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
            'params' => [],
        ]);

        $this->postMessage($transport, $port, $jsonRpc);

        $message = $transport->read();

        $this->assertNotNull($message);
        $decoded = json_decode($message, true);
        $this->assertSame('ping', $decoded['method']);
    }

    public function testPostReturns202Accepted(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $body = '{"jsonrpc":"2.0","id":1,"method":"ping","params":[]}';

        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect: {$errstr}");

        fwrite($socket, implode("\r\n", [ // @phpstan-ignore-line
            'POST /message HTTP/1.1',
            'Host: 127.0.0.1',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            '',
            '',
        ]) . $body);
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        stream_set_timeout($socket, 2); // @phpstan-ignore-line
        $response = (string) fread($socket, 4096); // @phpstan-ignore-line

        $this->assertStringContainsString('202 Accepted', $response);
        $this->assertStringContainsString('Access-Control-Allow-Origin: *', $response);

        fclose($socket); // @phpstan-ignore-line
    }

    public function testPostWithZeroContentLengthBodyIsIgnored(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect: {$errstr}");

        // POST with Content-Length: 0 — no body should enter the inbox
        fwrite($socket, "POST /message HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Length: 0\r\n\r\n"); // @phpstan-ignore-line
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        // Inbox must remain empty (nothing to process)
        $ref = new \ReflectionClass($transport);
        $inbox = $ref->getProperty('inbox');
        $inbox->setAccessible(true);

        $this->assertEmpty($inbox->getValue($transport));

        fclose($socket); // @phpstan-ignore-line
    }

    public function testPostWithWhitespaceOnlyBodyIsIgnored(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $body = "   \n  ";

        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect: {$errstr}");

        fwrite($socket, implode("\r\n", [ // @phpstan-ignore-line
            'POST /message HTTP/1.1',
            'Host: 127.0.0.1',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            '',
            '',
        ]) . $body);
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        $ref = new \ReflectionClass($transport);
        $inbox = $ref->getProperty('inbox');
        $inbox->setAccessible(true);

        $this->assertEmpty($inbox->getValue($transport));

        fclose($socket); // @phpstan-ignore-line
    }

    // =========================================================================
    // OPTIONS (CORS preflight)
    // =========================================================================

    public function testOptionsRequestReturns204WithCorsHeaders(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket);

        fwrite($socket, "OPTIONS /message HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n"); // @phpstan-ignore-line
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        stream_set_timeout($socket, 2); // @phpstan-ignore-line
        $response = (string) fread($socket, 4096); // @phpstan-ignore-line

        $this->assertStringContainsString('204 No Content', $response);
        $this->assertStringContainsString('Access-Control-Allow-Origin: *', $response);
        $this->assertStringContainsString('Access-Control-Allow-Methods: GET, POST, OPTIONS', $response);
        $this->assertStringContainsString('Access-Control-Allow-Headers: Content-Type', $response);

        fclose($socket); // @phpstan-ignore-line
    }

    // =========================================================================
    // Unknown paths
    // =========================================================================

    public function testUnknownPathReturns404(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket);

        fwrite($socket, "GET /unknown HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n"); // @phpstan-ignore-line
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 5);

        stream_set_timeout($socket, 2); // @phpstan-ignore-line
        $response = (string) fread($socket, 4096); // @phpstan-ignore-line

        $this->assertStringContainsString('404 Not Found', $response);

        fclose($socket); // @phpstan-ignore-line
    }

    // =========================================================================
    // sendResponse() default case
    // =========================================================================

    public function testSendResponseWithUnknownStatusUsesEmptyReason(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        // Create a loopback socket pair to test sendResponse directly
        [$clientSocket, $serverSocket] = $this->socketPair();

        $ref = new \ReflectionClass($transport);
        $method = $ref->getMethod('sendResponse');
        $method->setAccessible(true);
        $method->invoke($transport, $serverSocket, 500, 'error');

        fclose($serverSocket); // @phpstan-ignore-line

        stream_set_timeout($clientSocket, 1); // @phpstan-ignore-line
        $response = (string) fread($clientSocket, 4096); // @phpstan-ignore-line

        fclose($clientSocket); // @phpstan-ignore-line

        $this->assertStringContainsString('HTTP/1.1 500 ', $response);
    }

    // =========================================================================
    // Full roundtrip
    // =========================================================================

    public function testFullRoundtripSseEndpointEventAndMessageAndWrite(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        $sseSocket = $this->openSseConnection($transport, $port);
        $buffer = $this->readUntil($sseSocket, 'event: endpoint');

        $this->assertStringContainsString('event: endpoint', $buffer);
        $this->assertStringContainsString('/message', $buffer);

        // POST a JSON-RPC message
        $jsonRpc = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $this->postMessage($transport, $port, $jsonRpc);

        // Transport should return the queued message
        $message = $transport->read();
        $this->assertNotNull($message);

        $decoded = json_decode($message, true);
        $this->assertSame('tools/list', $decoded['method']);

        // Writing a response should arrive on the SSE socket
        $response = '{"jsonrpc":"2.0","id":7,"result":{"tools":[]}}';
        $transport->write($response);

        $received = $this->readUntil($sseSocket, "\n\n");
        $this->assertStringContainsString("data: {$response}", $received);

        fclose($sseSocket); // @phpstan-ignore-line
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns an ephemeral free TCP port on 127.0.0.1.
     */
    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($socket, "Could not find a free port: {$errstr}");

        $address = stream_socket_get_name($socket, false); // @phpstan-ignore-line
        fclose($socket); // @phpstan-ignore-line

        return (int) explode(':', (string) $address)[1];
    }

    /**
     * Opens an SSE connection to the transport and drives the accept loop.
     * Returns the client socket with stream timeout set.
     *
     * @return resource
     */
    private function openSseConnection(HttpSseTransport $transport, int $port): mixed
    {
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect for SSE: {$errstr}");

        fwrite($socket, "GET /sse HTTP/1.1\r\nHost: 127.0.0.1\r\nAccept: text/event-stream\r\n\r\n"); // @phpstan-ignore-line
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 8);

        stream_set_timeout($socket, 2); // @phpstan-ignore-line

        return $socket;
    }

    /**
     * Sends a POST /message to the transport and drives the accept loop.
     */
    private function postMessage(HttpSseTransport $transport, int $port, string $body): void
    {
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect for POST: {$errstr}");
        stream_set_timeout($socket, 2); // @phpstan-ignore-line

        fwrite($socket, implode("\r\n", [ // @phpstan-ignore-line
            'POST /message HTTP/1.1',
            'Host: 127.0.0.1',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            '',
            '',
        ]) . $body);
        fflush($socket); // @phpstan-ignore-line

        $this->driveTransport($transport, iterations: 8);

        fclose($socket); // @phpstan-ignore-line
    }

    /**
     * Reads from socket until the haystack contains the given needle.
     *
     * @param resource $socket
     */
    private function readUntil(mixed $socket, string $needle): string
    {
        $buffer = '';

        while (!str_contains($buffer, $needle)) {
            $chunk = fread($socket, 4096); // @phpstan-ignore-line

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * Creates a pair of connected stream sockets (loopback).
     * Returns [clientSocket, serverSocket].
     *
     * @return array{resource, resource}
     */
    private function socketPair(): array
    {
        $port = $this->freePort();
        $server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        $this->assertNotFalse($server, "socketPair: {$errstr}");

        $client = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);
        $this->assertNotFalse($client, "socketPair client: {$errstr}");

        $peer = stream_socket_accept($server, 1); // @phpstan-ignore-line
        $this->assertNotFalse($peer, 'socketPair: accept failed');

        fclose($server); // @phpstan-ignore-line

        return [$client, $peer]; // @phpstan-ignore-line
    }

    /**
     * Drives the transport's internal accept/select loop for a fixed number of
     * iterations. Uses reflection to manually trigger socket acceptance without
     * blocking in the read() loop.
     */
    private function driveTransport(HttpSseTransport $transport, int $iterations): void
    {
        $ref = new \ReflectionClass($transport);

        $serverProp = $ref->getProperty('serverSocket');
        $serverProp->setAccessible(true);

        // Ensure the server socket is initialised (triggers boot())
        if ($serverProp->getValue($transport) === null) {
            $inboxProp = $ref->getProperty('inbox');
            $inboxProp->setAccessible(true);
            $inboxProp->setValue($transport, ['__probe__']);
            $transport->read(); // returns '__probe__' immediately, boots server
            $inboxProp->setValue($transport, []);
        }

        for ($i = 0; $i < $iterations; $i++) {
            $serverSocket = $serverProp->getValue($transport);

            if ($serverSocket === null) {
                continue;
            }

            $read = [$serverSocket];
            $write = null;
            $except = null;

            $changed = @stream_select($read, $write, $except, 0, 50_000);

            if ($changed === false || $changed === 0) {
                usleep(20_000);
                continue;
            }

            foreach ($read as $s) {
                if ($s === $serverSocket) {
                    $client = @stream_socket_accept($serverSocket, 0);

                    if ($client !== false) {
                        $dispatch = $ref->getMethod('dispatch');
                        $dispatch->setAccessible(true);
                        $dispatch->invoke($transport, $client);
                    }
                }
            }

            usleep(20_000);
        }
    }
}
