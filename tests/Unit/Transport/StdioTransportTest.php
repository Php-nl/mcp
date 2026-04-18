<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Transport;

use Phpnl\Mcp\Tests\TestCase;
use Phpnl\Mcp\Transport\StdioTransport;
use Phpnl\Mcp\Transport\TransportInterface;

final class StdioTransportTest extends TestCase
{
    public function testImplementsTransportInterface(): void
    {
        $this->assertInstanceOf(TransportInterface::class, new StdioTransport());
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StdioTransport::class, new StdioTransport());
    }

    public function testReadReturnsLineFromStream(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "hello world\n");
        rewind($stdin);

        $transport = new StdioTransport(stdin: $stdin);

        $this->assertSame('hello world', $transport->read());
    }

    public function testReadTrimsWhitespaceAndNewlines(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "  trimmed line  \n");
        rewind($stdin);

        $transport = new StdioTransport(stdin: $stdin);

        $this->assertSame('trimmed line', $transport->read());
    }

    public function testReadReturnsNullOnEof(): void
    {
        $stdin = fopen('php://memory', 'r');

        $transport = new StdioTransport(stdin: $stdin);

        $this->assertNull($transport->read());
    }

    public function testReadReturnsMultipleLinesSequentially(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "first\nsecond\nthird\n");
        rewind($stdin);

        $transport = new StdioTransport(stdin: $stdin);

        $this->assertSame('first', $transport->read());
        $this->assertSame('second', $transport->read());
        $this->assertSame('third', $transport->read());
        $this->assertNull($transport->read());
    }

    public function testReadReturnsEmptyStringForBlankLine(): void
    {
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "\n");
        rewind($stdin);

        $transport = new StdioTransport(stdin: $stdin);

        $this->assertSame('', $transport->read());
    }

    public function testWriteAppendsNewline(): void
    {
        $stdout = fopen('php://memory', 'r+');

        $transport = new StdioTransport(stdout: $stdout);
        $transport->write('hello');

        rewind($stdout);
        $this->assertSame("hello\n", stream_get_contents($stdout));
    }

    public function testWriteMultipleMessages(): void
    {
        $stdout = fopen('php://memory', 'r+');

        $transport = new StdioTransport(stdout: $stdout);
        $transport->write('first');
        $transport->write('second');

        rewind($stdout);
        $this->assertSame("first\nsecond\n", stream_get_contents($stdout));
    }

    public function testWriteEmptyString(): void
    {
        $stdout = fopen('php://memory', 'r+');

        $transport = new StdioTransport(stdout: $stdout);
        $transport->write('');

        rewind($stdout);
        $this->assertSame("\n", stream_get_contents($stdout));
    }

    public function testWriteJsonMessage(): void
    {
        $stdout = fopen('php://memory', 'r+');

        $transport = new StdioTransport(stdout: $stdout);
        $json = '{"jsonrpc":"2.0","id":1,"result":{}}';
        $transport->write($json);

        rewind($stdout);
        $this->assertSame($json . "\n", stream_get_contents($stdout));
    }
}
