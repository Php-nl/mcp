<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\Commands\InspectCommand;
use Phpnl\Mcp\Tests\TestCase;

final class InspectCommandTest extends TestCase
{
    public function testInspectShowsRegisteredTools(): void
    {
        ob_start();
        $code = (new InspectCommand())->execute(self::exampleServerPath());
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('hello_world', $output);
        $this->assertStringContainsString('get_php_version', $output);
    }

    public function testInspectWithNoToolsServer(): void
    {
        ob_start();
        $code = (new InspectCommand())->execute(self::fixturePath('no-tools-server.php'));
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No tools registered', $output);
    }
}
