<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_sms_log';

// پاکسازی تمام لاگ‌های پیامک (گزارش ارسال + لاگ تفصیلی)
if (isset($_POST['sc_clear_sms_logs']) && current_user_can('manage_options')) {
    if (wp_verify_nonce($_POST['_wpnonce_clear_sms_logs'], 'sc_clear_sms_logs')) {
        $table_ent = $wpdb->prefix . 'sc_sms_log_entries';
        $wpdb->query("TRUNCATE TABLE `$table`");
        $wpdb->query("TRUNCATE TABLE `$table_ent`");
        wp_safe_redirect(add_query_arg(['page' => 'sc-reports-sms-log', 'sc_sms_cleared' => '1'], admin_url('admin.php')));
        exit;
    }
}
if (isset($_GET['sc_sms_cleared']) && $_GET['sc_sms_cleared'] === '1') {
    echo '<div class="notice notice-success is-dismissible"><p>تمام لاگ‌های گزارش ارسال پیامک و لاگ تفصیلی پاکسازی شدند.</p></div>';
}

// برچسب بخش‌ها برای نمایش
$context_labels = [
    'ticket_new' => 'تیکت جدید',
    'ticket_reply' => 'پاسخ تیکت',
    'invoice' => 'صورت‌حساب',
    'enrollment' => 'ثبت‌نام دوره',
    'reminder' => 'یادآور پرداخت',
    'absence' => 'غیبت',
    'wallet_charge_success' => 'شارژ کیف پول',
    'wallet_payment' => 'پرداخت از کیف پول',
    'wallet_low_balance' => 'موجودی کم کیف پول',
    'wallet_negative_balance' => 'موجودی منفی کیف پول',
    'insurance_expiry' => 'انقضای بیمه',
    'birthday' => 'تولد',
    'notification' => 'اطلاعیه',
    '' => '—',
];

// فیلترها — تاریخ: شمسی (بدون پیش‌فرض امروز) یا میلادی برای سازگاری
$filter_date_from_shamsi = isset($_GET['date_from_shamsi']) ? sanitize_text_field($_GET['date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['date_to_shamsi']) ? sanitize_text_field($_GET['date_to_shamsi']) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi)) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
} elseif (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
    $filter_date_from = sanitize_text_field($_GET['date_from']);
    $filter_date_from_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_from) : '';
}
if (!empty($filter_date_to_shamsi)) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
} elseif (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
    $filter_date_to = sanitize_text_field($_GET['date_to']);
    $filter_date_to_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_to) : '';
}
$filter_context    = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : '';
$filter_status     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : ''; // '', '1', '0'
$filter_log_level  = isset($_GET['log_level']) ? sanitize_text_field($_GET['log_level']) : ''; // برای لاگ تفصیلی
$filter_detail_search = isset($_GET['detail_search']) ? sanitize_text_field($_GET['detail_search']) : '';

// فقط برای نمایش در فیلدها: وقتی کاربر تاریخی نفرستاده، امروز نشان بده (در فیلتر اعمال نمی‌شود)
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
if ($filter_context !== '') {
    $where[] = 'context = %s';
    $prepare_args[] = $filter_context;
}
if ($filter_status === '1') {
    $where[] = 'success = 1';
} elseif ($filter_status === '0') {
    $where[] = 'success = 0';
}

$where_sql = implode(' AND ', $where);

$screen_per_page = get_user_meta(get_current_user_id(), 'sms_report_per_page', true);
$per_page = isset($_GET['per_page']) ? max(1, min(500, absint($_GET['per_page']))) : ($screen_per_page ? max(1, (int) $screen_per_page) : 25);
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM `$table` WHERE $where_sql";
if (!empty($prepare_args)) {
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare_args));
} else {
    $total_items = (int) $wpdb->get_var($count_sql);
}

$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

$list_sql = "SELECT * FROM `$table` WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
$prepare_args[] = $per_page;
$prepare_args[] = $offset;
$logs = $wpdb->get_results($wpdb->prepare($list_sql, $prepare_args));
if (!empty($logs)) {
    $logs = sc_sms_log_refresh_delivery_states_for_list($logs);
}

// --- لاگ تفصیلی (sc_sms_log_entries) ---
$table_entries = $wpdb->prefix . 'sc_sms_log_entries';
$entries_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_entries)) === $table_entries;

