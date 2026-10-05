# WHERE условия

QueryBuilder поддерживает два синтаксиса для построения WHERE условий:

1. **Стандартный синтаксис** - классический подход с явным вызовом `end()`
2. **Альтернативный синтаксис с замыканиями** - современный подход без необходимости вызова `end()`

Оба синтаксиса полностью эквивалентны и генерируют одинаковый SQL.

## Базовые операторы

### Равенство (=)

**Стандартный синтаксис:**

```php
$qb->select('*')
    ->from('users')
    ->where()
        ->eq('status', 'active')
        ->end();

// WHERE (`status` = 'active')
```

**Альтернативный синтаксис с замыканием:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->select('*')
    ->from('users')
    ->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));

// WHERE (`status` = 'active')
```

### Неравенство (!=)

**Стандартный синтаксис:**

```php
$qb->where()
    ->neq('status', 'deleted')
    ->end();

// WHERE (`status` != 'deleted')
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->neq('status', 'deleted'));

// WHERE (`status` != 'deleted')
```

### Больше / Меньше

**Стандартный синтаксис:**

```php
$qb->where()
    ->gt('age', 18)
    ->and()->lt('age', 65)
    ->end();

// WHERE (`age` > 18) AND (`age` < 65)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gt('age', 18)->and()->lt('age', 65)
);

// WHERE (`age` > 18) AND (`age` < 65)
```

### Больше или равно / Меньше или равно

**Стандартный синтаксис:**

```php
$qb->where()
    ->gte('price', 100)
    ->and()->lte('price', 1000)
    ->end();

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gte('price', 100)->and()->lte('price', 1000)
);

// WHERE (`price` >= 100) AND (`price` <= 1000)
```

## Логические операторы

### AND

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()->eq('verified', 1)
    ->and()->gt('balance', 0)
    ->end();

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->eq('verified', 1)
      ->and()->gt('balance', 0)
);

// WHERE (`status` = 'active') AND (`verified` = 1) AND (`balance` > 0)
```

### OR

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->or()->eq('status', 'pending')
    ->end();

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->or()->eq('status', 'pending')
);

// WHERE (`status` = 'active') OR (`status` = 'pending')
```

### Комбинация AND и OR

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('country', 'US')
    ->and()
    ->startGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->endGroup()
    ->end();

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

**Стандартный синтаксис (используя andGroup/orGroup):**

```php
$qb->where()
    ->eq('country', 'US')
    ->andGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->end()
    ->end();

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

**Альтернативный синтаксис с замыканиями:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('country', 'US')
      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2
        ->eq('status', 'active')
        ->or()
        ->eq('status', 'pending'))
);

// WHERE (`country` = 'US') AND ((`status` = 'active') OR (`status` = 'pending'))
```

## Специальные операторы

### IN

**Стандартный синтаксис:**

```php
$qb->where()
    ->in('status', ['active', 'pending', 'verified'])
    ->end();

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->in('status', ['active', 'pending', 'verified'])
);

// WHERE (`status` IN ('active', 'pending', 'verified'))
```

### NOT IN

**Стандартный синтаксис:**

```php
$qb->where()
    ->notIn('status', ['deleted', 'banned'])
    ->end();

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notIn('status', ['deleted', 'banned'])
);

// WHERE (`status` NOT IN ('deleted', 'banned'))
```

### Список значений IN / NOT IN

Правила одинаковы для WHERE, HAVING и JOIN:

- элементы массива берутся как есть — пустая строка `''` и `null` попадают в список (`IN ('', NULL)`); тип элемента форматируется по правилам из [Безопасности](11-security.md#форматирование-значений-по-типу);
- строка разбивается по запятой, пустые элементы отбрасываются: `in('status', 'active,,pending')` → `IN ('active', 'pending')`;
- пустой список: `in()` → `1 = 0`, `notIn()` → `1 = 1`.

**`NOT IN` с `NULL` в списке никогда не выполняется.** `id NOT IN (1, NULL)` не бывает истинным, поэтому запрос не возвращает ни одной строки — проверено на MySQL, PostgreSQL и SQLite. Билдер передаёт `NULL` без изменений; убирайте `null` из списка `notIn()`, если пустой результат не нужен, а строки с `NULL` оставляйте явным `isNull()`.

```php
$qb->where()
    ->in('code', ['0012', 7, '', null])
    ->end();

// WHERE (`code` IN ('0012', 7, '', NULL))
```

### BETWEEN

**Стандартный синтаксис:**

```php
$qb->where()
    ->between('age', 18, 65)
    ->end();

// WHERE (`age` BETWEEN 18 AND 65)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->between('age', 18, 65)
);

// WHERE (`age` BETWEEN 18 AND 65)
```

### NOT BETWEEN

**Стандартный синтаксис:**

```php
$qb->where()
    ->notBetween('age', 18, 65)
    ->end();

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBetween('age', 18, 65)
);

// WHERE (`age` NOT BETWEEN 18 AND 65)
```

### LIKE

**Стандартный синтаксис:**

```php
$qb->where()
    ->like('name', 'John')
    ->end();

// WHERE (`name` LIKE '%John%')
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John')
);

// WHERE (`name` LIKE '%John%')
```

**LIKE с типами границ:**

**Стандартный синтаксис:**

```php
use QBuilder\QbConsts;

// Содержит (по умолчанию)
$qb->where()->like('name', 'John', QbConsts::LIKE_FULL)->end();
// WHERE (`name` LIKE '%John%')

