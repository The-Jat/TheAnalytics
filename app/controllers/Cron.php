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

use Altum\Logger;
use Altum\Models\User;

defined('ALTUMCODE') || die();

class Cron extends Controller {

    private function initiate() {
        /* Initiation */
        set_time_limit(0);

        /* Make sure the key is correct */
        if(!isset($_GET['key']) || (isset($_GET['key']) && $_GET['key'] != settings()->cron->key)) {
            die();
        }

        /* Send webhook notification if needed */
        if(settings()->webhooks->cron_start) {
            $backtrace = debug_backtrace();
            fire_and_forget('post', settings()->webhooks->cron_start, [
                'type' => $backtrace[1]['function'] ?? null,
                'datetime' => get_date(),
            ]);
        }
    }

    private function close() {
        /* Send webhook notification if needed */
        if(settings()->webhooks->cron_end) {
            $backtrace = debug_backtrace();
            fire_and_forget('post', settings()->webhooks->cron_end, [
                'type' => $backtrace[1]['function'] ?? null,
                'datetime' => get_date(),
            ]);
        }
    }

    private function update_cron_execution_datetimes($key) {
        $date = get_date();

        /* Database query */
        database()->query("UPDATE `settings` SET `value` = JSON_SET(`value`, '$.{$key}', '{$date}') WHERE `key` = 'cron'");
    }

    public function index() {

        $this->initiate();

        $this->users_plan_expiry_checker();

        $this->users_deletion_reminder();

        $this->auto_delete_inactive_users();

        $this->auto_delete_unconfirmed_users();

        $this->websites_replays_cleanup();

        $this->websites_replays_offload();

        $this->sessions_events_cleanup();

        $this->events_children_cleanup();

        $this->users_plan_expiry_reminder();

        $this->websites_sessions_events_notice();

        $this->websites_events_children_notice();

        $this->websites_sessions_replays_notice();

        $this->update_cron_execution_datetimes('cron_datetime');

        /* Make sure the reset date month is different than the current one to avoid double resetting */
        $reset_date = settings()->cron->reset_date ? (new \DateTime(settings()->cron->reset_date))->format('m') : null;
        $current_date = (new \DateTime())->format('m');

        if($reset_date != $current_date) {
            $this->logs_cleanup();

            $this->users_logs_cleanup();

            $this->internal_notifications_cleanup();

            $this->websites_events_reset();

            $this->update_cron_execution_datetimes('reset_date');

            /* Clear the cache */
            cache()->deleteItem('settings');
        }

        $this->close();
    }

