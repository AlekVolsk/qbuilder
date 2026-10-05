<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use Testo\Assert;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class LinterTest
{
    public function testNoTabsInFiles(): void
    {
        $filesWithTabs = [];
        $phpFiles = $this->getPhpFiles();

        foreach ($phpFiles as $file) {
            $content = file_get_contents((string) $file);
            if (false === $content) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $lineNumber => $line) {
                if (str_contains($line, "\t")) {
                    $filesWithTabs[] = \sprintf(
                        '%s:%d',
                        str_replace(__DIR__ . '/../', '', (string) $file),
                        $lineNumber + 1
                    );
                }
            }
        }

        $message = \sprintf(
            "Found tabs in %d file(s):\n%s",
            \count($filesWithTabs),
            implode("\n", $filesWithTabs)
        );

        Assert::blank($filesWithTabs, $message);
    }

    /**
     * @return array<string>
     */
    private function getPhpFiles(): array
    {
        $files = [];
        $directories = [__DIR__ . '/../src', __DIR__];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RegexIterator::GET_MATCH);

            foreach ($phpFiles as $file) {
                /** @var array<string>|false $file */
                if (false === $file) {
                    continue;
                }

                $filePath = $file[0];

                if (
                    str_contains($filePath, '/vendor/')
                    || str_contains($filePath, '/coverage/')
                    || str_contains($filePath, '/build-dev/')
                    || str_contains($filePath, '\vendor\\')
                    || str_contains($filePath, '\coverage\\')
                    || str_contains($filePath, '\build-dev\\')
                ) {
                    continue;
                }

                $files[] = $filePath;
            }
        }

        return $files;
    }
}
