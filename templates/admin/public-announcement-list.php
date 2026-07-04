<?php
if (!defined('ABSPATH')) exit;

$option_name = 'sc_public_announcements';
$announcements = get_option($option_name, []);
if (!is_array($announcements)) {
    $announcements = [];
}
$active_id = (int) get_option('sc_active_public_announcement_id', 0);
$add_url = admin_url('admin.php?page=sc-public-announcement-add');

if (isset($_POST['sc_pa_action']) && check_admin_referer('sc_pa_nonce')) {
    $action = sanitize_text_field(wp_unslash($_POST['sc_pa_action']));
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $announcements = array_filter($announcements, function ($a) use ($id) {
            return (int) $a['id'] !== $id;
        });
        update_option($option_name, array_values($announcements));
        if ($active_id === $id) {
            update_option('sc_active_public_announcement_id', 0);
            $active_id = 0;
        }
        echo '<div class="notice notice-success is-dismissible"><p>اطلاعیه حذف شد.</p></div>';
        $announcements = get_option($option_name, []);
        if (!is_array($announcements)) {
            $announcements = [];
        }
        $active_id = (int) get_option('sc_active_public_announcement_id', 0);
    }
}
?>
<div class="wrap sc-pa-list-wrap">
    <div class="sc-pa-list-header">
        <div class="sc-pa-list-header-text">
            <h1 class="sc-pa-list-title">اطلاعیه‌های عمومی</h1>
            <p class="sc-pa-list-desc">این اطلاعیه در بالای هدر تمام صفحات سایت و پنل کاربری نمایش داده می‌شود.</p>
        </div>
        <div class="sc-pa-list-header-actions">
            <a href="<?php echo esc_url($add_url); ?>" class="sc-pa-list-add-btn">افزودن اطلاعیه جدید</a>
        </div>
    </div>

    <div class="sc-pa-active-card">
        <h2 class="sc-pa-card-title">اطلاعیه فعال فعلی</h2>
        <?php if (empty($announcements)) : ?>
            <p class="sc-pa-empty-inline">هیچ اطلاعیه‌ای ثبت نشده است. <a href="<?php echo esc_url($add_url); ?>">اولین اطلاعیه را بسازید</a>.</p>
        <?php else : ?>
            <div class="sc-pa-active-row">
                <select id="sc-active-announcement" class="sc-pa-active-select">
                    <option value="0">— هیچ‌کدام (غیرفعال) —</option>
                    <?php foreach ($announcements as $a) : ?>
                        <option value="<?php echo (int) $a['id']; ?>" <?php selected($active_id, (int) $a['id']); ?>>
                            <?php echo esc_html($a['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="sc-save-active-btn" class="button button-primary">ذخیره فعال</button>
                <span id="sc-active-status" class="sc-pa-active-status" style="display:none;"></span>
            </div>
            <p class="description">فقط یک اطلاعیه می‌تواند فعال باشد. با تغییر آن، فوراً در سایت اعمال می‌شود.</p>
        <?php endif; ?>
    </div>

    <div class="sc-pa-list-table-card">
        <div class="sc-pa-list-summary"><span><?php echo (int) count($announcements); ?> اطلاعیه</span></div>
        <div class="sc-pa-table-scroll">
            <table class="wp-list-table widefat striped sc-pa-table">
                <thead>
                    <tr>
                        <th class="manage-column">عنوان</th>
                        <th class="manage-column">پیش‌نمایش متن</th>
                        <th class="manage-column">رنگ‌ها</th>
                        <th class="manage-column">ارتفاع / متحرک / جلوه</th>
                        <th class="manage-column">قابل بستن</th>
                        <th class="manage-column">وضعیت</th>
                        <th class="manage-column">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($announcements)) : ?>
                    <tr><td colspan="7" class="sc-pa-empty">هیچ اطلاعیه‌ای ثبت نشده.</td></tr>
                <?php else : ?>
                    <?php foreach ($announcements as $a) :
                        $is_active = ((int) $a['id'] === $active_id);
                        $effects = isset($a['effects']) ? (array) $a['effects'] : [];
                        $edit_url = admin_url('admin.php?page=sc-public-announcement-add&id=' . (int) $a['id']);
                        ?>
                    <tr>
                        <td data-label="عنوان"><strong><?php echo esc_html($a['title']); ?></strong></td>
                        <td data-label="پیش‌نمایش"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($a['content'] ?? ''), 8, '...')); ?></td>
                        <td data-label="رنگ‌ها">
                            <span class="sc-pa-color-sample" style="background:<?php echo esc_attr($a['bg_color'] ?? '#6D34FF'); ?>; color:<?php echo esc_attr($a['text_color'] ?? '#fff'); ?>;">نمونه رنگ</span>
                        </td>
                        <td data-label="تنظیمات">
                            <?php echo esc_html($a['height'] ?? 'auto'); ?>
                            <?php if (!empty($a['marquee'])) : ?> <span class="dashicons dashicons-controls-repeat" title="متحرک"></span><?php endif; ?>
                            <?php if (!empty($effects)) : ?> <span class="dashicons dashicons-star-filled" title="<?php echo esc_attr(implode(', ', $effects)); ?>"></span><?php endif; ?>
                        </td>
                        <td data-label="قابل بستن">
                            <?php if (!empty($a['dismissable'])) : ?>
                                <span class="sc-badge sc-badge--success">بله</span>
                            <?php else : ?>
                                <span class="sc-badge sc-badge--muted">خیر</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="وضعیت">
                            <?php if ($is_active) : ?>
                                <span class="sc-badge sc-badge--success">فعال</span>
                            <?php else : ?>
                                <span class="sc-badge sc-badge--soft">غیرفعال</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="عملیات" class="sc-pa-actions">
                            <a href="<?php echo esc_url($edit_url); ?>" class="sc-pa-action-link">ویرایش</a>
                            <form method="post" class="sc-pa-delete-form" onsubmit="return confirm('حذف شود؟');">
                                <?php wp_nonce_field('sc_pa_nonce'); ?>
                                <input type="hidden" name="sc_pa_action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                <button type="submit" class="sc-pa-action-delete">حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($){
    var $select = $('#sc-active-announcement');
    var $btn = $('#sc-save-active-btn');
    var $status = $('#sc-active-status');
    if (!$btn.length) return;

    var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    $btn.on('click', function(){
        var newId = parseInt($select.val(), 10) || 0;
        $btn.prop('disabled', true);
        $status.text('در حال ذخیره...').show().css('color', '#6d34ff');

        $.post(ajaxUrl, {
            action: 'sc_set_active_public_announcement',
            nonce: '<?php echo esc_js(wp_create_nonce('sc_pa_ajax')); ?>',
            id: newId
        }).done(function(resp){
            if (resp && resp.success) {
                $status.text('✓ ' + resp.data.message).css('color', '#059669');
                window.setTimeout(function(){ window.location.reload(); }, 800);
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'خطا در ذخیره';
                $status.text('✕ ' + msg).css('color', '#dc2626');
            }
            $btn.prop('disabled', false);
        }).fail(function(xhr){
            $status.text('✕ خطا در ارتباط (' + xhr.status + ')').css('color', '#dc2626');
            $btn.prop('disabled', false);
        });
    });
});
</script>
