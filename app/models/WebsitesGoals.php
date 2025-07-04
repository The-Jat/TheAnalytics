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

class WebsitesGoals extends Model {

    public function get_website_goals_by_website_id($website_id) {

        $cache_instance = cache()->getItem('website_goals?website_id=' . $website_id);

        /* Set cache if not existing */
        if(is_null($cache_instance->get())) {

            $result = database()->query("SELECT * FROM `websites_goals` WHERE `website_id` = {$website_id}");
            $data = [];

            while($row = $result->fetch_object()) {

                $data[] = $row;

            }

            cache()->save(
                $cache_instance->set($data)->expiresAfter(CACHE_DEFAULT_SECONDS)->addTag('website_id=' . $website_id)
            );

        } else {

            /* Get cache */
            $data = $cache_instance->get();

        }

        return $data;
    }

}
