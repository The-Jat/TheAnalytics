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

use Altum\Title;

defined('ALTUMCODE') || die();

class Team extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        if($this->team) {
            redirect('teams');
        }

        $team_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        /* Get the Visitor basic data and make sure it exists */
        if(!$team = db()->where('team_id', $team_id)->where('user_id', $this->user->user_id)->getOne('teams')) {
            redirect('teams');
        }
        $team->websites_ids = json_decode($team->websites_ids);

        /* Create Modal */
        $view = new \Altum\View('team/team_association_create_modal', (array) $this);
        \Altum\Event::add_content($view->run(['team' => $team]), 'modals');

        $view = new \Altum\View('team/team_association_delete_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Get the team members */
        $teams_associations_result = database()->query("SELECT `teams_associations`.*, `users`.`email`, `users`.`name` FROM `teams_associations` LEFT JOIN `users` ON `users`.`user_id` = `teams_associations`.`user_id` WHERE `teams_associations`.`team_id` = {$team->team_id}");

        /* Prepare the view */
        $data = [
            'team'                      => $team,
            'teams_associations_result' => $teams_associations_result,
        ];

        $view = new \Altum\View('team/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

        /* Set a custom title */
        Title::set(sprintf(l('team.title'), $team->name));
    }

}