// Начинается с
$qb->where()->like('name', 'John', QbConsts::LIKE_RIGHT)->end();
// WHERE (`name` LIKE 'John%')

// Заканчивается на
$qb->where()->like('name', 'John', QbConsts::LIKE_LEFT)->end();
// WHERE (`name` LIKE '%John')
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;
use QBuilder\QbConsts;

// Содержит (по умолчанию)
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_FULL)
);
// WHERE (`name` LIKE '%John%')

// Начинается с
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_RIGHT)
);
// WHERE (`name` LIKE 'John%')

// Заканчивается на
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->like('name', 'John', QbConsts::LIKE_LEFT)
);
// WHERE (`name` LIKE '%John')
```

**Значение — буквальная строка поиска.** Символы `%` и `_` в нём экранируются, подстановочные знаки добавляет только тип границы. Для MySQL, PostgreSQL, SQLite и Oracle экранирование идёт через `!` с явным `ESCAPE '!'` — результат не зависит от `sql_mode` и `standard_conforming_strings`; в MS SQL — через `[...]`, в ClickHouse — через `\`. `ESCAPE` добавляется, только если в значении было что экранировать.

```php
use QBuilder\QbConsts;

$qb->where()->like('discount', '50%', QbConsts::LIKE_RIGHT)->end();
// WHERE (`discount` LIKE '50!%%' ESCAPE '!')   — начинается с «50%»

$qb->where()->like('code', 'a_b')->end();
// WHERE (`code` LIKE '%a!_b%' ESCAPE '!')       — содержит «a_b», но не «axb»
```

### NOT LIKE

**Стандартный синтаксис:**

```php
$qb->where()
    ->notLike('name', 'Admin')
    ->end();

// WHERE (`name` NOT LIKE '%Admin%')
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notLike('name', 'Admin')
);

// WHERE (`name` NOT LIKE '%Admin%')
```

### IS NULL / IS NOT NULL

**Стандартный синтаксис:**

```php
$qb->where()
    ->isNull('deleted_at')
    ->and()->isNotNull('email')
    ->end();

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->isNull('deleted_at')->and()->isNotNull('email')
);

// WHERE (`deleted_at` IS NULL) AND (`email` IS NOT NULL)
```

## Сравнение полей

### Сравнение полей из разных таблиц

**Стандартный синтаксис:**

```php
$qb->where()
    ->eqField('orders', 'user_id', 'users', 'id')
    ->end();

// WHERE (`orders`.`user_id` = `users`.`id`)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eqField('orders', 'user_id', 'users', 'id')
);

// WHERE (`orders`.`user_id` = `users`.`id`)
```

### Другие операторы сравнения полей

**Стандартный синтаксис:**

```php
$qb->where()
    ->gtField('orders', 'total', 'users', 'credit_limit')
    ->and()->neqField('orders', 'status', 'users', 'default_status')
    ->end();

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->gtField('orders', 'total', 'users', 'credit_limit')
      ->and()
      ->neqField('orders', 'status', 'users', 'default_status')
);

// WHERE (`orders`.`total` > `users`.`credit_limit`) AND (`orders`.`status` != `users`.`default_status`)
```

## Битовые операции (MySQL)

### Проверка битовой маски

**Стандартный синтаксис:**

```php
$qb->where()
    ->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0])
    ->end();

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->bitmask('permissions', [1 => 1, 2 => 1, 4 => 0]));

// WHERE ((`permissions` & 1 = 1)) AND ((`permissions` & 2 = 2)) AND ((`permissions` & 4 = 0))
```

### Отрицание битовой маски

**Стандартный синтаксис:**

```php
$qb->where()
    ->notBitmask('permissions', [8 => 1])
    ->end();

// WHERE NOT ((`permissions` & 8 = 8))
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->notBitmask('permissions', [8 => 1])
);

// WHERE NOT ((`permissions` & 8 = 8))
```

## FIND_IN_SET (MySQL)

**Стандартный синтаксис:**

```php
$qb->where()
    ->findInSet('tags', 'admin')
    ->end();

// WHERE (FIND_IN_SET('admin', `tags`))

$qb->where()
    ->findInSet('tags', ['admin', 'moderator'])
    ->end();

// WHERE (FIND_IN_SET('admin', `tags`) OR FIND_IN_SET('moderator', `tags`))
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->findInSet('tags', 'admin')
);

// WHERE (FIND_IN_SET('admin', `tags`))

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->findInSet('tags', ['admin', 'moderator'])
);

// WHERE (FIND_IN_SET('admin', `tags`) OR FIND_IN_SET('moderator', `tags`))
```

## Произвольные SQL условия (raw)

**⚠️ ВНИМАНИЕ:** Использование `raw()` обходит защиту от SQL-инъекций. Используйте только с проверенными данными!

**Стандартный синтаксис:**

```php
$qb->where()
    ->eq('status', 'active')
    ->and()
    ->raw('YEAR(created_at) = 2024')
    ->end();

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**Альтернативный синтаксис:**

```php
use QBuilder\Condition\ConditionBuilder;

$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')->and()->raw('YEAR(created_at) = 2024')
);

// WHERE (`status` = 'active') AND (YEAR(created_at) = 2024)
```

**Когда использовать `raw()`:**

- ✅ Сложные SQL-выражения, которые не поддерживаются стандартными методами
- ✅ Специфичные функции СУБД
- ❌ **НИКОГДА** не используйте с пользовательским вводом без валидации

---

[← SELECT запросы](02-select.md) · [Содержание](index.md) · [Альтернативный синтаксис с замыканиями →](04-closure-syntax.md)
