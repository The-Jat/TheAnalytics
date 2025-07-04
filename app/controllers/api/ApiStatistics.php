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

use Altum\Response;
use Altum\Traits\Apiable;

defined('ALTUMCODE') || die();

class ApiStatistics extends Controller {
    use Apiable;
    public $website;
    public $datetime;

    public function index() {

        $this->verify_request();

        /* Decide what to continue with */
        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':

                /* Detect if we only need an object, or the whole list */
                if(isset($this->params[0])) {
                    $this->get();
                }

            break;
        }

        $this->return_404();
    }

    private function get() {

        $website_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        /* Try to get details about the resource id */
        $this->website = $website = db()->where('website_id', $website_id)->where('user_id', $this->api_user->user_id)->getOne('websites');

        /* We haven't found the resource */
        if(!$website) {
            $this->return_404();
        }

        /* :) */
        $this->datetime = \Altum\Date::get_start_end_dates_new();

        $type = isset($_GET['type']) && method_exists($this, $_GET['type']) ? $_GET['type'] : 'overview';

        $this->{$type}();

    }

    private function realtime() {
        $start_date = (new \DateTime())->modify('-5 minute')->format('Y-m-d H:i:s');
        $end_date = (new \DateTime())->format('Y-m-d H:i:s');

        $this->datetime = \Altum\Date::get_start_end_dates($start_date, $end_date , \Altum\Date::$default_timezone, \Altum\Date::$default_timezone);

        switch($this->website->tracking_type) {
            case 'lightweight':

                $pageviews = database()->query("
                    SELECT 
                        COUNT(*) AS `pageviews`
                    FROM 
                        `lightweight_events`
                    WHERE 
                        `website_id` = {$this->website->website_id} 
                        AND (`date` BETWEEN '{$this->datetime->start_date_query}' AND '{$this->datetime->end_date_query}')
                ")->fetch_object()->pageviews ?? 0;

                Response::jsonapi_success([
                    'pageviews' => $pageviews,
                ]);

                break;

            case 'normal':

                $visitors = database()->query("
                    SELECT 
                        COUNT(*) AS `visitors`
                    FROM 
                        `websites_visitors` 
                    WHERE
                        `website_id` = {$this->website->website_id} 
                        AND (`last_date` BETWEEN '{$this->datetime->start_date_query}' AND '{$this->datetime->end_date_query}')
                ")->fetch_object()->visitors ?? 0;

                $pageviews = database()->query("
                    SELECT 
                        COUNT(*) AS `pageviews`
                    FROM 
                        `sessions_events` 
                    WHERE
                        `website_id` = {$this->website->website_id} 
                        AND (`date` BETWEEN '{$this->datetime->start_date_query}' AND '{$this->datetime->end_date_query}')
                ")->fetch_object()->pageviews ?? 0;

                Response::jsonapi_success([
                    'visitors' => (int) $visitors,
                    'pageviews' => (int) $pageviews,
                ]);

                break;
        }
    }

    private function overview() {
        $logs = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $convert_tz_sql = get_convert_tz_sql('`date`', $this->api_user->timezone);
                $result = database()->query("
                    SELECT 
                        COUNT(*) AS `pageviews`, 
                        SUM(CASE WHEN `type` = 'landing_page' THEN 1 ELSE 0 END) AS `visitors`,
                        DATE_FORMAT({$convert_tz_sql}, '{$this->datetime['query_date_format']}') AS `formatted_date`
                    FROM 
                        `lightweight_events`
                    WHERE 
                        `website_id` = {$this->website->website_id} 
                        AND ({$convert_tz_sql} BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `formatted_date`
                ");

                while($row = $result->fetch_object()) {
                    $logs[] = [
                        'pageviews' => (int) $row->pageviews,
                        'visitors' => (int) $row->visitors,
                        'formatted_date' => $this->datetime['process']($row->formatted_date, true),
                    ];
                }
                break;

            case 'normal':
                $convert_tz_sql = get_convert_tz_sql('`sessions_events`.`date`', $this->api_user->timezone);
                $result = database()->query("
                    SELECT 
                        COUNT(*) AS `pageviews`, 
                        COUNT(DISTINCT `sessions_events`.`session_id`) AS `sessions`, 
                        COUNT(DISTINCT `sessions_events`.`visitor_id`) AS `visitors`,
                        DATE_FORMAT({$convert_tz_sql}, '{$this->datetime['query_date_format']}') AS `formatted_date`
                    FROM 
                        `sessions_events`
                    WHERE 
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND ({$convert_tz_sql} BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `formatted_date`
                ");

                while($row = $result->fetch_object()) {
                    $logs[] = [
                        'pageviews' => (int) $row->pageviews,
                        'sessions' => (int) $row->sessions,
                        'visitors' => (int) $row->visitors,
                        'formatted_date' => $this->datetime['process']($row->formatted_date, true),
                    ];
                }
                break;
        }

        Response::jsonapi_success($logs);
    }

    private function paths() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `path`,
                        COUNT(IFNULL(`path`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}') 
                    GROUP BY
                        `path`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'path' => $row->path,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `sessions_events`.`path`,
                        COUNT(`sessions_events`.`path`) AS `pageviews`,
                        SUM(`sessions_events`.`has_bounced`) AS `bounces`
                    FROM
                        `sessions_events`
                    WHERE
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND (`sessions_events`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                   GROUP BY
                        `sessions_events`.`path`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'path' => $row->path,
                        'pageviews' => (int) $row->pageviews,
                        'bounces' => (int) $row->bounces,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function referrers() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `referrer_host`,
                        COUNT(IFNULL(`referrer_host`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}') 
                    GROUP BY
                        `referrer_host`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'referrer_host' => $row->referrer_host,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `sessions_events`.`referrer_host`,
                        COUNT(IFNULL(`sessions_events`.`referrer_host`, 1)) AS `pageviews`,
                        SUM(`sessions_events`.`has_bounced`) AS `bounces`
                    FROM
                        `sessions_events`
                    WHERE
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND (`sessions_events`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                   GROUP BY
                        `sessions_events`.`referrer_host`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'referrer_host' => $row->referrer_host,
                        'pageviews' => (int) $row->pageviews,
                        'bounces' => (int) $row->bounces,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function referrer_paths() {
        $_GET['referrer_host'] = query_clean($_GET['referrer_host']);

        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `referrer_path`,
                        `referrer_host`,
                        COUNT(IFNULL(`referrer_path`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                         AND `referrer_host` = '{$_GET['referrer_host']}'
                    GROUP BY
                        `referrer_path`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'referrer_host' => $row->referrer_host,
                        'referrer_path' => $row->referrer_path,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `sessions_events`.`referrer_path`,
                        `sessions_events`.`referrer_host`,
                        COUNT(IFNULL(`sessions_events`.`referrer_path`, 1)) AS `pageviews`,
                        SUM(`sessions_events`.`has_bounced`) AS `bounces`
                    FROM
                        `sessions_events`
                    WHERE
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND (`sessions_events`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        AND `referrer_host` = '{$_GET['referrer_host']}'
                    GROUP BY
                        `sessions_events`.`referrer_path`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'referrer_host' => $row->referrer_host,
                        'referrer_path' => $row->referrer_host,
                        'pageviews' => (int) $row->pageviews,
                        'bounces' => (int) $row->bounces,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function continents() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `continent_code`,
                        COUNT(IFNULL(`continent_code`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `continent_code`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'continent_code' => $row->continent_code,
                        'continent_name' => $row->continent_code ? get_continent_from_continent_code($row->continent_code) : l('global.unknown'),
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`continent_code`,
                        COUNT(IFNULL(`websites_visitors`.`continent_code`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`continent_code`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'continent_code' => $row->continent_code,
                        'continent_name' => $row->continent_code ? get_continent_from_continent_code($row->continent_code) : l('global.unknown'),
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function countries() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `country_code`,
                        COUNT(IFNULL(`country_code`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `country_code`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'country_code' => $row->country_code,
                        'country_name' => $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown'),
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`country_code`,
                        COUNT(IFNULL(`websites_visitors`.`country_code`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`country_code`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'country_code' => $row->country_code,
                        'country_name' => $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown'),
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function cities() {
        $_GET['country_code'] = query_clean($_GET['country_code']);

        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `country_code`,
                        `city_name`,
                        COUNT(IFNULL(`city_name`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                         AND `country_code` = '{$_GET['country_code']}'
                    GROUP BY
                        `city_name`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'country_code' => $row->country_code,
                        'country_name' => $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown'),
                        'city_name' => $row->city_name,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`country_code`,
                        `websites_visitors`.`city_name`,
                        COUNT(IFNULL(`websites_visitors`.`city_name`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE 
                                `visitors_sessions`.`website_id` = {$this->website->website_id}
                                AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                                AND `websites_visitors`.`country_code` = '{$_GET['country_code']}'
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`city_name`,
                        `websites_visitors`.`country_code`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'country_code' => $row->country_code,
                        'country_name' => $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown'),
                        'city_name' => $row->city_name,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function operating_systems() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `os_name`,
                        COUNT(IFNULL(`os_name`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `os_name`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'os_name' => $row->os_name,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`os_name`,
                        COUNT(IFNULL(`websites_visitors`.`os_name`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`os_name`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'os_name' => $row->os_name,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function device_types() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `device_type`,
                        COUNT(IFNULL(`device_type`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `device_type`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'device_type' => $row->device_type,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`device_type`,
                        COUNT(IFNULL(`websites_visitors`.`device_type`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`device_type`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'device_type' => $row->device_type,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function browser_names() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `browser_name`,
                        COUNT(IFNULL(`browser_name`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `browser_name`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_name' => $row->browser_name,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`browser_name`,
                        COUNT(IFNULL(`websites_visitors`.`browser_name`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`browser_name`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_name' => $row->browser_name,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function browser_timezones() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `browser_timezone`,
                        COUNT(IFNULL(`browser_timezone`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `browser_timezone`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_timezone' => $row->browser_timezone,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`browser_timezone`,
                        COUNT(IFNULL(`websites_visitors`.`browser_timezone`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`browser_timezone`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_timezone' => $row->browser_timezone,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function utms_source() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `utm_source`,
                        COUNT(IFNULL(`utm_source`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `utm_source`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'utm_source' => $row->utm_source,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `sessions_events`.`utm_source` AS `utm_source`,
                        COUNT(`sessions_events`.`utm_source`) AS `pageviews`
                    FROM
                        `sessions_events`
                    WHERE
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND (`sessions_events`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `sessions_events`.`utm_source`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'utm_source' => $row->utm_source,
                        'pageviews' => (int) $row->pageviews,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function utms_medium_campaign() {
        $_GET['utm_source'] = query_clean($_GET['utm_source']);

        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `utm_source`,
                        `utm_medium`,
                        `utm_campaign`,
                        COUNT(IFNULL(`city_name`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                         AND `utm_source` = '{$_GET['utm_source']}'
                    GROUP BY
                        `utm_source`,
                        `utm_medium`,
                        `utm_campaign`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'utm_source' => $row->utm_source,
                        'utm_medium' => $row->utm_medium,
                        'utm_campaign' => $row->utm_campaign,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `sessions_events`.`utm_source`,
                        `sessions_events`.`utm_medium`,
                        `sessions_events`.`utm_campaign`,
                        COUNT(IFNULL(`sessions_events`.`utm_medium`, 1)) AS `pageviews`,
                        SUM(`sessions_events`.`has_bounced`) AS `bounces`
                    FROM
                        `sessions_events`
                    WHERE
                        `sessions_events`.`website_id` = {$this->website->website_id} 
                        AND `sessions_events`.`utm_source` = '{$_GET['utm_source']}'
                        AND (`sessions_events`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                   GROUP BY
                        `sessions_events`.`utm_source`,
                        `sessions_events`.`utm_medium`,
                        `sessions_events`.`utm_campaign`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'utm_source' => $row->utm_source,
                        'utm_medium' => $row->utm_medium,
                        'utm_campaign' => $row->utm_campaign,
                        'pageviews' => (int) $row->pageviews,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function screen_resolutions() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `screen_resolution`,
                        COUNT(IFNULL(`screen_resolution`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `screen_resolution`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'screen_resolution' => $row->screen_resolution,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`screen_resolution`,
                        COUNT(IFNULL(`websites_visitors`.`screen_resolution`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`screen_resolution`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'screen_resolution' => $row->screen_resolution,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function themes() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `theme`,
                        COUNT(IFNULL(`theme`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `theme`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'theme' => $row->theme,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`theme`,
                        COUNT(IFNULL(`websites_visitors`.`theme`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`theme`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'theme' => $row->theme,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function browser_languages() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
                $result = database()->query("
                    SELECT
                        `browser_language`,
                        COUNT(IFNULL(`browser_language`, 1)) AS `pageviews`
                    FROM
                        `lightweight_events`
                    WHERE
                        `website_id` = {$this->website->website_id}
                         AND (`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                    GROUP BY
                        `browser_language`
                    ORDER BY 
                        `pageviews` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_language' => $row->browser_language,
                        'pageviews' => (int) $row->pageviews
                    ];
                }
                break;

            case 'normal':
                $result = database()->query("
                    SELECT
                        `websites_visitors`.`browser_language`,
                        COUNT(IFNULL(`websites_visitors`.`browser_language`, 1)) AS `visitors`
                    FROM
                        (
                            SELECT DISTINCT `visitors_sessions`.`visitor_id` 
                            FROM `visitors_sessions`
                            JOIN `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                            WHERE `visitors_sessions`.`website_id` = {$this->website->website_id}  AND (`visitors_sessions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `visitors_sessions`
                    RIGHT JOIN 
                        `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                    WHERE
                        `visitors_sessions`.`visitor_id` IS NOT NULL
                    GROUP BY
                        `websites_visitors`.`browser_language`
                    ORDER BY 
                        `visitors` DESC
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'browser_language' => $row->browser_language,
                        'visitors' => (int) $row->visitors,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

    private function goals() {
        $data = [];

        switch($this->website->tracking_type) {
            case 'lightweight':
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
                                AND (`goals_conversions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `conversions`
                    FROM
                        `websites_goals`
                    WHERE
                        `websites_goals`.`website_id` = {$this->website->website_id}  
                    ORDER BY 
                        `conversions` DESC;
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'goal_id' => (int) $row->goal_id,
                        'key' => $row->key,
                        'type' => $row->type,
                        'path' => $row->path,
                        'name' => $row->name,
                        'conversions' => (int) $row->conversions,
                    ];
                }
                break;

            case 'normal':
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
                            WHERE
                                `goals_conversions`.`goal_id` = `websites_goals`.`goal_id`
                                AND `goals_conversions`.`website_id` = {$this->website->website_id} 
                                AND (`goals_conversions`.`date` BETWEEN '{$this->datetime['query_start_date']}' AND '{$this->datetime['query_end_date']}')
                        ) AS `conversions`
                    FROM
                        `websites_goals`
                    WHERE
                        `websites_goals`.`website_id` = {$this->website->website_id}  
                    ORDER BY 
                        `conversions` DESC;
                ");

                while($row = $result->fetch_object()) {
                    $data[] = [
                        'goal_id' => (int) $row->goal_id,
                        'key' => $row->key,
                        'type' => $row->type,
                        'path' => $row->path,
                        'name' => $row->name,
                        'conversions' => (int) $row->conversions,
                    ];
                }
                break;
        }


        Response::jsonapi_success($data);
    }

}
