-- Скрипт для проверки созданных таблиц
-- Выполните этот скрипт в phpMyAdmin для проверки

-- 1. Проверить список всех таблиц
SHOW TABLES;

-- 2. Проверить структуру каждой таблицы
DESCRIBE bookings;
DESCRIBE blocked_dates;
DESCRIBE airbnb_calendar;
DESCRIBE booking_confirmations;
DESCRIBE room_airbnb_settings;

-- 3. Проверить количество записей в каждой таблице (должно быть 0)
SELECT 'bookings' as table_name, COUNT(*) as row_count FROM bookings
UNION ALL
SELECT 'blocked_dates', COUNT(*) FROM blocked_dates
UNION ALL
SELECT 'airbnb_calendar', COUNT(*) FROM airbnb_calendar
UNION ALL
SELECT 'booking_confirmations', COUNT(*) FROM booking_confirmations
UNION ALL
SELECT 'room_airbnb_settings', COUNT(*) FROM room_airbnb_settings;

