<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Prompt;

use Closure;
use Phpnl\Mcp\Protocol\ErrorCode;

final class PromptRegistry
{
    /** @var array<string, Prompt> */
    private array $prompts = [];

    public function register(string $name, string $description, Closure $handler): void
    {
        $this->prompts[$name] = new Prompt($name, $description, $handler);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (Prompt $prompt) => [
                'name' => $prompt->name,
                'description' => $prompt->description,
            ],
            array_values($this->prompts),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function get(string $name, array $arguments = []): string
    {
        if (! isset($this->prompts[$name])) {
            throw new \RuntimeException(
                ErrorCode::PromptNotFound->message() . ": {$name}",
                ErrorCode::PromptNotFound->value,
            );
        }

        return (string) ($this->prompts[$name]->handler)($arguments);
    }

    public function has(string $name): bool
    {
        return isset($this->prompts[$name]);
    }

    public function description(string $name): string
    {
        return isset($this->prompts[$name]) ? $this->prompts[$name]->description : '';
    }

    public function isEmpty(): bool
    {
        return $this->prompts === [];
    }
}
