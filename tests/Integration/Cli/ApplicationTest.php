<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Integration\Cli;

use Phpnl\Mcp\Cli\Application;
use Phpnl\Mcp\Tests\TestCase;

final class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testRunWithNoArgsShowsHelp(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunWithHelpFlagShowsHelp(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', '--help']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunWithShortHelpFlagShowsHelp(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', '-h']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunWithMissingScriptReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'inspect']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunWithNonExistentScriptReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'inspect', '/non/existent/server.php']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunWithUnknownCommandReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'unknown', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunInspectWithRealServer(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'inspect', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunDebugWithRealServer(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'debug', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunCallWithMissingToolNameReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'call', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunReadWithMissingUriReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'read', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunPromptWithMissingNameReturnsError(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'prompt', self::exampleServerPath()]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testRunCallWithRealServer(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'call', self::exampleServerPath(), 'hello_world']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }

    public function testRunCallWithStringArgument(): void
    {
        ob_start();
        $code = $this->app->run(['phpnl', 'call', self::exampleServerPath(), 'hello_world', '--label=test']);
        ob_end_clean();

        $this->assertSame(0, $code);
    }
}
