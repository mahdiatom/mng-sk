<?php
if (!defined('ABSPATH')) exit;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$paged = max(1, absint($_GET['paged'] ?? 1));
$per_page = 20;
$result = sc_tarddod_query_sessions(['search' => $search, 'status' => $status, 'paged' => $paged, 'per_page' => $per_page]);
$items = $result['items'];
$total = (int) $result['total'];
$total_pages = max(1, (int) ceil($total / $per_page));
$active_filters = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0);
$clear_url = admin_url('admin.php?page=sc-tarddod-sessions');
$panel_id = 'sc-tarddod-sessions-filters';
$toggle_id = 'sc-tarddod-sessions-filters-toggle';
$status_badges = [
    'open'   => 'sc-badge--success',
    'draft'  => 'sc-badge--muted',
    'closed' => 'sc-badge--soft',
];
?>
<div class="wrap sc-members-list-wrap sc-tarddod-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">جلسات تردد</h1>
            <p class="sc-members-list-desc">مدیریت جلسات ثبت تردد — ایجاد، ویرایش و اسکن QR</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-session-add')); ?>" class="button button-primary sc-members-list-add-btn">افزودن جلسه</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-register')); ?>" class="button">ثبت تردد</a>
        </div>
    </div>

    <?php
    sc_members_list_filter_card_open([
        'page'                  => 'sc-tarddod-sessions',
        'panel_id'              => $panel_id,
        'toggle_id'             => $toggle_id,
        'clear_url'             => $clear_url,
        'active_filters_count'  => $active_filters,
        'filters_open'          => $active_filters > 0,
    ]);
    ?>
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="tarddod_sessions_search">جستجو</label>
                    <input type="search" name="s" id="tarddod_sessions_search" value="<?php echo esc_attr($search); ?>" class="sc-filter-control" placeholder="عنوان، مکان یا توضیحات">
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="tarddod_sessions_status">وضعیت</label>
                    <select name="status" id="tarddod_sessions_status" class="sc-filter-control">
                        <option value="">همه وضعیت‌ها</option>
                        <?php foreach (['draft', 'open', 'closed'] as $st) : ?>
                            <option value="<?php echo esc_attr($st); ?>" <?php selected($status, $st); ?>><?php echo esc_html(sc_tarddod_status_label($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
    <?php sc_members_list_filter_card_close(['clear_url' => $clear_url]); ?>

    <div class="sc-members-list-table-card">
        <table class="wp-list-table widefat fixed striped sc-tarddod-sessions-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>تاریخ</th>
                    <th>ساعت</th>
                    <th>مکان</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) : ?>
                <tr><td colspan="6" class="sc-reports-empty">جلسه‌ای یافت نشد.</td></tr>
            <?php else : foreach ($items as $row) :
                $date_disp = function_exists('sc_date_shamsi') ? sc_date_shamsi($row->session_date, 'Y/m/d') : $row->session_date;
                $time_disp = $row->session_time ? substr((string) $row->session_time, 0, 5) : '—';
                $badge = $status_badges[$row->status] ?? 'sc-badge--muted';
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($row->title); ?></strong>
                        <div class="description">#<?php echo (int) $row->id; ?></div>
                    </td>
                    <td><?php echo esc_html($date_disp); ?></td>
                    <td><?php echo esc_html($time_disp); ?></td>
                    <td><?php echo esc_html($row->location ?: '—'); ?></td>
                    <td><span class="sc-badge <?php echo esc_attr($badge); ?>"><?php echo esc_html(sc_tarddod_status_label($row->status)); ?></span></td>
                    <td>
                        <div class="sc-tarddod-table-actions">
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-register&session_id=' . (int) $row->id)); ?>">ثبت تردد</a>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-session-add&session_id=' . (int) $row->id)); ?>">ویرایش</a>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-records&session_id=' . (int) $row->id)); ?>">لیست</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
        $base_args = ['page' => 'sc-tarddod-sessions'];
        if ($search !== '') {
            $base_args['s'] = $search;
        }
        if ($status !== '') {
            $base_args['status'] = $status;
        }
        sc_members_list_render_pagination($base_args, $paged, $total_pages);
        ?>
    </div>
</div>
<?php sc_members_list_filter_toggle_script($toggle_id, $panel_id); ?>
