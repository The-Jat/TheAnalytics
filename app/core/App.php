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

use Altum\Models\Plan;
use Altum\Models\User;
use Altum\Models\Website;


defined('ALTUMCODE') || die();

class App {

    public function __construct() {

        /* Connect to the database */
        //\Altum\Database::initialize();

        /* Initialize caching system */
        Cache::initialize();

        /* Initiate the plugin system */
        Plugin::initialize();

        /* Initiate the Language system */
        Language::initialize();

        /* Parse the URL parameters */
        \Altum\Router::parse_url();

        /* Parse the potential language url */
        \Altum\Router::parse_language();

        /* Handle the controller */
        \Altum\Router::parse_controller();

        /* Create a new instance of the controller */
        $controller = \Altum\Router::get_controller(\Altum\Router::$controller, \Altum\Router::$path);

        /* Process the method and get it */
        $method = \Altum\Router::parse_method($controller);

        /* Get the remaining params */
        $params = \Altum\Router::get_params();

        if(!\Altum\Router::$controller_settings['allow_indexing']) {
            header('X-Robots-Tag: noindex');
        }

        /* Iframe embedding */
        settings()->main->iframe_embedding = settings()->main->iframe_embedding ?? 'all';
        $iframe_embedding = match(settings()->main->iframe_embedding) {
            'all' => '*',
            'none' => "'none'",
            default => implode(' ', explode(',', settings()->main->iframe_embedding))
        };
        header("Content-Security-Policy: frame-ancestors $iframe_embedding;");

        /* HSTS */
        if(string_starts_with('https://', SITE_URL)) {
            header("Strict-Transport-Security: max-age=31536000; preload");
        }

        /* Check for Preflight requests for the tracking pixel */
        if(\Altum\Router::$controller == 'PixelTrack') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 7200');

            /* Check if preflight request */
            if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') die();
        }

        /* Allow iframe embedding */
        $allow_iframe_embedding = 0;
        if($allow_iframe_embedding) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 7200');

