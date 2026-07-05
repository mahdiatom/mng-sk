<?php
/**
 * QR حضور و غیاب — توکن هش، تولید تصویر با لوگو، اسکن و ثبت AJAX
 */
if (!defined('ABSPATH')) {
    exit;
}

define('SC_ATTENDANCE_QR_PREFIX', 'SC1:');
define('SC_ATTENDANCE_QR_HASH_LENGTH', 64);

/**
 * @return bool
 */
function sc_attendance_qr_is_enabled() {
    if (function_exists('sc_is_pro_feature_attendance_enabled') && !sc_is_pro_feature_attendance_enabled()) {
        return false;
    }
    if (function_exists('sc_is_pro_feature_attendance_qr_enabled') && !sc_is_pro_feature_attendance_qr_enabled()) {
        return false;
    }
    return (int) sc_get_setting('attendance_qr_enabled', '1') === 1;
}

/**
 * @return string
 */
function sc_attendance_qr_generate_hash() {
    do {
        $hash = hash('sha256', wp_generate_password(48, true, true) . wp_salt('auth') . microtime(true) . wp_rand());
        $hash = substr($hash, 0, SC_ATTENDANCE_QR_HASH_LENGTH);
    } while (sc_attendance_qr_get_member_by_hash($hash));

    return $hash;
}

/**
 * @param int  $member_id
 * @param bool $force
 * @return string|false
 */
function sc_attendance_qr_ensure_member_hash($member_id, $force = false) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return false;
    }

    sc_attendance_qr_ensure_db_ready();

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT attendance_qr_hash FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));

    if (!$force && is_string($existing) && strlen(trim($existing)) === SC_ATTENDANCE_QR_HASH_LENGTH) {
        return trim($existing);
    }

    if ($force) {
        sc_attendance_qr_clear_member_cache($member_id);
    }

    $hash = sc_attendance_qr_generate_hash();
    $updated = $wpdb->update(
        $table,
        [
            'attendance_qr_hash' => $hash,
            'updated_at'         => current_time('mysql'),
        ],
        ['id' => $member_id],
        ['%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        return false;
    }

    $saved = $wpdb->get_var($wpdb->prepare(
        "SELECT attendance_qr_hash FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (is_string($saved) && strlen(trim($saved)) === SC_ATTENDANCE_QR_HASH_LENGTH) {
        return trim($saved);
    }

    return false;
}

/**
 * @param string $hash
 * @return object|null
 */
function sc_attendance_qr_get_member_by_hash($hash) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    if ($hash === '') {
        return null;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE attendance_qr_hash = %s AND is_active = 1 LIMIT 1",
        $hash
    ));
}

/**
 * @param string $raw
 * @return string
 */
function sc_attendance_qr_sanitize_hash($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        return '';
    }
    if (stripos($raw, SC_ATTENDANCE_QR_PREFIX) === 0) {
        $raw = substr($raw, strlen(SC_ATTENDANCE_QR_PREFIX));
    }
    $raw = preg_replace('/[^a-f0-9]/i', '', $raw);
    if (strlen($raw) !== SC_ATTENDANCE_QR_HASH_LENGTH) {
        return '';
    }
    return strtolower($raw);
}

/**
 * @param string $hash
 * @return string
 */
function sc_attendance_qr_format_payload($hash) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    return $hash === '' ? '' : SC_ATTENDANCE_QR_PREFIX . $hash;
}

/**
 * @param string $scanned
 * @return string
 */
function sc_attendance_qr_parse_payload($scanned) {
    return sc_attendance_qr_sanitize_hash($scanned);
}

/**
 * @return string
 */
function sc_attendance_qr_get_logo_url() {
    $custom = trim((string) sc_get_setting('attendance_qr_logo_url', ''));
    if ($custom !== '') {
        return esc_url_raw($custom);
    }
    $club = trim((string) sc_get_setting('sc_club_logo_url', ''));
    if ($club !== '') {
        return esc_url_raw($club);
    }
    return '';
}

/**
 * @return int
 */
function sc_attendance_qr_get_logo_size_percent() {
    $size = (int) sc_get_setting('attendance_qr_logo_size_percent', '22');
    return max(12, min(30, $size));
}

/**
 * @return int
 */
function sc_attendance_qr_get_scan_cooldown_ms() {
    return max(300, min(5000, (int) sc_get_setting('attendance_qr_scan_cooldown_ms', '800')));
}

/**
 * @param string $type success|error|duplicate
 * @return string
 */
