<?php

declare(strict_types=1);

namespace Phpnl\Mcp;

use Closure;
use Phpnl\Mcp\Prompt\PromptRegistry;
use Phpnl\Mcp\Protocol\JsonRpcHandler;
use Phpnl\Mcp\Resource\ResourceRegistry;
use Phpnl\Mcp\Tool\ToolRegistry;
use Phpnl\Mcp\Transport\StdioTransport;
use Phpnl\Mcp\Transport\TransportInterface;

final class McpServer
{
    public const VERSION = '1.0.0';

    private readonly ToolRegistry $toolRegistry;
    private readonly ResourceRegistry $resourceRegistry;
    private readonly PromptRegistry $promptRegistry;

    public function __construct(
        private readonly TransportInterface $transport = new StdioTransport(),
    ) {
        $this->toolRegistry = new ToolRegistry();
        $this->resourceRegistry = new ResourceRegistry();
        $this->promptRegistry = new PromptRegistry();
    }

    public static function make(TransportInterface $transport = new StdioTransport()): self
    {
        return new self($transport);
    }

    /**
     * Registers a middleware that wraps every tool invocation.
     *
     * The middleware receives the tool name, the (validated) arguments, and a
     * $next callable to continue the chain. It must return the tool result.
     *
     * Example:
     *
     *   ->middleware(function (string $name, array $args, callable $next): mixed {
     *       error_log("Calling tool: {$name}");
     *       $result = $next($name, $args);
     *       error_log("Tool {$name} returned: {$result}");
     *       return $result;
     *   })
     */
    public function middleware(Closure $fn): self
    {
        $this->toolRegistry->addMiddleware($fn);

        return $this;
    }

    public function tool(string $name, string $description, Closure $handler): self
    {
        $this->toolRegistry->register($name, $description, $handler);

        return $this;
    }

    public function resource(string $uri, string $name, string $mimeType, Closure $handler): self
    {
        $this->resourceRegistry->register($uri, $name, $mimeType, $handler);

        return $this;
    }

    public function prompt(string $name, string $description, Closure $handler): self
    {
        $this->promptRegistry->register($name, $description, $handler);

        return $this;
    }

    public function serve(): void
    {
        $handler = new JsonRpcHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            fn (string $msg) => $this->transport->write($msg),
        );

        while (true) {
            $line = $this->transport->read();

            if ($line === null) {
                break;
            }

            if ($line === '') {
                continue;
            }

            $response = $handler->handle($line);

            if ($response !== null) {
                $this->transport->write($response);
            }
        }
    }
}
