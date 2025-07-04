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


defined('ALTUMCODE') || die();

class Visitor extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        $visitor_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        /* Get the Visitor basic data and make sure it exists */
        if(!$visitor = db()->where('visitor_id', $visitor_id)->where('website_id', $this->website->website_id)->getOne('websites_visitors')) {
            redirect('visitors');
        }
        $visitor->goals_conversions_ids = json_decode($visitor->goals_conversions_ids ?? '[]');
        $datetime = \Altum\Date::get_start_end_dates_new();

        /* Get session data */
        $sessions_result = database()->query("
            SELECT
                `visitors_sessions`.*,
                `sessions_replays`.`session_id` AS `sessions_replays_session_id`,
                COUNT(DISTINCT  `sessions_events`.`event_id`) AS `pageviews`,
	       		MAX(`sessions_events`.`date`) AS `last_date`
            FROM
                `visitors_sessions`
            LEFT JOIN
            	`sessions_events` ON `sessions_events`.`session_id` = `visitors_sessions`.`session_id`
            LEFT JOIN
                `sessions_replays` ON `sessions_replays`.`session_id` = `visitors_sessions`.`session_id`
            WHERE
			     `visitors_sessions`.`visitor_id` = {$visitor->visitor_id}
			     AND (`visitors_sessions`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
			GROUP BY
				`visitors_sessions`.`session_id`
			ORDER BY
				`visitors_sessions`.`session_id` DESC
        ");

        /* Average time per session */
        $average_time_per_session = database()->query("
            SELECT 
                   AVG(`seconds`) AS `average` 
            FROM 
                 (
                     SELECT 
                            TIMESTAMPDIFF(SECOND, MIN(date), MAX(date)) AS `seconds` 
                     FROM 
                          `sessions_events`
                     WHERE 
                            `visitor_id` = {$visitor->visitor_id}
                     GROUP BY `session_id`
                 ) AS `seconds`
        ")->fetch_object()->average ?? 0;

        /* Get goal conversions */
        $goals = [];

        if(count($visitor->goals_conversions_ids)) {
            $goals_conversions_ids = implode(',', $visitor->goals_conversions_ids);
            $goals_result = database()->query("
                SELECT
                    `websites_goals`.`path`,
                    `websites_goals`.`name`,
                    `websites_goals`.`type`,
                    `goals_conversions`.`conversion_id`,
                    `goals_conversions`.`date`
                FROM
                    `goals_conversions`
                LEFT JOIN
                    `websites_goals` ON `goals_conversions`.`goal_id` = `websites_goals`.`goal_id`
                WHERE
                     `goals_conversions`.`conversion_id` IN ({$goals_conversions_ids})
                     AND `goals_conversions`.`visitor_id` = {$visitor->visitor_id}
                     AND (`goals_conversions`.`date` BETWEEN '{$datetime['query_start_date']}' AND '{$datetime['query_end_date']}')
                ORDER BY
                    `goals_conversions`.`conversion_id` DESC
            ");


            while($goal = $goals_result->fetch_object()) {
                $goals[] = $goal;
            }
        }

        /* Session Events Modal */
        $view = new \Altum\View('session/session_events_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Prepare the view */
        $data = [
            'datetime' => $datetime,
            'visitor' => $visitor,
            'average_time_per_session' => $average_time_per_session,
            'sessions_result' => $sessions_result,
            'goals' => $goals,
        ];

        $view = new \Altum\View('visitor/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

}
