<?php
if (!defined('ABSPATH')) exit;

$option_name = 'sc_public_announcements';
$announcements = get_option($option_name, []);
if (!is_array($announcements)) $announcements = [];

$is_edit = false;
$edit_item = null;
if (isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($announcements as $a) {
        if ((int)$a['id'] === $edit_id) {
            $edit_item = $a;
            $is_edit = true;
            break;
        }
    }
}

if (isset($_POST['sc_pa_action']) && check_admin_referer('sc_pa_nonce')) {
    $action = sanitize_text_field($_POST['sc_pa_action']);
    if ($action === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $bg_color = sanitize_hex_color($_POST['bg_color'] ?? '#6D34FF');
        $text_color = sanitize_hex_color($_POST['text_color'] ?? '#FFFFFF');
        $bg_image = esc_url_raw($_POST['bg_image'] ?? '');
        $height = isset($_POST['height']) && $_POST['height'] !== '' ? (int)$_POST['height'] : 'auto';
        $dismissable = !empty($_POST['dismissable']);
        $marquee = !empty($_POST['marquee']);
        $marquee_speed = isset($_POST['marquee_speed']) ? max(5, min(60, (int)$_POST['marquee_speed'])) : 18;
        $effects = isset($_POST['effects']) && is_array($_POST['effects']) ? array_map('sanitize_key', $_POST['effects']) : [];

        $item = [
            'id' => $id ?: time(),
            'title' => $title,
            'content' => $content,
            'bg_color' => $bg_color,
            'text_color' => $text_color,
            'bg_image' => $bg_image,
            'height' => $height,
            'dismissable' => $dismissable,
            'marquee' => $marquee,
            'marquee_speed' => $marquee_speed,
            'effects' => $effects,
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            foreach ($announcements as &$a) {
                if ((int)$a['id'] === $id) {
                    $a = array_merge($a, $item);
                    break;
                }
            }
        } else {
            $item['created_at'] = current_time('mysql');
            $announcements[] = $item;
        }

        update_option($option_name, $announcements);
        echo '<div class="updated"><p>' . ($id ? 'اطلاعیه ویرایش شد.' : 'اطلاعیه ثبت شد.') . ' <a href="' . admin_url('admin.php?page=sc-public-announcement') . '">بازگشت به لیست</a></p></div>';
        // refresh
        $announcements = get_option($option_name, []);
        if ($is_edit) {
            foreach ($announcements as $a) if ((int)$a['id'] === $id) { $edit_item = $a; break; }
        }
    }
}

$effects_options = [
    'pulse' => 'پالس (بزرگ/کوچک شدن)',
    'glow' => 'درخشش (Glow)',
    'shine' => 'نور متحرک (Shine)',
    'bounce' => 'پرش',
    'flash' => 'چشمک زدن',
    'swing' => 'تاب خوردن',
];
?>

