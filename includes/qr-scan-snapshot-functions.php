<?php
/**
 * عکس لحظه اسکن QR — حضور و غیاب و تردد (دوربین اسکن + اختیاری جلو)
 */
if (!defined('ABSPATH')) {
    exit;
}

function sc_qr_scan_photo_is_enabled() {
    return (int) sc_get_setting('qr_scan_photo_enabled', '0') === 1;
}

function sc_qr_scan_photo_front_is_enabled() {
    return sc_qr_scan_photo_is_enabled()
        && (int) sc_get_setting('qr_scan_photo_front_enabled', '0') === 1;
}

function sc_qr_scan_photo_retention_days() {
    return max(1, min(60, (int) sc_get_setting('qr_scan_photo_retention_days', '30')));
}

function sc_qr_scan_photo_ensure_columns() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $wpdb;
    $att = $wpdb->prefix . 'sc_attendances';
    $tard = $wpdb->prefix . 'sc_tarddod_records';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $att)) === $att) {
        if (empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$att` LIKE %s", 'scan_photo')))) {
            $wpdb->query("ALTER TABLE `$att` ADD COLUMN `scan_photo` varchar(255) DEFAULT NULL COMMENT 'عکس دوربین اسکن' AFTER `record_method`");
        }
        if (empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$att` LIKE %s", 'scan_photo_front')))) {
            $wpdb->query("ALTER TABLE `$att` ADD COLUMN `scan_photo_front` varchar(255) DEFAULT NULL COMMENT 'عکس دوربین جلو' AFTER `scan_photo`");
        }
    }
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tard)) === $tard) {
        if (empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tard` LIKE %s", 'scan_photo')))) {
            $wpdb->query("ALTER TABLE `$tard` ADD COLUMN `scan_photo` varchar(255) DEFAULT NULL COMMENT 'عکس دوربین اسکن' AFTER `record_method`");
        }
        if (empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tard` LIKE %s", 'scan_photo_front')))) {
            $wpdb->query("ALTER TABLE `$tard` ADD COLUMN `scan_photo_front` varchar(255) DEFAULT NULL COMMENT 'عکس دوربین جلو' AFTER `scan_photo`");
        }
    }
}

function sc_qr_scan_photo_upload_dir() {
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return ['basedir' => '', 'baseurl' => ''];
    }
    $basedir = trailingslashit($upload['basedir']) . 'sportclub/qr-snapshots';
    $baseurl = trailingslashit($upload['baseurl']) . 'sportclub/qr-snapshots';
    if (!is_dir($basedir)) {
        wp_mkdir_p($basedir);
    }
    $htaccess = $basedir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n");
    }
    $index = $basedir . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }
    return ['basedir' => $basedir, 'baseurl' => $baseurl];
}

function sc_qr_scan_photo_url_from_relative($relative) {
    $relative = basename((string) $relative);
    if ($relative === '' || $relative === '.' || $relative === '..') {
        return '';
    }
    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['baseurl'] === '') {
        return '';
    }
    $path = trailingslashit($dir['basedir']) . $relative;
    if (!is_file($path)) {
        return '';
    }
    return trailingslashit($dir['baseurl']) . rawurlencode($relative);
}

/**
 * @param string $data_url
 * @param string $context attendance|tarddod
 * @param int    $record_id
 * @param string $suffix rear|front
 * @return string|false filename
 */
