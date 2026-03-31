<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Cli;

use Phpnl\Mcp\Cli\Commands\CallCommand;
use Phpnl\Mcp\Cli\Commands\DebugCommand;
use Phpnl\Mcp\Cli\Commands\InspectCommand;
use Phpnl\Mcp\Cli\Commands\PromptCommand;
use Phpnl\Mcp\Cli\Commands\ReadCommand;

final class Application
{
    public function __construct(
        private readonly InspectCommand $inspectCommand = new InspectCommand(),
        private readonly DebugCommand $debugCommand = new DebugCommand(),
        private readonly CallCommand $callCommand = new CallCommand(),
        private readonly ReadCommand $readCommand = new ReadCommand(),
        private readonly PromptCommand $promptCommand = new PromptCommand(),
    ) {}

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;
        $script = $argv[2] ?? null;

        if ($command === null || $command === '--help' || $command === '-h') {
            $this->printHelp();

            return 0;
        }

        if ($script === null) {
            echo "\033[31mError:\033[0m Missing server script argument.\n";
            echo "Usage: phpnl {$command} <server.php>\n";

            return 1;
        }

        if (! file_exists($script)) {
            echo "\033[31mError:\033[0m File not found: {$script}\n";

            return 1;
        }

        return match ($command) {
            'inspect' => $this->inspectCommand->execute($script),
            'debug' => $this->debugCommand->execute($script),
            'call' => $this->dispatchCall($argv, $script),
            'read' => $this->dispatchRead($argv, $script),
            'prompt' => $this->dispatchPrompt($argv, $script),
            default => $this->unknownCommand($command),
        };
    }

    /**
     * @param array<int, string> $argv
     */
    private function dispatchCall(array $argv, string $script): int
    {
        $toolName = $argv[3] ?? null;

        if ($toolName === null) {
            echo "\033[31mError:\033[0m Missing tool name.\n";
            echo "Usage: phpnl call <server.php> <tool-name> [--param=value]\n";

            return 1;
        }

        return $this->callCommand->execute($script, $toolName, array_slice($argv, 4));
    }

    /**
     * @param array<int, string> $argv
     */
    private function dispatchRead(array $argv, string $script): int
    {
        $uri = $argv[3] ?? null;

        if ($uri === null) {
            echo "\033[31mError:\033[0m Missing resource URI.\n";
            echo "Usage: phpnl read <server.php> <uri>\n";

            return 1;
        }

        return $this->readCommand->execute($script, $uri);
    }

    /**
     * @param array<int, string> $argv
     */
    private function dispatchPrompt(array $argv, string $script): int
    {
        $promptName = $argv[3] ?? null;

        if ($promptName === null) {
            echo "\033[31mError:\033[0m Missing prompt name.\n";
            echo "Usage: phpnl prompt <server.php> <prompt-name> [--key=value]\n";

            return 1;
        }

        return $this->promptCommand->execute($script, $promptName, array_slice($argv, 4));
    }

    private function unknownCommand(string $command): int
    {
        echo "\033[31mUnknown command:\033[0m {$command}\n\n";
        $this->printHelp();

        return 1;
    }

    private function printHelp(): void
    {
        echo <<<HELP

\033[1mphpnl\033[0m — MCP developer tools for PHP

\033[1mUsage:\033[0m
  phpnl <command> <server.php> [options]

\033[1mCommands:\033[0m
  \033[32minspect\033[0m  <server.php>                  List all registered tools
  \033[32mdebug\033[0m    <server.php>                  Stream live JSON-RPC traffic
  \033[32mcall\033[0m     <server.php> <tool> [--k=v]   Call a specific tool
  \033[32mread\033[0m     <server.php> <uri>             Read a resource by URI
  \033[32mprompt\033[0m   <server.php> <name> [--k=v]   Invoke a prompt

\033[1mExamples:\033[0m
  phpnl inspect examples/hello-world/server.php
  phpnl call    examples/hello-world/server.php get_user --id=1
  phpnl read    examples/hello-world/server.php file://config
  phpnl prompt  examples/hello-world/server.php summarize --topic=PHP

HELP;
    }
}
