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

<script type="text/javascript">
// تابع انتخاب کاربر
function scSelectMember(element, memberId, memberText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');
    
    // تنظیم مقدار
    $hiddenInput.val(memberId);
    $placeholder.hide();
    $selected.text(memberText).show();
    
    // بستن منو
    $menu.slideUp(200);
    
    // حذف انتخاب قبلی و اضافه کردن انتخاب جدید
    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected');
    jQuery(element).addClass('sc-selected');
    
    // تغییر background
    $dropdown.find('.sc-dropdown-option').css('background', '');
    jQuery(element).css('background', '#f0f6fc');
    
    // اضافه کردن چکمارک
    jQuery(element).find('span').remove();
    jQuery(element).append('<span style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

jQuery(document).ready(function($) {
    // مدیریت باز و بسته شدن dropdown
    $('.sc-dropdown-toggle').on('click', function(e) {
        e.stopPropagation();
        var $menu = $(this).siblings('.sc-dropdown-menu');
        var isOpen = $menu.is(':visible');
        
        // بستن همه dropdown‌ها
        $('.sc-dropdown-menu').slideUp(200);
        
        if (!isOpen) {
            $menu.slideDown(200);
            // فوکوس به input جستجو
            setTimeout(function() {
                $menu.find('.sc-search-input').focus();
            }, 250);
        }
    });
    
    // جستجو در dropdown
    $('.sc-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var $options = $(this).closest('.sc-dropdown-menu').find('.sc-dropdown-option');
        var visibleCount = 0;
        var maxVisible = 10;
        
        // حذف پیام "نتیجه‌ای یافت نشد" قبلی
        $options.closest('.sc-dropdown-options').find('div:not(.sc-dropdown-option)').remove();
        
        if (searchTerm === '') {
            // اگر جستجو خالی است، 10 مورد اول را نمایش بده
            $options.each(function(index) {
                if (index < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
        } else {
            $options.each(function() {
                var searchText = $(this).attr('data-search') || '';
                var matches = searchText.includes(searchTerm);
                
                if (matches && visibleCount < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
            
            // اگر هیچ نتیجه‌ای پیدا نشد
            if (visibleCount === 0) {
                $options.closest('.sc-dropdown-options').append(
                    '<div style="padding: 15px; text-align: center; color: #757575; border-bottom: 1px solid #f0f0f1;">نتیجه‌ای یافت نشد</div>'
                );
            }
        }
    });
    
    // بستن dropdown با کلیک خارج
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sc-searchable-dropdown').length) {
            $('.sc-dropdown-menu').slideUp(200);
        }
    });
    
    // جلوگیری از بستن dropdown با کلیک داخل
    $('.sc-dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });
    
    // نمایش مقدار انتخاب شده در صورت وجود
    $('.sc-searchable-dropdown').each(function() {
        var $dropdown = $(this);
        var selectedValue = $dropdown.find('input[type="hidden"]').val();
        if (selectedValue) {
            var $selectedOption = $dropdown.find('.sc-dropdown-option[data-value="' + selectedValue + '"]');
            if ($selectedOption.length) {
                var selectedText = $selectedOption.text().replace('✓', '').trim();
                $dropdown.find('.sc-dropdown-placeholder').hide();
                $dropdown.find('.sc-dropdown-selected').text(selectedText).show();
            }
        }
    });
    
    // فرمت کردن مبلغ به صورت سه رقم سه رقم (روش مستقیم)
    var $amountInput = $('#amount');
    var $amountRaw = $('#amount_raw');
    
    $amountInput.on('input', function() {
        var $this = $(this);
        var value = $this.val();
        
        // حذف تمام کاماها و کاراکترهای غیر عددی
        var cleaned = value.replace(/,/g, '').replace(/\D/g, '');
        
        // اگر خالی است
        if (cleaned === '' || cleaned === '0') {
            $this.val('');
            $amountRaw.val('0');
            return;
        }
        
        // فرمت کردن با کاما (سه رقم سه رقم)
        var formatted = parseInt(cleaned, 10).toLocaleString('en-US');
        $this.val(formatted);
        
        // ذخیره مقدار خالص در hidden input
        $amountRaw.val(cleaned);
    });
    
    // هنگام blur
    $amountInput.on('blur', function() {
        var value = $(this).val();
        var cleaned = value.replace(/,/g, '');
        if (cleaned === '' || cleaned === '0') {
            $(this).val('');
            $amountRaw.val('0');
        }
    });
    
    // قبل از submit
    $('form').on('submit', function() {
        var rawValue = $amountRaw.val() || '0';
        $amountInput.val(rawValue);
    });
    
    // فرمت کردن مقدار اولیه در صورت وجود
    if ($amountInput.val()) {
        $amountInput.trigger('input');
    }
    
});
</script>

<style>
.sc-dropdown-option:hover {
    background: #f0f6fc !important;
}
.sc-dropdown-option.sc-selected {
    background: #f0f6fc;
}
.sc-searchable-dropdown {
    direction: rtl;
}
.sc-dropdown-menu::-webkit-scrollbar {
    width: 8px;
}
.sc-dropdown-menu::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.sc-dropdown-menu::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.sc-dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

