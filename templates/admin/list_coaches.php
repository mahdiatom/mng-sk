<?php
if ( ! defined('ABSPATH') ) exit;
global $title, $coaches_list_table;
if (!isset($coaches_list_table)) {
    $coaches_list_table = new Coaches_List_Table();
    $coaches_list_table->prepare_items();
}

$search_value = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$active_filters_count = 0;
if ($search_value !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>
<div class="wrap sc-coaches-list-wrap">
    <div class="sc-coaches-list-header">
        <div class="sc-coaches-list-header-text">
            <h1 class="sc-coaches-list-title">لیست مربیان</h1>
            <p class="sc-coaches-list-desc">برای مشاهده اکشن‌ها روی نام مربی بروید (ویرایش، حذف).</p>
        </div>
        <div class="sc-coaches-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-coach')); ?>" class="sc-coaches-list-add-btn">افزودن مربی</a>
        </div>
    </div>

    <div class="sc-coaches-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-coaches-list-filters-toolbar">
            <button type="button"
                    class="sc-coaches-list-filters-toggle"
                    id="sc-coaches-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-coaches-filters-panel">
                <span class="sc-coaches-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-coaches-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-coaches-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-coaches-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coaches')); ?>" class="sc-coaches-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" class="sc-coaches-list-filters-panel" id="sc-coaches-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coaches">
            <?php if ($status_filter !== '') : ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
            <?php endif; ?>
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="search_coach">جستجو</label>
                    <input type="search" id="search_coach" name="s" value="<?php echo esc_attr($search_value); ?>" class="sc-filter-control" placeholder="نام، کد ملی یا موبایل…">
                </div>
            </div>
            <div class="sc-coaches-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coaches')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-coaches-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc-coaches">
            <?php
            if ($status_filter !== '') {
                echo '<input type="hidden" name="status" value="' . esc_attr($status_filter) . '">';
            }
            if ($search_value !== '') {
                echo '<input type="hidden" name="s" value="' . esc_attr($search_value) . '">';
            }
            $coaches_list_table->views();
            $coaches_list_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-coaches-filters-toggle');
    var $panel = $('#sc-coaches-filters-panel');
    var $card = $toggle.closest('.sc-coaches-list-filters-card');
    var $label = $toggle.find('.sc-coaches-list-filters-toggle-label');

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
