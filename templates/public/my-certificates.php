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
    <h2>گواهینامه‌های من</h2>

    <?php if (!empty($items)) : ?>
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>تاریخ صدور</th>
                    <th>دانلود / مشاهده</th>
                </tr>
            </thead>
            <tbody>
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
                    <tr>
                        <td data-title="عنوان"><?php echo esc_html($item->title); ?></td>
                        <td data-title="تاریخ صدور"><?php echo esc_html(sc_date_shamsi($item->created_at, 'Y/m/d')); ?></td>
                        <td data-title="دانلود / مشاهده"><a class="button" href="<?php echo esc_url($url); ?>" target="_blank">مشاهده</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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
