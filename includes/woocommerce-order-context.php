<?php
/**
 * Shared SportClub order context for checkout pay & thank-you pages.
 *
 * @package SportClub Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default SportClub invoice/order display context.
 *
 * @return array<string,mixed>
 */
function sc_get_empty_sportclub_context() {
    return [
        'invoice' => null,
        'course' => null,
        'member_course' => null,
        'private_booking' => null,
        'member' => null,
        'event' => null,
        'item_type' => 'other',
        'item_name' => '',
        'is_private' => false,
        'course_type_label' => '',
        'display_price' => 0.0,
        'start_date' => '',
        'end_date' => '',
        'chapter' => '',
        'coach_name' => '',
        'sessions' => 0,
        'team' => '',
        'skill_level' => '',
        'course_chapter_legacy' => '',
    ];
}

/**
 * Build display context from a SportClub invoice row.
 *
 * @param object|int|null $invoice Invoice row or invoice ID.
 * @return array<string,mixed>
 */
function sc_get_invoice_sportclub_context($invoice) {
    global $wpdb;

    $context = sc_get_empty_sportclub_context();

    if (is_numeric($invoice)) {
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $invoices_table WHERE id = %d LIMIT 1",
            absint($invoice)
        ));
    }

    if (!$invoice || !is_object($invoice)) {
        return $context;
    }

    $context['invoice'] = $invoice;
    $display_price = (float) $invoice->amount;
    if (!empty($invoice->penalty_applied) && (float) $invoice->penalty_amount > 0) {
        $display_price += (float) $invoice->penalty_amount;
    }
    $context['display_price'] = $display_price;

    if (!empty($invoice->member_id)) {
        $members_table = $wpdb->prefix . 'sc_members';
        $context['member'] = $wpdb->get_row($wpdb->prepare(
            "SELECT team_player, skill_level FROM $members_table WHERE id = %d LIMIT 1",
            (int) $invoice->member_id
        ));
        if ($context['member']) {
            $context['team'] = trim((string) ($context['member']->team_player ?? ''));
            $context['skill_level'] = trim((string) ($context['member']->skill_level ?? ''));
        }
    }

    if (!empty($invoice->member_course_id)) {
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $context['member_course'] = $wpdb->get_row($wpdb->prepare(
            "SELECT mc.*, ch.first_name AS coach_first_name, ch.last_name AS coach_last_name
             FROM $member_courses_table mc
             LEFT JOIN $coaches_table ch ON ch.id = mc.coach_id
             WHERE mc.id = %d LIMIT 1",
            (int) $invoice->member_course_id
        ));

        if ($context['member_course']) {
            $context['chapter'] = trim((string) ($context['member_course']->chapter ?? ''));
            $coach_name = trim((string) ($context['member_course']->coach_first_name ?? '') . ' ' . (string) ($context['member_course']->coach_last_name ?? ''));
            if ($coach_name === '' && !empty($context['member_course']->coach_id) && function_exists('sc_get_coach_display_name')) {
                $coach_name = sc_get_coach_display_name((int) $context['member_course']->coach_id);
            }
            $context['coach_name'] = $coach_name;

            $sessions = (int) ($context['member_course']->enrollment_sessions ?? 0);
            if ($sessions <= 0) {
                $sessions = (int) ($context['member_course']->total_sessions ?? 0);
            }
            $context['sessions'] = $sessions;
        }
    }

    $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bookings_table)) === $bookings_table) {
        $context['private_booking'] = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $bookings_table WHERE invoice_id = %d LIMIT 1",
            (int) $invoice->id
        ));
    }

    if (!empty($invoice->course_id)) {
        $courses_table = $wpdb->prefix . 'sc_courses';
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL LIMIT 1",
            (int) $invoice->course_id
        ));

        if ($course) {
            $context['course'] = $course;
            $context['item_type'] = 'course';
            $context['item_name'] = (string) $course->title;
            $context['course_chapter_legacy'] = trim((string) ($course->chapter ?? ''));
            $is_private = ((string) ($course->course_type ?? 'group') === 'private') || !empty($context['private_booking']);
            $context['is_private'] = $is_private;
            $context['course_type_label'] = $is_private ? 'کلاس خصوصی' : 'کلاس گروهی';

            if ($context['chapter'] === '' && $context['course_chapter_legacy'] !== '') {
                $context['chapter'] = $context['course_chapter_legacy'];
            }

            if ($is_private && $context['private_booking']) {
                $context['start_date'] = (string) $context['private_booking']->start_date;
                $context['end_date'] = (string) $context['private_booking']->end_date;
                if ((int) ($context['private_booking']->package_sessions ?? 0) > 0) {
                    $context['sessions'] = (int) $context['private_booking']->package_sessions;
                }
                if ($context['chapter'] === '' && !empty($context['private_booking']->chapter)) {
                    $context['chapter'] = trim((string) $context['private_booking']->chapter);
                }
                if ($context['coach_name'] === '' && !empty($context['private_booking']->coach_id) && function_exists('sc_get_coach_display_name')) {
                    $context['coach_name'] = sc_get_coach_display_name((int) $context['private_booking']->coach_id);
                }
            } else {
                $context['start_date'] = (string) ($course->start_date ?? '');
                $context['end_date'] = (string) ($course->end_date ?? '');
            }
        }
    } elseif (!empty($invoice->event_id)) {
        $events_table = $wpdb->prefix . 'sc_events';
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d AND deleted_at IS NULL LIMIT 1",
            (int) $invoice->event_id
        ));
        if ($event) {
            $context['event'] = $event;
            $context['item_type'] = 'event';
            $context['item_name'] = (string) $event->name;
        }
    }

    if ($context['item_type'] === 'other' && !empty($invoice->expense_name)) {
        $context['item_type'] = 'expense';
        $context['item_name'] = (string) $invoice->expense_name;
    }

    return $context;
}

