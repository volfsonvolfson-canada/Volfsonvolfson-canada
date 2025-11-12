-- ==========================================
-- Простое добавление недостающих полей (без проверок)
-- Используйте этот скрипт, если предыдущий не работает
-- ==========================================

-- Добавляем поле date_from (если его нет)
ALTER TABLE `blocked_dates` 
ADD COLUMN IF NOT EXISTS `date_from` DATE NULL COMMENT 'Начало заблокированного периода' AFTER `room_name`;

-- Добавляем поле date_to (если его нет)
ALTER TABLE `blocked_dates` 
ADD COLUMN IF NOT EXISTS `date_to` DATE NULL COMMENT 'Конец заблокированного периода' AFTER `date_from`;

-- Добавляем поле reason (если его нет)
ALTER TABLE `blocked_dates` 
ADD COLUMN IF NOT EXISTS `reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Причина/комментарий блокировки' AFTER `date_to`;

-- Добавляем поле created_at (если его нет)
ALTER TABLE `blocked_dates` 
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи';

-- Добавляем индексы
ALTER TABLE `blocked_dates` 
ADD INDEX IF NOT EXISTS `idx_date_from` (`date_from`),
ADD INDEX IF NOT EXISTS `idx_date_to` (`date_to`),
ADD INDEX IF NOT EXISTS `idx_date_range` (`date_from`, `date_to`);

-- Показываем структуру
DESCRIBE blocked_dates;

