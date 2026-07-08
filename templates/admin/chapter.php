<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$categories_table = $wpdb->prefix . 'sc_chapter_categories';

/**
 * @return array<string,mixed>
 */
function sc_chapter_collect_meta_from_post() {
    return [
        'description' => isset($_POST['chapter_description']) ? sanitize_textarea_field(wp_unslash($_POST['chapter_description'])) : '',
        'address' => isset($_POST['chapter_address']) ? sanitize_textarea_field(wp_unslash($_POST['chapter_address'])) : '',
        'phone' => isset($_POST['chapter_phone']) ? sanitize_text_field(wp_unslash($_POST['chapter_phone'])) : '',
        'latitude' => (isset($_POST['chapter_latitude']) && $_POST['chapter_latitude'] !== '') ? (float) $_POST['chapter_latitude'] : null,
        'longitude' => (isset($_POST['chapter_longitude']) && $_POST['chapter_longitude'] !== '') ? (float) $_POST['chapter_longitude'] : null,
        'image' => isset($_POST['chapter_image']) ? esc_url_raw(wp_unslash($_POST['chapter_image'])) : '',
        'sort_order' => isset($_POST['chapter_sort_order']) ? absint($_POST['chapter_sort_order']) : 0,
        'is_active' => isset($_POST['chapter_is_active']) ? 1 : 0,
    ];
}

/**
 * @param object|null $category
 */
function sc_chapter_render_extra_fields($category = null) {
    $description = $category && isset($category->description) ? (string) $category->description : '';
    $address = $category && isset($category->address) ? (string) $category->address : '';
    $phone = $category && isset($category->phone) ? (string) $category->phone : '';
    $latitude = $category && isset($category->latitude) && $category->latitude !== null && $category->latitude !== '' ? (string) $category->latitude : '';
    $longitude = $category && isset($category->longitude) && $category->longitude !== null && $category->longitude !== '' ? (string) $category->longitude : '';
    $image = $category && isset($category->image) ? (string) $category->image : '';
    $sort_order = $category && isset($category->sort_order) ? (int) $category->sort_order : 0;
    $is_active = !$category || !isset($category->is_active) || (int) $category->is_active === 1;
    ?>
    <tr>
        <th scope="row"><label for="chapter_description">توضیحات</label></th>
        <td><textarea id="chapter_description" name="chapter_description" class="large-text" rows="4"><?php echo esc_textarea($description); ?></textarea></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_address">آدرس</label></th>
        <td><textarea id="chapter_address" name="chapter_address" class="large-text" rows="3"><?php echo esc_textarea($address); ?></textarea></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_phone">تلفن</label></th>
        <td><input type="text" id="chapter_phone" name="chapter_phone" class="regular-text" value="<?php echo esc_attr($phone); ?>" dir="ltr"></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_latitude">عرض جغرافیایی</label></th>
        <td><input type="text" id="chapter_latitude" name="chapter_latitude" class="regular-text" value="<?php echo esc_attr($latitude); ?>" dir="ltr" placeholder="35.6892"></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_longitude">طول جغرافیایی</label></th>
        <td><input type="text" id="chapter_longitude" name="chapter_longitude" class="regular-text" value="<?php echo esc_attr($longitude); ?>" dir="ltr" placeholder="51.3890"></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_image">تصویر (URL)</label></th>
        <td><input type="url" id="chapter_image" name="chapter_image" class="regular-text" value="<?php echo esc_attr($image); ?>" dir="ltr"></td>
    </tr>
    <tr>
        <th scope="row"><label for="chapter_sort_order">ترتیب نمایش</label></th>
        <td><input type="number" id="chapter_sort_order" name="chapter_sort_order" class="small-text" value="<?php echo esc_attr((string) $sort_order); ?>" min="0"></td>
    </tr>
    <tr>
        <th scope="row">فعال برای API عمومی</th>
        <td>
            <label>
                <input type="checkbox" name="chapter_is_active" value="1" <?php checked($is_active); ?>>
                نمایش در API عمومی / سایت اصلی
            </label>
        </td>
    </tr>
    <?php
}

$message = '';
$message_type = '';
$editing_category = null;

