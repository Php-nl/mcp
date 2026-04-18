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
        usleep(20_000); // allow OS to deliver the write to the client receive buffer

        // The client should receive an SSE data frame.
        // Use readUntil so we don't stop early on the endpoint event's own "\n\n".
        stream_set_timeout($sseSocket, 2); // @phpstan-ignore-line
        $received = $this->readUntil($sseSocket, '"id":42');

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

        $this->driveTransport($transport, iterations: 8);

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

        $this->driveTransport($transport, iterations: 8);

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

        // First socket should be closed by now (server closed it on reconnect).
        // Drain any data that was buffered before the server closed its end
        // (SSE headers + endpoint event arrive before the close propagates).
        stream_set_blocking($first, false); // @phpstan-ignore-line
        $deadline = microtime(true) + 1.0;
        while (microtime(true) < $deadline) {
            $chunk = @fread($first, 4096); // @phpstan-ignore-line
            if ($chunk === false || $chunk === '') {
                break;
            }
        }
        stream_set_blocking($first, true); // @phpstan-ignore-line

        // Now a blocking read must return false or '' (EOF — server side is gone)
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

        $this->driveTransport($transport, iterations: 8);

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
    // SSE client disconnect detection
    // =========================================================================

    public function testReadDetectsSseClientDisconnectAndClearsSseSocket(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        // Create a socket pair and close our end to simulate an SSE client disconnecting
        [$clientEnd, $serverEnd] = $this->socketPair();
        fclose($clientEnd); // @phpstan-ignore-line
        usleep(10_000); // Allow EOF to propagate through the OS

        // Inject serverEnd as the active SSE socket
        $ref = new \ReflectionClass($transport);
        $sseProp = $ref->getProperty('sseSocket');
        $sseProp->setAccessible(true);
        $sseProp->setValue($transport, $serverEnd);

        // POST a message so that read() has something to return after the loop
        $body = '{"jsonrpc":"2.0","id":1,"method":"ping"}';
        $postSocket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($postSocket, "POST connect failed: {$errstr}");
        fwrite($postSocket, implode("\r\n", [ // @phpstan-ignore-line
            'POST /message HTTP/1.1',
            'Host: 127.0.0.1',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            '',
            '',
        ]) . $body);
        fflush($postSocket); // @phpstan-ignore-line
        usleep(5_000); // Let the POST data arrive in the server's receive buffer

        // read() should process both: the POST (fills inbox) and the SSE disconnect
        $message = $transport->read();

        $this->assertNotNull($message);
        $this->assertNull($sseProp->getValue($transport), 'sseSocket must be null after client disconnects');

        fclose($postSocket); // @phpstan-ignore-line
    }

    public function testWriteHandlesFwriteFailureGracefully(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        // Create a stream and immediately close it to produce a broken resource
        $stream = fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);
        fclose($stream); // @phpstan-ignore-line — now $stream is a closed/invalid resource

        // Inject the closed stream as the SSE socket
        $ref = new \ReflectionClass($transport);
        $sseProp = $ref->getProperty('sseSocket');
        $sseProp->setAccessible(true);
        $sseProp->setValue($transport, $stream);

        // write() uses @fwrite which returns false for a closed resource
        $transport->write('some message');

        // sseSocket must be null after the fwrite failure
        $this->assertNull($sseProp->getValue($transport));
    }

    public function testDispatchHandlesClientThatDisconnectsBeforeSendingRequest(): void
    {
        $port = $this->freePort();
        $transport = new HttpSseTransport(host: '127.0.0.1', port: $port);

        // Connect and immediately close without sending any HTTP data
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 2);
        $this->assertNotFalse($socket, "Could not connect: {$errstr}");
        fclose($socket); // @phpstan-ignore-line — triggers fgets() === false inside dispatch()

        // Drive the transport — dispatch() should handle fgets returning false without throwing
        $this->driveTransport($transport, iterations: 10);

        $this->addToAssertionCount(1);
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
     * Drives the transport's internal accept/select loop until the expected
     * number of connections have been dispatched, or the iteration budget runs out.
     *
     * Uses reflection to manually trigger socket acceptance without blocking in
     * the read() loop. Exits as soon as all expected connections are handled so
     * tests don't spend time in idle sleeps after the work is done.
     */
    private function driveTransport(HttpSseTransport $transport, int $iterations, int $connections = 1): void
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

        $dispatched = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $serverSocket = $serverProp->getValue($transport);

            if ($serverSocket === null) {
                usleep(2_000);
                continue;
            }

            $read = [$serverSocket];
            $write = null;
            $except = null;

            // Poll with a 50 ms window — reliable on loopback even under load.
            $changed = @stream_select($read, $write, $except, 0, 50_000);

            if ($changed === false || $changed === 0) {
                usleep(2_000);
                continue;
            }

            foreach ($read as $s) {
                if ($s === $serverSocket) {
                    $client = @stream_socket_accept($serverSocket, 0);

                    if ($client !== false) {
                        $dispatch = $ref->getMethod('dispatch');
                        $dispatch->setAccessible(true);
                        $dispatch->invoke($transport, $client);
                        $dispatched++;
                    }
                }
            }

            if ($dispatched >= $connections) {
                usleep(20_000); // allow OS to deliver written data to client receive buffer
                return; // all expected connections handled — exit immediately
            }
        }
    }
}
