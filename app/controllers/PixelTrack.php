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

use Altum\Models\User;
use Altum\Response;

defined('ALTUMCODE') || die();

class PixelTrack extends Controller {
    public $website;
    public $website_user;

    public function index() {

        /* Get the Payload of the Post */
        if(!isset($_POST['data'])) {
            die(settings()->main->title . " (" . SITE_URL. "): No content posted.");
        }

        $post = json_decode($_POST['data']);

        if(!$post) {
            die(settings()->main->title . " (" . SITE_URL. "): No content posted.");
        }

        /* Clean the pixel key */
        $pixel_key = isset($this->params[0]) ? input_clean($this->params[0]) : null;
        $date = get_date();

        /* Allowed types of requests to this endpoint */
        $allowed_types = [
            /* Sessions events */
            'initiate_visitor',
            'landing_page',
            'pageview',

            /* Events children */
            'click',
            'scroll',
            'form',
            'resize',

            /* Sessions replays */
            'replays',

            /* Heatmaps */
            'heatmap_snapshot',

            /* Goal conversions */
            'goal_conversion'
        ];

        if(!isset($post->type) || isset($post->type) && !in_array($post->type, $allowed_types)) {
            die(settings()->main->title . " (" . SITE_URL. "): Invalid type.");
        }

        /* Find the website for the domain */
        $host = query_clean(parse_url($post->url, PHP_URL_HOST));

        /* Remove www. from the host */
        $prefix = 'www.';

        if(mb_substr($host, 0, mb_strlen($prefix)) == $prefix) {
            $host = mb_substr($host, mb_strlen($prefix));
        }

        /* Get the details of the campaign from the database */
        $website = $this->website = (new \Altum\Models\Website())->get_website_by_pixel_key($pixel_key);

        /* Make sure the campaign has access */
        if(!$website) {
            die(settings()->main->title . ' (' . SITE_URL . '): No website found for this pixel.');
        }

        if(
            !$website->is_enabled
            || ($website->host != $host && $website->host != 'www.' . $host)
        ) {
            die(settings()->main->title . " (" . SITE_URL. "): Website disabled.");
        }

        /* Check against bots */
        if($website->bot_exclusion_is_enabled) {
            $CrawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();

            if($CrawlerDetect->isCrawler()) {
                die(settings()->main->title . " (" . SITE_URL. "): Bot usage has been detected, pixel stopped from executing.");
            }
        }

        /* Check excluded IPs */
        $excluded_ips = $this->website->excluded_ips ? array_flip(explode(',', $this->website->excluded_ips)) : [];

        /* Do not track if it's an excluded ip */
        if(isset($excluded_ips[get_ip()])) {
            die(settings()->main->title . " (" . SITE_URL . "): Your IP is excluded from being tracked.");
        }

        /* Make sure to get the user data and confirm the user is ok */
        $user = $this->website_user = (new \Altum\Models\User())->get_user_by_user_id($website->user_id);

        if(!$user) {
            die(settings()->main->title . " (" . SITE_URL. "): Website owner not found.");
        }

        if($user->status != 1) {
            die(settings()->main->title . " (" . SITE_URL. "): Website owner is disabled.");
        }

        /* Check for a custom domain */
        if(isset(\Altum\Router::$data['domain']) && $website->domain_id != \Altum\Router::$data['domain']->domain_id) {
            die(settings()->main->title . " (" . SITE_URL. "): Domain id mismatch.");
        }

        /* Process the plan of the user */
        (new User())->process_user_plan_expiration_by_user($user);

        /* Check against available limits */
        if(
            ($this->website_user->plan_settings->sessions_events_limit != -1 && $this->website->current_month_sessions_events >= $this->website_user->plan_settings->sessions_events_limit) ||

            (
                $this->website_user->plan_settings->events_children_limit != -1 &&
                $this->website->current_month_events_children >= $this->website_user->plan_settings->events_children_limit &&
                in_array($post->type, ['click', 'scroll', 'form','resize']) &&
                !isset($post->heatmap_id)
            ) ||

            (
                $this->website_user->plan_settings->sessions_replays_limit != -1 &&
                $this->website->current_month_sessions_replays >= $this->website_user->plan_settings->sessions_replays_limit &&
                in_array($post->type, ['replays'])
            ) ||

            (
                $this->website_user->plan_settings->websites_heatmaps_limit == 0 &&
                in_array($post->type, ['click', 'scroll']) &&
                isset($post->heatmap_id)
            ) ||

            (
                $this->website_user->plan_settings->websites_goals_limit == 0 &&
                in_array($post->type, ['goal_conversion'])
            )
        ) {
            die(settings()->main->title . " (" . SITE_URL. "): Your plan limit has been reached.");
        }

        /* Lightweight */
        if($website->tracking_type == 'lightweight') {
            /* Processing depending on the type of request */
            switch($post->type) {
                case 'landing_page':
                case 'pageview':

                    /* Process referrer */
                    $referrer = parse_url($post->data->referrer);

                    /* Check if the referrer comes from the same location */
                    if(
                        isset($referrer['host'])
                        && $referrer['host'] == $this->website->host
                        && (
                            isset($referrer['path']) && mb_substr($referrer['path'], 0, mb_strlen($this->website->path)) == $this->website->path
                        )
                    ) {
                        $referrer = [
                            'host' => null,
                            'path' => null
                        ];
                    }

                    if(isset($referrer['host']) && !isset($referrer['path'])) {
                        $referrer['path'] = '/';
                    }

                    /* Detect the location */
                    try {
                        $maxmind = (get_maxmind_reader_city())->get(get_ip());
                    } catch(\Exception $exception) {
                        /* :) */
                    }

                    $location = [
                        'continent_code' => isset($maxmind) && isset($maxmind['continent']) ? $maxmind['continent']['code'] : null,
                        'city_name' => isset($maxmind) && isset($maxmind['city']) ? $maxmind['city']['names']['en'] : null,
                        'country_code' => isset($maxmind) && isset($maxmind['country']) ? $maxmind['country']['iso_code'] : null,
                    ];

                    /* Detect extra details about the user */
                    $whichbrowser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);

                    /* Detect extra details about the user */
                    $os = [
                        'name' => $whichbrowser->os->name ?? null
                    ];

                    $browser = [
                        'name' => $whichbrowser->browser->name ?? null,
                        'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null,
                        'timezone' => !empty($post->data->timezone) && in_array($post->data->timezone, \DateTimeZone::listIdentifiers()) ? query_clean($post->data->timezone) : null
                    ];

                    $device_type = get_this_device_type();
                    $screen_resolution = (int) $post->data->resolution->width . 'x' . (int) $post->data->resolution->height;
                    $theme = isset($post->data->theme) && in_array($post->data->theme, ['dark', 'light']) ? $post->data->theme : null;

                    $event = [
                        'path'              => $this->website->path ? preg_replace('/^' . preg_quote($this->website->path, '/') . '/', '', $post->data->path) : $post->data->path ?? '',
                        'referrer_host'     => $referrer['host'] ?? null,
                        'referrer_path'     => $referrer['path'] ?? null,
                        'utm_source'        => input_clean($post->data->utm->source ?? null),
                        'utm_medium'        => input_clean($post->data->utm->medium ?? null),
                        'utm_campaign'      => input_clean($post->data->utm->campaign ?? null),
                    ];

                    /* Insert the event */
                    $expiration_date = (new \DateTime($date))->modify('+' . ($this->website_user->plan_settings->sessions_events_retention ?? 365) . ' days')->format('Y-m-d');
                    db()->insert('lightweight_events', [
                        'website_id' => $this->website->website_id,
                        'type' => $post->type,
                        'path' => $event['path'],
                        'referrer_host' => $event['referrer_host'],
                        'referrer_path' => $event['referrer_path'],
                        'utm_source' => $event['utm_source'],
                        'utm_medium' => $event['utm_medium'],
                        'utm_campaign' => $event['utm_campaign'],
                        'continent_code' => $location['continent_code'],
                        'country_code' => $location['country_code'],
                        'city_name' => $location['city_name'],
                        'os_name' => $os['name'],
                        'browser_name' => $browser['name'],
                        'browser_language' => $browser['language'],
                        'browser_timezone' => $browser['timezone'],
                        'screen_resolution' => $screen_resolution,
                        'device_type' => $device_type,
                        'theme' => $theme,
                        'date' => $date,
                        'expiration_date' => $expiration_date,
                    ]);

                    break;

                /* Handling goal conversions */
                case 'goal_conversion':

                    /* Some data to use */
                    $goal_key = query_clean($post->goal_key);

                    /* Get the goal if any */
                    $website_goal = database()->query("SELECT `goal_id`, `type`, `path` FROM `websites_goals` WHERE `website_id` = {$this->website->website_id} AND `key` = '{$goal_key}'")->fetch_object() ?? null;

                    if(!$website_goal) {
                        die('4');
                    }

                    /* Check if the goal is valid */
                    if($website_goal->type == 'pageview') {
                        $referrer_explode = explode($host, $post->url);

                        if(!isset($referrer_explode[1]) || (isset($referrer_explode[1]) && $referrer_explode[1] != $this->website->path . $website_goal->path)) {
                            die('5');
                        }
                    }

                    /* Prepare to insert the goal conversion */
                    db()->insert('goals_conversions', [
                        'goal_id' => $website_goal->goal_id,
                        'website_id' => $this->website->website_id,
                        'date' => $date
                    ]);

                    break;
            }

            /* Update the website usage */
            db()->where('website_id', $this->website->website_id)->update('websites', ['current_month_sessions_events' => db()->inc()]);

        }

