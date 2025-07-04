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
use Altum\Title;

defined('ALTUMCODE') || die();

class PageviewsNormal extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        if(!$this->website) {
            redirect('pageviews');
        }

        if($this->website->tracking_type == 'lightweight') {
            redirect('pageviews-lightweight');
        }

        /* Establish the start and end date for the statistics */
        list($start_date, $end_date) = AnalyticsFilters::get_date();

        $datetime = \Altum\Date::get_start_end_dates_new($start_date, $end_date);

        /* Filters */
        $filters = AnalyticsFilters::get_filters_sql(['websites_visitors', 'sessions_events']);

        /* Prepare the paginator */
        $total_rows = database()->query("
            SELECT 
                COUNT(*) AS `total`
            FROM 
                `sessions_events`
            JOIN
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
            WHERE 
                `sessions_events`.`website_id` = {$this->website->website_id} 
                AND (`sessions_events`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                {$filters}
        ")->fetch_object()->total ?? 0;

        $paginator = (new \Altum\Paginator($total_rows, settings()->main->default_results_per_page, $_GET['page'] ?? 1, url('pageviews-normal?page=%d')));

        /* Get the websites list for the user */
        $pageviews = [];
        $pageviews_result = database()->query("
            SELECT
                `sessions_events`.*,
                `websites_visitors`.`visitor_uuid_binary`,
                `websites_visitors`.`ip`,
                `websites_visitors`.`custom_parameters`,
                `websites_visitors`.`continent_code`,
                `websites_visitors`.`country_code`,
                `websites_visitors`.`city_name`,
                `websites_visitors`.`os_name`,
                `websites_visitors`.`browser_name`,
                `websites_visitors`.`device_type`,
                `websites_visitors`.`screen_resolution`,
                `websites_visitors`.`browser_timezone`,
                `websites_visitors`.`browser_language`,
                `websites_visitors`.`theme`
            FROM
            	`sessions_events`
            JOIN
                `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
			WHERE
			     `sessions_events`.`website_id` = {$this->website->website_id}
                AND (`sessions_events`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                {$filters}
            ORDER BY
                `sessions_events`.`event_id` DESC
            {$paginator->get_sql_limit()}
        ");
        while($row = $pageviews_result->fetch_object()) {
            $pageviews[] = $row;
        }

        /* Set a custom title */
        Title::set(l('pageviews.title'));

        /* Export handler */
        process_export_csv($pageviews, 'include', ['event_id', 'event_uuid', 'session_id', 'visitor_id', 'visitor_uuid_binary', 'website_id', 'ip', 'type', 'path', 'title', 'referrer_host', 'referrer_path', 'utm_source', 'utm_medium', 'utm_campaign', 'viewport_width', 'viewport_height', 'continent_code', 'country_code', 'city_name', 'os_name', 'os_version', 'browser_name', 'browser_version', 'browser_language', 'browser_timezone', 'screen_resolution', 'device_type', 'theme', 'date'], sprintf(l('pageviews.title')));
        process_export_json($pageviews, 'include', ['event_id', 'event_uuid', 'session_id', 'visitor_id', 'visitor_uuid_binary', 'website_id', 'ip', 'type', 'path', 'title', 'referrer_host', 'referrer_path', 'utm_source', 'utm_medium', 'utm_campaign', 'viewport_width', 'viewport_height', 'custom_parameters', 'continent_code', 'country_code', 'city_name', 'os_name', 'os_version', 'browser_name', 'browser_version', 'browser_language', 'browser_timezone', 'screen_resolution', 'device_type', 'theme', 'date'], sprintf(l('pageviews.title')));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Prepare the view */
        $data = [
            'datetime' => $datetime,
            'total_rows' => $total_rows,
            'pagination' => $pagination,
            'pageviews' => $pageviews
        ];

        $view = new \Altum\View('pageviews-normal/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function bulk() {

        if(!$this->website) {
            redirect('pageviews');
        }

        if($this->team) {
            redirect('pageviews');
        }

        \Altum\Authentication::guard();

        /* Check for any errors */
        if(empty($_POST)) {
            redirect('pageviews');
        }

        if(empty($_POST['selected'])) {
            redirect('pageviews');
        }

        if(!isset($_POST['type'])) {
            redirect('pageviews');
        }

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            set_time_limit(0);

            switch($_POST['type']) {
                case 'delete':

                    foreach($_POST['selected'] as $event_id) {
                        db()->where('event_id', $event_id)->where('website_id', $this->website->website_id)->delete('sessions_events');
                    }

                    break;
            }

            /* Set a nice success message */
            Alerts::add_success(l('bulk_delete_modal.success_message'));

        }

        redirect('pageviews');
    }

    public function delete() {

        if(!$this->website) {
            redirect('pageviews');
        }

        if($this->team) {
            redirect('pageviews');
        }

        \Altum\Authentication::guard();

        if(empty($_POST)) {
            redirect('pageviews');
        }

        $event_id = (int) query_clean($_POST['event_id']);

        //ALTUMCODE:DEMO if(DEMO) if($this->user->user_id == 1) Alerts::add_error('Please create an account on the demo to test out this function.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            /* Database query */
            db()->where('event_id', $event_id)->where('website_id', $this->website->website_id)->delete('sessions_events');

            /* Set a nice success message */
            Alerts::add_success(l('global.success_message.delete2'));

            redirect('pageviews');
        }

        redirect('pageviews');
    }

}