<style>
/* استایل مدرن مثل ویرایش بازیکن و رویداد */
body[class*="sc-public-announcement"] .wrap {
    background: #f4f2fa;
    border-radius: 12px;
    padding: 20px 24px;
    box-sizing: border-box;
}
body[class*="sc-public-announcement"] .wrap > h1 {
    color: #1e1b2e;
    font-weight: 800;
}
body[class*="sc-public-announcement"] form .form-table {
    display: block;
    width: 100%;
    margin-top: 16px;
}
body[class*="sc-public-announcement"] form .form-table > tbody {
    display: grid;
    gap: 14px;
}
body[class*="sc-public-announcement"] form .form-table > tbody > tr {
    display: block;
    margin: 0;
    padding: 18px 20px;
    border-radius: 14px;
    background: linear-gradient(145deg, #ffffff 0%, #f8f7ff 55%, #f3f0ff 100%);
    border: 1px solid rgba(109, 52, 255, 0.12);
    box-shadow: 0 8px 28px rgba(74, 31, 184, 0.07), 0 2px 8px rgba(15, 23, 42, 0.04);
}
body[class*="sc-public-announcement"] form .form-table > tbody > tr > th,
body[class*="sc-public-announcement"] form .form-table > tbody > tr > td {
    display: block;
    width: 100% !important;
    padding: 0 0 6px 0;
    border: none;
    background: transparent;
}
body[class*="sc-public-announcement"] form .form-table > tbody > tr > th {
    font-weight: 700;
    color: #2f2a44;
    padding-bottom: 8px;
}
body[class*="sc-public-announcement"] .submit {
    margin-top: 20px;
}
</style>

<div class="wrap">
    <h1><?php echo $is_edit ? 'ویرایش اطلاعیه' : 'افزودن اطلاعیه عمومی'; ?></h1>
    <p>این اطلاعیه بالای هدر سایت و پنل کاربری نمایش داده می‌شود.</p>

    <form method="post" id="sc-pa-form">
        <?php wp_nonce_field('sc_pa_nonce'); ?>
        <input type="hidden" name="sc_pa_action" value="save">
        <input type="hidden" name="id" value="<?php echo $is_edit ? (int)$edit_item['id'] : ''; ?>">

        <table class="form-table">
            <tr>
                <th><label for="title">عنوان (فقط برای مدیریت)</label></th>
                <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo $is_edit ? esc_attr($edit_item['title']) : ''; ?>" required></td>
            </tr>
            <tr>
                <th><label for="content">متن اطلاعیه (نمایش به کاربر)</label></th>
                <td>
                    <?php 
                    $content_val = $is_edit ? ($edit_item['content'] ?? '') : '';
                    wp_editor($content_val, 'content', [
                        'textarea_name' => 'content',
                        'media_buttons' => false,
                        'textarea_rows' => 5,
                        'tinymce' => ['toolbar1' => 'bold italic underline | alignleft aligncenter alignright | bullist numlist']
                    ]); 
                    ?>
                    <p class="description">فقط این متن در بالای هدر به کاربر نشان داده می‌شود (عنوان نمایش داده نمی‌شود).</p>
                </td>
            </tr>
            <tr>
                <th>رنگ پس‌زمینه</th>
                <td><input type="color" name="bg_color" value="<?php echo $is_edit ? esc_attr($edit_item['bg_color']) : '#6D34FF'; ?>"></td>
            </tr>
            <tr>
                <th>رنگ متن</th>
                <td><input type="color" name="text_color" value="<?php echo $is_edit ? esc_attr($edit_item['text_color']) : '#FFFFFF'; ?>"></td>
            </tr>
            <tr>
                <th>تصویر پس‌زمینه (اختیاری)</th>
                <td>
                    <input type="text" name="bg_image" class="regular-text sc-pa-image-url" value="<?php echo $is_edit ? esc_attr($edit_item['bg_image'] ?? '') : ''; ?>" placeholder="https://...">
                    <button type="button" class="button sc-pa-upload-btn">انتخاب تصویر</button>
                    <p class="description">اگر انتخاب شود، به صورت cover نمایش داده می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th>ارتفاع (px)</th>
                <td>
                    <input type="number" name="height" min="40" step="5" value="<?php echo ($is_edit && $edit_item['height'] !== 'auto') ? (int)$edit_item['height'] : ''; ?>" placeholder="خالی = خودکار (auto)">
                    <p class="description">اگر عدد وارد کنید، حداقل ارتفاع به پیکسل اعمال می‌شود. پیش‌فرض: auto (متناسب با متن)</p>
                </td>
            </tr>
            <tr>
                <th>سرعت حرکت نوار اخبار (ثانیه)</th>
                <td>
                    <input type="number" name="marquee_speed" min="5" max="60" step="1" value="<?php echo $is_edit ? (int)($edit_item['marquee_speed'] ?? 18) : 18; ?>">
                    <p class="description">مدت زمان یک دور کامل اسکرول (پیش‌فرض ۱۸ ثانیه). فقط وقتی تیک «متحرک» فعال باشد اعمال می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th>تنظیمات نمایش</th>
                <td>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="dismissable" value="1" <?php echo ($is_edit && !empty($edit_item['dismissable'])) ? 'checked' : ''; ?>>
                        اجازه بستن توسط کاربر (دکمه × نمایش داده شود)
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="marquee" value="1" <?php echo ($is_edit && !empty($edit_item['marquee'])) ? 'checked' : ''; ?>>
                        متحرک (نوار اخبار - متن به صورت اسکرول رد شود)
                    </label>
                </td>
            </tr>
            <tr>
                <th>جلوه‌های ویژه متن</th>
                <td>
                    <div style="display:flex; flex-wrap:wrap; gap:12px;">
                        <?php 
                        $current_effects = $is_edit && isset($edit_item['effects']) ? (array)$edit_item['effects'] : [];
                        foreach ($effects_options as $key => $label): 
                            $checked = in_array($key, $current_effects, true) ? 'checked' : '';
                        ?>
                            <label style="min-width:180px;">
                                <input type="checkbox" name="effects[]" value="<?php echo esc_attr($key); ?>" <?php echo $checked; ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">جلوه‌ها را انتخاب کنید تا متن جلب توجه کند (چند مورد قابل انتخاب است).</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php echo $is_edit ? 'ویرایش اطلاعیه' : 'ثبت اطلاعیه'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=sc-public-announcement'); ?>" class="button button-large">انصراف و بازگشت به لیست</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($){
    // Media uploader
    $('.sc-pa-upload-btn').on('click', function(){
        var frame = wp.media({
            title: 'انتخاب تصویر پس‌زمینه',
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $('input[name="bg_image"]').val(attachment.url);
        });
        frame.open();
    });
});
</script>