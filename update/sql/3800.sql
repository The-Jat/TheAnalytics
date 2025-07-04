UPDATE `settings` SET `value` = '{\"version\":\"38.0.0\", \"code\":\"3800\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table websites_visitors add visitor_uuid_binary binary(16) null after visitor_uuid;

-- SEPARATOR --

alter table websites_visitors add constraint websites_visitors_pk unique (visitor_uuid_binary);

-- SEPARATOR --

update websites_visitors set `visitor_uuid_binary` =  UNHEX(REPLACE(UUID(), '-', ''));

-- SEPARATOR --

alter table websites_visitors drop column visitor_uuid;

-- SEPARATOR --

alter table visitors_sessions add session_uuid_binary binary(16) null after session_uuid;

-- SEPARATOR --

alter table visitors_sessions add constraint visitors_sessions_pk unique (session_uuid_binary);

-- SEPARATOR --

update visitors_sessions set `session_uuid_binary` =  UNHEX(REPLACE(UUID(), '-', ''));

-- SEPARATOR --

alter table visitors_sessions drop column session_uuid;

-- SEPARATOR --

alter table sessions_events add event_uuid_binary binary(16) null after event_id;

-- SEPARATOR --

update sessions_events set `event_uuid_binary` =  UNHEX(REPLACE(UUID(), '-', ''));

-- SEPARATOR --

alter table sessions_events drop column event_uuid;

-- SEPARATOR --