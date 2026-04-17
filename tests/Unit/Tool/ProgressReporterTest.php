<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Tool;

use Phpnl\Mcp\Tool\ProgressReporter;
use PHPUnit\Framework\TestCase;

final class ProgressReporterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // No-op when no token
    // -------------------------------------------------------------------------

    public function testReportIsNoOpWhenProgressTokenIsNull(): void
    {
        $written = [];
        $reporter = new ProgressReporter(null, function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(1, 10);

        $this->assertEmpty($written);
    }

    // -------------------------------------------------------------------------
    // Notification format
    // -------------------------------------------------------------------------

    public function testReportSendsProgressNotificationWithStringToken(): void
    {
        $written = [];
        $reporter = new ProgressReporter('tok-abc', function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(3, 10);

        $this->assertCount(1, $written);
        $payload = json_decode($written[0], true);

        $this->assertSame('2.0', $payload['jsonrpc']);
        $this->assertSame('notifications/progress', $payload['method']);
        $this->assertSame('tok-abc', $payload['params']['progressToken']);
        $this->assertSame(3, $payload['params']['progress']);
        $this->assertSame(10, $payload['params']['total']);
    }

    public function testReportSendsProgressNotificationWithIntegerToken(): void
    {
        $written = [];
        $reporter = new ProgressReporter(42, function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(5);

        $payload = json_decode($written[0], true);

        $this->assertSame(42, $payload['params']['progressToken']);
        $this->assertSame(5, $payload['params']['progress']);
    }

    public function testReportOmitsTotalWhenNotProvided(): void
    {
        $written = [];
        $reporter = new ProgressReporter('tok', function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(7);

        $payload = json_decode($written[0], true);

        $this->assertArrayNotHasKey('total', $payload['params']);
    }

    public function testReportCanBeCalledMultipleTimes(): void
    {
        $written = [];
        $reporter = new ProgressReporter('tok', function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(1, 3);
        $reporter->report(2, 3);
        $reporter->report(3, 3);

        $this->assertCount(3, $written);

        $this->assertSame(1, json_decode($written[0], true)['params']['progress']);
        $this->assertSame(2, json_decode($written[1], true)['params']['progress']);
        $this->assertSame(3, json_decode($written[2], true)['params']['progress']);
    }

    public function testReportAcceptsFloatValues(): void
    {
        $written = [];
        $reporter = new ProgressReporter('tok', function (string $msg) use (&$written): void {
            $written[] = $msg;
        });

        $reporter->report(0.5, 1.0);

        $payload = json_decode($written[0], true);

        $this->assertSame(0.5, $payload['params']['progress']);
        $this->assertSame(1.0, $payload['params']['total']);
    }
}