/**
 * Build display context for a WooCommerce order linked to SportClub invoices.
 *
 * @param int $order_id
 * @return array<string,mixed>
 */
function sc_get_order_sportclub_context($order_id) {
    global $wpdb;

    $order_id = absint($order_id);
    if ($order_id <= 0) {
        return sc_get_empty_sportclub_context();
    }

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE woocommerce_order_id = %d LIMIT 1",
        $order_id
    ));

    return sc_get_invoice_sportclub_context($invoice);
}

/**
 * Status badge data for public invoice lists.
 *
 * @param string $status
 * @return array{label:string,class:string,bg:string,color:string,icon:string}
 */
function sc_get_invoice_status_display($status) {
    switch ($status) {
        case 'paid':
        case 'completed':
            return [
                'label' => 'تایید پرداخت',
                'class' => 'paid',
                'bg' => '#d4edda',
                'color' => '#155724',
                'icon' => '✅',
            ];
        case 'processing':
            return [
                'label' => 'پرداخت شده',
                'class' => 'processing',
                'bg' => '#d4edda',
                'color' => '#155724',
                'icon' => '✅',
            ];
        case 'under_review':
        case 'on-hold':
            return [
                'label' => 'در حال بررسی',
                'class' => 'under_review',
                'bg' => '#e5f5fa',
                'color' => '#2271b1',
                'icon' => '🔍',
            ];
        case 'cancelled':
            return [
                'label' => 'لغو شده',
                'class' => 'cancelled',
                'bg' => '#ffeaea',
                'color' => '#d63638',
                'icon' => '❌',
            ];
        case 'refunded':
            return [
                'label' => 'بازگشت شده',
                'class' => 'refunded',
                'bg' => '#ffeaea',
                'color' => '#d63638',
                'icon' => '↩️',
            ];
        case 'failed':
            return [
                'label' => 'ناموفق',
                'class' => 'failed',
                'bg' => '#ffeaea',
                'color' => '#d63638',
                'icon' => '⚠️',
            ];
        case 'pending':
        default:
            return [
                'label' => 'در انتظار پرداخت',
                'class' => 'pending',
                'bg' => '#fff3cd',
                'color' => '#856404',
                'icon' => '⏳',
            ];
    }
}

/**
 * Status badge for WooCommerce order status strings (wc-pending, etc.).
 *
 * @param string $status
 * @return array{label:string,class:string,bg:string,color:string,icon:string}
 */
function sc_get_wc_order_status_display($status) {
    $normalized = preg_replace('/^wc-/', '', (string) $status);
    if ($normalized === 'under_review') {
        $normalized = 'under_review';
    }
    if ($normalized === 'checkout-draft') {
        $normalized = 'pending';
    }
    if ($normalized === 'paid') {
        $normalized = 'completed';
    }
    return sc_get_invoice_status_display($normalized);
}

/**
 * Whether an invoice card should show expandable details.
 *
 * @param object              $invoice
 * @param array<string,mixed> $context
 */
function sc_invoice_has_expandable_details($invoice, $context) {
    if (!empty($invoice->invoice_description)) {
        return true;
    }

    $item_type = $context['item_type'] ?? 'other';
    if (in_array($item_type, ['course', 'event'], true)) {
        return true;
    }

    if (!empty($context['chapter']) || !empty($context['coach_name'])) {
        return true;
    }

    if (!empty($invoice->expense_name) && !empty($invoice->course_title)) {
        return true;
    }

    return false;
}

/**
 * Whether a shop order card should show expandable details.
 *
 * @param object              $order
 * @param array<string,mixed> $context
 */
function sc_shop_order_has_expandable_details($order, $context) {
    if (!empty($order->order_description)) {
        return true;
    }

    if (!empty($order->products_with_quantity) && substr_count((string) $order->products_with_quantity, '<br>') > 0) {
        return true;
    }

    $item_type = $context['item_type'] ?? 'other';
    if (in_array($item_type, ['course', 'event'], true)) {
        return true;
    }

    if (!empty($context['chapter']) || !empty($context['coach_name'])) {
        return true;
    }

    return false;
}

