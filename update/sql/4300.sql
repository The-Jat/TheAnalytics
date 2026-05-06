UPDATE `settings` SET `value` = '{\"version\":\"43.0.0\", \"code\":\"4300\"}' WHERE `key` = 'product_info';
-- SEPARATOR --

ALTER TABLE `users` ADD INDEX `idx_users_next_cleanup_datetime` (`next_cleanup_datetime`);

-- SEPARATOR --
alter table users modify twofa_secret varchar(32) collate utf8mb4_unicode_ci null;


-- SEPARATOR --

alter table sessions_replays add is_too_short tinyint(1) default 1 null after is_offloaded;

-- SEPARATOR --

update sessions_replays set is_too_short = 1;

-- SEPARATOR --
create index sessions_replays_datetime_index on sessions_replays (datetime);

-- SEPARATOR --

ALTER TABLE `sessions_replays` DROP INDEX `session_id`;

-- SEPARATOR --

alter table lightweight_events modify type enum ('landing_page', 'pageview') null;

-- SEPARATOR --

alter table sessions_events modify type enum ('landing_page', 'pageview') null;

-- SEPARATOR --
alter table heatmaps_snapshots modify type enum ('desktop', 'mobile', 'tablet') null;

-- SEPARATOR --

alter table events_children modify type enum ('click', 'scroll', 'form', 'resize') null;

-- SEPARATOR --

alter table websites modify tracking_type enum ('normal', 'lightweight') default 'normal' null;

-- SEPARATOR --
alter table websites_goals modify type enum ('pageview', 'custom') default 'pageview' not null;


-- SEPARATOR --

alter table broadcasts_statistics modify type enum ('view', 'click') null;

-- SEPARATOR --

alter table pages modify type enum ('internal', 'external') null;
-- SEPARATOR --

alter table users modify device_type enum ('mobile', 'tablet', 'desktop') null;

-- SEPARATOR --

alter table users_logs modify device_type enum ('mobile', 'tablet', 'desktop') null;

-- SEPARATOR --

alter table lightweight_events modify device_type enum ('mobile', 'tablet', 'desktop') null;
-- SEPARATOR --

alter table websites_visitors modify device_type enum ('mobile', 'tablet', 'desktop') null;

-- SEPARATOR --
alter table lightweight_events modify theme enum ('light', 'dark') null;

-- SEPARATOR --

alter table websites_visitors modify theme enum ('light', 'dark') null;
-- SEPARATOR --

alter table users modify continent_code ENUM('AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA') null;

-- SEPARATOR --

alter table users_logs modify continent_code ENUM('AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA') null;

-- SEPARATOR --

alter table lightweight_events modify continent_code ENUM('AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA') null;

-- SEPARATOR --

alter table websites_visitors modify continent_code ENUM('AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA') null;
-- SEPARATOR --