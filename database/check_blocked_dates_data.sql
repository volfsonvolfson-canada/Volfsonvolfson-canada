-- ==========================================
-- Проверка данных в таблице blocked_dates
-- ==========================================

-- 1. Общее количество записей
SELECT '=== Общее количество записей ===' as info;
SELECT COUNT(*) as total_records FROM blocked_dates;

-- 2. Записи со старым форматом (есть blocked_date, но нет date_from/date_to)
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

-- 3. Записи с новым форматом (есть date_from и date_to)
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

-- 4. Записи с проблемами (NULL в критических полях)
SELECT '=== Записи с проблемами (NULL в критических полях) ===' as info;
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

-- 5. Все записи (для полного обзора)
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
ORDER BY id DESC;

