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

use Altum\Alerts;
use Altum\AnalyticsFilters;
use Altum\Date;
use Altum\Title;

defined('ALTUMCODE') || die();

class Dashboard extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        if(!$this->website) {
            redirect('websites');
        }

        $type = isset($this->params[0]) && in_array($this->params[0], ['paths', 'referrers', 'screen-resolutions', 'utms', 'operating-systems', 'device-types', 'continents', 'countries', 'cities', 'browser-names', 'browser-languages', 'browser-timezones', 'goals', 'realtime', 'themes']) ? query_clean(str_replace('-', '_', $this->params[0])) : 'default';

        /* Check to see if we need to switch the selected website */
        if(isset($_GET['website_id']) && array_key_exists($_GET['website_id'], $this->websites)) {
            $redirect = $_GET['redirect'] ?? 'dashboard';

            $_COOKIE['selected_website_id'] = (int) $_GET['website_id'];

            setcookie('selected_website_id', (int) $_GET['website_id'], time() + (86400 * 30), COOKIE_PATH);

            redirect($redirect);
        }

        $base_url_path = 'dashboard/';

        /* Custom realtime page */
        if($type == 'realtime') {

            Title::set(sprintf(l('dashboard.title_dynamic'), l('realtime.header'), $this->website->name));

            /* Prepare the view */
            $data = [
                'base_url_path' => $base_url_path,
            ];

            $view = new \Altum\View('realtime/index', (array)$this);

            $this->add_view_content('content', $view->run($data));
        } else {

            /* Load data based on the website type */
            $dashboard = $this->{$this->website->tracking_type}();

            /* Referrer Paths Modal */
            $view = new \Altum\View('dashboard/referrer_paths_modal', (array)$this);
            \Altum\Event::add_content($view->run(), 'modals');

            /* UTMs medium campaign Modal */
            $view = new \Altum\View('dashboard/utms_medium_campaign_modal', (array)$this);
            \Altum\Event::add_content($view->run(), 'modals');

            /* Cities Modal */
            $view = new \Altum\View('dashboard/cities_modal', (array)$this);
            \Altum\Event::add_content($view->run(), 'modals');

            /* Create Goal Modal */
            $view = new \Altum\View('dashboard/goal_create_modal', (array)$this);
            \Altum\Event::add_content($view->run(), 'modals');

            /* Update Goal Modal */
            $view = new \Altum\View('dashboard/goal_update_modal', (array)$this);
            \Altum\Event::add_content($view->run(), 'modals');

            /* Set a custom title */
            if($type == 'default') {
                Title::set(sprintf(l('dashboard.title'), $this->website->name));
            } else {
                Title::set(sprintf(l('dashboard.title_dynamic'), l('dashboard.' . $type . '.header'), $this->website->name));
            }

            /* Prepare the inside content View */
            $data = [
                'logs' => $dashboard['logs'],
                'basic_totals' => $dashboard['basic_totals'],
                'logs_chart' => $dashboard['logs_chart'],
                'has_logs' => count($dashboard['logs']),
                'base_url_path' => $base_url_path,
            ];

            $view = new \Altum\View('dashboard/partials/' . $type, (array)$this);
            $this->add_view_content('dashboard_content', $view->run($data));


            /* Prepare the view */
            $data = [
                'datetime' => $dashboard['datetime'],
                'logs' => $dashboard['logs'],
                'basic_totals' => $dashboard['basic_totals'],
                'logs_chart' => $dashboard['logs_chart'],
                'has_logs' => count($dashboard['logs']),
                'type' => $type,
                'base_url_path' => $base_url_path,
            ];

            $view = new \Altum\View('dashboard/index', (array)$this);
            $this->add_view_content('content', $view->run($data));

        }
    }

    private function normal() {
        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date, Date::$default_timezone);

        /* Get basic overall data */
        $logs = [];
        $logs_chart = [];
        $basic_totals = [
            'pageviews' => 0,
            'sessions'  => 0,
            'visitors'  => 0
        ];

        $filters = AnalyticsFilters::get_filters_sql(['websites_visitors', 'sessions_events']);

        $convert_tz_sql = get_convert_tz_sql('`sessions_events`.`date`', $this->user->timezone);

        /* Apply different query when filters are applied */
        if($filters) {
            $result = database()->query("
                SELECT 
                    COUNT(*) AS `pageviews`, 
                    COUNT(DISTINCT `sessions_events`.`session_id`) AS `sessions`, 
                    COUNT(DISTINCT `sessions_events`.`visitor_id`) AS `visitors`,
                    DATE_FORMAT({$convert_tz_sql}, '{$datetime['query_date_format']}') AS `formatted_date`
                FROM 
                    `sessions_events`
                LEFT JOIN
                    `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
                WHERE 
                    `sessions_events`.`website_id` = {$this->website->website_id} 
                    AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                    {$filters}
                GROUP BY
                    `formatted_date`
            ");
        } else {
            $result = database()->query("
                SELECT 
                    COUNT(*) AS `pageviews`, 
                    COUNT(DISTINCT `sessions_events`.`session_id`) AS `sessions`, 
                    COUNT(DISTINCT `sessions_events`.`visitor_id`) AS `visitors`,
                    DATE_FORMAT({$convert_tz_sql}, '{$datetime['query_date_format']}') AS `formatted_date`
                FROM 
                    `sessions_events`
                WHERE 
                    `sessions_events`.`website_id` = {$this->website->website_id} 
                    AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                GROUP BY
                    `formatted_date`
            ");
        }

        /* Generate the raw chart data and save logs for later usage */
        while($row = $result->fetch_object()) {
            $logs[] = $row;

            $formatted_date = $datetime['process']($row->formatted_date, true);

            /* Insert data for the chart */
            $logs_chart[$formatted_date] = [
                'pageviews' => $row->pageviews,
                'sessions'  => $row->sessions,
                'visitors'  => $row->visitors,
            ];

            /* Sum for basic totals */
            $basic_totals['pageviews'] += $row->pageviews;
            $basic_totals['sessions'] += $row->sessions;
        }

        $logs_chart = get_chart_data($logs_chart);

        /* Apply different query when filters are applied */
        if($filters) {
            $basic_totals['visitors'] = database()->query("
                SELECT 
                    COUNT(DISTINCT `visitors_sessions`.`visitor_id`) AS `total`
                FROM 
                    `visitors_sessions`
                LEFT JOIN
                    `sessions_events` ON `visitors_sessions`.`visitor_id` = `sessions_events`.`visitor_id`
                LEFT JOIN
                    `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
                WHERE 
                    `visitors_sessions`.`website_id` = {$this->website->website_id} 
                    AND (`visitors_sessions`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                    {$filters}
            ")->fetch_object()->total ?? 0;
        } else {
            $basic_totals['visitors'] = database()->query("
                SELECT 
                    COUNT(DISTINCT `visitors_sessions`.`visitor_id`) AS `total`
                FROM 
                    `visitors_sessions`
                WHERE 
                    `visitors_sessions`.`website_id` = {$this->website->website_id} 
                    AND (`visitors_sessions`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
            ")->fetch_object()->total ?? 0;
        }

        return [
            'datetime' => $datetime,
            'logs' => $logs,
            'basic_totals' => $basic_totals,
            'logs_chart' => $logs_chart
        ];
    }

    private function lightweight() {
        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date, Date::$default_timezone);

        /* Get basic overall data */
        $logs = [];
        $logs_chart = [];
        $basic_totals = [
            'pageviews' => 0,
            'visitors'  => 0
        ];

        $filters = AnalyticsFilters::get_filters_sql(['lightweight_events']);

        $convert_tz_sql = get_convert_tz_sql('`date`', $this->user->timezone);

        $result = database()->query("
            SELECT 
                COUNT(*) AS `pageviews`, 
                SUM(CASE WHEN `type` = 'landing_page' THEN 1 ELSE 0 END) AS `visitors`,
                DATE_FORMAT({$convert_tz_sql}, '{$datetime['query_date_format']}') AS `formatted_date`
            FROM 
                `lightweight_events`
            WHERE 
                `website_id` = {$this->website->website_id} 
                AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                {$filters}
            GROUP BY
                `formatted_date`
        ");

        /* Generate the raw chart data and save logs for later usage */
        while($row = $result->fetch_object()) {
            $logs[] = $row;

            $formatted_date = $datetime['process']($row->formatted_date, true);

            /* Insert data for the chart */
            $logs_chart[$formatted_date] = [
                'pageviews' => $row->pageviews,
                'visitors'  => $row->visitors,
                'labels_alt' => $formatted_date
            ];

            /* Sum for basic totals */
            $basic_totals['pageviews'] += $row->pageviews;
            $basic_totals['visitors'] += $row->visitors;
        }

        $logs_chart = get_chart_data($logs_chart);

        return [
            'datetime' => $datetime,
            'logs' => $logs,
            'basic_totals' => $basic_totals,
            'logs_chart' => $logs_chart
        ];
    }

    public function export_normal() {

        \Altum\Authentication::guard();

        if(!$this->website) {
            redirect('websites');
        }

        $type = isset($this->params[0]) && in_array($this->params[0], ['csv', 'json']) ? $this->params[0] : 'csv';

        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date, Date::$default_timezone);

        /* Filters */
        $filters = AnalyticsFilters::get_filters_sql(['websites_visitors', 'sessions_events']);

        /* Get the data from the database */
        $rows = [];

        $convert_tz_sql = get_convert_tz_sql('`sessions_events`.`date`', $this->user->timezone);

        $result = database()->query("
            SELECT 
                `websites_visitors`.`continent_code`,
                `websites_visitors`.`country_code`,
                `websites_visitors`.`os_name`,
                `websites_visitors`.`os_version`,
                `websites_visitors`.`browser_name`,
                `websites_visitors`.`browser_version`,
                `websites_visitors`.`browser_language`,
                `websites_visitors`.`browser_timezone`,
                `websites_visitors`.`screen_resolution`,
                `websites_visitors`.`device_type`,
                `sessions_events`.`type`,
                `sessions_events`.`path`,
                `sessions_events`.`title`,
                `sessions_events`.`referrer_host`,
                `sessions_events`.`referrer_path`,
                `sessions_events`.`utm_source`,
                `sessions_events`.`utm_medium`,
                `sessions_events`.`utm_campaign`,
                `sessions_events`.`viewport_width`,
                `sessions_events`.`viewport_height`,
                DATE_FORMAT({$convert_tz_sql}, '%Y-%m-%d') AS `formatted_date`
            FROM 
                `sessions_events`
            LEFT JOIN
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
            WHERE 
                `sessions_events`.`website_id` = {$this->website->website_id} 
                AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                {$filters}
        ");

        while($row = $result->fetch_object()) {
            $rows[] = $row;
        }

        switch($type) {
            case 'csv':
                header('Content-Disposition: attachment; filename="' . get_slug($this->website->name) . '.csv";');
                header('Content-Type: application/csv; charset=UTF-8');

                $data = csv_exporter($rows);
                break;

            case 'json':
                header('Content-Disposition: attachment; filename="' . get_slug($this->website->name) . '.json";');
                header('Content-Type: application/json; charset=UTF-8');

                $data = json_exporter($rows);
                break;
        }

        die($data);
    }

    public function export_lightweight() {

        \Altum\Authentication::guard();

        if(!$this->website) {
            redirect('websites');
        }

        $type = isset($this->params[0]) && in_array($this->params[0], ['csv', 'json']) ? $this->params[0] : 'csv';

        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date, Date::$default_timezone);

        /* Get the data from the database */
        $rows = [];

        $convert_tz_sql = get_convert_tz_sql('`date`', $this->user->timezone);

        $result = database()->query("
            SELECT 
                *
            FROM 
                `lightweight_events`
            WHERE 
                `website_id` = {$this->website->website_id} 
                AND ({$convert_tz_sql} BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
            ");

        while($row = $result->fetch_object()) {

            unset($row->event_id);
            unset($row->website_id);

            $rows[] = $row;
        }

        switch($type) {
            case 'csv':
                header('Content-Disposition: attachment; filename="' . get_slug($this->website->name) . '.csv";');
                header('Content-Type: application/csv; charset=UTF-8');

                $data = csv_exporter($rows);
                break;

            case 'json':
                header('Content-Disposition: attachment; filename="' . get_slug($this->website->name) . '.json";');
                header('Content-Type: application/json; charset=UTF-8');

                $data = json_exporter($rows);
                break;
        }

        die($data);
    }

    public function reset() {

        \Altum\Authentication::guard();

        if(empty($_POST)) {
            redirect('dashboard');
        }

        $website_id = (int) $_POST['website_id'];
        $datetime = \Altum\Date::get_start_end_dates_new($_POST['start_date'], $_POST['end_date']);

        /* Team */
        if($this->team) {
            die();
        }

        /* Make sure the resource is created by the logged in user */
        if(!array_key_exists($_POST['website_id'], $this->websites)) {
            redirect('dashboard');
        }

        //ALTUMCODE:DEMO if(DEMO) if($this->user->user_id == 1) Alerts::add_error('Please create an account on the demo to test out this function.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
            redirect('dashboard');
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            /* Clear statistics data */
            database()->query("DELETE FROM `websites_visitors` WHERE `website_id` = {$website_id} AND (`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')");
            database()->query("DELETE FROM `visitors_sessions` WHERE `website_id` = {$website_id} AND (`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')");
            database()->query("DELETE FROM `sessions_events` WHERE `website_id` = {$website_id} AND (`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')");
            database()->query("DELETE FROM `events_children` WHERE `website_id` = {$website_id} AND (`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')");
            database()->query("DELETE FROM `lightweight_events` WHERE `website_id` = {$website_id} AND (`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')");

            /* Set a nice success message */
            Alerts::add_success(l('global.success_message.update2'));

            redirect('dashboard');

        }

        redirect('dashboard');

    }

}
