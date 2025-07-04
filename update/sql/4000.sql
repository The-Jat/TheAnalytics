UPDATE `settings` SET `value` = '{\"version\":\"40.0.0\", \"code\":\"4000\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table websites modify current_month_sessions_events bigint unsigned default 0 not null;

-- SEPARATOR --

alter table websites modify current_month_events_children bigint unsigned default 0 not null;

-- SEPARATOR --

alter table websites modify current_month_sessions_replays bigint unsigned default 0 not null;

-- SEPARATOR --

alter table websites add plan_sessions_events_limit_notice tinyint default 0 null after current_month_sessions_replays;

-- SEPARATOR --

alter table websites add plan_events_children_limit_notice tinyint default 0 null after plan_sessions_events_limit_notice;

-- SEPARATOR --

alter table websites add plan_sessions_replays_limit_notice tinyint default 0 null after plan_events_children_limit_notice;

-- SEPARATOR --

INSERT INTO `settings` (`key`, `value`) VALUES ('myfatoorah', '{}');

-- SEPARATOR --