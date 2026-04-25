<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';

// پردازش فرم
$message = '';
$message_type = '';

// دریافت member_id از URL (اگر از صفحه مدیریت شارژ آمده باشد)
$member_id_from_url = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;

if (isset($_POST['sc_charge_wallet']) && check_admin_referer('sc_charge_wallet_nonce', 'sc_charge_wallet_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    // مبلغ را از فیلد خام (بدون جداکننده) بخوان
    $amount_raw = isset($_POST['amount_raw']) && $_POST['amount_raw'] !== '' ? $_POST['amount_raw'] : (isset($_POST['amount']) ? $_POST['amount'] : '');
    $amount = $amount_raw !== '' ? floatval(str_replace(',', '', $amount_raw)) : 0;
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';

    if ($member_id <= 0) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif ($amount <= 0) {
        $message = 'مبلغ باید بیشتر از صفر باشد.';
        $message_type = 'error';
    } else {
        // بررسی حداقل مبلغ شارژ
        $min_charge = sc_get_wallet_min_charge();
        if ($amount < $min_charge) {
            $message = 'حداقل مبلغ شارژ ' . number_format($min_charge, 0, '.', ',') . ' تومان است.';
            $message_type = 'error';
        } else {
            // بررسی حداکثر مبلغ شارژ
            $max_charge = sc_get_wallet_max_charge();
            if ($max_charge > 0 && $amount > $max_charge) {
                $message = 'حداکثر مبلغ شارژ ' . number_format($max_charge, 0, '.', ',') . ' تومان است.';
                $message_type = 'error';
            } else {
                $result = sc_charge_wallet($member_id, $amount, $description);
                if ($result['success']) {
                    $message = 'کیف پول با موفقیت شارژ شد. موجودی جدید: ' . number_format($result['balance_after'], 0, '.', ',') . ' تومان';
                    $message_type = 'success';
                    // پاک کردن فرم
                    $_POST = [];
                } else {
                    $message = $result['message'];
                    $message_type = 'error';
                }
            }
        }
    }
}

// دریافت لیست اعضا
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id, player_phone
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);
?>

<div class="wrap">
    <h1>شارژ کیف پول</h1>
    
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
</div>
<div class="wrap">
    <form method="POST" action="" >
        <?php wp_nonce_field('sc_charge_wallet_nonce', 'sc_charge_wallet_nonce'); ?>

    <div class="sc-form-flex">

    <div class="sc-form-row">

        <div class="sc-form-field">
            <label>بازیکن <span style="color: red;">*</span></label>

            <?php
            $selected_member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : (isset($member_id_from_url) ? $member_id_from_url : 0);
            $selected_member_text = 'انتخاب بازیکن';

            if ($selected_member_id > 0) {
                foreach ($members as $m) {
                    if ($m->id == $selected_member_id) {
                        $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                        break;
                    }
                }
            }
            ?>

            <div class="sc-searchable-dropdown">
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
                        ?>

                        <?php foreach ($members as $member) :
                            $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                            $display_count++;
                        ?>
                            <div class="sc-dropdown-option <?php echo $display_class; ?>"
                                data-value="<?php echo esc_attr($member->id); ?>"
                                data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                onclick="scSelectMemberForWallet(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>


        <div class="sc-form-field">
            <label for="amount">مبلغ شارژ (تومان) <span style="color: red;">*</span></label>

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

    </div>


    <div class="sc-form-description">
        <p class="description">بازیکنی که می‌خواهید کیف پولش را شارژ کنید را انتخاب کنید.</p>

        <p class="description">
            حداقل مبلغ: <?php echo number_format(sc_get_wallet_min_charge(), 0, '.', ','); ?> تومان
            <?php if (sc_get_wallet_max_charge() > 0) : ?>
                - حداکثر مبلغ: <?php echo number_format(sc_get_wallet_max_charge(), 0, '.', ','); ?> تومان
            <?php endif; ?>
        </p>
    </div>


    <div class="sc-form-field sc-full">
        <label for="description">توضیحات</label>

        <textarea name="description"
                  id="description"
                  class="large-text"
                  rows="3"
                  placeholder="توضیحات اختیاری برای این تراکنش"><?php echo isset($_POST['description']) ? esc_textarea($_POST['description']) : ''; ?></textarea>

        <p class="description">توضیحات اختیاری برای این تراکنش شارژ (مثلاً: شارژ دستی توسط مدیر)</p>
    </div>

</div>


        <p class="submit">
            <input type="submit" name="sc_charge_wallet" class="button button-primary" value="شارژ کیف پول">
            <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">بازگشت به لیست</a>
        </p>
    </form>
</div>
                    

<script>
jQuery(document).ready(function($) {
    // تابع انتخاب بازیکن برای کیف پول (استفاده از scSelectMember از admin.js)
    window.scSelectMemberForWallet = function(element, memberId, memberText) {
        // استفاده از تابع موجود scSelectMember
        scSelectMember(element, memberId, memberText);
    };
});
</script>

