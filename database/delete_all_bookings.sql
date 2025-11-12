-- Удаление всех бронирований из базы данных
-- ВНИМАНИЕ: Этот скрипт удалит ВСЕ бронирования без возможности восстановления!

-- Сначала удаляем связанные записи из booking_confirmations
DELETE FROM booking_confirmations;

-- Затем удаляем все бронирования
DELETE FROM bookings;

-- Проверяем результат
SELECT 'All bookings deleted successfully' AS message;
SELECT COUNT(*) AS remaining_bookings FROM bookings;
SELECT COUNT(*) AS remaining_confirmations FROM booking_confirmations;

