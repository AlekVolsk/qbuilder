<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Builder\ConflictBuilderInterface;
use QBuilder\Exceptions\InvalidIdentifierException;
use QBuilder\Exceptions\InvalidQueryException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ConflictBuilderInjectionTest
{
    /**
     * @param \Closure(ConflictBuilderInterface): ConflictBuilderInterface $apply
     */
    #[DataProvider('provideIncrementAndDecrementRejectNonNumericOperandCases')]
    #[ExpectException(InvalidQueryException::class)]
    public function testIncrementAndDecrementRejectNonNumericOperand(\Closure $apply): void
    {
        $apply($this->conflictBuilder(QbConsts::DRIVER_PDO_MYSQL));
    }

    /**
     * @return iterable<string, array{\Closure(ConflictBuilderInterface): ConflictBuilderInterface}>
     */
    public static function provideIncrementAndDecrementRejectNonNumericOperandCases(): iterable
    {
        yield 'increment statement' => [
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->increment(
                'cnt',
                // @phpstan-ignore argument.type (non-numeric input on purpose)
                '1; DROP TABLE users; --'
            ),
        ];

        yield 'decrement subquery' => [
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->decrement(
                'cnt',
                // @phpstan-ignore argument.type (non-numeric input on purpose)
                '(SELECT 1)'
            ),
        ];

        yield 'increment empty string' => [
            // @phpstan-ignore argument.type (non-numeric input on purpose)
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->increment('cnt', ''),
        ];

        yield 'increment INF' => [
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->increment('cnt', INF),
        ];

        yield 'decrement NAN' => [
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->decrement('cnt', NAN),
        ];
    }

    public function testIncrementAndDecrementAcceptNumbers(): void
    {
        $qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);
        $cb = $qb->conflictBuilder()
            ->increment('a', ' 2.5 ')
            ->decrement('b', -3)
            ->increment('c', 1.5)
        ;

        $sql = $qb->insert('t')->insertRow(['id' => 1])->insertConflictHandler($cb)->build(true);

        Assert::same(
            $sql,
            'INSERT INTO `t` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `a` = `a` + 2.5, `b` = `b` - '
                . '-3, `c` = `c` + 1.5'
        );
    }

    /**
     * @param \Closure(ConflictBuilderInterface): ConflictBuilderInterface $apply
     */
    #[DataProvider('provideFragmentsCannotLeaveTheirAssignmentCases')]
    #[ExpectException(InvalidIdentifierException::class)]
    public function testFragmentsCannotLeaveTheirAssignment(string $driver, \Closure $apply): void
    {
        $apply($this->conflictBuilder($driver));
    }

    /**
     * @return iterable<string, array{string, \Closure(ConflictBuilderInterface): ConflictBuilderInterface}>
     */
    public static function provideFragmentsCannotLeaveTheirAssignmentCases(): iterable
    {
        yield 'expression subquery' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->expression(
                'note',
                '(SELECT password FROM users LIMIT 1)'
            ),
        ];

        yield 'expression RETURNING' => [
            QbConsts::DRIVER_PGSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->expression(
                'note',
                'note RETURNING password'
            ),
        ];

        yield 'case END breakout' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->caseExpression('note', [
                'WHEN 1 = 1 THEN 1 END, role = (SELECT 1) WHERE 1 = 1 OR CASE WHEN 1 THEN 1',
            ]),
        ];

        yield 'case subquery' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->caseExpression('note', [
                'WHEN 1 = 1 THEN (SELECT password FROM users)',
            ]),
        ];

        yield 'function top-level comma' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                'NOW(), role = 1'
            ),
        ];

        yield 'function closes parentheses' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                'UPPER(note)), (1'
            ),
        ];

        yield 'function quote inside identifier' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                "`a'`), role = (`'`"
            ),
        ];

        yield 'function backslash' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                "CONCAT('a\\', 1)"
            ),
        ];

        yield 'function hash comment' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                'NOW() # x'
            ),
        ];

        yield 'function unterminated literal' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                "CONCAT('a, 1)"
            ),
        ];

        yield 'function brackets outside MS SQL' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                '[a), x = (1]'
            ),
        ];

        yield 'function dollar quoting' => [
            QbConsts::DRIVER_PGSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                '$$x$$'
            ),
        ];

        yield 'function unclosed parenthesis' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                'UPPER(note'
            ),
        ];

        yield 'function server variable' => [
            QbConsts::DRIVER_MSSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                '@@version'
            ),
        ];
    }

    /**
     * @param \Closure(ConflictBuilderInterface): ConflictBuilderInterface $apply
     * @param non-empty-string                                             $expected
     */
    #[DataProvider('provideSelfContainedFragmentsAreAcceptedCases')]
    public function testSelfContainedFragmentsAreAccepted(string $driver, \Closure $apply, string $expected): void
    {
        $qb = new QueryBuilder($driver);
        $cb = $apply($this->conflictBuilder($driver, $qb));

        $sql = $qb->insert('t')->insertRow(['id' => 1])->insertConflictHandler($cb)->build(true);

        Assert::same($sql, $expected);
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     \Closure(ConflictBuilderInterface): ConflictBuilderInterface,
     *     non-empty-string,
     * }>
     */
    public static function provideSelfContainedFragmentsAreAcceptedCases(): iterable
    {
        yield 'expression function' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->expression(
                'updated_at',
                'NOW()'
            ),
            'INSERT INTO `t` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `updated_at` = NOW()',
        ];

        yield 'case with keywords inside literals' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->caseExpression('tier', [
                "WHEN total > 1000 THEN 'vip'",
                "WHEN status = 'it''s END' THEN \"select\"",
                'ELSE tier',
            ]),
            'INSERT INTO `t` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `tier` = '
                . "CASE WHEN total > 1000 THEN 'vip' WHEN status = 'it''s END' THEN \"select\" ELSE tier END",
        ];

        yield 'function comma inside call and literal' => [
            QbConsts::DRIVER_PDO_MYSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'label',
                "CONCAT(first_name, ', ', last_name)"
            ),
            "INSERT INTO `t` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `label` = CONCAT(first_name, ', ', last_name)",
        ];

        yield 'function PostgreSQL quoted identifier' => [
            QbConsts::DRIVER_PGSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                "COALESCE(\"note\", 'x')"
            ),
            'INSERT INTO "t" ("id") VALUES (1) ON CONFLICT ("id") DO UPDATE SET "note" = COALESCE("note", \'x\')',
        ];

        yield 'function MS SQL escaped bracket stays one identifier' => [
            QbConsts::DRIVER_MSSQL,
            static fn (ConflictBuilderInterface $cb): ConflictBuilderInterface => $cb->sqlFunction(
                'note',
                'UPPER([a]]b])'
            ),
            'MERGE INTO [t] AS target USING (VALUES (1)) AS source ([id]) ON target.[id] = source.[id] '
                . 'WHEN MATCHED THEN UPDATE SET [note] = UPPER([a]]b]) WHEN NOT MATCHED THEN INSERT ([id]) '
                . 'VALUES (source.[id]);',
        ];
    }

    private function conflictBuilder(string $driver, ?QueryBuilder $qb = null): ConflictBuilderInterface
    {
        $cb = ($qb ?? new QueryBuilder($driver))->conflictBuilder();

        return QbConsts::DRIVER_PDO_MYSQL === $driver ? $cb : $cb->conflictTarget(['id']);
    }
}
