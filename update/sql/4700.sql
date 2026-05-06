UPDATE `settings` SET `value` = '{\"version\":\"44.0.0\", \"code\":\"4400\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
ALTER TABLE lightweight_events ADD INDEX idx_website_date_type (website_id, date, type);
-- SEPARATOR --
alter table websites add outbound_clicks_is_enabled tinyint default 0 null after events_children_is_enabled;
-- SEPARATOR --
alter table pages add plans_ids text null after pages_category_id;
-- SEPARATOR --

CREATE TABLE `outbound_clicks` (
`outbound_click_id` bigint unsigned NOT NULL AUTO_INCREMENT,
`event_id` int DEFAULT NULL,
`session_id` int DEFAULT NULL,
`visitor_id` int DEFAULT NULL,
`website_id` int DEFAULT NULL,
`host` varchar(256) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`title` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`datetime` datetime DEFAULT NULL,
`expiration_date` date DEFAULT NULL,
PRIMARY KEY (`outbound_click_id`),
KEY `website_id` (`website_id`),
CONSTRAINT `outbound_clicks_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"45.0.0\", \"code\":\"4500\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table domains add type tinyint default 0 null after custom_not_found_url;
-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"46.0.0\", \"code\":\"4600\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
UPDATE users SET email = LOWER(email);

-- SEPARATOR --

alter table websites add sessions_replays_hide_text_selector varchar(1024) null after sessions_replays_is_enabled;

-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('klarna', '{"is_enabled":1,"mode":"https:\/\/api.playground.klarna.com\/","username":"","password":"","currencies":["USD"]}');

-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('paddle_billing', '{"is_enabled":1,"mode":"sandbox","api_key":"","secret_key":"","client_side_token":"","currencies":["USD"]}');

-- SEPARATOR --

alter table plans add additional_settings text null after settings;

-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"47.0.0\", \"code\":\"4700\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('plisio', '{\"is_enabled\":false,\"secret_key\":\"\",\"accepted_cryptocurrencies\":[\"DOGE\",\"SOL\",\"ETH\",\"BTC\"],\"default_cryptocurrency\":\"SOL\",\"currencies\":[\"USD\"]}');
-- SEPARATOR --

INSERT INTO `settings` (`key`, `value`) VALUES ('revolut', '{\"is_enabled\":false,\"mode\":\"sandbox\",\"secret_key\":\"\",\"webhook_id\":\"\",\"currencies\":[\"USD\"]}');
-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('plisio_whitelabel', '{\"is_enabled\":false,\"secret_key\":\"\",\"accepted_cryptocurrencies\":[\"DOGE\",\"SOL\",\"ETH\",\"BTC\"],\"default_cryptocurrency\":\"SOL\",\"currencies\":[\"USD\"]}');

-- SEPARATOR --

create index `status` on users (status);

-- SEPARATOR --

create index users_logs_datetime_index on users_logs (datetime);

-- SEPARATOR --

create index internal_notifications_datetime_index on internal_notifications (datetime);
-- SEPARATOR --