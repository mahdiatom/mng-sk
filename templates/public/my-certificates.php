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
                        <div class="sc-my-certificate-row-meta">تاریخ صدور: <?php echo esc_html(sc_date_shamsi($item->created_at, 'Y/m/d')); ?></div>
                    </div>
                    <div class="sc-my-certificate-row-action">
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
.sc-my-certificate-row-action .button.sc-my-certificate-action {
    background: linear-gradient(135deg, #6d34ff 0%, #4a1fb8 100%);
    border: none;
    color: #fff;
    border-radius: 8px;
    padding: 6px 12px;
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
