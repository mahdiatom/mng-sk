<?php
if (!defined('ABSPATH')) exit;

$option_name = 'sc_public_announcements';
$announcements = get_option($option_name, []);
if (!is_array($announcements)) $announcements = [];
$active_id = (int) get_option('sc_active_public_announcement_id', 0);

// Handle non-AJAX actions (delete)
if (isset($_POST['sc_pa_action']) && check_admin_referer('sc_pa_nonce')) {
    $action = sanitize_text_field($_POST['sc_pa_action']);
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $announcements = array_filter($announcements, function($a) use ($id) { return (int)$a['id'] !== $id; });
        update_option($option_name, array_values($announcements));
        if ($active_id === $id) {
            update_option('sc_active_public_announcement_id', 0);
            $active_id = 0;
        }
        echo '<div class="updated"><p>اطلاعیه حذف شد.</p></div>';
        $announcements = get_option($option_name, []);
        $active_id = (int) get_option('sc_active_public_announcement_id', 0);
    }
}

// AJAX handler is registered below
?>
<div class="wrap">
    <h1>اطلاعیه‌های عمومی</h1>
    <p>این اطلاعیه در بالای هدر تمام صفحات سایت و پنل کاربری نمایش داده می‌شود.</p>

    <!-- Active Announcement Section with AJAX -->
    <div class="sc-pa-active-box" style="background:#f8f9ff; border:1px solid #e0e7ff; padding:16px; border-radius:12px; margin-bottom:20px;">
        <h2 style="margin-top:0;">اطلاعیه فعال فعلی</h2>
        <?php if (empty($announcements)): ?>
            <p>هیچ اطلاعیه‌ای ثبت نشده است. <a href="<?php echo admin_url('admin.php?page=sc-public-announcement-add'); ?>">اولین اطلاعیه را بسازید</a>.</p>
        <?php else: ?>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <select id="sc-active-announcement" style="min-width:280px; padding:8px;">
                    <option value="0">— هیچ‌کدام (غیرفعال) —</option>
                    <?php foreach ($announcements as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>" <?php selected($active_id, (int)$a['id']); ?>>
                            <?php echo esc_html($a['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="sc-save-active-btn" class="button button-primary">ذخیره فعال</button>
                <span id="sc-active-status" style="color:#2271b1; font-weight:600; display:none;"></span>
            </div>
            <p class="description">فقط یک اطلاعیه می‌تواند فعال باشد. با تغییر آن، فوراً در سایت اعمال می‌شود.</p>
        <?php endif; ?>
    </div>

    <h2 style="display:flex; align-items:center; justify-content:space-between;">
        لیست اطلاعیه‌ها
        <a href="<?php echo admin_url('admin.php?page=sc-public-announcement-add'); ?>" class="button button-primary">افزودن اطلاعیه جدید</a>
    </h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>عنوان</th>
                <th>پیش‌نمایش متن</th>
                <th>رنگ‌ها</th>
                <th>ارتفاع / متحرک / جلوه</th>
                <th>قابل بستن</th>
                <th>وضعیت</th>
                <th style="width:180px;">عملیات</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($announcements)): ?>
            <tr><td colspan="7">هیچ اطلاعیه‌ای ثبت نشده.</td></tr>
        <?php else: ?>
            <?php foreach ($announcements as $a): 
                $is_active = ((int)$a['id'] === $active_id);
                $effects = isset($a['effects']) ? (array)$a['effects'] : [];
            ?>
            <tr>
                <td><strong><?php echo esc_html($a['title']); ?></strong></td>
                <td><?php echo wp_trim_words(strip_tags($a['content'] ?? ''), 8, '...'); ?></td>
                <td>
                    <span style="background:<?php echo esc_attr($a['bg_color'] ?? '#6D34FF'); ?>; color:<?php echo esc_attr($a['text_color'] ?? '#fff'); ?>; padding:3px 10px; border-radius:4px; font-size:12px;">نمونه رنگ</span>
                </td>
                <td>
                    <?php echo esc_html($a['height'] ?? 'auto'); ?> 
                    <?php if (!empty($a['marquee'])): ?> <span class="dashicons dashicons-controls-repeat" title="متحرک"></span><?php endif; ?>
                    <?php if (!empty($effects)): ?> <span class="dashicons dashicons-star-filled" title="<?php echo esc_attr(implode(', ', $effects)); ?>"></span><?php endif; ?>
                </td>
                <td><?php echo !empty($a['dismissable']) ? '✓' : '—'; ?></td>
                <td><?php echo $is_active ? '<span style="color:green; font-weight:700;">فعال</span>' : '<span style="color:#666;">غیرفعال</span>'; ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=sc-public-announcement-add&id=' . (int)$a['id']); ?>" class="button button-small">ویرایش</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                        <?php wp_nonce_field('sc_pa_nonce'); ?>
                        <input type="hidden" name="sc_pa_action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                        <button type="submit" class="button button-small button-link-delete">حذف</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($){
    var $select = $('#sc-active-announcement');
    var $btn = $('#sc-save-active-btn');
    var $status = $('#sc-active-status');

    var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';

    $btn.on('click', function(){
        var newId = parseInt($select.val()) || 0;
        $btn.prop('disabled', true);
        $status.text('در حال ذخیره...').show().css('color', '#2271b1');

        $.post(ajaxUrl, {
            action: 'sc_set_active_public_announcement',
            nonce: '<?php echo wp_create_nonce('sc_pa_ajax'); ?>',
            id: newId
        }).done(function(resp){
            if (resp && resp.success) {
                $status.text('✓ ' + resp.data.message).css('color', '#00a32a');
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'خطا در ذخیره';
                $status.text('✕ ' + msg).css('color', '#d63638');
            }
            setTimeout(function(){ $status.fadeOut(400); }, 2600);
            $btn.prop('disabled', false);
        }).fail(function(xhr){
            $status.text('✕ خطا در ارتباط (' + xhr.status + ')').css('color', '#d63638');
            $btn.prop('disabled', false);
        });
    });
});
</script>

<?php
?>