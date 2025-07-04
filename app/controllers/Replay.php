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

defined('ALTUMCODE') || die();

class Replay extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        if(!$this->website || !settings()->analytics->sessions_replays_is_enabled || ($this->website && $this->website->tracking_type == 'lightweight')) {
            redirect('websites');
        }

        $session_id = (isset($this->params[0])) ? (int) query_clean($this->params[0]) : 0;

        /* Get the Visitor basic data and make sure it exists */
        $visitor = database()->query("
            SELECT
                `visitors_sessions`.`session_id`,
                `websites_visitors`.`visitor_uuid_binary`,
                `websites_visitors`.`custom_parameters`,
                `websites_visitors`.`country_code`,
                `websites_visitors`.`city_name`,
                `websites_visitors`.`visitor_id`,
                `websites_visitors`.`date`
            FROM
                `visitors_sessions`
            LEFT JOIN   
                `websites_visitors` ON `visitors_sessions`.`visitor_id` = `websites_visitors`.`visitor_id`
            WHERE
                `visitors_sessions`.`session_id` = {$session_id}
                AND `visitors_sessions`.`website_id` = {$this->website->website_id}
        ")->fetch_object() ?? null;

        if(!$visitor) redirect('replays');

        /* Get the replay */
        if(!$replay = db()->where('session_id', $visitor->session_id)->getOne('sessions_replays')) {
            redirect('replays');
        }

        /* Events Modal */
        $view = new \Altum\View('replay/replay_events_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Prepare the view */
        $data = [
            'visitor'   => $visitor,
            'replay'    => $replay,
        ];

        $view = new \Altum\View('replay/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function read() {
        \Altum\Authentication::guard();

        $session_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        /* Get the replay */
        if(!$replay = db()->where('session_id', $session_id)->where('website_id', $this->website->website_id)->getOne('sessions_replays')) {
            die();
        }

        /* Offload uploading */
        if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url && $replay->is_offloaded) {

            try {
                $s3 = new \Aws\S3\S3Client(get_aws_s3_config());

                $file_name = base64_encode($replay->session_id . $replay->date) . '.txt';

                /* Get data */
                $result = $s3->getObject([
                    'Bucket' => settings()->offload->storage_name,
                    'Key' => UPLOADS_URL_PATH . 'store/' . $file_name,
                ]);

                $file_data = unserialize($result['Body']);
            } catch (\Exception $exception) {
                dil($exception->getMessage());
                die();
            }
        } else {
            /* Get from file store */
            $file_data = cache('store_adapter')->getItem('session_replay_' . $session_id)->get();
        }

        $rows = [];

        foreach($file_data as $row) {
            $row = [
                'type' => (int) $row->type,
                'data' => json_decode(gzdecode($row->data)),
                'timestamp' => (int) $row->timestamp,
            ];

            $rows[] = $row;
        }

        /* Prepare the events modal html */
        $events = array_filter($rows, function($item) {
            return $item['type'] == 4;
        });

        $replay_events_html = (new \Altum\View('replay/replay_events', (array) $this))->run(['events' => $events]);

        /* Output the proper replay data */
        Response::simple_json([
            'rows' => $rows,
            'replay_events_html' => $replay_events_html
        ]);
    }

}
