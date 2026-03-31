<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Resource;

use Phpnl\Mcp\Protocol\ErrorCode;
use Phpnl\Mcp\Resource\ResourceRegistry;
use Phpnl\Mcp\Tests\TestCase;

final class ResourceRegistryTest extends TestCase
{
    private ResourceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ResourceRegistry();
    }

    public function testIsEmptyByDefault(): void
    {
        $this->assertTrue($this->registry->isEmpty());
        $this->assertEmpty($this->registry->all());
    }

    public function testRegisterAndListResource(): void
    {
        $this->registry->register('file://readme', 'README', 'text/plain', fn () => 'Hello');

        $this->assertFalse($this->registry->isEmpty());
        $resources = $this->registry->all();
        $this->assertCount(1, $resources);
        $this->assertSame('file://readme', $resources[0]['uri']);
        $this->assertSame('README', $resources[0]['name']);
        $this->assertSame('text/plain', $resources[0]['mimeType']);
    }

    public function testReadCallsHandlerAndReturnsContent(): void
    {
        $this->registry->register('file://readme', 'README', 'text/plain', fn () => 'Hello World');

        $this->assertSame('Hello World', $this->registry->read('file://readme'));
    }

    public function testHasReturnsTrueForRegisteredUri(): void
    {
        $this->registry->register('file://readme', 'README', 'text/plain', fn () => '');

        $this->assertTrue($this->registry->has('file://readme'));
        $this->assertFalse($this->registry->has('file://other'));
    }

    public function testReadThrowsForUnknownUri(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ResourceNotFound->value);

        $this->registry->read('file://missing');
    }
}