/**
 * Format a SportClub order price for display.
 *
 * @param float $amount
 * @return string
 */
function sc_format_order_display_price($amount) {
    if (function_exists('wc_price')) {
        return wc_price($amount);
    }
    return number_format((float) $amount, 0) . ' تومان';
}

/**
 * Resolve formatted order total (falls back to invoice amount when WC total is zero).
 *
 * @param WC_Order|null       $order
 * @param array<string,mixed>|null $context
 * @return string
 */
function sc_get_order_formatted_total($order, $context = null) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return '';
    }

    if ($context === null) {
        $context = sc_get_order_sportclub_context($order->get_id());
    }

    $order_total = (float) $order->get_total();
    if ($order_total > 0) {
        return $order->get_formatted_order_total();
    }

    $display_price = (float) ($context['display_price'] ?? 0);
    if ($display_price > 0) {
        return sc_format_order_display_price($display_price);
    }

    return $order->get_formatted_order_total();
}

/**
 * Resolve numeric line item display amount.
 *
 * @param WC_Order_Item $item
 * @param array<string,mixed> $context
 * @return float
 */
function sc_get_order_item_display_amount($item, $context) {
    $item_total = (float) $item->get_total();
    if ($item_total > 0) {
        return $item_total;
    }

    $display_price = (float) ($context['display_price'] ?? 0);
    if ($display_price > 0) {
        return $display_price;
    }

    return $item_total;
}

/**
 * Render course/event detail rows for order pages.
 *
 * @param array<string,mixed> $context
 * @param string              $row_class
 * @param array<string,bool>  $options
 */
function sc_render_order_item_detail_rows($context, $row_class = 'sc-thankyou-item-row', $options = []) {
    if (empty($context['item_type']) || $context['item_type'] === 'other') {
        return;
    }

    $row_class = sanitize_html_class($row_class);
    $show_dates = !isset($options['show_dates']) || $options['show_dates'];
    $show_price = !isset($options['show_price']) || $options['show_price'];

    if ($context['item_type'] === 'course') {
        if (!empty($context['course_type_label'])) {
            sc_render_order_detail_row($row_class, 'نوع دوره:', esc_html($context['course_type_label']));
        }

        $price = (float) ($context['display_price'] ?? 0);
        if ($show_price && $price > 0) {
            sc_render_order_detail_row($row_class, 'مبلغ:', wp_kses_post(sc_format_order_display_price($price)));
        }

        if (!empty($context['chapter'])) {
            sc_render_order_detail_row($row_class, 'شعبه:', esc_html($context['chapter']));
        }

        if (!empty($context['coach_name'])) {
            sc_render_order_detail_row($row_class, 'مربی:', esc_html($context['coach_name']));
        }

        if (!empty($context['sessions'])) {
            sc_render_order_detail_row($row_class, 'تعداد جلسات:', esc_html((string) (int) $context['sessions']));
        }

        if ($show_dates && !empty($context['start_date']) && function_exists('sc_date_shamsi_date_only')) {
            sc_render_order_detail_row($row_class, 'تاریخ شروع:', esc_html(sc_date_shamsi_date_only($context['start_date'])));
        }

        if ($show_dates && !empty($context['end_date']) && function_exists('sc_date_shamsi_date_only')) {
            sc_render_order_detail_row($row_class, 'تاریخ پایان:', esc_html(sc_date_shamsi_date_only($context['end_date'])));
        }

        if (!empty($context['team'])) {
            sc_render_order_detail_row($row_class, 'تیم:', esc_html($context['team']));
        }

        if (!empty($context['skill_level'])) {
            sc_render_order_detail_row($row_class, 'سطح:', esc_html($context['skill_level']));
        }

        $course = $context['course'] ?? null;
        if ($course && empty($context['is_private']) && !empty($course->capacity)) {
            sc_render_order_detail_row($row_class, 'ظرفیت:', esc_html((string) (int) $course->capacity) . ' نفر');
        }

        return;
    }

    if ($context['item_type'] === 'event' && !empty($context['event'])) {
        $event = $context['event'];
        if (!empty($event->event_date) && function_exists('sc_date_shamsi_date_only')) {
            sc_render_order_detail_row($row_class, 'تاریخ برگزاری:', esc_html(sc_date_shamsi_date_only($event->event_date)));
        }
        if (!empty($event->event_time)) {
            sc_render_order_detail_row($row_class, 'زمان:', esc_html($event->event_time));
        }
        if (!empty($event->event_location)) {
            sc_render_order_detail_row($row_class, 'مکان:', esc_html($event->event_location));
        }
    }
}

/**
 * @param string $row_class
 * @param string $label
 * @param string $value_html Already escaped HTML for value.
 */
function sc_render_order_detail_row($row_class, $label, $value_html) {
    echo '<div class="' . esc_attr($row_class) . '">';
    echo '<span class="sc-thankyou-item-label">' . esc_html($label) . '</span>';
    echo '<span class="sc-thankyou-item-value">' . $value_html . '</span>';
    echo '</div>';
}