function sc_attendance_qr_get_sound_url($type) {
    $defaults = [
        'success'   => SC_ASSETS_URL . 'sounds/qr-success.mp3',
        'error'     => SC_ASSETS_URL . 'sounds/qr-error.mp3',
        'duplicate' => SC_ASSETS_URL . 'sounds/qr-duplicate.mp3',
    ];
    $key = 'attendance_qr_sound_' . $type . '_url';
    $custom = trim((string) sc_get_setting($key, ''));
    if ($custom !== '') {
        return esc_url_raw($custom);
    }
    if (!isset($defaults[$type])) {
        return '';
    }
    $mp3_path = SC_ASSETS_DIR . 'sounds/qr-' . $type . '.mp3';
    if (file_exists($mp3_path) && filesize($mp3_path) > 100) {
        return $defaults[$type];
    }
    $wav_url = str_replace('.mp3', '.wav', $defaults[$type]);
    $wav_path = SC_ASSETS_DIR . 'sounds/qr-' . $type . '.wav';
    if (file_exists($wav_path) && filesize($wav_path) > 100) {
        return $wav_url;
    }
    return $defaults[$type];
}

/**
 * @param string $url
 * @return bool
 */
function sc_attendance_qr_is_valid_sound_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (is_string($path) && preg_match('/\.mp3$/i', $path)) {
        return true;
    }
    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id > 0) {
        $mime = get_post_mime_type($attachment_id);
        return in_array($mime, ['audio/mpeg', 'audio/mp3'], true);
    }
    return false;
}

/**
 * @param string $wav_path
 * @param string $mp3_path
 * @return bool
 */
function sc_attendance_qr_convert_wav_to_mp3($wav_path, $mp3_path) {
    if (!file_exists($wav_path) || !function_exists('exec')) {
        return false;
    }
    $ffmpeg = '';
    foreach (['ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe', 'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe'] as $candidate) {
        $cmd = stripos(PHP_OS, 'WIN') === 0
            ? 'where ' . escapeshellarg($candidate) . ' 2>nul'
            : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';
        @exec($cmd, $out, $code);
        if ($code === 0 && !empty($out[0])) {
            $ffmpeg = trim((string) $out[0]);
            break;
        }
        if (is_file($candidate)) {
            $ffmpeg = $candidate;
            break;
        }
    }
    if ($ffmpeg === '') {
        return false;
    }
    $cmd = escapeshellarg($ffmpeg)
        . ' -y -i ' . escapeshellarg($wav_path)
        . ' -codec:a libmp3lame -b:a 128k '
        . escapeshellarg($mp3_path)
        . ' 2>&1';
    @exec($cmd, $unused, $exit_code);
    return $exit_code === 0 && is_file($mp3_path) && filesize($mp3_path) > 100;
}

/**
 * @return bool
 */
function sc_attendance_qr_show_in_dashboard() {
    return (int) sc_get_setting('attendance_qr_show_dashboard', '1') === 1;
}

/**
 * @param string $context public|admin
 * @return bool
 */
function sc_attendance_qr_should_show_member_card($context = 'public') {
    if (!sc_attendance_qr_is_enabled()) {
        return false;
    }
    if ($context === 'public' && !sc_attendance_qr_show_in_dashboard()) {
        return false;
    }
    return true;
}

/**
 * اطمینان از وجود ستون attendance_qr_hash (مهاجرت فقط admin_init قبلاً اجرا می‌شد).
 *
 * @return bool
 */
function sc_attendance_qr_ensure_db_ready() {
    if (get_option('sc_attendance_qr_hash_column_added', '0') === '1') {
        return true;
    }
    if (function_exists('sc_update_database')) {
        sc_update_database();
    }
    return get_option('sc_attendance_qr_hash_column_added', '0') === '1';
}

/**
 * @param resource $src
 * @param int      $new_w
 * @param int      $new_h
 * @return resource|false
 */
function sc_attendance_qr_scale_image($src, $new_w, $new_h) {
    if (!is_resource($src) && !($src instanceof GdImage)) {
        return false;
    }
    $new_w = max(1, (int) $new_w);
    $new_h = max(1, (int) $new_h);
    if (function_exists('imagescale')) {
        return imagescale($src, $new_w, $new_h);
    }
    $src_w = imagesx($src);
    $src_h = imagesy($src);
    if (!$src_w || !$src_h) {
        return false;
    }
    $dst = imagecreatetruecolor($new_w, $new_h);
    if (!$dst) {
        return false;
    }
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
    return $dst;
}

/**
 * @param int $member_id
 * @param int $size
 * @return array{member_id:int,member_name:string,image_url:string,download_url:string,error:string}
 */
