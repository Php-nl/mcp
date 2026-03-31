<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Protocol;

use Phpnl\Mcp\Protocol\JsonRpcMessage;
use Phpnl\Mcp\Tests\TestCase;

final class JsonRpcMessageTest extends TestCase
{
    public function testFromArrayWithAllFields(): void
    {
        $message = JsonRpcMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'tools/list',
            'params' => ['key' => 'value'],
            'result' => ['data' => true],
            'error' => null,
        ]);

        $this->assertSame('2.0', $message->jsonrpc);
        $this->assertSame(42, $message->id);
        $this->assertSame('tools/list', $message->method);
        $this->assertSame(['key' => 'value'], $message->params);
    }

    public function testFromArrayUsesDefaults(): void
    {
        $message = JsonRpcMessage::fromArray([]);

        $this->assertSame('2.0', $message->jsonrpc);
        $this->assertNull($message->id);
        $this->assertNull($message->method);
        $this->assertNull($message->params);
        $this->assertNull($message->result);
        $this->assertNull($message->error);
    }

    public function testIsRequestReturnsTrueWhenMethodSet(): void
    {
        $message = JsonRpcMessage::fromArray(['method' => 'tools/list']);

        $this->assertTrue($message->isRequest());
    }

    public function testIsRequestReturnsFalseWhenMethodNull(): void
    {
        $message = JsonRpcMessage::fromArray([]);

        $this->assertFalse($message->isRequest());
    }

    public function testToArrayFiltersNullValues(): void
    {
        $message = JsonRpcMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => []],
        ]);

        $array = $message->toArray();

        $this->assertArrayHasKey('jsonrpc', $array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('result', $array);
        $this->assertArrayNotHasKey('error', $array);
    }

    public function testToArrayIncludesError(): void
    {
        $message = JsonRpcMessage::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
        ]);

        $array = $message->toArray();

        $this->assertArrayHasKey('error', $array);
        $this->assertArrayNotHasKey('result', $array);
    }
}
