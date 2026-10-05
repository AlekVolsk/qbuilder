<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Drivers\Mysql\MysqlDriver;
use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class AbstractDriverTest
{
    public function testSupportsRecursiveCteReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        Assert::true($driver->supportsRecursiveCte());
    }

    public function testSupportsUnionReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        Assert::true($driver->supportsUnion());
    }

    public function testSupportsConflictHandlerReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        Assert::true($driver->supportsConflictHandler());
    }

    private function getDriver(): MysqlDriver
    {
        return new MysqlDriver();
    }
}
