<?php
/**
 * لیست مرکزی همه کدهای QR بازیکنان
 */
if (!defined('ABSPATH')) {
    exit;
}

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$status = isset($_GET['qr_status']) ? sanitize_text_field(wp_unslash($_GET['qr_status'])) : '';
$member_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 25;

$result = function_exists('sc_attendance_qr_query_all_codes')
    ? sc_attendance_qr_query_all_codes([
        'search'    => $search,
        'status'    => $status,
        'member_id' => $member_id,
        'paged'     => $paged,
        'per_page'  => $per_page,
    ])
    : ['items' => [], 'total' => 0];

$items = $result['items'];
$total = (int) $result['total'];
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

$status_labels = [
    'active'   => 'فعال',
    'inactive' => 'غیرفعال',
    'disabled' => 'غیرفعال موقت',
];
?>
<div class="wrap sc-members-list-wrap sc-member-qr-codes-wrap">
    <div class="sc-members-list-header">
        <div>
            <h1 class="sc-members-list-title">مدیریت کدهای QR</h1>
            <p class="sc-members-list-desc">همه QRهای ذخیره‌شده بازیکنان — کد ۷ حرفی، وضعیت و لینک به پروفایل</p>
        </div>
    </div>

    <div class="sc-members-list-filters-card is-open">
        <form method="get" action="" class="sc-members-list-filters-panel">
            <input type="hidden" name="page" value="sc-member-qr-codes">
            <div class="sc-filter-grid sc-attendance-qr-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="sc-qr-search">جستجو</label>
                    <input type="search" name="s" id="sc-qr-search" value="<?php echo esc_attr($search); ?>" class="sc-filter-control" placeholder="نام بازیکن یا کد ۷ حرفی">
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="sc-qr-status">وضعیت</label>
                    <select name="qr_status" id="sc-qr-status" class="sc-filter-control">
                        <option value="">همه</option>
                        <?php foreach ($status_labels as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="sc-qr-member-id">شناسه بازیکن</label>
                    <input type="number" name="member_id" id="sc-qr-member-id" value="<?php echo $member_id > 0 ? esc_attr((string) $member_id) : ''; ?>" class="sc-filter-control" min="1">
                </div>
            </div>
            <p class="sc-members-list-filters-actions">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-member-qr-codes')); ?>" class="button">پاک کردن</a>
            </p>
        </form>
    </div>

    <div class="sc-members-list-table-card">
        <table class="wp-list-table widefat fixed striped sc-member-qr-codes-table">
            <thead>
                <tr>
                    <th>بازیکن</th>
                    <th>کد ۷ حرفی</th>
                    <th>وضعیت</th>
                    <th>تاریخ ساخت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)) : ?>
                    <tr><td colspan="5" class="sc-reports-empty">موردی یافت نشد.</td></tr>
                <?php else : ?>
                    <?php foreach ($items as $row) :
                        $name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
                        $view_url = admin_url('admin.php?page=sc-view-member&player_id=' . (int) $row->member_id);
                        $status_key = (string) ($row->status ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($name !== '' ? $name : '—'); ?></strong>
                                <div class="description">#<?php echo esc_html((string) $row->member_id); ?></div>
                            </td>
                            <td><code class="sc-attendance-qr-short-code sc-attendance-qr-short-code--table"><?php echo esc_html((string) $row->short_code); ?></code></td>
                            <td>
                                <span class="sc-qr-status-badge sc-qr-status-badge--<?php echo esc_attr($status_key); ?>">
                                    <?php echo esc_html($status_labels[$status_key] ?? $status_key); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html((string) ($row->created_at ?? '')); ?></td>
                            <td><a href="<?php echo esc_url($view_url); ?>" class="button button-small">مشاهده بازیکن</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) :
            $base_args = ['page' => 'sc-member-qr-codes'];
            if ($search !== '') {
                $base_args['s'] = $search;
            }
            if ($status !== '') {
                $base_args['qr_status'] = $status;
            }
            if ($member_id > 0) {
                $base_args['member_id'] = $member_id;
            }
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'      => add_query_arg(array_merge($base_args, ['paged' => '%#%']), admin_url('admin.php')),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]);
            echo '</div></div>';
        endif; ?>
    </div>
</div>
