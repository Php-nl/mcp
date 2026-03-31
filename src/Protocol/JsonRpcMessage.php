<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Protocol;

final readonly class JsonRpcMessage
{
    public function __construct(
        public string $jsonrpc,
        public string|int|null $id,
        public ?string $method,
        /** @var array<string, mixed>|null */
        public ?array $params,
        public mixed $result,
        /** @var array<string, mixed>|null */
        public ?array $error,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            jsonrpc: $data['jsonrpc'] ?? '2.0',
            id: $data['id'] ?? null,
            method: $data['method'] ?? null,
            params: $data['params'] ?? null,
            result: $data['result'] ?? null,
            error: $data['error'] ?? null,
        );
    }

    public function isRequest(): bool
    {
        return $this->method !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'result' => $this->result,
            'error' => $this->error,
        ], fn (mixed $value) => $value !== null);
    }
}
