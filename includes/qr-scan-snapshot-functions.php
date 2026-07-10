<?php
/**
 * عکس لحظه اسکن QR — حضور و غیاب و تردد
 */
if (!defined('ABSPATH')) {
    exit;
}

function sc_qr_scan_photo_is_enabled() {
    return (int) sc_get_setting('qr_scan_photo_enabled', '0') === 1;
}

function sc_qr_scan_photo_retention_days() {
    return max(1, min(60, (int) sc_get_setting('qr_scan_photo_retention_days', '30')));
}

function sc_qr_scan_photo_upload_dir() {
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return ['basedir' => '', 'baseurl' => ''];
    }
    $subdir = '/sportclub/qr-snapshots';
    $basedir = trailingslashit($upload['basedir']) . 'sportclub/qr-snapshots';
    $baseurl = trailingslashit($upload['baseurl']) . 'sportclub/qr-snapshots';
    if (!is_dir($basedir)) {
        wp_mkdir_p($basedir);
    }
    $index = $basedir . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }
    return ['basedir' => $basedir, 'baseurl' => $baseurl, 'subdir' => $subdir];
}

function sc_qr_scan_photo_url_from_relative($relative) {
    $relative = ltrim((string) $relative, '/');
    if ($relative === '') {
        return '';
    }
    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['baseurl'] === '') {
        return '';
    }
    return trailingslashit($dir['baseurl']) . basename($relative);
}

/**
 * @param string $data_url data:image/jpeg;base64,...
 * @param string $context attendance|tarddod
 * @param int    $record_id
 * @return string|false Relative filename or false
 */
function sc_qr_scan_photo_save_data_url($data_url, $context, $record_id) {
    if (!sc_qr_scan_photo_is_enabled()) {
        return false;
    }
    $context = $context === 'tarddod' ? 'tarddod' : 'attendance';
    $record_id = absint($record_id);
    if (!$record_id) {
        return false;
    }

    $data_url = (string) $data_url;
    if (!preg_match('#^data:image/(jpeg|jpg|png);base64,#i', $data_url)) {
        return false;
    }
    $raw = preg_replace('#^data:image/\w+;base64,#i', '', $data_url);
    $binary = base64_decode($raw, true);
    if ($binary === false || strlen($binary) < 500 || strlen($binary) > 2.5 * 1024 * 1024) {
        return false;
    }

    if (!function_exists('imagecreatefromstring')) {
        return false;
    }
    $img = @imagecreatefromstring($binary);
    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $max_w = 720;
    if ($w > $max_w) {
        $nw = $max_w;
        $nh = max(1, (int) round($h * ($max_w / $w)));
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['basedir'] === '') {
        imagedestroy($img);
        return false;
    }

    $filename = sprintf(
        '%s-%d-%s.jpg',
        $context,
        $record_id,
        gmdate('YmdHis') . '-' . wp_generate_password(6, false, false)
    );
    $path = trailingslashit($dir['basedir']) . $filename;

    $ok = imagejpeg($img, $path, 72);
    imagedestroy($img);
    if (!$ok || !is_file($path)) {
        return false;
    }

    return $filename;
}

function sc_qr_scan_photo_attach_to_attendance($attendance_id, $filename) {
    global $wpdb;
    $attendance_id = absint($attendance_id);
    $filename = sanitize_file_name((string) $filename);
    if (!$attendance_id || $filename === '') {
        return false;
    }
    $table = $wpdb->prefix . 'sc_attendances';
    return false !== $wpdb->update(
        $table,
        ['scan_photo' => $filename, 'updated_at' => current_time('mysql')],
        ['id' => $attendance_id],
        ['%s', '%s'],
        ['%d']
    );
}

function sc_qr_scan_photo_attach_to_tarddod($record_id, $filename) {
    global $wpdb;
    $record_id = absint($record_id);
    $filename = sanitize_file_name((string) $filename);
    if (!$record_id || $filename === '') {
        return false;
    }
    $table = $wpdb->prefix . 'sc_tarddod_records';
    return false !== $wpdb->update(
        $table,
        ['scan_photo' => $filename],
        ['id' => $record_id],
        ['%s'],
        ['%d']
    );
}

