<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

sc_check_and_create_tables();

global $wpdb;
$honors_table = $wpdb->prefix . 'sc_honors';
$categories_table = $wpdb->prefix . 'sc_honor_categories';
$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';

// پردازش فرم افزودن افتخار
$message = '';
$message_type = '';

if (isset($_POST['save_honor']) && check_admin_referer('save_honor_for_member_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $honor_name = isset($_POST['honor_name']) ? sanitize_text_field($_POST['honor_name']) : '';
    $honor_category = isset($_POST['honor_category']) ? absint($_POST['honor_category']) : 0;
    $honor_coach_id = isset($_POST['honor_coach_id']) ? absint($_POST['honor_coach_id']) : 0;
    $honor_description = isset($_POST['honor_description']) ? sanitize_textarea_field($_POST['honor_description']) : '';
    
    if (empty($member_id)) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif (empty($honor_name)) {
        $message = 'لطفاً عنوان افتخار را وارد کنید.';
        $message_type = 'error';
    } elseif (empty($honor_category)) {
        $message = 'لطفاً دسته را انتخاب کنید.';
        $message_type = 'error';
    } else {
        // بررسی وجود دسته
        $category_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE id = %d",
            $honor_category
        ));
        
        if (!$category_exists) {
            $message = 'دسته انتخاب شده معتبر نیست.';
            $message_type = 'error';
        } else {
            // پردازش فایل انتخابی با آپلودر مشترک (هم‌ساختار تیکت)
            $file_url = null;
            $uploaded_attachment_ids = [];
            if (!empty($_POST['honor_attachment_ids']) && function_exists('sc_honor_validate_attachment_ids')) {
                $raw_attachment_ids = is_array($_POST['honor_attachment_ids']) ? $_POST['honor_attachment_ids'] : explode(',', (string) $_POST['honor_attachment_ids']);
                $uploaded_attachment_ids = sc_honor_validate_attachment_ids($raw_attachment_ids, 1);
            }
            if (!empty($uploaded_attachment_ids)) {
                $file_url = wp_get_attachment_url((int) $uploaded_attachment_ids[0]);
            }
            
            if ($message_type !== 'error') {
                // ذخیره افتخار
                $coach_id_to_save = ($honor_coach_id > 0) ? $honor_coach_id : null;
                $inserted = $wpdb->insert(
                    $honors_table,
                    [
                        'member_id' => $member_id,
                        'coach_id' => $coach_id_to_save,
                        'name' => $honor_name,
                        'category_id' => $honor_category,
                        'description' => $honor_description ?: null,
                        'file_url' => $file_url,
                        'status' => 'approved',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
                );
                
                if ($inserted) {
                    $honor_id = $wpdb->insert_id;
                    if (function_exists('sc_log_activity')) {
                        sc_log_activity('created', 'honor', $honor_id, 'افتخار «' . $honor_name . '» برای عضو ' . $member_id . ' افزوده شد', null, ['name' => $honor_name, 'member_id' => $member_id, 'category_id' => $honor_category]);
                    }
                    $message = 'افتخار با موفقیت افزوده شد.';
                    $message_type = 'success';
                    // پاک کردن فرم
                    $_POST = [];
                } else {
                    $message = 'خطا در افزودن افتخار.';
                    $message_type = 'error';
                }
            }
        }
    }
}

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");

// دریافت لیست بازیکنان (با national_id برای دراپ‌داون با جستجو)
$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// دریافت لیست مربی‌های فعال برای فیلد مربی افتخار
$coaches = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

