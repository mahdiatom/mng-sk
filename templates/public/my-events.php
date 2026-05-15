<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($current_page) ? max(1, (int) $current_page) : (isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1);
$total_pages    = isset($total_pages) ? max(1, (int) $total_pages) : 1;
$total_events   = isset($total_events) ? (int) $total_events : (isset($count_events) ? (int) $count_events : 0);
$user_events    = isset($user_events) && is_array($user_events) ? $user_events : [];

$events_base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-my-events') : home_url('/my-account/sc-my-events/');

$sc_mev_event_type_label = static function ($type) {
    $t = strtolower((string) $type);
    $map = [
        'event'        => 'رویداد',
        'competition'  => 'مسابقه',
        'workshop'     => 'کارگاه',
        'tournament'   => 'تورنمنت',
    ];
    return isset($map[$t]) ? $map[$t] : ($t !== '' ? $t : 'رویداد');
};

$sc_mev_invoice_badge = static function ($status) {
    $s = strtolower(trim((string) $status));
    if ($s === '') {
        return ['label' => 'بدون فاکتور', 'class' => 'sc-mev-badge sc-mev-badge--muted'];
    }
    $map = [
        'paid'        => ['label' => 'پرداخت‌شده', 'class' => 'sc-mev-badge sc-mev-badge--success'],
        'completed'   => ['label' => 'تکمیل‌شده', 'class' => 'sc-mev-badge sc-mev-badge--success'],
        'processing'  => ['label' => 'در حال پردازش', 'class' => 'sc-mev-badge sc-mev-badge--info'],
        'pending'     => ['label' => 'در انتظار پرداخت', 'class' => 'sc-mev-badge sc-mev-badge--warning'],
        'on-hold'     => ['label' => 'معلق', 'class' => 'sc-mev-badge sc-mev-badge--warning'],
        'cancelled'   => ['label' => 'لغوشده', 'class' => 'sc-mev-badge sc-mev-badge--danger'],
        'failed'      => ['label' => 'ناموفق', 'class' => 'sc-mev-badge sc-mev-badge--danger'],
        'refunded'    => ['label' => 'بازگشت وجه', 'class' => 'sc-mev-badge sc-mev-badge--muted'],
    ];
    return isset($map[$s]) ? $map[$s] : ['label' => $status, 'class' => 'sc-mev-badge sc-mev-badge--muted'];
};

$sc_mev_holding_status = static function ($holding_shamsi) {
    $holding = trim((string) $holding_shamsi);
    if ($holding === '' || !function_exists('sc_get_today_shamsi') || !function_exists('sc_compare_shamsi_dates')) {
        return ['label' => 'بدون تاریخ برگزاری', 'class' => 'sc-mev-badge sc-mev-badge--muted'];
    }
    $today = sc_get_today_shamsi();
    $cmp   = sc_compare_shamsi_dates($holding, $today);
    if ($cmp < 0) {
        return ['label' => 'برگزار شده', 'class' => 'sc-mev-badge sc-mev-badge--neutral'];
    }
    if ($cmp === 0) {
        return ['label' => 'امروز', 'class' => 'sc-mev-badge sc-mev-badge--today'];
    }
    return ['label' => 'پیش‌رو', 'class' => 'sc-mev-badge sc-mev-badge--upcoming'];
};

$sc_mev_detail_url = static function ($event_id) {
    $eid = absint($event_id);
    if ($eid <= 0) {
        return '#';
    }
    if (function_exists('wc_get_account_endpoint_url')) {
        return trailingslashit(wc_get_account_endpoint_url('sc-event-detail')) . $eid . '/';
    }
    return home_url('/my-account/sc-event-detail/' . $eid);
};

