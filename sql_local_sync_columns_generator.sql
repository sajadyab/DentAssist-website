-- MySQL: generate ALTERs for missing sync columns on all base tables.
-- This query outputs ALTER statements; copy/paste and run them.

SELECT CONCAT(
  'ALTER TABLE `', t.TABLE_NAME, '` ',
  TRIM(BOTH ', ' FROM CONCAT_WS(', ',
    IF(c.has_sync_status = 0, "ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending'", NULL),
    IF(c.has_last_modified = 0, "ADD COLUMN `last_modified` DATETIME NULL", NULL),
    IF(c.has_last_sync_attempt = 0, "ADD COLUMN `last_sync_attempt` DATETIME NULL", NULL),
    IF(c.has_sync_error = 0, "ADD COLUMN `sync_error` TEXT NULL", NULL)
  )),
  ';'
) AS alter_sql
FROM INFORMATION_SCHEMA.TABLES t
JOIN (
  SELECT
    TABLE_NAME,
    MAX(CASE WHEN COLUMN_NAME = 'sync_status' THEN 1 ELSE 0 END) AS has_sync_status,
    MAX(CASE WHEN COLUMN_NAME = 'last_modified' THEN 1 ELSE 0 END) AS has_last_modified,
    MAX(CASE WHEN COLUMN_NAME = 'last_sync_attempt' THEN 1 ELSE 0 END) AS has_last_sync_attempt,
    MAX(CASE WHEN COLUMN_NAME = 'sync_error' THEN 1 ELSE 0 END) AS has_sync_error
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
  GROUP BY TABLE_NAME
) c ON c.TABLE_NAME = t.TABLE_NAME
WHERE t.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND (c.has_sync_status = 0 OR c.has_last_modified = 0 OR c.has_last_sync_attempt = 0 OR c.has_sync_error = 0);

-- Optional: auto-apply with a stored procedure
DELIMITER $$
CREATE PROCEDURE add_missing_sync_columns()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_table VARCHAR(128);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_table;
    IF done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_table AND COLUMN_NAME = 'sync_status'
    ) THEN
      SET @sql = CONCAT("ALTER TABLE `", v_table, "` ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending'");
      PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_table AND COLUMN_NAME = 'last_modified'
    ) THEN
      SET @sql = CONCAT("ALTER TABLE `", v_table, "` ADD COLUMN `last_modified` DATETIME NULL");
      PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_table AND COLUMN_NAME = 'last_sync_attempt'
    ) THEN
      SET @sql = CONCAT("ALTER TABLE `", v_table, "` ADD COLUMN `last_sync_attempt` DATETIME NULL");
      PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = v_table AND COLUMN_NAME = 'sync_error'
    ) THEN
      SET @sql = CONCAT("ALTER TABLE `", v_table, "` ADD COLUMN `sync_error` TEXT NULL");
      PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;

CALL add_missing_sync_columns();
DROP PROCEDURE add_missing_sync_columns;
