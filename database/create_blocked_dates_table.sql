-- ==========================================
-- Создание таблицы blocked_dates с нуля
-- Поддерживает и старый формат (blocked_date), и новый (date_from, date_to)
-- ==========================================

-- Создаем таблицу blocked_dates
CREATE TABLE IF NOT EXISTS `blocked_dates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_name` VARCHAR(255) NOT NULL COMMENT 'Название комнаты',
  `blocked_date` DATE NULL COMMENT 'Заблокированная дата (старый формат, для обратной совместимости)',
  `date_from` DATE NULL COMMENT 'Начало заблокированного периода (новый формат)',
  `date_to` DATE NULL COMMENT 'Конец заблокированного периода (новый формат)',
  `reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Причина/комментарий блокировки',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
  PRIMARY KEY (`id`),
  INDEX `idx_room_name` (`room_name`),
  INDEX `idx_blocked_date` (`blocked_date`),
  INDEX `idx_date_from` (`date_from`),
  INDEX `idx_date_to` (`date_to`),
  INDEX `idx_date_range` (`date_from`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Заблокированные даты и периоды для комнат';

-- Проверяем, что таблица создана
SELECT 'Таблица blocked_dates успешно создана!' as status;
SELECT COUNT(*) as total_records FROM blocked_dates;

