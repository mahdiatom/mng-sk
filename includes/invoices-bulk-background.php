<?php
/**
 * Background bulk processing for invoices list.
 *
 * Provides an AJAX endpoint that processes a small batch of invoices per request,
 * allowing the admin to apply bulk actions (status change, delete, remove penalty,
 * mark as card-to-card) on large selections without hitting PHP timeouts.
 *
 * The original synchronous bulk handler in templates/admin/list_invoices.php is
 * kept as a fallback when JavaScript is not available.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map of supported bulk actions to translatable labels (for the progress UI).
 */
function sc_invoices_bulk_bg_supported_actions() {
    return [
        'mark_pending'      => 'تغییر وضعیت به: در انتظار پرداخت',
        'mark_processing'   => 'تغییر وضعیت به: پرداخت شده',
        'mark_on-hold'      => 'تغییر وضعیت به: در حال بررسی',
        'mark_completed'    => 'تغییر وضعیت به: تایید پرداخت',
        'mark_cancelled'    => 'تغییر وضعیت به: لغو شده',
        'mark_failed'       => 'تغییر وضعیت به: ناموفق',
        'mark_card_to_card' => 'پرداخت کارت به کارت',
        'delete'            => 'حذف صورت‌حساب',
        'remove_penalty'    => 'حذف جریمه',
    ];
}

/**
 * Status-change action -> Woo/sc_invoices status mapping.
 */
function sc_invoices_bulk_bg_status_map() {
    return [
        'mark_pending'    => 'pending',
        'mark_processing' => 'processing',
        'mark_on-hold'    => 'on-hold',
        'mark_completed'  => 'completed',
        'mark_cancelled'  => 'cancelled',
        'mark_failed'     => 'failed',
    ];
}

/**
 * Process one invoice for one bulk action. Returns ['success'=>bool,'message'=>string].
 */
function sc_invoices_bulk_bg_process_one($action, $invoice_id) {
    global $wpdb;

    $invoice_id = absint($invoice_id);
    if (!$invoice_id) {
        return ['success' => false, 'message' => 'شناسه نامعتبر است.'];
    }

    $table_name = $wpdb->prefix . 'sc_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $invoice_id
    ));
    if (!$invoice) {
        return ['success' => false, 'message' => 'صورت‌حساب یافت نشد.'];
    }

    $status_map = sc_invoices_bulk_bg_status_map();

    /* ---------- 1) Status change actions ---------- */
    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];

        $update_data = [
            'status'     => $new_status,
            'updated_at' => current_time('mysql'),
        ];
        $update_format = ['%s', '%s'];

        if (in_array($new_status, ['completed', 'processing'], true)) {
            $update_data['payment_date'] = current_time('mysql');
        } else {
            $update_data['payment_date'] = null;
        }
        $update_format[] = '%s';

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $invoice_id],
            $update_format,
            ['%d']
        );

        if ($updated === false) {
            return ['success' => false, 'message' => 'به‌روزرسانی پایگاه‌داده ناموفق بود.'];
        }

        if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order && $order->get_status() !== $new_status) {
                $order->update_status($new_status, 'تغییر وضعیت از طریق پردازش پس‌زمینه صورت‌حساب‌ها');
            }
        }

        return ['success' => true, 'message' => 'وضعیت به «' . $new_status . '» تغییر کرد.'];
    }

    /* ---------- 2) Card-to-card payment method ---------- */
    if ($action === 'mark_card_to_card') {
        if (empty($invoice->woocommerce_order_id) || !function_exists('wc_get_order')) {
            return ['success' => false, 'message' => 'سفارش ووکامرس برای این صورت‌حساب وجود ندارد.'];
        }
        $order = wc_get_order($invoice->woocommerce_order_id);
        if (!$order) {
            return ['success' => false, 'message' => 'سفارش ووکامرس یافت نشد.'];
        }
        $order->update_meta_data('pay', 'کارت به کارت');
        $order->save();
        return ['success' => true, 'message' => 'روش پرداخت «کارت به کارت» ثبت شد.'];
    }

    /* ---------- 3) Delete invoice (and Woo order) ---------- */
    if ($action === 'delete') {
        $invoice_snapshot = (array) $invoice;

        if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order) {
                $order->delete(true);
            }
        }

        $deleted = $wpdb->delete($table_name, ['id' => $invoice_id], ['%d']);
        if ($deleted === false) {
            return ['success' => false, 'message' => 'حذف از پایگاه‌داده ناموفق بود.'];
        }

        if (function_exists('sc_log_activity')) {
            sc_log_activity(
                'deleted',
                'invoice',
                $invoice_id,
                'صورتحساب #' . $invoice_id . ' (عضو ' . $invoice->member_id . ') حذف شد',
                $invoice_snapshot,
                null
            );
        }

        do_action('sc_invoice_deleted', $invoice_id);

        return ['success' => true, 'message' => 'صورت‌حساب حذف شد.'];
    }

    /* ---------- 4) Remove penalty ---------- */
    if ($action === 'remove_penalty') {
        $wpdb->update(
            $table_name,
            [
                'penalty_amount'  => 0,
                'penalty_applied' => 0,
                'disable_penalty' => 1,
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $invoice_id],
            ['%f', '%d', '%d', '%s'],
            ['%d']
        );

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

        return ['success' => true, 'message' => 'جریمه حذف شد.'];
    }

    return ['success' => false, 'message' => 'عملیات نامعتبر است.'];
}

