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

class Heatmap extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        if(!$this->website || !settings()->analytics->websites_heatmaps_is_enabled) {
            redirect('websites');
        }

        $heatmap_id = (isset($this->params[0])) ? (int) query_clean($this->params[0]) : 0;
        $snapshot_type = (isset($this->params[1])) && in_array($this->params[1], ['desktop', 'tablet', 'mobile']) ? query_clean($this->params[1]) : 'desktop';
        $heatmap_data_type = (isset($this->params[2])) && in_array($this->params[2], ['click', 'scroll']) ? query_clean($this->params[2]) : 'click';

        /* Get the Visitor basic data and make sure it exists */
        $heatmap = database()->query("SELECT * FROM `websites_heatmaps` WHERE `heatmap_id` = {$heatmap_id} AND `website_id` = {$this->website->website_id}")->fetch_object() ?? null;

        if(!$heatmap) redirect('heatmaps');

        /* Get snapshot data */
        $snapshot = database()->query("SELECT `snapshot_id`, `type` FROM `heatmaps_snapshots` WHERE `heatmap_id` = {$heatmap->heatmap_id} AND `type` = '{$snapshot_type}'")->fetch_object() ?? null;

        /* Update Modal */
        $view = new \Altum\View('heatmap/heatmap_update_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Update Modal */
        $view = new \Altum\View('heatmap/heatmap_retake_snapshots_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Prepare the view */
        $data = [
            'heatmap'   => $heatmap,
            'snapshot'  => $snapshot,
            'snapshot_type' => $snapshot_type,
        ];

        $view = new \Altum\View('heatmap/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function read() {

        \Altum\Authentication::guard();

        $heatmap_id = (isset($this->params[0])) ? (int) query_clean($this->params[0]) : 0;
        $snapshot_type = (isset($this->params[1])) && in_array($this->params[1], ['desktop', 'tablet', 'mobile']) ? query_clean($this->params[1]) : 'desktop';
        $heatmap_data_type = (isset($this->params[2])) && in_array($this->params[2], ['click', 'scroll']) ? query_clean($this->params[2]) : 'click';

        /* Get snapshot data */
        $snapshot = database()->query("SELECT * FROM `heatmaps_snapshots` WHERE `heatmap_id` = {$heatmap_id} AND `website_id` = {$this->website->website_id} AND `type` = '{$snapshot_type}'")->fetch_object() ?? null;

        if($snapshot) {
            /* Decode the snapshot */
            $snapshot->data = json_decode(gzdecode($snapshot->data));

            /* Get all the data needed for the heatmap */
            $heatmap_data = [];

            $result = database()->query("SELECT `data`, `count`, `type` FROM `events_children` WHERE `snapshot_id` = {$snapshot->snapshot_id} AND `type` = '{$heatmap_data_type}' AND `website_id` = {$this->website->website_id}");

            while($row = $result->fetch_object()) {
                $row->data = json_decode($row->data);

                /* Initial processing to prepare for the heatmap */
                for($i = 0; $i < (int) $row->count; $i++) {
                    switch ($row->type) {
                        case 'click':

                            $event = [
                                (int) $row->data->mouse->x,
                                (int) $row->data->mouse->y
                            ];

                            break;
                    }

                    $heatmap_data[] = $event;
                }

            }

            /* Clear the cache */
            cache()->deleteItemsByTag('website_id=' . $this->website->website_id);

            Response::simple_json([
                'snapshot_data' => $snapshot->data,
                'heatmap_data' => $heatmap_data,
                'heatmap_data_count' => nr(count($heatmap_data))
            ]);
        }

    }

}
