<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QBuilder\Drivers\Mssql\MssqlDriver;
use QBuilder\Drivers\Mysql\MysqlDriver;

/**
 * @internal
 *
 * @coversNothing
 */
final class AbstractDriverTest extends TestCase
{
    public function testSupportsRecursiveCteReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        self::assertTrue($driver->supportsRecursiveCte());
    }

    public function testSupportsUnionReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        self::assertTrue($driver->supportsUnion());
    }

    public function testSupportsConflictHandlerReturnsTrueByDefault(): void
    {
        $driver = $this->getDriver();

        self::assertTrue($driver->supportsConflictHandler());
    }

    public function testTransformIdentifierReturnsIdentifierAsIsByDefault(): void
    {
        $driver = $this->getDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('transformIdentifier');
        $method->setAccessible(true);
        $result = $method->invoke($driver, 'test_identifier');

        self::assertSame('test_identifier', $result);
    }

    public function testGetEscapeCharReturnsDoubleQuoteForStandardQuotes(): void
    {
        $driver = $this->getDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getEscapeChar');
        $method->setAccessible(true);
        $result = $method->invoke($driver);

        self::assertSame('``', $result);
    }

    public function testGetClosingQuoteReturnsSameAsOpeningQuoteForStandardQuotes(): void
    {
        $driver = $this->getDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getClosingQuote');
        $method->setAccessible(true);
        $result = $method->invoke($driver);

        self::assertSame('`', $result);
    }

    public function testGetEscapeCharReturnsDoubleBracketForMssql(): void
    {
        $driver = new MssqlDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getEscapeChar');
        $method->setAccessible(true);
        $result = $method->invoke($driver);

        self::assertSame(']]', $result);
    }

    public function testGetClosingQuoteReturnsBracketForMssql(): void
    {
        $driver = new MssqlDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getClosingQuote');
        $method->setAccessible(true);
        $result = $method->invoke($driver);

        self::assertSame(']', $result);
    }

    private function getDriver(): MysqlDriver
    {
        return new MysqlDriver();
    }
}
