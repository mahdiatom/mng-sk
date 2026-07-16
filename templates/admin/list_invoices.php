<?php
if ( ! defined('ABSPATH') ) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!class_exists('Invoices_List_Table')) {
class Invoices_List_Table extends WP_List_Table {

    public function __construct($args = []) {
        parent::__construct([
            'singular' => 'invoice',
            'plural' => 'invoices',
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
           // 'row' => 'ردیف',
            'member_name' => 'نام و نام خانوادگی کاربر',
            'order_number' => 'سفارش',
            'status' => 'وضعیت',
            'created_at' => 'تاریخ ثبت سفارش',
            'course_title' => 'جزئیات سفارش',
            'total_amount' => 'مجموع قیمت',
            'phone' => 'شماره تماس'
        ];
    }

    // public function column_row($item) {
    //     static $row_number = 0;
    //     $page = $this->get_pagenum();
    //     $per_page = $this->get_items_per_page('invoices_per_page', 20);
    //     $row_number++;
    //     return (($page - 1) * $per_page) + $row_number;
    // }
    
public function column_order_number($item) {

    // اگر سفارش ووکامرس وجود ندارد
    if (empty($item['woocommerce_order_id'])) {
        return '<span style="color:#999;">—</span>';
    }

    $order_id = absint($item['woocommerce_order_id']);

    // لینک صفحه ویرایش سفارش ووکامرس
    $url = add_query_arg(
        [
            'page'   => 'wc-orders',
            'action' => 'edit',
            'id'     => $order_id,
        ],
        admin_url('admin.php')
    );

    // شماره سفارش
    if (function_exists('wc_get_order')) {
        $order = wc_get_order($order_id);
        $order_number = $order ? $order->get_order_number() : $order_id;
    } else {
        $order_number = $order_id;
    }

    return sprintf(
        '<a href="%s" target="_blank"><strong>#%s</strong></a>',
        esc_url($url),
        esc_html($order_number)
    );
}


   public function column_member_name($item) {
    $is_guest = (isset($item['registration_source']) && $item['registration_source'] === 'guest');
    $member_name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
    if ($is_guest) {
        $guest_name = trim(($item['guest_first_name'] ?? '') . ' ' . ($item['guest_last_name'] ?? ''));
        if ($guest_name !== '') {
            $member_name = $guest_name;
        }
    }
    if ($member_name === '') {
        $member_name = $is_guest ? 'کاربر مهمان' : 'کاربر حذف شده';
    }

    $fn = trim((string) ($item['first_name'] ?? ''));
    $ln = trim((string) ($item['last_name'] ?? ''));
    if ($is_guest) {
        $fn = trim((string) ($item['guest_first_name'] ?? ''));
        $ln = trim((string) ($item['guest_last_name'] ?? ''));
    }
    $initials = '';
    if ($fn !== '') {
        $initials .= mb_substr($fn, 0, 1);
    }
    if ($ln !== '') {
        $initials .= mb_substr($ln, 0, 1);
    }
    if ($initials === '') {
        $initials = mb_substr($member_name, 0, 1) ?: '؟';
    }

    $photo = !empty($item['personal_photo']) ? $item['personal_photo'] : '';
    if ($photo && !$is_guest) {
        $avatar_html = '<span class="sc-member-avatar"><img src="' . esc_url($photo) . '" alt="" loading="lazy"></span>';
    } else {
        $avatar_html = '<span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true">' . esc_html($initials) . '</span>';
    }

    $meta = $is_guest ? '<span class="sc-badge sc-badge--danger">مهمان</span>' : '';
    $name_inner = '<span class="sc-member-identity">'
        . $avatar_html
        . '<span class="sc-member-identity-text"><span class="sc-member-name">' . esc_html($member_name) . '</span>' . $meta . '</span>'
        . '</span>';

    if (empty($item['woocommerce_order_id'])) {
        return $name_inner;
    }

    $order_id = absint($item['woocommerce_order_id']);
    $url = add_query_arg(
        [
            'page'   => 'wc-orders',
            'action' => 'edit',
            'id'     => $order_id,
        ],
        admin_url('admin.php')
    );

    return '<a href="' . esc_url($url) . '" target="_blank" style="text-decoration:none;">' . $name_inner . '</a>';
}
    protected function get_primary_column_name() {
    return 'member_name';
    }

    public function column_status($item) {
        $status = $item['status'];
        
        // تبدیل وضعیت‌های قدیمی به WooCommerce
        if ($status === 'under_review') {
            $status = 'on-hold';
        } elseif ($status === 'paid') {
            $status = 'completed';
        }
        
        // بررسی وضعیت WooCommerce اگر سفارش موجود باشد
        if (!empty($item['woocommerce_order_id']) && function_exists('wc_get_order')) {
            $order = wc_get_order($item['woocommerce_order_id']);
            if ($order) {
                $status = $order->get_status();
            }
        }
        
        $status_labels = [
            'pending' => ['label' => 'در انتظار پرداخت', 'class' => 'sc-badge--warning'],
            'on-hold' => ['label' => 'در حال بررسی', 'class' => 'sc-badge--purple'],
            'processing' => ['label' => 'پرداخت شده', 'class' => 'sc-badge--success'],
            'completed' => ['label' => 'تایید پرداخت', 'class' => 'sc-badge--success'],
            'cancelled' => ['label' => 'لغو شده', 'class' => 'sc-badge--danger'],
            'refunded' => ['label' => 'بازگشت شده', 'class' => 'sc-badge--danger'],
            'failed' => ['label' => 'ناموفق', 'class' => 'sc-badge--danger']
        ];

        $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'class' => 'sc-badge--muted'];

        return '<span class="sc-badge ' . esc_attr($status_info['class']) . '">' . esc_html($status_info['label']) . '</span>';
    }

    public function column_created_at($item) {
        return sc_date_shamsi($item['created_at'], 'Y/m/d H:i');
    }

    public function column_course_title($item) {
        $course_title = $item['course_title'] ?? '';
        $course_price = isset($item['course_price']) ? floatval($item['course_price']) : 0;
        $event_name = $item['event_name'] ?? '';
        $event_price = isset($item['event_price']) ? floatval($item['event_price']) : 0;
        $expense_name = $item['expense_name'] ?? '';
        $total_amount = isset($item['amount']) ? floatval($item['amount']) : 0;
        
        $parts = [];
        
        // نمایش دوره
        if (!empty($course_title) && trim($course_title) !== '') {
            $course_display = esc_html($course_title);
            
            $parts[] = $course_display;
        }
        
        // نمایش رویداد / مسابقه
        if (!empty($event_name) && trim($event_name) !== '') {
            $event_display = esc_html($event_name);
            
            $parts[] =  $event_display;
        }
        
        // نمایش هزینه اضافی
        if (!empty($expense_name) && trim($expense_name) !== '') {
            $expense_display = esc_html($expense_name);
            // محاسبه مبلغ هزینه اضافی
            $base_amount = $course_price > 0 ? $course_price : ($event_price > 0 ? $event_price : 0);
            $expense_amount = $total_amount - $base_amount;
            
            $parts[] = '<strong>هزینه اضافی:</strong> ' . $expense_display;
        }
        // نمایش جریمه
        $penalty_amount = isset($item['penalty_amount']) ? floatval($item['penalty_amount']) : 0;

        if ($penalty_amount > 0) {

            if (function_exists('wc_price')) {
                $penalty_display = wc_price($penalty_amount);
            } else {
                $penalty_display = number_format($penalty_amount, 0, '.', ',') . ' تومان';
            }

            $parts[] = '<span style="color:#d63638;font-weight:bold;">⚠️ جریمه تأخیر:</span> ' . $penalty_display;
        }
         // ===== اضافه کردن فیلد دلخواه ووکامرس با کلید pay =====
        if (!empty($item['woocommerce_order_id']) && function_exists('wc_get_order')) {
            $order = wc_get_order($item['woocommerce_order_id']);

            if ($order) {
                $pay_value = $order->get_meta('pay'); // دریافت مقدار فیلد دلخواه
                if (!empty($pay_value)) {
                    $parts[] = '<strong>روش پرداخت :</strong> ' . esc_html($pay_value);
                }
            }
        }
        
        if (empty($parts)) {
            return '<span style="color: #999; font-style: italic;">بدون دوره</span>';
        }
        
        return '<div style="line-height: 1.8;">' . implode('<br>', $parts) . '</div>';
    }

    public function column_total_amount($item) {
        $total = function_exists('sc_invoice_get_total_payable')
            ? sc_invoice_get_total_payable((object) $item)
            : ((float) $item['amount'] + (float) ($item['penalty_amount'] ?? 0));
        
        if (function_exists('wc_price')) {
            return wc_price($total);
        } else {
            return number_format($total, 0, '.', ',') . ' تومان';
        }
    }

    public function column_phone($item) {
        $is_guest = (isset($item['registration_source']) && $item['registration_source'] === 'guest');
        $phone = $is_guest ? ($item['guest_phone'] ?? '') : ($item['player_phone'] ?? '');
        if ($phone === '') {
            $phone = $item['player_phone'] ?? '-';
        }
        return esc_html($phone);
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['id']
        );
    }

    public function column_default($item, $column_name) {
        return '-';
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function no_items() {
        if (isset($_GET['s'])) {
            echo "صورت حسابی با این مشخصات یافت نشد!";
        } else {
            echo "هنوز صورت حسابی ثبت نشده است.";
        }
    }

    public function get_sortable_columns() {
        return [
            'created_at' => ['created_at', true],
            'status' => ['status', false],
            'total_amount' => ['amount', true]
        ];
    }

    public function get_bulk_actions() {
        return [
            'mark_pending' => 'تغییر وضعیت به: در انتظار پرداخت',
            'mark_processing' => 'تغییر وضعیت به: پرداخت شده',
            'mark_on-hold' => 'تغییر وضعیت به: در حال بررسی',
            'mark_completed' => 'تغییر وضعیت به: تایید پرداخت',
            'mark_cancelled' => 'تغییر وضعیت به: لغو شده',
            'mark_failed' => 'تغییر وضعیت به: ناموفق',
            'mark_card_to_card' => 'پرداخت کارت به کارت',
            'sales_invoice' => 'فاکتور فروش',
            'delete' => 'حذف',
            'remove_penalty' => 'حذف جریمه'

        ];
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }

        // دریافت ID های انتخاب شده
        $invoice_ids = isset($_REQUEST[$this->_args['singular']]) ? (array) wp_unslash($_REQUEST[$this->_args['singular']]) : [];
        $invoice_ids = array_map('absint', $invoice_ids);
        
        if (empty($invoice_ids)) {
            return;
        }

        // بررسی nonce
        check_admin_referer('bulk-' . $this->_args['plural']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_invoices';

        // تعیین وضعیت جدید بر اساس action
        $new_status = '';
        switch ($action) {
            case 'mark_pending':
                $new_status = 'pending';
                break;
            case 'mark_processing':
                $new_status = 'processing';
                break;
            case 'mark_on-hold':
                $new_status = 'on-hold';
                break;
            case 'mark_completed':
                $new_status = 'completed';
                break;
            case 'mark_cancelled':
                $new_status = 'cancelled';
                break;
            case 'mark_failed':
                $new_status = 'failed';
                break;
            case 'sales_invoice':
                if (function_exists('sc_sales_invoice_render_pdf_for_sc_invoices')) {
                    sc_sales_invoice_render_pdf_for_sc_invoices($invoice_ids);
                }
                wp_die(esc_html__('امکان صدور فاکتور فروش در حال حاضر در دسترس نیست.', 'sportclub-manager'));
                exit;
            case 'delete':
                // حذف صورت حساب‌ها
                foreach ($invoice_ids as $invoice_id) {
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, member_id, woocommerce_order_id, status FROM $table_name WHERE id = %d",
                        $invoice_id
                    ));
                    
                    // حذف سفارش WooCommerce اگر وجود دارد
                    if ($invoice && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
                        $order = wc_get_order($invoice->woocommerce_order_id);
                        if ($order) {
                            $order->delete(true); // true = force delete
                        }
                    }
                    
                    // حذف صورت حساب
                    $wpdb->delete($table_name, ['id' => $invoice_id], ['%d']);
                    if (function_exists('sc_log_activity') && $invoice) {
                        sc_log_activity('deleted', 'invoice', $invoice_id, 'صورتحساب #' . $invoice_id . ' (عضو ' . $invoice->member_id . ') حذف شد', (array) $invoice, null);
                    }
                }
                 do_action('sc_invoice_deleted', $invoice_id);
                
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_deleted'));
                exit;
            case 'remove_penalty':

                foreach ($invoice_ids as $invoice_id) {

                    // دریافت invoice
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE id = %d",
                        $invoice_id
                    ));

                    if (!$invoice) {
                        continue;
                    }

                    // 1. صفر کردن جریمه در دیتابیس
                    $wpdb->update(
                        $table_name,
                        [
                            'penalty_amount'   => 0,
                            'penalty_applied'  => 0,
                            'disable_penalty'  => 1, // جلوگیری از اعمال مجدد
                            'updated_at'       => current_time('mysql')
                        ],
                        ['id' => $invoice_id],
                        ['%f', '%d', '%d', '%s'],
                        ['%d']
                    );

                    // 2. حذف fee جریمه از سفارش ووکامرس
                    if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {

                        $order = wc_get_order($invoice->woocommerce_order_id);

                        if ($order) {
                            foreach ($order->get_items('fee') as $item_id => $item) {
                                $name = $item->get_name();

                                if (
                                    strpos($name, 'جریمه') !== false ||
                                    strpos($name, 'Penalty') !== false ||
                                    strpos($name, 'تأخیر') !== false
                                ) {
                                    $order->remove_item($item_id);
                                }
                            }

                            $order->calculate_totals();
                            $order->save();
                        }
                    }
                }

                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=penalty_removed'));
                exit;

            case 'mark_card_to_card':
                $updated = 0;
                foreach ($invoice_ids as $invoice_id) {
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT woocommerce_order_id FROM $table_name WHERE id = %d",
                        $invoice_id
                    ));
                    if ($invoice && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
                        $order = wc_get_order($invoice->woocommerce_order_id);
                        if ($order) {
                            $order->update_meta_data('pay', 'کارت به کارت');
                            $order->save();
                            $updated++;
                        }
                    }
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=pay_card_to_card&updated=' . $updated));
                exit;

            default:
                return;
        }

        // به‌روزرسانی وضعیت همه صورت حساب‌های انتخاب شده
        foreach ($invoice_ids as $invoice_id) {
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status, woocommerce_order_id FROM $table_name WHERE id = %d",
                $invoice_id
            ));
            if (!$invoice) {
                continue;
            }

            $prev_status = (string) $invoice->status;

            $update_data = [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ];
            $update_format = ['%s', '%s'];
            
            // اگر وضعیت completed یا processing است، payment_date را تنظیم کن
            if (in_array($new_status, ['completed', 'processing'])) {
                $update_data['payment_date'] = current_time('mysql');
                $update_format[] = '%s';
            } else {
                // برای سایر وضعیت‌ها، payment_date را null کن
                $update_data['payment_date'] = NULL;
                $update_format[] = '%s';
            }
            
            $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $invoice_id],
                $update_format,
                ['%d']
            );
            
            // اگر سفارش WooCommerce وجود دارد، وضعیت آن را هم به‌روزرسانی کن
            if (function_exists('wc_get_order') && !empty($invoice->woocommerce_order_id)) {
                $order = wc_get_order($invoice->woocommerce_order_id);
                if ($order && $order->get_status() !== $new_status) {
                    $order->update_status($new_status, 'تغییر وضعیت از طریق bulk action');
                }
            }

            // مثل مسیر منشی: انتقال به پرداخت‌شده را اعلام کن (شارژ جلسات / باز شدن قفل)
            $was_paid = in_array($prev_status, ['completed', 'paid', 'processing'], true);
            if (!$was_paid && in_array($new_status, ['completed', 'processing'], true)) {
                do_action('sc_invoice_paid', (int) $invoice_id);
            }
        }

        if (function_exists('sc_log_activity') && !empty($new_status)) {
            sc_log_activity('updated', 'invoice', 0, 'وضعیت ' . count($invoice_ids) . ' صورتحساب به «' . $new_status . '» تغییر کرد', null, ['invoice_ids' => $invoice_ids, 'new_status' => $new_status]);
        }
        // ریدایرکت با پیام موفقیت
        wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_status_updated'));
        exit;
    }

    public function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_invoices';

        // دریافت فیلترهای فعال
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_user_type = isset($_GET['filter_user_type']) ? sanitize_text_field($_GET['filter_user_type']) : 'all';
        // پردازش فیلترهای تاریخ (شمسی به میلادی)
        $filter_date_from = '';
        $filter_date_to = '';
        if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
            $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
        } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
            $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
        }
        
        if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
            $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
        } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
            $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
        }
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // ساخت WHERE clause برای شمارش
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($filter_course > 0) {
            $where_conditions[] = "i.course_id = %d";
            $where_values[] = $filter_course;
        }
        
        if ($filter_member > 0) {
            $where_conditions[] = "i.member_id = %d";
            $where_values[] = $filter_member;
        }
        if ($filter_user_type === 'member') {
            $where_conditions[] = "(r.registration_source IS NULL OR r.registration_source <> %s)";
            $where_values[] = 'guest';
        } elseif ($filter_user_type === 'guest') {
            $where_conditions[] = "r.registration_source = %s";
            $where_values[] = 'guest';
        }
        
        if ($filter_date_from) {
            $where_conditions[] = "DATE(i.created_at) >= %s";
            $where_values[] = $filter_date_from;
        }
        
        if ($filter_date_to) {
            $where_conditions[] = "DATE(i.created_at) <= %s";
            $where_values[] = $filter_date_to;
        }
        
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            if (is_numeric($search)) {
                $where_conditions[] = "(i.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = intval($search);
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            } else {
                $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            }
        }
        
        if (function_exists('sc_secretary_merge_invoice_where')) {
            sc_secretary_merge_invoice_where($where_conditions, $where_values, 'i');
        }

        $where_clause = implode(' AND ', $where_conditions);

        $count_query = "SELECT COUNT(*) FROM $table_name i 
                        LEFT JOIN {$wpdb->prefix}sc_members m ON i.member_id = m.id
                        LEFT JOIN {$wpdb->prefix}sc_event_registrations r ON i.id = r.invoice_id
                        WHERE $where_clause";
        
        if (!empty($where_values)) {
            $count_all = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $count_all = $wpdb->get_var($count_query);
        }

        $statuses = [
            'all' => 'همه',
            'pending' => 'در انتظار پرداخت',
            'processing' => 'پرداخت شده',
            'on-hold' => 'در حال بررسی',
            'completed' => 'تایید پرداخت',
            'cancelled' => 'لغو شده',
            'failed' => 'ناموفق',
            'penalty' => 'جریمه‌دارها'
        ];
        $views = [];

        foreach ($statuses as $status_key => $status_label) {
            $count = ($status_key === 'all') ? (int) $count_all : 0;
            if ($status_key !== 'all') {
                $count_where = $where_conditions;
                $count_where_values = $where_values;
                // برای completed، باید paid و completed را هم در نظر بگیریم
                if ($status_key === 'completed') {
                    $count_where[] = "(i.status = %s OR i.status = %s)";
                    $count_where_values[] = 'completed';
                    $count_where_values[] = 'paid';
                } elseif ($status_key === 'on-hold') {
                    $count_where[] = "(i.status = %s OR i.status = %s)";
                    $count_where_values[] = 'on-hold';
                    $count_where_values[] = 'under_review';
                } elseif ($status_key === 'penalty') {
                // اضافه کردن شرط برای جریمه‌دارها
                $count_where[] = "(i.penalty_amount > 0 OR i.penalty_applied = 1)";
            }
                else {
                    $count_where[] = "i.status = %s";
                    $count_where_values[] = $status_key;
                }
                $count_where_clause = implode(' AND ', $count_where);
                
                $count_query_status = "SELECT COUNT(*) FROM $table_name i 
                                       LEFT JOIN {$wpdb->prefix}sc_members m ON i.member_id = m.id
                                       LEFT JOIN {$wpdb->prefix}sc_event_registrations r ON i.id = r.invoice_id
                                       WHERE $count_where_clause";
                
                if (!empty($count_where_values)) {
                    $count = $wpdb->get_var($wpdb->prepare($count_query_status, $count_where_values));
                } else {
                    $count = $wpdb->get_var($count_query_status);
                }
            }

            $url = admin_url('admin.php?page=sc-invoices');
            if ($status_key !== 'all') {
                $url = add_query_arg('filter_status', $status_key, $url);
            }
            if ($filter_course) {
                $url = add_query_arg('filter_course', $filter_course, $url);
            }
            if ($filter_member) {
                $url = add_query_arg('filter_member', $filter_member, $url);
            }
            if ($filter_user_type !== 'all') {
                $url = add_query_arg('filter_user_type', $filter_user_type, $url);
            }
            if ($filter_date_from) {
                $url = add_query_arg('filter_date_from', $filter_date_from, $url);
            }
            if ($filter_date_to) {
                $url = add_query_arg('filter_date_to', $filter_date_to, $url);
            }
            if ($search) {
                $url = add_query_arg('s', $search, $url);
            }

            $class = ($filter_status === $status_key || ($status_key === 'all' && $filter_status === 'all')) ? 'current' : '';
            $views[$status_key] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $class,
                $status_label,
                $count
            );
        }

        return $views;
    }

    public function prepare_items() {
        $this->process_bulk_action();
        
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $members_table = $wpdb->prefix . 'sc_members';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $per_page = $this->get_items_per_page('invoices_per_page', 20);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
        
        // امنیت برای orderby
        $allowed_orderby = ['created_at', 'status', 'amount', 'id'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }
        
        // امنیت برای order
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // دریافت فیلترها
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        

        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_user_type = isset($_GET['filter_user_type']) ? sanitize_text_field($_GET['filter_user_type']) : 'all';
        // پردازش فیلترهای تاریخ (شمسی به میلادی)
        $filter_date_from = '';
        $filter_date_to = '';
        if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
            $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
        } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
            $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
        }
        
        if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
            $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
        } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
            $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
        }
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // ساخت WHERE clause
        $where_conditions = ['1=1'];
        $where_values = [];

       // فیلتر وضعیت (به جز جریمه‌دارها)
