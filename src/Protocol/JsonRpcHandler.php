<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Protocol;

use Closure;
use Phpnl\Mcp\Exception\McpException;
use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Prompt\PromptRegistry;
use Phpnl\Mcp\Resource\ResourceRegistry;
use Phpnl\Mcp\Tool\ProgressReporter;
use Phpnl\Mcp\Tool\ToolRegistry;
use Phpnl\Mcp\Tool\ToolResult;

final class JsonRpcHandler
{
    /** @var Closure(string): void */
    private readonly Closure $writer;

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly PromptRegistry $promptRegistry,
        ?Closure $writer = null,
    ) {
        $this->writer = $writer ?? static fn (string $_) => null;
    }

    public function handle(string $rawMessage): ?string
    {
        if (trim($rawMessage) === '') {
            return null;
        }

        $data = json_decode($rawMessage, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return $this->errorResponse(null, ErrorCode::ParseError);
        }

        $message = JsonRpcMessage::fromArray($data);

        if (! $message->isRequest()) {
            return null;
        }

        return match ($message->method) {
            'initialize' => $this->handleInitialize($message),
            'notifications/initialized' => null,
            'ping' => $this->handlePing($message),
            'tools/list' => $this->handleToolsList($message),
            'tools/call' => $this->handleToolsCall($message),
            'resources/list' => $this->handleResourcesList($message),
            'resources/read' => $this->handleResourcesRead($message),
            'prompts/list' => $this->handlePromptsList($message),
            'prompts/get' => $this->handlePromptsGet($message),
            default => $this->errorResponse($message->id, ErrorCode::MethodNotFound),
        };
    }

    private function handleInitialize(JsonRpcMessage $message): string
    {
        $clientVersion = $message->params['protocolVersion'] ?? null;
        $supportedVersion = '2024-11-05';

        if ($clientVersion !== null && $clientVersion !== $supportedVersion) {
            return $this->errorResponse(
                $message->id,
                ErrorCode::InvalidParams,
                "Unsupported protocol version: {$clientVersion}. Expected: {$supportedVersion}",
            );
        }

        $capabilities = ['tools' => new \stdClass()];

        if (! $this->resourceRegistry->isEmpty()) {
            $capabilities['resources'] = new \stdClass();
        }

        if (! $this->promptRegistry->isEmpty()) {
            $capabilities['prompts'] = new \stdClass();
        }

        return $this->successResponse($message->id, [
            'protocolVersion' => $supportedVersion,
            'capabilities' => $capabilities,
            'serverInfo' => [
                'name' => 'phpnl/mcp',
                'version' => McpServer::VERSION,
            ],
        ]);
    }

    private function handlePing(JsonRpcMessage $message): string
    {
        return $this->successResponse($message->id, new \stdClass());
    }

    private function handleToolsList(JsonRpcMessage $message): string
    {
        return $this->successResponse($message->id, [
            'tools' => $this->toolRegistry->all(),
        ]);
    }

    private function handleToolsCall(JsonRpcMessage $message): string
    {
        $name = $message->params['name'] ?? null;
        $arguments = $message->params['arguments'] ?? [];

        if ($name === null) {
            return $this->errorResponse($message->id, ErrorCode::InvalidParams);
        }

        $progressToken = $message->params['_meta']['progressToken'] ?? null;
        $reporter = new ProgressReporter($progressToken, $this->writer);

        try {
            $result = $this->toolRegistry->call($name, $arguments, $reporter);

            $content = $result instanceof ToolResult
                ? $result->toContent()
                : [['type' => 'text', 'text' => (string) $result]];

            return $this->successResponse($message->id, ['content' => $content]);
        } catch (McpException $exception) {
            return $this->errorResponse($message->id, $exception->getErrorCode(), $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->errorResponse($message->id, ErrorCode::InternalError, $exception->getMessage());
        }
    }

    private function handleResourcesList(JsonRpcMessage $message): string
    {
        return $this->successResponse($message->id, [
            'resources' => $this->resourceRegistry->all(),
        ]);
    }

    private function handleResourcesRead(JsonRpcMessage $message): string
    {
        $uri = $message->params['uri'] ?? null;

        if ($uri === null) {
            return $this->errorResponse($message->id, ErrorCode::InvalidParams);
        }

        try {
            $content = $this->resourceRegistry->read($uri);

            return $this->successResponse($message->id, [
                'contents' => [
                    ['uri' => $uri, 'text' => $content],
                ],
            ]);
        } catch (McpException $exception) {
            return $this->errorResponse($message->id, $exception->getErrorCode(), $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->errorResponse($message->id, ErrorCode::InternalError, $exception->getMessage());
        }
    }

    private function handlePromptsList(JsonRpcMessage $message): string
    {
        return $this->successResponse($message->id, [
            'prompts' => $this->promptRegistry->all(),
        ]);
    }

    private function handlePromptsGet(JsonRpcMessage $message): string
    {
        $name = $message->params['name'] ?? null;
        $arguments = $message->params['arguments'] ?? [];

        if ($name === null) {
            return $this->errorResponse($message->id, ErrorCode::InvalidParams);
        }

        try {
            $content = $this->promptRegistry->get($name, $arguments);

            return $this->successResponse($message->id, [
                'description' => $this->promptRegistry->description($name),
                'messages' => [
                    ['role' => 'user', 'content' => ['type' => 'text', 'text' => $content]],
                ],
            ]);
        } catch (McpException $exception) {
            return $this->errorResponse($message->id, $exception->getErrorCode(), $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->errorResponse($message->id, ErrorCode::InternalError, $exception->getMessage());
        }
    }

    private function successResponse(string|int|null $id, mixed $result): string
    {
        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function errorResponse(string|int|null $id, ErrorCode $code, ?string $data = null): string
    {
        $error = [
            'code' => $code->value,
            'message' => $code->message(),
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ]);
    }
}
