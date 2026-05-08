<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$user_id = get_current_user_id();
$member = $wpdb->get_row($wpdb->prepare("SELECT id FROM $members_table WHERE user_id = %d LIMIT 1", $user_id));
if (!$member) {
    echo '<div class="woocommerce-info">اطلاعات بازیکن یافت نشد.</div>';
    return;
}

$per_page = 10;
$current_page = isset($_GET['cert_page']) ? max(1, absint($_GET['cert_page'])) : 1;
$offset = ($current_page - 1) * $per_page;
$items = sc_get_member_certificates((int) $member->id, $per_page, $offset);
$total_items = sc_count_member_certificates((int) $member->id);
$total_pages = (int) ceil($total_items / $per_page);
?>

<div class="woocommerce-MyAccount-content">
    <div class="sc-my-certificates-header">
        <h2>گواهینامه‌های من</h2>
        <p>در این بخش می‌توانید گواهینامه‌های صادرشده را مشاهده و دانلود کنید.</p>
    </div>
    <?php
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $phys_status = isset($_GET['sc_phys_status']) ? sanitize_text_field(wp_unslash($_GET['sc_phys_status'])) : '';
    if ($phys_status === 'exists') {
        echo '<div class="woocommerce-error">برای این گواهینامه قبلا درخواست نسخه فیزیکی ثبت شده است. وارد بخش صورت حساب شوید و پرداخت تون تکمیل کنید.</div>';
    } elseif ($phys_status === 'paid') {
        echo '<div class="woocommerce-message">درخواست نسخه فیزیکی قبلا ثبت و پرداخت آن انجام شده است.</div>';
    } elseif ($phys_status === 'price_not_set') {
        echo '<div class="woocommerce-error">برای این قالب، مبلغ نسخه فیزیکی تنظیم نشده است.</div>';
    } elseif (in_array($phys_status, ['invalid', 'db_error', 'error'], true)) {
        echo '<div class="woocommerce-error">ایجاد درخواست نسخه فیزیکی با خطا مواجه شد.</div>';
    }
    ?>

    <?php if (!empty($items)) : ?>
        <div class="sc-my-certificates-stats">
            <div class="sc-my-certificates-stat-item">
                <span>تعداد کل</span>
                <strong><?php echo (int) $total_items; ?></strong>
            </div>
            <div class="sc-my-certificates-stat-item">
                <span>صفحه فعلی</span>
                <strong><?php echo (int) $current_page; ?> از <?php echo max(1, (int) $total_pages); ?></strong>
            </div>
        </div>

        <div class="sc-my-certificates-list">
            <?php foreach ($items as $item) : ?>
                <?php
                $url = add_query_arg(
                    [
                        'action' => 'sc_download_certificate',
                        'certificate_id' => (int) $item->id,
                        'nonce' => wp_create_nonce('sc_download_certificate_' . (int) $item->id),
                    ],
                    admin_url('admin-post.php')
                );
                ?>
                <div class="sc-my-certificate-row">
                    <div class="sc-my-certificate-row-main">
                        <div class="sc-my-certificate-row-title"><?php echo esc_html($item->title); ?></div>
                        <div class="sc-my-certificate-row-meta">
                            تاریخ صدور: <?php echo esc_html(sc_date_shamsi($item->created_at, 'Y/m/d')); ?>
                            <span class="sc-my-certificate-tracking-code">| کد رهگیری: <?php echo esc_html((string) ($item->tracking_code ?: '-')); ?></span>
                        </div>
                    </div>
                    <div class="sc-my-certificate-row-action">
                        <?php
                        $tracking_code = !empty($item->tracking_code) ? (string) $item->tracking_code : ('ID-' . (int) $item->id);
                        $invoice_description = 'درخواست نسخه فیزیکی گواهینامه به کد رهگیری: ' . $tracking_code;
                        $physical_invoice = $wpdb->get_row($wpdb->prepare(
                            "SELECT id, status FROM {$invoices_table} WHERE member_id = %d AND invoice_description = %s ORDER BY id DESC LIMIT 1",
                            (int) $member->id,
                            $invoice_description
                        ));
                        $physical_invoice_id = !empty($physical_invoice->id) ? (int) $physical_invoice->id : 0;
                        $physical_invoice_status = !empty($physical_invoice->status) ? (string) $physical_invoice->status : '';
                        $is_paid_invoice = in_array($physical_invoice_status, ['processing', 'completed', 'paid'], true);
                        $physical_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action' => 'sc_request_certificate_physical_invoice',
                                    'certificate_id' => (int) $item->id,
                                ],
                                admin_url('admin-post.php')
                            ),
                            'sc_request_certificate_physical_invoice_' . (int) $item->id
                        );
                        $invoices_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-invoices') : '#';
                        ?>
                        <?php if ($physical_invoice_id > 0) : ?>
                            <?php if ($is_paid_invoice) : ?>
                                <span class="button sc-my-certificate-action sc-my-certificate-physical-btn is-paid">ثبت و پرداخت انجام شده</span>
                            <?php else : ?>
                                <a class="button sc-my-certificate-action sc-my-certificate-physical-btn is-requested" href="<?php echo esc_url($invoices_url); ?>">صورتحساب نسخه فیزیکی ثبت شده</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <a class="button sc-my-certificate-action sc-my-certificate-physical-btn" href="<?php echo esc_url($physical_url); ?>">درخواست نسخه فیزیکی</a>
                        <?php endif; ?>
                        <a class="button sc-my-certificate-action" href="<?php echo esc_url($url); ?>" target="_blank">مشاهده و دانلود</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin-top:20px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('cert_page', '%#%'),
                        'format' => '',
                        'prev_text' => '< قبلی ',
                        'next_text' => ' بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="woocommerce-info">هنوز گواهینامه‌ای برای شما ثبت نشده است.</div>
    <?php endif; ?>
