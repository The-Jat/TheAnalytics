<?php
/*
 * Copyright (c) 2025 AltumCode (https://altumcode.com/)
 *
 * This software is licensed exclusively by AltumCode and is sold only via https://altumcode.com/.
 * Unauthorized distribution, modification, or use of this software without a valid license is not permitted and may be subject to applicable legal actions.
 *
 * 🌍 View all other existing AltumCode projects via https://altumcode.com/
 * 📧 Get in touch for support or general queries via https://altumcode.com/contact
 * 📤 Download the latest version via https://altumcode.com/downloads
 *
 * 🐦 X/Twitter: https://x.com/AltumCode
 * 📘 Facebook: https://facebook.com/altumcode
 * 📸 Instagram: https://instagram.com/altumcode
 */

namespace Altum\Models;

defined('ALTUMCODE') || die();

class Website extends Model {

    public function get_website_by_pixel_key($pixel_key) {

        /* Try to check if the store posts exists via the cache */
        $cache_instance = cache()->getItem('website?pixel_key=' . md5($pixel_key));

        /* Set cache if not existing */
        if(is_null($cache_instance->get())) {

            /* Get data from the database */
            $data = db()->where('pixel_key', $pixel_key)->getOne('websites');

            if($data) {
                /* Save to cache */
                cache()->save(
                    $cache_instance->set($data)->expiresAfter(43200)->addTag('user_id=' . $data->user_id)->addTag('website_id=' . $data->website_id)
                );
            }

        } else {

            /* Get cache */
            $data = $cache_instance->get();

        }

        return $data;
    }

    public function get_websites_by_user_id($user_id) {

        $cache_instance = cache()->getItem('websites_' . $user_id);

        /* Set cache if not existing */
        if(is_null($cache_instance->get())) {

            $result = database()->query("SELECT * FROM `websites` WHERE `user_id` = {$user_id}");
            $data = [];

            while($row = $result->fetch_object()) {

                $data[$row->website_id] = $row;

            }

            cache()->save($cache_instance->set($data)->expiresAfter(CACHE_DEFAULT_SECONDS));

        } else {

            /* Get cache */
            $data = $cache_instance->get();

        }

        return $data;
    }

    public function get_websites_by_websites_ids(array $websites_ids = []) {

        $websites_ids_query = implode(',', $websites_ids);
        $result = database()->query("SELECT * FROM `websites` WHERE `website_id` IN ({$websites_ids_query}) ");
        $data = [];

        while($row = $result->fetch_object()) {

            $data[$row->website_id] = $row;

        }

        return $data;
    }
}
