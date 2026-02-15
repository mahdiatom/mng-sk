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

$filter_date_from_shamsi = isset($_GET['date_from_shamsi']) ? sanitize_text_field($_GET['date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['date_to_shamsi']) ? sanitize_text_field($_GET['date_to_shamsi']) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}
$filter_entity_type = isset($_GET['entity_type']) ? sanitize_text_field($_GET['entity_type']) : '';
$filter_action     = isset($_GET['action_type']) ? sanitize_text_field($_GET['action_type']) : '';
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
$per_page = 25;
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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لاگ فعالیت</h1>
    <p style="color: #646970; margin-top: 8px;">ثبت عملیات انجام‌شده در پنل ادمین (چه کسی چه عملی روی چه چیزی انجام داده).</p>

    <form method="get" action="" style="margin: 20px 0;">
        <input type="hidden" name="page" value="sc-reports-activity-log">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div>
                <label for="date_from_shamsi" style="display: block; margin-bottom: 4px; font-size: 12px;">از تاریخ</label>
                <input type="text" name="date_from_shamsi" id="date_from_shamsi"
                       value="<?php echo esc_attr($display_date_from); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="انتخاب تاریخ (شمسی)" readonly style="width: 140px;">
            </div>
            <div>
                <label for="date_to_shamsi" style="display: block; margin-bottom: 4px; font-size: 12px;">تا تاریخ</label>
                <input type="text" name="date_to_shamsi" id="date_to_shamsi"
                       value="<?php echo esc_attr($display_date_to); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="انتخاب تاریخ (شمسی)" readonly style="width: 140px;">
            </div>
            <div>
                <label for="entity_type" style="display: block; margin-bottom: 4px; font-size: 12px;">نوع موجودیت</label>
                <select name="entity_type" id="entity_type" style="width: 140px;">
                    <option value="">همه</option>
                    <?php foreach ($distinct_entities as $e) : ?>
                        <option value="<?php echo esc_attr($e); ?>" <?php selected($filter_entity_type, $e); ?>><?php echo esc_html($entity_type_labels[$e] ?? $e); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="action_type" style="display: block; margin-bottom: 4px; font-size: 12px;">نوع عملیات</label>
                <select name="action_type" id="action_type" style="width: 120px;">
                    <option value="">همه</option>
                    <?php foreach ($distinct_actions as $a) : ?>
                        <option value="<?php echo esc_attr($a); ?>" <?php selected($filter_action, $a); ?>><?php echo esc_html($action_labels[$a] ?? $a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-activity-log')); ?>" class="button">پاک کردن</a>
            </div>
        </div>
    </form>

    <p style="color: #646970; margin-bottom: 12px;">تعداد: <strong><?php echo number_format_i18n($total_items); ?></strong> مورد</p>

    <?php if (empty($logs)) : ?>
        <p>رکوردی یافت نشد.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ردیف</th>
                    <th style="width: 150px;">تاریخ و زمان</th>
                    <th style="width: 120px;">کاربر</th>
                    <th style="width: 90px;">عملیات</th>
                    <th style="width: 100px;">موجودیت</th>
                    <th>خلاصه</th>
                    <th style="width: 80px;">جزئیات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row = $offset + 1;
                foreach ($logs as $log) :
                    $entity_label = $entity_type_labels[$log->entity_type] ?? $log->entity_type;
                    $action_label = $action_labels[$log->action] ?? $log->action;
                ?>
                    <tr>
                        <td><?php echo (int) $row; ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->user_display_name ?: '—'); ?></td>
                        <td><?php echo esc_html($action_label); ?></td>
                        <td><?php echo esc_html($entity_label); ?><?php echo $log->entity_id ? ' #' . (int) $log->entity_id : ''; ?></td>
                        <td><?php echo esc_html($log->summary ?: '—'); ?></td>
                        <td>
                            <?php if (!empty($log->old_value) || !empty($log->new_value)) : ?>
                                <button type="button" class="button button-small sc-activity-log-detail" data-old="<?php echo esc_attr($log->old_value); ?>" data-new="<?php echo esc_attr($log->new_value); ?>">مشاهده</button>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    $row++;
                endforeach;
                ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format_i18n($total_items); ?> مورد</span>
                    <span class="pagination-links">
                        <?php
                        $base = add_query_arg('paged', '%#%', admin_url('admin.php'));
                        $base = remove_query_arg('paged', $base);
                        $base = add_query_arg('page', 'sc-reports-activity-log', $base);
                        if (!empty($filter_date_from_shamsi)) $base = add_query_arg('date_from_shamsi', $filter_date_from_shamsi, $base);
                        if (!empty($filter_date_to_shamsi)) $base = add_query_arg('date_to_shamsi', $filter_date_to_shamsi, $base);
                        if (!empty($filter_entity_type)) $base = add_query_arg('entity_type', $filter_entity_type, $base);
                        if (!empty($filter_action)) $base = add_query_arg('action_type', $filter_action, $base);
                        if ($filter_user_id > 0) $base = add_query_arg('user_id', $filter_user_id, $base);
                        echo paginate_links([
                            'base' => $base,
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="sc-activity-log-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto;">
    <div style="background: #fff; margin: 5% auto; padding: 20px; max-width: 700px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0;">قبل از تغییر</h3>
        <pre id="sc-activity-old" style="background: #f5f5f5; padding: 12px; overflow: auto; max-height: 200px; font-size: 12px;"></pre>
        <h3>بعد از تغییر</h3>
        <pre id="sc-activity-new" style="background: #f5f5f5; padding: 12px; overflow: auto; max-height: 200px; font-size: 12px;"></pre>
        <p><button type="button" class="button" id="sc-activity-log-close">بستن</button></p>
    </div>
</div>

<script>
jQuery(function($) {
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
    $(document).on('click', '.sc-activity-log-detail', function() {
        var oldRaw = $(this).attr('data-old') || '';
        var newRaw = $(this).attr('data-new') || '';
        $('#sc-activity-old').text(formatLogValue(oldRaw));
        $('#sc-activity-new').text(formatLogValue(newRaw));
        $('#sc-activity-log-modal').show();
    });
    $('#sc-activity-log-close').on('click', function() {
        $('#sc-activity-log-modal').hide();
    });
    $('#sc-activity-log-modal').on('click', function(e) {
        if (e.target === this) $(this).hide();
    });
});
</script>
