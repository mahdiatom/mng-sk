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
    <hr class="wp-header-end">

    <div style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- لیست کاربران -->
        <div style="flex: 1; min-width: 300px;">
            <h2 style="margin-top: 0;">لیست کاربران</h2>
            
            <div class="sc-searchable-dropdown" style="width: 100%; margin-bottom: 20px;">
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
                        ?>
                        
                        <?php foreach ($members as $member) :
                            $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                            $display_count++;
                            $member_balance = sc_get_wallet_balance($member->id);
                        ?>
                            <div class="sc-dropdown-option <?php echo $display_class; ?> <?php echo $selected_member_id == $member->id ? 'sc-selected' : ''; ?>"
                                 data-value="<?php echo esc_attr($member->id); ?>"
                                 data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                 onclick="scSelectMemberForManage(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?></span>
                                    <span style="color: <?php echo $member_balance >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: 600; font-size: 12px;">
                                        <?php echo number_format($member_balance, 0, '.', ','); ?> تومان
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- تاریخچه تراکنش‌های کاربر انتخاب شده -->
        <div style="flex: 2; min-width: 500px;">
            <?php if ($selected_member_id > 0 && $member_info) : ?>
                <h2 style="margin-top: 0;">
                    تاریخچه تراکنش‌های 
                    <?php echo esc_html($member_info->first_name . ' ' . $member_info->last_name); ?>
                </h2>
                
                <!-- نمایش موجودی -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">موجودی کیف پول</div>
                            <div style="font-size: 28px; font-weight: 700;">
                                <?php echo number_format($wallet_balance, 0, '.', ','); ?> تومان
                            </div>
                        </div>
                        <div style="font-size: 40px; opacity: 0.3;">💳</div>
                    </div>
                </div>
                
                <!-- اطلاعات کاربر -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;">
                    <h3 style="margin-top: 0; margin-bottom: 15px;">اطلاعات کاربر</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 5px 0; width: 150px; font-weight: 600;">نام و نام خانوادگی:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($member_info->first_name . ' ' . $member_info->last_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: 600;">کد ملی:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($member_info->national_id); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: 600;">شماره تماس:</td>
                            <td style="padding: 5px 0;"><?php echo esc_html($member_info->player_phone ?: '-'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- دکمه‌های عملیات -->
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge&member_id=' . $selected_member_id); ?>" class="button button-primary">شارژ کیف پول</a>
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct&member_id=' . $selected_member_id); ?>" class="button">کاهش کیف پول</a>
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet&filter_member=' . $selected_member_id); ?>" class="button">مشاهده تمام تراکنش‌ها</a>
                </div>
                
                <!-- لیست تراکنش‌ها -->
                <?php if (!empty($member_transactions)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 80px;">شناسه</th>
                                <th style="width: 100px;">نوع</th>
                                <th style="width: 120px;">مبلغ</th>
                                <th style="width: 130px;">موجودی بعد</th>
                                <th style="min-width: 200px;">توضیحات</th>
                                <th style="width: 100px;">وضعیت</th>
                                <th style="width: 130px;">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_transactions as $transaction) : 
                                $type_labels = [
                                    'charge' => 'شارژ',
                                    'deduct' => 'کاهش',
                                    'payment' => 'پرداخت',
                                    'refund' => 'بازگشت وجه'
                                ];
                                $type_label = isset($type_labels[$transaction->transaction_type]) ? $type_labels[$transaction->transaction_type] : $transaction->transaction_type;
                                $type_color = [
                                    'charge' => '#28a745',
                                    'deduct' => '#dc3545',
                                    'payment' => '#007bff',
                                    'refund' => '#ffc107'
                                ];
                                $type_bg = [
                                    'charge' => '#d4edda',
                                    'deduct' => '#f8d7da',
                                    'payment' => '#d1ecf1',
                                    'refund' => '#fff3cd'
                                ];
                                
                                $status_labels = [
                                    'completed' => 'تکمیل شده',
                                    'pending' => 'در انتظار',
                                    'failed' => 'ناموفق',
                                    'cancelled' => 'لغو شده'
                                ];
                                $status_label = isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status;
                                $status_color = [
                                    'completed' => '#28a745',
                                    'pending' => '#ffc107',
                                    'failed' => '#dc3545',
                                    'cancelled' => '#6c757d'
                                ];
                                
                                $desc = $transaction->description ?? '';
                                $desc_display = empty($desc) ? '<span style="color: #999;">-</span>' : 
                                    (mb_strlen($desc) > 60 ? '<span title="' . esc_attr($desc) . '">' . esc_html(mb_substr($desc, 0, 60)) . '...</span>' : esc_html($desc));
                            ?>
                                <tr>
                                    <td><?php echo esc_html($transaction->id); ?></td>
                                    <td>
                                        <span style="
                                            display: inline-block;
                                            padding: 4px 8px;
                                            border-radius: 4px;
                                            background: <?php echo esc_attr($type_bg[$transaction->transaction_type] ?? '#f0f0f0'); ?>;
                                            color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;
                                            font-size: 12px;
                                            font-weight: 600;
                                        ">
                                            <?php echo esc_html($type_label); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;">
                                            <?php echo number_format(floatval($transaction->amount), 0, '.', ','); ?> تومان
                                        </strong>
                                    </td>
                                    <td>
                                        <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#dc3545' : '#28a745'; ?>;">
                                            <?php echo number_format(floatval($transaction->balance_after), 0, '.', ','); ?> تومان
                                        </strong>
                                    </td>
                                    <td><?php echo $desc_display; ?></td>
                                    <td>
                                        <span style="
                                            display: inline-block;
                                            padding: 4px 8px;
                                            border-radius: 4px;
                                            background: #f0f0f0;
                                            color: <?php echo esc_attr($status_color[$transaction->status] ?? '#333'); ?>;
                                            font-size: 12px;
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
                    <div class="notice notice-info">
                        <p>این کاربر هنوز تراکنشی ندارد.</p>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="notice notice-info">
                    <p>لطفاً یک کاربر را از لیست انتخاب کنید تا تاریخچه تراکنش‌هایش نمایش داده شود.</p>
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
        scSelectMember(element, memberId, memberText);
        
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
            var memberText = $selectedOption.text().trim();
            $('.sc-dropdown-placeholder').hide();
            $('.sc-dropdown-selected').text(memberText).show();
        }
    }
});
</script>

