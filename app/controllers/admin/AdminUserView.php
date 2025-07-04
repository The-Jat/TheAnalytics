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

use Altum\Models\Plan;

defined('ALTUMCODE') || die();

class AdminUserView extends Controller {

    public function index() {

        $user_id = (isset($this->params[0])) ? (int) $this->params[0] : null;

        /* Check if user exists */
        if(!$user = db()->where('user_id', $user_id)->getOne('users')) {
            redirect('admin/users');
        }

        /* Get widget stats */
        $websites = db()->where('user_id', $user_id)->getValue('websites', 'count(`website_id`)');
        $heatmaps = db()->where('user_id', $user_id)->getValue('websites_heatmaps', 'count(`heatmap_id`)');
        $replays = db()->where('user_id', $user_id)->getValue('sessions_replays', 'count(`replay_id`)');
        $teams = db()->where('user_id', $user_id)->getValue('teams', 'count(`team_id`)');
        $teams_associations = db()->where('user_id', $user_id)->getValue('teams_associations', 'count(`team_association_id`)');
        $email_reports = db()->where('user_id', $user_id)->getValue('email_reports', 'count(`id`)');
        $payments = in_array(settings()->license->type, ['Extended License', 'extended']) ? db()->where('user_id', $user_id)->getValue('payments', 'count(`id`)') : 0;
        $domains = db()->where('user_id', $user_id)->getValue('domains', 'count(`domain_id`)');

        /* Get the current plan details */
        $user->plan = (new Plan())->get_plan_by_id($user->plan_id);

        /* Check if its a custom plan */
        if($user->plan_id == 'custom') {
            $user->plan->settings = $user->plan_settings;
        }

        $user->billing = json_decode($user->billing ?? '');

        /* Main View */
        $data = [
            'user' => $user,
            'websites' => $websites,
            'heatmaps' => $heatmaps,
            'replays' => $replays,
            'teams' => $teams,
            'teams_associations' => $teams_associations,
            'email_reports' => $email_reports,
            'payments' => $payments,
            'domains' => $domains,
        ];

        $view = new \Altum\View('admin/user-view/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

}
