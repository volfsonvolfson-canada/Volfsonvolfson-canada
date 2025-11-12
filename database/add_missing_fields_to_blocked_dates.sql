-- ==========================================
-- Добавление недостающих полей в существующую таблицу blocked_dates
-- Безопасно: проверяет наличие полей перед добавлением
-- ==========================================

-- Проверяем и добавляем поле date_from, если его нет
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND COLUMN_NAME = 'date_from'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `blocked_dates` ADD COLUMN `date_from` DATE NULL COMMENT ''Начало заблокированного периода (новый формат)'' AFTER `room_name`',
    'SELECT ''Поле date_from уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем поле date_to, если его нет
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND COLUMN_NAME = 'date_to'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `blocked_dates` ADD COLUMN `date_to` DATE NULL COMMENT ''Конец заблокированного периода (новый формат)'' AFTER `date_from`',
    'SELECT ''Поле date_to уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем поле reason, если его нет
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND COLUMN_NAME = 'reason'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `blocked_dates` ADD COLUMN `reason` VARCHAR(500) NULL DEFAULT NULL COMMENT ''Причина/комментарий блокировки'' AFTER `date_to`',
    'SELECT ''Поле reason уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем поле created_at, если его нет
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND COLUMN_NAME = 'created_at'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `blocked_dates` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT ''Дата создания записи''',
    'SELECT ''Поле created_at уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индексы, если их нет
-- Индекс для date_from
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND INDEX_NAME = 'idx_date_from'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `blocked_dates` ADD INDEX `idx_date_from` (`date_from`)',
    'SELECT ''Индекс idx_date_from уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Индекс для date_to
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND INDEX_NAME = 'idx_date_to'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `blocked_dates` ADD INDEX `idx_date_to` (`date_to`)',
    'SELECT ''Индекс idx_date_to уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Индекс для диапазона дат
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'blocked_dates'
      AND INDEX_NAME = 'idx_date_range'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `blocked_dates` ADD INDEX `idx_date_range` (`date_from`, `date_to`)',
    'SELECT ''Индекс idx_date_range уже существует'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Показываем финальную структуру
SELECT '=== Финальная структура таблицы ===' as info;
DESCRIBE blocked_dates;