function sc_attendance_qr_get_member_card_data($member_id, $size = 420) {
    $member_id = absint($member_id);
    $card = [
        'member_id'     => $member_id,
        'member_name'   => '',
        'image_url'     => '',
        'download_url'  => '',
        'error'         => '',
    ];

    if (!$member_id) {
        $card['error'] = 'شناسه بازیکن نامعتبر است.';
        return $card;
    }

    sc_attendance_qr_ensure_db_ready();

    global $wpdb;
    $table  = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        $card['error'] = 'بازیکن یافت نشد.';
        return $card;
    }

    $card['member_name'] = trim((string) $member->first_name . ' ' . (string) $member->last_name);

    if (!function_exists('imagecreatetruecolor')) {
        $card['error'] = 'افزونه GD در سرور فعال نیست. با مدیر سایت تماس بگیرید.';
        return $card;
    }

    $hash = sc_attendance_qr_ensure_member_hash($member_id);
    if (!$hash) {
        $card['error'] = 'امکان تولید کد اختصاصی وجود ندارد. لطفاً با پشتیبانی تماس بگیرید.';
        return $card;
    }

    $card['image_url']    = sc_attendance_qr_get_image_url($member_id, $size);
    $card['download_url'] = sc_attendance_qr_get_download_url($member_id, max($size, 420));
    return $card;
}

/**
 * @return string
 */
function sc_attendance_qr_get_cache_base_dir() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit($upload['basedir']) . 'sc-qr-cache/members/';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    return $dir;
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_cache_file_path($member_id, $size = 420) {
    $member_id = absint($member_id);
    $size      = max(200, min(800, absint($size)));
    if (!$member_id) {
        return '';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $hash  = $wpdb->get_var($wpdb->prepare(
        "SELECT attendance_qr_hash FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!is_string($hash) || strlen(trim($hash)) !== SC_ATTENDANCE_QR_HASH_LENGTH) {
        return '';
    }

    $cache_key = md5(
        trim($hash) . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    );

    return sc_attendance_qr_get_cache_base_dir() . $member_id . '-' . $cache_key . '.png';
}

/**
 * @param int $member_id
 */
function sc_attendance_qr_clear_member_cache($member_id) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return;
    }
    $dir  = sc_attendance_qr_get_cache_base_dir();
    $glob = glob($dir . $member_id . '-*.png');
    if (!is_array($glob)) {
        return;
    }
    foreach ($glob as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * بارگذاری phpqrcode با تنظیمات سریع‌تر
 */
function sc_attendance_qr_prepare_library() {
    if (class_exists('QRcode')) {
        return;
    }
    if (!defined('QR_CACHEABLE')) {
        define('QR_CACHEABLE', true);
    }
    if (!defined('QR_CACHE_DIR')) {
        $upload    = wp_upload_dir();
        $cache_dir = trailingslashit($upload['basedir']) . 'sc-qr-cache/lib/';
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        define('QR_CACHE_DIR', trailingslashit($cache_dir));
    }
    if (!defined('QR_FIND_BEST_MASK')) {
        define('QR_FIND_BEST_MASK', false);
    }
    if (!defined('QR_FIND_FROM_RANDOM')) {
        define('QR_FIND_FROM_RANDOM', false);
    }
    require_once SC_INCLUDES_DIR . 'lib/phpqrcode/qrlib.php';
}

/**
 * @param string $path
 * @param string $disposition inline|attachment
 * @param int    $member_id
 */
function sc_attendance_qr_send_png_file($path, $disposition, $member_id) {
    if (!is_file($path)) {
        status_header(404);
        exit;
    }

    $filesize = (int) filesize($path);
    if ($filesize <= 0) {
        status_header(500);
        exit;
    }

    $etag = '"' . md5_file($path) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: public, max-age=604800, immutable');
    header('ETag: ' . $etag);

    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
    if ($if_none_match !== '' && ($if_none_match === $etag || $if_none_match === trim($etag, '"'))) {
        status_header(304);
        exit;
    }

    $filename = 'attendance-qr-' . absint($member_id) . '.png';
    if ($disposition === 'attachment') {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }

    readfile($path);
    exit;
}

/**
 * @param int    $member_id
 * @param string $context public|admin
 * @param int    $size
 */
function sc_attendance_qr_render_member_card($member_id, $context = 'public', $size = 420) {
    if (!sc_attendance_qr_should_show_member_card($context)) {
        return;
    }

    $display_size = $context === 'public' ? 280 : 320;
    $sc_qr_card = sc_attendance_qr_get_member_card_data($member_id, $display_size);
    $sc_qr_card['context'] = $context;
    $partial = SC_TEMPLATES_DIR . 'partials/member-qr-card.php';
    if (file_exists($partial)) {
        include $partial;
    }
}

/**
 * @param int $member_id
 * @param int $size
 * @return string|false PNG binary
 */
function sc_attendance_qr_render_png_binary($member_id, $size = 420) {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $size = max(200, min(800, absint($size)));
    $cache_path = sc_attendance_qr_get_cache_file_path($member_id, $size);
    if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
        $cached = @file_get_contents($cache_path);
        if ($cached) {
            return $cached;
        }
    }

    $hash = sc_attendance_qr_ensure_member_hash($member_id);
    if (!$hash) {
        return false;
    }

    $payload = sc_attendance_qr_format_payload($hash);
    if ($payload === '') {
        return false;
    }

    sc_attendance_qr_prepare_library();

    // phpqrcode با outfile=false هدر Content-Type: image/png می‌فرستد و کل صفحه HTML را خراب می‌کند.
    $tmp_file = wp_tempnam('sc-attendance-qr-');
    if (!$tmp_file) {
        return false;
    }

    $module_size = max(3, min(8, (int) round($size / 56)));
    QRcode::png($payload, $tmp_file, QR_ECLEVEL_H, $module_size, 2);
    $qr_binary = @file_get_contents($tmp_file);
    @unlink($tmp_file);

    if (!$qr_binary) {
        return false;
    }

    $qr_img = @imagecreatefromstring($qr_binary);
    if (!$qr_img) {
        return false;
    }

    $qr_w = imagesx($qr_img);
    $qr_h = imagesy($qr_img);
    $canvas = imagecreatetruecolor($qr_w, $qr_h);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $qr_w, $qr_h, $white);
    imagecopy($canvas, $qr_img, 0, 0, 0, 0, $qr_w, $qr_h);
    imagedestroy($qr_img);

    $logo_url = sc_attendance_qr_get_logo_url();
    if ($logo_url !== '') {
        $logo_path = sc_attendance_qr_resolve_logo_path();
        $logo_img  = $logo_path ? sc_attendance_qr_load_image($logo_path) : null;
        if ($logo_img) {
            $pct = sc_attendance_qr_get_logo_size_percent() / 100;
            $logo_box = (int) round(min($qr_w, $qr_h) * $pct);
            $pad = (int) round($logo_box * 0.12);
            $inner = max(8, $logo_box - ($pad * 2));
            $bg_box = imagecreatetruecolor($logo_box, $logo_box);
            $bg_white = imagecolorallocate($bg_box, 255, 255, 255);
            imagefilledrectangle($bg_box, 0, 0, $logo_box, $logo_box, $bg_white);
            $resized = sc_attendance_qr_scale_image($logo_img, $inner, $inner);
            if ($resized) {
                imagecopy($bg_box, $resized, $pad, $pad, 0, 0, $inner, $inner);
                imagedestroy($resized);
            }
            $x = (int) (($qr_w - $logo_box) / 2);
            $y = (int) (($qr_h - $logo_box) / 2);
            imagecopy($canvas, $bg_box, $x, $y, 0, 0, $logo_box, $logo_box);
            imagedestroy($bg_box);
            imagedestroy($logo_img);
        }
    }

    if ($size > 0 && ($qr_w !== $size || $qr_h !== $size)) {
        $scaled = sc_attendance_qr_scale_image($canvas, $size, $size);
        if ($scaled) {
            imagedestroy($canvas);
            $canvas = $scaled;
        }
    }

    ob_start();
    imagepng($canvas, null, 6);
    $png = ob_get_clean();
    imagedestroy($canvas);

    if ($png && $cache_path !== '') {
        @file_put_contents($cache_path, $png);
    }

    return $png ?: false;
}

