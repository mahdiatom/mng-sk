<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}

global $wpdb;
$is_coach = !empty($GLOBALS['sc_private_notes_is_coach']);
$title_page = 'افزودن یادداشت خصوصی';
$list_page = $is_coach ? 'sc-coach-private-notes' : 'sc-private-notes';
$list_url = admin_url('admin.php?page=' . $list_page);

$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table = $wpdb->prefix . 'sc_events';
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';

$message = '';
$message_type = '';

if (isset($_POST['sc_save_private_note']) && check_admin_referer('sc_save_private_note_action', 'sc_save_private_note_nonce')) {
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'specific';
    $attachment_ids = !empty($_POST['private_note_attachment_ids']) ? sc_private_notes_validate_attachment_ids($_POST['private_note_attachment_ids'], 5) : [];

    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
        'member_status' => isset($_POST['member_status']) ? sanitize_text_field(wp_unslash($_POST['member_status'])) : 'all',
    ];
    if ($is_coach) {
        $target_type = in_array($target_type, ['specific', 'course', 'team', 'level', 'team_level'], true) ? $target_type : 'specific';
    }

    $members = function_exists('sc_bulk_actions_get_members') ? sc_bulk_actions_get_members($target_type, $config) : [];
    $member_ids = array_values(array_unique(array_map('absint', wp_list_pluck((array) $members, 'id'))));

    if (empty($title) || trim(wp_strip_all_tags($content)) === '') {
        $message = 'عنوان و متن یادداشت الزامی است.';
        $message_type = 'error';
    } elseif (empty($member_ids)) {
        $message = 'هیچ کاربری با این فیلترها پیدا نشد.';
        $message_type = 'error';
    } else {
        $created = sc_private_notes_create_for_members($member_ids, $title, $content, $attachment_ids);
        if (is_wp_error($created)) {
            $message = $created->get_error_message();
            $message_type = 'error';
        } else {
            $message = sprintf('یادداشت با موفقیت برای %d کاربر ثبت شد.', (int) $created);
            $message_type = 'success';
        }
    }
}

