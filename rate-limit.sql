-- Rate Limiting Tablosu
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'IP address or user ID',
  `endpoint` varchar(255) NOT NULL,
  `requests` int(11) DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_limit` (`identifier`, `endpoint`),
  KEY `identifier` (`identifier`),
  KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eski kayıtları temizlemek için (cron job)
-- DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR) AND blocked_until IS NULL;