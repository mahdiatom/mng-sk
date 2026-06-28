<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';

$member_id_from_url = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
$message = '';
$message_type = '';

if (isset($_POST['sc_deduct_wallet']) && check_admin_referer('sc_deduct_wallet_nonce', 'sc_deduct_wallet_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $amount_raw = isset($_POST['amount_raw']) && $_POST['amount_raw'] !== '' ? $_POST['amount_raw'] : (isset($_POST['amount']) ? $_POST['amount'] : '');
    $amount = $amount_raw !== '' ? floatval(str_replace(',', '', $amount_raw)) : 0;
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';

    if ($member_id <= 0) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif ($amount <= 0) {
        $message = 'مبلغ باید بیشتر از صفر باشد.';
        $message_type = 'error';
    } elseif (empty($description)) {
        $message = 'لطفاً توضیحات هزینه را وارد کنید.';
        $message_type = 'error';
    } else {
        $current_balance = sc_get_wallet_balance($member_id);
        $max_negative = sc_get_wallet_max_negative_balance();

        if ($current_balance - $amount < -$max_negative) {
            $message = 'موجودی کیف پول نمی‌تواند کمتر از ' . number_format($max_negative, 0, '.', ',') . ' تومان باشد. موجودی فعلی: ' . number_format($current_balance, 0, '.', ',') . ' تومان';
            $message_type = 'error';
        } else {
            $result = sc_deduct_wallet($member_id, $amount, $description);
            if ($result['success']) {
                $message = 'مبلغ از کیف پول کسر شد. موجودی جدید: ' . number_format($result['balance_after'], 0, '.', ',') . ' تومان';
                $message_type = 'success';
                $_POST = [];
            } else {
                $message = $result['message'];
                $message_type = 'error';
            }
        }
    }
}

$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id, player_phone
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);

$selected_member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : $member_id_from_url;
$selected_member_text = 'انتخاب بازیکن';
$selected_member_balance = 0;

if ($selected_member_id > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $selected_member_id) {
            $selected_member_balance = sc_get_wallet_balance($m->id);
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id . ' (موجودی: ' . number_format($selected_member_balance, 0, '.', ',') . ' تومان)';
            break;
        }
    }
}
?>

<div class="wrap sc-wallet-deduct-page-header sc-finance-page-header">
    <h1 class="wp-heading-inline">کاهش کیف پول بازیکن</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge')); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-manage')); ?>" class="page-title-action">مدیریت شارژ</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="page-title-action">لیست تراکنش‌ها</a>
    <hr class="wp-header-end">
    <p class="sc-wallet-deduct-subtitle">بازیکن را انتخاب کنید و مبلغ کسر را با توضیحات ثبت کنید.</p>
</div>
<div class="wrap sc-wallet-deduct-page-body sc-finance-page-body">
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="sc-wallet-deduct-form">
        <?php wp_nonce_field('sc_deduct_wallet_nonce', 'sc_deduct_wallet_nonce'); ?>

        <div class="sc-wallet-panel sc-finance-panel postbox">
            <div class="postbox-header"><h2>اطلاعات کسر</h2></div>
            <div class="inside sc-wallet-panel-fields">

                <div class="sc-wallet-field-row">
                    <label>بازیکن <span class="required">*</span></label>
                    <div class="sc-searchable-dropdown sc-wallet-member-dropdown">
                        <input type="hidden" name="member_id" id="member_id" value="<?php echo esc_attr($selected_member_id); ?>" required>
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
                                    $balance = sc_get_wallet_balance($member->id);
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-balance="<?php echo esc_attr($balance); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         onclick="scSelectMemberForWalletDeduct(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id . ' (موجودی: ' . number_format($balance, 0, '.', ',') . ' تومان)'); ?>', <?php echo esc_js($balance); ?>)">
                                        <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id . ' (موجودی: ' . number_format($balance, 0, '.', ',') . ' تومان)'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <p class="description" id="balance_info">
                        موجودی فعلی: <strong id="current_balance">-</strong> تومان
                        <?php if (sc_get_wallet_max_negative_balance() > 0) : ?>
                            — حداقل مجاز: <?php echo number_format(-sc_get_wallet_max_negative_balance(), 0, '.', ','); ?> تومان
                        <?php endif; ?>
                    </p>
                </div>

                <div class="sc-wallet-field-row">
                    <label for="amount">مبلغ کسر (تومان) <span class="required">*</span></label>
                    <input type="text"
                           name="amount"
                           id="amount"
                           class="regular-text"
                           placeholder="0"
                           dir="ltr"
                           inputmode="numeric"
                           value="<?php echo isset($_POST['amount']) ? esc_attr($_POST['amount']) : ''; ?>"
                           required>
                    <input type="hidden"
                           name="amount_raw"
                           id="amount_raw"
                           value="<?php echo isset($_POST['amount_raw']) ? esc_attr($_POST['amount_raw']) : (isset($_POST['amount']) ? esc_attr($_POST['amount']) : ''); ?>">
                </div>

                <div class="sc-wallet-field-row sc-wallet-field-row--full">
                    <label for="description">توضیحات هزینه <span class="required">*</span></label>
                    <textarea name="description"
                              id="description"
                              class="large-text"
                              rows="3"
                              placeholder="مثلاً: جریمه تأخیر، هزینه اضافی و..."
                              required><?php echo isset($_POST['description']) ? esc_textarea($_POST['description']) : ''; ?></textarea>
                    <p class="description">دلیل کسر این مبلغ را وارد کنید.</p>
                </div>

            </div>
        </div>

        <p class="submit sc-wallet-deduct-submit">
            <input type="submit" name="sc_deduct_wallet" class="button button-primary" value="کسر از کیف پول">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="button button-secondary">انصراف</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    window.scSelectMemberForWalletDeduct = function(element, memberId, memberText, balance) {
        scSelectMember(element, memberId, memberText);
        if (memberId == '0') {
            $('#current_balance').text('-');
        } else if (balance !== undefined) {
            $('#current_balance').text(parseFloat(balance).toLocaleString('fa-IR'));
        } else {
            $('#current_balance').text('-');
        }
    };

    <?php if ($selected_member_id > 0) : ?>
    $('#current_balance').text('<?php echo esc_js(number_format($selected_member_balance, 0, '.', ',')); ?>');
    <?php endif; ?>
});
</script>
