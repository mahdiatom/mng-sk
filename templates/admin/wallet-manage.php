<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$transactions_table = $wpdb->prefix . 'sc_wallet_transactions';

// دریافت member_id از URL (اگر انتخاب شده باشد)
$selected_member_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;

// دریافت لیست اعضا
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id, player_phone
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);

// اگر کاربری انتخاب شده است، تاریخچه تراکنش‌هایش را دریافت کن
$member_transactions = [];
$member_info = null;
$wallet_balance = 0;

if ($selected_member_id > 0) {
    // دریافت اطلاعات کاربر
    $member_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE id = %d",
        $selected_member_id
    ));
    
    if ($member_info) {
        // دریافت موجودی کیف پول
        $wallet_balance = sc_get_wallet_balance($selected_member_id);
        
        // دریافت تراکنش‌ها
        $member_transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT wt.*, 
                    u.display_name as created_by_name
             FROM $transactions_table wt
             LEFT JOIN {$wpdb->users} u ON wt.created_by = u.ID
             WHERE wt.member_id = %d
             ORDER BY wt.created_at DESC
             LIMIT 100",
            $selected_member_id
        ));
    }
}
?>

<div class="wrap sc-wallet-manage-page-header sc-finance-page-header wrap_wallet_manage_list_uesr">
    <h1 class="wp-heading-inline">مدیریت کیف پول بازیکنان</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge')); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-deduct')); ?>" class="page-title-action">کاهش کیف پول</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="page-title-action">لیست تراکنش‌ها</a>
    <hr class="wp-header-end">
    <p class="sc-wallet-manage-subtitle">بازیکن را انتخاب کنید تا موجودی و تاریخچه تراکنش‌ها نمایش داده شود.</p>