    private function users_plan_expiry_checker() {
        if(!settings()->payment->user_plan_expiry_checker_is_enabled) {
            return;
        }

        $date = get_date();

        $result = database()->query("
            SELECT 
                `user_id`,
                `plan_id`,
                `name`,
                `email`,
                `language`,
                `anti_phishing_code`
            FROM 
                `users`
            WHERE 
                `plan_id` <> 'free'
				AND `plan_expiration_date` < '{$date}' 
            LIMIT 25
        ");

        $plans = [];
        if($result->num_rows) {
            $plans = (new \Altum\Models\Plan())->get_plans();
        }

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Switch the user to the default plan */
            db()->where('user_id', $user->user_id)->update('users', [
                'plan_id' => 'free',
                'plan_settings' => json_encode(settings()->plan_free->settings),
                'payment_subscription_id' => ''
            ]);

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.user_plan_expired.subject', $user->language),
                [
                    '{{USER_PLAN_RENEW_LINK}}' => url('pay/' . $user->plan_id),
                    '{{NAME}}' => $user->name,
                    '{{PLAN_NAME}}' => $plans[$user->plan_id]->name,
                ],
                l('global.emails.user_plan_expired.body', $user->language)
            );

            send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

            /* Clear the cache */
            cache()->deleteItemsByTag('user_id=' .  \Altum\Authentication::$user_id);

            if(DEBUG) {
                echo sprintf('users_plan_expiry_checker() -> Plan expired for user_id %s - reverting account to free plan', $user->user_id);
            }
        }
    }

    private function users_deletion_reminder() {
        if(!settings()->users->auto_delete_inactive_users) {
            return;
        }

        /* Determine when to send the email reminder */
        $days_until_deletion = settings()->users->user_deletion_reminder;
        $days = settings()->users->auto_delete_inactive_users - $days_until_deletion;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT `user_id`, `name`, `email`, `language`, `anti_phishing_code` 
            FROM `users` 
            WHERE 
                `plan_id` = 'free' 
                AND `last_activity` < '{$past_date}' 
                AND `user_deletion_reminder` = 0 
                AND `type` = 0 
            LIMIT 25
        ");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Prepare the email */
            $email_template = get_email_template(
                [
                    '{{DAYS_UNTIL_DELETION}}' => $days_until_deletion,
                ],
                l('global.emails.user_deletion_reminder.subject', $user->language),
                [
                    '{{DAYS_UNTIL_DELETION}}' => $days_until_deletion,
                    '{{LOGIN_LINK}}' => url('login'),
                    '{{NAME}}' => $user->name,
                ],
                l('global.emails.user_deletion_reminder.body', $user->language)
            );

            if(settings()->users->user_deletion_reminder) {
                send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);
            }

            /* Update user */
            db()->where('user_id', $user->user_id)->update('users', ['user_deletion_reminder' => 1]);

            if(DEBUG) {
                if(settings()->users->user_deletion_reminder) echo sprintf('users_deletion_reminder() -> User deletion reminder email sent for user_id %s', $user->user_id);
            }
        }

    }

    private function auto_delete_inactive_users() {
        if(!settings()->users->auto_delete_inactive_users) {
            return;
        }

        /* Determine what users to delete */
        $days = settings()->users->auto_delete_inactive_users;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT `user_id`, `name`, `email`, `language`, `anti_phishing_code` FROM `users` WHERE `plan_id` = 'free' AND `last_activity` < '{$past_date}' AND `user_deletion_reminder` = 1 AND `type` = 0 LIMIT 25
        ");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.auto_delete_inactive_users.subject', $user->language),
                [
                    '{{INACTIVITY_DAYS}}' => settings()->users->auto_delete_inactive_users,
                    '{{REGISTER_LINK}}' => url('register'),
                    '{{NAME}}' => $user->name,
                ],
                l('global.emails.auto_delete_inactive_users.body', $user->language)
            );

            send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

            /* Delete user */
            (new User())->delete($user->user_id);

            if(DEBUG) {
                echo sprintf('User deletion for inactivity user_id %s', $user->user_id);
            }
        }

    }

    private function auto_delete_unconfirmed_users() {
        if(!settings()->users->auto_delete_unconfirmed_users) {
            return;
        }

        /* Determine what users to delete */
        $days = settings()->users->auto_delete_unconfirmed_users;
        $past_date = (new \DateTime())->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get the users that need to be reminded */
        $result = database()->query("SELECT `user_id` FROM `users` WHERE `status` = '0' AND `datetime` < '{$past_date}' LIMIT 100");

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Delete user */
            (new User())->delete($user->user_id);

            if(DEBUG) {
                echo sprintf('User deleted for unconfirmed account user_id %s', $user->user_id);
            }
        }
    }

    private function logs_cleanup() {
        /* Clear files caches */
        clearstatcache();

        $current_month = (new \DateTime())->format('m');

        $deleted_count = 0;

        /* Get the data */
        foreach(glob(UPLOADS_PATH . 'logs/' . '*.log') as $file_path) {
            $file_last_modified = filemtime($file_path);

            if((new \DateTime())->setTimestamp($file_last_modified)->format('m') != $current_month) {
                unlink($file_path);
                $deleted_count++;
            }
        }

        if(DEBUG) {
            echo sprintf('logs_cleanup: Deleted %s file logs.', $deleted_count);
        }
    }

    private function users_logs_cleanup() {
        /* Delete old users logs */
        $ninety_days_ago_datetime = (new \DateTime())->modify('-90 days')->format('Y-m-d H:i:s');
        db()->where('datetime', $ninety_days_ago_datetime, '<')->delete('users_logs');
    }

    private function internal_notifications_cleanup() {
        /* Delete old users notifications */
        $ninety_days_ago_datetime = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
        db()->where('datetime', $ninety_days_ago_datetime, '<')->delete('internal_notifications');
    }

    private function websites_events_reset() {
        db()->update('websites', [
            'current_month_sessions_events' => 0,
            'current_month_events_children' => 0,
            'current_month_sessions_replays' => 0,
            'plan_sessions_events_limit_notice' => 0,
            'plan_events_children_limit_notice' => 0,
            'plan_sessions_replays_limit_notice' => 0,
        ]);
    }

    private function websites_sessions_events_notice() {
        if(!settings()->analytics->email_notices_is_enabled) {
            return;
        }

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT
                `website_id`,
                `users`.`user_id`,
                `plan_id`,
                `users`.`name`,
                `websites`.`name` AS `website_name`,
                `websites`.`host` AS `website_host`,
                `websites`.`path` AS `website_path`,
                `email`,
                `language`,
                `anti_phishing_code`,
                `plan_settings`
            FROM
                `users`
            LEFT JOIN
                `websites`
                ON `users`.`user_id` = `websites`.`user_id`
            WHERE
                `users`.`status` = 1
                AND JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.sessions_events_limit')) != '-1'
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.sessions_events_limit')) AS UNSIGNED) < `websites`.`current_month_sessions_events`
                AND `websites`.`plan_sessions_events_limit_notice` = 0
            LIMIT 25        
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings ?? '');

            db()->where('website_id', $row->website_id)->update('websites', [
                'plan_sessions_events_limit_notice' => 1,
            ]);

            /* Clear the cache */
            cache()->deleteItemsByTag('user_id=' . $row->user_id);

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.user_sessions_events_limit.subject', $row->language),
                [
                    '{{USER_PLAN_RENEW_LINK}}' => url('plan'),
                    '{{NAME}}' => $row->name,
                    '{{PLAN_NAME}}' => (new \Altum\Models\Plan())->get_plan_by_id($row->plan_id)->name,
                    '{{WEBSITE_NAME}}' => $row->website_name . ' (' . $row->website_host . $row->website_path . ')',
                    '{{SESSIONS_EVENTS_LIMIT}}' => $row->plan_settings->sessions_events_limit,
                ],
                l('global.emails.user_sessions_events_limit.body', $row->language)
            );

            send_mail($row->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

            if(DEBUG) {
                echo sprintf('User sessions events limit notice email sent for user_id %s', $row->user_id);
            }
        }
    }

    private function websites_events_children_notice() {
        if(!settings()->analytics->email_notices_is_enabled) {
            return;
        }

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT
                `website_id`,
                `users`.`user_id`,
                `plan_id`,
                `users`.`name`,
                `websites`.`name` AS `website_name`,
                `websites`.`host` AS `website_host`,
                `websites`.`path` AS `website_path`,
                `email`,
                `language`,
                `anti_phishing_code`,
                `plan_settings`
            FROM
                `users`
            LEFT JOIN
                `websites`
                ON `users`.`user_id` = `websites`.`user_id`
            WHERE
                `users`.`status` = 1
                AND JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.events_children_limit')) != '-1'
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.events_children_limit')) AS UNSIGNED) < `websites`.`current_month_events_children`
                AND `websites`.`plan_events_children_limit_notice` = 0
            LIMIT 25        
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings ?? '');

            db()->where('website_id', $row->website_id)->update('websites', [
                'plan_events_children_limit_notice' => 1,
            ]);

            /* Clear the cache */
            cache()->deleteItemsByTag('user_id=' . $row->user_id);

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.user_events_children_limit.subject', $row->language),
                [
                    '{{USER_PLAN_RENEW_LINK}}' => url('plan'),
                    '{{NAME}}' => $row->name,
                    '{{PLAN_NAME}}' => (new \Altum\Models\Plan())->get_plan_by_id($row->plan_id)->name,
                    '{{WEBSITE_NAME}}' => $row->website_name . ' (' . $row->website_host . $row->website_path . ')',
                    '{{EVENTS_CHILDREN_LIMIT}}' => $row->plan_settings->events_children_limit,
                ],
                l('global.emails.user_events_children_limit.body', $row->language)
            );

            send_mail($row->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

            if(DEBUG) {
                echo sprintf('User events children limit notice email sent for user_id %s', $row->user_id);
            }
        }
    }

    private function websites_sessions_replays_notice() {
        if(!settings()->analytics->email_notices_is_enabled) {
            return;
        }

        /* Get the users that need to be reminded */
        $result = database()->query("
            SELECT
                `website_id`,
                `users`.`user_id`,
                `plan_id`,
                `users`.`name`,
                `websites`.`name` AS `website_name`,
                `websites`.`host` AS `website_host`,
                `websites`.`path` AS `website_path`,
                `email`,
                `language`,
                `anti_phishing_code`,
                `plan_settings`
            FROM
                `users`
            LEFT JOIN
                `websites`
                ON `users`.`user_id` = `websites`.`user_id`
            WHERE
                `users`.`status` = 1
                AND JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.sessions_replays_limit')) != '-1'
                AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`users`.`plan_settings`, '$.sessions_replays_limit')) AS UNSIGNED) < `websites`.`current_month_sessions_replays`
                AND `websites`.`plan_sessions_replays_limit_notice` = 0
            LIMIT 25        
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings ?? '');

            db()->where('website_id', $row->website_id)->update('websites', [
                'plan_sessions_replays_limit_notice' => 1,
            ]);

            /* Clear the cache */
            cache()->deleteItemsByTag('user_id=' . $row->user_id);

            /* Prepare the email */
            $email_template = get_email_template(
                [],
                l('global.emails.user_sessions_replays_limit.subject', $row->language),
                [
                    '{{USER_PLAN_RENEW_LINK}}' => url('plan'),
                    '{{NAME}}' => $row->name,
                    '{{PLAN_NAME}}' => (new \Altum\Models\Plan())->get_plan_by_id($row->plan_id)->name,
                    '{{WEBSITE_NAME}}' => $row->website_name . ' (' . $row->website_host . $row->website_path . ')',
                    '{{SESSIONS_REPLAYS_LIMIT}}' => $row->plan_settings->sessions_replays_limit,
                ],
                l('global.emails.user_sessions_replays_limit.body', $row->language)
            );

            send_mail($row->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

            if(DEBUG) {
                echo sprintf('User sessions replays limit notice email sent for user_id %s', $row->user_id);
            }
        }
    }

    private function sessions_events_cleanup() {
        $date = get_date();
        db()->where('expiration_date', $date, '<')->delete('sessions_events');
        db()->where('expiration_date', $date, '<')->delete('lightweight_events');
    }

    private function events_children_cleanup() {
        $date = get_date();
        db()->where('expiration_date', $date, '<')->delete('events_children');
    }

    private function websites_replays_cleanup() {
        $date = get_date();

        /* Delete all the sessions replays which do not meet the minimum amount of seconds or are expired */
        $sessions_replays_minimum_duration = settings()->analytics->sessions_replays_minimum_duration;
        $result = database()->query("
            SELECT `session_id`, TIMESTAMPDIFF(SECOND, `datetime`, `last_datetime`) AS `seconds`, `is_offloaded`, `datetime`
            FROM `sessions_replays` 
            WHERE 
                (TIMESTAMPDIFF(HOUR, `datetime`, NOW()) > 1 AND TIMESTAMPDIFF(SECOND, `datetime`, `last_datetime`) < {$sessions_replays_minimum_duration} )
               OR `expiration_date` < '{$date}' 
            LIMIT 25;
        ");

        while($row = $result->fetch_object()) {
            db()->where('session_id', $row->session_id)->delete('sessions_replays');

            /* Clear cache */
            cache('store_adapter')->deleteItem('session_replay_' . $row->session_id);

            /* Offload uploading */
            if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url && $row->is_offloaded) {
                $file_name = base64_encode($row->session_id . $row->datetime) . '.txt';

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
    }

    private function websites_replays_offload() {
        if(!\Altum\Plugin::is_active('offload') || (\Altum\Plugin::is_active('offload') && !settings()->offload->uploads_url)) {
            return;
        }

        /* Get session replays that were not yet offloaded */
        $result = database()->query("
            SELECT 
                `session_id`,
                `datetime`
            FROM 
                `sessions_replays` 
            WHERE 
                DATE_ADD(`last_datetime`, INTERVAL 1 DAY) < NOW()
                AND `is_offloaded` = 0
            LIMIT 25;
        ");

        while($row = $result->fetch_object()) {

            /* Get from file store */
            $file_data = serialize(cache('store_adapter')->getItem('session_replay_' . $row->session_id)->get());

            $file_name = base64_encode($row->session_id . $row->datetime) . '.txt';

            /* Offload uploading */
            if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url) {
                try {
                    $s3 = new \Aws\S3\S3Client(get_aws_s3_config());

                    /* Upload image */
                    $s3_result = $s3->putObject([
                        'Bucket' => settings()->offload->storage_name,
                        'Key' => UPLOADS_URL_PATH . 'store/' . $file_name,
                        'ContentType' => 'text/plain',
                        'Body' => $file_data,
                        'ACL' => 'public-read'
                    ]);
                } catch (\Exception $exception) {
                    dil($exception->getMessage());
                }
            }

            /* Update the database */
            db()->where('session_id', $row->session_id)->update('sessions_replays', ['is_offloaded' => 1]);

            /* Clear cache */
            cache('store_adapter')->deleteItem('session_replay_' . $row->session_id);
        }
    }

    private function users_plan_expiry_reminder() {
        if(!settings()->payment->user_plan_expiry_reminder) {
            return;
        }

        /* Determine when to send the email reminder */
        $days = settings()->payment->user_plan_expiry_reminder;
        $future_date = (new \DateTime())->modify('+' . $days . ' days')->format('Y-m-d H:i:s');

        /* Get potential monitors from users that have almost all the conditions to get an email report right now */
        $result = database()->query("
            SELECT
                `user_id`,
                `name`,
                `email`,
                `plan_id`,
                `plan_expiration_date`,
                `language`,
                `anti_phishing_code`
            FROM 
                `users`
            WHERE 
                `status` = 1
                AND `plan_id` <> 'free'
                AND `plan_expiry_reminder` = '0'
                AND (`payment_subscription_id` IS NULL OR `payment_subscription_id` = '')
				AND `plan_expiration_date` < '{$future_date}'
            LIMIT 25
        ");

        $plans = [];
        if($result->num_rows) {
            $plans = (new \Altum\Models\Plan())->get_plans();
        }

        /* Go through each result */
        while($user = $result->fetch_object()) {

            /* Determine the exact days until expiration */
            $days_until_expiration = (new \DateTime($user->plan_expiration_date))->diff((new \DateTime()))->days;

            /* Prepare the email */
            $email_template = get_email_template(
                [
                    '{{DAYS_UNTIL_EXPIRATION}}' => $days_until_expiration,
                ],
                l('global.emails.user_plan_expiry_reminder.subject', $user->language),
                [
                    '{{DAYS_UNTIL_EXPIRATION}}' => $days_until_expiration,
                    '{{USER_PLAN_RENEW_LINK}}' => url('pay/' . $user->plan_id),
                    '{{NAME}}' => $user->name,
                    '{{PLAN_NAME}}' => $plans[$user->plan_id]->name,
                ],
                l('global.emails.user_plan_expiry_reminder.body', $user->language)
            );

            send_mail($user->email, $email_template->subject, $email_template->body, ['anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]);

            /* Update user */
            db()->where('user_id', $user->user_id)->update('users', ['plan_expiry_reminder' => 1]);

            if(DEBUG) {
                echo sprintf('users_plan_expiry_reminder() -> Email sent for user_id %s', $user->user_id);
            }
        }

    }

    public function email_reports() {

        /* Only run this part if the email reports are enabled */
        if(!settings()->analytics->email_reports_is_enabled) {
            return;
        }

        $this->initiate();
        $this->update_cron_execution_datetimes('email_reports_datetime');

        $date = get_date();

        /* Determine the frequency of email reports */
        $days_interval = 7;

        switch(settings()->analytics->email_reports_is_enabled) {
            case 'weekly':
                $days_interval = 7;
                break;

            case 'monthly':
                $days_interval = 30;
                break;
        }

        /* Get potential websites from users that have almost all the conditions to get an email report right now */
        $result = database()->query("
            SELECT
                `websites`.`website_id`,
                `websites`.`name`,
                `websites`.`host`,
                `websites`.`path`,
                `websites`.`email_reports_last_date`,
                `websites`.`tracking_type`,
                `users`.`user_id`,
                `users`.`email`,
                `users`.`plan_settings`,
                `users`.`language`,
                `users`.`anti_phishing_code`
            FROM 
                `websites`
            LEFT JOIN 
                `users` ON `websites`.`user_id` = `users`.`user_id` 
            WHERE 
                `users`.`status` = 1
                AND `websites`.`is_enabled` = 1 
                AND `websites`.`email_reports_is_enabled` = 1
				AND DATE_ADD(`websites`.`email_reports_last_date`, INTERVAL {$days_interval} DAY) <= '{$date}'
            LIMIT 25
        ");

        /* Go through each result */
        while($row = $result->fetch_object()) {
            $row->plan_settings = json_decode($row->plan_settings);

            /* Make sure the plan still lets the user get email reports */
            if(!$row->plan_settings->email_reports_is_enabled) {
                database()->query("UPDATE `websites` SET `email_reports_is_enabled` = 0 WHERE `website_id` = {$row->website_id}");
                continue;
            }

            /* Prepare */
            $previous_start_date = (new \DateTime())->modify('-' . $days_interval * 2 . ' days')->format('Y-m-d H:i:s');
            $start_date = (new \DateTime())->modify('-' . $days_interval . ' days')->format('Y-m-d H:i:s');

            /* Start getting information about the website to generate the statistics */
            switch($row->tracking_type) {
                case 'lightweight':
                    $basic_analytics = database()->query("
                        SELECT 
                            COUNT(*) AS `pageviews`, 
                            COALESCE(SUM(CASE WHEN `type` = 'landing_page' THEN 1 ELSE 0 END), 0) AS `visitors`
                        FROM 
                            `lightweight_events`
                        WHERE 
                            `website_id` = {$row->website_id} 
                            AND (`date` BETWEEN '{$start_date}' AND '{$date}')
                    ")->fetch_object() ?? null;

                    $previous_basic_analytics = database()->query("
                        SELECT 
                            COUNT(*) AS `pageviews`, 
                            COALESCE(SUM(CASE WHEN `type` = 'landing_page' THEN 1 ELSE 0 END), 0) AS `visitors`
                        FROM 
                            `lightweight_events`
                        WHERE 
                            `website_id` = {$row->website_id} 
                            AND (`date` BETWEEN '{$previous_start_date}' AND '{$start_date}')
                    ")->fetch_object() ?? null;
                    break;

                case 'normal':
                    $basic_analytics = database()->query("
                        SELECT 
                            COUNT(*) AS `pageviews`, 
                            COUNT(DISTINCT `sessions_events`.`session_id`) AS `sessions`, 
                            COUNT(DISTINCT `sessions_events`.`visitor_id`) AS `visitors`
                        FROM 
                            `sessions_events`
                        LEFT JOIN
                            `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
                        WHERE 
                            `sessions_events`.`website_id` = {$row->website_id} 
                            AND (`sessions_events`.`date` BETWEEN '{$start_date}' AND '{$date}')
                    ")->fetch_object() ?? null;

                    $previous_basic_analytics = database()->query("
                        SELECT 
                            COUNT(*) AS `pageviews`, 
                            COUNT(DISTINCT `sessions_events`.`session_id`) AS `sessions`, 
                            COUNT(DISTINCT `sessions_events`.`visitor_id`) AS `visitors`
                        FROM 
                            `sessions_events`
                        LEFT JOIN
                            `websites_visitors` ON `sessions_events`.`visitor_id` = `websites_visitors`.`visitor_id`
                        WHERE 
                            `sessions_events`.`website_id` = {$row->website_id} 
                            AND (`sessions_events`.`date` BETWEEN '{$previous_start_date}' AND '{$start_date}')
                    ")->fetch_object() ?? null;
                    break;
            }

            /* Prepare the email title */
            $replacers = [
                '{{WEBSITE:NAME}}' => $row->name,
                '{{START_DATE}}' => \Altum\Date::get($start_date, 5),
                '{{END_DATE}}' => \Altum\Date::get('', 5),
            ];

            $email_title = str_replace(
                array_keys($replacers),
                array_values($replacers),
                l('cron.email_reports.title', $row->language)
            );

            /* Prepare the View for the email content */
            $data = [
                'row'                       => $row,
                'basic_analytics'           => $basic_analytics,
                'previous_basic_analytics'  => $previous_basic_analytics,
                'previous_start_date'       => $previous_start_date,
                'start_date'                => $start_date,
                'date'                      => $date,
            ];

            $email_content = (new \Altum\View('partials/cron/email_reports', (array) $this))->run($data);

            /* Send the email */
            send_mail($row->email, $email_title, $email_content, ['anti_phishing_code' => $row->anti_phishing_code, 'language' => $row->language]);

            /* Update the website */
            db()->where('website_id', $row->website_id)->update('websites', ['email_reports_last_date' => $date]);

            /* Insert email log */
            db()->insert('email_reports', [
                'user_id' => $row->user_id,
                'website_id' => $row->website_id,
                'datetime' => $date,
            ]);

            if(DEBUG) {
                echo sprintf('Email sent for user_id %s and website_id %s', $row->user_id, $row->website_id);
            }
        }

    }

    public function broadcasts() {

        $this->initiate();
        $this->update_cron_execution_datetimes('broadcasts_datetime');

        /* We'll send up to 40 emails per run */
        $max_batch_size = 40;

        /* Fetch a broadcast in "processing" status */
        $broadcast = db()->where('status', 'processing')->getOne('broadcasts');
        if(!$broadcast) {
            $this->close();
            return;
        }

        $broadcast->users_ids = json_decode($broadcast->users_ids ?? '[]', true);
        $broadcast->sent_users_ids = json_decode($broadcast->sent_users_ids ?? '[]', true);
        $broadcast->settings = json_decode($broadcast->settings ?? '[]');

        /* Find which users are left to process */
        $remaining_user_ids = array_diff($broadcast->users_ids, $broadcast->sent_users_ids);

        /* If no one is left, mark broadcast as "sent" */
        if(empty($remaining_user_ids)) {
            db()->where('broadcast_id', $broadcast->broadcast_id)->update('broadcasts', [
                'status' => 'sent'
            ]);
            $this->close();
            return;
        }

        /* Get all batch users at once in one go */
        $user_ids_for_this_run = array_slice($remaining_user_ids, 0, $max_batch_size);

        $users = db()
            ->where('user_id', $user_ids_for_this_run, 'IN')
            ->get('users', null, [
                'user_id',
                'name',
                'email',
                'language',
                'anti_phishing_code',
                'continent_code',
                'country',
                'city_name',
                'device_type',
                'os_name',
                'browser_name',
                'browser_language'
            ]);

        /* Initialize PHPMailer once for this batch */
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->isHTML(true);

        /* SMTP connection settings */
        $mail->SMTPAuth = settings()->smtp->auth;
        $mail->Host = settings()->smtp->host;
        $mail->Port = settings()->smtp->port;
        $mail->Username = settings()->smtp->username;
        $mail->Password = settings()->smtp->password;

        if(settings()->smtp->encryption != '0') {
            $mail->SMTPSecure = settings()->smtp->encryption;
        }

        /* Keep the SMTP connection alive */
        $mail->SMTPKeepAlive = true;

        /* Set From / Reply-to */
        $mail->setFrom(settings()->smtp->from, settings()->smtp->from_name);
        if(!empty(settings()->smtp->reply_to) && !empty(settings()->smtp->reply_to_name)) {
            $mail->addReplyTo(settings()->smtp->reply_to, settings()->smtp->reply_to_name);
        } else {
            $mail->addReplyTo(settings()->smtp->from, settings()->smtp->from_name);
        }

        /* Optional CC/BCC */
        if(settings()->smtp->cc) {
            foreach (explode(',', settings()->smtp->cc) as $cc_email) {
                $mail->addCC(trim($cc_email));
            }
        }
        if(settings()->smtp->bcc) {
            foreach (explode(',', settings()->smtp->bcc) as $bcc_email) {
                $mail->addBCC(trim($bcc_email));
            }
        }

        $newly_sent_user_ids = [];

        /* Loop through users and send */
        foreach ($users as $user) {

            /* Prepare placeholders and the final template */
            $vars = [
                '{{USER:NAME}}'              => $user->name,
                '{{USER:EMAIL}}'             => $user->email,
                '{{USER:CONTINENT_NAME}}'    => get_continent_from_continent_code($user->continent_code),
                '{{USER:COUNTRY_NAME}}'      => get_country_from_country_code($user->country),
                '{{USER:CITY_NAME}}'         => $user->city_name,
                '{{USER:DEVICE_TYPE}}'       => l('global.device.' . $user->device_type),
                '{{USER:OS_NAME}}'           => $user->os_name,
                '{{USER:BROWSER_NAME}}'      => $user->browser_name,
                '{{USER:BROWSER_LANGUAGE}}'  => get_language_from_locale($user->browser_language),
            ];

            $email_template = get_email_template(
                $vars,
                htmlspecialchars_decode($broadcast->subject),
                $vars,
                convert_editorjs_json_to_html($broadcast->content)
            );

            /* Optional: tracking pixel & link rewriting */
            if(settings()->main->broadcasts_statistics_is_enabled) {
                $tracking_id = base64_encode('broadcast_id=' . $broadcast->broadcast_id . '&user_id=' . $user->user_id);
                $email_template->body .= '<img src="' . SITE_URL . 'broadcast?id=' . $tracking_id . '" style="display: none;" />';
                $email_template->body = preg_replace(
                    '/<a href=\"(.+)\"/',
                    '<a href="' . SITE_URL . 'broadcast?id=' . $tracking_id . '&url=$1"',
                    $email_template->body
                );
            }

            /* Clear addresses from previous iteration */
            $mail->clearAddresses();

            /* Add new email address */
            $mail->addAddress($user->email);

            /* Process the email title, template and body */
            extract(process_send_mail_template($email_template->subject, $email_template->body, ['is_broadcast' => true, 'is_system_email' => $broadcast->settings->is_system_email, 'anti_phishing_code' => $user->anti_phishing_code, 'language' => $user->language]));

            /* Set subject/body, then send */
            $mail->Subject = $title;
            $mail->Body = $email_template;
            $mail->AltBody = strip_tags($mail->Body);

            /* SEND */
            $mail->send();

            /* Track who we just emailed */
            $broadcast->sent_users_ids[] = $user->user_id;
            $newly_sent_user_ids[] = $user->user_id;

            Logger::users($user->user_id, 'broadcast.' . $broadcast->broadcast_id . '.sent');
        }

        /* Close this SMTP connection for the batch */
        $mail->smtpClose();

        /* Update broadcast once for the entire batch */
        db()->where('broadcast_id', $broadcast->broadcast_id)->update('broadcasts', [
            'sent_emails'             => db()->inc(count($newly_sent_user_ids)),
            'sent_users_ids'          => json_encode($broadcast->sent_users_ids),
            'status'                  => count($broadcast->users_ids) == count($broadcast->sent_users_ids) ? 'sent' : 'processing',
            'last_sent_email_datetime'=> get_date(),
        ]);

        /* Debugging */
        if(DEBUG) {
            echo '<br />' . "broadcast_id - {$broadcast->broadcast_id} | sent emails to users ids (total - " . count($newly_sent_user_ids) . "): " . implode(',', $newly_sent_user_ids) . '<br />';
        }

        $this->close();
    }

    public function push_notifications() {
        if(\Altum\Plugin::is_active('push-notifications')) {

            $this->initiate();

            /* Update cron job last run date */
            $this->update_cron_execution_datetimes('push_notifications_datetime');

            require_once \Altum\Plugin::get('push-notifications')->path . 'controllers/Cron.php';

            $this->close();
        }
    }

}
