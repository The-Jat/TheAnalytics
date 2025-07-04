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

class WebsitesAjax extends Controller {

    public function index() {

        \Altum\Authentication::guard();

        /* Make sure its not a request from a team member */
        if($this->team) {
            die();
        }

        //ALTUMCODE:DEMO if(DEMO) if($this->user->user_id == 1) Response::json('Please create an account on the demo to test out this function.', 'error');

        if(!empty($_POST) && (\Altum\Csrf::check() || \Altum\Csrf::check('global_token')) && isset($_POST['request_type'])) {

            switch($_POST['request_type']) {

                /* Create */
                case 'create': $this->create(); break;

                /* Update */
                case 'update': $this->update(); break;

                /* Delete */
                case 'delete': $this->delete(); break;

            }

        }

        die();
    }

    private function create() {
        $_POST['name'] = trim(query_clean($_POST['name']));
        $_POST['scheme'] = in_array($_POST['scheme'], ['https://', 'http://']) ? query_clean($_POST['scheme']) : 'https://';
        $_POST['host'] = str_replace(' ', '', mb_strtolower(input_clean($_POST['host'], 128)));
        $_POST['host'] = string_starts_with('http://', $_POST['host']) || string_starts_with('https://', $_POST['host']) ? parse_url($_POST['host'], PHP_URL_HOST) : $_POST['host'];
        $_POST['tracking_type'] = in_array($_POST['tracking_type'], ['lightweight', 'normal']) ? query_clean($_POST['tracking_type']) : 'lightweight';
        $_POST['events_children_is_enabled'] = (int) isset($_POST['events_children_is_enabled']);
        $_POST['sessions_replays_is_enabled'] = (int) isset($_POST['sessions_replays_is_enabled']);
        $_POST['email_reports_is_enabled'] = $this->user->plan_settings->email_reports_is_enabled ? (int) isset($_POST['email_reports_is_enabled']) : 0;
        $is_enabled = 1;

        /* Get available custom domains */
        $domain_id = null;
        if(isset($_POST['domain_id'])) {
            $domain = (new \Altum\Models\Domain())->get_domain_by_domain_id($_POST['domain_id']);

            if($domain && $domain->user_id == $this->user->user_id) {
                $domain_id = $domain->domain_id;
            }
        }

        /* Check for possible errors */
        if(empty($_POST['name']) || empty($_POST['host'])) {
            Response::json(l('global.error_message.empty_fields'), 'error');
        }

        /* Domain checking */
        $path = null;
        if(function_exists('idn_to_utf8')) {
            $path = parse_url($_POST['scheme'] . idn_to_utf8($_POST['host']), PHP_URL_PATH);
            $_POST['host'] = parse_url($_POST['scheme'] . idn_to_utf8($_POST['host']), PHP_URL_HOST);
        }

        if(function_exists('idn_to_ascii')) {
            $_POST['host'] = idn_to_ascii($_POST['host']);
        }

        /* Check for blacklisted domain */
        if(in_array($_POST['host'], settings()->analytics->blacklisted_domains)) {
            Response::json(l('websites.error_message.blacklisted_domain'), 'error');
        }

        /* Generate a unique pixel key for the website */
        $pixel_key = string_generate(16);
        while(db()->where('pixel_key', $pixel_key)->getOne('websites', ['pixel_key'])) {
            $pixel_key = string_generate(16);
        }

        /* Make sure that the user didn't exceed the limit */
        $total_rows = db()->where('user_id', $this->user->user_id)->getValue('websites', 'count(`website_id`)');
        if($this->user->plan_settings->websites_limit != -1 && $total_rows >= $this->user->plan_settings->websites_limit) {
            Response::json(l('global.info_message.plan_feature_limit'), 'error');
        }

        /* Database query */
        $website_id = db()->insert('websites', [
            'user_id' => $this->user->user_id,
            'domain_id' => $domain_id,
            'pixel_key' => $pixel_key,
            'name' => $_POST['name'],
            'scheme' => $_POST['scheme'],
            'host' => $_POST['host'],
            'path' => $path,
            'tracking_type' => $_POST['tracking_type'],
            'events_children_is_enabled' => $_POST['events_children_is_enabled'],
            'sessions_replays_is_enabled' => $_POST['sessions_replays_is_enabled'],
            'email_reports_is_enabled' => $_POST['email_reports_is_enabled'],
            'email_reports_last_date' => get_date(),
            'is_enabled' => $is_enabled,
            'datetime' => get_date(),
        ]);

        /* Clear cache */
        cache()->deleteItem('websites_' . $this->user->user_id);
        cache()->deleteItemsByTag('website_id=' . $website_id);

        Response::json(sprintf(l('global.success_message.create1'), '<strong>' . $_POST['name'] . '</strong>'), 'success', ['pixel_key' => $pixel_key]);
    }

