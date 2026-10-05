<?php

declare(strict_types=1);

namespace QBuilder\Tests;

use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\Exceptions\UnsupportedFeatureException;
use QBuilder\QbConsts;
use QBuilder\QueryBuilder;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * @internal
 */
#[Test]
final class ClickhouseDriverTest
{
    public function testClickhouseQuoting(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('id', 'name')->from('users')->build(true);

        Assert::same($sql, 'SELECT `id`, `name` FROM `users`');
    }

    public function testClickhouseLimitOffsetSyntax(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('events')->limit(100, 50)->build(true);

        Assert::same($sql, 'SELECT * FROM `events` LIMIT 50, 100');
    }

    public function testClickhouseLimitOnly(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')->from('events')->limit(100)->build(true);

        Assert::same($sql, 'SELECT * FROM `events` LIMIT 100');
    }

    public function testClickhouseLimitWithTies(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('event_type', Field::set('COUNT(*)', '', 'total'))
            ->from('events')
            ->orderBy(ConditionBy::orderBy()->add('total', '', 'DESC'))
            ->limitWithTies(5)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `event_type`, COUNT(*) AS `total` FROM `events` '
        . 'ORDER BY `total` DESC LIMIT 5 WITH TIES');
    }

    public function testClickhouseInsertSimple(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('events')->insertRow(['event_time' => '2025-01-01', 'event_type' => 'click'])
            ->build(true)
        ;

        $expected = 'INSERT INTO `events` (`event_time`, `event_type`) '
            . "VALUES ('2025-01-01', 'click')";

        Assert::same($sql, $expected);
    }

    public function testClickhouseInsertMultipleRows(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->insert('events')
            ->insertRow(['event_time' => '2025-01-01', 'event_type' => 'click'])
            ->insertRow(['event_time' => '2025-01-02', 'event_type' => 'view'])
            ->build(true)
        ;

        $expected = 'INSERT INTO `events` (`event_time`, `event_type`) '
            . "VALUES ('2025-01-01', 'click'), ('2025-01-02', 'view')";

        Assert::same($sql, $expected);
    }

    public function testClickhouseUpdateMutation(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->update('events')->updateRow(['status' => 'processed'])
            ->where()->eq('id', 123)->end()
            ->build(true)
        ;

        Assert::same($sql, 'ALTER TABLE `events` UPDATE `status` = \'processed\' WHERE (`id` = 123)');
    }

    public function testClickhouseUpdateWithoutWhereThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining(
                'ClickHouse ALTER TABLE UPDATE requires WHERE clause for safety. '
                . 'This is a slow mutation operation, not suitable for frequent updates.'
            )
        ;

        $qb->update('events')->updateRow(['status' => 'processed'])->build(true);
    }

    public function testClickhouseDeleteMutation(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->delete('events')
            ->where()->lt('event_time', '2024-01-01')->end()
            ->build(true)
        ;

        Assert::same($sql, "ALTER TABLE `events` DELETE WHERE (`event_time` < '2024-01-01')");
    }

    public function testClickhouseDeleteWithoutWhereThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining(
                'ClickHouse ALTER TABLE DELETE requires WHERE clause for safety. '
                . 'This is a slow mutation operation, not suitable for frequent deletes.'
            )
        ;

        $qb->delete('events')->build(true);
    }

    public function testClickhouseSelectWithJoin(): void
    {
        $qb = $this->getQueryBuilder();

        $joinCondition = new ConditionJoin($qb);
        $joinCondition->eqField('u', 'id', 'e', 'user_id');

        $sql = $qb->select(
            Field::set('id', 'e'),
            Field::set('event_type', 'e'),
            Field::set('name', 'u')
        )
            ->from('events', 'e')
            ->leftJoin('users', 'u', $joinCondition)
            ->where()->gte(Field::set('event_time', 'e'), '2025-01-01')->end()
            ->limit(1000)
            ->build(true)
        ;

        $expected = 'SELECT `e`.`id`, `e`.`event_type`, `u`.`name` '
            . 'FROM `events` AS `e` '
            . 'LEFT JOIN `users` AS `u` ON (`u`.`id` = `e`.`user_id`) '
            . "WHERE (`e`.`event_time` >= '2025-01-01') "
            . 'LIMIT 1000';

        Assert::same($sql, $expected);
    }

    public function testClickhouseGroupByHaving(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('event_type', Field::set('COUNT(*)', '', 'total'))
            ->from('events')
            ->groupBy(ConditionBy::groupBy()->add('event_type'))
            ->having()->gt(Field::set('COUNT(*)', '', 'total'), 100)->end()
            ->orderBy(ConditionBy::orderBy()->desc('total'))
            ->build(true)
        ;

        $expected = 'SELECT `event_type`, COUNT(*) AS `total` FROM `events` '
            . 'GROUP BY `event_type` HAVING (COUNT(*) > 100) ORDER BY `total` DESC';

        Assert::same($sql, $expected);
    }

    public function testClickhouseSubqueryInWhere(): void
    {
        $qb = $this->getQueryBuilder();

        $subquery = $qb->subQuery()
            ->select('user_id')
            ->from('orders')
            ->where()->gte('total', 1000)->end()
        ;

        $sql = $qb->select('*')
            ->from('users')
            ->where()->inSubquery('id', $subquery)->end()
            ->limit(100)
            ->build(true)
        ;

        $expected = 'SELECT * FROM `users` WHERE (`id` IN '
            . '(SELECT `user_id` FROM `orders` WHERE (`total` >= 1000))) LIMIT 100';

        Assert::same($sql, $expected);
    }

    public function testClickhouseConflictBuilderThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining(
                'ClickHouse does not support conflict handlers (ON DUPLICATE KEY UPDATE / ON CONFLICT). '
                . 'Use ReplacingMergeTree or CollapsingMergeTree engines for data deduplication.'
            )
        ;

        $conflictBuilder = $qb->conflictBuilder()->set('name', 'updated');

        $qb->insert('events')->insertRow(['id' => 1, 'name' => 'test'])
            ->insertConflictHandler($conflictBuilder)
            ->build(true)
        ;
    }

    public function testClickhouseProcedureThrowsException(): void
    {
        $qb = $this->getQueryBuilder();

        Expect::exception(UnsupportedFeatureException::class)
            ->withMessageContaining('ClickHouse does not support stored procedures')
        ;

        $qb->procedure('test_proc')->build(true);
    }

    public function testClickhouseEscapeLikePattern(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('*')
            ->from('users')
            ->where()->like('name', '50%', QbConsts::LIKE_RIGHT)->end()
            ->build(true)
        ;

        Assert::same($sql, "SELECT * FROM `users` WHERE (`name` LIKE '50\\\\%%')");
    }

    public function testClickhouseDistinctQuery(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('event_type')->from('events')->distinct()->build(true);

        Assert::same($sql, 'SELECT DISTINCT `event_type` FROM `events`');
    }

    public function testClickhouseDistinctMultipleFields(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id', 'event_type')->distinct()->from('events')->build(true);

        Assert::same($sql, 'SELECT DISTINCT `user_id`, `event_type` FROM `events`');
    }

    public function testClickhouseDistinctWithWhere(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('user_id')->distinct()->from('events')
            ->where()->eq('status', 'active')->end()
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT `user_id` FROM `events` WHERE (`status` = \'active\')');
    }

    public function testClickhouseDistinctWithOrderBy(): void
    {
        $qb = $this->getQueryBuilder();
        $orderBy = ConditionBy::orderBy()->asc('category');
        $sql = $qb->select('category')->distinct()->from('products')
            ->orderBy($orderBy)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT DISTINCT `category` FROM `products` ORDER BY `category` ASC');
    }

    public function testClickhouseFinalModifier(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('telemetry.sensor_data')->final()->build(true);

        Assert::same($sql, 'SELECT * FROM `telemetry`.`sensor_data` FINAL');
    }

    public function testClickhouseFinalWithAlias(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select()->from('telemetry.sensor_data', 't')->final()->build(true);

        Assert::same($sql, 'SELECT * FROM `telemetry`.`sensor_data` FINAL AS `t`');
    }

    public function testClickhouseFinalWithWhereAndLimit(): void
    {
        $qb = $this->getQueryBuilder();
        $sql = $qb->select('well_id', 'measure_time', 'value')
            ->from('telemetry.adku_sensor_data')
            ->final()
            ->where()->eq('well_id', 123)->end()
            ->limit(10)
            ->build(true)
        ;

        Assert::same($sql, 'SELECT `well_id`, `measure_time`, `value` FROM `telemetry`.`adku_sensor_data` FINAL '
        . 'WHERE (`well_id` = 123) LIMIT 10');
    }

    public function testClickhouseFinalNotAppliedToSubquery(): void
    {
        $qb = $this->getQueryBuilder();
        $subQuery = $qb->subQuery()->select('id')->from('temp_users');
        $sql = $qb->select()->from($subQuery, 'tu')->final()->build(true);

        Assert::same($sql, 'SELECT * FROM (SELECT `id` FROM `temp_users`) AS `tu`');
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $qb = new QueryBuilder();
        $qb->setDriver(QbConsts::DRIVER_CLICKHOUSE);

        return $qb;
    }
}
