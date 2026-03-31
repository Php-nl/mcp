<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Transport;

interface TransportInterface
{
    public function read(): ?string;

    public function write(string $message): void;
}
