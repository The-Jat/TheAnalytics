UPDATE `settings` SET `value` = '{\"version\":\"35.0.0\", \"code\":\"3500\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
UPDATE settings SET `value` = JSON_SET(`value`, '$.blacklisted_domains', JSON_ARRAY()) WHERE `key` = 'users';

-- SEPARATOR --

UPDATE settings SET `value` = JSON_SET(`value`, '$.blacklisted_domains', JSON_ARRAY()) WHERE `key` = 'analytics';

-- SEPARATOR --

UPDATE settings SET `value` = JSON_SET(`value`, '$.blacklisted_keywords', JSON_ARRAY()) WHERE `key` = 'analytics';

-- SEPARATOR --

alter table websites add public_statistics_is_enabled tinyint default 0 null after email_reports_last_date;

-- SEPARATOR --

alter table websites add public_statistics_password varchar(128) null after public_statistics_is_enabled;

-- SEPARATOR --

alter table websites_visitors add continent_code varchar(8) null after custom_parameters;

-- SEPARATOR --

alter table lightweight_events add continent_code varchar(8) null after utm_campaign;

-- SEPARATOR --

alter table sessions_replays add size bigint unsigned default 0 null after events;

-- SEPARATOR --

alter table websites_heatmaps add desktop_size bigint unsigned default 0 null after snapshot_id_desktop;

-- SEPARATOR --

alter table websites_heatmaps add tablet_size bigint unsigned default 0 null after snapshot_id_tablet;

-- SEPARATOR --

alter table websites_heatmaps add mobile_size bigint unsigned default 0 null after snapshot_id_mobile;

-- SEPARATOR --

alter table websites add ip_storage_is_enabled tinyint unsigned default 0 null after tracking_type;

-- SEPARATOR --

alter table websites_visitors add ip varchar(64) null after website_id;

-- SEPARATOR --

alter table websites_visitors add browser_timezone varchar(32) null after browser_language;

-- SEPARATOR --

alter table lightweight_events add browser_timezone varchar(32) null after browser_language;
-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"35.0.1\", \"code\":\"3501\"}' WHERE `key` = 'product_info';

-- SEPARATOR --