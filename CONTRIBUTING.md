# Contributing to phpnl/mcp

## Requirements

- PHP 8.3+
- Composer

## Setup

```bash
git clone https://github.com/phpnl/mcp.git
cd mcp
composer install
```

## Running checks

```bash
composer test   # PHPUnit
composer stan   # PHPStan level 8
composer lint   # PHP CS Fixer (dry-run)
```

To auto-fix code style:

```bash
vendor/bin/php-cs-fixer fix
```

## Project structure

```
src/
  McpServer.php               Entry point — fluent builder API
  Protocol/
    JsonRpcHandler.php         Handles all MCP methods; sends progress notifications
    JsonRpcMessage.php         Value object for JSON-RPC messages
    ErrorCode.php              Enum of standard JSON-RPC / MCP error codes
  Tool/
    Tool.php                   Value object + JSON Schema generator + argument validator + caller
    ToolRegistry.php           Stores tools, runs middleware pipeline, dispatches calls
    ToolResult.php             Immutable rich result (text / image / resource, chainable)
    ProgressReporter.php       Sends notifications/progress out-of-band during tool execution
    Description.php            #[Description] attribute for parameter-level descriptions
  Exception/
    McpException.php           Abstract base (extends RuntimeException, carries ErrorCode)
    ToolNotFoundException.php
    InvalidToolArgumentsException.php
    ResourceNotFoundException.php
    PromptNotFoundException.php
  Resource/
    Resource.php               Value object
    ResourceRegistry.php       Stores and reads resources
  Prompt/
    Prompt.php                 Value object
    PromptRegistry.php         Stores and invokes prompts
  Transport/
    TransportInterface.php     read(): ?string  /  write(string): void
    StdioTransport.php         Default — STDIN/STDOUT
    HttpSseTransport.php       HTTP + Server-Sent Events, no framework required
  Cli/
    Application.php            CLI entry point (injectable for testing)
    ServerProcess.php          Launches a server subprocess and communicates with it
    Commands/                  One class per CLI command (inspect, debug, call, read, prompt)
examples/
  hello-world/                 Minimal tool example
  http-sse/                    HTTP + SSE transport example
  resources-and-prompts/       Full example with resources + prompts
tests/
  Unit/                        Fast unit tests (no subprocess)
  Integration/                 Tests that spawn a real server process
```

## Adding a new MCP method

1. Add the method name to the `match` in `JsonRpcHandler::handle()`
2. Implement `handleXxx(JsonRpcMessage $message): string`
3. Add unit tests in `JsonRpcHandlerTest`

## Adding a new CLI command

1. Create `src/Cli/Commands/XxxCommand.php`
2. Add it to `Application::__construct()` with a default
3. Add a `match` arm in `Application::run()`
4. Add integration tests in `tests/Integration/Cli/Commands/`
