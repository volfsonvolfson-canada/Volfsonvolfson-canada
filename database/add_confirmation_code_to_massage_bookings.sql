-- Добавление поля confirmation_code в таблицу massage_bookings
-- Если поле уже существует, скрипт не вызовет ошибку

-- Проверяем, существует ли поле confirmation_code
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'massage_bookings'
      AND COLUMN_NAME = 'confirmation_code'
);

-- Добавляем поле, если его нет
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `massage_bookings` ADD COLUMN `confirmation_code` VARCHAR(50) NULL COMMENT ''Код подтверждения бронирования'' AFTER `status`',
    'SELECT ''Поле confirmation_code уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем результат
SELECT 'Поле confirmation_code добавлено в таблицу massage_bookings' as status;

