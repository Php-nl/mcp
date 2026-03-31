<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\Commands\ReadCommand;
use PHPUnit\Framework\TestCase;

final class ReadCommandTest extends TestCase
{
    private static function exampleServerPath(): string
    {
        return __DIR__ . '/../../../../examples/resources-and-prompts/server.php';
    }

    public function testReadReturnsResourceContent(): void
    {
        ob_start();
        $code = (new ReadCommand())->execute(self::exampleServerPath(), 'file://config');
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    public function testReadReturnsErrorOnUnknownUri(): void
    {
        ob_start();
        $code = (new ReadCommand())->execute(self::exampleServerPath(), 'file://missing');
        ob_get_clean();

        $this->assertSame(1, $code);
    }
}
