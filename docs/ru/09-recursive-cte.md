# Рекурсивные запросы (CTE)

Рекурсивные CTE (Common Table Expressions) позволяют работать с иерархическими данными, такими как деревья категорий, организационные структуры, графы зависимостей и т.д.

## Поддержка СУБД

- ✅ MySQL 8.0+
- ✅ MariaDB 10.2+
- ✅ PostgreSQL
- ✅ MS SQL Server
- ✅ Oracle
- ❌ ClickHouse (не поддерживает рекурсивные CTE)
- ❌ SQLite 3.8.3+ (поддержка ограничена)

## Основы рекурсивных CTE

Рекурсивный CTE состоит из трёх частей:

1. **Base Query (якорный запрос)** - начальная точка рекурсии
2. **Recursive Query (рекурсивный запрос)** - запрос, ссылающийся на CTE
3. **Final Select** - финальный SELECT, использующий результат CTE

## Создание рекурсивного CTE

```php
use QBuilder\Builder\RecursiveCteBuilder;

// Создание через QueryBuilder
$cte = $qb->recursiveCte('TableName');

// Или напрямую
$cte = new RecursiveCteBuilder($qb, 'TableName');
```

## Пример 1: Дерево категорий с уровнями

Построение полного дерева категорий с подсчётом уровня вложенности:

```php
use QBuilder\Builder\RecursiveCteBuilder;
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Якорный запрос: корневые категории (без родителя)
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '1 AS level')
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Рекурсивный запрос: дочерние категории
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

// Финальный запрос: выборка всех категорий
$finalQuery = $qb->subQuery()
    ->select('*')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('level')->asc('name'));

// Построение CTE
$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Результат:
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

## Пример 2: Путь от корня до узла

Построение полного пути от корневой категории до каждого узла:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Якорный запрос: корневые категории
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', Field::set('name', '', 'path'))
    ->from('categories')
    ->where()->isNull('parent_id')->end();

// Рекурсивный запрос: добавляем путь
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

// Финальный запрос
$finalQuery = $qb->subQuery()
    ->select('id', 'name', 'path')
    ->from('category_tree')
    ->orderBy(ConditionBy::orderBy()->asc('path'));

$cte = $qb->recursiveCte('category_tree');
$sql = $cte->baseQuery($baseQuery)
    ->recursiveQuery($recursiveQuery)
    ->finalSelect($finalQuery)
    ->build();

// Результат:
// Electronics
// Electronics > Computers
// Electronics > Computers > Laptops
// Electronics > Phones
// Home & Garden
// Home & Garden > Furniture
```

## Пример 3: Все предки узла

Получение всех родительских категорий для заданной категории:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$categoryId = 15;

$qb = new QueryBuilder();

// Якорный запрос: начальная категория
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS level')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Рекурсивный запрос: родительские категории
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

// Финальный запрос: сортировка от корня к листу
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

## Пример 4: Все потомки узла

Получение всех дочерних категорий для заданной категории:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$categoryId = 5;

$qb = new QueryBuilder();

// Якорный запрос: начальная категория
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'parent_id', '0 AS depth')
    ->from('categories')
    ->where()->eq('id', $categoryId)->end();

// Рекурсивный запрос: дочерние категории
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

// Финальный запрос
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

## Пример 5: Граф зависимостей (обнаружение циклов)

Построение графа зависимостей с защитой от циклов:

```php
use QBuilder\Condition\ConditionBy;
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;
use QBuilder\QueryBuilder;

$qb = new QueryBuilder();

// Якорный запрос: начальные узлы без зависимостей
$baseQuery = $qb->subQuery()
    ->select('id', 'name', 'depends_on', Field::set('CAST(id AS CHAR(1000))', '', 'path'))
    ->from('tasks')
    ->where()->isNull('depends_on')->end();

// Рекурсивный запрос: зависимые задачи
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
    ->where()->raw("FIND_IN_SET(t.id, dg.path) = 0")->end(); // Защита от циклов

// Финальный запрос
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

## Методы RecursiveCteBuilder

### `baseQuery(QueryBuilder $query): self`

Устанавливает якорный запрос (начальную точку рекурсии).

**Требования:**

- Должен быть SELECT запрос
- Определяет структуру столбцов для всего CTE

### `recursiveQuery(QueryBuilder $query): self`

Устанавливает рекурсивный запрос, который ссылается на имя CTE.

**Требования:**

- Должен быть SELECT запрос
- Должен содержать JOIN с именем CTE
- Структура столбцов должна совпадать с baseQuery

### `finalSelect(QueryBuilder $query): self`

Устанавливает финальный SELECT, который использует результат CTE.

**Требования:**

- Должен быть SELECT запрос
- Может содержать ORDER BY, LIMIT, GROUP BY и т.д.

### `build(bool $compact = false): string`

Строит финальный SQL запрос.

**Параметры:**

- `$compact` - удалить лишние пробелы и переносы строк

**Возвращает:** готовый SQL запрос

## Поддержка замыканий

Для рекурсивных CTE замыкания не применяются, так как требуется явное определение трёх отдельных запросов.

## Ограничения и best practices

### Ограничения

1. **Максимальная глубина рекурсии**
   - MySQL/MariaDB: по умолчанию 1000 (настраивается через `cte_max_recursion_depth`)
   - PostgreSQL: по умолчанию нет ограничений
   - MS SQL Server: по умолчанию 100 (настраивается через `MAXRECURSION`)

2. **Структура столбцов**
   - Все запросы (base, recursive, final) должны иметь совместимые типы столбцов

3. **Производительность**
   - Рекурсивные запросы могут быть медленными на больших деревьях
   - Рекомендуется добавлять условия ограничения глубины

### Best Practices

1. **Защита от бесконечной рекурсии**

```php
// Добавьте ограничение глубины
->where()->lt('level', 10)->end()

// Или используйте FIND_IN_SET для обнаружения циклов
->where()->raw("FIND_IN_SET(id, path) = 0")->end()
```

1. **Индексы**

```sql
-- Для иерархических структур
CREATE INDEX idx_parent_id ON categories(parent_id);
CREATE INDEX idx_id_parent ON categories(id, parent_id);
```

1. **Оптимизация**

```php
// Используйте WHERE в baseQuery для ограничения начальной выборки
$baseQuery->where()->eq('status', 'active')->end();

// Добавляйте условия в recursiveQuery
$recursiveQuery->where()->eq('status', 'active')->end();
```

1. **Тестирование**

```php
// Всегда тестируйте на небольших данных
$finalQuery->limit(100);

// Проверяйте глубину рекурсии
$finalQuery->select('*', 'MAX(level) AS max_depth')->from('category_tree');
```

---

[← INSERT, UPDATE, DELETE](08-insert-update-delete.md) · [Содержание](index.md) · [Сложные примеры →](10-examples.md)
