<?php
if (!defined('ABSPATH')) exit;

$saved = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_tarddod_save_session'])) {
    check_admin_referer('sc_tarddod_session_save');
    $date_shamsi = sanitize_text_field(wp_unslash($_POST['session_date_shamsi'] ?? ''));
    $date_greg = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($date_shamsi) : sanitize_text_field(wp_unslash($_POST['session_date'] ?? ''));
    if ($date_greg === '') {
        $date_greg = current_time('Y-m-d');
    }
    $session_id = absint($_POST['session_id'] ?? 0);
    $data = [
        'title'        => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'description'  => wp_unslash($_POST['description'] ?? ''),
        'session_date' => $date_greg,
        'session_time' => sanitize_text_field(wp_unslash($_POST['session_time'] ?? '')),
        'location'     => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
        'notes'        => wp_unslash($_POST['notes'] ?? ''),
        'status'       => sanitize_text_field(wp_unslash($_POST['status'] ?? 'open')),
    ];
    if ($session_id > 0) {
        $saved = sc_tarddod_update_session($session_id, $data);
    } else {
        $saved = (bool) sc_tarddod_create_session($data);
    }
    if (!$saved) {
        $error = 'ذخیره جلسه انجام نشد. عنوان و تاریخ را بررسی کنید.';
    }
}

$edit_id = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;
$edit = $edit_id ? sc_tarddod_get_session($edit_id) : null;
$today_shamsi = function_exists('sc_date_shamsi') ? sc_date_shamsi(current_time('Y-m-d'), 'Y/m/d') : '';
$date_shamsi_val = $edit && function_exists('sc_date_shamsi') ? sc_date_shamsi($edit->session_date, 'Y/m/d') : $today_shamsi;
?>
<div class="wrap sc-members-list-wrap sc-tarddod-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title"><?php echo $edit ? 'ویرایش جلسه تردد' : 'افزودن جلسه تردد'; ?></h1>
            <p class="sc-members-list-desc">تعریف جلسه با عنوان، تاریخ، ساعت و توضیحات — سپس از «ثبت تردد» QRها را اسکن کنید.</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-sessions')); ?>" class="button">بازگشت به جلسات</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-register')); ?>" class="button button-primary sc-members-list-add-btn">ثبت تردد</a>
        </div>
    </div>

    <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p>جلسه با موفقیت ذخیره شد.</p></div><?php endif; ?>
    <?php if ($error !== '') : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <div class="sc-members-list-table-card sc-tarddod-form-card">
        <form method="post" class="sc-tarddod-session-form">
            <?php wp_nonce_field('sc_tarddod_session_save'); ?>
            <input type="hidden" name="sc_tarddod_save_session" value="1">
            <?php if ($edit_id) : ?><input type="hidden" name="session_id" value="<?php echo esc_attr((string) $edit_id); ?>"><?php endif; ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tarddod_title">عنوان جلسه *</label></th>
                    <td><input type="text" name="title" id="tarddod_title" class="regular-text sc-filter-control" required value="<?php echo esc_attr($edit->title ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tarddod_description">توضیحات</label></th>
                    <td><textarea name="description" id="tarddod_description" class="large-text sc-filter-control" rows="3"><?php echo esc_textarea($edit->description ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="session_date_shamsi">تاریخ (شمسی) *</label></th>
                    <td>
                        <input type="text" name="session_date_shamsi" id="session_date_shamsi" class="regular-text sc-filter-control persian-date-input" value="<?php echo esc_attr($date_shamsi_val); ?>" required readonly>
                        <input type="hidden" name="session_date" id="session_date" value="<?php echo esc_attr($edit->session_date ?? current_time('Y-m-d')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tarddod_time">ساعت</label></th>
                    <td><input type="time" name="session_time" id="tarddod_time" class="sc-filter-control" value="<?php echo esc_attr(isset($edit->session_time) ? substr((string) $edit->session_time, 0, 5) : ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tarddod_location">مکان</label></th>
                    <td><input type="text" name="location" id="tarddod_location" class="regular-text sc-filter-control" value="<?php echo esc_attr($edit->location ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tarddod_notes">یادداشت</label></th>
                    <td><textarea name="notes" id="tarddod_notes" class="large-text sc-filter-control" rows="2"><?php echo esc_textarea($edit->notes ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tarddod_status">وضعیت</label></th>
                    <td>
                        <select name="status" id="tarddod_status" class="sc-filter-control">
                            <?php foreach (['draft' => 'پیش‌نویس', 'open' => 'باز (قابل اسکن)', 'closed' => 'بسته'] as $k => $lbl) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($edit->status ?? 'open', $k); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">ذخیره جلسه</button></p>
        </form>
    </div>
</div>