/**
 * AJAX: process a single batch of invoice IDs for the given bulk action.
 *
 * Expected POST:
 *   - nonce        : sc_invoices_bulk_background nonce
 *   - bulk_action  : one of supported actions
 *   - invoice_ids  : array<int>
 */
add_action('wp_ajax_sc_invoices_bulk_process_batch', 'sc_ajax_invoices_bulk_process_batch');
function sc_ajax_invoices_bulk_process_batch() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    check_ajax_referer('sc_invoices_bulk_background', 'nonce');

    $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
    $ids    = isset($_POST['invoice_ids']) ? (array) $_POST['invoice_ids'] : [];
    $ids    = array_values(array_filter(array_map('absint', $ids)));

    $supported = sc_invoices_bulk_bg_supported_actions();
    if ($action === '' || !isset($supported[$action])) {
        wp_send_json_error(['message' => 'عملیات انتخاب‌شده پشتیبانی نمی‌شود.']);
    }
    if (empty($ids)) {
        wp_send_json_error(['message' => 'هیچ آیتمی برای پردازش ارسال نشده است.']);
    }

    // Avoid output buffering surprises and try to extend script time per batch.
    @ignore_user_abort(true);
    @set_time_limit(120);

    $results       = [];
    $success_count = 0;
    $error_count   = 0;

    foreach ($ids as $id) {
        $r = sc_invoices_bulk_bg_process_one($action, $id);
        if (!empty($r['success'])) {
            $success_count++;
        } else {
            $error_count++;
        }
        $results[] = [
            'id'      => $id,
            'success' => !empty($r['success']),
            'message' => isset($r['message']) ? $r['message'] : '',
        ];
    }

    wp_send_json_success([
        'results'       => $results,
        'success_count' => $success_count,
        'error_count'   => $error_count,
    ]);
}

/**
 * AJAX: log aggregate result after the whole job is finished (called once by JS).
 */
add_action('wp_ajax_sc_invoices_bulk_finalize', 'sc_ajax_invoices_bulk_finalize');
function sc_ajax_invoices_bulk_finalize() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    check_ajax_referer('sc_invoices_bulk_background', 'nonce');

    $action        = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
    $success_count = isset($_POST['success_count']) ? absint($_POST['success_count']) : 0;
    $error_count   = isset($_POST['error_count']) ? absint($_POST['error_count']) : 0;
    $ids           = isset($_POST['invoice_ids']) ? (array) $_POST['invoice_ids'] : [];
    $ids           = array_values(array_filter(array_map('absint', $ids)));

    $supported = sc_invoices_bulk_bg_supported_actions();
    if ($action === '' || !isset($supported[$action])) {
        wp_send_json_error(['message' => 'عملیات انتخاب‌شده پشتیبانی نمی‌شود.']);
    }

    if (function_exists('sc_log_activity')) {
        $status_map = sc_invoices_bulk_bg_status_map();

        if (isset($status_map[$action])) {
            sc_log_activity(
                'updated',
                'invoice',
                0,
                'وضعیت ' . $success_count . ' صورت‌حساب به «' . $status_map[$action] . '» تغییر کرد (پردازش پس‌زمینه)',
                null,
                [
                    'invoice_ids'   => $ids,
                    'new_status'    => $status_map[$action],
                    'success_count' => $success_count,
                    'error_count'   => $error_count,
                ]
            );
        } elseif ($action === 'delete') {
            sc_log_activity(
                'deleted',
                'invoice',
                0,
                $success_count . ' صورت‌حساب به‌صورت دسته‌ای حذف شد (پردازش پس‌زمینه)',
                null,
                [
                    'invoice_ids'   => $ids,
                    'success_count' => $success_count,
                    'error_count'   => $error_count,
                ]
            );
        } elseif ($action === 'remove_penalty') {
            sc_log_activity(
                'updated',
                'invoice',
                0,
                'جریمه ' . $success_count . ' صورت‌حساب حذف شد (پردازش پس‌زمینه)',
                null,
                ['invoice_ids' => $ids, 'success_count' => $success_count, 'error_count' => $error_count]
            );
        } elseif ($action === 'mark_card_to_card') {
            sc_log_activity(
                'updated',
                'invoice',
                0,
                'روش پرداخت «کارت به کارت» برای ' . $success_count . ' صورت‌حساب ثبت شد (پردازش پس‌زمینه)',
                null,
                ['invoice_ids' => $ids, 'success_count' => $success_count, 'error_count' => $error_count]
            );
        }
    }

    wp_send_json_success(['ok' => true]);
}

