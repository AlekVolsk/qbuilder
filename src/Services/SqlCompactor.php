<?php

declare(strict_types=1);

namespace QBuilder\Services;

/**
 * Service for SQL query compaction.
 *
 * Collapses whitespace outside of string literals, quoted identifiers and comments.
 * Literals, quoted identifiers and comments are copied byte for byte.
 *
 * @internal
 */
final class SqlCompactor
{
    private const array CLOSING_QUOTES = ["'" => "'", '"' => '"', '`' => '`', '[' => ']'];

    /**
     * @param bool $backslashEscapes Whether backslash escapes the next character inside '...' and "..."
     *                               (MySQL default sql_mode, ClickHouse)
     */
    public function __construct(private readonly bool $backslashEscapes = false) {}

    /**
     * Compact SQL query by collapsing whitespace.
     *
     * A line comment keeps its terminating newline, so the rest of the query is not commented out.
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
        $length = \strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if (isset(self::CLOSING_QUOTES[$char])) {
                $end = $this->findQuotedEnd($sql, $i, self::CLOSING_QUOTES[$char]);
                $result .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ('-' === $char && '-' === $next) {
                $end = strpos($sql, "\n", $i);
                $end = false === $end ? $length : $end + 1;
                $result .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ('/' === $char && '*' === $next) {
                $end = strpos($sql, '*/', $i + 2);
                $end = false === $end ? $length : $end + 2;
                $result .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if (ctype_space($char)) {
                if ('' !== $result && ! ctype_space($result[\strlen($result) - 1])) {
                    $result .= ' ';
                }
            } else {
                $result .= $char;
            }

            ++$i;
        }

        return trim($result);
    }

    /**
     * Find position right after the closing quote of a quoted literal or identifier.
     *
     * The closing quote doubled inside is an escaped quote. Backslash escapes the next
     * character only in '...' and "..." when backslash escapes are enabled.
     *
     * @param string $sql          SQL query
     * @param int    $start        Position of the opening quote
     * @param string $closingQuote Closing quote character
     *
     * @return int Position after the closing quote, or query length for an unclosed quote
     */
    private function findQuotedEnd(string $sql, int $start, string $closingQuote): int
    {
        $length = \strlen($sql);
        $backslashEscapes = $this->backslashEscapes && ("'" === $closingQuote || '"' === $closingQuote);
        $i = $start + 1;

        while ($i < $length) {
            $char = $sql[$i];

            if ($backslashEscapes && '\\' === $char) {
                $i += 2;

                continue;
            }

            if ($char === $closingQuote) {
                if ($i + 1 < $length && $sql[$i + 1] === $closingQuote) {
                    $i += 2;

                    continue;
                }

                return $i + 1;
            }

            ++$i;
        }

        return $length;
    }
}
