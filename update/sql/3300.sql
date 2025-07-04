UPDATE `settings` SET `value` = '{\"version\":\"33.0.0\", \"code\":\"3300\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table websites add bot_exclusion_is_enabled tinyint default 1 null after excluded_ips;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table websites add query_parameters_tracking_is_enabled tinyint default 0 null after excluded_ips;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table teams change date datetime datetime not null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table teams add last_datetime datetime null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --
alter table websites_heatmaps change date datetime datetime not null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table websites_heatmaps add last_datetime datetime null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_replays add user_id int null after replay_id;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_replays add constraint sessions_replays_users_user_id_fk foreign key (user_id) references users (user_id);

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_replays change date datetime datetime not null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_replays change last_date last_datetime datetime null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

update sessions_replays join websites on websites.website_id = sessions_replays.website_id set sessions_replays.user_id = websites.user_id;