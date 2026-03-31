<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\Commands\CallCommand;
use Phpnl\Mcp\Tests\TestCase;

final class CallCommandTest extends TestCase
{
    public function testCallReturnsToolResult(): void
    {
        ob_start();
        $code = (new CallCommand())->execute(self::exampleServerPath(), 'hello_world', []);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Hello from PHP', $output);
    }

    public function testCallWithStringArgument(): void
    {
        ob_start();
        $code = (new CallCommand())->execute(self::exampleServerPath(), 'hello_world', ['--greeting=hello']);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
    }

    public function testCallWithNumericArgument(): void
    {
        ob_start();
        $code = (new CallCommand())->execute(self::exampleServerPath(), 'hello_world', ['--count=5']);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
    }

    public function testCallWithFloatArgument(): void
    {
        ob_start();
        $code = (new CallCommand())->execute(self::exampleServerPath(), 'hello_world', ['--price=9.99']);
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    public function testCallReturnsErrorOnUnknownTool(): void
    {
        ob_start();
        $code = (new CallCommand())->execute(self::exampleServerPath(), 'non_existent_tool', []);
        $output = ob_get_clean();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Error', $output);
    }
}