</div>
<div class="wrap sc-wallet-manage-page-body sc-finance-page-body">
    <div class="sc-wallet-manage-layout">
        <div class="sc-wallet-manage-sidebar">
            <div class="sc-wallet-panel sc-finance-panel postbox">
                <div class="postbox-header"><h2>انتخاب بازیکن</h2></div>
                <div class="inside">
                    <div class="sc-wallet-field-row">
                        <label for="member_id">بازیکن</label>
                    <div class="sc-searchable-dropdown sc-wallet-member-dropdown">
                        <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($selected_member_id) echo 'style="display:none"'; ?>>انتخاب بازیکن</span>
                            <span class="sc-dropdown-selected" <?php if (!$selected_member_id) echo 'style="display:none"'; ?>>
                                <?php
                                if ($selected_member_id > 0) {
                                    foreach ($members as $m) {
                                        if ($m->id == $selected_member_id) {
                                            echo esc_html($m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id);
                                            break;
                                        }
                                    }
                                }
                                ?>
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
                                foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                    $search_text = strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id);
                                    $label = $member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?> <?php echo $selected_member_id == $member->id ? 'sc-selected' : ''; ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr($search_text); ?>"
                                         onclick="scSelectMemberForManage(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($label); ?>')">
                                        <span><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></span>
                                        <small style="display: block; color: #666; font-size: 11px;"><?php echo esc_html($member->national_id); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sc-wallet-manage-main">
            <?php if ($selected_member_id > 0 && $member_info) : ?>
                <div class="sc-wallet-balance-card sc-finance-panel postbox">
                    <div class="inside sc-wallet-balance-inner">
                        <div class="sc-wallet-balance-label">موجودی کیف پول</div>
                        <div class="sc-wallet-balance-amount">
                            <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <span>تومان</span>
                        </div>
                    </div>
                </div>

                <div class="sc-wallet-panel sc-finance-panel postbox">
                    <div class="postbox-header"><h2>اطلاعات بازیکن</h2></div>
                    <div class="inside">
                        <table class="form-table sc-wallet-member-info-table">
                            <tr>
                                <th scope="row" style="width: 180px;">نام و نام خانوادگی:</th>
                                <td><strong><?php echo esc_html($member_info->first_name . ' ' . $member_info->last_name); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row">کد ملی:</th>
                                <td><?php echo esc_html($member_info->national_id); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">شماره تماس:</th>
                                <td><?php echo esc_html($member_info->player_phone ?: '-'); ?></td>
                            </tr>
                        </table>
                        
                        <div class="sc-wallet-member-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge&member_id=' . $selected_member_id)); ?>" class="button button-primary">شارژ کیف پول</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-deduct&member_id=' . $selected_member_id)); ?>" class="button button-secondary">کاهش کیف پول</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet&filter_member=' . $selected_member_id)); ?>" class="button button-secondary">مشاهده تراکنش‌ها</a>
                        </div>
                    </div>
                </div>
                
                <div class="sc-wallet-panel sc-finance-panel postbox">
                    <div class="postbox-header sc-wallet-transactions-header">
                        <h2>تاریخچه تراکنش‌ها</h2>
                        <?php
                        // لینک خروجی اکسل برای تراکنش‌های همین کاربر
                        $member_export_url = admin_url('admin.php?page=sc-wallet&sc_export=excel&export_type=wallet_transactions');
                        $member_export_url = add_query_arg('filter_member', $selected_member_id, $member_export_url);
                        $member_export_url = wp_nonce_url($member_export_url, 'sc_export_excel');
                        ?>
                        <a href="<?php echo esc_url($member_export_url); ?>" class="button button-secondary button_export">خروجی Excel</a>
                    </div>
                    <div class="inside sc-wallet-transactions-inside">
                        <?php if (!empty($member_transactions)) : ?>
                            <div class="back_table_list">
                            <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">شناسه</th>
                                        <th style="width: 25%;">نوع</th>
                                        <th style="width: 30%;">مبلغ</th>
                                        <th style="width: 30%">موجودی بعد</th>
                                        <th style="width: 40%;">توضیحات</th>
                                        <th style="width: 25%;">تاریخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($member_transactions as $transaction) : 
                                        $type_labels = [
                                            'charge' => 'شارژ',
                                            'deduct' => 'کاهش',
                                            'payment' => 'پرداخت',
                                            'refund' => 'بازگشت وجه',
                                            'session_fee' => 'کسر جلسه'
                                        ];
                                        $type_icons = [
                                            'charge' => '➕',
                                            'deduct' => '➖',
                                            'payment' => '💳',
                                            'refund' => '↩',
                                            'session_fee' => '📋'
                                        ];
                                        $type_label = isset($type_labels[$transaction->transaction_type]) ? $type_labels[$transaction->transaction_type] : $transaction->transaction_type;
                                        $type_color = [
                                            'charge' => '#00a32a',
                                            'deduct' => '#d63638',
                                            'payment' => '#2271b1',
                                            'refund' => '#f0b849',
                                            'session_fee' => '#856404'
                                        ];
                                        
                                        $status_labels = [
                                            'completed' => 'تکمیل شده',
                                            'pending' => 'در انتظار',
                                            'failed' => 'ناموفق',
                                            'cancelled' => 'لغو شده'
                                        ];
                                        $status_label = isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status;
                                        $status_color = [
                                            'completed' => '#00a32a',
                                            'pending' => '#f0b849',
                                            'failed' => '#d63638',
                                            'cancelled' => '#666'
                                        ];
                                        
                                        $desc = $transaction->description ?? '';
                                        $desc_display = empty($desc) ? '<span style="color: #999;">-</span>' : 
                                            (mb_strlen($desc) > 50 ? '<span title="' . esc_attr($desc) . '">' . esc_html(mb_substr($desc, 0, 50)) . '...</span>' : esc_html($desc));
                                    ?>
                                        <tr>
                                            <td><?php echo esc_html($transaction->id); ?></td>
                                            <td>
                                                <span style="
                                                    display: inline-block;
                                                    padding: 3px 8px;
                                                    border-radius: 3px;
                                                    background: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#f0f0f0'); ?>20;
                                                    color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;
                                                    font-size: 11px;
                                                    font-weight: 600;
                                                ">
                                                    <span style="margin-left: 3px;">
                                                        <?php echo esc_html($type_icons[$transaction->transaction_type] ?? ''); ?>
                                                    </span>
                                                    <?php echo esc_html($type_label); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;">
                                                    <?php echo esc_html(sc_format_amount_display(floatval($transaction->amount))); ?> تومان
                                                </strong>
                                            </td>
                                            <td>
                                                <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#d63638' : '#00a32a'; ?>;">
                                                    <?php echo esc_html(sc_format_amount_display(floatval($transaction->balance_after))); ?> تومان
                                                </strong>
                                            </td>
                                            <td><?php echo $desc_display; ?></td>
                                          
                                            <td><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php else : ?>
                            <div class="notice notice-info inline" style="margin: 15px;">
                                <p>این کاربر هنوز تراکنشی ندارد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="sc-wallet-panel sc-finance-panel postbox sc-wallet-empty-state">
                    <div class="inside">
                        <div class="sc-wallet-empty-icon">👤</div>
                        <h2>بازیکنی انتخاب نشده است</h2>
                        <p>از پنل کنار، یک بازیکن انتخاب کنید تا موجودی و تاریخچه تراکنش‌ها نمایش داده شود.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // تابع انتخاب کاربر برای مدیریت
    window.scSelectMemberForManage = function(element, memberId, memberText) {
        // استفاده از تابع موجود scSelectMember
        if (typeof scSelectMember === 'function') {
            scSelectMember(element, memberId, memberText);
        }
        
        // هدایت به همان صفحه با member_id
        var url = new URL(window.location.href);
        url.searchParams.set('member_id', memberId);
        window.location.href = url.toString();
    };
    
    // اگر member_id در URL وجود دارد، آن را در dropdown تنظیم کن
    var urlParams = new URLSearchParams(window.location.search);
    var memberId = urlParams.get('member_id');
    if (memberId) {
        $('#member_id').val(memberId);
        // پیدا کردن متن کاربر انتخاب شده
        var $selectedOption = $('.sc-dropdown-option[data-value="' + memberId + '"]');
        if ($selectedOption.length) {
            var memberText = $selectedOption.find('span').first().text().trim();
            $('.sc-dropdown-placeholder').hide();
            $('.sc-dropdown-selected').text(memberText).show();
        }
    }
});
</script>
