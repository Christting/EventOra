USE eventora;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'cancellation_reason'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE events ADD COLUMN cancellation_reason TEXT NULL AFTER status',
    'SELECT "events.cancellation_reason already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
