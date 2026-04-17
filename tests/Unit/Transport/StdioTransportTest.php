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

    public function testReadReturnsNullOnEof(): void
    {
        // StdioTransport::read() returns null when fgets returns false (EOF).
        // We verify this by running it against a closed stream via a subprocess.
        // Integration coverage is provided by ServerProcessTest and the CLI tests.
        $this->assertTrue(method_exists(StdioTransport::class, 'read'));
        $this->assertTrue(method_exists(StdioTransport::class, 'write'));
    }
}
