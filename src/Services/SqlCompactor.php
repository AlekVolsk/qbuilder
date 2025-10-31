<?php

namespace QBuilder\Services;

/**
 * Service for SQL query compaction.
 *
 * Removes extra whitespace from SQL queries while preserving string literals.
 * Handles escaped quotes properly to prevent data corruption.
 */
class SqlCompactor
{
    /**
     * Compact SQL query by removing extra whitespace.
     * Preserves spaces and newlines inside string literals (single and double quotes).
     * Correctly handles SQL-standard quote escaping (doubled quotes: '' or "").
     *
     * @param string $sql SQL query to compact
     *
     * @return string Compacted SQL query
     *
     * @example
     * $compactor = new SqlCompactor();
     * $sql = $compactor->compact($sql);
     */
    public function compact(string $sql): string
    {
        $result = '';
        $inString = false;
        $stringChar = '';
        $length = \strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];

            if ("'" === $char || '"' === $char) {
                $nextChar = ($i + 1 < $length) ? $sql[$i + 1] : '';

                if (! $inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar && $nextChar !== $stringChar) {
                    $inString = false;
                    $stringChar = '';
                } elseif ($char === $stringChar && $nextChar === $stringChar) {
                    $result .= $char.$nextChar;
                    ++$i;

                    continue;
                }
                $result .= $char;

                continue;
            }

            if ($inString) {
                $result .= $char;

                continue;
            }

            if (ctype_space($char)) {
                if ('' === $result || ' ' !== $result[\strlen($result) - 1]) {
                    $result .= ' ';
                }
            } else {
                $result .= $char;
            }
        }

        return trim($result);
    }
}
