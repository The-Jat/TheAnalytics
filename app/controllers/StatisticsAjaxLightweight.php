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

class StatisticsAjaxLightweight extends Controller {
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
            $this->filters = AnalyticsFilters::get_filters_sql(['lightweight_events']);

            /* Run the proper method */
            $this->{$this->request_type}();

        }

        die();
    }

    private function realtime() {

        /* Get the countries data */
        $result = database()->query("
            SELECT
                `country_code`,
                COUNT(IFNULL(`country_code`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}') 
            GROUP BY
                `country_code`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'countries';
        $this->by = 'pageviews';
        $countries_data = $this->process($result);
        $countries_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($countries_data);


        /* Get the device types data */
        $result = database()->query("
            SELECT
                `device_type`,
                COUNT(`device_type`) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            GROUP BY
                `device_type`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'device_types';
        $this->by = 'pageviews';
        $device_types_data = $this->process($result);
        $device_types_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($device_types_data);


        /* Get the paths data */
        $result = database()->query("
            SELECT
                `path`,
                COUNT(IFNULL(`path`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            GROUP BY
                `path`
            ORDER BY 
                `total` DESC
        ");

        $this->request_type = 'paths';
        $this->by = 'pageviews';
        $paths_data = $this->process($result);
        $paths_view = (new \Altum\View('dashboard/ajaxed_partials/' . $this->request_type, (array) $this))->run($paths_data);


        /* Get the visitors chart data */
        $logs_chart = [];

        $result = database()->query("
            SELECT 
                COUNT(*) AS `pageviews`, 
                `date`
            FROM 
                `lightweight_events`
            WHERE 
                `website_id` = {$this->website->website_id} 
			    AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
            GROUP BY
                `date`
        ");

        $visitors_total = 0;

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

            $visitors_total += $row->pageviews;
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
                `continent_code`,
                COUNT(IFNULL(`continent_code`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `continent_code`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function countries() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `country_code`,
                COUNT(IFNULL(`country_code`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `country_code`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function cities() {

        /* Search by country_code */
        $search_by_country_code_query = null;

        if(isset($_GET['country_code'])) {
            $_GET['country_code'] = query_clean($_GET['country_code']);
            $search_by_country_code_query = "AND `country_code` = '{$_GET['country_code']}'";
        }

        /* Get the data */
        $result = database()->query("
            SELECT
                `city_name`,
                `country_code`,
                COUNT(IFNULL(`country_code`, 1)) AS `total`
            FROM
            	`lightweight_events`
			WHERE
			    `website_id` = {$this->website->website_id} 
			    {$search_by_country_code_query}
			    AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    {$this->filters}
           GROUP BY
                `country_code`,
                `city_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function operating_systems() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `os_name`,
                COUNT(IFNULL(`os_name`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
                 AND `os_name` IS NOT NULL
            GROUP BY
                `os_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function screen_resolutions() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `screen_resolution`,
                COUNT(IFNULL(`screen_resolution`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
                 AND `screen_resolution` IS NOT NULL
            GROUP BY
                `screen_resolution`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function themes() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `theme`,
                COUNT(IFNULL(`theme`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
                 AND `theme` IS NOT NULL
            GROUP BY
                `theme`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function browser_languages() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `browser_language`,
                COUNT(IFNULL(`browser_language`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `browser_language`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function browser_timezones() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `browser_timezone`,
                COUNT(IFNULL(`browser_timezone`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `browser_timezone`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function device_types() {

        /* Get the data */
        $result = database()->query("
           SELECT
                `device_type`,
                COUNT(IFNULL(`device_type`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
                 AND `device_type` IS NOT NULL
            GROUP BY
                `device_type`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function browser_names() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `browser_name`,
                COUNT(IFNULL(`browser_name`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `browser_name`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function paths() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `path`,
                COUNT(IFNULL(`path`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `path`
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
                `path`,
                COUNT(IFNULL(`path`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
                 AND `type` = 'landing_page' 
            GROUP BY
                `path`
            ORDER BY 
                `total` DESC
        ");

        $this->by = 'pageviews';

        $this->process_and_run($result);
    }

    private function referrers() {

        /* Get the data */
        $result = database()->query("
            SELECT
                `referrer_host`,
                COUNT(IFNULL(`referrer_host`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `referrer_host`
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
                `referrer_path`,
                `referrer_host`,
                COUNT(IFNULL(`referrer_path`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                 AND `referrer_host` = '{$_GET['referrer_host']}'
                {$this->filters}
            GROUP BY
                `referrer_path`
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
                    WHEN `referrer_host` = 'l.threads.com' THEN 'threads.com'
                    WHEN `referrer_host` IN ('l.facebook.com', 'lm.facebook.com', 'm.facebook.com', 'www.facebook.com', 'staticxx.facebook.com') THEN 'facebook.com'
                    WHEN `referrer_host` IN ('l.instagram.com', 'www.instagram.com') THEN 'instagram.com'
                    WHEN `referrer_host` LIKE '%.pinterest.com' OR `referrer_host` = 'www.pinterest.com' THEN 'pinterest.com'
                    WHEN `referrer_host` IN ('t.co', 'twitter.com') THEN 'x.com'
                    WHEN `referrer_host` IN ('www.youtube.com', 'm.youtube.com', 'youtube.com') THEN 'youtube.com'
                    WHEN `referrer_host` IN ('www.tiktok.com', 'm.tiktok.com') THEN 'tiktok.com'
                    WHEN `referrer_host` IN ('www.reddit.com', 'reddit.com') THEN 'reddit.com'
                    WHEN `referrer_host` IN ('www.linkedin.com', 'linkedin.com') THEN 'linkedin.com'
                    WHEN `referrer_host` IN ('story.snapchat.com', 'www.snapchat.com') THEN 'snapchat.com'
                    WHEN `referrer_host` IN ('t.me', 'telegram.me') THEN 'telegram.org'
                END AS `referrer`,
                COUNT(IFNULL(`referrer_host`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                AND (
                    `referrer_host` IN (
                        'l.threads.com', 'l.facebook.com', 'lm.facebook.com', 'm.facebook.com', 'www.facebook.com', 'staticxx.facebook.com',
                        'l.instagram.com', 'www.instagram.com',
                        'www.pinterest.com', 't.co', 'twitter.com',
                        'www.youtube.com', 'm.youtube.com', 'youtube.com',
                        'www.tiktok.com', 'm.tiktok.com',
                        'www.reddit.com', 'reddit.com',
                        'www.linkedin.com', 'linkedin.com',
                        'story.snapchat.com', 'www.snapchat.com',
                        't.me', 'telegram.me'
                    )
                    OR `referrer_host` LIKE '%.pinterest.com'
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
                    WHEN `referrer_host` IN ('www.bing.com', 'bing.com') THEN 'bing.com'
                    WHEN `referrer_host` IN ('www.baidu.com', 'baidu.com') THEN 'baidu.com'
                    WHEN `referrer_host` LIKE 'www.google.%' OR `referrer_host` LIKE 'google.%' THEN 'google.com'
                    WHEN `referrer_host` LIKE 'search.yahoo.com' OR `referrer_host` LIKE 'www.yahoo.com' OR `referrer_host` LIKE '%.yahoo.com' THEN 'yahoo.com'
                    WHEN `referrer_host` IN ('yandex.com', 'www.yandex.com') THEN 'yandex.com'
                    WHEN `referrer_host` IN ('duckduckgo.com', 'www.duckduckgo.com') THEN 'duckduckgo.com'
                    WHEN `referrer_host` IN ('ecosia.org', 'www.ecosia.org') THEN 'ecosia.org'
                    WHEN `referrer_host` IN ('startpage.com', 'www.startpage.com') THEN 'startpage.com'
                    WHEN `referrer_host` IN ('search.aol.com') THEN 'aol.com'
                    WHEN `referrer_host` LIKE 'search.brave.com' THEN 'brave.com'
                END AS `referrer`,
                COUNT(IFNULL(`referrer_host`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                AND (
                    `referrer_host` IN (
                        'www.bing.com', 'bing.com',
                        'www.baidu.com', 'baidu.com',
                        'yandex.com', 'www.yandex.com',
                        'duckduckgo.com', 'www.duckduckgo.com',
                        'ecosia.org', 'www.ecosia.org',
                        'startpage.com', 'www.startpage.com',
                        'search.aol.com',
                        'search.brave.com'
                    )
                    OR `referrer_host` LIKE 'www.google.%'
                    OR `referrer_host` LIKE 'google.%'
                    OR `referrer_host` LIKE '%.yahoo.com'
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
                `utm_source` AS `utm`,
                COUNT(IFNULL(`utm_source`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
                {$this->filters}
            GROUP BY
                `utm_source`
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
                `utm_medium`,
                `utm_campaign`,
                COUNT(IFNULL(`utm_medium`, 1)) AS `total`
            FROM
                `lightweight_events`
            WHERE
                `website_id` = {$this->website->website_id}
                 AND (`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
			    AND `utm_source` = '{$_GET['utm_source']}'
                {$this->filters}
            GROUP BY
                `utm_medium`,
                `utm_campaign`
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
                		COUNT(*) 
                	FROM 
                		`goals_conversions`
            		WHERE
            			`goals_conversions`.`goal_id` = `websites_goals`.`goal_id`
                		AND `goals_conversions`.`website_id` = {$this->website->website_id} 
                		AND (`goals_conversions`.`date` BETWEEN '{$this->date->start_date_query}' AND '{$this->date->end_date_query}')
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

        $filters = AnalyticsFilters::get_filters_sql(['lightweight_events']);

        $convert_tz_sql = get_convert_tz_sql('`goals_conversions`.`date`', $this->user->timezone);

        /* Get the visitors chart data */
        $logs_chart = [];

        $result = database()->query("
            SELECT 
                COUNT(*) AS `conversions`,
                DATE_FORMAT({$convert_tz_sql}, '{$datetime['query_date_format']}') AS `formatted_date`
            FROM 
                `goals_conversions` 
            WHERE 
                `website_id` = {$this->website->website_id} 
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
        $options['bounce_rate'] = false;

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
