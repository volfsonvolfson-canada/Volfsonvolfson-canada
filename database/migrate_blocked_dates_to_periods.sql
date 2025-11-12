-- Миграция таблицы blocked_dates для поддержки периодов блокировки
-- Вместо отдельных дат теперь храним периоды (date_from, date_to)

-- Добавляем новые поля
ALTER TABLE `blocked_dates` 
ADD COLUMN `date_from` DATE NULL COMMENT 'Начало периода блокировки' AFTER `room_name`,
ADD COLUMN `date_to` DATE NULL COMMENT 'Конец периода блокировки' AFTER `date_from`;

-- Мигрируем существующие данные: каждая дата становится периодом из одного дня
UPDATE `blocked_dates` 
SET `date_from` = `blocked_date`, 
    `date_to` = `blocked_date`
WHERE `date_from` IS NULL;

-- Делаем новые поля обязательными
ALTER TABLE `blocked_dates` 
MODIFY COLUMN `date_from` DATE NOT NULL COMMENT 'Начало периода блокировки',
MODIFY COLUMN `date_to` DATE NOT NULL COMMENT 'Конец периода блокировки';

-- Удаляем старое поле blocked_date (после миграции данных)
-- ALTER TABLE `blocked_dates` DROP COLUMN `blocked_date`;

-- Обновляем индексы
ALTER TABLE `blocked_dates` 
DROP INDEX `idx_room_date`,
ADD INDEX `idx_room_date_from` (`room_name`, `date_from`),
ADD INDEX `idx_date_from` (`date_from`),
ADD INDEX `idx_date_to` (`date_to`);

-- Добавляем проверку, что date_from <= date_to
ALTER TABLE `blocked_dates` 
ADD CONSTRAINT `chk_date_range` CHECK (`date_from` <= `date_to`);