    private function update() {
        $_POST['website_id'] = (int) $_POST['website_id'];

        $website = db()->where('website_id', $_POST['website_id'])->where('user_id', $this->user->user_id)->getOne('websites');
        if(!$website) {
            Response::json(l('global.error_message.basic'), 'error');
        }

        $_POST['name'] = trim(query_clean($_POST['name']));
        $_POST['scheme'] = in_array($_POST['scheme'], ['https://', 'http://']) ? query_clean($_POST['scheme']) : 'https://';
        $_POST['host'] = str_replace(' ', '', mb_strtolower(input_clean($_POST['host'], 128)));
        $_POST['host'] = string_starts_with('http://', $_POST['host']) || string_starts_with('https://', $_POST['host']) ? parse_url($_POST['host'], PHP_URL_HOST) : $_POST['host'];

        $_POST['is_enabled'] = (int) isset($_POST['is_enabled']);
        $_POST['events_children_is_enabled'] = (int) isset($_POST['events_children_is_enabled']);
        $_POST['sessions_replays_is_enabled'] = (int) isset($_POST['sessions_replays_is_enabled']);
        $_POST['email_reports_is_enabled'] = $this->user->plan_settings->email_reports_is_enabled ? (int) isset($_POST['email_reports_is_enabled']) : 0;
        $_POST['bot_exclusion_is_enabled'] = (int) isset($_POST['bot_exclusion_is_enabled']);
        $_POST['query_parameters_tracking_is_enabled'] = (int) isset($_POST['query_parameters_tracking_is_enabled']);
        $_POST['ip_storage_is_enabled'] = (int) isset($_POST['ip_storage_is_enabled']);

        $_POST['public_statistics_is_enabled'] = (int) isset($_POST['public_statistics_is_enabled']);
        $_POST['public_statistics_password'] = !empty($_POST['public_statistics_password']) ?
            ($_POST['public_statistics_password'] != $website->public_statistics_password ? password_hash($_POST['public_statistics_password'], PASSWORD_DEFAULT) : $website->public_statistics_password)
            : null;

        $_POST['excluded_ips'] = implode(',', array_map(function($value) {
            return query_clean(trim($value));
        }, explode(',', $_POST['excluded_ips'])));

        /* Get available custom domains */
        $domain_id = null;
        if(isset($_POST['domain_id'])) {
            $domain = (new \Altum\Models\Domain())->get_domain_by_domain_id($_POST['domain_id']);

            if($domain && $domain->user_id == $this->user->user_id) {
                $domain_id = $domain->domain_id;
            }
        }

        /* Check for possible errors */
        if(empty($_POST['name']) || empty($_POST['host'])) {
            Response::json(l('global.error_message.empty_fields'), 'error');
        }

        /* Domain checking */
        $path = null;
        if(function_exists('idn_to_utf8')) {
            $path = parse_url($_POST['scheme'] . idn_to_utf8($_POST['host']), PHP_URL_PATH);
            $_POST['host'] = parse_url($_POST['scheme'] . idn_to_utf8($_POST['host']), PHP_URL_HOST);
        }

        if(function_exists('idn_to_ascii')) {
            $_POST['host'] = idn_to_ascii($_POST['host']);
        }

        /* Check for blacklisted domain */
        if(in_array($_POST['host'], settings()->analytics->blacklisted_domains)) {
            Response::json(l('websites.error_message.blacklisted_domain'), 'error');
        }

        /* Database query */
        db()->where('website_id', $_POST['website_id'])->where('user_id', $this->user->user_id)->update('websites', [
            'domain_id' => $domain_id,
            'name' => $_POST['name'],
            'scheme' => $_POST['scheme'],
            'host' => $_POST['host'],
            'path' => $path,
            'excluded_ips' => $_POST['excluded_ips'],
            'events_children_is_enabled' => $_POST['events_children_is_enabled'],
            'sessions_replays_is_enabled' => $_POST['sessions_replays_is_enabled'],
            'email_reports_is_enabled' => $_POST['email_reports_is_enabled'],
            'bot_exclusion_is_enabled' => $_POST['bot_exclusion_is_enabled'],
            'query_parameters_tracking_is_enabled' => $_POST['query_parameters_tracking_is_enabled'],
            'ip_storage_is_enabled' => $_POST['ip_storage_is_enabled'],
            'public_statistics_is_enabled' => $_POST['public_statistics_is_enabled'],
            'public_statistics_password' => $_POST['public_statistics_password'],
            'is_enabled' => $_POST['is_enabled'],
            'last_datetime' => get_date(),
        ]);

        /* Clear cache */
        cache()->deleteItem('websites_' . $this->user->user_id);
        cache()->deleteItemsByTag('website_id=' . $_POST['website_id']);

        Response::json(sprintf(l('global.success_message.update1'), '<strong>' . $_POST['name'] . '</strong>'), 'success');
    }

    private function delete() {
        $_POST['website_id'] = (int) $_POST['website_id'];

        /* Make sure of the owner */
        if(!db()->where('website_id', $_POST['website_id'])->where('user_id', $this->user->user_id)->getOne('websites', ['website_id'])) {
            die();
        }

        /* Get and delete all session replays */
        $sessions_replays = db()->where('website_id', $_POST['website_id'])->get('sessions_replays');

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

        /* Database query */
        db()->where('website_id', $_POST['website_id'])->where('user_id', $this->user->user_id)->delete('websites');

        /* Clear cache */
        cache()->deleteItem('websites_' . $this->user->user_id);
        cache()->deleteItemsByTag('website_id=' . $_POST['website_id']);

        Response::json(l('global.success_message.delete2'), 'success');

    }

}
