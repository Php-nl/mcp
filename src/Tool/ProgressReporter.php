<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

use Closure;

/**
 * Sends MCP progress notifications back to the client during a tool invocation.
 *
 * Inject this into your tool handler by adding a typed parameter:
 *
 *   function (string $query, ProgressReporter $progress): string {
 *       $progress->report(1, 3); // step 1 of 3
 *       // ... work ...
 *       $progress->report(2, 3);
 *       // ... more work ...
 *       $progress->report(3, 3);
 *       return 'done';
 *   }
 *
 * The parameter is automatically injected by the SDK and does NOT appear in
 * the tool's JSON Schema or in the arguments the AI must supply.
 *
 * When the client did not send a progressToken, all calls to report() are
 * silently ignored (no-op).
 */
final class ProgressReporter
{
    public function __construct(
        private readonly string|int|null $progressToken,
        private readonly Closure $writer,
    ) {
    }

    /**
     * Reports the current progress to the client.
     *
     * @param int|float      $progress Current progress value (e.g. number of items processed)
     * @param int|float|null $total    Optional total value (e.g. total number of items)
     */
    public function report(int|float $progress, int|float|null $total = null): void
    {
        if ($this->progressToken === null) {
            return;
        }

        $params = [
            'progressToken' => $this->progressToken,
            'progress' => $progress,
        ];

        if ($total !== null) {
            $params['total'] = $total;
        }

        ($this->writer)((string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => $params,
        ], JSON_PRESERVE_ZERO_FRACTION));
    }
}