$where_ent = ['1=1'];
$prepare_ent = [];
if ($filter_date_from !== '') {
    $where_ent[] = 'created_at >= %s';
    $prepare_ent[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where_ent[] = 'created_at <= %s';
    $prepare_ent[] = $filter_date_to . ' 23:59:59';
}
if ($filter_log_level !== '' && in_array($filter_log_level, ['DEBUG', 'INFO', 'SUCCESS', 'ERROR'], true)) {
    $where_ent[] = 'level = %s';
    $prepare_ent[] = $filter_log_level;
}
if ($filter_detail_search !== '') {
    $search_like = '%' . $wpdb->esc_like($filter_detail_search) . '%';
    $where_ent[] = '(message LIKE %s OR data LIKE %s)';
    $prepare_ent[] = $search_like;
    $prepare_ent[] = $search_like;
}
$where_ent_sql = implode(' AND ', $where_ent);

$per_page_ent = $per_page;
$current_page_ent = isset($_GET['detail_paged']) ? max(1, absint($_GET['detail_paged'])) : 1;

if ($entries_table_exists) {
    $count_ent_sql = "SELECT COUNT(*) FROM `$table_entries` WHERE $where_ent_sql";
    $total_entries = !empty($prepare_ent) ? (int) $wpdb->get_var($wpdb->prepare($count_ent_sql, $prepare_ent)) : (int) $wpdb->get_var($count_ent_sql);
} else {
    $total_entries = 0;
}
$total_pages_ent = $total_entries > 0 ? ceil($total_entries / $per_page_ent) : 1;
$current_page_ent = min($current_page_ent, max(1, $total_pages_ent));
$offset_ent = ($current_page_ent - 1) * $per_page_ent;

$entries = [];
if ($entries_table_exists && $total_entries > 0) {
    $list_ent_sql = "SELECT * FROM `$table_entries` WHERE $where_ent_sql ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
    $prepare_ent[] = $per_page_ent;
    $prepare_ent[] = $offset_ent;
    $entries = $wpdb->get_results($wpdb->prepare($list_ent_sql, $prepare_ent));
}

$active_filters_count = 0;
if ($filter_date_from !== '') {
    $active_filters_count++;
}
if ($filter_date_to !== '') {
    $active_filters_count++;
}
if ($filter_context !== '') {
    $active_filters_count++;
}
if ($filter_status !== '') {
    $active_filters_count++;
}
if ($filter_log_level !== '') {
    $active_filters_count++;
}
if ($filter_detail_search !== '') {
    $active_filters_count++;
}
if (isset($_GET['per_page']) && absint($_GET['per_page']) > 0) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">گزارشات ارسال پیامک</h1>
            <p class="sc-reports-list-desc">تاریخچه ارسال پیامک‌ها، وضعیت تحویل و لاگ تفصیلی سامانه.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <form method="post" action="" onsubmit="return scConfirmInline(event, { type: 'warning', message: 'تمام لاگ‌های گزارش ارسال پیامک و لاگ تفصیلی پاک می‌شوند. مطمئن هستید؟' });">
                <?php wp_nonce_field('sc_clear_sms_logs', '_wpnonce_clear_sms_logs'); ?>
                <input type="hidden" name="sc_clear_sms_logs" value="1">
                <button type="submit" class="sc-reports-danger-btn">پاکسازی تمام لاگ‌ها</button>
            </form>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-reports-sms-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-reports-sms-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
    <form method="get" action="" class="sc-reports-list-filters-panel" id="sc-reports-sms-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
        <input type="hidden" name="page" value="sc-reports-sms-log">

        <div class="sc-filter-grid sc-sms-log-filter-grid">

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="context">بخش</label>
                <select name="context" id="context" class="sc-filter-control">
                    <option value="">همه</option>
                    <?php foreach ($context_labels as $ctx => $label) : if ($ctx === '') {
                        continue;
                    } ?>
                        <option value="<?php echo esc_attr($ctx); ?>" <?php selected($filter_context, $ctx); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="status">وضعیت ارسال</label>
                <select name="status" id="status" class="sc-filter-control">
                    <option value="" <?php selected($filter_status, ''); ?>>همه</option>
                    <option value="1" <?php selected($filter_status, '1'); ?>>موفق</option>
                    <option value="0" <?php selected($filter_status, '0'); ?>>ناموفق</option>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="log_level">سطح لاگ تفصیلی</label>
                <select name="log_level" id="log_level" class="sc-filter-control">
                    <option value="" <?php selected($filter_log_level, ''); ?>>همه</option>
                    <option value="DEBUG" <?php selected($filter_log_level, 'DEBUG'); ?>>DEBUG</option>
                    <option value="INFO" <?php selected($filter_log_level, 'INFO'); ?>>INFO</option>
                    <option value="SUCCESS" <?php selected($filter_log_level, 'SUCCESS'); ?>>SUCCESS</option>
                    <option value="ERROR" <?php selected($filter_log_level, 'ERROR'); ?>>ERROR</option>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="detail_search">جستجو در لاگ تفصیلی</label>
                <input type="search"
                       name="detail_search"
                       id="detail_search"
                       value="<?php echo esc_attr($filter_detail_search); ?>"
                       class="sc-filter-control"
                       placeholder="پیام یا Data...">
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="per_page">تعداد در هر صفحه</label>
                <select name="per_page" id="per_page" class="sc-filter-control">
                    <?php foreach ([10, 25, 50, 100, 200] as $n) : ?>
                        <option value="<?php echo (int) $n; ?>" <?php selected($per_page, $n); ?>><?php echo (int) $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">بازه تاریخ</label>
                <div class="sc-date-range">
                    <input type="text" name="date_from_shamsi" id="date_from_shamsi" value="<?php echo esc_attr($display_date_from); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="از تاریخ" readonly>
                    <span class="sc-date-separator">تا</span>
                    <input type="text" name="date_to_shamsi" id="date_to_shamsi" value="<?php echo esc_attr($display_date_to); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="تا تاریخ" readonly>
                </div>
                <input type="hidden" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                <input type="hidden" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>

        </div>

        <div class="sc-reports-list-filters-actions">
            <button type="submit" class="button button-primary">اعمال فیلتر</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </div>
    </form>
    </div>

    <p class="sc-reports-note">تعداد کل: <strong><?php echo number_format($total_items); ?></strong> رکورد</p>

    <div class="sc-reports-list-table-card">
    <?php if (empty($logs)) : ?>
        <div class="sc-reports-empty">رکوردی یافت نشد.</div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 10%;">ردیف</th>
                    <th scope="col" style="width: 25%;">تاریخ و زمان</th>
                    <th scope="col" style="width: 20%;">شماره</th>
                    <th scope="col" style="width: 10%;">بخش</th>
                    <th scope="col" style="width: 30%;">متن / توضیح</th>
                    <th scope="col" style="width: 20%;">وضعیت ارسال</th>
                    <th scope="col" style="width: 20%;">وضعیت تحویل </th>
                    <th scope="col" style="width: 25%;">پیام خطا / توضیحات</th>
                    <th scope="col" style="width: 15%;">شناسه پیام</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row = $offset + 1;
                foreach ($logs as $log) :
                    $msg_preview = $log->message_text;
                    if (mb_strlen($msg_preview) > 80) {
                        $msg_preview = mb_substr($msg_preview, 0, 77) . '…';
                    }
                    $context_label = isset($context_labels[$log->context]) ? $context_labels[$log->context] : $log->context;
                    $delivery_state = isset($log->delivery_state) ? $log->delivery_state : '';
                    $delivery_fetch_error = isset($log->delivery_fetch_error) ? $log->delivery_fetch_error : '';
                ?>
                    <tr data-log-id="<?php echo (int) $log->id; ?>">
                        <td><?php echo (int) $row; ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->mobile); ?></td>
                        <td><?php echo esc_html($context_label); ?></td>
                        <td title="<?php echo esc_attr($log->message_text); ?>"><?php echo esc_html($msg_preview); ?></td>
                        <td>
                            <?php if ((int) $log->success === 1) : ?>
                                <span class="sc-badge sc-badge--success">پذیرش توسط سامانه</span>
                            <?php else : ?>
                                <span class="sc-badge sc-badge--danger">ناموفق</span>
                            <?php endif; ?>
                        </td>
                        <td class="sc-delivery-cell">
                            <?php echo sc_render_sms_delivery_state_html($delivery_state, $delivery_fetch_error); ?>
                        </td>
                        <td>
                            <?php
                            if ((int) $log->success === 1 && $log->response_message) {
                                echo esc_html($log->response_message);
                            } elseif ($log->error_message) {
                                echo esc_html($log->error_message);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo $log->message_id ? esc_html($log->message_id) : '—'; ?></td>
                    </tr>
                <?php
                    $row++;
                endforeach;
                ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate">
                <div class="tablenav-pages" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div class="displaying-num"><?php echo number_format($total_items); ?> مورد</div>
                    <div class="pagination-links">
                        <?php
                        $pagination_args = ['page' => 'sc-reports-sms-log', 'per_page' => $per_page];
                        if (!empty($filter_date_from_shamsi)) $pagination_args['date_from_shamsi'] = $filter_date_from_shamsi;
                        if (!empty($filter_date_to_shamsi))   $pagination_args['date_to_shamsi'] = $filter_date_to_shamsi;
                        if (!empty($filter_context))  $pagination_args['context'] = $filter_context;
                        if ($filter_status !== '')    $pagination_args['status'] = $filter_status;
                        if (!empty($filter_detail_search)) $pagination_args['detail_search'] = $filter_detail_search;
                        if (!empty($filter_log_level)) $pagination_args['log_level'] = $filter_log_level;
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                            'format' => '',
                            'prev_text' => '&laquo; قبلی',
                            'next_text' => 'بعدی &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => $pagination_args,
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>

    <p class="sc-reports-note">«وضعیت ارسال» یعنی پذیرش توسط سامانه؛ «وضعیت تحویل (واقعی)» هنگام بارگذاری گزارش از API سامانه (مثلاً لیست سیاه / رسیده به گوشی) دریافت و نمایش داده می‌شود.</p>

    <div class="sc-reports-list-panel" style="margin-top:16px;">
        <div class="sc-reports-list-panel-header">
            <h2>لاگ تفصیلی پیامک</h2>
            <span class="sc-badge sc-badge--soft"><?php echo number_format($total_entries); ?> مورد</span>
        </div>
    <?php if (empty($entries)) : ?>
        <div class="sc-reports-empty">ورودی لاگ تفصیلی یافت نشد. از همین لحظه هر بار که پیامکی ارسال یا لاگ شود، اینجا ثبت می‌شود.</div>
    <?php else : ?>
        <div class="sc-reports-list-table-card" style="margin:0;box-shadow:none;border:none;padding:0;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 50px;">ردیف</th>
                    <th scope="col" style="width: 155px;">تاریخ و زمان</th>
                    <th scope="col" style="width: 85px;">سطح</th>
                    <th scope="col" style="width: 85px;">پیام</th>
                    <th scope="col" style="min-width: 200px;">Data</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_ent = $offset_ent + 1;
                foreach ($entries as $ent) :
                    $data_display = $ent->data;
                    if (!empty($data_display)) {
                        $dec = json_decode($ent->data, true);
                        $data_display = is_array($dec) ? wp_json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $ent->data;
                        if (strlen($data_display) > 1500) {
                            $data_display = substr($data_display, 0, 1497) . '…';
                        }
                    } else {
                        $data_display = '—';
                    }
                    $level_badge = [
                        'DEBUG' => 'sc-badge--muted',
                        'INFO'  => 'sc-badge--purple',
                        'SUCCESS' => 'sc-badge--success',
                        'ERROR' => 'sc-badge--danger',
                    ];
                    $level_cls = isset($level_badge[$ent->level]) ? $level_badge[$ent->level] : 'sc-badge--soft';
                ?>
                    <tr>
                        <td><?php echo (int) $row_ent; ?></td>
                        <td><?php echo esc_html($ent->created_at); ?></td>
                        <td><span class="sc-badge <?php echo esc_attr($level_cls); ?>"><?php echo esc_html($ent->level); ?></span></td>
                        <td><?php echo esc_html($ent->message); ?></td>
                        <td style="font-family: monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all;"><?php echo esc_html($data_display); ?></td>
                    </tr>
                <?php
                    $row_ent++;
                endforeach;
                ?>
            </tbody>
        </table>
        </div>
        <?php if ($total_pages_ent > 1) : ?>
            <div class="tablenav bottom sc_paginate">
                <div class="tablenav-pages">
                    <p class="pagination-links">
                        <?php
                        $pagination_args_ent = ['page' => 'sc-reports-sms-log', 'per_page' => $per_page];
                        if (!empty($filter_date_from_shamsi)) $pagination_args_ent['date_from_shamsi'] = $filter_date_from_shamsi;
                        if (!empty($filter_date_to_shamsi))   $pagination_args_ent['date_to_shamsi'] = $filter_date_to_shamsi;
                        if (!empty($filter_log_level)) $pagination_args_ent['log_level'] = $filter_log_level;
                        if (!empty($filter_detail_search)) $pagination_args_ent['detail_search'] = $filter_detail_search;
                        if (!empty($filter_context)) $pagination_args_ent['context'] = $filter_context;
                        if ($filter_status !== '') $pagination_args_ent['status'] = $filter_status;
                        echo paginate_links([
                            'base' => add_query_arg(['page' => 'sc-reports-sms-log', 'detail_paged' => '%#%'], admin_url('admin.php')),
                            'format' => '',
                            'prev_text' => '&laquo; قبلی',
                            'next_text' => 'بعدی &raquo;',
                            'total' => $total_pages_ent,
                            'current' => $current_page_ent,
                            'add_args' => array_diff_key($pagination_args_ent, ['page' => 1]),
                        ]);
                        ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>
<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-reports-sms-filters-toggle');
    var $panel = $('#sc-reports-sms-filters-panel');
    var $card = $toggle.closest('.sc-reports-list-filters-card');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
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
