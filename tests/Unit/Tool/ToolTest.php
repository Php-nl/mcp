<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Tool;

use Phpnl\Mcp\Exception\InvalidToolArgumentsException;
use Phpnl\Mcp\Tests\TestCase;
use Phpnl\Mcp\Tool\Description;
use Phpnl\Mcp\Tool\ProgressReporter;
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

    public function testSchemaUnionTypeWithNullProducesArrayType(): void
    {
        // int|null written as a union type (ReflectionUnionType, not ReflectionNamedType)
        $tool = new Tool('find', 'Finds by id', fn (int|null $id): string => (string) ($id ?? 0));

        $schema = $tool->schema();

        $this->assertSame(['integer', 'null'], $schema['properties']['id']['type']);
    }

    public function testSchemaMultiTypeUnionFallsBackToString(): void
    {
        // int|string has multiple non-null types — falls back to 'string'
        $tool = new Tool('mixed', 'Mixed input', fn (int|string $value): string => (string) $value);

        $schema = $tool->schema();

        $this->assertSame('string', $schema['properties']['value']['type']);
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

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function testValidatePassesForCorrectArguments(): void
    {
        $tool = new Tool('add', 'Adds numbers', fn (int $a, int $b): string => (string) ($a + $b));

        $tool->validate(['a' => 1, 'b' => 2]); // must not throw

        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsForMissingRequiredArgument(): void
    {
        $tool = new Tool('add', 'Adds numbers', fn (int $a, int $b): string => (string) ($a + $b));

        $this->expectException(InvalidToolArgumentsException::class);
        $this->expectExceptionMessageMatches('/Missing required argument: b/');

        $tool->validate(['a' => 1]);
    }

    public function testValidateThrowsForWrongType(): void
    {
        $tool = new Tool('double', 'Doubles', fn (int $n): string => (string) ($n * 2));

        $this->expectException(InvalidToolArgumentsException::class);
        $this->expectExceptionMessageMatches("/Argument 'n' must be of type integer/");

        $tool->validate(['n' => 'not-an-int']);
    }

    public function testValidateAcceptsNullForNullableType(): void
    {
        $tool = new Tool('find', 'Finds', fn (?string $query): string => $query ?? '');

        $tool->validate(['query' => null]); // must not throw

        $this->addToAssertionCount(1);
    }

    public function testValidateAcceptsStringForNullableString(): void
    {
        $tool = new Tool('find', 'Finds', fn (?string $query): string => $query ?? '');

        $tool->validate(['query' => 'hello']); // must not throw

        $this->addToAssertionCount(1);
    }

    public function testValidatePassesForMissingOptionalArgument(): void
    {
        $tool = new Tool(
            'greet',
            'Greets',
            fn (string $name, string $greeting = 'Hello'): string => "{$greeting}, {$name}!",
        );

        $tool->validate(['name' => 'World']); // 'greeting' is optional, must not throw

        $this->addToAssertionCount(1);
    }

    public function testValidatePassesForAllSupportedTypes(): void
    {
        $tool = new Tool(
            'all_types',
            'All types',
            fn (int $i, float $f, bool $b, array $a, string $s): string => '',
        );

        $tool->validate(['i' => 1, 'f' => 1.5, 'b' => true, 'a' => [], 's' => 'x']);

        $this->addToAssertionCount(1);
    }

    public function testValidateAcceptsIntForFloatType(): void
    {
        // JSON 'number' type allows both int and float
        $tool = new Tool('price', 'Price', fn (float $amount): string => (string) $amount);

        $tool->validate(['amount' => 5]); // int satisfies 'number'

        $this->addToAssertionCount(1);
    }

    public function testValidateIgnoresExtraArguments(): void
    {
        $tool = new Tool('ping', 'Ping', fn (): string => 'pong');

        $tool->validate(['unexpected' => 'value']); // extra arg must not throw

        $this->addToAssertionCount(1);
    }

    public function testValidateExceptionHasInvalidParamsErrorCode(): void
    {
        $tool = new Tool('add', 'Adds', fn (int $a): string => (string) $a);

        try {
            $tool->validate([]);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame(\Phpnl\Mcp\Protocol\ErrorCode::InvalidParams->value, $e->getCode());
        }
    }

    // -------------------------------------------------------------------------
    // ProgressReporter injection
    // -------------------------------------------------------------------------

    public function testProgressReporterParameterIsSkippedInSchema(): void
    {
        $tool = new Tool(
            'search',
            'Searches',
            fn (string $query, ProgressReporter $progress): string => $query,
        );

        $schema = $tool->schema();

        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayNotHasKey('progress', $schema['properties']);
        $this->assertContains('query', $schema['required']);
        $this->assertNotContains('progress', $schema['required']);
    }

    public function testProgressReporterIsInjectedDuringCall(): void
    {
        $injectedReporter = null;
        $tool = new Tool(
            'work',
            'Does work',
            function (ProgressReporter $progress) use (&$injectedReporter): string {
                $injectedReporter = $progress;

                return 'done';
            },
        );

        $reporter = new ProgressReporter(null, fn () => null);
        $result = $tool->call([], $reporter);

        $this->assertSame('done', $result);
        $this->assertSame($reporter, $injectedReporter);
    }

    public function testProgressReporterIsInjectedAlongsideRegularArguments(): void
    {
        $reportedValues = [];
        $tool = new Tool(
            'count',
            'Counts',
            function (int $n, ProgressReporter $progress) use (&$reportedValues): string {
                for ($i = 1; $i <= $n; $i++) {
                    $progress->report($i, $n);
                    $reportedValues[] = $i;
                }

                return "counted {$n}";
            },
        );

        $written = [];
        $reporter = new ProgressReporter('tok', function (string $msg) use (&$written): void {
            $written[] = json_decode($msg, true);
        });

        $result = $tool->call(['n' => 3], $reporter);

        $this->assertSame('counted 3', $result);
        $this->assertCount(3, $written);
        $this->assertSame(1, $written[0]['params']['progress']);
        $this->assertSame(2, $written[1]['params']['progress']);
        $this->assertSame(3, $written[2]['params']['progress']);
    }
}
