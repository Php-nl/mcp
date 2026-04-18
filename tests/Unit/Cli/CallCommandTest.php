<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Cli;

use Phpnl\Mcp\Cli\Commands\CallCommand;
use Phpnl\Mcp\Tests\TestCase;

/**
 * Unit tests for CallCommand::castValue() via reflection.
 *
 * The integration tests in Integration/Cli/Commands/CallCommandTest cover the
 * full execute() path. These unit tests specifically exercise the castValue()
 * branches that are not reachable from the integration tests (booleans,
 * negative integers).
 */
final class CallCommandTest extends TestCase
{
    private \ReflectionMethod $castValue;
    private CallCommand $command;

    protected function setUp(): void
    {
        $this->command = new CallCommand();
        $ref = new \ReflectionClass($this->command);
        $this->castValue = $ref->getMethod('castValue');
        $this->castValue->setAccessible(true);
    }

    public function testCastValueReturnsTrueForTrueString(): void
    {
        $this->assertTrue($this->castValue->invoke($this->command, 'true'));
    }

    public function testCastValueReturnsFalseForFalseString(): void
    {
        $this->assertFalse($this->castValue->invoke($this->command, 'false'));
    }

    public function testCastValueReturnsIntForNegativeInteger(): void
    {
        $this->assertSame(-42, $this->castValue->invoke($this->command, '-42'));
    }

    public function testCastValueReturnsIntForPositiveInteger(): void
    {
        $this->assertSame(7, $this->castValue->invoke($this->command, '7'));
    }

    public function testCastValueReturnsFloatForDecimalString(): void
    {
        $this->assertSame(3.14, $this->castValue->invoke($this->command, '3.14'));
    }

    public function testCastValueReturnsStringForPlainText(): void
    {
        $this->assertSame('hello', $this->castValue->invoke($this->command, 'hello'));
    }
}