function sc_qr_scan_photo_delete_file($filename) {
    $filename = basename((string) $filename);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }
    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['basedir'] === '') {
        return;
    }
    $path = trailingslashit($dir['basedir']) . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function sc_qr_scan_photo_purge_expired() {
    if (!sc_qr_scan_photo_is_enabled() && (int) sc_get_setting('qr_scan_photo_retention_days', '30') <= 0) {
        // still purge if files exist with retention setting
    }
    $days = sc_qr_scan_photo_retention_days();
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    // Use site local time for DB comparison
    $cutoff_local = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

    global $wpdb;
    $att = $wpdb->prefix . 'sc_attendances';
    $tard = $wpdb->prefix . 'sc_tarddod_records';

    $att_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$att` LIKE %s", 'scan_photo'));
    if (!empty($att_col)) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scan_photo FROM `$att`
             WHERE scan_photo IS NOT NULL AND scan_photo <> ''
               AND created_at < %s
             LIMIT 500",
            $cutoff_local
        ));
        foreach ((array) $rows as $row) {
            sc_qr_scan_photo_delete_file($row->scan_photo);
            $wpdb->update($att, ['scan_photo' => ''], ['id' => (int) $row->id], ['%s'], ['%d']);
        }
    }

    $tard_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tard` LIKE %s", 'scan_photo'));
    if (!empty($tard_col)) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scan_photo FROM `$tard`
             WHERE scan_photo IS NOT NULL AND scan_photo <> ''
               AND created_at < %s
             LIMIT 500",
            $cutoff_local
        ));
        foreach ((array) $rows as $row) {
            sc_qr_scan_photo_delete_file($row->scan_photo);
            $wpdb->update($tard, ['scan_photo' => ''], ['id' => (int) $row->id], ['%s'], ['%d']);
        }
    }

    // Also remove orphan files older than retention by mtime
    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['basedir'] !== '' && is_dir($dir['basedir'])) {
        $expire_ts = time() - ($days * DAY_IN_SECONDS);
        foreach (glob(trailingslashit($dir['basedir']) . '*.jpg') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $expire_ts) {
                @unlink($file);
            }
        }
    }
}

add_action('init', 'sc_qr_scan_photo_schedule_cron');
function sc_qr_scan_photo_schedule_cron() {
    if (!wp_next_scheduled('sc_qr_scan_photo_purge_cron')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sc_qr_scan_photo_purge_cron');
    }
}

add_action('sc_qr_scan_photo_purge_cron', 'sc_qr_scan_photo_purge_expired');

add_action('wp_ajax_sc_qr_scan_snapshot_save', 'sc_ajax_qr_scan_snapshot_save');
function sc_ajax_qr_scan_snapshot_save() {
    if (!sc_qr_scan_photo_is_enabled()) {
        wp_send_json_error(['message' => 'عکس اسکن غیرفعال است.']);
    }

    $context = sanitize_key(wp_unslash($_POST['context'] ?? ''));
    $record_id = absint($_POST['record_id'] ?? 0);
    $data_url = wp_unslash($_POST['photo_data'] ?? '');

    if ($context === 'attendance') {
        check_ajax_referer('sc_attendance_qr_scan', 'nonce');
        if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی ندارید.']);
        }
    } elseif ($context === 'tarddod') {
        check_ajax_referer('sc_tarddod_scan', 'nonce');
        if (!function_exists('sc_tarddod_can_manage') || !sc_tarddod_can_manage()) {
            wp_send_json_error(['message' => 'دسترسی ندارید.']);
        }
    } else {
        wp_send_json_error(['message' => 'context نامعتبر است.']);
    }

    if (!$record_id || $data_url === '') {
        wp_send_json_error(['message' => 'داده عکس ناقص است.']);
    }

    $filename = sc_qr_scan_photo_save_data_url($data_url, $context, $record_id);
    if (!$filename) {
        wp_send_json_error(['message' => 'ذخیره عکس ناموفق بود.']);
    }

    if ($context === 'attendance') {
        sc_qr_scan_photo_attach_to_attendance($record_id, $filename);
    } else {
        sc_qr_scan_photo_attach_to_tarddod($record_id, $filename);
    }

    wp_send_json_success([
        'filename' => $filename,
        'url'      => sc_qr_scan_photo_url_from_relative($filename),
    ]);
}
