-- Custom Domains Tablosu (Premium Özellik)
CREATE TABLE IF NOT EXISTS `custom_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255),
  `ssl_enabled` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `custom_domains_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domain Link Mapping (hangi linkler hangi domainde)
CREATE TABLE IF NOT EXISTS `domain_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `domain_id` (`domain_id`),
  CONSTRAINT `domain_links_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE,
  CONSTRAINT `domain_links_ibfk_2` FOREIGN KEY (`domain_id`) REFERENCES `custom_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;