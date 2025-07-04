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

namespace Altum;

defined('ALTUMCODE') || die();

class AnalyticsFilters {

    public static function get_date() {

        /* Establish the start and end date for the statistics */
        if(isset($_GET['start_date'], $_GET['end_date'])) {
            $start_date = query_clean($_GET['start_date']);
            $end_date = query_clean($_GET['end_date']);

            /* Set it to the session */
            $_SESSION['analytics_start_date'] = $start_date;
            $_SESSION['analytics_end_date'] = $end_date;
        }

        /* Try to get start / end date from sessions if any */
        else if(isset($_SESSION['analytics_start_date'], $_SESSION['analytics_end_date'])) {
            $start_date = query_clean($_SESSION['analytics_start_date']);
            $end_date = query_clean($_SESSION['analytics_end_date']);
        }

        /* Default start / end dates */
        else {
            $start_date = (new \DateTime())->modify('-30 day')->format('Y-m-d');
            $end_date = (new \DateTime())->format('Y-m-d');
        }

        return [
            $start_date,
            $end_date
        ];
    }

    public static function get_filters($available_filters = null) {

        /* Determine which type of filters to retrieve */
        switch($available_filters) {
            case 'websites_visitors':
                $available_filters = self::$websites_visitors;

                break;

            case 'sessions_events':
                $available_filters = self::$sessions_events;

                break;

            case 'lightweight_events':
                $available_filters = self::$lightweight_events;

                break;

            default:
                $available_filters = array_merge(self::$websites_visitors, self::$sessions_events);
                break;
        }

        $filters = isset($_COOKIE['filters']) ? json_decode($_COOKIE['filters']) : null;
        $processed_filters = [];

        if($filters) {

            foreach($filters as $filter) {

                if(!in_array($filter->by, $available_filters)) {
                    continue;
                }

                if(!in_array($filter->rule, [
                    'is',
                    'is_not',
                    'contains',
                    'starts_with',
                    'ends_with'
                ])) {
                    continue;
                }

                $filter->value = query_clean($filter->value);

                $processed_filters[] = $filter;
            }

        }

        return $processed_filters;
    }

    public static function get_filters_sql($filters_keys = []) {

        $websites_visitors = [
            'ip',
            'continent_code',
            'country_code',
            'city_name',
            'screen_resolution',
            'browser_language',
            'browser_timezone',
            'os_name',
            'device_type',
            'browser_name',
            'theme'
        ];

        $sessions_events = [
            'path',
            'title',
            'referrer_host',
            'utm_source',
            'utm_medium',
            'utm_campaign'
        ];

        $lightweight_events = [
            'continent_code',
            'country_code',
            'city_name',
            'screen_resolution',
            'browser_language',
            'browser_timezone',
            'os_name',
            'device_type',
            'browser_name',
            'path',
            'referrer_host',
            'utm_source',
            'utm_medium',
            'utm_campaign'
        ];

        if(!count($filters_keys)) {
            return null;
        }

        $available_filters = [];
        foreach($filters_keys as $filter) {
            $available_filters = array_merge($available_filters, ${$filter});
        }

        $filters = isset($_COOKIE['filters']) ? json_decode($_COOKIE['filters']) : null;
        $wheres = [];

        if($filters) {
            foreach($filters as $filter) {

                if(!in_array($filter->by, $available_filters)) {
                    continue;
                }

                if(!in_array($filter->rule, [
                    'is',
                    'is_not',
                    'contains',
                    'starts_with',
                    'ends_with'
                ])) {
                    continue;
                }

                $filter->value = query_clean($filter->value);

                switch($filter->rule) {
                    case 'is':
                        $condition = "= '{$filter->value}'";
                        break;

                    case 'is_not':
                        $condition = "<> '{$filter->value}'";
                        break;

                    case 'contains':
                        $condition = "LIKE '%{$filter->value}%'";
                        break;

                    case 'starts_with':
                        $condition = "LIKE '{$filter->value}%'";
                        break;

                    case 'ends_with':
                        $condition = "LIKE '%{$filter->value}'";
                        break;
                }

                if(in_array('websites_visitors', $filters_keys) && in_array($filter->by, $websites_visitors)) {
                    $table = 'websites_visitors';
                }

                if(in_array('sessions_events', $filters_keys) && in_array($filter->by, $sessions_events)) {
                    $table = 'sessions_events';
                }

                if(in_array('lightweight_events', $filters_keys) && in_array($filter->by, $lightweight_events)) {
                    $table = 'lightweight_events';
                }

                $wheres[] = "`{$table}`.`{$filter->by}` $condition";

            }
        }

        return count($wheres) ? ' AND ' . implode(' AND ', $wheres) : null;

    }

}
