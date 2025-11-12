-- ==========================================
-- Создание таблицы massage_bookings для хранения бронирований массажа
-- ==========================================

-- Создаем таблицу massage_bookings
CREATE TABLE IF NOT EXISTS `massage_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `guest_name` VARCHAR(255) NOT NULL COMMENT 'Имя гостя',
  `email` VARCHAR(255) NOT NULL COMMENT 'Email гостя',
  `phone` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Телефон гостя',
  `massage_date` DATE NOT NULL COMMENT 'Дата массажа',
  `massage_time` TIME NOT NULL COMMENT 'Время массажа',
  `massage_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Тип массажа',
  `duration` INT(11) NULL DEFAULT NULL COMMENT 'Длительность в минутах',
  `notes` TEXT NULL DEFAULT NULL COMMENT 'Дополнительные заметки',
  `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending' COMMENT 'Статус бронирования',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления записи',
  PRIMARY KEY (`id`),
  INDEX `idx_massage_date` (`massage_date`),
  INDEX `idx_status` (`status`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Бронирования массажа';

-- Проверяем, что таблица создана
SELECT 'Таблица massage_bookings успешно создана!' as status;
SELECT COUNT(*) as total_records FROM massage_bookings;