/**
 * @param string $url
 * @return string
 */
function sc_attendance_qr_url_to_local_path($url) {
    $upload = wp_upload_dir();
    if (!empty($upload['baseurl']) && strpos($url, $upload['baseurl']) === 0) {
        return str_replace($upload['baseurl'], $upload['basedir'], $url);
    }
    $site_url = site_url();
    if (strpos($url, $site_url) === 0) {
        $rel = ltrim(substr($url, strlen($site_url)), '/');
        $path = ABSPATH . $rel;
        return file_exists($path) ? $path : '';
    }
    return '';
}

/**
 * @return string|false مسیر محلی لوگو
 */
function sc_attendance_qr_resolve_logo_path() {
    $logo_url = sc_attendance_qr_get_logo_url();
    if ($logo_url === '') {
        return false;
    }

    $local = sc_attendance_qr_url_to_local_path($logo_url);
    if ($local && file_exists($local)) {
        return $local;
    }

    $cache_dir = trailingslashit(wp_upload_dir()['basedir']) . 'sc-qr-cache/';
    if (!is_dir($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    $cache_file = $cache_dir . 'logo-' . md5($logo_url) . '.img';
    if (file_exists($cache_file) && filesize($cache_file) > 32) {
        return $cache_file;
    }

    $logo_response = wp_remote_get($logo_url, ['timeout' => 4, 'sslverify' => false]);
    if (is_wp_error($logo_response) || wp_remote_retrieve_response_code($logo_response) !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($logo_response);
    if ($body === '') {
        return false;
    }

    @file_put_contents($cache_file, $body);
    return file_exists($cache_file) ? $cache_file : false;
}

/**
 * @param string $path
 * @return resource|false
 */
function sc_attendance_qr_load_image($path) {
    if (!file_exists($path)) {
        return false;
    }
    $info = @getimagesize($path);
    if (!$info) {
        return false;
    }
    switch ($info['mime']) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/gif':
            return @imagecreatefromgif($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default:
            return false;
    }
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_data_uri($member_id, $size = 420) {
    $png = sc_attendance_qr_render_png_binary($member_id, $size);
    if (!$png) {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($png);
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_image_url($member_id, $size = 280) {
    $member_id = absint($member_id);
    $size      = max(200, min(800, absint($size)));
    $hash      = sc_attendance_qr_ensure_member_hash($member_id);
    $version   = substr(md5(
        (string) $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    return add_query_arg([
        'action'    => 'sc_attendance_qr_image',
        'member_id' => $member_id,
        'size'      => $size,
        'nonce'     => wp_create_nonce('sc_attendance_qr_image_' . $member_id),
        'v'         => $version,
    ], admin_url('admin-ajax.php'));
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_download_url($member_id, $size = 420) {
    $member_id = absint($member_id);
    $size      = max(200, min(800, absint($size)));
    $hash      = sc_attendance_qr_ensure_member_hash($member_id);
    $version   = substr(md5(
        (string) $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    return add_query_arg([
        'action'    => 'sc_attendance_qr_download',
        'member_id' => $member_id,
        'size'      => $size,
        'nonce'     => wp_create_nonce('sc_attendance_qr_download_' . $member_id),
        'v'         => $version,
    ], admin_url('admin-ajax.php'));
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_export_data_uri($member_id, $size = 320) {
    if (!function_exists('sc_attendance_qr_is_enabled') || !sc_attendance_qr_is_enabled()) {
        return '';
    }
    return sc_attendance_qr_get_data_uri($member_id, $size);
}

/**
 * @param int $user_id
 * @param int $member_id
 * @return bool
 */
function sc_attendance_qr_user_can_view_member_qr($user_id, $member_id) {
    $user_id = absint($user_id);
    $member_id = absint($member_id);
    if (!$member_id) {
        return false;
    }
    if (current_user_can('manage_options') || current_user_can('sc_manage_attendance')) {
        return true;
    }
    if (!$user_id) {
        return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $owner = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    return $owner > 0 && $owner === $user_id;
}

/**
 * ثبت حضور یک بازیکن (منطق مشترک با attendance-add.php)
 *
 * @param array $args
 * @return array{success:bool,code:string,message:string,member_name?:string,attendance_id?:int,is_new?:bool}
 */
function sc_attendance_qr_register_present(array $args) {
    global $wpdb;

    $member_id = absint($args['member_id'] ?? 0);
    $course_id = absint($args['course_id'] ?? 0);
    $chapter_name = sanitize_text_field((string) ($args['chapter_name'] ?? ''));
    $group_name = sanitize_text_field((string) ($args['group_name'] ?? ''));
    $attendance_date = sanitize_text_field((string) ($args['attendance_date'] ?? ''));
    $current_user_id = absint($args['user_id'] ?? get_current_user_id());
    $status = 'present';

    if (!$member_id || !$course_id || $attendance_date === '') {
        return ['success' => false, 'code' => 'invalid', 'message' => 'اطلاعات ناقص است.'];
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name FROM `$members_table` WHERE id = %d AND is_active = 1 LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return ['success' => false, 'code' => 'member_not_found', 'message' => 'بازیکن یافت نشد.'];
    }
    $member_name = trim($member->first_name . ' ' . $member->last_name);

    $max_debt = floatval(sc_get_setting('max_debt_for_attendance', '0'));
    if ($max_debt > 0 && function_exists('debt_user')) {
        $debt = debt_user($member_id)[0];
        if ($debt >= $max_debt) {
            return [
                'success' => false,
                'code'    => 'debt_blocked',
                'message' => 'به دلیل بدهی بالاتر از سقف مجاز، ثبت حضور امکان‌پذیر نیست.',
                'member_name' => $member_name,
            ];
        }
    }

    $current_coach_id = 0;
    $current_is_coach = current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach');
    if ($current_is_coach) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $current_coach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
    }

    if ($current_coach_id > 0) {
        $member_scope = function_exists('sc_attendance_member_scope_sql')
            ? sc_attendance_member_scope_sql($course_id, $current_coach_id, $chapter_name)
            : [
                'coach_scope_where' => '(coach_id = %d OR coach_id IS NULL OR coach_id = 0)',
                'chapter_where'     => '',
                'prepare_args'      => [$current_coach_id],
            ];
        $group_filter = function_exists('sc_attendance_member_group_filter_sql')
            ? sc_attendance_member_group_filter_sql($group_name, $course_id)
            : ['sql' => '', 'args' => []];

        $can_touch_sql = "SELECT COUNT(*) FROM $member_courses_table mc
             WHERE mc.member_id = %d AND mc.course_id = %d AND mc.status = 'active'
               AND {$member_scope['coach_scope_where']}
               {$member_scope['chapter_where']}
               {$group_filter['sql']}
               AND (
                 mc.course_status_flags IS NULL OR mc.course_status_flags = ''
                 OR (
                   mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                 )
               )";
        $can_touch_args = array_merge(
            [$member_id, $course_id],
            $member_scope['prepare_args'],
            $group_filter['args'],
            ['%paused%', '%completed%', '%canceled%']
        );
        if (!(int) $wpdb->get_var($wpdb->prepare($can_touch_sql, $can_touch_args))) {
            return [
                'success' => false,
                'code'    => 'not_in_course',
                'message' => 'این بازیکن در دوره/گروه انتخاب‌شده ثبت‌نام فعال ندارد.',
                'member_name' => $member_name,
            ];
        }

        if ($chapter_name !== '') {
            $wpdb->query($wpdb->prepare(
                "UPDATE $member_courses_table SET coach_id = %d, chapter = %s, updated_at = %s
                 WHERE member_id = %d AND course_id = %d AND (coach_id IS NULL OR coach_id = 0)
                   AND (chapter = %s OR chapter IS NULL OR chapter = '')",
                $current_coach_id, $chapter_name, current_time('mysql'),
                $member_id, $course_id, $chapter_name
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $member_courses_table SET coach_id = %d, updated_at = %s
                 WHERE member_id = %d AND course_id = %d AND (coach_id IS NULL OR coach_id = 0)",
                $current_coach_id, current_time('mysql'), $member_id, $course_id
            ));
        }
    } else {
        $group_filter = function_exists('sc_attendance_member_group_filter_sql')
            ? sc_attendance_member_group_filter_sql($group_name, $course_id)
            : ['sql' => '', 'args' => []];
        $active_sql = "SELECT COUNT(*) FROM $member_courses_table mc
            WHERE mc.member_id = %d AND mc.course_id = %d AND mc.status = 'active' {$group_filter['sql']}";
        $active_args = array_merge([$member_id, $course_id], $group_filter['args']);
        if (!(int) $wpdb->get_var($wpdb->prepare($active_sql, $active_args))) {
            return [
                'success' => false,
                'code'    => 'not_in_course',
                'message' => 'این بازیکن در دوره انتخاب‌شده ثبت‌نام فعال ندارد.',
                'member_name' => $member_name,
            ];
        }
    }

    $course_row = $wpdb->get_row($wpdb->prepare(
        "SELECT title, price_per_session, course_type FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));
    $course_title = $course_row ? $course_row->title : '';
    $price_per_session = $course_row ? floatval($course_row->price_per_session) : 0;
    $attendance_date_shamsi = function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($attendance_date)
        : $attendance_date;

    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $attendances_table WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = 0",
        $member_id, $course_id, $attendance_date
    ));

    if ($existing > 0) {
        $current_record = $wpdb->get_row($wpdb->prepare(
            "SELECT status, user_id FROM $attendances_table WHERE id = %d",
            $existing
        ));
        if ($current_record && $current_record->status === 'present') {
            return [
                'success'     => true,
                'code'        => 'duplicate',
                'message'     => 'قبلاً به عنوان حاضر ثبت شده است.',
                'member_name' => $member_name,
                'attendance_id' => $existing,
                'is_new'      => false,
            ];
        }
        if ($current_record && $current_record->status === 'excused') {
            return [
                'success' => false,
                'code'    => 'excused_locked',
                'message' => 'وضعیت غیبت مجاز — امکان تغییر وجود ندارد.',
                'member_name' => $member_name,
            ];
        }

        $update_data = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];
        if ($current_record && empty($current_record->user_id)) {
            $update_data['user_id'] = $current_user_id;
        }
        if ($current_record && $current_record->status === 'absent') {
            $update_data['absence_sms_sent'] = 0;
        }
        $wpdb->update($attendances_table, $update_data, ['id' => $existing], ['%s', '%s', '%d'], ['%d']);

        if (function_exists('sc_is_private_course') && $course_row && sc_is_private_course($course_row) && function_exists('sc_private_sync_session_with_attendance')) {
            sc_private_sync_session_with_attendance($member_id, $course_id, $attendance_date, $status);
        }

        if (function_exists('sc_process_coach_salary_attendance_notifications')) {
            sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title);
        }

        return [
            'success'       => true,
            'code'          => 'updated',
            'message'       => 'حضور با موفقیت ثبت شد.',
            'member_name'   => $member_name,
            'attendance_id' => $existing,
            'is_new'        => false,
        ];
    }

    $need_deduct = sc_is_member_team($member_id) && $price_per_session > 0
        && function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
    if ($need_deduct) {
        $deduct = sc_deduct_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi);
        if (empty($deduct['success'])) {
            return [
                'success' => false,
                'code'    => 'wallet_failed',
                'message' => isset($deduct['message']) ? $deduct['message'] : 'موجودی کیف پول ناکافی.',
                'member_name' => $member_name,
            ];
        }
    }

    $inserted = $wpdb->insert(
        $attendances_table,
        [
            'member_id'        => $member_id,
            'course_id'        => $course_id,
            'schedule_slot_id' => 0,
            'attendance_date'  => $attendance_date,
            'status'           => $status,
            'user_id'          => $current_user_id,
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
    );

    if (!$inserted) {
        return ['success' => false, 'code' => 'db_error', 'message' => 'خطا در ثبت حضور.'];
    }

    $new_id = (int) $wpdb->insert_id;
    sc_decrease_member_session($member_id, $course_id);

    if (function_exists('sc_is_private_course') && $course_row && sc_is_private_course($course_row) && function_exists('sc_private_sync_session_with_attendance')) {
        sc_private_sync_session_with_attendance($member_id, $course_id, $attendance_date, $status);
    }

    if (function_exists('sc_process_coach_salary_attendance_notifications')) {
        sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title);
    }

    if (function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'attendance', $course_id, 'حضور QR — «' . $member_name . '» در «' . $course_title . '»', null, [
            'member_id' => $member_id,
            'attendance_date' => $attendance_date,
            'via' => 'qr_scan',
        ]);
    }

    return [
        'success'       => true,
        'code'          => 'created',
        'message'       => 'حضور با موفقیت ثبت شد.',
        'member_name'   => $member_name,
        'attendance_id' => $new_id,
        'is_new'        => true,
    ];
}

/**
 * تولید هش برای همه اعضای فاقد QR
 */
function sc_attendance_qr_backfill_member_hashes() {
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $ids = $wpdb->get_col(
        "SELECT id FROM `$table` WHERE attendance_qr_hash IS NULL OR attendance_qr_hash = '' OR CHAR_LENGTH(attendance_qr_hash) <> " . SC_ATTENDANCE_QR_HASH_LENGTH
    );
    foreach ($ids as $id) {
        sc_attendance_qr_ensure_member_hash((int) $id);
    }
}

add_action('init', 'sc_attendance_qr_ensure_default_sounds_on_init', 5);
function sc_attendance_qr_ensure_default_sounds_on_init() {
    sc_attendance_qr_ensure_default_sounds();
}

add_action('init', 'sc_attendance_qr_maybe_backfill_hashes', 20);
function sc_attendance_qr_maybe_backfill_hashes() {
    sc_attendance_qr_ensure_db_ready();
    sc_attendance_qr_ensure_default_sounds();
    if (get_option('sc_attendance_qr_hashes_backfilled', '0') === '1') {
        return;
    }
    sc_attendance_qr_backfill_member_hashes();
    update_option('sc_attendance_qr_hashes_backfilled', '1');
}

/**
 * تولید فایل‌های صوتی پیش‌فرض در صورت نبود
 */
function sc_attendance_qr_ensure_default_sounds() {
    $dir = SC_ASSETS_DIR . 'sounds/';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    $map = [
        'qr-success.mp3'   => [880, 180, 0.35],
        'qr-error.mp3'     => [220, 350, 0.40],
        'qr-duplicate.mp3' => [660, 120, 0.35],
    ];
    foreach ($map as $file => $cfg) {
        $mp3_path = $dir . $file;
        if (file_exists($mp3_path) && filesize($mp3_path) > 100) {
            continue;
        }
        $wav_path = preg_replace('/\.mp3$/i', '.wav', $mp3_path);
        $wav = sc_attendance_qr_build_wav((int) $cfg[0], (int) $cfg[1], (float) $cfg[2]);
        if (!$wav) {
            continue;
        }
        file_put_contents($wav_path, $wav);
        sc_attendance_qr_convert_wav_to_mp3($wav_path, $mp3_path);
    }
}

/**
 * @return string
 */
function sc_attendance_qr_build_wav($frequency, $duration_ms, $volume = 0.35) {
    $sample_rate = 44100;
    $samples = (int) ($sample_rate * $duration_ms / 1000);
    $data = '';
    for ($i = 0; $i < $samples; $i++) {
        $sample = (int) (32767 * $volume * sin(2 * M_PI * $frequency * $i / $sample_rate));
        $data .= pack('v', $sample);
    }
    $bits = 16;
    $channels = 1;
    $byte_rate = $sample_rate * $channels * $bits / 8;
    $block_align = $channels * $bits / 8;
    $chunk_size = 36 + strlen($data);
    return pack('a4Va4a4VvvVVvv', 'RIFF', $chunk_size, 'WAVE', 'fmt ', 16, 1, $channels, $sample_rate, $byte_rate, $block_align, $bits)
        . pack('a4V', 'data', strlen($data)) . $data;
}

add_action('sc_member_created', 'sc_attendance_qr_on_member_created', 10, 1);
add_action('sc_member_updated', 'sc_attendance_qr_on_member_created', 10, 1);
function sc_attendance_qr_on_member_created($member_id) {
    sc_attendance_qr_ensure_member_hash(absint($member_id));
}

add_action('wp_ajax_sc_attendance_qr_scan', 'sc_ajax_attendance_qr_scan');
function sc_ajax_attendance_qr_scan() {
    check_ajax_referer('sc_attendance_qr_scan', 'nonce');

    if (!sc_attendance_qr_is_enabled()) {
        wp_send_json_error(['message' => 'اسکن QR غیرفعال است.', 'code' => 'disabled']);
    }
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.', 'code' => 'forbidden']);
    }

    $hash = sc_attendance_qr_parse_payload(wp_unslash($_POST['qr_payload'] ?? ''));
    if ($hash === '') {
        wp_send_json_error(['message' => 'کد QR نامعتبر است.', 'code' => 'invalid_qr']);
    }

    $member = sc_attendance_qr_get_member_by_hash($hash);
    if (!$member) {
        wp_send_json_error(['message' => 'بازیکن مرتبط با این QR یافت نشد.', 'code' => 'unknown_qr']);
    }

    $course_id = absint($_POST['course_id'] ?? 0);
    $attendance_date = sanitize_text_field(wp_unslash($_POST['attendance_date'] ?? ''));
    $chapter_name = sanitize_text_field(wp_unslash($_POST['chapter_name'] ?? ''));
    $group_name = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));

    $result = sc_attendance_qr_register_present([
        'member_id'       => (int) $member->id,
        'course_id'       => $course_id,
        'chapter_name'    => $chapter_name,
        'group_name'      => $group_name,
        'attendance_date' => $attendance_date,
    ]);

    if (empty($result['success'])) {
        wp_send_json_error([
            'message'     => $result['message'],
            'code'        => $result['code'],
            'member_name' => $result['member_name'] ?? '',
        ]);
    }

    wp_send_json_success([
        'message'     => $result['message'],
        'code'        => $result['code'],
        'member_name' => $result['member_name'],
        'member_id'   => (int) $member->id,
        'is_new'      => !empty($result['is_new']),
    ]);
}

add_action('wp_ajax_sc_attendance_qr_image', 'sc_ajax_attendance_qr_image');
add_action('wp_ajax_sc_attendance_qr_download', 'sc_ajax_attendance_qr_download');

/**
 * @param int    $member_id
 * @param int    $size
 * @param string $disposition inline|attachment
 */
function sc_attendance_qr_output_png_response($member_id, $size = 420, $disposition = 'inline') {
    $member_id = absint($member_id);
    if (!$member_id || !sc_attendance_qr_user_can_view_member_qr(get_current_user_id(), $member_id)) {
        status_header(403);
        exit;
    }

    $size = max(200, min(800, absint($size)));
    $cache_path = sc_attendance_qr_get_cache_file_path($member_id, $size);
    if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
        sc_attendance_qr_send_png_file($cache_path, $disposition, $member_id);
    }

    $png = sc_attendance_qr_render_png_binary($member_id, $size);
    if (!$png) {
        status_header(500);
        exit;
    }

    $cache_path = sc_attendance_qr_get_cache_file_path($member_id, $size);
    if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
        sc_attendance_qr_send_png_file($cache_path, $disposition, $member_id);
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: public, max-age=604800, immutable');
    $filename = 'attendance-qr-' . $member_id . '.png';
    if ($disposition === 'attachment') {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }
    echo $png;
    exit;
}

function sc_ajax_attendance_qr_image() {
    $member_id = absint($_GET['member_id'] ?? 0);
    if (!$member_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_image_' . $member_id)) {
        status_header(403);
        exit;
    }
    sc_attendance_qr_output_png_response($member_id, absint($_GET['size'] ?? 420), 'inline');
}

function sc_ajax_attendance_qr_download() {
    $member_id = absint($_GET['member_id'] ?? 0);
    if (!$member_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_download_' . $member_id)) {
        status_header(403);
        exit;
    }
    sc_attendance_qr_output_png_response($member_id, absint($_GET['size'] ?? 420), 'attachment');
}

add_action('wp_ajax_sc_attendance_qr_regenerate', 'sc_ajax_attendance_qr_regenerate');
function sc_ajax_attendance_qr_regenerate() {
    check_ajax_referer('sc_attendance_qr_regenerate', 'nonce');
    $member_id = absint($_POST['member_id'] ?? 0);
    if (!$member_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
    }
    if (!current_user_can('manage_options') && !sc_attendance_qr_user_can_view_member_qr(get_current_user_id(), $member_id)) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $hash = sc_attendance_qr_ensure_member_hash($member_id, true);
    if (!$hash) {
        wp_send_json_error(['message' => 'خطا در تولید QR جدید.']);
    }
    wp_send_json_success([
        'message'      => 'کد QR جدید تولید شد.',
        'image_url'    => sc_attendance_qr_get_image_url($member_id),
        'download_url' => sc_attendance_qr_get_download_url($member_id),
        'data_uri'     => sc_attendance_qr_get_data_uri($member_id, 420),
    ]);
}
