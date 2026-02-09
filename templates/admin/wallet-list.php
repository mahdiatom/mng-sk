<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// این فایل برای نمایش لیست تراکنش‌های کیف پول است
global $wallet_transactions_list_table;

// دریافت لیست اعضا برای فیلتر
global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id 
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست تراکنش‌های کیف پول</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge'); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct'); ?>" class="page-title-action">کاهش کیف پول</a>
    <hr class="wp-header-end">

    <!-- فیلترها -->
    <div class="sc-wallet-filters" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-wallet">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="sc-filter-field" style="min-width: 200px; max-width: 300px;">
                    <label class="sc-filter-label">بازیکن</label>
                    
                    <div class="sc-searchable-dropdown">
                        <?php
                        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
                        $selected_member_text = 'همه کاربران';
                        
                        if ($filter_member > 0) {
                            foreach ($members as $m) {
                                if ($m->id == $filter_member) {
                                    $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                                    break;
                                }
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            
                            <div class="sc-dropdown-options">
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                ?>
                                
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
                                
                                <?php foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                        <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="filter_type" style="display: block; margin-bottom: 5px; font-weight: 600;">نوع تراکنش:</label>
                    <select name="filter_type" id="filter_type">
                        <option value="all" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all', 'all'); ?>>همه</option>
                        <option value="charge" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'deduct'); ?>>کاهش</option>
                        <option value="payment" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'payment'); ?>>پرداخت</option>
                        <option value="refund" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'refund'); ?>>بازگشت وجه</option>
                    </select>
                </div>

                <div>
                    <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                    <select name="filter_status" id="filter_status">
                        <option value="all" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all', 'all'); ?>>همه</option>
                        <option value="completed" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'completed'); ?>>تکمیل شده</option>
                        <option value="pending" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'pending'); ?>>در انتظار</option>
                        <option value="failed" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'failed'); ?>>ناموفق</option>
                        <option value="cancelled" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'cancelled'); ?>>لغو شده</option>
                    </select>
                </div>

                <div>
                    <label for="s" style="display: block; margin-bottom: 5px; font-weight: 600;">جستجو:</label>
                    <input type="text" name="s" id="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="نام، نام خانوادگی، کد ملی، توضیحات">
                </div>

                <div>
                    <input type="submit" class="button button-primary" value="اعمال فیلتر">
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">پاک کردن</a>
                </div>
            </div>
        </form>
    </div>

    <!-- نمایش جدول -->
    <form method="GET">
        <input type="hidden" name="page" value="sc-wallet">
        <?php if (isset($_GET['filter_member'])) : ?>
            <input type="hidden" name="filter_member" value="<?php echo esc_attr($_GET['filter_member']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['filter_type'])) : ?>
            <input type="hidden" name="filter_type" value="<?php echo esc_attr($_GET['filter_type']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['filter_status'])) : ?>
            <input type="hidden" name="filter_status" value="<?php echo esc_attr($_GET['filter_status']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['s'])) : ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($_GET['s']); ?>">
        <?php endif; ?>
        
        <?php $wallet_transactions_list_table->search_box('جستجو', 'search_wallet'); ?>
        <?php $wallet_transactions_list_table->display(); ?>
    </form>
</div>