/** آیکن‌های SVG یکدست (currentColor) */
$sc_mev_icon = static function ($name) {
    $stroke = ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    switch ($name) {
        case 'calendar':
            return '<svg class="sc-mev-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
        case 'clock':
            return '<svg class="sc-mev-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
        case 'venue':
            return '<svg class="sc-mev-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
        case 'address':
            return '<svg class="sc-mev-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><path d="M4 11a8 8 0 0 1 16 0c0 6-8 11-8 11S4 17 4 11"/><path d="M12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/></svg>';
        case 'event':
            return '<svg class="sc-mev-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>';
        case 'users':
            return '<svg class="sc-mev-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
        case 'arrow':
            return '<svg class="sc-mev-svg sc-mev-svg--arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"' . $stroke . '><path d="M15 18l-6-6 6-6"/></svg>';
        default:
            return '';
    }
};
?>
<div class="woocommerce-MyAccount-content sc-my-events-content">
    <div class="sc-mev-page-header">
        <div class="sc-mev-page-titles">
            <h2 class="sc-mev-page-title">رویدادهای من</h2>
            <p class="sc-mev-page-subtitle">رویدادهای ثبت‌نامی شما در دو ستون؛ هر کارت شامل تاریخ، ساعت، محل و آدرس برگزاری است.</p>
        </div>
        <?php if ($total_events > 0) : ?>
            <span class="sc-mev-count-badge"><?php echo (int) $total_events; ?> ثبت‌نام</span>
        <?php endif; ?>
    </div>

    <?php if (empty($user_events)) : ?>
        <div class="sc-mev-empty">
            <span class="sc-mev-empty-icon" aria-hidden="true"></span>
            <p class="sc-mev-empty-text">
                <?php
                if (!empty($my_events_no_member)) {
                    echo esc_html('پروفایل عضو شما در باشگاه یافت نشد. لطفاً با پشتیبانی تماس بگیرید.');
                } else {
                    echo esc_html('شما هنوز در هیچ رویدادی ثبت‌نام نکرده‌اید.');
                }
                ?>
            </p>
        </div>
    <?php else : ?>
        <div class="sc-mev-grid">
            <?php foreach ($user_events as $ue) :
                $event_id   = isset($ue['event_id']) ? absint($ue['event_id']) : 0;
                $name       = isset($ue['name']) ? (string) $ue['name'] : '';
                $detail_url = $sc_mev_detail_url($event_id);

                $holding_badge = $sc_mev_holding_status(isset($ue['holding_date_shamsi']) ? $ue['holding_date_shamsi'] : '');
                $inv_badge     = $sc_mev_invoice_badge(isset($ue['invoice_status']) ? $ue['invoice_status'] : '');

                $type_label = $sc_mev_event_type_label(isset($ue['event_type']) ? $ue['event_type'] : 'event');

                $excerpt = '';
                if (!empty($ue['description'])) {
                    $excerpt = wp_strip_all_tags((string) $ue['description']);
                    $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
                }

                $venue_name = isset($ue['event_location']) ? trim((string) $ue['event_location']) : '';
                $addr_full  = isset($ue['event_location_address']) ? trim(wp_strip_all_tags((string) $ue['event_location_address'])) : '';

                $cap       = isset($ue['capacity']) ? (int) $ue['capacity'] : 0;
                $enrolled  = isset($ue['enrolled_count']) ? (int) $ue['enrolled_count'] : 0;
                $remaining = ($cap > 0) ? max(0, $cap - $enrolled) : null;

                $is_deleted = !empty($ue['deleted_at']);
                $is_inactive = isset($ue['is_active']) && (int) $ue['is_active'] === 0;
                ?>
                <article class="sc-mev-card" id="event_<?php echo esc_attr((string) $event_id); ?>">
                    <div class="sc-mev-card-badges">
                        <span class="<?php echo esc_attr($holding_badge['class']); ?>"><?php echo esc_html($holding_badge['label']); ?></span>
                        <span class="<?php echo esc_attr($inv_badge['class']); ?>"><?php echo esc_html($inv_badge['label']); ?></span>
                        <span class="sc-mev-badge sc-mev-badge--type"><?php echo esc_html($type_label); ?></span>
                        <?php if ($is_deleted || $is_inactive) : ?>
                            <span class="sc-mev-badge sc-mev-badge--danger"><?php echo esc_html($is_deleted ? 'حذف‌شده از سامانه' : 'غیرفعال'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="sc-mev-card-title-row">
                        <span class="sc-mev-card-title-icon"><?php echo $sc_mev_icon('event'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <h3 class="sc-mev-card-title">
                            <a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($name !== '' ? $name : ('رویداد #' . $event_id)); ?></a>
                        </h3>
                    </div>

                    <?php if ($excerpt !== '') : ?>
                        <p class="sc-mev-card-excerpt" title="<?php echo esc_attr($excerpt); ?>"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>

                    <div class="sc-mev-detail-grid" role="group" aria-label="جزئیات برگزاری">
                        <div class="sc-mev-detail-cell">
                            <div class="sc-mev-detail-cell-head">
                                <?php echo $sc_mev_icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span class="sc-mev-detail-label">تاریخ برگزاری</span>
                            </div>
                            <span class="sc-mev-detail-value"><?php echo esc_html(!empty($ue['holding_date_shamsi']) ? (string) $ue['holding_date_shamsi'] : '—'); ?></span>
                        </div>
                        <div class="sc-mev-detail-cell">
                            <div class="sc-mev-detail-cell-head">
                                <?php echo $sc_mev_icon('clock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span class="sc-mev-detail-label">ساعت برگزاری</span>
                            </div>
                            <span class="sc-mev-detail-value"><?php echo esc_html(!empty($ue['event_time']) ? (string) $ue['event_time'] : '—'); ?></span>
                        </div>
                        <div class="sc-mev-detail-cell sc-mev-detail-cell--venue">
                            <div class="sc-mev-detail-cell-head">
                                <?php echo $sc_mev_icon('venue'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span class="sc-mev-detail-label">محل برگزاری</span>
                            </div>
                            <span class="sc-mev-detail-value"><?php echo esc_html($venue_name !== '' ? $venue_name : '—'); ?></span>
                        </div>
                        <div class="sc-mev-detail-cell sc-mev-detail-cell--address">
                            <div class="sc-mev-detail-cell-head">
                                <?php echo $sc_mev_icon('address'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span class="sc-mev-detail-label">آدرس</span>
                            </div>
                            <span class="sc-mev-detail-value sc-mev-detail-value--multiline"><?php echo esc_html($addr_full !== '' ? $addr_full : '—'); ?></span>
                        </div>
                    </div>

                    <?php if ($cap > 0) : ?>
                        <div class="sc-mev-capacity" role="status">
                            <p class="sc-mev-capacity-head">
                                <span class="sc-mev-capacity-ico" aria-hidden="true"><?php echo $sc_mev_icon('users'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span>وضعیت ظرفیت</span>
                            </p>
                            <div class="sc-mev-capacity-bar" aria-hidden="true">
                                <?php
                                $pct = $cap > 0 ? min(100, round(($enrolled / $cap) * 100)) : 0;
                                ?>
                                <span class="sc-mev-capacity-fill" style="width: <?php echo (int) $pct; ?>%;"></span>
                            </div>
                            <p class="sc-mev-capacity-text">
                                ظرفیت: <strong><?php echo (int) $enrolled; ?></strong> از <strong><?php echo (int) $cap; ?></strong> نفر
                                <?php if ($remaining !== null) : ?>
                                    — باقیمانده: <strong><?php echo (int) $remaining; ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="sc-mev-card-actions">
                        <a href="<?php echo esc_url($detail_url); ?>" class="sc-mev-btn sc-mev-btn-primary">
                            <span class="sc-mev-btn-ico" aria-hidden="true"><?php echo $sc_mev_icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            مشاهده جزئیات رویداد
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) :
            $pagination_base = esc_url_raw(trailingslashit($events_base_url));
            $page_links       = paginate_links([
                'base'      => $pagination_base . '%_%',
                'format'    => '?pag=%#%',
                'total'     => $total_pages,
                'current'   => $current_page,
                'prev_text' => '&lsaquo; قبلی',
                'next_text' => 'بعدی &rsaquo;',
                'type'      => 'list',
                'mid_size'  => 1,
                'end_size'  => 1,
            ]);
            ?>
            <nav class="sc-mev-pagination-wrap" aria-label="صفحه‌بندی رویدادها">
                <?php echo $page_links ? $page_links : ''; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-my-events-content .sc-mev-page-title');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
