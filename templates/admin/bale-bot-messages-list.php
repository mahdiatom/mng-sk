<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

global $wpdb;
$table = $wpdb->prefix . 'sc_bot_messages';
$list_url = admin_url('admin.php?page=sc-bale-bot-messages');
$add_url  = admin_url('admin.php?page=sc-bale-bot-send');

$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
$messages = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

$target_labels = [
    'all'             => 'همه',
    'free_users'      => 'کاربران آزاد',
    'specific'        => 'مخاطبین خاص',
    'course'          => 'دوره',
    'debtors'         => 'بدهکاران',
    'event'           => 'رویداد',
    'team'            => 'تیم',
    'level'           => 'سطح',
    'team_level'      => 'تیم + سطح',
    'wallet_negative' => 'کیف پول منفی',
    'phone'           => 'شماره',
];
$delivery_labels = [0 => 'فقط Chat ID', 1 => 'فقط سفیر', 2 => 'ترکیبی'];
?>
<div class="wrap sc-bale-list-page-header sc-bale-admin-wrap">
    <h1 class="wp-heading-inline">پیام‌های ربات بله</h1>
    <a href="<?php echo esc_url($add_url); ?>" class="page-title-action">ارسال پیام جدید</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=bale_bot')); ?>" class="page-title-action">تنظیمات ربات</a>
    <hr class="wp-header-end">
    <p class="sc-bale-list-subtitle">تاریخچه پیام‌های ارسال‌شده از ربات بله و جزئیات هر ارسال.</p>
</div>
<div class="wrap sc-bale-list-page-body sc-bale-admin-wrap">
    <?php if (isset($_GET['sent'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                پیام با موفقیت ارسال شد.
                <?php if (isset($_GET['bot']) || isset($_GET['safir'])) : ?>
                    — ربات: <?php echo (int) ($_GET['bot'] ?? 0); ?> |
                    سفیر: <?php echo (int) ($_GET['safir'] ?? 0); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="sc-bale-panel sc-bale-list-panel postbox">
        <div class="postbox-header">
            <h2>لیست پیام‌ها</h2>
        </div>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped sc-bale-messages-table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>مخاطب</th>
                        <th>حالت ارسال</th>
                        <th>دریافت‌کنندگان</th>
                        <th>ارسال موفق (ربات)</th>
                        <th>ارسال سفیر</th>
                        <th>ناموفق</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)) : ?>
                        <tr><td colspan="8" class="sc-bale-messages-empty">هنوز پیامی ارسال نشده است.</td></tr>
                    <?php else : ?>
                        <?php foreach ($messages as $msg) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($msg->title); ?></strong></td>
                                <td><?php echo esc_html($target_labels[$msg->target_type] ?? $msg->target_type); ?></td>
                                <td><?php echo esc_html($delivery_labels[(int) $msg->send_safir] ?? '-'); ?></td>
                                <td><?php echo (int) $msg->recipients_count; ?></td>
                                <td><?php echo (int) $msg->bot_sent_count; ?></td>
                                <td><?php echo (int) $msg->safir_sent_count; ?></td>
                                <td><?php echo (int) $msg->fail_count; ?></td>
                                <td><?php echo esc_html($msg->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total > $per_page) : ?>
                <div class="tablenav bottom sc-bale-list-pagination">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base'    => add_query_arg('paged', '%#%', $list_url),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => ceil($total / $per_page),
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
