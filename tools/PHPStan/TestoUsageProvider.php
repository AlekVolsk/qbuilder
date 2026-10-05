<?php

declare(strict_types=1);

namespace QBuilder\Tools\PHPStan;

use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Testo calls tests and their data providers via reflection: public instance methods of a #[Test] class,
 * #[Test] methods, and static methods named in #[DataProvider] of the same class.
 */
final class TestoUsageProvider extends ReflectionBasedMemberUsageProvider
{
    #[\Override]
    protected function shouldMarkMethodAsUsed(\ReflectionMethod $method): ?VirtualUsageData
    {
        $class = $method->getDeclaringClass();

        if ([] !== $method->getAttributes(Test::class)) {
            return VirtualUsageData::withNote('Testo test');
        }

        if ($method->isStatic()) {
            return self::isDataProvider($class, $method->getName())
                ? VirtualUsageData::withNote('Testo data provider')
                : null;
        }

        if ($method->isPublic() && [] !== $class->getAttributes(Test::class)) {
            return VirtualUsageData::withNote('Testo test');
        }

        return null;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private static function isDataProvider(\ReflectionClass $class, string $methodName): bool
    {
        foreach ($class->getMethods() as $test) {
            foreach ($test->getAttributes(DataProvider::class) as $attribute) {
                if ($methodName === ($attribute->getArguments()[0] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }
}