function sc_qr_scan_photo_save_data_url($data_url, $context, $record_id, $suffix = 'rear') {
    if (!sc_qr_scan_photo_is_enabled()) {
        return false;
    }
    sc_qr_scan_photo_ensure_columns();

    $context = $context === 'tarddod' ? 'tarddod' : 'attendance';
    $suffix = $suffix === 'front' ? 'front' : 'rear';
    $record_id = absint($record_id);
    if (!$record_id) {
        return false;
    }

    $data_url = trim((string) $data_url);
    if ($data_url === '') {
        return false;
    }
    // Accept with or without data: prefix
    if (strpos($data_url, 'data:') === 0) {
        if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $data_url)) {
            return false;
        }
        $raw = preg_replace('#^data:image/[^;]+;base64,#i', '', $data_url);
    } else {
        $raw = $data_url;
    }
    $raw = preg_replace('/\s+/', '', $raw);
    $binary = base64_decode($raw, true);
    if ($binary === false || strlen($binary) < 200 || strlen($binary) > 3 * 1024 * 1024) {
        return false;
    }

    $dir = sc_qr_scan_photo_upload_dir();
    if ($dir['basedir'] === '') {
        return false;
    }

    $filename = sprintf(
        '%s-%d-%s-%s.jpg',
        $context,
        $record_id,
        $suffix,
        gmdate('YmdHis') . wp_generate_password(4, false, false)
    );
    $path = trailingslashit($dir['basedir']) . $filename;

    // Prefer GD resize; fallback to raw write
    $written = false;
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($binary);
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            $max_w = 720;
            if ($w > $max_w && $w > 0) {
                $nw = $max_w;
                $nh = max(1, (int) round($h * ($max_w / $w)));
                $resized = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            $written = (bool) imagejpeg($img, $path, 75);
            imagedestroy($img);
        }
    }
    if (!$written) {
        $written = (bool) file_put_contents($path, $binary);
    }
    if (!$written || !is_file($path) || filesize($path) < 100) {
        return false;
    }
    return $filename;
}

function sc_qr_scan_photo_attach($context, $record_id, $rear_file = '', $front_file = '') {
    sc_qr_scan_photo_ensure_columns();
    global $wpdb;
    $record_id = absint($record_id);
    if (!$record_id) {
        return false;
    }
    $data = [];
    $fmt = [];
    if ($rear_file !== '') {
        $data['scan_photo'] = sanitize_file_name($rear_file);
        $fmt[] = '%s';
    }
    if ($front_file !== '') {
        $data['scan_photo_front'] = sanitize_file_name($front_file);
        $fmt[] = '%s';
    }
    if (!$data) {
        return false;
    }
    if ($context === 'tarddod') {
        return false !== $wpdb->update($wpdb->prefix . 'sc_tarddod_records', $data, ['id' => $record_id], $fmt, ['%d']);
    }
    $data['updated_at'] = current_time('mysql');
    $fmt[] = '%s';
    return false !== $wpdb->update($wpdb->prefix . 'sc_attendances', $data, ['id' => $record_id], $fmt, ['%d']);
}

/**
 * Process photos from POST after successful scan.
 *
 * @return array{rear:string,front:string,rear_url:string,front_url:string}
 */
function sc_qr_scan_photo_handle_post_for_record($context, $record_id) {
    $out = ['rear' => '', 'front' => '', 'rear_url' => '', 'front_url' => ''];
    if (!sc_qr_scan_photo_is_enabled() || !$record_id) {
        return $out;
    }

    $rear_data = isset($_POST['photo_data']) ? wp_unslash($_POST['photo_data']) : '';
    $front_data = isset($_POST['photo_data_front']) ? wp_unslash($_POST['photo_data_front']) : '';

    $rear_file = $rear_data !== '' ? sc_qr_scan_photo_save_data_url($rear_data, $context, $record_id, 'rear') : false;
    $front_file = '';
    if (sc_qr_scan_photo_front_is_enabled() && $front_data !== '') {
        $front_file = sc_qr_scan_photo_save_data_url($front_data, $context, $record_id, 'front');
    }

    if ($rear_file || $front_file) {
        sc_qr_scan_photo_attach($context, $record_id, $rear_file ?: '', $front_file ?: '');
    }
    if ($rear_file) {
        $out['rear'] = $rear_file;
        $out['rear_url'] = sc_qr_scan_photo_url_from_relative($rear_file);
    }
    if ($front_file) {
        $out['front'] = $front_file;
        $out['front_url'] = sc_qr_scan_photo_url_from_relative($front_file);
    }
    return $out;
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
    sc_qr_scan_photo_ensure_columns();
    $days = sc_qr_scan_photo_retention_days();
    $cutoff_local = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
    global $wpdb;
    $att = $wpdb->prefix . 'sc_attendances';
    $tard = $wpdb->prefix . 'sc_tarddod_records';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, scan_photo, scan_photo_front FROM `$att`
         WHERE ((scan_photo IS NOT NULL AND scan_photo <> '') OR (scan_photo_front IS NOT NULL AND scan_photo_front <> ''))
           AND created_at < %s LIMIT 500",
        $cutoff_local
    ));
    foreach ((array) $rows as $row) {
        if (!empty($row->scan_photo)) {
            sc_qr_scan_photo_delete_file($row->scan_photo);
        }
        if (!empty($row->scan_photo_front)) {
            sc_qr_scan_photo_delete_file($row->scan_photo_front);
        }
        $wpdb->update($att, ['scan_photo' => '', 'scan_photo_front' => ''], ['id' => (int) $row->id], ['%s', '%s'], ['%d']);
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, scan_photo, scan_photo_front FROM `$tard`
         WHERE ((scan_photo IS NOT NULL AND scan_photo <> '') OR (scan_photo_front IS NOT NULL AND scan_photo_front <> ''))
           AND created_at < %s LIMIT 500",
        $cutoff_local
    ));
    foreach ((array) $rows as $row) {
        if (!empty($row->scan_photo)) {
            sc_qr_scan_photo_delete_file($row->scan_photo);
        }
        if (!empty($row->scan_photo_front)) {
            sc_qr_scan_photo_delete_file($row->scan_photo_front);
        }
        $wpdb->update($tard, ['scan_photo' => '', 'scan_photo_front' => ''], ['id' => (int) $row->id], ['%s', '%s'], ['%d']);
    }

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

    $photos = sc_qr_scan_photo_handle_post_for_record($context, $record_id);
    if ($photos['rear'] === '' && $photos['front'] === '') {
        wp_send_json_error(['message' => 'ذخیره عکس ناموفق بود.']);
    }
    wp_send_json_success($photos);
}

