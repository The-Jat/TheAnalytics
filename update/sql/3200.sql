UPDATE `settings` SET `value` = '{\"version\":\"32.0.0\", \"code\":\"3200\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_events add expiration_date date null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table lightweight_events add expiration_date date null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

CREATE INDEX expiration_date ON sessions_events (expiration_date);

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

CREATE INDEX expiration_date ON lightweight_events (expiration_date);

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

UPDATE sessions_events SET expiration_date = CURDATE() + INTERVAL 3 YEAR;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

UPDATE lightweight_events SET expiration_date = CURDATE() + INTERVAL 3 YEAR;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table sessions_replays add is_offloaded tinyint default 0 null after events;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table users add extra text null after preferences;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table websites_heatmaps add user_id int null after heatmap_id;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table websites_heatmaps add constraint websites_heatmaps_users_user_id_fk foreign key (user_id) references users (user_id);

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

update websites_heatmaps left join websites on websites.website_id = websites_heatmaps.website_id set websites_heatmaps.user_id = websites.user_id;
