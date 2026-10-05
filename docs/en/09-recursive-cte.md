# Recursive Queries (CTE)

Recursive CTEs (Common Table Expressions) let you work with hierarchical data such as category trees, organizational structures, dependency graphs, and so on.

## Database Support

- ✅ MySQL 8.0+
- ✅ MariaDB 10.2+
- ✅ PostgreSQL
- ✅ MS SQL Server
- ✅ Oracle
- ❌ ClickHouse (does not support recursive CTEs)
- ❌ SQLite 3.8.3+ (support is limited)

## Recursive CTE Basics

A recursive CTE consists of three parts:

1. **Base Query (anchor query)** - the starting point of the recursion
2. **Recursive Query** - the query that references the CTE
3. **Final Select** - the final SELECT that uses the CTE result

## Creating a Recursive CTE

```php
use QBuilder\Builder\RecursiveCteBuilder;

// Create via QueryBuilder
$cte = $qb->recursiveCte('TableName');

// Or directly
$cte = new RecursiveCteBuilder($qb, 'TableName');
```

## Example 1: Category Tree with Levels

Building a full category tree while counting the nesting level:

```php
use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Anchor query: root categories (no parent)
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '1 AS level')
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Recursive query: child categories
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`ct`.`level` + 1 AS level'
    )
    ->from('categories', 'c')
    ->innerJoin('category_tree', 'ct', $joinCondition);

// Final query: select all categories
$finalQuery = $qb->subQuery()
    ->select('*')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('level')->asc('name'));

// Build the CTE
$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Result:
// WITH RECURSIVE `category_tree` AS (
//     SELECT `id`, `name`, `parent_id`, 1 AS level
//     FROM `categories`
//     WHERE (`parent_id` IS NULL)
//     UNION ALL
//     SELECT `c`.`id`, `c`.`name`, `c`.`parent_id`, `ct`.`level` + 1 AS level
//     FROM `categories` AS `c`
//     INNER JOIN `category_tree` AS `ct` ON (`ct`.`id` = `c`.`parent_id`)
// )
// SELECT * FROM `category_tree`
// ORDER BY `level` ASC, `name` ASC
```

## Example 2: Path from Root to Node

Building the full path from the root category to each node:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Anchor query: root categories
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', Field::set('name', '', 'path'))
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Recursive query: append to the path
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        Field::set("CONCAT(ct.path, ' > ', c.name)", '', 'path')
    )
    ->from('categories', 'c')
    ->innerJoin('category_tree', 'ct', $joinCondition);

// Final query
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'path')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('path'));

$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Result:
// Electronics
// Electronics > Computers
// Electronics > Computers > Laptops
// Electronics > Phones
// Home & Garden
// Home & Garden > Furniture
```

## Example 3: All Ancestors of a Node

Getting all parent categories for a given category:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$categoryId = 15;

$qb = new QueryBuilder();

// Anchor query: the starting category
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS level')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Recursive query: parent categories
$joinCondition = ConditionJoin::create($qb, 'parent_id', 'id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`a`.`level` + 1 AS level'
    )
    ->from('categories', 'c')
    ->innerJoin('ancestors', 'a', $joinCondition);

// Final query: sort from root to leaf
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'level')
    ->from('ancestors')
    ->orderBy(ConditionBy::orderBy()->desc('level'));

$cte = $qb->recursiveCte('ancestors');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

## Example 4: All Descendants of a Node

Getting all child categories for a given category:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$categoryId = 5;

$qb = new QueryBuilder();

// Anchor query: the starting category
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS depth')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Recursive query: child categories
$joinCondition = ConditionJoin::create($qb, 'id', 'parent_id', 'c');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 'c'),
        Field::set('name', 'c'),
        Field::set('parent_id', 'c'),
        '`d`.`depth` + 1 AS depth'
    )
    ->from('categories', 'c')
    ->innerJoin('descendants', 'd', $joinCondition);

// Final query
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'depth')
    ->from('descendants')
    ->orderBy(ConditionBy::orderBy()->asc('depth')->asc('name'));

$cte = $qb->recursiveCte('descendants');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

## Example 5: Dependency Graph (Cycle Detection)

Building a dependency graph with protection against cycles:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Anchor query: starting nodes with no dependencies
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'depends_on', Field::set('CAST(id AS CHAR(1000))', '', 'path'))
    ->from('tasks')
    ->where()->isNull('depends_on')->end();

// Recursive query: dependent tasks
$joinCondition = ConditionJoin::create($qb, 'id', 'depends_on', 't');

$recursiveQuery = $qb->subQuery()
    ->select(
        Field::set('id', 't'),
        Field::set('name', 't'),
        Field::set('depends_on', 't'),
        Field::set("CONCAT(dg.path, ',', t.id)", '', 'path')
    )
    ->from('tasks', 't')
    ->innerJoin('dependency_graph', 'dg', $joinCondition)
    ->where()->raw("FIND_IN_SET(t.id, dg.path) = 0")->end(); // Cycle protection

// Final query
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'path')
    ->from('dependency_graph')
    ->orderBy(ConditionBy::orderBy()->asc('id'));

$cte = $qb->recursiveCte('dependency_graph');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();
```

## RecursiveCteBuilder Methods

### `baseQuery(QueryBuilder $query): self`

Sets the anchor query (the starting point of the recursion).

**Requirements:**

- Must be a SELECT query
- Defines the column structure for the whole CTE

### `recursiveQuery(QueryBuilder $query): self`

Sets the recursive query, which references the CTE name.

**Requirements:**

- Must be a SELECT query
- Must contain a JOIN with the CTE name
- The column structure must match baseQuery

### `finalSelect(QueryBuilder $query): self`

Sets the final SELECT, which uses the CTE result.

**Requirements:**

- Must be a SELECT query
- May contain ORDER BY, LIMIT, GROUP BY, etc.

### `build(bool $compact = false): string`

Builds the final SQL query.

**Parameters:**

- `$compact` - remove extra whitespace and line breaks

**Returns:** the ready-to-use SQL query

## Closure Support

Closures are not used for recursive CTEs, since three separate queries must be defined explicitly.

## Limitations and Best Practices

### Limitations

1. **Maximum recursion depth**
   - MySQL/MariaDB: 1000 by default (configurable via `cte_max_recursion_depth`)
   - PostgreSQL: no limit by default
   - MS SQL Server: 100 by default (configurable via `MAXRECURSION`)

2. **Column structure**
   - All queries (base, recursive, final) must have compatible column types

3. **Performance**
   - Recursive queries can be slow on large trees
   - It is recommended to add depth-limiting conditions

### Best Practices

1. **Protection against infinite recursion**

```php
// Add a depth limit
->where()->lt('level', 10)->end()

// Or use FIND_IN_SET to detect cycles
->where()->raw("FIND_IN_SET(id, path) = 0")->end()
```

1. **Indexes**

```sql
-- For hierarchical structures
CREATE INDEX idx_parent_id ON categories(parent_id);
CREATE INDEX idx_id_parent ON categories(id, parent_id);
```

1. **Optimization**

```php
// Use WHERE in baseQuery to limit the initial selection
$baseQuery->where()->eq('status', 'active')->end();

// Add conditions to recursiveQuery
$recursiveQuery->where()->eq('status', 'active')->end();
```

1. **Testing**

```php
// Always test on small datasets first
$finalQuery->limit(100);

// Check the recursion depth
$finalQuery->select('*', 'MAX(level) AS max_depth')->from('category_tree');
```

---

[← INSERT, UPDATE, DELETE](08-insert-update-delete.md) · [Contents](index.md) · [Advanced Examples →](10-examples.md)