/**
 * Render view-only photo buttons for report tables (no thumbnail by default).
 */
function sc_qr_scan_photo_render_cell($rear_file, $front_file = '') {
    $rear_url = $rear_file ? sc_qr_scan_photo_url_from_relative($rear_file) : '';
    $front_url = $front_file ? sc_qr_scan_photo_url_from_relative($front_file) : '';
    if ($rear_url === '' && $front_url === '') {
        echo '<span style="color:#9ca3af;">—</span>';
        return;
    }
    echo '<div class="sc-qr-scan-photos sc-qr-scan-photos--buttons">';
    if ($rear_url !== '') {
        echo '<a href="' . esc_url($rear_url) . '" class="sc-qr-scan-lightbox button button-small" data-title="دوربین اسکن">مشاهده' . ($front_url !== '' ? ' اسکن' : '') . '</a>';
    }
    if ($front_url !== '') {
        echo '<a href="' . esc_url($front_url) . '" class="sc-qr-scan-lightbox button button-small" data-title="دوربین جلو">مشاهده جلو</a>';
    }
    echo '</div>';
}

add_action('admin_footer', 'sc_qr_scan_photo_admin_lightbox_script');
function sc_qr_scan_photo_admin_lightbox_script() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) {
        return;
    }
    $ok = (strpos((string) $screen->id, 'sc-reports-attendance-qr') !== false)
        || (strpos((string) $screen->id, 'sc-tarddod-records') !== false);
    if (!$ok) {
        return;
    }
    ?>
    <div id="sc-qr-lightbox" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.82);align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;max-width:920px;width:100%;padding:16px;position:relative;">
            <button type="button" id="sc-qr-lightbox-close" class="button" style="position:absolute;top:10px;left:10px;">بستن</button>
            <h3 id="sc-qr-lightbox-title" style="margin:0 0 12px;padding-left:70px;"></h3>
            <img id="sc-qr-lightbox-img" src="" alt="" style="max-width:100%;height:auto;display:block;margin:0 auto;border-radius:8px;">
        </div>
    </div>
    <script>
    (function(){
        var box = document.getElementById('sc-qr-lightbox');
        if (!box) return;
        var img = document.getElementById('sc-qr-lightbox-img');
        var title = document.getElementById('sc-qr-lightbox-title');
        function close(){ box.style.display='none'; img.src=''; }
        document.getElementById('sc-qr-lightbox-close').addEventListener('click', close);
        box.addEventListener('click', function(e){ if (e.target === box) close(); });
        document.addEventListener('click', function(e){
            var a = e.target.closest('.sc-qr-scan-lightbox');
            if (!a) return;
            e.preventDefault();
            var href = a.getAttribute('href');
            if (!href) return;
            title.textContent = a.getAttribute('data-title') || 'عکس اسکن';
            img.src = href;
            box.style.display = 'flex';
        });
    })();
    </script>
    <style>
    .sc-qr-scan-photos{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    .sc-qr-scan-photos--buttons .button{min-height:28px;line-height:26px;padding:0 10px;font-size:12px}
    </style>
    <?php
}
