<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\Commands\DebugCommand;
use Phpnl\Mcp\Tests\TestCase;

final class DebugCommandTest extends TestCase
{
    public function testDebugStreamsJsonRpcTraffic(): void
    {
        ob_start();
        $code = (new DebugCommand())->execute(self::exampleServerPath());
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('initialize', $output);
        $this->assertStringContainsString('tools/list', $output);
    }
}
