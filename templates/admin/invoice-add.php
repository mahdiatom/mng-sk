<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';

// دریافت لیست کاربران فعال
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);

// مقادیر پیش‌فرض
$selected_member_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : (isset($_POST['member_id']) ? absint($_POST['member_id']) : 0);
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">ایجاد صورت حساب جدید</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="page-title-action">بازگشت به لیست صورت حساب‌ها</a>
    
    <hr class="wp-header-end">
    
    <form method="POST" action="" style="max-width: 800px;">
        <?php wp_nonce_field('sc_add_invoice', 'sc_invoice_nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="member_id">انتخاب کاربر <span style="color:red;">*</span></label>
                    </th>
                    <td>
                        <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 500px;">
                            <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>" required>
                            <?php
                            $selected_member_text = '';
                            if ($selected_member_id > 0) {
                                foreach ($members as $member) {
                                    if ($member->id == $selected_member_id) {
                                        $selected_member_text = $member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <div class="sc-dropdown-toggle" style="position: relative; cursor: pointer; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 35px 8px 12px; background: #fff; min-height: 30px; display: flex; align-items: center;">
                                <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $selected_member_id > 0 ? 'none' : 'inline'; ?>;">-- انتخاب کاربر --</span>
                                <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $selected_member_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                            </div>
                            <div class="sc-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-top: -1px;">
                                <div class="sc-dropdown-search" style="padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0; background: #fff;">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی..." style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                                </div>
                                <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                    <?php 
                                    $display_count = 0;
                                    $max_display = 10;
                                    $selected_index = -1;
                                    
                                    // پیدا کردن ایندکس کاربر انتخاب شده
                                    foreach ($members as $idx => $member) {
                                        if ($selected_member_id == $member->id) {
                                            $selected_index = $idx;
                                            break;
                                        }
                                    }
                                    
                                    foreach ($members as $idx => $member) : 
                                        $is_selected = ($selected_member_id == $member->id);
                                        // نمایش 10 مورد اول + مورد انتخاب شده (اگر خارج از 10 مورد اول باشد)
                                        $should_display = ($display_count < $max_display) || $is_selected;
                                        $display_class = $should_display ? 'sc-visible' : 'sc-hidden';
                                    ?>
                                        <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                             data-value="<?php echo esc_attr($member->id); ?>"
                                             data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                             style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                             onclick="scSelectMember(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                            <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                            <?php if ($is_selected) : ?>
                                                <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        if ($should_display) {
                                            $display_count++;
                                        }
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                        <p class="description">کاربر مورد نظر برای دریافت صورت حساب را انتخاب کنید. می‌توانید جستجو کنید.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>هزینه اضافی (اختیاری)</label>
                    </th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <div style="flex: 1;">
                                <label for="expense_name" style="display: block; margin-bottom: 5px; font-weight: normal;">نام هزینه:</label>
                                <input type="text" 
                                       name="expense_name" 
                                       id="expense_name" 
                                       value="<?php echo esc_attr(isset($_POST['expense_name']) ? $_POST['expense_name'] : ''); ?>" 
                                       class="regular-text" 
                                       placeholder="مثلاً: هزینه ماهانه، هزینه تغذیه و..."
                                       style="width: 100%;">
                            </div>
                            <div style="flex: 1;">
                                <label for="amount" style="display: block; margin-bottom: 5px; font-weight: normal;">مبلغ (تومان):</label>
                                <input type="text" 
                                       name="amount" 
                                       id="amount" 
                                       value="<?php echo $amount > 0 ? number_format($amount, 0, '.', ',') : ''; ?>" 
                                       class="regular-text" 
                                       placeholder="0"
                                       style="width: 100%;"
                                       dir="ltr"
                                       inputmode="numeric">
                                <input type="hidden" name="amount_raw" id="amount_raw" value="<?php echo esc_attr($amount); ?>">
                            </div>
                        </div>
                        <p class="description">در صورت تمایل می‌توانید هزینه اضافی با نام و مبلغ مشخص اضافه کنید.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit_invoice" class="button button-primary" value="ثبت صورت حساب">
            <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="button">انصراف</a>
        </p>
    </form>
</div>


<style>

</style>

