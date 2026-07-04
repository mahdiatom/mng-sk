<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$codes = [];
$usage_tbl = $wpdb->prefix . 'sc_discount_code_usages';
$codes_tbl = $wpdb->prefix . 'sc_discount_codes';

$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

if (function_exists('sc_sc_discount_tables_ready') && sc_sc_discount_tables_ready()) {
    $where = ['1=1'];
    $args = [];

    if ($filter_status === 'active') {
        $where[] = 'is_active = 1';
    } elseif ($filter_status === 'inactive') {
        $where[] = 'is_active = 0';
    }

    if ($filter_type === 'percent' || $filter_type === 'fixed') {
        $where[] = 'discount_type = %s';
        $args[] = $filter_type;
    }

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(code LIKE %s OR description LIKE %s)';
        $args[] = $like;
        $args[] = $like;
    }

    $sql = "SELECT * FROM $codes_tbl WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";
    $codes = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
}

$active_filters_count = 0;
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if ($search !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
$clear_url = admin_url('admin.php?page=sc-discount-codes');
?>

<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">کدهای تخفیف صورت‌حساب</h1>
            <p class="sc-reports-list-desc">این کدها فقط برای ثبت‌نام کاربر در دوره / رویداد از طریق حساب کاربری اعمال می‌شوند و با کوپن فروشگاه ووکامرس متفاوت است.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-discount-code')); ?>" class="sc-reports-list-add-btn">افزودن کد تخفیف</a>
        </div>
    </div>

    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible"><p>کد حذف شد.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['sc_err'])) : ?>
        <div class="notice notice-error"><p>خطا در عملیات (کد: <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['sc_err']))); ?>).</p></div>
    <?php endif; ?>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button" class="sc-reports-list-filters-toggle" id="sc-discount-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-discount-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                <?php if ($active_filters_count > 0) : ?><span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span><?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($clear_url); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
        <form method="get" class="sc-reports-list-filters-panel" id="sc-discount-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-discount-codes">
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                        <option value="active" <?php selected($filter_status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($filter_status, 'inactive'); ?>>غیرفعال</option>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_type">نوع تخفیف</label>
                    <select name="filter_type" id="filter_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه</option>
                        <option value="percent" <?php selected($filter_type, 'percent'); ?>>درصدی</option>
                        <option value="fixed" <?php selected($filter_type, 'fixed'); ?>>مبلغ ثابت</option>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="s">جستجو</label>
                    <input type="search" name="s" id="s" class="sc-filter-control" value="<?php echo esc_attr($search); ?>" placeholder="کد یا توضیحات...">
                </div>
            </div>
            <div class="sc-reports-list-filters-actions">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url($clear_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-reports-list-table-card">
    <?php if (empty($codes)) : ?>
        <div class="sc-reports-empty">هنوز کدی ثبت نشده است.</div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">کد</th>
                    <th scope="col">نوع</th>
                    <th scope="col">مقدار</th>
                    <th scope="col">وضعیت</th>
                    <th scope="col">شروع</th>
                    <th scope="col">پایان</th>
                    <th scope="col">استفاده</th>
                    <th scope="col">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($codes as $c) :
                    $used = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $usage_tbl WHERE discount_code_id = %d",
                        $c->id
                    ));
                    $type_label = ($c->discount_type === 'fixed') ? 'مبلغ ثابت' : 'درصد';
                    $type_badge = ($c->discount_type === 'fixed') ? 'sc-badge--purple' : 'sc-badge--soft';
                    $val_show = ($c->discount_type === 'fixed')
                        ? number_format((float) $c->discount_value, 0, '.', ',')
                        : number_format((float) $c->discount_value, 2, '.', '');
                    $edit_url = admin_url('admin.php?page=sc-add-discount-code&discount_id=' . absint($c->id));
                    $del_url = wp_nonce_url(
                        admin_url('admin.php?page=sc-discount-codes&action=delete&discount_id=' . absint($c->id)),
                        'sc_delete_discount_' . absint($c->id)
                    );
                    $code_initials = $c->code !== '' ? mb_substr($c->code, 0, 1) : 'ک';
                    ?>
                    <tr>
                        <td>
                            <span class="sc-member-identity">
                                <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($code_initials); ?></span>
                                <span class="sc-member-identity-text"><span class="sc-member-name"><?php echo esc_html($c->code); ?></span></span>
                            </span>
                        </td>
                        <td><span class="sc-badge <?php echo esc_attr($type_badge); ?>"><?php echo esc_html($type_label); ?></span></td>
                        <td><strong><?php echo esc_html($val_show); ?><?php echo $c->discount_type === 'percent' ? '%' : ''; ?></strong></td>
                        <td>
                            <?php echo ((int) $c->is_active)
                                ? '<span class="sc-badge sc-badge--success">فعال</span>'
                                : '<span class="sc-badge sc-badge--muted">غیرفعال</span>'; ?>
                        </td>
                        <td><?php echo $c->starts_at ? esc_html($c->starts_at) : '—'; ?></td>
                        <td><?php echo $c->ends_at ? esc_html($c->ends_at) : '—'; ?></td>
                        <td><span class="sc-badge sc-badge--soft"><?php echo esc_html((string) $used); ?></span></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="sc-reports-action-btn">ویرایش</a>
                            <a href="<?php echo esc_url($del_url); ?>" class="sc-reports-action-btn" style="color:#dc2626;border-color:#fecaca;" onclick="return scConfirmInline(event, { type: 'warning', message: 'حذف این کد تخفیف؟' });">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-discount-filters-toggle');
    var $panel = $('#sc-discount-filters-panel');
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
