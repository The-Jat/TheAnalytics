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

use Altum\AnalyticsFilters;
use Altum\Date;
use Altum\Response;

defined('ALTUMCODE') || die();

class StatisticsAjaxNormal extends Controller {
    public $website;
    public $date;
    public $request_type;
    public $by = null;
    public $filters = null;
    public $limit = null;
    public $base_url_path = null;

    public function index() {

        /* Check if public or private call */
        $source = isset($_GET['source']) && in_array($_GET['source'], ['dashboard', 'statistics']) ? $_GET['source'] : 'dashboard';

        if($source == 'dashboard') {
            \Altum\Authentication::guard();

            $this->base_url_path = 'dashboard/';
        } else {
            $pixel_key = isset($_GET['pixel_key']) ? input_clean($_GET['pixel_key']) : null;

            if(!$website = (new \Altum\Models\Website())->get_website_by_pixel_key($pixel_key)) {
                redirect();
            }

            $this->website = $website;

            $this->base_url_path = 'statistics/' . $website->pixel_key . '/';
        }

        /* Do not use sessions anymore to not lockout the user from doing anything else on the site */
        session_write_close();

        if(
            \Altum\Csrf::check('global_token') &&
            isset($_GET['request_type']) &&
            method_exists($this, $_GET['request_type'])
        ) {
            $this->request_type = $_GET['request_type'];
            $this->limit = $_GET['limit'] == -1 ? 5000 : (int) $_GET['limit'];

            /* Date  */
            $start_date = isset($_GET['start_date']) ? query_clean($_GET['start_date']) : (new \DateTime())->modify('-30 day')->format('Y-m-d');
            $end_date = isset($_GET['end_date']) ? query_clean($_GET['end_date']) : (new \DateTime())->format('Y-m-d');

            $this->date = \Altum\Date::get_start_end_dates($start_date, $end_date);

            /* Check if realtime request */
            if(isset($_GET['request_subtype']) && $_GET['request_subtype'] == 'realtime' && $start_date == 'now' && $end_date == 'now') {
                $start_date = (new \DateTime())->modify('-5 minute')->format('Y-m-d H:i:s');
                $end_date = (new \DateTime())->format('Y-m-d H:i:s');

                $this->date = \Altum\Date::get_start_end_dates($start_date, $end_date , \Altum\Date::$default_timezone, \Altum\Date::$default_timezone);

            }

            /* Filters */
            $this->filters = AnalyticsFilters::get_filters_sql(['websites_visitors', 'sessions_events']);

            /* Run the proper method */
            $this->{$this->request_type}();

        }

        die();
    }

