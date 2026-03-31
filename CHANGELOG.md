# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- `#[Description]` PHP attribute — annotate tool handler parameters with a human-readable description included in `inputSchema` for AI models
- `McpServer::resource()` — register MCP resources with URI, name, MIME type, and handler
- `McpServer::prompt()` — register MCP prompts with name, description, and handler
- `ResourceRegistry` — handles `resources/list` and `resources/read`
- `PromptRegistry` — handles `prompts/list` and `prompts/get`
- `McpServer::VERSION` constant (replaces hardcoded version string)
- `ServerProcess::handshake()` — centralised MCP initialize/notifications flow
- Protocol version validation in `initialize`: returns `InvalidParams` error on mismatch
- `capabilities` in `initialize` response now only advertises `resources`/`prompts` when registered
- JSON Schema type mapping in `Tool::schema()`: PHP `int`→`integer`, `float`→`number`, `bool`→`boolean`, `array`→`array`
- Support for optional handler parameters: excluded from `inputSchema.required`
- `CallCommand`: `bool` argument casting (`--flag=true/false`)
- `DebugCommand`: now queries `resources/list` and `prompts/list` in addition to `tools/list`

### Changed
- Minimum PHP version bumped from `^8.2` to `^8.3`

### Fixed
- `ToolRegistry::call()` now matches arguments by **name** via Reflection, not by array position
- `McpServer::serve()` now **breaks** on EOF (`null` from transport) instead of busy-looping at 100% CPU
- `CallCommand::parseArgs()` correctly casts `float` values (e.g. `--price=9.99` no longer truncates to `9`)
