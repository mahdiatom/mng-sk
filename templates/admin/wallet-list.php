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

// تنظیم مقادیر تاریخ و مقدار پیش‌فرض شمسی (امروز) برای فیلتر بازه تاریخ
$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to   = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';

$today        = new DateTime();
$today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));

$filter_date_from_shamsi_default = '';
$filter_date_to_shamsi_default   = '';

if (empty($filter_date_from)) {
    $filter_date_from_shamsi_default = $today_jalali[0] . '/' .
        str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
        str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
} else {
    $filter_date_from_shamsi_default = sc_date_shamsi_date_only($filter_date_from);
}

if (empty($filter_date_to)) {
    $filter_date_to_shamsi_default = $today_jalali[0] . '/' .
        str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
        str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
} else {
    $filter_date_to_shamsi_default = sc_date_shamsi_date_only($filter_date_to);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست تراکنش‌های کیف پول</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge'); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct'); ?>" class="page-title-action">کاهش کیف پول</a>
    <hr class="wp-header-end">

    <?php
    // داشبورد خلاصه کیف پول برای مدیر
    if (function_exists('sc_get_wallet_admin_statistics')) :
        $sc_wallet_stats = sc_get_wallet_admin_statistics();
    ?>
        <div class="sc-wallet-admin-dashboard" style="margin-top: 20px; margin-bottom: 20px;">
            <h2 style="margin: 0 0 15px 0; font-size: 18px;">خلاصه وضعیت کیف پول</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <div style="background: #ffffff; border-radius: 6px; padding: 12px 14px; border-left: 4px solid #0073aa; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="font-size: 12px; color: #666; margin-bottom: 6px;">تعداد کاربران دارای تراکنش کیف پول</div>
                    <div style="font-size: 20px; font-weight: 700; color: #0073aa;">
                        <?php echo number_format( intval( $sc_wallet_stats['users_with_wallet'] ?? 0 ) ); ?>
                    </div>
                </div>

                <div style="background: #ffffff; border-radius: 6px; padding: 12px 14px; border-left: 4px solid #46b450; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="font-size: 12px; color: #666; margin-bottom: 6px;">مجموع موجودی همه کیف پول‌ها</div>
                    <div style="font-size: 20px; font-weight: 700; color: #46b450;">
                        <?php echo number_format( floatval( $sc_wallet_stats['total_balance'] ?? 0 ), 0, '.', ',' ); ?> تومان
                    </div>
                </div>

                <div style="background: #ffffff; border-radius: 6px; padding: 12px 14px; border-left: 4px solid #826eb4; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="font-size: 12px; color: #666; margin-bottom: 6px;">تعداد کل تراکنش‌های کیف پول</div>
                    <div style="font-size: 20px; font-weight: 700; color: #826eb4;">
                        <?php echo number_format( intval( $sc_wallet_stats['total_transactions'] ?? 0 ) ); ?>
                    </div>
                </div>

                <div style="background: #ffffff; border-radius: 6px; padding: 12px 14px; border-left: 4px solid #d63638; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="font-size: 12px; color: #666; margin-bottom: 6px;">کاربران با موجودی منفی</div>
                    <div style="font-size: 20px; font-weight: 700; color: #d63638;">
                        <?php echo number_format( intval( $sc_wallet_stats['users_with_negative'] ?? 0 ) ); ?>
                    </div>
                </div>

                <div style="background: #ffffff; border-radius: 6px; padding: 12px 14px; border-left: 4px solid #ffb900; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="font-size: 12px; color: #666; margin-bottom: 6px;">مجموع شارژ / مجموع پرداخت</div>
                    <div style="font-size: 14px; font-weight: 600; color: #333; line-height: 1.6;">
                        <span style="display: block; color: #46b450;">
                            شارژ: <?php echo number_format( floatval( $sc_wallet_stats['total_charges'] ?? 0 ), 0, '.', ',' ); ?> تومان
                        </span>
                        <span style="display: block; color: #d63638;">
                            پرداخت: <?php echo number_format( floatval( $sc_wallet_stats['total_payments'] ?? 0 ), 0, '.', ',' ); ?> تومان
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">بازه تاریخ (شمسی)</label>
                    <div>
                        <input type="text"
                               name="filter_date_from_shamsi"
                               id="filter_date_from_shamsi"
                               value="<?php echo esc_attr($filter_date_from_shamsi_default); ?>"
                               class="regular-text persian-date-input"
                               placeholder="از تاریخ"
                               style="padding: 5px; margin-left: 5px; width: 130px;"
                               readonly>
                        <input type="hidden"
                               name="filter_date_from"
                               id="filter_date_from"
                               value="<?php echo esc_attr($filter_date_from); ?>">
                        <span>تا</span>
                        <input type="text"
                               name="filter_date_to_shamsi"
                               id="filter_date_to_shamsi"
                               value="<?php echo esc_attr($filter_date_to_shamsi_default); ?>"
                               class="regular-text persian-date-input"
                               placeholder="تا تاریخ"
                               style="padding: 5px; margin: 0 5px; width: 130px;"
                               readonly>
                        <input type="hidden"
                               name="filter_date_to"
                               id="filter_date_to"
                               value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">بازه مبلغ (تومان)</label>
                    <div>
                        <input type="text"
                               name="filter_amount_min"
                               id="filter_amount_min"
                               value="<?php echo esc_attr(isset($_GET['filter_amount_min']) ? $_GET['filter_amount_min'] : ''); ?>"
                               placeholder="از"
                               style="width: 100px;"
                               dir="ltr"
                               inputmode="numeric">
                        <input type="hidden"
                               name="filter_amount_min_raw"
                               id="filter_amount_min_raw"
                               value="<?php echo esc_attr(isset($_GET['filter_amount_min_raw']) ? $_GET['filter_amount_min_raw'] : (isset($_GET['filter_amount_min']) ? $_GET['filter_amount_min'] : '')); ?>">
                        <span>تا</span>
                        <input type="text"
                               name="filter_amount_max"
                               id="filter_amount_max"
                               value="<?php echo esc_attr(isset($_GET['filter_amount_max']) ? $_GET['filter_amount_max'] : ''); ?>"
                               placeholder="تا"
                               style="width: 100px;"
                               dir="ltr"
                               inputmode="numeric">
                        <input type="hidden"
                               name="filter_amount_max_raw"
                               id="filter_amount_max_raw"
                               value="<?php echo esc_attr(isset($_GET['filter_amount_max_raw']) ? $_GET['filter_amount_max_raw'] : (isset($_GET['filter_amount_max']) ? $_GET['filter_amount_max'] : '')); ?>">
                    </div>
                </div>

                <div>
                    <label for="s" style="display: block; margin-bottom: 5px; font-weight: 600;">جستجو:</label>
                    <input type="text" name="s" id="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="نام، نام خانوادگی، کد ملی، توضیحات">
                </div>
                
                <div>
                    <input type="submit" class="button button-primary" value="اعمال فیلتر">
                    <?php
                    // ساخت URL خروجی اکسل با درنظرگرفتن فیلترهای فعلی
                    $export_url = admin_url('admin.php?page=sc-wallet&sc_export=excel&export_type=wallet_transactions');
                    
                    if (isset($_GET['filter_member']) && absint($_GET['filter_member']) > 0) {
                        $export_url = add_query_arg('filter_member', absint($_GET['filter_member']), $export_url);
                    }
                    if (isset($_GET['filter_type']) && $_GET['filter_type'] !== 'all') {
                        $export_url = add_query_arg('filter_type', sanitize_text_field($_GET['filter_type']), $export_url);
                    }
                    if (isset($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
                        $export_url = add_query_arg('filter_status', sanitize_text_field($_GET['filter_status']), $export_url);
                    }
                    if (isset($_GET['filter_date_from']) && $_GET['filter_date_from'] !== '') {
                        $export_url = add_query_arg('filter_date_from', sanitize_text_field($_GET['filter_date_from']), $export_url);
                    }
                    if (isset($_GET['filter_date_to']) && $_GET['filter_date_to'] !== '') {
                        $export_url = add_query_arg('filter_date_to', sanitize_text_field($_GET['filter_date_to']), $export_url);
                    }
                    if (isset($_GET['filter_amount_min_raw']) && $_GET['filter_amount_min_raw'] !== '') {
                        $export_url = add_query_arg('filter_amount_min_raw', sanitize_text_field($_GET['filter_amount_min_raw']), $export_url);
                    } elseif (isset($_GET['filter_amount_min']) && $_GET['filter_amount_min'] !== '') {
                        $export_url = add_query_arg('filter_amount_min', sanitize_text_field($_GET['filter_amount_min']), $export_url);
                    }
                    if (isset($_GET['filter_amount_max_raw']) && $_GET['filter_amount_max_raw'] !== '') {
                        $export_url = add_query_arg('filter_amount_max_raw', sanitize_text_field($_GET['filter_amount_max_raw']), $export_url);
                    } elseif (isset($_GET['filter_amount_max']) && $_GET['filter_amount_max'] !== '') {
                        $export_url = add_query_arg('filter_amount_max', sanitize_text_field($_GET['filter_amount_max']), $export_url);
                    }
                    if (isset($_GET['s']) && $_GET['s'] !== '') {
                        $export_url = add_query_arg('s', sanitize_text_field($_GET['s']), $export_url);
                    }
                    
                    $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                    ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                        📊 خروجی Excel
                    </a>
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
        
        <?php $wallet_transactions_list_table->display(); ?>
    </form>
</div>
