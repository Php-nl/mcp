<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

use Closure;
use Phpnl\Mcp\Protocol\ErrorCode;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(string $name, string $description, Closure $handler): void
    {
        $this->tools[$name] = new Tool($name, $description, $handler);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (Tool $tool) => [
                'name' => $tool->name,
                'description' => $tool->description,
                'inputSchema' => $tool->schema(),
            ],
            array_values($this->tools),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments): mixed
    {
        if (! isset($this->tools[$name])) {
            throw new \RuntimeException(
                ErrorCode::ToolNotFound->message() . ": {$name}",
                ErrorCode::ToolNotFound->value,
            );
        }

        return $this->tools[$name]->call($arguments);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
