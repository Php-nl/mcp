<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Tool;

use Phpnl\Mcp\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

final class ToolResultTest extends TestCase
{
    // -------------------------------------------------------------------------
    // text()
    // -------------------------------------------------------------------------

    public function testTextFactoryProducesSingleTextItem(): void
    {
        $result = ToolResult::text('Hello world');

        $this->assertSame([
            ['type' => 'text', 'text' => 'Hello world'],
        ], $result->toContent());
    }

    // -------------------------------------------------------------------------
    // image()
    // -------------------------------------------------------------------------

    public function testImageFactoryProducesSingleImageItem(): void
    {
        $result = ToolResult::image('abc123==', 'image/png');

        $this->assertSame([
            ['type' => 'image', 'data' => 'abc123==', 'mimeType' => 'image/png'],
        ], $result->toContent());
    }

    // -------------------------------------------------------------------------
    // resource()
    // -------------------------------------------------------------------------

    public function testResourceFactoryProducesSingleResourceItem(): void
    {
        $result = ToolResult::resource('file://config.json', '{"key":"val"}', 'application/json');

        $this->assertSame([
            [
                'type' => 'resource',
                'resource' => [
                    'uri' => 'file://config.json',
                    'text' => '{"key":"val"}',
                    'mimeType' => 'application/json',
                ],
            ],
        ], $result->toContent());
    }

    // -------------------------------------------------------------------------
    // with*() — chaining
    // -------------------------------------------------------------------------

    public function testWithTextAppendsTextItem(): void
    {
        $result = ToolResult::text('First')->withText('Second');

        $content = $result->toContent();

        $this->assertCount(2, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('First', $content[0]['text']);
        $this->assertSame('text', $content[1]['type']);
        $this->assertSame('Second', $content[1]['text']);
    }

    public function testWithImageAppendsImageItem(): void
    {
        $result = ToolResult::text('Here is the image:')->withImage('data==', 'image/jpeg');

        $content = $result->toContent();

        $this->assertCount(2, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('data==', $content[1]['data']);
        $this->assertSame('image/jpeg', $content[1]['mimeType']);
    }

    public function testWithResourceAppendsResourceItem(): void
    {
        $result = ToolResult::text('Config:')->withResource('file://app.json', '{}', 'application/json');

        $content = $result->toContent();

        $this->assertCount(2, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('resource', $content[1]['type']);
        $this->assertSame('file://app.json', $content[1]['resource']['uri']);
    }

    public function testChainingMultipleItemsPreservesOrder(): void
    {
        $result = ToolResult::text('A')
            ->withImage('img==', 'image/png')
            ->withText('B')
            ->withResource('file://x', 'x', 'text/plain');

        $content = $result->toContent();

        $this->assertCount(4, $content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('text', $content[2]['type']);
        $this->assertSame('resource', $content[3]['type']);
    }

    // -------------------------------------------------------------------------
    // Immutability
    // -------------------------------------------------------------------------

    public function testWithTextReturnsNewInstance(): void
    {
        $original = ToolResult::text('Hello');
        $extended = $original->withText('World');

        $this->assertNotSame($original, $extended);
        $this->assertCount(1, $original->toContent());
        $this->assertCount(2, $extended->toContent());
    }

    public function testWithImageReturnsNewInstance(): void
    {
        $original = ToolResult::text('Hello');
        $extended = $original->withImage('data==', 'image/png');

        $this->assertNotSame($original, $extended);
        $this->assertCount(1, $original->toContent());
        $this->assertCount(2, $extended->toContent());
    }

    public function testWithResourceReturnsNewInstance(): void
    {
        $original = ToolResult::text('Hello');
        $extended = $original->withResource('file://x', 'x', 'text/plain');

        $this->assertNotSame($original, $extended);
        $this->assertCount(1, $original->toContent());
        $this->assertCount(2, $extended->toContent());
    }

    // -------------------------------------------------------------------------
    // toContent() structure
    // -------------------------------------------------------------------------

    public function testToContentReturnsListOfArrays(): void
    {
        $content = ToolResult::text('test')->toContent();

        $this->assertIsArray($content);
        $this->assertArrayHasKey(0, $content);
        $this->assertIsArray($content[0]);
    }
}
