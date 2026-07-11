<?php
/**
 * انبار — لیست محصولات با موجودی شعبه‌ها
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_user_can_product_stock_inquiry') || !sc_user_can_product_stock_inquiry()) {
    wp_die(esc_html__('شما به این صفحه دسترسی ندارید.', 'sportclub-manager'), 403);
}

if (function_exists('sc_product_branch_stock_ensure_table')) {
    sc_product_branch_stock_ensure_table();
}

$is_secretary = function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only();
$allowed_chapters = null;
if ($is_secretary) {
    $allowed_chapters = function_exists('sc_secretary_get_effective_chapters')
        ? sc_secretary_get_effective_chapters()
        : (function_exists('sc_get_secretary_chapters') ? sc_get_secretary_chapters() : []);
}

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 20;

$items = function_exists('sc_warehouse_get_product_stock_items')
    ? sc_warehouse_get_product_stock_items([
        'search' => $search,
        'page' => $paged,
        'per_page' => $per_page,
        'allowed_chapters' => $allowed_chapters,
    ])
    : ['items' => [], 'total' => 0];

$total = (int) ($items['total'] ?? 0);
$rows = $items['items'] ?? [];
$total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

wp_enqueue_style(
    'sc-product-stock-inquiry',
    SC_ASSETS_URL . 'css/product-stock-inquiry.css',
    ['sc-admin-css'],
    defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0'
);
?>
<div class="wrap sc-members-list-wrap sc-pbs-inquiry-wrap sc-pbs-warehouse-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">انبار</h1>
            <p class="sc-members-list-desc">
                موجودی همه محصولات به تفکیک شعبه.
                <?php if ($is_secretary && is_array($allowed_chapters) && !empty($allowed_chapters)) : ?>
                    <span class="sc-pbs-scope-pill">محدوده شما: <?php echo esc_html(implode('، ', $allowed_chapters)); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-product-stock-inquiry')); ?>" class="sc-pbs-link-btn">استعلام موجودی</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-products')); ?>" class="sc-pbs-link-btn">لیست محصولات</a>
        </div>
    </div>

    <div class="sc-pbs-card sc-pbs-search-card">
        <form method="get" class="sc-pbs-warehouse-filter" action="">
            <input type="hidden" name="page" value="sc-warehouse" />
            <label class="sc-pbs-label" for="sc_warehouse_s">جستجوی محصول</label>
            <div class="sc-pbs-warehouse-filter-row">
                <input type="search"
                       id="sc_warehouse_s"
                       name="s"
                       class="sc-pbs-input"
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="نام یا SKU…" />
                <button type="submit" class="button button-primary sc-pbs-btn">جستجو</button>
                <?php if ($search !== '') : ?>
                    <a class="sc-pbs-link-btn" href="<?php echo esc_url(admin_url('admin.php?page=sc-warehouse')); ?>">پاک کردن</a>
                <?php endif; ?>
            </div>
            <p class="description" style="margin:8px 0 0;"><?php echo esc_html(number_format_i18n($total)); ?> مورد</p>
        </form>
    </div>

    <?php if (empty($rows)) : ?>
        <div class="sc-pbs-card">
            <div class="sc-pbs-placeholder-inner">
                <p>محصولی یافت نشد.</p>
            </div>
        </div>
    <?php else : ?>
        <div class="sc-pbs-warehouse-list">
            <?php foreach ($rows as $row) :
                $branches = $row['branches'] ?? [];
                $max_qty = max(1, (int) ($row['max_qty'] ?? 1));
                $total_qty = (int) ($row['total_qty'] ?? 0);
                ?>
                <div class="sc-pbs-card sc-pbs-warehouse-item">
                    <div class="sc-pbs-product-head">
                        <div class="sc-pbs-thumb">
                            <?php if (!empty($row['thumb'])) : ?>
                                <img class="sc-pbs-thumb-img" src="<?php echo esc_url($row['thumb']); ?>" alt="" width="56" height="56" />
                            <?php else : ?>
                                <span class="sc-pbs-thumb-ph" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                        <div class="sc-pbs-product-meta">
                            <h2 class="sc-pbs-product-name"><?php echo esc_html($row['name']); ?></h2>
                            <div class="sc-pbs-product-sub">
                                <?php if (!empty($row['sku'])) : ?>
                                    <span>SKU: <?php echo esc_html($row['sku']); ?></span>
                                <?php endif; ?>
                                <span class="sc-pbs-tag"><?php echo esc_html($row['type_label']); ?></span>
                                <span class="sc-pbs-tag">جمع: <?php echo esc_html((string) $total_qty); ?></span>
                                <?php if (!empty($row['edit_url'])) : ?>
                                    <a class="sc-pbs-edit-link" href="<?php echo esc_url($row['edit_url']); ?>">ویرایش</a>
                                <?php endif; ?>
                                <a class="sc-pbs-edit-link" href="<?php echo esc_url(admin_url('admin.php?page=sc-product-stock-inquiry')); ?>">استعلام</a>
                            </div>
                        </div>
                        <div class="sc-pbs-product-price"><?php echo wp_kses_post($row['price_html'] ?? ''); ?></div>
                    </div>

                    <?php if (empty($branches)) : ?>
                        <div class="sc-pbs-state">برای این محصول موجودی شعبه‌ای تعریف نشده است.</div>
                    <?php else : ?>
                        <div class="sc-pbs-branches">
                            <?php foreach ($branches as $b) :
                                $qty = (int) ($b['qty'] ?? 0);
                                $pct = min(100, (int) round(($qty / $max_qty) * 100));
                                $ok = $qty > 0;
                                ?>
                                <div class="sc-pbs-branch-row <?php echo $ok ? 'is-instock' : 'is-outofstock'; ?>">
                                    <div class="sc-pbs-branch-main">
                                        <strong class="sc-pbs-branch-name"><?php echo esc_html($b['chapter']); ?></strong>
                                        <span class="sc-pbs-badge <?php echo $ok ? 'is-ok' : 'is-off'; ?>">
                                            <?php echo $ok ? 'موجود' : 'ناموجود'; ?>
                                        </span>
                                    </div>
                                    <div class="sc-pbs-branch-stats">
                                        <span class="sc-pbs-branch-qty"><?php echo esc_html((string) $qty); ?></span>
                                        <div class="sc-pbs-bar" title="<?php echo esc_attr($pct . '%'); ?>">
                                            <span style="width:<?php echo esc_attr((string) $pct); ?>%"></span>
                                        </div>
                                        <span class="sc-pbs-branch-price"><?php echo wp_kses_post($b['price_html'] ?? '—'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="sc-pbs-warehouse-pagination tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(paginate_links([
                        'base' => add_query_arg(['paged' => '%#%', 'page' => 'sc-warehouse', 's' => $search], admin_url('admin.php')),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
