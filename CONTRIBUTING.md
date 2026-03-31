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
  McpServer.php           Entry point — fluent API
  Protocol/
    JsonRpcHandler.php    Handles all MCP methods
    JsonRpcMessage.php    Value object for JSON-RPC messages
    ErrorCode.php         Enum of standard error codes
  Tool/
    Tool.php              Value object + schema generator + caller
    ToolRegistry.php      Stores and dispatches tools
    Description.php       #[Description] attribute for parameters
  Resource/
    Resource.php          Value object
    ResourceRegistry.php  Stores and reads resources
  Prompt/
    Prompt.php            Value object
    PromptRegistry.php    Stores and invokes prompts
  Transport/
    StdioTransport.php    Default stdio transport
    TransportInterface.php
  Cli/
    Application.php       CLI entry point (injectable)
    ServerProcess.php     Launches and communicates with a server
    Commands/             One class per CLI command
examples/
  hello-world/            Basic tool example
  resources-and-prompts/  Full example with resources + prompts
tests/
  Unit/                   Fast unit tests (no subprocess)
  Integration/            Tests that spawn a real server process
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
