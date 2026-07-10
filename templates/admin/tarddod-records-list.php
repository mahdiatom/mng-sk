<?php
if (!defined('ABSPATH')) exit;
$session_id = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$paged = max(1, absint($_GET['paged'] ?? 1));
$per_page = 30;
$result = sc_tarddod_query_records(['session_id' => $session_id, 'search' => $search, 'paged' => $paged, 'per_page' => $per_page]);
$items = $result['items'];
$total = (int) $result['total'];
$total_pages = max(1, (int) ceil($total / $per_page));
$active_filters = ($session_id > 0 ? 1 : 0) + ($search !== '' ? 1 : 0);
$clear_url = admin_url('admin.php?page=sc-tarddod-records');
$panel_id = 'sc-tarddod-records-filters';
$toggle_id = 'sc-tarddod-records-filters-toggle';
?>
<div class="wrap sc-members-list-wrap sc-tarddod-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">لیست ترددها</h1>
            <p class="sc-members-list-desc">همه ثبت‌های تردد بازیکن و پرسنل — با فیلتر جلسه و جستجوی نام</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-register')); ?>" class="button button-primary sc-members-list-add-btn">ثبت تردد</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-sessions')); ?>" class="button">جلسات</a>
        </div>
    </div>

    <?php
    sc_members_list_filter_card_open([
        'page'                  => 'sc-tarddod-records',
        'panel_id'              => $panel_id,
        'toggle_id'             => $toggle_id,
        'clear_url'             => $clear_url,
        'active_filters_count'  => $active_filters,
        'filters_open'          => $active_filters > 0,
    ]);
    ?>
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="tarddod_filter_session">شناسه جلسه</label>
                    <input type="number" name="session_id" id="tarddod_filter_session" value="<?php echo $session_id > 0 ? esc_attr((string) $session_id) : ''; ?>" class="sc-filter-control" min="1" placeholder="مثلاً 12">
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="tarddod_filter_search">جستجوی نام</label>
                    <input type="search" name="s" id="tarddod_filter_search" value="<?php echo esc_attr($search); ?>" class="sc-filter-control" placeholder="نام بازیکن یا پرسنل">
                </div>
            </div>
    <?php sc_members_list_filter_card_close(['clear_url' => $clear_url]); ?>

    <div class="sc-members-list-table-card">
        <table class="wp-list-table widefat fixed striped sc-tarddod-records-table">
                <thead>
                <tr>
                    <th>جلسه</th>
                    <th>نام</th>
                    <th>نوع</th>
                    <th>تاریخ جلسه</th>
                    <th>زمان ثبت</th>
                    <th>عکس اسکن</th>
                </tr>
                </thead>
            <tbody>
            <?php if (empty($items)) : ?>
                <tr><td colspan="6" class="sc-reports-empty">رکوردی یافت نشد.</td></tr>
            <?php else : foreach ($items as $row) :
                $sd = $row->session_date && function_exists('sc_date_shamsi') ? sc_date_shamsi($row->session_date, 'Y/m/d') : ($row->session_date ?? '—');
                $type_badge = ($row->subject_type ?? '') === 'staff' ? 'sc-badge--purple' : 'sc-badge--soft';
                $scan_url = '';
                if (!empty($row->scan_photo) && function_exists('sc_qr_scan_photo_url_from_relative')) {
                    $scan_url = sc_qr_scan_photo_url_from_relative($row->scan_photo);
                }
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($row->session_title ?? '—'); ?></strong>
                        <?php if (!empty($row->session_id)) : ?>
                            <div class="description">#<?php echo (int) $row->session_id; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo esc_html($row->subject_name); ?></strong></td>
                    <td><span class="sc-badge <?php echo esc_attr($type_badge); ?>"><?php echo esc_html(sc_tarddod_subject_type_label($row->subject_type)); ?></span></td>
                    <td><?php echo esc_html($sd); ?></td>
                    <td><?php echo esc_html($row->created_at); ?></td>
                    <td>
                        <?php if ($scan_url) : ?>
                            <a href="<?php echo esc_url($scan_url); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo esc_url($scan_url); ?>" alt="عکس اسکن" style="width:64px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;" loading="lazy">
                            </a>
                        <?php else : ?>
                            <span style="color:#9ca3af;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
        $base_args = ['page' => 'sc-tarddod-records'];
        if ($session_id > 0) {
            $base_args['session_id'] = $session_id;
        }
        if ($search !== '') {
            $base_args['s'] = $search;
        }
        sc_members_list_render_pagination($base_args, $paged, $total_pages);
        ?>
    </div>
</div>
<?php sc_members_list_filter_toggle_script($toggle_id, $panel_id); ?>
