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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه – گزارشات ارسال پیامک</h1>
    <hr class="wp-header-end">

    <form method="get" action="" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="sc-reports-sms-log">
        <div class="input_from_shamsi_date_smslog">
            <div>
                <label for="date_from_shamsi" style="display: block; margin-bottom: 4px; font-size: 12px;">از تاریخ</label>
                <input type="text"
                       name="date_from_shamsi"
                       id="date_from_shamsi"
                       value="<?php echo esc_attr($display_date_from); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="انتخاب تاریخ (شمسی)"
                       readonly
                       >
            </div>
            <div>
                <label for="date_to_shamsi" style="display: block; margin-bottom: 4px; font-size: 12px;">تا تاریخ</label>
                <input type="text"
                       name="date_to_shamsi"
                       id="date_to_shamsi"
                       value="<?php echo esc_attr($display_date_to); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="انتخاب تاریخ (شمسی)"
                       readonly
                       >
            </div>
            <div class="section_filter_sms_log">
                <label for="context">بخش</label>
                <select name="context" id="context">
                    <option value="">همه</option>
                    <?php foreach ($context_labels as $ctx => $label) : if ($ctx === '') continue; ?>
                        <option value="<?php echo esc_attr($ctx); ?>" <?php selected($filter_context, $ctx); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="state_filter_sms_log">
                <label for="status" style="display: block; margin-bottom: 4px; font-size: 12px;">وضعیت</label>
                <select name="status" id="status" style="width: 100%;">
                    <option value="" <?php selected($filter_status, ''); ?>>همه</option>
                    <option value="1" <?php selected($filter_status, '1'); ?>>موفق</option>
                    <option value="0" <?php selected($filter_status, '0'); ?>>ناموفق</option>
                </select>
            </div>
            <div>
                <label for="log_level" style="display: block; margin-bottom: 4px; font-size: 12px;">سطح لاگ تفصیلی</label>
                <select name="log_level" id="log_level">
                    <option value="" <?php selected($filter_log_level, ''); ?>>همه</option>
                    <option value="DEBUG" <?php selected($filter_log_level, 'DEBUG'); ?>>DEBUG</option>
                    <option value="INFO" <?php selected($filter_log_level, 'INFO'); ?>>INFO</option>
                    <option value="SUCCESS" <?php selected($filter_log_level, 'SUCCESS'); ?>>SUCCESS</option>
                    <option value="ERROR" <?php selected($filter_log_level, 'ERROR'); ?>>ERROR</option>
                </select>
            </div>
            <div>
                <label for="detail_search" style="display: block; margin-bottom: 4px; font-size: 12px;">جستجو در لاگ تفصیلی</label>
                <input type="text"
                       name="detail_search"
                       id="detail_search"
                       value="<?php echo esc_attr($filter_detail_search); ?>"
                       class="regular-text"
                       placeholder="پیام یا Data..."
                       style="width: 160px;">
            </div>
            <div>
                <label for="per_page" style="display: block; margin-bottom: 4px; font-size: 12px;">تعداد در هر صفحه</label>
                <select name="per_page" id="per_page" style="width: 70px;">
                    <?php foreach ([10, 25, 50, 100, 200] as $n) : ?>
                        <option value="<?php echo (int) $n; ?>" <?php selected($per_page, $n); ?>><?php echo (int) $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>" class="button">پاک کردن فیلتر</a>
            </div>
        </div>
    </form>

    <form method="post" action="" style="margin-bottom: 16px;" onsubmit="return scConfirmInline(event, { type: 'warning', message: 'تمام لاگ‌های گزارش ارسال پیامک و لاگ تفصیلی پاک می‌شوند. مطمئن هستید؟' });">
        <?php wp_nonce_field('sc_clear_sms_logs', '_wpnonce_clear_sms_logs'); ?>
        <input type="hidden" name="sc_clear_sms_logs" value="1">
        <button type="submit" class="button button-secondary">پاکسازی تمام لاگ‌های پیامک</button>
    </form>

    <p style="color: #646970; margin-bottom: 12px;">تعداد کل: <strong><?php echo number_format($total_items); ?></strong> رکورد</p>

    <?php if (empty($logs)) : ?>
        <p>رکوردی یافت نشد.</p>
    <?php else : ?>
        <div class="back_list_log_admin" >
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
                $delivery_failed_states = ['لیست سیاه', 'ناموفق', 'نرسیده به گوشی', 'نرسیده به مخابرات'];
                foreach ($logs as $log) :
                    $msg_preview = $log->message_text;
                    if (mb_strlen($msg_preview) > 80) {
                        $msg_preview = mb_substr($msg_preview, 0, 77) . '…';
                    }
                    $context_label = isset($context_labels[$log->context]) ? $context_labels[$log->context] : $log->context;
                    $delivery_state = isset($log->delivery_state) ? $log->delivery_state : '';
                    $has_message_id = !empty($log->message_id);
                ?>
                    <tr data-log-id="<?php echo (int) $log->id; ?>">
                        <td><?php echo (int) $row; ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->mobile); ?></td>
                        <td><?php echo esc_html($context_label); ?></td>
                        <td title="<?php echo esc_attr($log->message_text); ?>"><?php echo esc_html($msg_preview); ?></td>
                        <td>
                            <?php if ((int) $log->success === 1) : ?>
                                <span style="color: #00a32a;">پذیرش توسط سامانه</span>
                            <?php else : ?>
                                <span style="color: #d63638; font-weight: 600;">ناموفق</span>
                            <?php endif; ?>
                        </td>
                        <td class="sc-delivery-cell">
                            <?php if ($delivery_state !== '') : ?>
                                <?php if (in_array($delivery_state, $delivery_failed_states, true)) : ?>
                                    <span class="sc-delivery-state sc-delivery-fail" style="color: #d63638; font-weight: 600;" title="وضعیت واقعی از API سامانه"><?php echo esc_html($delivery_state); ?></span>
                                <?php elseif ($delivery_state === 'رسیده به گوشی') : ?>
                                    <span class="sc-delivery-state sc-delivery-ok" style="color: #00a32a; font-weight: 600;"><?php echo esc_html($delivery_state); ?></span>
                                <?php else : ?>
                                    <span class="sc-delivery-state"><?php echo esc_html($delivery_state); ?></span>
                                <?php endif; ?>
                            <?php elseif ($has_message_id) : ?>
                                <button type="button" class="button button-small sc-check-delivery-btn" data-log-id="<?php echo (int) $log->id; ?>">بررسی تحویل</button>
                            <?php else : ?>
                                —
                            <?php endif; ?>
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
                </div>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
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

    <p style="margin-top: 12px; font-size: 12px; color: #646970;">«وضعیت ارسال» یعنی پذیرش توسط سامانه؛ «وضعیت تحویل (واقعی)» از API سامانه (مثلاً لیست سیاه / رسیده به گوشی) با دکمه «بررسی تحویل» به‌روز می‌شود.</p>

    <script>
    jQuery(function($) {
        $(document).on('click', '.sc-check-delivery-btn', function() {
            var $btn = $(this);
            var logId = $btn.data('log-id');
            if (!logId) return;
            $btn.prop('disabled', true).text('در حال بررسی...');
            $.post(ajaxurl, {
                action: 'sc_check_sms_delivery_status',
                log_id: logId,
                _wpnonce: '<?php echo esc_js(wp_create_nonce('sc_check_sms_delivery')); ?>'
            }).done(function(r) {
                if (r.success && r.data && r.data.delivery_state !== undefined) {
                    var state = r.data.delivery_state;
                    var fail = ['لیست سیاه', 'ناموفق', 'نرسیده به گوشی', 'نرسیده به مخابرات'].indexOf(state) >= 0;
                    var ok = state === 'رسیده به گوشی';
                    var cls = fail ? 'sc-delivery-fail' : (ok ? 'sc-delivery-ok' : '');
                    var style = fail ? 'color:#d63638;font-weight:600;' : (ok ? 'color:#00a32a;font-weight:600;' : '');
                    $btn.closest('td').html('<span class="sc-delivery-state ' + cls + '" style="' + style + '">' + state + '</span>');
                } else {
                    alert(r.data && r.data.message ? r.data.message : 'خطا در دریافت وضعیت.');
                    $btn.prop('disabled', false).text('بررسی تحویل');
                }
            }).fail(function() {
                alert('خطا در ارتباط با سرور.');
                $btn.prop('disabled', false).text('بررسی تحویل');
            });
        });
    });
    </script>

    <hr style="margin: 32px 0 16px 0;" />

    <h2 style="margin-bottom: 12px;">لاگ تفصیلی پیامک</h2>
    <p style="color: #646970; margin-bottom: 12px;"> تعداد لاگ ها :  <strong><?php echo number_format($total_entries); ?></strong></p>

    <?php if (empty($entries)) : ?>
        <p>ورودی لاگ تفصیلی یافت نشد. از همین لحظه هر بار که پیامکی ارسال یا لاگ شود، اینجا ثبت می‌شود.</p>
    <?php else : ?>
        <div class="back_list_log_admin">
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
                    $level_style = [
                        'DEBUG' => 'color:#646970;',
                        'INFO'  => 'color:#2271b1;',
                        'SUCCESS' => 'color:#00a32a; font-weight:600;',
                        'ERROR' => 'color:#d63638; font-weight:600;',
                    ];
                    $level_css = isset($level_style[$ent->level]) ? $level_style[$ent->level] : '';
                ?>
                    <tr>
                        <td><?php echo (int) $row_ent; ?></td>
                        <td><?php echo esc_html($ent->created_at); ?></td>
                        <td><span style="<?php echo esc_attr($level_css); ?>"><?php echo esc_html($ent->level); ?></span></td>
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
            <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                <div class="tablenav-pages" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="displaying-num"><?php echo number_format($total_entries); ?> مورد</span>
                    <span class="pagination-links">
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
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