if (isset($_POST['add_category']) && check_admin_referer('add_chapter_category')) {
    $category_name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';

    if (empty($category_name)) {
        $message = 'لطفاً نام شعبه را وارد کنید.';
        $message_type = 'error';
    } else {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s",
            $category_name
        ));

        if ($existing) {
            $message = 'این شعبه قبلاً ثبت شده است.';
            $message_type = 'error';
        } else {
            $meta = sc_chapter_collect_meta_from_post();
            $now = current_time('mysql');
            $data = [
                'name' => $category_name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $formats = ['%s', '%s', '%s'];

            $has_meta_cols = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$categories_table` LIKE %s", 'description'));
            if (!empty($has_meta_cols)) {
                $data = array_merge($data, $meta);
                $formats = array_merge($formats, ['%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d']);
            }

            $inserted = $wpdb->insert($categories_table, $data, $formats);

            if ($inserted) {
                $message = 'شعبه با موفقیت افزوده شد.';
                $message_type = 'success';
            } else {
                $message = 'خطا در افزودن شعبه.';
                $message_type = 'error';
            }
        }
    }
}

if (isset($_POST['edit_category']) && check_admin_referer('edit_chapter_category')) {
    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';

    if ($category_id && !empty($category_name)) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s AND id != %d",
            $category_name,
            $category_id
        ));

        if ($existing) {
            $message = 'شعبه‌ای با این نام قبلاً وجود دارد.';
            $message_type = 'error';
        } else {
            $meta = sc_chapter_collect_meta_from_post();
            $data = [
                'name' => $category_name,
                'updated_at' => current_time('mysql'),
            ];
            $formats = ['%s', '%s'];

            $has_meta_cols = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$categories_table` LIKE %s", 'description'));
            if (!empty($has_meta_cols)) {
                $data = array_merge($data, $meta);
                $formats = array_merge($formats, ['%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d']);
            }

            $updated = $wpdb->update(
                $categories_table,
                $data,
                ['id' => $category_id],
                $formats,
                ['%d']
            );

            if ($updated !== false) {
                wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=sc_chapter')));
                exit;
            }

            $message = 'خطا در ویرایش شعبه.';
            $message_type = 'error';
        }
    } else {
        $message = 'لطفاً نام شعبه را وارد کنید.';
        $message_type = 'error';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['category_id'])) {
    check_admin_referer('delete_category_' . $_GET['category_id']);

    $category_id = absint($_GET['category_id']);
    $chapter_table = $wpdb->prefix . 'sc_chapter';
    $used = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $chapter_table WHERE category_id = %d",
        $category_id
    ));

    if ($used > 0) {
        $message = 'این شعبه در ' . $used . ' افتخار استفاده شده و قابل حذف نیست.';
        $message_type = 'error';
    } else {
        $deleted = $wpdb->delete($categories_table, ['id' => $category_id], ['%d']);

        if ($deleted) {
            $message = 'شعبه با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'خطا در حذف شعبه.';
            $message_type = 'error';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['category_id'])) {
    $edit_id = absint($_GET['category_id']);
    if ($edit_id) {
        $editing_category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $categories_table WHERE id = %d LIMIT 1",
            $edit_id
        ));
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'شعبه با موفقیت ویرایش شد.';
    $message_type = 'success';
}

$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY sort_order ASC, name ASC");

?>
<div class="wrap">
    <h1>شعبه های باشگاه</h1>

    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sections_cat_chapter">
        <div class="postbox edit_cat_chapter">
            <div class="postbox-header">
                <h2 class=""><?php echo $editing_category ? 'ویرایش شعبه' : 'افزودن شعبه جدید'; ?></h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if ($editing_category) : ?>
                    <form method="post">
                        <?php wp_nonce_field('edit_chapter_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام شعبه <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" value="<?php echo esc_attr($editing_category->name); ?>" required>
                                </td>
                            </tr>
                            <?php sc_chapter_render_extra_fields($editing_category); ?>
                        </table>
                        <p class="submit">
                            <input type="submit" name="edit_category" class="button button-primary" value="ذخیره تغییرات">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_chapter')); ?>" class="button">انصراف</a>
                        </p>
                    </form>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('add_chapter_category'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام شعبه <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" required>
                                </td>
                            </tr>
                            <?php sc_chapter_render_extra_fields(); ?>
                        </table>
                        <p class="submit">
                            <input type="submit" name="add_category" class="button button-primary" value="افزودن شعبه">
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="postbox list_cat_chapter">
            <div class="postbox-header">
                <h2 class="">لیست شعبه ها</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if (!empty($categories)) : ?>
                    <table class="wp-list-table widefat fixed striped" style="border-radius: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 50px; padding:20px;">ردیف</th>
                                <th>نام شعبه</th>
                                <th>آدرس</th>
                                <th style="width: 90px;">وضعیت</th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $category) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td><?php echo esc_html(isset($category->address) ? wp_trim_words((string) $category->address, 12, '…') : ''); ?></td>
                                    <td><?php echo (!isset($category->is_active) || (int) $category->is_active === 1) ? 'فعال' : 'غیرفعال'; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc_chapter&action=edit&category_id=' . $category->id)); ?>"
                                           class="button button-small">ویرایش</a>
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            admin_url('admin.php?page=sc_chapter&action=delete&category_id=' . $category->id),
                                            'delete_category_' . $category->id
                                        );
                                        ?>
                                        <a href="<?php echo esc_url($delete_url); ?>"
                                           onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید؟' })"
                                           class="button button-small btn_delete_action_admin">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>هیچ شعبه‌ای ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
