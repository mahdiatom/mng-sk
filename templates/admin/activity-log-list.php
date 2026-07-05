<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}
sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_activity_log';

// پاکسازی تمام لاگ‌ها (با تأیید در فرم)
if (isset($_POST['sc_activity_log_clear_all']) && isset($_POST['sc_activity_log_clear_nonce']) && wp_verify_nonce($_POST['sc_activity_log_clear_nonce'], 'sc_activity_log_clear_all')) {
    $wpdb->query("DELETE FROM `$table`");
    wp_safe_redirect(add_query_arg('cleared', '1', admin_url('admin.php?page=sc-reports-activity-log')));
    exit;
}

$filter_date_from_shamsi = isset($_GET['date_from_shamsi']) ? sanitize_text_field(wp_unslash((string) $_GET['date_from_shamsi'])) : '';
$filter_date_to_shamsi   = isset($_GET['date_to_shamsi']) ? sanitize_text_field(wp_unslash((string) $_GET['date_to_shamsi'])) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}
$filter_entity_type = isset($_GET['entity_type']) ? sanitize_text_field(wp_unslash((string) $_GET['entity_type'])) : '';
$filter_action     = isset($_GET['action_type']) ? sanitize_text_field(wp_unslash((string) $_GET['action_type'])) : '';
$filter_user_id    = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

$today_shamsi_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
$display_date_from = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_display;
$display_date_to   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_display;

$where = ['1=1'];
$prepare_args = [];

if ($filter_date_from !== '') {
    $where[] = 'created_at >= %s';
    $prepare_args[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where[] = 'created_at <= %s';
    $prepare_args[] = $filter_date_to . ' 23:59:59';
}
if ($filter_entity_type !== '') {
    $where[] = 'entity_type = %s';
    $prepare_args[] = $filter_entity_type;
}
if ($filter_action !== '') {
    $where[] = 'action = %s';
    $prepare_args[] = $filter_action;
}
if ($filter_user_id > 0) {
    $where[] = 'user_id = %d';
    $prepare_args[] = $filter_user_id;
}

$where_sql = implode(' AND ', $where);
$per_page = 10;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$count_sql = "SELECT COUNT(*) FROM `$table` WHERE $where_sql";
$total_items = !empty($prepare_args) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare_args)) : (int) $wpdb->get_var($count_sql);
$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

$list_args = $prepare_args;
$list_args[] = $per_page;
$list_args[] = $offset;
$list_sql = "SELECT * FROM `$table` WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
$logs = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

$entity_type_labels = [
    'member' => 'عضو',
    'course' => 'دوره',
    'coach' => 'مربی',
    'notification' => 'اطلاعیه',
    'invoice' => 'صورتحساب',
    'attendance' => 'حضور و غیاب',
    'event' => 'رویداد',
    'withdrawal' => 'درخواست برداشت',
    'wallet' => 'کیف پول',
    'honor' => 'افتخار',
    'support_ticket' => 'تیکت پشتیبانی',
    'ticket' => 'تیکت',
    'settings' => 'تنظیمات',
    'setting' => 'تنظیمات',
    'expense' => 'هزینه',
];
$action_labels = [
    'created' => 'ایجاد',
    'updated' => 'ویرایش',
    'deleted' => 'حذف',
];

$distinct_entities = $wpdb->get_col("SELECT DISTINCT entity_type FROM `$table` ORDER BY entity_type");
$distinct_actions = $wpdb->get_col("SELECT DISTINCT action FROM `$table` ORDER BY action");

