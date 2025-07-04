UPDATE `settings` SET `value` = '{\"version\":\"31.0.0\", \"code\":\"3100\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table plans add translations text null after description;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table plans drop column monthly_price;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table plans drop column annual_price;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table plans drop column lifetime_price;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table users modify plan_settings longtext null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table plans modify settings longtext not null;