            /* Check if preflight request */
            if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') die();
        }

        if(in_array(\Altum\Router::$controller, ['Cron', 'PixelTrack', 'Replay', 'Replays', 'WebsitesAjax', 'AdminWebsites', 'AdminUsers', 'AccountDelete', 'ApiWebsites'])) {
            /* Cache store must be enabled in situations when dealing with  */
            Cache::store_initialize();
        }

        /* Initiate the Language system with the default language */
        Language::set_default_by_name(settings()->main->default_language);

        /* Set the default theme style */
        ThemeStyle::set_default(settings()->main->default_theme_style);

        /* Set the date timezone */
        date_default_timezone_set(Date::$default_timezone);
        Date::$timezone = date_default_timezone_get();

        /* Setting the datetime for backend usages ( insertions in database..etc ) */
        Date::$date = Date::get();

        /* Affiliate check */
        Affiliate::initiate();

        /* Full URL for ease of use */
        settings()->main->logo_light_full_url = \Altum\Uploads::get_full_url('logo_light') . settings()->main->logo_light;
        settings()->main->logo_dark_full_url = \Altum\Uploads::get_full_url('logo_dark') . settings()->main->logo_dark;
        settings()->main->favicon_full_url = \Altum\Uploads::get_full_url('favicon') . settings()->main->favicon;

        /* Check for a potential logged in account and do some extra checks */
        if(is_logged_in()) {

            $user = \Altum\Authentication::$user;

            if(!$user) {
                \Altum\Authentication::logout();
            }

            /* Determine if the current plan is expired or disabled */
            $user->plan_is_expired = false;

            /* Get current plan proper details */
            $user->plan = (new Plan())->get_plan_by_id($user->plan_id);

            if(!$user->plan || ($user->plan && ((new \DateTime()) > (new \DateTime($user->plan_expiration_date)) && $user->plan_id != 'free') || !$user->plan->status)) {
                $user->plan_is_expired = true;

                /* Switch the user to the default plan */
                db()->where('user_id', $user->user_id)->update('users', [
                    'plan_id' => 'free',
                    'plan_settings' => json_encode(settings()->plan_free->settings),
                    'payment_subscription_id' => ''
                ]);

                /* Clear the cache */
                cache()->deleteItemsByTag('user_id=' .  \Altum\Authentication::$user_id);
            }

            /* Update last activity */
            /* Do not update if user is impersonated by an admin */
            if(!$user->last_activity || (new \DateTime($user->last_activity))->modify('+15 minutes') < (new \DateTime()) && !isset($_SESSION['admin_user_id'])) {
                (new User())->update_last_activity(\Altum\Authentication::$user_id);
            }

            if(!isset($_COOKIE['set_language'])) {
                /* Update the language of the site for next page use if the current language (default) is different than the one the user has */
                if(Language::$name != $user->language) {
                    /* Make sure the language of the user still exists & is active */
                    if(array_key_exists($user->language, Language::$active_languages)) {
                        //Language::set_by_name($user->language);
                    } else {
                        db()->where('user_id', \Altum\Authentication::$user_id)->update('users', ['language' => Language::$default_name]);

                        /* Clear the cache */
                        cache()->deleteItemsByTag('user_id=' . \Altum\Authentication::$user_id);
                    }
                }
            }

            /* Update the language of the user if needed */
            if(isset($_COOKIE['set_language']) && array_key_exists($_COOKIE['set_language'], Language::$active_languages) && Language::$name != $user->language) {
                db()->where('user_id', \Altum\Authentication::$user_id)->update('users', ['language' => $_COOKIE['set_language']]);

                /* Clear the cache */
                cache()->deleteItemsByTag('user_id=' . \Altum\Authentication::$user_id);

                /* Remove cookie */
                setcookie('set_language', '', time()-30, COOKIE_PATH);

                /* Set the language */
                Language::set_by_name($_COOKIE['set_language']);
            }

            /* Update the currency of the user if needed */
            if(isset($_COOKIE['set_currency']) && array_key_exists($_COOKIE['set_currency'], (array) settings()->payment->currencies) && $_COOKIE['set_currency'] != $user->currency) {
                db()->where('user_id', \Altum\Authentication::$user_id)->update('users', ['currency' => $_COOKIE['set_currency']]);

                /* Clear the cache */
                cache()->deleteItemsByTag('user_id=' . \Altum\Authentication::$user_id);

                /* Remove cookie */
                setcookie('set_currency', '', time()-30, COOKIE_PATH);

                /* Set the currency */
                \Altum\Currency::$currency = $_COOKIE['set_currency'];
            }

            /* Set the timezone to be used for displaying */
            Date::$timezone = $user->timezone;

            /* Store all the details of the user in the Authentication static class as well */
            \Altum\Authentication::$user = $user;

            /* Check if team login */
            $team = null;

            if(isset($_COOKIE['selected_team_id'])) {
                $_COOKIE['selected_team_id'] = (int) $_COOKIE['selected_team_id'];

                $team = database()->query("SELECT `teams`.* FROM `teams` LEFT JOIN `teams_associations` ON `teams_associations`.`team_id` = `teams`.`team_id` WHERE `teams`.`team_id` = {$_COOKIE['selected_team_id']} AND `teams_associations`.`user_id` = {$user->user_id}")->fetch_object() ?? null;

                if($team) {
                    $team->websites_ids = json_decode($team->websites_ids);
                }
            }

            /* Extra if needed */
            if($team) {
                $websites = (new Website())->get_websites_by_websites_ids($team->websites_ids);
            } else {
                $websites = (new Website())->get_websites_by_user_id(\Altum\Authentication::$user->user_id);
            }

            /* Detect which is the default shown website */
            $website = !empty($_COOKIE['selected_website_id']) && array_key_exists($_COOKIE['selected_website_id'], $websites) ? $websites[$_COOKIE['selected_website_id']] : reset($websites);

            /* Add the data to the main controller */
            $controller->add_params([
                'websites' => $websites,
                'website' => $website,
                'team' => $team
            ]);

            /* Make sure to redirect the person to the payment page and only let the person access the following pages */
            if(
                $user->plan_is_expired
                && !in_array(\Altum\Router::$controller_key, ['index', 'blog', 'affiliate', 'contact', 'page', 'pages', 'plan', 'pay', 'pay-billing', 'pay-thank-you', 'account', 'account-plan', 'account-payments', 'invoice', 'account-logs', 'account-preferences',  'account-delete', 'referrals', 'account-api', 'account-redeem-code', 'logout', 'register', 'teams-system', 'teams-member', 'teams-members', 'teams', 'team', 'teams-ajax', 'teams-associations-ajax', 'register'])
                && \Altum\Router::$path != 'admin'
                && (\Altum\Router::$controller_settings['wrapper'] == 'app_wrapper' && !$team)
            )
            {
                redirect('plan/new');
            }

            /* White label */
            if(settings()->main->white_labeling_is_enabled && $user->plan_settings->white_labeling_is_enabled && \Altum\Router::$controller_key != 'invoice' && \Altum\Router::$path != 'admin') {
                if($user->preferences->white_label_title) settings()->main->title = $user->preferences->white_label_title;

                if($user->preferences->white_label_logo_light) {
                    settings()->main->logo_light = $user->preferences->white_label_logo_light;
                    settings()->main->logo_light_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->logo_light;
                }

                if($user->preferences->white_label_logo_dark) {
                    settings()->main->logo_dark = $user->preferences->white_label_logo_dark;
                    settings()->main->logo_dark_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->logo_dark;
                }

                if($user->preferences->white_label_favicon) {
                    settings()->main->favicon = $user->preferences->white_label_favicon;
                    settings()->main->favicon_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->favicon;
                }
            }

            /* Custom plan limit notice */
            if($website && $user->plan_settings->sessions_events_limit != -1 && $website->current_month_sessions_events > $user->plan_settings->sessions_events_limit && !isset($_COOKIE['plan_sessions_events_limit_notice_' . $website->website_id])) {
                Alerts::add_warning('<strong>' . l('global.notifications.user_sessions_events_limit.title') . '</strong><br />' . sprintf(l('global.notifications.user_sessions_events_limit.description'), '<a href="' . url('plan') . '" class="font-weight-bold">', '</a>'));
                setcookie('plan_sessions_events_limit_notice_' . $website->website_id, 1, time()+60*60*24*1, COOKIE_PATH);
            }

            /* Custom plan limit notice */
            if($website && $user->plan_settings->events_children_limit != -1 && $website->current_month_events_children > $user->plan_settings->events_children_limit && !isset($_COOKIE['plan_events_children_limit_notice_' . $website->website_id])) {
                Alerts::add_warning('<strong>' . l('global.notifications.user_events_children_limit.title') . '</strong><br />' . sprintf(l('global.notifications.user_events_children_limit.description'), '<a href="' . url('plan') . '" class="font-weight-bold">', '</a>'));
                setcookie('plan_events_children_limit_notice_' . $website->website_id, 1, time()+60*60*24*1, COOKIE_PATH);
            }

            /* Custom plan limit notice */
            if($website && $user->plan_settings->sessions_replays_limit != -1 && $website->current_month_sessions_replays > $user->plan_settings->sessions_replays_limit && !isset($_COOKIE['plan_sessions_replays_limit_notice_' . $website->website_id])) {
                Alerts::add_warning('<strong>' . l('global.notifications.user_sessions_replays_limit.title') . '</strong><br />' . sprintf(l('global.notifications.user_sessions_replays_limit.description'), '<a href="' . url('plan') . '" class="font-weight-bold">', '</a>'));
                setcookie('plan_sessions_replays_limit_notice_' . $website->website_id, 1, time()+60*60*24*1, COOKIE_PATH);
            }
        }

        /* Maintenance mode */
        if(settings()->main->maintenance_is_enabled && (!is_logged_in() || $user->type != 1) && !in_array(\Altum\Router::$controller_key, ['maintenance', 'login', 'lost-password', 'reset-password'])) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: 3600');
            header('Location: ' . url('maintenance'));
            exit();
        }

        /* Initiate the Title system */
        Title::initialize(settings()->main->title);
        Meta::initialize();

        /* Set a CSRF Token */
        Csrf::set('token');
        Csrf::set('global_token');

        /* If the language code is the default one, redirect to index */
        if(\Altum\Router::$language_code == Language::$default_code) {
            redirect(\Altum\Router::$original_request . (\Altum\Router::$original_request_query ? '?' . \Altum\Router::$original_request_query : null));
        }

        /* Redirect based on browser language if needed */
        $browser_language_code = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
        if(settings()->main->auto_language_detection_is_enabled && \Altum\Router::$controller_settings['no_browser_language_detection'] == false && !\Altum\Router::$language_code && !is_logged_in() && $browser_language_code && Language::$default_code != $browser_language_code && array_search($browser_language_code, Language::$active_languages)) {
            if(!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'])['host'] != parse_url(SITE_url, PHP_URL_HOST))) {
                header('Location: ' . SITE_URL . $browser_language_code . '/' . \Altum\Router::$original_request . (\Altum\Router::$original_request_query ? '?' . \Altum\Router::$original_request_query : null));
            }
        }

        /* Force HTTPS is needed */
        if(settings()->main->force_https_is_enabled && ($_SERVER['HTTPS'] ?? '') != 'on' && php_sapi_name() != 'cli' && string_starts_with('https://', SITE_URL)) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301); die();
        }

        /* Add main vars inside of the controller */
        $controller->add_params([
            /* Extra params available from the URL */
            'params' => $params,

            /* Potential logged in user */
            'user' => \Altum\Authentication::$user
        ]);

        /* Check for authentication checks */
        if(!is_null(\Altum\Router::$controller_settings['authentication'])) {
            \Altum\Authentication::guard(\Altum\Router::$controller_settings['authentication']);
        }

        /* Call the controller method */
        call_user_func_array([ $controller, $method ], []);

        /* Render and output everything */
        $controller->run();

        /* Close database */
        Database::close();
    }

}