        if($website->tracking_type == 'normal') {

            /* Process the visitor uuid */
            if(isset($post->visitor_uuid)) {
                $post->visitor_uuid_binary = hex2bin($post->visitor_uuid);
            }

            if(isset($post->visitor_session_uuid)) {
                $post->session_uuid_binary = hex2bin($post->visitor_session_uuid);
            }

            if(isset($post->visitor_session_event_uuid)) {
                $post->event_uuid_binary = hex2bin($post->visitor_session_event_uuid);
            }

            /* Processing depending on the type of request */
            switch($post->type) {

                /* Initiate the visitor event */
                case 'initiate_visitor':

                    /* Check for custom parameters */
                    $dirty_custom_parameters = $post->data->custom_parameters ?? null;
                    $custom_parameters = [];

                    if($dirty_custom_parameters) {
                        $i = 1;
                        foreach((array) $dirty_custom_parameters as $key => $value) {
                            $key = input_clean($key, '64');
                            $value = input_clean($value, '512');

                            if($i++ >= 10) {
                                break;
                            } else {
                                $custom_parameters[$key] = $value;
                            }
                        }
                    }

                    $custom_parameters = json_encode($custom_parameters);

                    /* IP */
                    $ip = get_ip();
                    $original_ip = $ip;

                    /* Check if we can save the real IP or not */
                    $ip = settings()->analytics->ip_storage_is_enabled && $this->website->ip_storage_is_enabled ? $ip : preg_replace('/\d/', '*', $ip);

                    /* Detect the location */
                    try {
                        $maxmind = (get_maxmind_reader_city())->get($original_ip);
                    } catch(\Exception $exception) {
                        /* :) */
                    }

                    $location = [
                        'continent_code' => isset($maxmind) && isset($maxmind['continent']) ? $maxmind['continent']['code'] : null,
                        'country_code' => isset($maxmind) && isset($maxmind['country']) ? $maxmind['country']['iso_code'] : null,
                        'city_name' => isset($maxmind) && isset($maxmind['city']) ? $maxmind['city']['names']['en'] : null,
                    ];

                    /* Detect extra details about the user */
                    $whichbrowser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);

                    /* Detect extra details about the user */
                    $os = [
                        'name' => $whichbrowser->os->name ?? null,
                        'version' => $whichbrowser->os->version->value ?? null
                    ];

                    $browser = [
                        'name' => $whichbrowser->browser->name ?? null,
                        'version' => $whichbrowser->browser->version->value ?? null,
                        'language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null,
                        'timezone' => !empty($post->data->timezone) && in_array($post->data->timezone, \DateTimeZone::listIdentifiers()) ? query_clean($post->data->timezone) : null,
                    ];

                    $theme = isset($post->data->theme) && in_array($post->data->theme, ['dark', 'light']) ? $post->data->theme : null;
                    $device_type = get_this_device_type();
                    $screen_resolution = (int) $post->data->resolution->width . 'x' . (int)$post->data->resolution->height;

                    /* Insert or update the visitor */
                    $stmt = database()->prepare("
                        INSERT INTO `websites_visitors` (
                                 `website_id`, 
                                 `visitor_uuid_binary`, 
                                 `ip`, 
                                 `custom_parameters`, 
                                 `continent_code`, 
                                 `country_code`, 
                                 `city_name`, 
                                 `os_name`, 
                                 `os_version`, 
                                 `browser_name`, 
                                 `browser_version`, 
                                 `browser_language`, 
                                 `browser_timezone`,
                                 `screen_resolution`, 
                                 `device_type`,
                                 `theme`,
                                 `date`,
                                 `last_date`
                        ) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            `ip` = VALUES (ip),
                            `custom_parameters` = VALUES (custom_parameters),
                            `continent_code` = VALUES (continent_code),
                            `country_code` = VALUES (country_code),
                            `city_name` = VALUES (city_name),
                            `os_name` = VALUES (os_name),
                            `os_version` = VALUES (os_version),
                            `browser_name` = VALUES (browser_name),
                            `browser_version` = VALUES (browser_version),
                            `browser_language` = VALUES (browser_language),
                            `browser_timezone` = VALUES (browser_timezone),
                            `screen_resolution` = VALUES (screen_resolution),
                            `device_type` = VALUES (device_type),
                            `theme` = VALUES (theme),
                            `last_date` = VALUES (last_date)
                    ");
                    $stmt->bind_param(
                        'ssssssssssssssssss',
                        $this->website->website_id,
                        $post->visitor_uuid_binary,
                        $ip,
                        $custom_parameters,
                        $location['continent_code'],
                        $location['country_code'],
                        $location['city_name'],
                        $os['name'],
                        $os['version'],
                        $browser['name'],
                        $browser['version'],
                        $browser['language'],
                        $browser['timezone'],
                        $screen_resolution,
                        $device_type,
                        $theme,
                        $date,
                        $date
                    );
                    $stmt->execute();
                    $stmt->close();

                    break;

                /* Landing page event */
                case 'landing_page':

                    $post->data = json_encode($post->data);

                    /* Make sure to check if the visitor exists */
                    $visitor = \Altum\Cache::cache_function_result('visitor?visitor_uuid=' . md5($post->visitor_uuid), 'website_id=' . $this->website->website_id, function() use ($post) {
                        return db()->where('visitor_uuid_binary', $post->visitor_uuid_binary)->where('website_id', $this->website->website_id)->getOne('websites_visitors', ['visitor_id']);
                    });

                    if(!$visitor) {
                        Response::json('', 'error', ['refresh' => 'visitor']);
                    }

                    /* Insert the session */
                    $session_id = db()->insert('visitors_sessions', [
                        'session_uuid_binary' => $post->session_uuid_binary,
                        'visitor_id' => $visitor->visitor_id,
                        'website_id' => $this->website->website_id,
                        'date' => $date,
                    ]);

                    /* If session is false then it was a double request, end it */
                    if(!$session_id) {
                        die('6');
                    }

                    /* Insert the event */
                    $event_id = $this->insert_session_event(
                        $post->event_uuid_binary,
                        $session_id,
                        $visitor->visitor_id,
                        $this->website->website_id,
                        $post->type,
                        $post->data,
                        $date
                    );

                    /* Update the last action of the visitor */
                    db()->where('visitor_id', $visitor->visitor_id)->update('websites_visitors', ['last_date' => $date, 'total_sessions' => db()->inc(), 'last_event_id' => $event_id]);

                    break;

                /* Pageview event */
                case 'pageview':

                    $post->data = json_encode($post->data);

                    /* Make sure to check if the visitor exists */
                    $visitor = \Altum\Cache::cache_function_result('visitor?visitor_uuid=' . md5($post->visitor_uuid), 'website_id=' . $this->website->website_id, function() use ($post) {
                        return db()->where('visitor_uuid_binary', $post->visitor_uuid_binary)->where('website_id', $this->website->website_id)->getOne('websites_visitors', ['visitor_id']);
                    });

                    if(!$visitor) {
                        Response::json('', 'error', ['refresh' => 'visitor']);
                    }

                    /* Make sure to check if the session exists */
                    $session = db()->where('session_uuid_binary', $post->session_uuid_binary)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('visitors_sessions', ['session_id', 'total_events']);

                    if(!$session) {
                        Response::json('', 'error', ['refresh' => 'session']);
                    }

                    /* Insert the event */
                    $event_id = $this->insert_session_event(
                        $post->event_uuid_binary,
                        $session->session_id,
                        $visitor->visitor_id,
                        $this->website->website_id,
                        $post->type,
                        $post->data,
                        $date
                    );

                    /* Check if we should update the landing page event to set it as not bounced */
                    if($session->total_events == 1) {
                        db()->where('session_id', $session->session_id)->where('type', 'landing_page')->update('sessions_events', ['has_bounced' => 0]);
                    }

                    /* Update session */
                    db()->where('session_id', $session->session_id)->update('visitors_sessions', ['total_events' => db()->inc()]);

                    /* Update visitor */
                    db()->where('visitor_id', $visitor->visitor_id)->update('websites_visitors', ['last_date' => $date, 'last_event_id' => $event_id]);

                    break;

                /* Events Children */
                case 'click':
                case 'scroll':
                case 'form':
                case 'resize':

                    $post->data = json_encode($post->data);

                    /* Make sure to check if the visitor exists */
                    $visitor = \Altum\Cache::cache_function_result('visitor?visitor_uuid=' . md5($post->visitor_uuid), 'website_id=' . $this->website->website_id, function() use ($post) {
                        return db()->where('visitor_uuid_binary', $post->visitor_uuid_binary)->where('website_id', $this->website->website_id)->getOne('websites_visitors', ['visitor_id']);
                    });

                    if(!$visitor) {
                        Response::json('', 'error', ['refresh' => 'visitor']);
                    }

                    /* Make sure to check if the session exists */
                    $session = \Altum\Cache::cache_function_result('session?session_uuid=' . md5($post->session_uuid_binary), 'website_id=' . $this->website->website_id, function() use ($post, $visitor) {
                        return db()->where('session_uuid_binary', $post->session_uuid_binary)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('visitors_sessions', ['session_id']);
                    });

                    if(!$session) {
                        Response::json('', 'error', ['refresh' => 'session']);
                    }

                    /* Make sure to check if the main event exists */
                    $event = \Altum\Cache::cache_function_result('event?event_uuid=' . md5($post->event_uuid_binary), 'website_id=' . $this->website->website_id, function() use ($post, $visitor, $session) {
                        return db()->where('event_uuid_binary', $post->event_uuid_binary)->where('session_id', $session->session_id)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('sessions_events', ['event_id']);
                    });

                    if(!$event) {
                        die('7');
                    }

                    $expiration_date = (new \DateTime($date))->modify('+' . $this->website_user->plan_settings->events_children_retention . ' days')->format('Y-m-d');
                    $snapshot_id = null;

                    /* Check if the event is sent for a heatmap */
                    if(isset($post->heatmap_id) && $post->heatmap_id && $this->website_user->plan_settings->websites_heatmaps_limit != 0) {

                        /* Make sure the heatmap exists and matches the data */
                        $heatmap_id = (int) $post->heatmap_id;
                        $device_type = get_this_device_type();
                        $snapshot_id_type = 'snapshot_id_' . $device_type;

                        /* Get heatmaps if any */
                        $website_heatmap_query = "SELECT `heatmap_id`, `path`, `{$snapshot_id_type}` FROM `websites_heatmaps` WHERE `website_id` = {$this->website->website_id} AND `heatmap_id` = {$heatmap_id} AND `{$snapshot_id_type}` IS NOT NULL AND `is_enabled` = 1";
                        $website_heatmap = \Altum\Cache::cache_function_result('heatmap?hash=' . md5($website_heatmap_query), 'website_id=' . $this->website->website_id, function() use ($website_heatmap_query) {
                            return database()->query($website_heatmap_query)->fetch_object() ?? null;
                        });

                        if(!$website_heatmap) {
                            die('8');
                        }

                        /* Check the referrer against the set heatmap path */
                        $referrer_explode = explode($host, $post->url);

                        if(!isset($referrer_explode[1]) || (isset($referrer_explode[1]) && $referrer_explode[1] != $this->website->path . $website_heatmap->path)) {
                            die('n');
                        }

                        $snapshot_id = $website_heatmap->{$snapshot_id_type};

                        $expiration_date = null;
                    }

                    /* Insert the event */
                    $this->insert_session_event_child(
                        $event->event_id,
                        $session->session_id,
                        $visitor->visitor_id,
                        $snapshot_id,
                        $this->website->website_id,
                        $post->type,
                        $post->data,
                        (int)$post->count,
                        $date,
                        $expiration_date
                    );

                    break;

                /* Replay events */
                case 'replays':

                    /* Make sure to check if the visitor exists */
                    $visitor = \Altum\Cache::cache_function_result('visitor?visitor_uuid=' . md5($post->visitor_uuid), 'website_id=' . $this->website->website_id, function() use ($post) {
                        return db()->where('visitor_uuid_binary', $post->visitor_uuid_binary)->where('website_id', $this->website->website_id)->getOne('websites_visitors', ['visitor_id']);
                    });

                    if(!$visitor) {
                        die('9');
                    }

                    /* Make sure to check if the session exists */
                    $session = \Altum\Cache::cache_function_result('session?session_uuid=' . md5($post->session_uuid_binary), 'website_id=' . $this->website->website_id, function() use ($post, $visitor) {
                        return db()->where('session_uuid_binary', $post->session_uuid_binary)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('visitors_sessions', ['session_id']);
                    });

                    if(!$session) {
                        Response::json('', 'error', ['refresh' => 'session']);
                    }

                    /* Check if the replay exists and get the data */
                    $replay = \Altum\Cache::cache_function_result('replay?session_id=' . $session->session_id, 'website_id=' . $this->website->website_id, function() use ($session) {
                        return db()->where('session_id', $session->session_id)->getOne('sessions_replays');
                    });

                    /* Check if the time limit was crossed */
                    if($replay && (new \DateTime())->diff((new \DateTime($replay->datetime)))->i >= $this->website_user->plan_settings->sessions_replays_time_limit) {
                        die('10');
                    }

                    /* Expiration date for the replay */
                    $expiration_date = (new \DateTime($date))->modify('+' . $this->website_user->plan_settings->sessions_replays_retention . ' days')->format('Y-m-d');

                    /* New events to save */
                    $events = count($post->data);

                    /* Try to get already existing session replay data, if any */
                    $cache_instance = cache('store_adapter')->getItem('session_replay_' . $session->session_id);

                    $session_replay_data = $cache_instance->get();

                    if(is_null($session_replay_data)) {
                        $session_replay_data = [];
                    }

                    /* Gzencode the big data */
                    foreach($post->data as $key => $value) {
                        $post->data[$key]->data = gzencode(json_encode($post->data[$key]->data), 4);

                        $session_replay_data[] = $post->data[$key];
                    }

                    /* Prepare the expiration seconds data */
                    $expiration_seconds = (new \DateTime($date))->modify('+' . $this->website_user->plan_settings->sessions_replays_retention . ' days')->getTimestamp() - (new \DateTime())->getTimestamp();

                    $cache_instance->set($session_replay_data)->expiresAfter($expiration_seconds)->addTag('session_replay_user_' . $this->website->user_id)->addTag('session_replay_website_' . $this->website->website_id);

                    cache('store_adapter')->save($cache_instance);

                    /* Get the current size */
                    try {
                        $session_replay_data_key = $cache_instance->getEncodedKey('session_replay_' . $session->session_id);
                        $session_replay_data_path = UPLOADS_PATH . 'store/' . PRODUCT_KEY . '/Files/' . mb_substr($session_replay_data_key, 0, 2) . '/' . mb_substr($session_replay_data_key, 2, 2) . '/' . $session_replay_data_key . '.txt';
                        $session_replay_data_size = filesize($session_replay_data_path);
                    } catch (\Exception $exception) {
                        $session_replay_data_size = 0;
                    }

                    /* Database query */
                    $stmt = database()->prepare("
                        INSERT INTO
                            `sessions_replays` (`user_id`, `session_id`, `visitor_id`, `website_id`, `events`, `size`, `datetime`, `last_datetime`, `expiration_date`) 
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            `events` = `events` + VALUES (events),
                            `size` = VALUES (size),
                            `last_datetime` = VALUES (last_datetime),
                            `expiration_date` = VALUES (expiration_date)
                    ");
                    $stmt->bind_param(
                        'sssssssss',
                        $this->website->user_id,
                        $session->session_id,
                        $visitor->visitor_id,
                        $this->website->website_id,
                        $events,
                        $session_replay_data_size,
                        $date,
                        $date,
                        $expiration_date
                    );
                    $stmt->execute();
                    $affected_rows = $stmt->affected_rows;
                    $stmt->close();

                    /* If its a new session replay, insert the usage */
                    if($affected_rows == 1) {
                        db()->where('website_id', $this->website->website_id)->update('websites', ['current_month_sessions_replays' => db()->inc()]);
                    }

                    break;

                /* The initial snapshot of the heatmap */
                case 'heatmap_snapshot':

                    /* Some data to use */
                    $heatmap_id = (int) query_clean($post->heatmap_id);
                    $device_type = get_this_device_type();
                    $snapshot_id_type = 'snapshot_id_' . $device_type;

                    /* Get heatmaps if any */
                    $website_heatmap_query = "SELECT `heatmap_id`, `path`, `{$snapshot_id_type}` FROM `websites_heatmaps` WHERE `website_id` = {$this->website->website_id} AND `heatmap_id` = {$heatmap_id} AND `{$snapshot_id_type}` IS NULL AND `is_enabled` = 1";
                    $website_heatmap = \Altum\Cache::cache_function_result('heatmap?hash=' . md5($website_heatmap_query), 'website_id=' . $this->website->website_id, function() use ($website_heatmap_query) {
                        return database()->query($website_heatmap_query)->fetch_object() ?? null;
                    });

                    if(!$website_heatmap) {
                        die('11');
                    }

                    /* Check the referrer against the set heatmap path */
                    $referrer_explode = explode($host, $post->url);

                    if(!isset($referrer_explode[1]) || (isset($referrer_explode[1]) && $referrer_explode[1] != $this->website->path . $website_heatmap->path)) {
                        die('12');
                    }

                    /* Gzencode the data for storage in the database */
                    $data = gzencode(json_encode($post->data), 4);

                    /* Prepare to insert the snapshot */
                    $snapshot_id = db()->insert('heatmaps_snapshots', [
                        'heatmap_id' => $heatmap_id,
                        'website_id' => $this->website->website_id,
                        'type' => $device_type,
                        'data' => $data,
                        'date' => $date,
                    ]);

                    db()->where('heatmap_id', $website_heatmap->heatmap_id)->update('websites_heatmaps', [
                        $snapshot_id_type => $snapshot_id,
                        $device_type . '_size' => mb_strlen($data),
                    ]);

                    break;

                /* Handling goal conversions */
                case 'goal_conversion':

                    /* Make sure to check if the visitor exists */
                    $visitor = \Altum\Cache::cache_function_result('visitor?visitor_uuid=' . md5($post->visitor_uuid), 'website_id=' . $this->website->website_id, function() use ($post) {
                        return db()->where('visitor_uuid_binary', $post->visitor_uuid_binary)->where('website_id', $this->website->website_id)->getOne('websites_visitors', ['visitor_id', 'goals_conversions_ids']);
                    });

                    if(!$visitor) {
                        Response::json('', 'error', ['refresh' => 'visitor']);
                    }

                    $visitor->goals_conversions_ids = json_decode($visitor->goals_conversions_ids ?? '[]');

                    /* Make sure to check if the session exists */
                    $session = \Altum\Cache::cache_function_result('session?session_uuid=' . md5($post->session_uuid_binary), 'website_id=' . $this->website->website_id, function() use ($post, $visitor) {
                        return db()->where('session_uuid_binary', $post->session_uuid_binary)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('visitors_sessions', ['session_id']);
                    });

                    if(!$session) {
                        Response::json('', 'error', ['refresh' => 'session']);
                    }

                    /* Make sure to check if the main event exists */
                    $event = db()->where('event_uuid_binary', $post->event_uuid_binary)->where('session_id', $session->session_id)->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->getOne('sessions_events', ['event_id']);

                    if(!$event) {
                        die('13');
                    }

                    /* Some data to use */
                    $goal_key = query_clean($post->goal_key);

                    /* Get the goal if any */
                    $website_goal = database()->query("SELECT `goal_id`, `type`, `path` FROM `websites_goals` WHERE `website_id` = {$this->website->website_id} AND `key` = '{$goal_key}'")->fetch_object() ?? null;

                    if(!$website_goal) {
                        die('14');
                    }

                    /* Check if the goal is valid */
                    if($website_goal->type == 'pageview') {
                        /* Check the referrer against the set goal path */
                        $referrer_explode = explode($host, $post->url);

                        if(!isset($referrer_explode[1]) || (isset($referrer_explode[1]) && $referrer_explode[1] != $this->website->path . $website_goal->path)) {
                            die('15');
                        }
                    }

                    /* Make sure the goal for this user didn't already convert */
                    $conversion = db()->where('visitor_id', $visitor->visitor_id)->where('website_id', $this->website->website_id)->where('goal_id', $website_goal->goal_id)->getOne('goals_conversions');

                    if($conversion) {
                        die('16');
                    }

                    /* Prepare to insert the goal conversion */
                    $goal_conversion_id = db()->insert('goals_conversions', [
                        'event_id' => $event->event_id,
                        'session_id' => $session->session_id,
                        'visitor_id' => $visitor->visitor_id,
                        'website_id' => $this->website->website_id,
                        'goal_id' => $website_goal->goal_id,
                        'date' => $date,
                    ]);

                    /* Update visitor */
                    $goals_conversions_ids[] = $goal_conversion_id;
                    db()->where('visitor_id', $visitor->visitor_id)->update('websites_visitors', [
                        'goals_conversions_ids' => json_encode($goals_conversions_ids),
                    ]);

                    break;
            }
        }
    }