if ($filter_status !== 'all' && $filter_status !== 'penalty') {

    if ($filter_status === 'completed') {
        $where_conditions[] = "(i.status = %s OR i.status = %s)";
        $where_values[] = 'completed';
        $where_values[] = 'paid';

    } elseif ($filter_status === 'on-hold') {
        $where_conditions[] = "(i.status = %s OR i.status = %s)";
        $where_values[] = 'on-hold';
        $where_values[] = 'under_review';

    } else {
        $where_conditions[] = "i.status = %s";
        $where_values[] = $filter_status;
    }
}
// فیلتر مخصوص جریمه‌دارها
if ($filter_status === 'penalty') {
    $where_conditions[] = "(i.penalty_amount > 0 OR i.penalty_applied = 1)";
}


        if ($filter_course > 0) {
            $where_conditions[] = "i.course_id = %d";
            $where_values[] = $filter_course;
        }

        if ($filter_member > 0) {
            $where_conditions[] = "i.member_id = %d";
            $where_values[] = $filter_member;
        }
        if ($filter_user_type === 'member') {
            $where_conditions[] = "(r.registration_source IS NULL OR r.registration_source <> %s)";
            $where_values[] = 'guest';
        } elseif ($filter_user_type === 'guest') {
            $where_conditions[] = "r.registration_source = %s";
            $where_values[] = 'guest';
        }

        if ($filter_date_from) {
            $where_conditions[] = "DATE(i.created_at) >= %s";
            $where_values[] = $filter_date_from;
        }

        if ($filter_date_to) {
            $where_conditions[] = "DATE(i.created_at) <= %s";
            $where_values[] = $filter_date_to;
        }

        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            
            // اگر عدد است، جستجو بر اساس ID
            if (is_numeric($search)) {
                $where_conditions[] = "(i.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR r.guest_first_name LIKE %s OR r.guest_last_name LIKE %s OR r.guest_national_id LIKE %s)";
                $where_values[] = intval($search);
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            } else {
                // جستجو بر اساس نام، نام خانوادگی یا کد ملی
                $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR r.guest_first_name LIKE %s OR r.guest_last_name LIKE %s OR r.guest_national_id LIKE %s)";
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            }
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        // ساخت query
        $events_table = $wpdb->prefix . 'sc_events';
        $base_query = "SELECT SQL_CALC_FOUND_ROWS 
                    i.id,
                    i.member_id,
                    i.course_id,
                    i.event_id,
                    i.woocommerce_order_id,
                    i.amount,
                    i.expense_name,
                    i.penalty_amount,
                    i.tax_amount,
                    i.status,
                    i.payment_date,
                    i.created_at,
                    i.updated_at,
                    m.first_name,
                    m.last_name,
                    m.player_phone,
                    m.personal_photo,
                    c.title as course_title,
                    c.price as course_price,
                    e.name as event_name,
                    e.price as event_price,
                    r.registration_source,
                    r.guest_first_name,
                    r.guest_last_name,
                    r.guest_phone,
                    r.guest_national_id
                  FROM $invoices_table i
                  LEFT JOIN $members_table m ON i.member_id = m.id
                  LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
                  LEFT JOIN $events_table e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
                  LEFT JOIN {$wpdb->prefix}sc_event_registrations r ON i.id = r.invoice_id
                  WHERE $where_clause
                  ORDER BY i.$orderby $order
                  LIMIT %d OFFSET %d";

        $query_values = array_merge($where_values, [$per_page, $offset]);

        if (!empty($query_values)) {
            $results = $wpdb->get_results($wpdb->prepare($base_query, $query_values), ARRAY_A);
        } else {
            $results = $wpdb->get_results($base_query, ARRAY_A);
        }

        $this->set_pagination_args([
            'total_items' => $wpdb->get_var("SELECT FOUND_ROWS()"),
            'per_page' => $per_page
        ]);

        // همگام‌سازی وضعیت‌های WooCommerce با صورت حساب‌ها
        if (function_exists('wc_get_order')) {
            foreach ($results as $key => $item) {
                if (!empty($item['woocommerce_order_id'])) {
                    $order = wc_get_order($item['woocommerce_order_id']);
                    if ($order) {
                        $wc_status = $order->get_status();
                        $current_invoice_status = $item['status'];
                        
                        // sync وضعیت WooCommerce با صورت حساب
                        $sync_needed = false;
                        $new_status = $current_invoice_status;
                        
                        // تبدیل وضعیت‌های قدیمی به WooCommerce
                        if ($current_invoice_status === 'under_review') {
                            $current_invoice_status = 'on-hold';
                        } elseif ($current_invoice_status === 'paid') {
                            $current_invoice_status = 'completed';
                        }
                        
                        if ($wc_status !== $current_invoice_status) {
                            $new_status = $wc_status;
                            $sync_needed = true;
                        }
                        
                        if ($sync_needed) {
                            // به‌روزرسانی وضعیت در دیتابیس
                            $update_data = ['status' => $new_status, 'updated_at' => current_time('mysql')];
                            $update_format = ['%s', '%s'];
                            
                            if (in_array($new_status, ['completed', 'processing'])) {
                                $update_data['payment_date'] = current_time('mysql');
                                $update_format[] = '%s';
                            }
                            
                            $wpdb->update(
                                $invoices_table,
                                $update_data,
                                ['id' => $item['id']],
                                $update_format,
                                ['%d']
                            );
                            
                            // به‌روزرسانی در آرایه نتایج
                            $results[$key]['status'] = $new_status;
                        }
                    }
                }
            }
        }

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
        $this->items = $results;
    }
}
} // End if (!class_exists('Invoices_List_Table'))
?>