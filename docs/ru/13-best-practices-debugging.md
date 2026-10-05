# Лучшие практики и отладка

## Лучшие практики

### 1. Всегда используйте Field для сложных запросов

**✅ ПРАВИЛЬНО:**

```php
use QBuilder\Condition\Field;

$qb->select(
    Field::set('id', 'users'),
    Field::set('name', 'users', 'user_name')
);
```

**❌ ИЗБЕГАЙТЕ:**

```php
$qb->select('users.id', 'users.name AS user_name');
```

### 2. Используйте алиасы для читаемости

**✅ ПРАВИЛЬНО:**

```php
use QBuilder\Condition\ConditionJoin;
use QBuilder\Condition\Field;

$qb->select(
    Field::set('name', 'u', 'user_name'),
    Field::set('name', 'c', 'company_name')
)
->from('users', 'u')
->leftJoin('companies', 'c', ConditionJoin::create($qb, 'id', 'company_id', 'u'));
```

### 3. Группируйте сложные условия

**✅ ПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('country', 'US')
    ->and()
    ->startGroup()
        ->eq('status', 'active')
        ->or()->eq('status', 'pending')
    ->endGroup()
    ->end();
```

### 4. Используйте подзапросы для сложной логики

**✅ ПРАВИЛЬНО:**

```php
$activeUsersSubquery = $qb->subQuery()
    ->select('user_id')
    ->from('orders')
    ->where()
        ->gte('created_at', '2024-01-01')
        ->end();

$qb->where()
    ->inSubquery('id', $activeUsersSubquery)
    ->end();
```

### 5. Всегда вызывайте end() для условий

**✅ ПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('status', 'active')
    ->end();  // ← обязательно!
```

**❌ НЕПРАВИЛЬНО:**

```php
$qb->where()
    ->eq('status', 'active');  // ← забыли end()
```

## Отладка

### Получение SQL без выполнения

```php
$sql = $qb->select('*')
    ->from('users')
    ->build();

echo $sql;  // Вывод SQL для проверки
```

### Компактный SQL

```php
$sql = $qb->build(true);  // Убирает лишние пробелы и переносы строк
```

Схлопываются только пробелы между лексемами. Строковые литералы, идентификаторы в кавычках (`` `...` ``, `"..."`, `[...]`) и комментарии `/* ... */` копируются без изменений; экранирование в литералах учитывается по диалекту (в MySQL и ClickHouse — включая `\'`). После однострочного комментария `-- ...` перевод строки сохраняется, чтобы комментарий не поглотил остаток запроса.

### Проверка типа запроса

```php
$type = $qb->getType();  // 'SELECT', 'INSERT', 'UPDATE', 'DELETE'
```

## Памятка

- ✅ Всегда используйте QueryBuilder вместо конкатенации строк
- ✅ Используйте `Field::set()` для полей с таблицами и алиасами
- ✅ Выбирайте синтаксис (стандартный или с замыканиями) в зависимости от задачи
- ✅ Используйте подзапросы для сложной логики
- ⚠️ Используйте `raw()` методы **только с проверенными данными** - они обходят защиту от SQL-инъекций!

---

[← Поддержка драйверов](12-drivers.md) · [Содержание](index.md)
