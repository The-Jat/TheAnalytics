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

namespace Altum\Controllers;

use Altum\Models\User;


defined('ALTUMCODE') || die();

class Pixel extends Controller {

    public function index() {
        $seconds_to_cache = settings()->analytics->pixel_cache;
        header('Content-Type: application/javascript');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds_to_cache) . ' GMT');
        header('Pragma: cache');
        header('Cache-Control: max-age=' . $seconds_to_cache);

        /* Clean the pixel key */
        $pixel_key = isset($this->params[0]) ? input_clean($this->params[0]) : null;

        /* Get the details of the website from the database */
        $website = (new \Altum\Models\Website())->get_website_by_pixel_key($pixel_key);

        if(!$website) {
            die("console.log('" . settings()->main->title . " (" . SITE_URL. "): No website found for this pixel.')");
        }

        if(!$website->is_enabled) {
            die("console.log('" . settings()->main->title . " (" . SITE_URL. "): Website disabled.')");
        }

        /* Check against bots */
        if($website->bot_exclusion_is_enabled) {
            $CrawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();

            if($CrawlerDetect->isCrawler()) {
                die("console.log('" . settings()->main->title . " (" . SITE_URL. "): Bot usage has been detected, pixel stopped from executing.')");
            }
        }

        /* Check excluded IPs */
        $excluded_ips = $this->website->excluded_ips ? array_flip(explode(',', $this->website->excluded_ips)) : [];

        /* Do not track if it's an excluded ip */
        if(isset($excluded_ips[get_ip()])) {
            die(settings()->main->title . " (" . SITE_URL . "): Your IP is excluded from being tracked.");
        }

        /* Make sure to get the user data and confirm the user is ok */
        $user = (new \Altum\Models\User())->get_user_by_user_id($website->user_id);

        if(!$user) {
            die("console.log('" . settings()->main->title . " (" . SITE_URL. "): Website owner not found.')");
        }

        if($user->status != 1) {
            die("console.log('" . settings()->main->title . " (" . SITE_URL. "): Website owner is disabled.')");
        }

        /* Check for a custom domain */
        if(isset(\Altum\Router::$data['domain']) && $website->domain_id != \Altum\Router::$data['domain']->domain_id) {
            die("console.log('" . settings()->main->title . " (" . SITE_URL. "): Domain id mismatch.')");
        }

        /* Process the plan of the user */
        (new User())->process_user_plan_expiration_by_user($user);

        /* Make sure that the user didn't exceed the current plan */
        if($user->plan_settings->sessions_events_limit != -1 && $website->current_month_sessions_events >= $user->plan_settings->sessions_events_limit) {
            die(settings()->main->title . " (" . SITE_URL. "): Your plan limit has been reached.");
        }

        $pixel_track_events_children = (bool) $website->events_children_is_enabled && ($user->plan_settings->events_children_limit == -1 || $website->current_month_events_children < $user->plan_settings->events_children_limit);
        $pixel_track_sessions_replays = (bool) settings()->analytics->sessions_replays_is_enabled && $website->sessions_replays_is_enabled && ($user->plan_settings->sessions_replays_limit == -1 || $website->current_month_sessions_replays < $user->plan_settings->sessions_replays_limit);

        /* Get heatmaps if any and if the user has rights */
        $pixel_heatmaps = [];

        if($website->tracking_type == 'normal' && $user->plan_settings->websites_heatmaps_limit != 0) {
            $pixel_heatmaps = (new \Altum\Models\WebsitesHeatmaps())->get_website_heatmaps_by_website_id($website->website_id);

            foreach($pixel_heatmaps as $key => $pixel_heatmap) {
                /* Make sure the heatmap is active */
                if(!$pixel_heatmap->is_enabled) {
                    unset($pixel_heatmaps[$key]);
                    continue;
                }

                /* Generate the full url needed to match for the heatmap */
                $pixel_heatmap->url = $website->host . $website->path . $pixel_heatmap->path;
            }
        }

        /* Get available goals for the website */
        if($user->plan_settings->websites_goals_limit != 0) {
            $pixel_goals = (new \Altum\Models\WebsitesGoals())->get_website_goals_by_website_id($website->website_id);

            foreach($pixel_goals as $pixel_goal) {
                /* Generate the full url needed to match */
                $pixel_goal->url = $website->host . $website->path . $pixel_goal->path;
            }
        }

        /* Main View */
        $data = [];

        switch($website->tracking_type) {
            case 'lightweight':

                /* :) */

                break;

            case 'normal':
                $data = [
                    'pixel_heatmaps'                => $pixel_heatmaps,
                    'pixel_track_events_children'   => $pixel_track_events_children,
                    'pixel_track_sessions_replays'  => $pixel_track_sessions_replays
                ];

                break;
        }

        $data['pixel_key'] = $pixel_key;
        $data['pixel_goals'] = $pixel_goals ?? [];
        $data['pixel_query_parameters_tracking_is_enabled'] = $website->query_parameters_tracking_is_enabled;

        $view = new \Altum\View('pixel/' . $website->tracking_type . '/pixel', (array) $this);

        echo $view->run($data);

    }
}
