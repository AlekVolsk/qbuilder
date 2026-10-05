<?php

declare(strict_types=1);

namespace QBuilder\Tools\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Graph\UsageOrigin;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Methods of an interface are used while the interface has a consumer: a class outside the tests that
 * implements it. The consumer's own methods implementing them are used too, since the contract requires them.
 * Without such a class every method of the interface stays reported as dead.
 */
final readonly class InterfaceConsumerUsageProvider implements MemberUsageProvider
{
    public function __construct(
        private string $testsDir,
    ) {}

    /**
     * @return list<ClassMethodUsage>
     */
    #[\Override]
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- signature from MemberUsageProvider
    public function getUsages(Node $node, Scope $scope): array
    {
        if (! $node instanceof InClassNode) { // @phpstan-ignore phpstanApi.instanceofAssumption
            return [];
        }

        $class = $node->getClassReflection();

        if ($class->isInterface() || $class->isTrait() || $this->isTestFile($class->getFileName())) {
            return [];
        }

        $usages = [];
        $contractMethods = [];

        foreach ($class->getInterfaces() as $interface) {
            $nativeInterface = $interface->getNativeReflection();

            foreach ($nativeInterface->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $nativeInterface->getName()) {
                    continue;
                }

                $contractMethods[strtolower($method->getName())] = true;
                $usages[] = $this->usage(
                    $nativeInterface->getName(),
                    $method->getName(),
                    'Interface consumed by ' . $class->getName()
                );
            }
        }

        foreach ($class->getNativeReflection()->getMethods() as $method) {
            if (
                $method->getDeclaringClass()->getName() === $class->getName()
                && isset($contractMethods[strtolower($method->getName())])
            ) {
                $usages[] = $this->usage($class->getName(), $method->getName(), 'Implements a consumed interface');
            }
        }

        return $usages;
    }

    private function usage(string $className, string $methodName, string $note): ClassMethodUsage
    {
        return new ClassMethodUsage(
            UsageOrigin::createVirtual($this, VirtualUsageData::withNote($note)),
            new ClassMethodRef($className, $methodName, possibleDescendant: false),
        );
    }

    private function isTestFile(?string $fileName): bool
    {
        return null !== $fileName && str_starts_with($fileName, rtrim($this->testsDir, '/') . '/');
    }
}
