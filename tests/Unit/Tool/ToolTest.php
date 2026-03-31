<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Tool;

use Phpnl\Mcp\Tests\TestCase;
use Phpnl\Mcp\Tool\Description;
use Phpnl\Mcp\Tool\Tool;

final class ToolTest extends TestCase
{
    public function testSchemaWithTypedParameters(): void
    {
        $tool = new Tool('add', 'Adds numbers', fn (int $a, string $b): string => $a . $b);

        $schema = $tool->schema();

        $this->assertSame('object', $schema['type']);
        $this->assertSame('integer', $schema['properties']['a']['type']);
        $this->assertSame('string', $schema['properties']['b']['type']);
        $this->assertSame(['a', 'b'], $schema['required']);
    }

    public function testSchemaWithNoParameters(): void
    {
        $tool = new Tool('ping', 'Returns pong', fn (): string => 'pong');

        $schema = $tool->schema();

        $this->assertSame('object', $schema['type']);
        $this->assertEmpty($schema['properties']);
        $this->assertEmpty($schema['required']);
    }

    public function testSchemaWithUntypedParameterFallsBackToString(): void
    {
        $tool = new Tool('echo', 'Echoes input', fn ($value): string => (string) $value);

        $schema = $tool->schema();

        $this->assertSame('string', $schema['properties']['value']['type']);
    }

    public function testSchemaMapsBoolToBoolean(): void
    {
        $tool = new Tool('flag', 'Takes a bool', fn (bool $active): string => (string) $active);

        $schema = $tool->schema();

        $this->assertSame('boolean', $schema['properties']['active']['type']);
    }

    public function testSchemaMapFloatToNumber(): void
    {
        $tool = new Tool('price', 'Takes a float', fn (float $amount): string => (string) $amount);

        $schema = $tool->schema();

        $this->assertSame('number', $schema['properties']['amount']['type']);
    }

    public function testSchemaExcludesOptionalParametersFromRequired(): void
    {
        $tool = new Tool('greet', 'Greets', fn (string $name, string $greeting = 'Hello'): string => "{$greeting}, {$name}!");

        $schema = $tool->schema();

        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('greeting', $schema['required']);
    }

    public function testSchemaMapsArrayToArray(): void
    {
        $tool = new Tool('list', 'Lists items', fn (array $items): string => implode(',', $items));

        $schema = $tool->schema();

        $this->assertSame('array', $schema['properties']['items']['type']);
    }

    public function testSchemaIncludesDescriptionFromAttribute(): void
    {
        $tool = new Tool(
            'get_user',
            'Fetch a user',
            function (#[Description('The unique user ID')] int $id): string {
                return (string) $id;
            },
        );

        $schema = $tool->schema();

        $this->assertSame('The unique user ID', $schema['properties']['id']['description']);
    }

    public function testSchemaWithoutDescriptionAttributeHasNoDescriptionKey(): void
    {
        $tool = new Tool('ping', 'Returns pong', fn (string $name): string => $name);

        $schema = $tool->schema();

        $this->assertArrayNotHasKey('description', $schema['properties']['name']);
    }

    public function testSchemaNullableTypeProducesArrayType(): void
    {
        $tool = new Tool('find', 'Finds item', fn (?string $query): string => $query ?? '');

        $schema = $tool->schema();

        $this->assertSame(['string', 'null'], $schema['properties']['query']['type']);
    }

    public function testCallDelegatesToHandler(): void
    {
        $tool = new Tool('double', 'Doubles a number', fn (int $n): string => (string) ($n * 2));

        $this->assertSame('10', $tool->call(['n' => 5]));
    }

    public function testCallMatchesArgumentsByName(): void
    {
        $tool = new Tool(
            'subtract',
            'Subtracts b from a',
            fn (int $a, int $b): string => (string) ($a - $b),
        );

        $this->assertSame('7', $tool->call(['b' => 3, 'a' => 10]));
    }

    public function testCallUsesDefaultForOptionalParameter(): void
    {
        $tool = new Tool(
            'greet',
            'Greets',
            fn (string $name, string $greeting = 'Hello'): string => "{$greeting}, {$name}!",
        );

        $this->assertSame('Hello, World!', $tool->call(['name' => 'World']));
    }
}