</div>

<style>
.sc-my-certificates-header p {
    margin-top: 0;
    color: #666;
}
.sc-my-certificates-stats {
    display: flex;
    gap: 12px;
    margin: 12px 0 18px;
    flex-wrap: wrap;
}
.sc-my-certificates-stat-item {
    background: #f6f7f7;
    border: 1px solid #e3e5e7;
    border-radius: 10px;
    padding: 10px 14px;
    min-width: 140px;
}
.sc-my-certificates-stat-item span {
    display: block;
    color: #666;
    font-size: 12px;
    margin-bottom: 4px;
}
.sc-my-certificates-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sc-my-certificate-row {
    border: 1px solid #e3e5e7;
    border-radius: 12px;
    padding: 12px 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.sc-my-certificate-row-title {
    font-weight: 700;
    margin-bottom: 5px;
}
.sc-my-certificate-row-meta {
    color: #666;
    font-size: 13px;
}
.sc-my-certificate-tracking-code {
    display: inline-block;
    margin-right: 8px;
    font-weight: 600;
}
.sc-my-certificate-row-action .button.sc-my-certificate-action {
    background: linear-gradient(135deg, #6d34ff 0%, #4a1fb8 100%);
    border: none;
    color: #fff;
    border-radius: 7px;
    padding: 4px 10px;
    font-size: 12px;
    line-height: 1.5;
    min-height: 30px;
}
.sc-my-certificate-row-action .button.sc-my-certificate-physical-btn {
    background: linear-gradient(135deg, #0f766e 0%, #0b4f59 100%);
}
.sc-my-certificate-row-action .button.sc-my-certificate-physical-btn.is-requested {
    background: #6b7280;
}
.sc-my-certificate-row-action .button.sc-my-certificate-physical-btn.is-paid {
    background: #16a34a;
    cursor: default;
    pointer-events: none;
}
@media (max-width: 640px) {
    .sc-my-certificate-row {
        flex-direction: column;
        align-items: stretch;
    }
    .sc-my-certificate-row-action .button.sc-my-certificate-action {
        width: 100%;
        text-align: center;
        box-sizing: border-box;
    }
}
</style>
