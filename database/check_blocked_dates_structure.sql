-- ==========================================
-- Скрипт для проверки структуры таблицы blocked_dates
-- Проверяет: миграцию, формат записей, NULL значения
-- ==========================================

-- 1. Проверяем структуру таблицы (какие поля существуют)
SELECT '=== Структура таблицы blocked_dates ===' as info;
DESCRIBE blocked_dates;

-- 2. Проверяем, существуют ли поля date_from и date_to
SELECT '=== Проверка наличия полей date_from и date_to ===' as info;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'blocked_dates'
  AND COLUMN_NAME IN ('date_from', 'date_to', 'blocked_date')
ORDER BY COLUMN_NAME;

-- 3. Проверяем общее количество записей
SELECT '=== Общее количество записей ===' as info;
SELECT COUNT(*) as total_records FROM blocked_dates;

-- 4. Проверяем записи со старым форматом (есть blocked_date, но нет date_from/date_to)
-- Это работает только если поля date_from и date_to существуют
SELECT '=== Записи со старым форматом (blocked_date без date_from/date_to) ===' as info;
SELECT 
    id,
    room_name,
    blocked_date,
    date_from,
    date_to,
    reason,
    created_at
FROM blocked_dates
WHERE (date_from IS NULL OR date_from = '')
  AND (date_to IS NULL OR date_to = '')
  AND blocked_date IS NOT NULL
  AND blocked_date != '';

-- 5. Проверяем записи с новым форматом (есть date_from и date_to)
SELECT '=== Записи с новым форматом (date_from и date_to) ===' as info;
SELECT 
    id,
    room_name,
    blocked_date,
    date_from,
    date_to,
    reason,
    created_at
FROM blocked_dates
WHERE date_from IS NOT NULL 
  AND date_from != ''
  AND date_to IS NOT NULL 
  AND date_to != '';

-- 6. Проверяем записи с NULL значениями в критических полях
SELECT '=== Записи с NULL в критических полях ===' as info;
SELECT 
    id,
    room_name,
    blocked_date,
    date_from,
    date_to,
    reason,
    created_at,
    CASE 
        WHEN room_name IS NULL OR room_name = '' THEN 'room_name is NULL/empty'
        WHEN blocked_date IS NULL AND (date_from IS NULL OR date_to IS NULL) THEN 'all date fields are NULL'
        ELSE 'OK'
    END as issue
FROM blocked_dates
WHERE room_name IS NULL 
   OR room_name = ''
   OR (blocked_date IS NULL AND (date_from IS NULL OR date_to IS NULL));

-- 7. Показываем все записи для полного обзора
SELECT '=== Все записи в таблице ===' as info;
SELECT 
    id,
    room_name,
    blocked_date,
    date_from,
    date_to,
    reason,
    created_at
FROM blocked_dates
ORDER BY id DESC
LIMIT 20;

