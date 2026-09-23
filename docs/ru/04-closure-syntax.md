# Альтернативный синтаксис с замыканиями

## Преимущества

QueryBuilder поддерживает альтернативный синтаксис с использованием замыканий (closures), который предоставляет следующие преимущества:

1. **Не нужно вызывать `end()`** - замыкание автоматически завершает построение условий
2. **Более компактный код** - меньше строк кода для тех же условий
3. **Улучшенная читаемость** - вложенные группы становятся более понятными
4. **Полная совместимость** - оба синтаксиса генерируют идентичный SQL

## Сравнение синтаксисов

### Простое условие

```php
// Стандартный синтаксис
$qb->where()->eq('status', 'active')->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder => $q->eq('status', 'active'));
```

### Несколько условий

```php
// Стандартный синтаксис
$qb->where()
    ->eq('status', 'active')
    ->and()->gt('age', 18)
    ->and()->like('name', 'John', QbConsts::LIKE_RIGHT)
    ->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('status', 'active')
      ->and()->gt('age', 18)
      ->and()->like('name', 'John', QbConsts::LIKE_RIGHT)
);
```

### Группы условий

```php
// Стандартный синтаксис
$qb->where()
    ->eq('country', 'US')
    ->andGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->end()
    ->end();

// Альтернативный синтаксис
$qb->where(static fn (ConditionBuilder $q): ConditionBuilder =>
    $q->eq('country', 'US')
      ->andGroup(static fn (ConditionBuilder $q2): ConditionBuilder => $q2->eq('status', 'active')->or()->eq('status', 'pending'))
);
```

### Вложенные группы

```php
// Стандартный синтаксис
$qb->where()
    ->eq('status', 'active')
    ->andGroup()
        ->eq('country', 'US')
        ->orGroup()
            ->eq('state', 'CA')
            ->or()->eq('state', 'NY')
        ->end()
    ->end()
    ->end();

// Альтернативный синтаксис
$qb->where(function($q) {
    $q->eq('status', 'active')
      ->andGroup(function($q2) {
          $q2->eq('country', 'US')
             ->orGroup(fn ($q3) => $q3->eq('state', 'CA')->or()->eq('state', 'NY'));
      });
});
```

## Использование с HAVING

Альтернативный синтаксис также работает с `having()`:

```php
// Стандартный синтаксис
$qb->having()
    ->gt('COUNT(*)', 5)
    ->and()->lt('SUM(amount)', 10000)
    ->end();

// Альтернативный синтаксис
$qb->having(static fn (ConditionBuilder $q): ConditionBuilder => $q->gt('COUNT(*)', 5)->and()->lt('SUM(amount)', 10000));
```

## Сложный пример

```php
// Альтернативный синтаксис для сложного запроса
$qb->select('*')
    ->from('users')
    ->where(function($q) {
        $q->eq('status', 'active')
          ->and()->bitmask('permissions', [1 => 1, 2 => 1])
          ->and()->in('role', ['admin', 'moderator'])
          ->andGroup(function($q2) {
              $q2->gte('created_at', '2024-01-01')
                 ->or()->isNull('deleted_at');
          });
    })
    ->orderBy(ConditionBy::orderBy()->desc('created_at'))
    ->limit(10);
```

## Когда использовать каждый синтаксис

**Стандартный синтаксис рекомендуется:**

- При динамическом построении условий в циклах
- Когда условия добавляются условно (if/else)
- В легаси-коде для совместимости

**Альтернативный синтаксис рекомендуется:**

- Для статических запросов с известной структурой
- Когда нужна максимальная читаемость
- Для сложных вложенных условий
- В новом коде

---

[← WHERE условия](03-where.md) · [Содержание](index.md) · [JOIN операции →](05-join.md)
