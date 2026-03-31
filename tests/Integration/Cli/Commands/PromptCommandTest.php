<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli\Commands;

use Phpnl\Mcp\Cli\Commands\PromptCommand;
use PHPUnit\Framework\TestCase;

final class PromptCommandTest extends TestCase
{
    private static function exampleServerPath(): string
    {
        return __DIR__ . '/../../../../examples/resources-and-prompts/server.php';
    }

    public function testPromptCommandReturnsOutput(): void
    {
        ob_start();
        $code = (new PromptCommand())->execute(self::exampleServerPath(), 'summarize', ['--topic=PHP']);
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    public function testPromptCommandReturnsErrorOnUnknownPrompt(): void
    {
        ob_start();
        $code = (new PromptCommand())->execute(self::exampleServerPath(), 'nonexistent', []);
        ob_get_clean();

        $this->assertSame(1, $code);
    }
}
