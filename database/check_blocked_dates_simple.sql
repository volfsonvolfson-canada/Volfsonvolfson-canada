-- ==========================================
-- Простая проверка структуры таблицы blocked_dates
-- ==========================================

-- 1. Проверяем структуру таблицы
DESCRIBE blocked_dates;

-- 2. Проверяем наличие полей date_from и date_to
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Поля date_from и date_to СУЩЕСТВУЮТ'
        ELSE 'Поля date_from и date_to НЕ СУЩЕСТВУЮТ'
    END as migration_status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'blocked_dates'
  AND COLUMN_NAME IN ('date_from', 'date_to');

-- 3. Количество записей
SELECT COUNT(*) as total_records FROM blocked_dates;

-- 4. Примеры записей (первые 5)
SELECT * FROM blocked_dates ORDER BY id DESC LIMIT 5;

