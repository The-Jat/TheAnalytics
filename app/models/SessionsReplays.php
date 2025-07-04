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

namespace Altum\Models;

use Altum\Cache;

defined('ALTUMCODE') || die();

class SessionsReplays extends Model {

    public function delete($replay_id) {
        Cache::store_initialize();

        /* Database query */
        $replay = db()->where('replay_id', $replay_id)->getOne('sessions_replays');

        /* Clear cache */
        cache('store_adapter')->deleteItem('session_replay_' . $replay->session_id);

        /* Offload uploading */
        if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url && $replay->is_offloaded) {
            $file_name = base64_encode($replay->session_id . $replay->date) . '.txt';

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

        /* Database query */
        db()->where('replay_id', $replay_id)->delete('sessions_replays');

    }

}
