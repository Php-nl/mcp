# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- **HTTP + SSE Transport** (`HttpSseTransport`) — run the MCP server as a plain PHP process that speaks HTTP and Server-Sent Events, suitable for web-based clients and remote deployments. Supports custom host/port/paths, a `baseUrl` override for reverse-proxy setups, and automatic CORS headers.

- **Rich tool results** (`ToolResult`) — tool handlers can now return structured content with multiple items of mixed types: `text`, `image` (base64), and embedded `resource`. Immutable; chainable via `withText()`, `withImage()`, `withResource()`. Plain `string` return values remain supported for backward compatibility.

- **Input validation** — arguments are validated against the tool's generated JSON Schema before the handler is called. Missing required arguments and type mismatches produce a well-formed `InvalidParams` JSON-RPC error without invoking the handler.

- **Progress notifications** (`ProgressReporter`) — inject `ProgressReporter $progress` into a tool handler to send `notifications/progress` out-of-band messages during execution. The parameter is auto-injected by the SDK and excluded from the tool's JSON Schema. Calls are silently ignored (no-op) when the client does not supply a `progressToken`.

- **Middleware pipeline** — `McpServer::middleware(Closure $fn)` registers a closure that wraps every tool invocation. Signature: `function(string $name, array $args, callable $next): mixed`. Multiple middleware execute in registration order (outermost first).

- **Typed exception hierarchy** — all MCP-specific errors are now thrown as dedicated exception classes instead of bare `RuntimeException`:
  - `McpException` — abstract base class (`extends RuntimeException`)
  - `ToolNotFoundException` — tool name not registered
  - `InvalidToolArgumentsException` — validation failure
  - `ResourceNotFoundException` — resource URI not registered
  - `PromptNotFoundException` — prompt name not registered
  
  Each exception carries the correct JSON-RPC error code via `getErrorCode(): ErrorCode` and the native `getCode(): int`.

- `#[Description]` PHP attribute — annotate tool handler parameters with a human-readable description included in `inputSchema`.
- `McpServer::resource()` — register MCP resources with URI, name, MIME type, and handler.
- `McpServer::prompt()` — register MCP prompts with name, description, and handler.
- `ResourceRegistry` — handles `resources/list` and `resources/read`.
- `PromptRegistry` — handles `prompts/list` and `prompts/get`.
- `McpServer::VERSION` constant (replaces hardcoded version string).
- `ServerProcess::handshake()` — centralised MCP initialize/notifications flow.
- Protocol version validation in `initialize`: returns `InvalidParams` error on mismatch.
- `capabilities` in `initialize` response only advertises `resources`/`prompts` when at least one is registered.
- JSON Schema type mapping in `Tool::schema()`: `int`→`integer`, `float`→`number`, `bool`→`boolean`, `array`→`array`.
- Support for optional handler parameters: excluded from `inputSchema.required`.
- `CallCommand`: `bool` and `float` argument casting.
- `DebugCommand`: now queries `resources/list` and `prompts/list` in addition to `tools/list`.
- `ping` method support in `JsonRpcHandler`.

### Changed

- Minimum PHP version bumped from `^8.2` to `^8.3`.
- `JsonRpcHandler` now accepts an optional `Closure $writer` for sending out-of-band notifications (progress). Backward compatible — existing instantiation without the writer still works.
- `ToolRegistry::call()` and `Tool::call()` now accept an optional `?ProgressReporter $reporter`.

### Fixed

- `ToolRegistry::call()` now matches arguments by **name** via Reflection, not by array position.
- `McpServer::serve()` now **breaks** on EOF (`null` from transport) instead of busy-looping at 100% CPU.
- `CallCommand::parseArgs()` correctly casts `float` values (e.g. `--price=9.99` no longer truncates to `9`).
