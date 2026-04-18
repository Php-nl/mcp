<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Transport;

final class StdioTransport implements TransportInterface
{
    /** @var resource */
    private mixed $stdin;

    /** @var resource */
    private mixed $stdout;

    /**
     * @param resource|null $stdin  Defaults to STDIN
     * @param resource|null $stdout Defaults to STDOUT
     */
    public function __construct(
        mixed $stdin = null,
        mixed $stdout = null,
    ) {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
    }

    public function read(): ?string
    {
        $line = fgets($this->stdin);

        if ($line === false) {
            return null;
        }

        return trim($line);
    }

    public function write(string $message): void
    {
        fwrite($this->stdout, $message . "\n");
        fflush($this->stdout);
    }
}
