UPDATE `settings` SET `value` = '{\"version\":\"34.0.0\", \"code\":\"3400\"}' WHERE `key` = 'product_info';

-- SEPARATOR --

alter table email_reports modify id bigint unsigned auto_increment;

-- SEPARATOR --

alter table email_reports change date datetime datetime not null;

-- SEPARATOR --

alter table blog_posts add image_description varchar(256) null after description;
-- SEPARATOR --