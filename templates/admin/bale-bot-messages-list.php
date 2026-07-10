<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
    wp_die('دسترسی غیرمجاز.');
}

global $wpdb;
$table = $wpdb->prefix . 'sc_bot_messages';
$list_url = admin_url('admin.php?page=sc-bale-bot-messages');
$add_url  = admin_url('admin.php?page=sc-bale-bot-send');

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$filter_target = isset($_GET['filter_target']) ? sanitize_text_field(wp_unslash($_GET['filter_target'])) : '';
$filter_delivery = isset($_GET['filter_delivery']) ? sanitize_text_field(wp_unslash($_GET['filter_delivery'])) : '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';

$filter_date_from = '';
$filter_date_to = '';
if ($filter_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if ($filter_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}

$target_labels = [
    'all'             => 'همه',
    'free_users'      => 'کاربران آزاد',
    'identity_verified' => 'احراز شده',
    'identity_unverified' => 'احراز نشده',
    'registration_fee_unpaid' => 'بدون پرداخت عضویت',
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
$allowed_targets = array_keys($target_labels);

$where = ['1=1'];
$where_values = [];

if ($search !== '') {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where[] = '(title LIKE %s OR content LIKE %s)';
    $where_values[] = $like;
    $where_values[] = $like;
}

if ($filter_target !== '' && in_array($filter_target, $allowed_targets, true)) {
    $where[] = 'target_type = %s';
    $where_values[] = $filter_target;
}

if ($filter_delivery !== '' && in_array($filter_delivery, ['0', '1', '2'], true)) {
    $where[] = 'send_safir = %d';
    $where_values[] = (int) $filter_delivery;
}

if ($filter_date_from !== '') {
    $where[] = 'DATE(created_at) >= %s';
    $where_values[] = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where[] = 'DATE(created_at) <= %s';
    $where_values[] = $filter_date_to;
}

$where_sql = implode(' AND ', $where);

$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
if (!empty($where_values)) {
    $count_sql = $wpdb->prepare($count_sql, $where_values);
}
$total = (int) $wpdb->get_var($count_sql);
$total_pages = max(1, (int) ceil($total / $per_page));

$list_sql = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
$list_values = array_merge($where_values, [$per_page, $offset]);
$messages = $wpdb->get_results($wpdb->prepare($list_sql, $list_values));

$active_filters_count = 0;
if ($search !== '') {
    $active_filters_count++;
}
if ($filter_target !== '') {
    $active_filters_count++;
}
if ($filter_delivery !== '') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$pagination_args = ['page' => 'sc-bale-bot-messages'];
if ($search !== '') {
    $pagination_args['s'] = $search;
}
if ($filter_target !== '') {
    $pagination_args['filter_target'] = $filter_target;
}
if ($filter_delivery !== '') {
    $pagination_args['filter_delivery'] = $filter_delivery;
}
if ($filter_date_from_shamsi !== '') {
    $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
}
if ($filter_date_to_shamsi !== '') {
    $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
}
?>

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

<div class="wrap sc-bale-list-wrap sc-bale-admin-wrap">
    <div class="sc-bale-list-header">
        <div class="sc-bale-list-header-text">
            <h1 class="sc-bale-list-title">پیام‌های ربات بله</h1>
            <p class="sc-bale-list-desc">تاریخچه پیام‌های ارسال‌شده از ربات بله و جزئیات هر ارسال</p>
        </div>
        <div class="sc-bale-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=bale_bot')); ?>" class="sc-bale-list-secondary-btn">تنظیمات ربات</a>
            <a href="<?php echo esc_url($add_url); ?>" class="sc-bale-list-add-btn">ارسال پیام جدید</a>
        </div>
    </div>

    <div class="sc-bale-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-bale-list-filters-toolbar">
            <button type="button"
                    class="sc-bale-list-filters-toggle"
                    id="sc-bale-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-bale-filters-panel">
                <span class="sc-bale-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-bale-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-bale-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-bale-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($list_url); ?>" class="sc-bale-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-bale-list-filters-panel" id="sc-bale-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-bale-bot-messages">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="bale_search">جستجو</label>
                    <input type="search" id="bale_search" name="s" class="sc-filter-control" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن...">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_target">نوع مخاطب</label>
                    <select name="filter_target" id="filter_target" class="sc-filter-control">
                        <option value="">همه</option>
                        <?php foreach ($target_labels as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_target, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_delivery">حالت ارسال</label>
                    <select name="filter_delivery" id="filter_delivery" class="sc-filter-control">
                        <option value="">همه</option>
                        <?php foreach ($delivery_labels as $key => $label) : ?>
                            <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($filter_delivery, (string) $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range sc-bale-date-range">
                        <input type="text"
                               name="filter_date_from_shamsi"
                               id="bale_filter_date_from_shamsi"
                               value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="از تاریخ"
                               readonly>
                        <input type="text"
                               name="filter_date_to_shamsi"
                               id="bale_filter_date_to_shamsi"
                               value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="تا تاریخ"
                               readonly>
                    </div>
                </div>
            </div>

            <div class="sc-bale-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($list_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-bale-list-table-card">
        <div class="sc-bale-list-summary"><span><?php echo (int) $total; ?> پیام</span></div>
        <div class="sc-bale-table-scroll">
            <table class="wp-list-table widefat striped sc-bale-messages-table">
                <thead>
                    <tr>
                        <th class="manage-column">عنوان</th>
                        <th class="manage-column">مخاطب</th>
                        <th class="manage-column">حالت ارسال</th>
                        <th class="manage-column">دریافت‌کنندگان</th>
                        <th class="manage-column">ارسال موفق (ربات)</th>
                        <th class="manage-column">ارسال سفیر</th>
                        <th class="manage-column">ناموفق</th>
                        <th class="manage-column">تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)) : ?>
                        <tr><td colspan="8" class="sc-bale-messages-empty">پیامی یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php foreach ($messages as $msg) :
                            $delivery_key = (int) $msg->send_safir;
                            $delivery_badge = 'sc-badge--soft';
                            if ($delivery_key === 0) {
                                $delivery_badge = 'sc-badge--success';
                            } elseif ($delivery_key === 1) {
                                $delivery_badge = 'sc-badge--warning';
                            } elseif ($delivery_key === 2) {
                                $delivery_badge = 'sc-badge--purple';
                            }
                            $fail_count = (int) $msg->fail_count;
                            $date_display = function_exists('sc_date_shamsi')
                                ? sc_date_shamsi($msg->created_at, 'Y/m/d H:i')
                                : $msg->created_at;
                            ?>
                            <tr>
                                <td data-label="عنوان"><strong class="sc-bale-msg-title"><?php echo esc_html($msg->title); ?></strong></td>
                                <td data-label="مخاطب"><span class="sc-badge sc-badge--soft"><?php echo esc_html($target_labels[$msg->target_type] ?? $msg->target_type); ?></span></td>
                                <td data-label="حالت ارسال"><span class="sc-badge <?php echo esc_attr($delivery_badge); ?>"><?php echo esc_html($delivery_labels[$delivery_key] ?? '-'); ?></span></td>
                                <td data-label="دریافت‌کنندگان"><?php echo (int) $msg->recipients_count; ?></td>
                                <td data-label="ارسال موفق (ربات)"><span class="sc-bale-count sc-bale-count--ok"><?php echo (int) $msg->bot_sent_count; ?></span></td>
                                <td data-label="ارسال سفیر"><span class="sc-bale-count sc-bale-count--safir"><?php echo (int) $msg->safir_sent_count; ?></span></td>
                                <td data-label="ناموفق">
                                    <?php if ($fail_count > 0) : ?>
                                        <span class="sc-badge sc-badge--danger"><?php echo $fail_count; ?></span>
                                    <?php else : ?>
                                        <span class="sc-bale-count">0</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="تاریخ"><?php echo esc_html($date_display); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate sc-bale-list-pagination">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base'    => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format'  => '',
                        'prev_text' => '‹',
                        'next_text' => '›',
                        'current' => $paged,
                        'total'   => $total_pages,
                        'add_args' => $pagination_args,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(function($) {
    var $toggle = $('#sc-bale-filters-toggle');
    var $panel = $('#sc-bale-filters-panel');
    var $card = $toggle.closest('.sc-bale-list-filters-card');
    var $label = $toggle.find('.sc-bale-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });
});
</script>
