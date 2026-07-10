<?php
if (!defined('ABSPATH')) {
    exit;
}

global $products_list_table;

if (empty($products_list_table) || !is_a($products_list_table, 'products_List_Table')) {
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    require_once SC_TEMPLATES_ADMIN_DIR . 'list_products.php';
    $products_list_table = new products_List_Table();
    $products_list_table->prepare_items();
    $GLOBALS['products_list_table'] = $products_list_table;
}

$product_cat_terms = [];
$product_tag_terms = [];
if (taxonomy_exists('product_cat')) {
    $product_cat_terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($product_cat_terms)) {
        $product_cat_terms = [];
    }
}
if (taxonomy_exists('product_tag')) {
    $product_tag_terms = get_terms([
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($product_tag_terms)) {
        $product_tag_terms = [];
    }
}

$filter_category = sc_admin_sc_products_get_request_scalar_int('filter_category', 0);
$filter_tag = sc_admin_sc_products_get_request_scalar_int('filter_tag', 0);
$filter_stock = sc_admin_sc_products_get_request_scalar_string('filter_stock', 'all');
$filter_type = sc_admin_sc_products_get_request_scalar_string('filter_type', 'all');
$filter_publish = sc_admin_sc_products_get_request_scalar_string('filter_publish', 'all');
$filter_featured = sc_admin_sc_products_get_request_scalar_string('filter_featured', 'all');
$filter_price_min = sc_admin_sc_products_get_request_scalar_string('filter_price_min', '');
$filter_price_max = sc_admin_sc_products_get_request_scalar_string('filter_price_max', '');

$active_filters_count = 0;
if ($filter_category > 0) {
    $active_filters_count++;
}
if ($filter_tag > 0) {
    $active_filters_count++;
}
if ($filter_stock !== 'all') {
    $active_filters_count++;
}
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if ($filter_publish !== 'all') {
    $active_filters_count++;
}
if ($filter_featured !== 'all') {
    $active_filters_count++;
}
if ($filter_price_min !== '') {
    $active_filters_count++;
}
if ($filter_price_max !== '') {
    $active_filters_count++;
}
if (!empty($_GET['s'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-shop-products-wrap sc-members-list-wrap">
    <div class="sc-shop-products-header sc-members-list-header">
        <div class="sc-shop-products-header-text sc-members-list-header-text">
            <h1 class="sc-shop-products-title sc-members-list-title">لیست محصولات</h1>
            <p class="sc-shop-products-desc sc-members-list-desc">مدیریت محصولات فروشگاه باشگاه. برای ویرایش روی نام محصول کلیک کنید.</p>
        </div>
        <div class="sc-shop-products-header-actions sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-product-stock-inquiry')); ?>" class="sc-shop-products-add-btn sc-members-list-add-btn page-title-action" style="background:#fff;color:#4a1fb8!important;border:1px solid rgba(109,52,255,.22);box-shadow:none;">استعلام موجودی</a>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="sc-shop-products-add-btn sc-members-list-add-btn page-title-action">افزودن محصول</a>
        </div>
    </div>

    <?php if (isset($_GET['sc_status']) && $_GET['sc_status'] === 'bulk_trashed') : ?>
        <div class="notice notice-success is-dismissible"><p>محصولات انتخاب‌شده به زباله‌دان منتقل شدند.</p></div>
    <?php endif; ?>

    <div class="sc-shop-products-filters-card sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-shop-products-filters-toolbar sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-shop-products-filters-toggle sc-members-list-filters-toggle"
                    id="sc-shop-products-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-shop-products-filters-panel">
                <span class="sc-shop-products-filters-toggle-icon sc-members-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-shop-products-filters-toggle-label sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-shop-products-filters-badge sc-members-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-shop-products-filters-chevron sc-members-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-products')); ?>" class="sc-shop-products-filters-clear sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-shop-products-filters-panel sc-members-list-filters-panel" id="sc-shop-products-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-products">
            <div class="sc-filter-grid sc-shop-products-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_category">دسته محصول</label>
                    <select name="filter_category" id="filter_category" class="sc-filter-control">
                        <option value="0">همه دسته‌ها</option>
                        <?php foreach ($product_cat_terms as $term) :
                            if (!is_object($term) || empty($term->term_id)) {
                                continue;
                            }
                            $depth = function_exists('get_ancestors') ? count(get_ancestors((int) $term->term_id, 'product_cat')) : 0;
                            $pad = $depth > 0 ? str_repeat('— ', $depth) : '';
                        ?>
                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($filter_category, (int) $term->term_id); ?>>
                                <?php echo esc_html($pad . $term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_tag">برچسب</label>
                    <select name="filter_tag" id="filter_tag" class="sc-filter-control">
                        <option value="0">همه برچسب‌ها</option>
                        <?php foreach ($product_tag_terms as $term) :
                            if (!is_object($term) || empty($term->term_id)) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($filter_tag, (int) $term->term_id); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_stock">موجودی</label>
                    <select name="filter_stock" id="filter_stock" class="sc-filter-control">
                        <option value="all" <?php selected($filter_stock, 'all'); ?>>همه</option>
                        <option value="instock" <?php selected($filter_stock, 'instock'); ?>>موجود</option>
                        <option value="outofstock" <?php selected($filter_stock, 'outofstock'); ?>>ناموجود</option>
                        <option value="onbackorder" <?php selected($filter_stock, 'onbackorder'); ?>>پیش‌سفارش</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_type">نوع محصول</label>
                    <select name="filter_type" id="filter_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه انواع</option>
                        <option value="simple" <?php selected($filter_type, 'simple'); ?>>ساده</option>
                        <option value="variable" <?php selected($filter_type, 'variable'); ?>>متغیر</option>
                        <option value="grouped" <?php selected($filter_type, 'grouped'); ?>>گروهی</option>
                        <option value="external" <?php selected($filter_type, 'external'); ?>>خارجی</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_publish">وضعیت انتشار</label>
                    <select name="filter_publish" id="filter_publish" class="sc-filter-control">
                        <option value="all" <?php selected($filter_publish, 'all'); ?>>همه</option>
                        <option value="publish" <?php selected($filter_publish, 'publish'); ?>>منتشر شده</option>
                        <option value="draft" <?php selected($filter_publish, 'draft'); ?>>پیش‌نویس</option>
                        <option value="pending" <?php selected($filter_publish, 'pending'); ?>>در انتظار بررسی</option>
                        <option value="private" <?php selected($filter_publish, 'private'); ?>>خصوصی</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_featured">محصول ویژه</label>
                    <select name="filter_featured" id="filter_featured" class="sc-filter-control">
                        <option value="all" <?php selected($filter_featured, 'all'); ?>>همه</option>
                        <option value="yes" <?php selected($filter_featured, 'yes'); ?>>فقط ویژه</option>
                        <option value="no" <?php selected($filter_featured, 'no'); ?>>غیر ویژه</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_price_min">حداقل قیمت</label>
                    <input type="number" name="filter_price_min" id="filter_price_min" class="sc-filter-control" value="<?php echo esc_attr($filter_price_min); ?>" min="0" step="any" placeholder="0">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_price_max">حداکثر قیمت</label>
                    <input type="number" name="filter_price_max" id="filter_price_max" class="sc-filter-control" value="<?php echo esc_attr($filter_price_max); ?>" min="0" step="any" placeholder="بدون سقف">
                </div>
            </div>

            <div class="sc-shop-products-filters-actions sc-members-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-products')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-shop-products-table-card sc-members-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc-products">
            <?php
            foreach (['filter_category', 'filter_tag', 'filter_stock', 'filter_type', 'filter_publish', 'filter_featured', 'filter_price_min', 'filter_price_max'] as $f) {
                if (!isset($_GET[$f]) || $_GET[$f] === '') {
                    continue;
                }
                $v = wp_unslash($_GET[$f]);
                if (is_array($v)) {
                    $v = reset($v);
                }
                if ($v === '' || $v === null || !is_scalar($v)) {
                    continue;
                }
                if (in_array($f, ['filter_category', 'filter_tag'], true) && (int) $v === 0) {
                    continue;
                }
                if (in_array($f, ['filter_stock', 'filter_type', 'filter_publish', 'filter_featured'], true) && $v === 'all') {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($f) . '" value="' . esc_attr((string) $v) . '">';
            }
            $products_list_table->search_box('جستجوی نام یا SKU', 'search_product');
            $products_list_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-shop-products-filters-toggle');
    var $panel = $('#sc-shop-products-filters-panel');
    var $card = $toggle.closest('.sc-shop-products-filters-card');
    var $label = $toggle.find('.sc-shop-products-filters-toggle-label');
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
