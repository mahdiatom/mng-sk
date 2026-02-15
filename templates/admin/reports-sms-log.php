<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_sms_log';

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

$per_page = 25;
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

$per_page_ent = 50;
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
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div>
                <label for="date_from_shamsi" style="display: block; margin-bottom: 4px; font-size: 12px;">از تاریخ</label>
                <input type="text"
                       name="date_from_shamsi"
                       id="date_from_shamsi"
                       value="<?php echo esc_attr($display_date_from); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="انتخاب تاریخ (شمسی)"
                       readonly
                       style="width: 140px;">
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
                       style="width: 140px;">
            </div>
            <div>
                <label for="context" style="display: block; margin-bottom: 4px; font-size: 12px;">بخش</label>
                <select name="context" id="context">
                    <option value="">همه</option>
                    <?php foreach ($context_labels as $ctx => $label) : if ($ctx === '') continue; ?>
                        <option value="<?php echo esc_attr($ctx); ?>" <?php selected($filter_context, $ctx); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" style="display: block; margin-bottom: 4px; font-size: 12px;">وضعیت</label>
                <select name="status" id="status">
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
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>" class="button">پاک کردن</a>
            </div>
        </div>
    </form>

    <p style="color: #646970; margin-bottom: 12px;">تعداد کل: <strong><?php echo number_format($total_items); ?></strong> رکورد</p>

    <?php if (empty($logs)) : ?>
        <p>رکوردی یافت نشد.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 50px;">ردیف</th>
                    <th scope="col" style="width: 140px;">تاریخ و زمان</th>
                    <th scope="col" style="width: 110px;">شماره</th>
                    <th scope="col" style="width: 120px;">بخش</th>
                    <th scope="col">متن / توضیح</th>
                    <th scope="col" style="width: 85px;">وضعیت ارسال</th>
                    <th scope="col" style="width: 160px;">وضعیت تحویل (واقعی)</th>
                    <th scope="col" style="width: 200px;">پیام خطا / توضیحات</th>
                    <th scope="col" style="width: 90px;">شناسه پیام</th>
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

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_items); ?> مورد</span>
                    <span class="pagination-links">
                        <?php
                        $base = add_query_arg('paged', '%#%');
                        $base = remove_query_arg('paged', $base);
                        if (!empty($filter_date_from_shamsi)) $base = add_query_arg('date_from_shamsi', $filter_date_from_shamsi, $base);
                        if (!empty($filter_date_to_shamsi))   $base = add_query_arg('date_to_shamsi', $filter_date_to_shamsi, $base);
                        if (!empty($filter_context))  $base = add_query_arg('context', $filter_context, $base);
                        if ($filter_status !== '')    $base = add_query_arg('status', $filter_status, $base);
                        if (!empty($filter_detail_search)) $base = add_query_arg('detail_search', $filter_detail_search, $base);
                        if (!empty($filter_log_level)) $base = add_query_arg('log_level', $filter_log_level, $base);
                        $base = add_query_arg('page', 'sc-reports-sms-log', $base);
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
    <p style="color: #646970; margin-bottom: 12px;">همهٔ ورودی‌های لاگ (DEBUG, INFO, SUCCESS, ERROR) مانند فایل sc-sms-log — تعداد: <strong><?php echo number_format($total_entries); ?></strong></p>

    <?php if (empty($entries)) : ?>
        <p>ورودی لاگ تفصیلی یافت نشد. از همین لحظه هر بار که پیامکی ارسال یا لاگ شود، اینجا ثبت می‌شود.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 50px;">ردیف</th>
                    <th scope="col" style="width: 155px;">تاریخ و زمان</th>
                    <th scope="col" style="width: 85px;">سطح</th>
                    <th scope="col">پیام</th>
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

        <?php if ($total_pages_ent > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_entries); ?> مورد</span>
                    <span class="pagination-links">
                        <?php
                        $base_ent = add_query_arg('detail_paged', '%#%');
                        $base_ent = remove_query_arg('detail_paged', $base_ent);
                        if (!empty($filter_date_from_shamsi)) $base_ent = add_query_arg('date_from_shamsi', $filter_date_from_shamsi, $base_ent);
                        if (!empty($filter_date_to_shamsi))   $base_ent = add_query_arg('date_to_shamsi', $filter_date_to_shamsi, $base_ent);
                        if (!empty($filter_log_level)) $base_ent = add_query_arg('log_level', $filter_log_level, $base_ent);
                        if (!empty($filter_detail_search)) $base_ent = add_query_arg('detail_search', $filter_detail_search, $base_ent);
                        $base_ent = add_query_arg('page', 'sc-reports-sms-log', $base_ent);
                        echo paginate_links([
                            'base' => $base_ent,
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages_ent,
                            'current' => $current_page_ent,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
