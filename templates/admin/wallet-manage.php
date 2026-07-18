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
    "SELECT id, first_name, last_name, national_id, player_phone, personal_photo
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

$type_labels = [
    'charge' => 'شارژ',
    'deduct' => 'کاهش',
    'payment' => 'پرداخت',
    'refund' => 'بازگشت وجه',
    'session_fee' => 'کسر جلسه'
];
$type_icons = [
    'charge' => '↑',
    'deduct' => '↓',
    'payment' => '−',
    'refund' => '↩',
    'session_fee' => '•'
];
$type_badges = [
    'charge' => 'sc-badge--success',
    'deduct' => 'sc-badge--danger',
    'payment' => 'sc-badge--purple',
    'refund' => 'sc-badge--warning',
    'session_fee' => 'sc-badge--soft'
];
$status_labels = [
    'completed' => 'تکمیل شده',
    'pending' => 'در انتظار',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده'
];
$status_badges = [
    'completed' => 'sc-badge--success',
    'pending' => 'sc-badge--warning',
    'failed' => 'sc-badge--danger',
    'cancelled' => 'sc-badge--muted'
];

$selected_member_text = 'انتخاب بازیکن';
if ($selected_member_id > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $selected_member_id) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}
?>

<div class="wrap sc-wallet-manage-wrap">
    <div class="sc-wallet-manage-header">
        <div class="sc-wallet-manage-header-text">
            <h1 class="sc-wallet-manage-title">مدیریت شارژ</h1>
            <p class="sc-wallet-manage-desc">بازیکن را انتخاب کنید تا موجودی، اطلاعات و تاریخچه تراکنش‌ها نمایش داده شود.</p>
        </div>
        <div class="sc-wallet-manage-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge')); ?>" class="sc-wallet-manage-add-btn">شارژ کیف پول</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-deduct')); ?>" class="sc-wallet-manage-export-btn">کاهش کیف پول</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="sc-wallet-manage-export-btn">لیست تراکنش‌ها</a>
        </div>
    </div>

    <div class="sc-wallet-manage-layout">
        <div class="sc-wallet-manage-sidebar">
            <div class="sc-wallet-manage-panel">
                <div class="sc-wallet-manage-panel-header">
                    <h2>انتخاب بازیکن</h2>
                </div>
                <div class="sc-wallet-manage-panel-body">
                    <div class="sc-filter-field_wallet">
                        <label class="sc-filter-label" for="member_id">بازیکن</label>
                        <div class="sc-searchable-dropdown sc-wallet-member-dropdown">
                            <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>">
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder" <?php if ($selected_member_id) echo 'style="display:none"'; ?>>انتخاب بازیکن</span>
                                <span class="sc-dropdown-selected" <?php if (!$selected_member_id) echo 'style="display:none"'; ?>>
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
                                            <small><?php echo esc_html($member->national_id); ?></small>
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
            <?php if ($selected_member_id > 0 && $member_info) :
                $full_name = trim($member_info->first_name . ' ' . $member_info->last_name);
                $photo = !empty($member_info->personal_photo) ? $member_info->personal_photo : '';
                $initials = '';
                if (!empty($member_info->first_name)) {
                    $initials .= mb_substr((string) $member_info->first_name, 0, 1);
                }
                if (!empty($member_info->last_name)) {
                    $initials .= mb_substr((string) $member_info->last_name, 0, 1);
                }
                if ($initials === '') {
                    $initials = '؟';
                }
                $member_export_url = admin_url('admin.php?page=sc-wallet&sc_export=excel&export_type=wallet_transactions');
                $member_export_url = add_query_arg('filter_member', $selected_member_id, $member_export_url);
                $member_export_url = wp_nonce_url($member_export_url, 'sc_export_excel');
            ?>
                <div class="sc-wallet-manage-balance-card">
                    <div class="sc-wallet-manage-balance-label">موجودی کیف پول</div>
                    <div class="sc-wallet-manage-balance-amount">
                        <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <span>تومان</span>
                    </div>
                </div>

                <div class="sc-wallet-manage-panel">
                    <div class="sc-wallet-manage-panel-header">
                        <h2>اطلاعات بازیکن</h2>
                    </div>
                    <div class="sc-wallet-manage-panel-body">
                        <div class="sc-wallet-manage-member-card">
                            <?php if ($photo) : ?>
                                <span class="sc-member-avatar"><img src="<?php echo esc_url($photo); ?>" alt="" loading="lazy"></span>
                            <?php else : ?>
                                <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                            <?php endif; ?>
                            <div class="sc-wallet-manage-member-info">
                                <div class="sc-member-name"><?php echo esc_html($full_name); ?></div>
                                <div class="sc-member-meta">
                                    <span class="sc-member-meta-item"><?php echo esc_html($member_info->national_id ?: '—'); ?></span>
                                    <span class="sc-member-meta-dot"></span>
                                    <span class="sc-member-meta-item"><?php echo esc_html($member_info->player_phone ?: '—'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="sc-wallet-manage-member-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge&member_id=' . $selected_member_id)); ?>" class="sc-wallet-manage-add-btn">شارژ کیف پول</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-deduct&member_id=' . $selected_member_id)); ?>" class="sc-wallet-manage-export-btn">کاهش کیف پول</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet&filter_member=' . $selected_member_id)); ?>" class="sc-wallet-manage-export-btn">مشاهده تراکنش‌ها</a>
                        </div>
                    </div>
                </div>

                <div class="sc-wallet-manage-panel sc-wallet-manage-tx-panel">
                    <div class="sc-wallet-manage-panel-header sc-wallet-manage-tx-header">
                        <h2>تاریخچه تراکنش‌ها</h2>
                        <a href="<?php echo esc_url($member_export_url); ?>" class="sc-wallet-manage-export-btn">خروجی Excel</a>
                    </div>
                    <div class="sc-wallet-manage-table-card">
                        <?php if (!empty($member_transactions)) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>شناسه</th>
                                        <th>نوع</th>
                                        <th>مبلغ</th>
                                        <th>موجودی بعد</th>
                                        <th>وضعیت</th>
                                        <th>توضیحات</th>
                                        <th>تاریخ</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($member_transactions as $transaction) :
                                        $type = $transaction->transaction_type ?? '';
                                        $status = $transaction->status ?? '';
                                        $type_label = $type_labels[$type] ?? $type;
                                        $type_icon = $type_icons[$type] ?? '•';
                                        $type_badge = $type_badges[$type] ?? 'sc-badge--muted';
                                        $status_label = $status_labels[$status] ?? $status;
                                        $status_badge = $status_badges[$status] ?? 'sc-badge--soft';
                                        $is_credit = in_array($type, ['charge', 'refund'], true);
                                        $amount_class = $is_credit ? 'sc-wallet-amount is-credit' : 'sc-wallet-amount is-debit';
                                        $amount_prefix = $is_credit ? '+' : '−';
                                        $balance = floatval($transaction->balance_after);
                                        $balance_class = 'sc-wallet-balance';
                                        if ($balance < 0) {
                                            $balance_class .= ' is-negative';
                                        } elseif ($balance > 0) {
                                            $balance_class .= ' is-positive';
                                        }
                                        $desc = $transaction->description ?? '';
                                        $desc_display = $desc === ''
                                            ? '<span class="sc-badge sc-badge--muted">—</span>'
                                            : (mb_strlen($desc) > 50
                                                ? '<span class="sc-wallet-desc" title="' . esc_attr($desc) . '">' . esc_html(mb_substr($desc, 0, 50)) . '...</span>'
                                                : '<span class="sc-wallet-desc">' . esc_html($desc) . '</span>');
                                        $ref_id = !empty($transaction->related_order_id)
                                            ? $transaction->related_order_id
                                            : (!empty($transaction->related_invoice_id) ? $transaction->related_invoice_id : '');
                                    ?>
                                        <tr>
                                            <td><span class="sc-wallet-tx-id">#<?php echo esc_html($transaction->id); ?></span></td>
                                            <td>
                                                <span class="sc-badge <?php echo esc_attr($type_badge); ?> sc-wallet-type-badge">
                                                    <span class="sc-wallet-type-icon" aria-hidden="true"><?php echo esc_html($type_icon); ?></span>
                                                    <?php echo esc_html($type_label); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?php echo esc_attr($amount_class); ?>">
                                                    <?php echo esc_html($amount_prefix . ' ' . sc_format_amount_display(floatval($transaction->amount))); ?> تومان
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?php echo esc_attr($balance_class); ?>">
                                                    <?php echo esc_html(sc_format_amount_display($balance)); ?> تومان
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sc-badge <?php echo esc_attr($status_badge); ?>"><?php echo esc_html($status_label); ?></span>
                                            </td>
                                            <td>
                                                <?php echo $desc_display; ?>
                                                <?php if ($ref_id !== '') : ?>
                                                    <div class="sc-wallet-ref">مرجع: <?php echo esc_html($ref_id); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="sc-wallet-date"><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></span></td>
                                            <td>
                                                <a class="sc-wallet-action-btn" href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet&filter_member=' . $selected_member_id . '&s=' . urlencode((string) $transaction->id))); ?>">جزئیات</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="sc-wallet-manage-empty-inline">
                                <p>این کاربر هنوز تراکنشی ندارد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="sc-wallet-manage-panel sc-wallet-manage-empty-state">
                    <div class="sc-wallet-manage-panel-body">
                        <div class="sc-wallet-manage-empty-icon" aria-hidden="true">👤</div>
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
    window.scSelectMemberForManage = function(element, memberId, memberText) {
        if (typeof scSelectMember === 'function') {
            scSelectMember(element, memberId, memberText);
        }

        var url = new URL(window.location.href);
        url.searchParams.set('member_id', memberId);
        window.location.href = url.toString();
    };

    var urlParams = new URLSearchParams(window.location.search);
    var memberId = urlParams.get('member_id');
    if (memberId) {
        $('#member_id').val(memberId);
        var $selectedOption = $('.sc-dropdown-option[data-value="' + memberId + '"]');
        if ($selectedOption.length) {
            var memberText = $selectedOption.find('span').first().text().trim();
            var nationalId = $selectedOption.find('small').first().text().trim();
            if (nationalId) {
                memberText = memberText + ' - ' + nationalId;
            }
            $('.sc-dropdown-placeholder').hide();
            $('.sc-dropdown-selected').text(memberText).show();
        }
    }
});
</script>
