<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

use Closure;
use Phpnl\Mcp\Protocol\ErrorCode;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @var list<Closure> */
    private array $middleware = [];

    /**
     * Registers a middleware that wraps every tool invocation.
     *
     * Signature: function(string $name, array $arguments, callable $next): mixed
     *
     * Middleware is executed in registration order (first registered = outermost).
     */
    public function addMiddleware(Closure $fn): void
    {
        $this->middleware[] = $fn;
    }

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

        $tool = $this->tools[$name];
        $tool->validate($arguments);

        $pipeline = static function (string $n, array $args) use ($tool): mixed {
            return $tool->call($args);
        };

        foreach (array_reverse($this->middleware) as $layer) {
            $pipeline = static function (string $n, array $args) use ($layer, $pipeline): mixed {
                return $layer($n, $args, $pipeline);
            };
        }

        return $pipeline($name, $arguments);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
