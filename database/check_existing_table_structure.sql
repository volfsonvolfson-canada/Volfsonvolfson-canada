-- ==========================================
-- Проверка структуры существующей таблицы blocked_dates
-- ==========================================

-- 1. Показываем структуру таблицы
SELECT '=== Структура таблицы blocked_dates ===' as info;
DESCRIBE blocked_dates;

-- 2. Показываем все поля таблицы
SELECT '=== Все поля таблицы ===' as info;
SHOW COLUMNS FROM blocked_dates;

-- 3. Проверяем наличие критических полей
SELECT '=== Проверка наличия полей ===' as info;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'blocked_dates'
ORDER BY ORDINAL_POSITION;

-- 4. Количество записей
SELECT '=== Количество записей ===' as info;
SELECT COUNT(*) as total_records FROM blocked_dates;

-- 5. Примеры записей (если есть)
SELECT '=== Примеры записей (первые 5) ===' as info;
SELECT * FROM blocked_dates ORDER BY id DESC LIMIT 5;