$active_filters_count = 0;
if ($filter_entity_type !== '') {
    $active_filters_count++;
}
if ($filter_action !== '') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '') {
    $active_filters_count++;
}
if ($filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
if ($filter_user_id > 0) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$clear_filters_url = admin_url('admin.php?page=sc-reports-activity-log');
?>

<div class="wrap sc-members-list-wrap sc-activity-log-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">لاگ فعالیت</h1>
            <p class="sc-members-list-desc">ثبت عملیات انجام‌شده در پنل ادمین (چه کسی چه عملی روی چه چیزی انجام داده).</p>
        </div>
        <div class="sc-members-list-header-actions">
            <form method="post" action="" id="sc-activity-log-clear-form" class="sc-activity-log-clear-form">
                <?php wp_nonce_field('sc_activity_log_clear_all', 'sc_activity_log_clear_nonce'); ?>
                <button type="submit" name="sc_activity_log_clear_all" value="1" class="sc-activity-log-clear-btn">پاکسازی تمام لاگ‌ها</button>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['cleared']) && $_GET['cleared'] === '1') : ?>
        <div class="notice notice-success is-dismissible sc-activity-log-notice"><p>تمام لاگ‌های فعالیت با موفقیت حذف شدند.</p></div>
    <?php endif; ?>

    <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-members-list-filters-toggle"
                    id="sc-activity-log-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-activity-log-filters-panel">
                <span class="sc-members-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-members-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-members-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($clear_filters_url); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-members-list-filters-panel" id="sc-activity-log-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-reports-activity-log">

            <div class="sc-filter-grid sc-activity-log-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="entity_type">نوع موجودیت</label>
                    <select name="entity_type" id="entity_type" class="sc-filter-control">
                        <option value="">همه</option>
                        <?php foreach ($distinct_entities as $e) : ?>
                            <option value="<?php echo esc_attr($e); ?>" <?php selected($filter_entity_type, $e); ?>><?php echo esc_html($entity_type_labels[$e] ?? $e); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="action_type">نوع عملیات</label>
                    <select name="action_type" id="action_type" class="sc-filter-control">
                        <option value="">همه</option>
                        <?php foreach ($distinct_actions as $a) : ?>
                            <option value="<?php echo esc_attr($a); ?>" <?php selected($filter_action, $a); ?>><?php echo esc_html($action_labels[$a] ?? $a); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range">
                        <input type="text" name="date_from_shamsi" id="date_from_shamsi"
                               value="<?php echo esc_attr($display_date_from); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="از تاریخ" readonly>
                        <span class="sc-date-separator">تا</span>
                        <input type="text" name="date_to_shamsi" id="date_to_shamsi"
                               value="<?php echo esc_attr($display_date_to); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="تا تاریخ" readonly>
                    </div>
                </div>
            </div>

            <div class="sc-members-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($clear_filters_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-activity-log-meta">
        <span class="sc-activity-log-count">تعداد: <strong><?php echo number_format_i18n($total_items); ?></strong> مورد</span>
    </div>

    <div class="sc-members-list-table-card">
        <?php if (empty($logs)) : ?>
            <div class="sc-activity-log-empty">رکوردی یافت نشد.</div>
        <?php else : ?>
            <div class="sc-activity-log-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ردیف</th>
                            <th style="width: 150px;">تاریخ و زمان</th>
                            <th style="width: 120px;">کاربر</th>
                            <th style="width: 90px;">عملیات</th>
                            <th style="width: 100px;">موجودیت</th>
                            <th>خلاصه</th>
                            <th style="width: 90px;">جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row = $offset + 1;
                        foreach ($logs as $log) :
                            $entity_label = $entity_type_labels[$log->entity_type] ?? $log->entity_type;
                            $action_label = $action_labels[$log->action] ?? $log->action;
                            $action_badge_class = 'sc-badge--muted';
                            if ($log->action === 'created') {
                                $action_badge_class = 'sc-badge--success';
                            } elseif ($log->action === 'updated') {
                                $action_badge_class = 'sc-badge--info';
                            } elseif ($log->action === 'deleted') {
                                $action_badge_class = 'sc-badge--danger';
                            }
                            ?>
                            <tr>
                                <td><?php echo (int) $row; ?></td>
                                <td><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($log->created_at, 'Y/m/d H:i') : $log->created_at); ?></td>
                                <td><?php echo esc_html($log->user_display_name ?: '—'); ?></td>
                                <td><span class="sc-badge <?php echo esc_attr($action_badge_class); ?>"><?php echo esc_html($action_label); ?></span></td>
                                <td><?php echo esc_html($entity_label); ?><?php echo $log->entity_id ? ' #' . (int) $log->entity_id : ''; ?></td>
                                <td><?php echo esc_html($log->summary ?: '—'); ?></td>
                                <td>
                                    <?php if (!empty($log->old_value) || !empty($log->new_value)) : ?>
                                        <button type="button" class="button button-small sc-activity-log-detail" data-old="<?php echo esc_attr($log->old_value); ?>" data-new="<?php echo esc_attr($log->new_value); ?>">مشاهده</button>
                                    <?php else : ?>
                                        <span class="sc-badge sc-badge--muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                            $row++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = ['page' => 'sc-reports-activity-log'];
                        if (!empty($filter_date_from_shamsi)) {
                            $pagination_args['date_from_shamsi'] = $filter_date_from_shamsi;
                        }
                        if (!empty($filter_date_to_shamsi)) {
                            $pagination_args['date_to_shamsi'] = $filter_date_to_shamsi;
                        }
                        if (!empty($filter_entity_type)) {
                            $pagination_args['entity_type'] = $filter_entity_type;
                        }
                        if (!empty($filter_action)) {
                            $pagination_args['action_type'] = $filter_action;
                        }
                        if ($filter_user_id > 0) {
                            $pagination_args['user_id'] = $filter_user_id;
                        }
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => $pagination_args,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div id="sc-activity-log-modal" class="sc-activity-log-modal" hidden>
    <div class="sc-activity-log-modal-card" role="dialog" aria-modal="true" aria-labelledby="sc-activity-log-modal-title">
        <div class="sc-activity-log-modal-header">
            <h3 id="sc-activity-log-modal-title">جزئیات تغییر</h3>
            <button type="button" class="sc-activity-log-modal-close" id="sc-activity-log-close" aria-label="بستن">&times;</button>
        </div>
        <div class="sc-activity-log-modal-body">
            <h4>قبل از تغییر</h4>
            <pre id="sc-activity-old" class="sc-activity-log-pre"></pre>
            <h4>بعد از تغییر</h4>
            <pre id="sc-activity-new" class="sc-activity-log-pre"></pre>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var $toggle = $('#sc-activity-log-filters-toggle');
    var $panel = $('#sc-activity-log-filters-panel');
    var $card = $toggle.closest('.sc-members-list-filters-card');
    var $label = $toggle.find('.sc-members-list-filters-toggle-label');

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

    $('#sc-activity-log-clear-form').on('submit', function() {
        return scConfirmInline(event, { type: 'warning', message: 'آیا از حذف تمام لاگ‌های فعالیت اطمینان دارید؟\nاین عمل قابل بازگشت نیست.' });
    });

    function formatLogValue(v) {
        if (v == null || v === '') return '—';
        if (typeof v === 'object') return JSON.stringify(v, null, 2);
        var s = String(v).trim();
        if (s === '') return '—';
        if (s.charAt(0) === '{' || s.charAt(0) === '[') {
            try { return JSON.stringify(JSON.parse(s), null, 2); } catch (e) { return s; }
        }
        return s;
    }

    function openActivityModal() {
        $('#sc-activity-log-modal').removeAttr('hidden').addClass('is-visible');
    }

    function closeActivityModal() {
        $('#sc-activity-log-modal').attr('hidden', true).removeClass('is-visible');
    }

    $(document).on('click', '.sc-activity-log-detail', function() {
        var oldRaw = $(this).attr('data-old') || '';
        var newRaw = $(this).attr('data-new') || '';
        $('#sc-activity-old').text(formatLogValue(oldRaw));
        $('#sc-activity-new').text(formatLogValue(newRaw));
        openActivityModal();
    });

    $('#sc-activity-log-close').on('click', closeActivityModal);
    $('#sc-activity-log-modal').on('click', function(e) {
        if (e.target === this) {
            closeActivityModal();
        }
    });
});
</script>
