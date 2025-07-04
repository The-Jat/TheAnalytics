UPDATE `settings` SET `value` = '{\"version\":\"33.1.0\", \"code\":\"3310\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table users add next_cleanup_datetime datetime default CURRENT_TIMESTAMP null after datetime;