    private function realtime() {

        /* Get the countries data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`country_code`,
                COUNT(IFNULL(`websites_visitors`.`country_code`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitor_id` 
                    FROM `websites_visitors` 
                    WHERE `websites_visitors`.`website_id` = {$this->website->website_id}  AND (`last_date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                ) AS `altum`
            INNER JOIN 
                `websites_visitors` ON `altum`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`country_code`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'countries';
        $this->by = 'visitors';
        $countries_data = $this->process($result);
        $countries_data['cities_link'] = false;
        $countries_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($countries_data);


        /* Get the device types data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`device_type`,
                COUNT(IFNULL(`websites_visitors`.`device_type`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitor_id` 
                    FROM `websites_visitors` 
                    WHERE `websites_visitors`.`website_id` = {$this->website->website_id}  AND (`last_date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                ) AS `altum`
            INNER JOIN 
                `websites_visitors` ON `altum`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`device_type`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'device_types';
        $this->by = 'visitors';
        $device_types_data = $this->process($result);
        $device_types_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($device_types_data);


        /* Get the paths data */
        $result = database()->query("
            SELECT
                `sessions_events`.`path`,
                COUNT(`sessions_events`.`path`) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
                `sessions_events`
            LEFT JOIN
                `websites_visitors` ON `sessions_events`.`event_id` = `websites_visitors`.`last_event_id`
            WHERE
                `websites_visitors`.`website_id` = {$this->website->website_id} 
                AND (`websites_visitors`.`last_date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            GROUP BY
                `path`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'paths';
        $this->by = 'visitors';
        $paths_data = $this->process($result);
        $paths_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($paths_data);


        /* Get the visitors count data */
        $visitors_total = database()->query("
            SELECT 
                COUNT(*) AS `total`
            FROM 
                `websites_visitors` 
            WHERE
                `website_id` = {$this->website->website_id} 
			    AND (`last_date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            ORDER BY
                `last_date` DESC
        ")->fetch_object()->total ?? 0;


        /* Get the visitors chart data */
        $logs_chart = [];

        $result = database()->query("
            SELECT 
                COUNT(*) AS `pageviews`,
                `date`
            FROM 
                `sessions_events` 
            WHERE 
                `website_id` = {$this->website->website_id} 
			    AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            GROUP BY
                `date`
        ");

        /* Generate the raw chart data and save logs for later usage */
        while($row = $result->fetch_object()) {

            /* Insert data for the chart */
            $formatted_date = Date::get($row->date, 'H:i');

            if(isset($logs_chart[$formatted_date])) {
                $logs_chart[$formatted_date] = [
                    'pageviews' => $logs_chart[$formatted_date]['pageviews'] + $row->pageviews
                ];
            } else {
                $logs_chart[$formatted_date] = [
                    'pageviews' => $row->pageviews
                ];
            }

        }


        $logs_chart = get_chart_data($logs_chart);

        Response::json('', 'success', [
            'logs_chart_labels' => $logs_chart['labels'],
            'logs_chart_pageviews' => $logs_chart['pageviews'] ?? '[]',

            'visitors_total' => $visitors_total,

            'countries_html' => $countries_view,
            'countries_data' => $countries_data,

            'paths_html' => $paths_view,
            'paths_data' => $paths_data,

            'device_types_html' => $device_types_view,
            'device_types_data' => $device_types_data,
        ]);
    }

    private function continents() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`continent_code`,
                COUNT(IFNULL(`websites_visitors`.`continent_code`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`continent_code`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function countries() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`country_code`,
                COUNT(IFNULL(`websites_visitors`.`country_code`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`country_code`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function cities() {

        /* Search by country_code */
        $search_by_country_code_query = null;

        if(isset($_GET['country_code'])) {
            $_GET['country_code'] = query_clean($_GET['country_code']);
            $search_by_country_code_query = "AND `websites_visitors`.`country_code` = '{$_GET['country_code']}'";
        }

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`city_name`,
                `websites_visitors`.`country_code`,
                COUNT(IFNULL(`websites_visitors`.`country_code`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id} AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$search_by_country_code_query} {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`country_code`,
                `websites_visitors`.`city_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function operating_systems() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`os_name`,
                COUNT(IFNULL(`websites_visitors`.`country_code`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`os_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function screen_resolutions() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`screen_resolution`,
                COUNT(IFNULL(`websites_visitors`.`screen_resolution`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`screen_resolution`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function themes() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`theme`,
                COUNT(IFNULL(`websites_visitors`.`theme`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`theme`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function browser_languages() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`browser_language`,
                COUNT(IFNULL(`websites_visitors`.`browser_language`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`browser_language`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function browser_timezones() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`browser_timezone`,
	            COUNT(IFNULL(`websites_visitors`.`browser_timezone`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`browser_timezone`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function device_types() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`device_type`,
                COUNT(`websites_visitors`.`device_type`) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`device_type`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function browser_names() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_visitors`.`browser_name`,
                COUNT(IFNULL(`websites_visitors`.`browser_name`, 1)) AS `total`
            FROM
                (
                    SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                    FROM `visitors_sessions`
                    JOIN `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                    JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') {$this->filters}
                ) AS `visitors_sessions`
            INNER JOIN 
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            GROUP BY
                `websites_visitors`.`browser_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'visitors';

        $this->process_and_run($result);
    }

    private function paths() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`path`,
                COUNT(`sessions_events`.`path`) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    {$this->filters}
           GROUP BY
                `sessions_events`.`path`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function landing_paths() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`path`,
                COUNT(`sessions_events`.`path`) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    AND `sessions_events`.`type` = 'landing_page'
			    {$this->filters}
           GROUP BY
                `sessions_events`.`path`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function exit_paths() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`path`,
                COUNT(`sessions_events`.`path`) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
                (
                    SELECT
                        `sessions_events`.`session_id`,
                        MAX(`sessions_events`.`event_id`) AS `event_id`
                    FROM `sessions_events`
                    LEFT JOIN  `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE 
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                        {$this->filters}
                    GROUP BY `sessions_events`.`session_id`
                ) AS `sessions_events_x`
            LEFT JOIN
            	`sessions_events` ON `sessions_events_x`.`event_id` = `sessions_events`.`event_id`
            GROUP BY
                `sessions_events`.`path`
            ORDER BY 
                `total` DESC;
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function referrers() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`referrer_host`,
                COUNT(IFNULL(`sessions_events`.`referrer_host`, 1)) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    {$this->filters}
           GROUP BY
                `sessions_events`.`referrer_host`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function referrer_paths() {
        $_GET['referrer_host'] = query_clean($_GET['referrer_host']);

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`referrer_path`,
                `sessions_events`.`referrer_host`,
                COUNT(IFNULL(`sessions_events`.`referrer_path`, 1)) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND `sessions_events`.`referrer_host` = '{$_GET['referrer_host']}'
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
           GROUP BY
                `sessions_events`.`referrer_path`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function social_media_referrers() {

        /* Get the data */
        $result = database()->query("
            SELECT
                CASE
                    WHEN `sessions_events`.`referrer_host` = 'l.facebook.com' then 'facebook.com'
                    WHEN `sessions_events`.`referrer_host` = 'l.threads.com' then 'threads.com'
                    WHEN `sessions_events`.`referrer_host` = 'l.instagram.com' then 'instagram.com'
                    WHEN `sessions_events`.`referrer_host` LIKE '%.pinterest.com' then 'pinterest.com'
                    WHEN `sessions_events`.`referrer_host` = 't.co' then 'twitter.com'
                    WHEN `sessions_events`.`referrer_host` = 'www.youtube.com' then 'youtube.com'
                    WHEN `sessions_events`.`referrer_host` = 'www.tiktok.com' then 'tiktok.com'
                END AS `referrer`,
                COUNT(IFNULL(`sessions_events`.`referrer_host`, 1)) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    AND (
			        `sessions_events`.`referrer_host` IN ('l.threads.com', 'l.facebook.com', 't.co', 'www.pinterest.com', 'l.instagram.com', 'www.youtube.com', 'www.tiktok.com') 
			        OR `sessions_events`.`referrer_host` LIKE '%.pinterest.com'
			    )
			    {$this->filters}
            GROUP BY
                `referrer`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function search_engines_referrers() {

        /* Get the data */
        $result = database()->query("
            SELECT
                CASE
                    WHEN `sessions_events`.`referrer_host` = 'www.bing.com' then 'bing.com'
                    WHEN `sessions_events`.`referrer_host` = 'www.baidu.com' then 'baidu.com'
                    WHEN `sessions_events`.`referrer_host` LIKE 'www.google.%' then 'google.com'
                    WHEN `sessions_events`.`referrer_host` LIKE '%.yahoo.com' then 'yahoo.com'
                    WHEN `sessions_events`.`referrer_host` = 'yandex.com' then 'yandex.com'
                END AS `referrer`,
                COUNT(IFNULL(`sessions_events`.`referrer_host`, 1)) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    AND (
			        `sessions_events`.`referrer_host` IN ('www.bing.com', 'bing.com', 'www.baidu.com', 'baidu.com', 'yandex.com') 
			        OR `sessions_events`.`referrer_host` LIKE 'www.google.%' 
			        OR `sessions_events`.`referrer_host` LIKE '%.yahoo.com'
			    )
			    {$this->filters}
            GROUP BY
                `referrer`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function utms_source() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`utm_source` AS `utm`,
                COUNT(IFNULL(`sessions_events`.`utm_source`, 0)) AS `total`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
           GROUP BY
                `sessions_events`.`utm_source`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'utms';
        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function utms_medium_campaign() {
        $_GET['utm_source'] = query_clean($_GET['utm_source']);

        /* Get the data */
        $result = database()->query("
            SELECT
                `sessions_events`.`utm_medium`,
                `sessions_events`.`utm_campaign`,
                COUNT(IFNULL(`sessions_events`.`utm_medium`, 1)) AS `total`,
                SUM(`sessions_events`.`has_bounced`) AS `bounces`
            FROM
            	`sessions_events`
            LEFT JOIN 
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			    `sessions_events`.`website_id` = {$this->website->website_id} 
			    AND `sessions_events`.`utm_source` = '{$_GET['utm_source']}'
			    AND (`sessions_events`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
           GROUP BY
                `sessions_events`.`utm_medium`,
                `sessions_events`.`utm_campaign`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    /* Goals */
    private function goals() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `websites_goals`.`goal_id`,
                `websites_goals`.`key`,
                `websites_goals`.`type`,
                `websites_goals`.`path`,
                `websites_goals`.`name`,
                (
                	SELECT 
                		COUNT(`goals_conversions`.`conversion_id`) 
                	FROM 
                		`goals_conversions`
                	LEFT JOIN
                		`sessions_events` ON `sessions_events`.`event_id` = `goals_conversions`.`event_id`
            		LEFT JOIN
                		`websites_visitors` ON `websites_visitors`.`visitor_id` = `goals_conversions`.`visitor_id`
            		WHERE
            			`goals_conversions`.`goal_id` = `websites_goals`.`goal_id`
                		AND `goals_conversions`.`website_id` = {$this->website->website_id} 
                		AND (`goals_conversions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			            {$this->filters}
                ) AS `total`
            FROM
                `websites_goals`
            WHERE
                `websites_goals`.`website_id` = {$this->website->website_id}  
            ORDER BY 
                `total` DESC;
        ");

        $this->by = 'conversions';

        $this->process_and_run($result);
    }

    private function goals_chart() {

        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date, Date::$default_timezone);

        $filters = AnalyticsFilters::get_filters_sql(['websites_visitors', 'sessions_events']);

        $convert_tz_sql = get_convert_tz_sql('`goals_conversions`.`date`', $this->user->timezone);

        /* Get the visitors chart data */
        $logs_chart = [];

        $result = database()->query("
            SELECT 
                COUNT(*) AS `conversions`,
                DATE_FORMAT({$convert_tz_sql}, '{$datetime['query_date_format']}') AS `formatted_date`
            FROM 
                `goals_conversions` 
            LEFT JOIN
                `sessions_events` ON `sessions_events`.`event_id` = `goals_conversions`.`event_id`
            LEFT JOIN
                `websites_visitors` ON `websites_visitors`.`visitor_id` = `goals_conversions`.`visitor_id`
            WHERE 
                 `goals_conversions`.`website_id` = {$this->website->website_id} 
			    AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                {$filters}
            GROUP BY
                `formatted_date`
        ");

        /* Generate the raw chart data and save goals for later usage */
        while($row = $result->fetch_object()) {
            $formatted_date = $datetime['process']($row->formatted_date, true);

            /* Insert data for the chart */
            $logs_chart[$formatted_date] = [
                'conversions' => $row->conversions,
            ];
        }

        $logs_chart = get_chart_data($logs_chart);

        Response::json('', 'success', [
            'logs_chart_labels' => $logs_chart['labels'],
            'logs_chart_conversions' => $logs_chart['conversions'] ?? '[]',
        ]);
    }

    private function process($result) {
        /* Go over the result */
        $rows = [];
        $total_sum = 0;
        $total_rows = 0;
        $options = [];

        while($row = $result->fetch_object()) {
            $total_rows++;

            if(!$this->limit || ($this->limit && $total_rows <= $this->limit)) {
                $rows[] = $row;

                $total_sum += $row->total;
            }

        }

        /* Check for options in displayment */
        $options['bounce_rate'] = isset($_GET['bounce_rate']) && $_GET['bounce_rate'] == 'true';

        return [
            'by'        => $this->by,
            'rows'      => $rows,
            'options'   => $options,
            'total_sum' => $total_sum,
            'total_rows'=> $total_rows
        ];
    }

    private function process_and_run($result) {

        /* Prepare the view */
        $data = $this->process($result);

        $view = new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this);

        Response::json('', 'success', ['html' => $view->run($data), 'data' => json_encode($data)]);

    }

}
