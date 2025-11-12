-- ==========================================
-- Скрипт для очистки всех бронирований и заблокированных дат
-- ВНИМАНИЕ: Этот скрипт удалит ВСЕ данные без возможности восстановления!
-- ==========================================

-- Отключаем проверку внешних ключей временно
SET FOREIGN_KEY_CHECKS = 0;

-- Удаляем все подтверждения бронирований (связанные записи)
DELETE FROM booking_confirmations;

-- Удаляем все бронирования комнат
DELETE FROM bookings;

-- Удаляем все заблокированные даты (периоды блокировки)
DELETE FROM blocked_dates;

-- Примечание: Бронирования массажа не хранятся в базе данных отдельно
-- Они отправляются через форму, но не сохраняются в отдельной таблице
-- Если в будущем будет добавлена таблица massage_bookings, добавьте:
-- DELETE FROM massage_bookings;

-- Включаем обратно проверку внешних ключей
SET FOREIGN_KEY_CHECKS = 1;

-- Проверяем, что все удалено
SELECT 'Bookings count:' as info, COUNT(*) as count FROM bookings
UNION ALL
SELECT 'Blocked dates count:', COUNT(*) FROM blocked_dates
UNION ALL
SELECT 'Booking confirmations count:', COUNT(*) FROM booking_confirmations;

