ALTER TABLE `viajes` 
ADD COLUMN `origen` ENUM('telegram', 'excel', 'manual') NOT NULL DEFAULT 'telegram' 
COMMENT 'Origen del registro: telegram, excel, manual';

ALTER TABLE `viajes` 
ADD INDEX `idx_origen` (`origen`);