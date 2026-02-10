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

<div class="wrap">
    <h1 class="wp-heading-inline">مدیریت شارژ کیف پول</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge'); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct'); ?>" class="page-title-action">کاهش کیف پول</a>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="page-title-action">لیست تراکنش‌ها</a>
    <hr class="wp-header-end">

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 20px; margin-top: 20px;">
        <!-- ستون سمت راست: لیست کاربران -->
        <div>
            <div class="postbox" style="margin-top: 0;">
                <div class="postbox-header">
                    <h2 class="hndle">لیست کاربران</h2>
                </div>
                <div class="inside" style="padding: 15px;">
                    <p style="margin-bottom: 10px;"><label for="member_id">کاربر:</label></p>
                    <div class="sc-searchable-dropdown" style="width: 100%;">
                        <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>">
                        <div class="sc-dropdown-toggle" style="width: 100%;">
                            <span class="sc-dropdown-placeholder" <?php if ($selected_member_id) echo 'style="display:none"'; ?>>انتخاب کاربر</span>
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
                        <div class="sc-dropdown-menu" style="width: 100%; max-height: 400px; overflow-y: auto;">
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

        <!-- ستون سمت چپ: اطلاعات کاربر و تاریخچه -->
        <div>
            <?php if ($selected_member_id > 0 && $member_info) : ?>
                <!-- کارت موجودی -->
                <div class="postbox" style="margin-top: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <div class="inside" style="padding: 20px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">موجودی کیف پول</div>
                                <div style="font-size: 36px; font-weight: 700; line-height: 1.2;">
                                    <?php echo number_format($wallet_balance, 0, '.', ','); ?> <span style="font-size: 20px; font-weight: 400;">تومان</span>
                                </div>
                            </div>
                            <div style="font-size: 60px; opacity: 0.2;">💳</div>
                        </div>
                    </div>
                </div>

                <!-- اطلاعات کاربر -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">اطلاعات کاربر</h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
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
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge&member_id=' . $selected_member_id); ?>" class="button button-primary">شارژ کیف پول</a>
                            <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct&member_id=' . $selected_member_id); ?>" class="button">کاهش کیف پول</a>
                            <a href="<?php echo admin_url('admin.php?page=sc-wallet&filter_member=' . $selected_member_id); ?>" class="button">مشاهده تمام تراکنش‌ها</a>
                        </div>
                    </div>
                </div>
                
                <!-- لیست تراکنش‌ها -->
                <div class="postbox">
                    <div class="postbox-header" style="display: flex; align-items: center; justify-content: space-between;">
                        <h2 class="hndle" style="margin: 0;">تاریخچه تراکنش‌ها</h2>
                        <?php
                        // لینک خروجی اکسل برای تراکنش‌های همین کاربر
                        $member_export_url = admin_url('admin.php?page=sc-wallet&sc_export=excel&export_type=wallet_transactions');
                        $member_export_url = add_query_arg('filter_member', $selected_member_id, $member_export_url);
                        $member_export_url = wp_nonce_url($member_export_url, 'sc_export_excel');
                        ?>
                        <a href="<?php echo esc_url($member_export_url); ?>" class="button button-secondary" style="margin: 4px 10px 4px auto;">
                            📊 خروجی Excel این کاربر
                        </a>
                    </div>
                    <div class="inside" style="padding: 0;">
                        <?php if (!empty($member_transactions)) : ?>
                            <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">شناسه</th>
                                        <th style="width: 100px;">نوع</th>
                                        <th style="width: 120px;">مبلغ</th>
                                        <th style="width: 130px;">موجودی بعد</th>
                                        <th>توضیحات</th>
                                        <th style="width: 100px;">وضعیت</th>
                                        <th style="width: 140px;">تاریخ</th>
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
                                                    <?php echo number_format(floatval($transaction->amount), 0, '.', ','); ?> تومان
                                                </strong>
                                            </td>
                                            <td>
                                                <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#d63638' : '#00a32a'; ?>;">
                                                    <?php echo number_format(floatval($transaction->balance_after), 0, '.', ','); ?> تومان
                                                </strong>
                                            </td>
                                            <td><?php echo $desc_display; ?></td>
                                            <td>
                                                <span style="
                                                    display: inline-block;
                                                    padding: 3px 8px;
                                                    border-radius: 3px;
                                                    background: <?php echo esc_attr($status_color[$transaction->status] ?? '#333'); ?>20;
                                                    color: <?php echo esc_attr($status_color[$transaction->status] ?? '#333'); ?>;
                                                    font-size: 11px;
                                                    font-weight: 600;
                                                ">
                                                    <?php echo esc_html($status_label); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="notice notice-info inline" style="margin: 15px;">
                                <p>این کاربر هنوز تراکنشی ندارد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="postbox" style="margin-top: 0;">
                    <div class="inside" style="padding: 30px; text-align: center;">
                        <div style="font-size: 48px; opacity: 0.3; margin-bottom: 15px;">👤</div>
                        <h2 style="margin: 0 0 10px 0; color: #666;">کاربری انتخاب نشده است</h2>
                        <p style="color: #999; margin: 0;">لطفاً یک کاربر را از لیست سمت راست انتخاب کنید تا اطلاعات و تاریخچه تراکنش‌هایش نمایش داده شود.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media (max-width: 1200px) {
    .wrap > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

.postbox table.wp-list-table tbody tr {
    transition: background-color 0.15s ease, transform 0.1s ease;
}

.postbox table.wp-list-table tbody tr:hover {
    background-color: #f7f7f7;
    transform: translateY(-1px);
}
</style>

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
