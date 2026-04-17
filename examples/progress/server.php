<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Phpnl\Mcp\McpServer;
use Phpnl\Mcp\Tool\Description;
use Phpnl\Mcp\Tool\ProgressReporter;

/**
 * Progress notifications example
 *
 * Demonstrates how to report progress during a long-running tool.
 *
 * Start this server:
 *   php examples/progress/server.php
 *
 * Then run the tool via the CLI:
 *   ./vendor/bin/phpnl call examples/progress/server.php process_items --count=5
 *
 * To see progress notifications, run with debug mode:
 *   ./vendor/bin/phpnl debug examples/progress/server.php
 *
 * How it works:
 * - The tool handler declares a `ProgressReporter $progress` parameter.
 * - The SDK injects this automatically; it does NOT appear in the tool's JSON Schema.
 * - When the client sends a `progressToken` in `_meta`, calls to `$progress->report()`
 *   emit `notifications/progress` JSON-RPC messages out-of-band.
 * - When no token is sent (e.g. via the CLI), `report()` is silently ignored.
 */
McpServer::make()
    ->tool(
        name: 'process_items',
        description: 'Simulate processing a batch of items with progress reporting',
        handler: function (
            #[Description('Number of items to process (1–20)')] int $count,
            ProgressReporter $progress,
        ): string {
            $count = max(1, min(20, $count));

            for ($i = 1; $i <= $count; $i++) {
                // Simulate work
                usleep(200_000); // 200 ms per item

                // Report progress: current step and total
                $progress->report($i, $count);
            }

            return "Successfully processed {$count} items.";
        },
    )
    ->tool(
        name: 'generate_report',
        description: 'Generate a multi-section report with progress updates',
        handler: function (
            #[Description('Report title')] string $title,
            ProgressReporter $progress,
        ): string {
            $sections = ['Collecting data', 'Analysing results', 'Building charts', 'Writing summary'];
            $total = count($sections);

            $output = "# {$title}\n\n";

            foreach ($sections as $i => $section) {
                usleep(300_000); // 300 ms per section
                $progress->report($i + 1, $total);
                $output .= "## {$section}\n\nLorem ipsum dolor sit amet.\n\n";
            }

            return $output;
        },
    )
    ->serve();