if ($is_coach && function_exists('sc_support_get_coach_id_by_user_id')) {
    $coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
    $coach_members = $coach_id > 0 && function_exists('sc_support_get_members_for_coach') ? sc_support_get_members_for_coach($coach_id) : [];
    $members = [];
    foreach ((array) $coach_members as $cm) {
        $members[] = (object) [
            'id' => (int) $cm['member_id'],
            'first_name' => '',
            'last_name' => trim((string) ($cm['name'] ?? '')),
            'national_id' => (string) ($cm['national_id'] ?? ''),
        ];
    }
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.title FROM {$wpdb->prefix}sc_courses c
         INNER JOIN {$wpdb->prefix}sc_course_coaches cc ON cc.course_id = c.id
         WHERE cc.coach_id = %d AND c.deleted_at IS NULL AND c.is_active = 1 ORDER BY c.title",
        $coach_id
    ));
    $events = [];
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name, first_name");
    $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
    $events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");
}
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");
?>
<div class="wrap sc-private-notes-wrap">
    <h1><?php echo esc_html($title_page); ?></h1>
    <a href="<?php echo esc_url($list_url); ?>" class="button">بازگشت به لیست</a>
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <form method="post" class="sc-private-note-form">
        <?php wp_nonce_field('sc_save_private_note_action', 'sc_save_private_note_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="sc-private-note-title">عنوان</label></th>
                <td><input type="text" id="sc-private-note-title" name="title" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="sc-private-note-content">متن یادداشت</label></th>
                <td><textarea id="sc-private-note-content" name="content" rows="6" class="large-text" required></textarea></td>
            </tr>
            <tr>
                <th>پیوست</th>
                <td>
                    <div class="sc-ticket-attachment-zone" data-input-name="private_note_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_private_note_upload_attachment')); ?>" data-action="sc_upload_private_note_attachment" data-nonce-key="sc_private_note_upload_nonce">
                        <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                            <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
                            <span class="sc-file-upload-icon">📎</span>
                            <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                            <span class="sc-file-upload-hint">حداکثر ۵ فایل، هرکدام ۵ مگابایت</span>
                        </div>
                        <div class="sc-ticket-upload-progress-wrap" style="display:none;"><div class="sc-upload-progress sc-ticket-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div></div>
                        <div class="sc-ticket-uploaded-list"></div>
                        <div class="sc-ticket-attachment-ids-hidden"></div>
                    </div>
                </td>
            </tr>
            <tr>
                <th>نوع انتخاب کاربران</th>
                <td>
                    <select name="target_type" id="sc-private-target-type">
                        <option value="specific">کاربران خاص</option>
                        <option value="all">همه کاربران</option>
                        <option value="course">بر اساس دوره</option>
                        <?php if (!$is_coach) : ?>
                            <option value="event">بر اساس رویداد</option>
                        <?php endif; ?>
                        <option value="team">بر اساس تیم</option>
                        <option value="level">بر اساس سطح</option>
                        <option value="team_level">بر اساس تیم + سطح</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>فیلتر وضعیت/نوع بازیکن</th>
                <td>
                    <select name="member_status">
                        <option value="all">همه وضعیت‌ها</option>
                        <option value="active">فعال</option>
                        <option value="inactive">غیرفعال</option>
                    </select>
                    <select name="member_type">
                        <option value="all">همه نوع‌ها</option>
                        <option value="normal">بازیکن عادی</option>
                        <option value="team">بازیکن تیم</option>
                    </select>
                </td>
            </tr>
            <tr class="sc-target-row" id="sc-target-specific">
                <th>کاربران</th>
                <td>
                    <select name="member_ids[]" multiple size="8" style="min-width:360px;">
                        <?php foreach ((array) $members as $member) : ?>
                            <option value="<?php echo (int) $member->id; ?>">
                                <?php
                                $full_name = trim((string) ($member->first_name ?? '') . ' ' . (string) ($member->last_name ?? ''));
                                echo esc_html(($full_name !== '' ? $full_name : 'کاربر #' . (int) $member->id) . ' - ' . ($member->national_id ?: '-'));
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="sc-target-row" id="sc-target-course" style="display:none;">
                <th>دوره‌ها</th>
                <td><select name="course_ids[]" multiple size="6" style="min-width:360px;"><?php foreach ((array) $courses as $c) : ?><option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->title); ?></option><?php endforeach; ?></select></td>
            </tr>
            <?php if (!$is_coach) : ?>
            <tr class="sc-target-row" id="sc-target-event" style="display:none;">
                <th>رویدادها</th>
                <td><select name="event_ids[]" multiple size="6" style="min-width:360px;"><?php foreach ((array) $events as $e) : ?><option value="<?php echo (int) $e->id; ?>"><?php echo esc_html($e->name); ?></option><?php endforeach; ?></select></td>
            </tr>
            <?php endif; ?>
            <tr class="sc-target-row" id="sc-target-team" style="display:none;">
                <th>تیم‌ها</th>
                <td><select name="team_names[]" multiple size="6" style="min-width:360px;"><?php foreach ((array) $teams as $t) : ?><option value="<?php echo esc_attr($t->name); ?>"><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select></td>
            </tr>
            <tr class="sc-target-row" id="sc-target-level" style="display:none;">
                <th>سطح‌ها</th>
                <td><select name="level_names[]" multiple size="6" style="min-width:360px;"><?php foreach ((array) $levels as $l) : ?><option value="<?php echo esc_attr($l->name); ?>"><?php echo esc_html($l->name); ?></option><?php endforeach; ?></select></td>
            </tr>
            <tr class="sc-target-row" id="sc-target-team_level" style="display:none;">
                <th>تیم + سطح</th>
                <td>
                    <select name="team_names[]" multiple size="6" style="min-width:260px;"><?php foreach ((array) $teams as $t) : ?><option value="<?php echo esc_attr($t->name); ?>"><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select>
                    <select name="level_names[]" multiple size="6" style="min-width:260px;"><?php foreach ((array) $levels as $l) : ?><option value="<?php echo esc_attr($l->name); ?>"><?php echo esc_html($l->name); ?></option><?php endforeach; ?></select>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="sc_save_private_note" class="button button-primary">ثبت یادداشت خصوصی</button>
        </p>
    </form>
</div>
<script>
jQuery(function($){
    function toggleRows(){
        var t = $('#sc-private-target-type').val();
        $('.sc-target-row').hide();
        $('#sc-target-' + t).show();
    }
    $('#sc-private-target-type').on('change', toggleRows);
    toggleRows();
});
</script>
