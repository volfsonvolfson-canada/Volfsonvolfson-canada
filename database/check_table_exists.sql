-- ==========================================
-- Проверка существования таблицы blocked_dates
-- ==========================================

-- Проверяем, существует ли таблица
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Таблица blocked_dates СУЩЕСТВУЕТ'
        ELSE 'Таблица blocked_dates НЕ СУЩЕСТВУЕТ'
    END as table_status
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'blocked_dates';

-- Показываем все таблицы в базе данных (для справки)
SELECT '=== Все таблицы в базе данных ===' as info;
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

