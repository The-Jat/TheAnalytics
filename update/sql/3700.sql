UPDATE `settings` SET `value` = '{\"version\":\"37.0.0\", \"code\":\"3700\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table websites_visitors add theme varchar(8) null after device_type;

-- SEPARATOR --

alter table lightweight_events add theme varchar(8) null after device_type;

-- SEPARATOR --

alter table sessions_events drop column utm_term;

-- SEPARATOR --

alter table sessions_events drop column utm_content;

-- SEPARATOR --

alter table websites_visitors add goals_conversions_ids text null after website_id;

-- SEPARATOR --