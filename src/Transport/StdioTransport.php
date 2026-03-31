<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Transport;

final class StdioTransport implements TransportInterface
{
    public function read(): ?string
    {
        $line = fgets(STDIN);

        if ($line === false) {
            return null;
        }

        return trim($line);
    }

    public function write(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
        fflush(STDOUT);
    }
}
