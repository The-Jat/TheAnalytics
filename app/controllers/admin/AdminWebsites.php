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

defined('ALTUMCODE') || die();

class AdminWebsites extends Controller {

    public function index() {

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['is_enabled', 'tracking_type', 'user_id', 'domain_id', 'website_id'], ['name', 'host'], ['website_id', 'email', 'datetime', 'last_datetime', 'name', 'host', 'current_month_sessions_events']));
        $filters->set_default_order_by($this->user->preferences->websites_default_order_by, $this->user->preferences->default_order_type ?? settings()->main->default_order_type);
        $filters->set_default_results_per_page($this->user->preferences->default_results_per_page ?? settings()->main->default_results_per_page);

        /* Prepare the paginator */
        $total_rows = database()->query("SELECT COUNT(*) AS `total` FROM `websites` WHERE 1 = 1 {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('admin/websites?' . $filters->get_get() . '&page=%d')));

        /* Get the users */
        $websites = [];
        $websites_result = database()->query("
            SELECT
                `websites`.*, `users`.`name` AS `user_name`, `users`.`email` AS `user_email`, `users`.`avatar` AS `user_avatar`
            FROM
                `websites`
            LEFT JOIN
                `users` ON `websites`.`user_id` = `users`.`user_id`
            WHERE
                1 = 1
                {$filters->get_sql_where('websites')}
                {$filters->get_sql_order_by('websites')}
            
            {$paginator->get_sql_limit()}
        ");
        while($row = $websites_result->fetch_object()) {
            $websites[] = $row;
        }

        /* Export handler */
        process_export_csv($websites, 'include', ['website_id', 'domain_id', 'user_id', 'pixel_key', 'name', 'scheme', 'host', 'path', 'tracking_type', 'excluded_ips', 'events_children_is_enabled', 'sessions_replays_is_enabled', 'email_reports_is_enabled', 'email_reports_last_date', 'is_enabled', 'last_datetime', 'datetime'], sprintf(l('websites.title')));
        process_export_json($websites, 'include', ['website_id', 'domain_id', 'user_id', 'pixel_key', 'name', 'scheme', 'host', 'path', 'tracking_type', 'excluded_ips', 'events_children_is_enabled', 'sessions_replays_is_enabled', 'email_reports_is_enabled', 'email_reports_last_date', 'is_enabled', 'last_datetime', 'datetime'], sprintf(l('websites.title')));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/admin_pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Main View */
        $data = [
            'websites' => $websites,
            'pagination' => $pagination,
            'filters' => $filters
        ];

        $view = new \Altum\View('admin/websites/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function bulk() {

        /* Check for any errors */
        if(empty($_POST)) {
            redirect('admin/websites');
        }

        if(empty($_POST['selected'])) {
            redirect('admin/websites');
        }

        if(!isset($_POST['type'])) {
            redirect('admin/websites');
        }

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            set_time_limit(0);

            switch($_POST['type']) {
                case 'delete':

                    foreach($_POST['selected'] as $website_id) {

                        if(!$website = db()->where('website_id', $website_id)->getOne('websites', ['website_id', 'user_id'])) {
                            continue;
                        }

                        /* Get and delete all session replays */
                        $sessions_replays = db()->where('website_id', $website_id)->get('sessions_replays');

                        foreach($sessions_replays as $session_replay) {
                            /* Clear cache */
                            cache('store_adapter')->deleteItem('session_replay_' . $session_replay->session_id);

                            /* Offload uploading */
                            if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url && $session_replay->is_offloaded) {
                                $file_name = base64_encode($session_replay->session_id . $session_replay->date) . '.txt';

                                try {
                                    $s3 = new \Aws\S3\S3Client(get_aws_s3_config());

                                    /* Upload image */
                                    $s3_result = $s3->deleteObject([
                                        'Bucket' => settings()->offload->storage_name,
                                        'Key' => UPLOADS_URL_PATH . 'store/' . $file_name,
                                    ]);
                                } catch (\Exception $exception) {
                                    dil($exception->getMessage());
                                }
                            }
                        }

                        /* Delete the website */
                        db()->where('website_id', $website_id)->delete('websites');

                        /* Clear cache */
                        cache()->deleteItem('websites_' . $website->user_id);
                        cache()->deleteItemsByTag('website_id=' . $website_id);

                    }
                    break;
            }

            /* Set a nice success message */
            Alerts::add_success(l('bulk_delete_modal.success_message'));

        }

        redirect('admin/websites');
    }

    public function delete() {

        $website_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check('global_token')) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!$website = db()->where('website_id', $website_id)->getOne('websites', ['website_id', 'user_id', 'name'])) {
            redirect('admin/websites');
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            /* Get and delete all session replays */
            $sessions_replays = db()->where('website_id', $website_id)->get('sessions_replays');

            foreach($sessions_replays as $session_replay) {
                /* Clear cache */
                cache('store_adapter')->deleteItem('session_replay_' . $session_replay->session_id);

                /* Offload uploading */
                if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url && $session_replay->is_offloaded) {
                    $file_name = base64_encode($session_replay->session_id . $session_replay->date) . '.txt';

                    try {
                        $s3 = new \Aws\S3\S3Client(get_aws_s3_config());

                        /* Upload image */
                        $s3_result = $s3->deleteObject([
                            'Bucket' => settings()->offload->storage_name,
                            'Key' => UPLOADS_URL_PATH . 'store/' . $file_name,
                        ]);
                    } catch (\Exception $exception) {
                        dil($exception->getMessage());
                    }
                }
            }

            /* Delete the website */
            db()->where('website_id', $website->website_id)->delete('websites');

            /* Clear cache */
            cache()->deleteItem('websites_' . $website->user_id);
            cache()->deleteItemsByTag('website_id=' . $website->website_id);

            /* Set a nice success message */
            Alerts::add_success(sprintf(l('global.success_message.delete1'), '<strong>' . $website->name . '</strong>'));

        }

        redirect('admin/websites');
    }

}
