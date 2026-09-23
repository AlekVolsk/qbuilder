# Advanced Examples

## Example 1: Order Report with Subqueries

```php
use QBuilder\Condition\Field;
use QBuilder\Condition\ConditionBy;

$qb = new QueryBuilder(QbConsts::DRIVER_PDO_MYSQL);

$sql = $qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users'),
    Field::set('email', 'users'),
    Field::subquery(
        $qb->subQuery()
            ->select(Field::set('COUNT(*)'))
            ->from('orders')
            ->where()
                ->eqField('orders', 'user_id', 'users', 'id')
                ->and()->eq('status', 'completed')
                ->end(),
        'completed_orders'
    ),
    Field::subquery(
        $qb->subQuery()
            ->select(Field::set('SUM(total)'))
            ->from('orders')
            ->where()
                ->eqField('orders', 'user_id', 'users', 'id')
                ->end(),
        'total_spent'
    )
)
->from('users')
->where()
    ->eq('status', 'active')
    ->end()
->orderBy(ConditionBy::orderBy()->desc('total_spent'))
->limit(10)
->build();
```

## Example 2: Complex JOIN with GROUP_CONCAT

```php
use QBuilder\Condition\Field;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;

$layerSubquery = $qb->subQuery()
    ->select(
        Field::set('perforation_id', 'well_perforation_layer'),
        'GROUP_CONCAT(`layer`.`name`) AS layer_names',
        'GROUP_CONCAT(`well_perforation_layer`.`layer_id`) AS layer_ids'
    )
    ->from('well_perforation_layer')
    ->leftJoin(
        'layer',
        '',
        ConditionJoin::create($qb, 'id', 'layer_id', 'well_perforation_layer')
    )
    ->groupBy(ConditionBy::groupBy()->add('perforation_id', 'well_perforation_layer'));

$sql = $qb->select(
    Field::set('*', 'perforations'),
    Field::set('name', 'wells', 'well_name'),
    Field::set('layer_names', 'layers'),
    Field::set('layer_ids', 'layers')
)
->from('perforations')
->leftJoin('wells', '', ConditionJoin::create($qb, 'id', 'well_id', 'perforations'))
->leftJoinFromSelect(
    $layerSubquery,
    'layers',
    ConditionJoin::create($qb, 'perforation_id', 'id', 'perforations')
)
->build();
```

## Example 3: Nested Subqueries with Calculations

```php
use QBuilder\Condition\Field;
use QBuilder\Condition\ConditionJoin;

$innerSubquery = $qb->subQuery()
    ->select(
        Field::subquery(
            $qb->subQuery()
                ->select(Field::set('MAX(md)'))
                ->from('inclinometry')
                ->where()
                    ->eqField('inclinometry', 'well_id', 'p', 'well_id')
                    ->and()->ltField('inclinometry', 'md', 'p', 'top_md')
                    ->end(),
            'top_md_min'
        ),
        Field::subquery(
            $qb->subQuery()
                ->select(Field::set('MIN(md)'))
                ->from('inclinometry')
                ->where()
                    ->eqField('inclinometry', 'well_id', 'p', 'well_id')
                    ->and()->gteField('inclinometry', 'md', 'p', 'top_md')
                    ->end(),
            'top_md_max'
        ),
        Field::set('*', 'p')
    )
    ->from('wells_perforations', 'p');

$outerSubquery = $qb->subQuery()
    ->select(
        Field::set('*', 't'),
        Field::set('IF(top_md_max, top_tvdss_min + (top_md - top_md_min) * (top_tvdss_max - top_tvdss_min) / (top_md_max - top_md_min), (top_md_min + top_tvdss_min - top_md))', '', 'top_tvd_calc')
    )
    ->from($innerSubquery, 't');

$sql = $qb->select(
    Field::set('*', 'perforations'),
    Field::set('name', 'wells', 'well_name')
)
->from($outerSubquery, 'perforations')
->leftJoin('wells', '', ConditionJoin::create($qb, 'id', 'well_id', 'perforations'))
->build();
```

## Example 4: UNION Queries

```php
use QBuilder\Builder\UnionBuilder;

$query1 = $qb->select('id', 'name', '"customer" AS type')
    ->from('customers')
    ->where()
        ->eq('status', 'active')
        ->end();

$query2 = $qb->subQuery()
    ->select('id', 'name', '"supplier" AS type')
    ->from('suppliers')
    ->where()
        ->eq('status', 'active')
        ->end();

$union = new UnionBuilder($qb);
$union->add($query1);
$union->add($query2);

$sql = $union->build();

// (SELECT `id`, `name`, "customer" AS type FROM `customers` WHERE (`status` = 'active'))
// UNION
// (SELECT `id`, `name`, "supplier" AS type FROM `suppliers` WHERE (`status` = 'active'))
```

---

[← Recursive Queries (CTE)](09-recursive-cte.md) · [Contents](index.md) · [Security →](11-security.md)
