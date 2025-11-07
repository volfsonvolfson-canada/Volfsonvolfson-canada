-- ==========================================
-- Схема базы данных для системы бронирования
-- Back to Base Hotel Booking System
-- ==========================================

-- Таблица бронирований
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_name` VARCHAR(255) NOT NULL COMMENT 'Название комнаты (Basement, Ground Floor - Queen bed, и т.д.)',
  `checkin_date` DATE NOT NULL COMMENT 'Дата заезда',
  `checkout_date` DATE NOT NULL COMMENT 'Дата выезда',
  `guest_name` VARCHAR(255) NOT NULL COMMENT 'Имя гостя',
  `email` VARCHAR(255) NOT NULL COMMENT 'Email гостя',
  `phone` VARCHAR(50) NOT NULL COMMENT 'Телефон гостя',
  `guests_count` INT(3) NOT NULL DEFAULT 1 COMMENT 'Количество гостей',
  `pets` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Наличие домашних животных (0/1)',
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Статус бронирования',
  `payment_status` ENUM('pending', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'pending' COMMENT 'Статус оплаты',
  `payment_intent_id` VARCHAR(255) NULL COMMENT 'ID платежного намерения Stripe',
  `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Общая сумма бронирования',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'CAD' COMMENT 'Валюта (CAD)',
  `stripe_payment_id` VARCHAR(255) NULL COMMENT 'ID платежа в Stripe',
  `airbnb_synced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Синхронизировано с Airbnb (0/1)',
  `special_requests` TEXT NULL COMMENT 'Особые пожелания гостя',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',
  PRIMARY KEY (`id`),
  INDEX `idx_room_name` (`room_name`),
  INDEX `idx_checkin_date` (`checkin_date`),
  INDEX `idx_checkout_date` (`checkout_date`),
  INDEX `idx_status` (`status`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_email` (`email`),
  INDEX `idx_dates_range` (`checkin_date`, `checkout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица бронирований';

-- Таблица заблокированных дат (для ручной блокировки)
CREATE TABLE IF NOT EXISTS `blocked_dates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_name` VARCHAR(255) NOT NULL COMMENT 'Название комнаты',
  `blocked_date` DATE NOT NULL COMMENT 'Заблокированная дата',
  `reason` VARCHAR(500) NULL COMMENT 'Причина блокировки',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_date` (`room_name`, `blocked_date`),
  INDEX `idx_room_name` (`room_name`),
  INDEX `idx_blocked_date` (`blocked_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица заблокированных дат';

-- Таблица синхронизации календаря Airbnb
CREATE TABLE IF NOT EXISTS `airbnb_calendar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_name` VARCHAR(255) NOT NULL COMMENT 'Название комнаты',
  `date` DATE NOT NULL COMMENT 'Дата',
  `is_available` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Доступна ли дата (0 - заблокирована/забронирована, 1 - доступна)',
  `ical_event_id` VARCHAR(255) NULL COMMENT 'ID события из iCal',
  `last_synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последней синхронизации',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_date` (`room_name`, `date`),
  INDEX `idx_room_name` (`room_name`),
  INDEX `idx_date` (`date`),
  INDEX `idx_is_available` (`is_available`),
  INDEX `idx_last_synced` (`last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица синхронизации календаря Airbnb';

-- Таблица подтверждений бронирований
CREATE TABLE IF NOT EXISTS `booking_confirmations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL COMMENT 'ID бронирования',
  `confirmation_code` VARCHAR(50) NOT NULL COMMENT 'Код подтверждения (уникальный)',
  `email_sent_at` TIMESTAMP NULL COMMENT 'Время отправки email гостю',
  `host_confirmed_at` TIMESTAMP NULL COMMENT 'Время подтверждения хозяином',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_confirmation_code` (`confirmation_code`),
  INDEX `idx_booking_id` (`booking_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблица подтверждений бронирований';

-- Таблица настроек комнат для синхронизации Airbnb
CREATE TABLE IF NOT EXISTS `room_airbnb_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_name` VARCHAR(255) NOT NULL COMMENT 'Название комнаты',
  `ical_url` TEXT NOT NULL COMMENT 'URL iCal календаря Airbnb для этой комнаты',
  `sync_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Включена ли синхронизация (0/1)',
  `last_sync_at` TIMESTAMP NULL COMMENT 'Время последней синхронизации',
  `sync_interval_hours` INT(3) NOT NULL DEFAULT 1 COMMENT 'Интервал синхронизации в часах',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_name` (`room_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Настройки синхронизации Airbnb для комнат';