    private function insert_session_event($event_uuid, $session_id, $visitor_id, $website_id, $type, $data, $date) {

        /* Parse data */
        $data = json_decode($data);

        /* Process the page path */
        $data->path = $this->website->path ? preg_replace('/^' . preg_quote($this->website->path, '/') . '/', '', $data->path) : $data->path;

        /* Process referrer */
        $referrer = parse_url($data->referrer ?? '');

        /* Check if the referrer comes from the same location */
        if(
            isset($referrer['host'])
            && $referrer['host'] == $this->website->host
            && (
                isset($referrer['path']) && mb_substr($referrer['path'], 0, mb_strlen($this->website->path)) == $this->website->path
            )
        ) {
            $referrer = [
                'host' => null,
                'path' => null
            ];
        }

        if(isset($referrer['host']) && !isset($referrer['path'])) {
            $referrer['path'] = '/';
        }

        $session_data = [
            'path'              => input_clean($data->path ?? ''),
            'title'             => input_clean($data->title ?? ''),
            'referrer_host'     => $referrer['host'] ?? null,
            'referrer_path'     => $referrer['path'] ?? null,
            'utm_source'        => input_clean($data->utm->source ?? null),
            'utm_medium'        => input_clean($data->utm->medium ?? null),
            'utm_campaign'      => input_clean($data->utm->campaign ?? null),
            'viewport_width'    => $data->viewport->width ? (int) $data->viewport->width : 0,
            'viewport_height'   => $data->viewport->height ? (int) $data->viewport->height : 0,
            'has_bounced'       => $type == 'landing_page' ? 1 : null
        ];

        /* Insert the event */
        $expiration_date = (new \DateTime($date))->modify('+' . ($this->website_user->plan_settings->sessions_events_retention ?? 365) . ' days')->format('Y-m-d');
        $event_id = db()->insert('sessions_events', [
            'event_uuid_binary' => $event_uuid,
            'session_id' => $session_id,
            'visitor_id' => $visitor_id,
            'website_id' => $website_id,
            'type' => $type,
            'path' => $session_data['path'],
            'title' => $session_data['title'],
            'referrer_host' => $session_data['referrer_host'],
            'referrer_path' => $session_data['referrer_path'],
            'utm_source' => $session_data['utm_source'],
            'utm_medium' => $session_data['utm_medium'],
            'utm_campaign' => $session_data['utm_campaign'],
            'viewport_width' => $session_data['viewport_width'],
            'viewport_height' => $session_data['viewport_height'],
            'has_bounced' => $session_data['has_bounced'],
            'date' => $date,
            'expiration_date' => $expiration_date,
        ]);

        /* Update the website usage */
        db()->where('website_id', $website_id)->update('websites', ['current_month_sessions_events' => db()->inc()]);

        return $event_id;
    }

    private function insert_session_event_child($event_id, $session_id, $visitor_id, $snapshot_id, $website_id, $type, $data, $count, $date, $expiration_date) {

        /* Insert the event */
        db()->insert('events_children', [
            'event_id' => $event_id,
            'session_id' => $session_id,
            'visitor_id' => $visitor_id,
            'snapshot_id' => $snapshot_id,
            'website_id' => $website_id,
            'type' => $type,
            'data' => $data,
            'count' => $count,
            'date' => $date,
            'expiration_date' => $expiration_date
        ]);

        /* Update the website usage */
        db()->where('website_id', $website_id)->update('websites', ['current_month_events_children' => db()->inc()]);

    }

}
