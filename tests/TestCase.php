<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected static function projectRoot(): string
    {
        return dirname(__DIR__);
    }

    protected static function exampleServerPath(): string
    {
        return self::projectRoot() . '/examples/hello-world/server.php';
    }

    protected static function fixturePath(string $fixture): string
    {
        return self::projectRoot() . '/tests/Fixtures/' . $fixture;
    }
}