?>
<div class="wrap">
    <h1>افزودن افتخار برای بازیکن</h1>
    
    <div class="card sc-honor-add-card">
        
        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" class="sc-honor-add-form">
            <?php wp_nonce_field('save_honor_for_member_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="member_id">بازیکن <span class="required">*</span></label></th>
                        <td>
                            <?php
                            $selected_member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
                            $selected_member_text = 'انتخاب بازیکن';
                            if ($selected_member_id > 0) {
                                foreach ($members as $m) {
                                    if ($m->id == $selected_member_id) {
                                        $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . ($m->national_id ? $m->national_id : $m->id);
                                        break;
                                    }
                                }
                            }
                            ?>
                            <div class="sc-searchable-dropdown sc-honor-member-dropdown">
                                <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>" required>
                                <div class="sc-dropdown-toggle">
                                    <span class="sc-dropdown-placeholder" <?php if ($selected_member_id) echo 'style="display:none"'; ?>>انتخاب بازیکن</span>
                                    <span class="sc-dropdown-selected" <?php if (!$selected_member_id) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                                    <span class="sc-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-dropdown-menu">
                                    <div class="sc-dropdown-search">
                                        <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                    </div>
                                    <div class="sc-dropdown-options">
                                        <?php
                                        $display_count = 0;
                                        $max_display = 10;
                                        foreach ($members as $member) :
                                            $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                            $display_count++;
                                            $label = $member->first_name . ' ' . $member->last_name . ' - ' . ($member->national_id ? $member->national_id : $member->id);
                                            $is_selected = ($selected_member_id == $member->id);
                                        ?>
                                            <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?> <?php echo $is_selected ? 'sc-selected' : ''; ?>"
                                                 data-value="<?php echo esc_attr($member->id); ?>"
                                                 data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . ($member->national_id ? $member->national_id : ''))); ?>"
                                                 onclick="scSelectMemberForHonor(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($label); ?>')">
                                                <?php echo esc_html($label); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_name">عنوان افتخار <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="honor_name" id="honor_name" class="regular-text" required value="<?php echo isset($_POST['honor_name']) ? esc_attr($_POST['honor_name']) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_category">دسته <span class="required">*</span></label></th>
                        <td>
                            <select name="honor_category" id="honor_category" class="regular-text" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected(isset($_POST['honor_category']) ? $_POST['honor_category'] : '', $category->id); ?>><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_coach_id">مربی مرتبط <span class="required">*</span></label></th>
                        <td>
                            <?php $selected_coach = isset($_POST['honor_coach_id']) ? absint($_POST['honor_coach_id']) : 0; ?>
                            <select name="honor_coach_id" id="honor_coach_id" class="regular-text" required>
                                <option value="0" <?php selected($selected_coach, 0); ?>>هیچ کدام</option>
                                <?php foreach ($coaches as $coach) : 
                                    $coach_label = trim($coach->first_name . ' ' . $coach->last_name);
                                    if ($coach_label === '') { $coach_label = 'مربی #' . $coach->id; }
                                ?>
                                    <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($selected_coach, $coach->id); ?>><?php echo esc_html($coach_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">مربی‌ای که این افتخار به او مرتبط است (اختیاری، پیش‌فرض: هیچ کدام).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_description">توضیحات</label></th>
                        <td>
                            <textarea name="honor_description" id="honor_description" rows="4"  class="large-text"><?php echo isset($_POST['honor_description']) ? esc_textarea($_POST['honor_description']) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>فایل</label></th>
                        <td>
                            <div class="sc-ticket-attachment-zone sc-honor-attachment-zone"
                                 data-input-name="honor_attachment_ids"
                                 data-nonce="<?php echo esc_attr(wp_create_nonce('sc_honor_upload_attachment')); ?>"
                                 data-action="sc_upload_honor_attachment"
                                 data-nonce-key="sc_honor_upload_nonce"
                                 data-max-files="1"
                                 data-max-size-mb="1"
                                 data-allowed-ext="jpg,jpeg,jpe,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx">
                                <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                                    <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.jpe,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx">
                                    <span class="sc-file-upload-icon">📎</span>
                                    <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                                    <span class="sc-file-upload-hint">حداکثر ۱ فایل، حداکثر ۱ مگابایت. فرمت‌های مجاز: تصویر، PDF، ورد، اکسل</span>
                                </div>
                                <div class="sc-ticket-upload-progress-wrap" style="display:none;">
                                    <div class="sc-upload-progress sc-ticket-upload-progress">
                                        <div class="sc-upload-bar"></div>
                                        <span class="sc-upload-text"></span>
                                    </div>
                                </div>
                                <div class="sc-ticket-uploaded-list"></div>
                                <div class="sc-ticket-attachment-ids-hidden"></div>
                            </div>
                            <p class="description">حداکثر ۱ مگابایت. فرمت‌های مجاز: تصاویر، PDF، Word، Excel</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_honor" id="save_honor" class="button button-primary" value="ذخیره افتخار">
            </p>
        </form>
    </div>
</div>



<script>
jQuery(document).ready(function($) {
    // انتخاب بازیکن (استفاده از تابع مشترک admin.js)
    window.scSelectMemberForHonor = function(element, memberId, memberText) {
        if (typeof scSelectMember === 'function') {
            scSelectMember(element, memberId, memberText);
        }
    };

});
</script>