/**
 * Enqueue background-bulk JS/CSS only on the invoices list page.
 */
add_action('admin_enqueue_scripts', 'sc_invoices_bulk_bg_enqueue');
function sc_invoices_bulk_bg_enqueue($hook) {
    $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($current_page !== 'sc-invoices') {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_style(
        'sc-invoices-bulk-bg-css',
        SC_ASSETS_URL . 'css/invoices-bulk-admin.css',
        [],
        file_exists(SC_ASSETS_DIR . 'css/invoices-bulk-admin.css')
            ? (string) filemtime(SC_ASSETS_DIR . 'css/invoices-bulk-admin.css')
            : '1.0'
    );

    wp_enqueue_script(
        'sc-invoices-bulk-bg-js',
        SC_ASSETS_URL . 'js/invoices-bulk-admin.js',
        ['jquery'],
        file_exists(SC_ASSETS_DIR . 'js/invoices-bulk-admin.js')
            ? (string) filemtime(SC_ASSETS_DIR . 'js/invoices-bulk-admin.js')
            : '1.0',
        true
    );

    wp_localize_script('sc-invoices-bulk-bg-js', 'scInvoicesBulkBg', [
        'ajaxurl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('sc_invoices_bulk_background'),
        'batchSize'  => 3,
        'actions'    => sc_invoices_bulk_bg_supported_actions(),
        'reloadUrl'  => add_query_arg(['sc_status' => 'bulk_status_updated'], admin_url('admin.php?page=sc-invoices')),
        'i18n'       => [
            'title'           => 'پردازش پس‌زمینه صورت‌حساب‌ها',
            'preparing'       => 'در حال آماده‌سازی...',
            'processing'      => 'در حال پردازش',
            'of'              => 'از',
            'success'         => 'موفق',
            'errors'          => 'ناموفق',
            'remaining'       => 'باقی‌مانده',
            'elapsed'         => 'زمان سپری‌شده',
            'cancel'          => 'توقف',
            'cancelling'      => 'در حال توقف...',
            'cancelled'       => 'پردازش متوقف شد.',
            'completed'       => 'پردازش با موفقیت پایان یافت.',
            'completedWithErr'=> 'پردازش پایان یافت (با خطا).',
            'close'           => 'بستن',
            'reload'          => 'بارگذاری مجدد جدول',
            'noSelection'     => 'هیچ صورت‌حسابی انتخاب نشده است.',
            'noAction'        => 'لطفاً ابتدا یک عملیات از فهرست انتخاب کنید.',
            'confirmDelete'   => 'آیا از حذف صورت‌حساب‌های انتخاب‌شده مطمئن هستید؟ این عملیات غیرقابل بازگشت است.',
            'confirmStatus'   => 'تغییر وضعیت برای صورت‌حساب‌های انتخاب‌شده در پس‌زمینه انجام می‌شود. ادامه می‌دهید؟',
            'errorPrefix'     => 'خطا: ',
            'networkError'    => 'خطای ارتباط با سرور. تلاش مجدد...',
            'idLabel'         => 'صورت‌حساب #',
        ],
    ]);
}
