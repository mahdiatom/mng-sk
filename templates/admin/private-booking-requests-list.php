<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
$courses_table = $wpdb->prefix . 'sc_courses';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members_table = $wpdb->prefix . 'sc_members';

$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;

$where = ['1=1'];
$values = [];
if ($filter_status !== 'all') {
    $where[] = 'b.status = %s';
    $values[] = $filter_status;
}
if ($filter_member > 0) {
    $where[] = 'b.member_id = %d';
    $values[] = $filter_member;
}
$where_clause = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) FROM {$bookings_table} b WHERE {$where_clause}";
$total_items = !empty($values)
    ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
    : (int) $wpdb->get_var($count_sql);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = max(1, (int) ceil($total_items / $per_page));

$list_sql = "SELECT b.*, c.title AS course_title,
                    m.first_name, m.last_name, m.national_id,
                    co.first_name AS coach_first_name, co.last_name AS coach_last_name
             FROM {$bookings_table} b
             LEFT JOIN {$courses_table} c ON c.id = b.course_id
             LEFT JOIN {$members_table} m ON m.id = b.member_id
             LEFT JOIN {$coaches_table} co ON co.id = b.coach_id
             WHERE {$where_clause}
             ORDER BY b.created_at DESC
             LIMIT %d OFFSET %d";
$list_values = $values;
$list_values[] = $per_page;
$list_values[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_values));

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM {$members_table} WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");

$status_options = [
    'all' => 'همه وضعیت‌ها',
    'pending_admin' => 'در انتظار بررسی',
    'pending_payment' => 'منتظر پرداخت',
    'active' => 'فعال',
    'rejected' => 'رد شده',
    'paused' => 'متوقف',
    'cancelled' => 'لغو شده',
];
$bulk_status_options = [
    'pending_admin' => 'در انتظار بررسی',
    'pending_payment' => 'منتظر پرداخت',
    'active' => 'فعال',
    'rejected' => 'رد شده',
    'paused' => 'متوقف',
    'cancelled' => 'لغو شده',
];

$active_filters_count = 0;
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if ($filter_member > 0) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$selected_member_text = 'همه بازیکنان';
if ($filter_member > 0) {
    foreach ($members as $member) {
        if ((int) $member->id === $filter_member) {
            $selected_member_text = trim($member->first_name . ' ' . $member->last_name) . ' - ' . $member->national_id;
            break;
        }
    }
}
?>
<?php if (!empty($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p>رزرو با موفقیت ثبت شد و صورت‌حساب ایجاد گردید.</p></div>
<?php endif; ?>
<?php if (!empty($_GET['rejected'])) : ?>
    <div class="notice notice-success is-dismissible"><p>درخواست رد شد و وضعیت به «رد شده» تغییر کرد.</p></div>
<?php endif; ?>
<?php if (!empty($_GET['bulk_done'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf('%d مورد با موفقیت انجام شد.', absint($_GET['bulk_done']))); ?></p></div>
<?php endif; ?>
<?php if (!empty($_GET['bulk_error'])) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html(urldecode(sanitize_text_field(wp_unslash($_GET['bulk_error'])))); ?></p></div>
<?php endif; ?>
<?php if (!empty($_GET['error'])) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html(urldecode(sanitize_text_field(wp_unslash($_GET['error'])))); ?></p></div>
<?php endif; ?>

<div class="wrap sc-private-admin-wrap sc-private-regs-list-wrap">
    <div class="sc-private-regs-list-header">
        <div class="sc-private-regs-list-header-text">
            <h1 class="sc-private-regs-list-title">لیست رزرو کلاس‌های خصوصی</h1>
            <p class="sc-private-regs-list-desc">مدیریت درخواست‌ها و ثبت‌نام کلاس خصوصی بازیکنان</p>
        </div>
        <div class="sc-private-regs-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-form')); ?>" class="sc-private-regs-list-add-btn">ثبت‌نام کلاس خصوصی</a>
        </div>
    </div>

    <div class="sc-private-regs-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-private-regs-list-filters-toolbar">
            <button type="button"
                    class="sc-private-regs-list-filters-toggle"
                    id="sc-private-regs-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-private-regs-filters-panel">
                <span class="sc-private-regs-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-private-regs-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-private-regs-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-private-regs-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-requests')); ?>" class="sc-private-regs-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-private-regs-list-filters-panel" id="sc-private-regs-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-private-booking-requests">
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select class="sc-filter-control" id="filter_status" name="filter_status">
                        <?php foreach ($status_options as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label">بازیکن</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php echo $filter_member > 0 ? 'style="display:none"' : ''; ?>>همه بازیکنان</span>
                            <span class="sc-dropdown-selected" <?php echo $filter_member <= 0 ? 'style="display:none"' : ''; ?>><?php echo esc_html($selected_member_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible <?php echo $filter_member <= 0 ? 'sc-selected' : ''; ?>"
                                     data-value="0"
                                     data-search="همه بازیکنان"
                                     onclick="scSelectMemberFilter(this,'0','همه بازیکنان')">
                                    همه بازیکنان
                                </div>
                                <?php
                                $display_count = 0;
                                $max_display = 15;
                                foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                    $member_label = trim($member->first_name . ' ' . $member->last_name) . ' - ' . $member->national_id;
                                    $member_search = strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id);
                                    $is_selected = ((int) $member->id === $filter_member);
                                    ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?><?php echo $is_selected ? ' sc-selected' : ''; ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr($member_search); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member_label); ?>')">
                                        <?php echo esc_html($member_label); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="sc-private-regs-list-filters-actions">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a class="button delete_fillter" href="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-requests')); ?>">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-private-regs-list-table-card">
        <div class="sc-private-regs-list-summary"><span><?php echo (int) $total_items; ?> رزرو</span></div>

        <?php if (empty($rows)) : ?>
            <div class="sc-private-regs-empty"><p>رکوردی یافت نشد.</p></div>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-requests')); ?>" id="sc-private-bookings-bulk-form">
                <?php wp_nonce_field('sc_private_bookings_bulk', 'sc_private_bookings_bulk_nonce'); ?>
                <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                <div class="sc-private-regs-bulk-bar">
                    <label class="sc-private-regs-bulk-check-all">
                        <input type="checkbox" id="sc-private-bookings-check-all">
                        <span>انتخاب همه</span>
                    </label>
                    <div class="sc-private-regs-bulk-bar-actions">
                        <select name="sc_private_bookings_bulk_action" id="sc-private-bookings-bulk-action" class="sc-filter-control sc-private-regs-bulk-select">
                            <option value="">عملیات دست‌جمعی</option>
                            <option value="reject">رد درخواست</option>
                            <option value="set_status">تغییر وضعیت</option>
                            <option value="delete">حذف</option>
                        </select>
                        <select name="bulk_new_status" id="sc-private-bookings-bulk-status" class="sc-filter-control sc-private-regs-bulk-select" hidden>
                            <option value="">انتخاب وضعیت جدید</option>
                            <?php foreach ($bulk_status_options as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="bulk_rejected_reason" id="sc-private-bookings-bulk-reason" class="sc-filter-control sc-private-regs-bulk-reason" placeholder="دلیل رد (در صورت نیاز)" hidden>
                        <button type="submit" class="button button-primary" id="sc-private-bookings-bulk-submit">اجرا</button>
                    </div>
                </div>

                <div class="sc-private-regs-table-scroll">
                    <table class="wp-list-table widefat striped sc-private-regs-table">
                        <thead>
                            <tr>
                                <td class="check-column"><span class="screen-reader-text">انتخاب</span></td>
                                <th>#</th>
                                <th>بازیکن</th>
                                <th>دوره</th>
                                <th>شعبه</th>
                                <th>مربی</th>
                                <th>جلسات</th>
                                <th>وضعیت</th>
                                <th>صورت‌حساب</th>
                                <th>تاریخ ثبت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) : ?>
                                <?php
                                $status_label = function_exists('sc_private_booking_status_label')
                                    ? sc_private_booking_status_label((string) $row->status)
                                    : $row->status;
                                $badge_class = function_exists('sc_private_booking_status_badge_class')
                                    ? sc_private_booking_status_badge_class((string) $row->status)
                                    : 'sc-pb-status';
                                $form_url = add_query_arg(['page' => 'sc-private-booking-form', 'booking_id' => (int) $row->id], admin_url('admin.php'));
                                $status = (string) $row->status;
                                $player_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                                $coach_name = trim(($row->coach_first_name ?? '') . ' ' . ($row->coach_last_name ?? ''));
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr((int) $row->id); ?>" class="sc-private-booking-row-check">
                                    </th>
                                    <td data-label="#"><strong class="sc-private-regs-id">#<?php echo esc_html((string) $row->id); ?></strong></td>
                                    <td data-label="بازیکن">
                                        <strong><?php echo esc_html($player_name !== '' ? $player_name : '—'); ?></strong>
                                        <?php if (!empty($row->national_id)) : ?>
                                            <span class="sc-private-regs-meta"><?php echo esc_html($row->national_id); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="دوره"><?php echo esc_html($row->course_title ?: '—'); ?></td>
                                    <td data-label="شعبه"><?php echo esc_html($row->chapter !== '' ? $row->chapter : '—'); ?></td>
                                    <td data-label="مربی"><?php echo esc_html($coach_name !== '' ? $coach_name : '—'); ?></td>
                                    <td data-label="جلسات"><?php echo (int) $row->package_sessions > 0 ? esc_html((string) $row->package_sessions) : '—'; ?></td>
                                    <td data-label="وضعیت">
                                        <span class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status_label); ?></span>
                                        <?php if ($status === 'rejected' && !empty($row->rejected_reason)) : ?>
                                            <span class="sc-private-booking-reason-cell"><?php echo esc_html($row->rejected_reason); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="صورت‌حساب">
                                        <?php if (!empty($row->invoice_id)) : ?>
                                            <a class="sc-private-regs-invoice-link" href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices&invoice_id=' . (int) $row->invoice_id)); ?>">صورت‌حساب #<?php echo esc_html((string) $row->invoice_id); ?></a>
                                        <?php else : ?>
                                            <span class="sc-private-regs-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="تاریخ ثبت"><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at) : $row->created_at); ?></td>
                                    <td data-label="عملیات" class="sc-private-regs-actions">
                                        <?php if ($status === 'pending_admin') : ?>
                                            <a class="sc-private-regs-action-primary" href="<?php echo esc_url($form_url); ?>">تکمیل و ثبت</a>
                                        <?php else : ?>
                                            <a class="sc-private-regs-action-link" href="<?php echo esc_url($form_url); ?>">مشاهده</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc-private-regs-pagination">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '‹',
                            'next_text' => '›',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => [
                                'page' => 'sc-private-booking-requests',
                                'filter_status' => $filter_status,
                                'filter_member' => $filter_member,
                            ],
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('sc-private-regs-filters-toggle');
    var panel = document.getElementById('sc-private-regs-filters-panel');
    var card = toggle ? toggle.closest('.sc-private-regs-list-filters-card') : null;
    var label = toggle ? toggle.querySelector('.sc-private-regs-list-filters-toggle-label') : null;

    if (toggle && panel && card && label) {
        toggle.addEventListener('click', function () {
            var isOpen = card.classList.contains('is-open');
            if (isOpen) {
                card.classList.remove('is-open');
                panel.setAttribute('hidden', 'hidden');
                toggle.setAttribute('aria-expanded', 'false');
                label.textContent = label.getAttribute('data-label-closed');
            } else {
                card.classList.add('is-open');
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                label.textContent = label.getAttribute('data-label-open');
            }
        });
    }

    var checkAll = document.getElementById('sc-private-bookings-check-all');
    var bulkAction = document.getElementById('sc-private-bookings-bulk-action');
    var bulkStatus = document.getElementById('sc-private-bookings-bulk-status');
    var bulkReason = document.getElementById('sc-private-bookings-bulk-reason');
    var bulkForm = document.getElementById('sc-private-bookings-bulk-form');

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.sc-private-booking-row-check').forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
        });
    }

    function syncBulkFields() {
        if (!bulkAction || !bulkStatus || !bulkReason) {
            return;
        }
        var action = bulkAction.value;
        bulkStatus.hidden = action !== 'set_status';
        bulkReason.hidden = action !== 'reject' && action !== 'set_status';
    }

    if (bulkAction) {
        bulkAction.addEventListener('change', syncBulkFields);
        syncBulkFields();
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            var action = bulkAction ? bulkAction.value : '';
            var checked = document.querySelectorAll('.sc-private-booking-row-check:checked').length;
            if (!action) {
                e.preventDefault();
                window.alert('عملیات دست‌جمعی را انتخاب کنید.');
                return false;
            }
            if (!checked) {
                e.preventDefault();
                window.alert('حداقل یک رزرو را انتخاب کنید.');
                return false;
            }
            if (action === 'set_status' && bulkStatus && !bulkStatus.value) {
                e.preventDefault();
                window.alert('وضعیت جدید را انتخاب کنید.');
                return false;
            }
            if (action === 'delete' && !window.confirm('رزروهای انتخاب‌شده حذف شوند؟ اگر صورت‌حساب داشته باشند حذف نمی‌شوند.')) {
                e.preventDefault();
                return false;
            }
            if (action === 'reject' && !window.confirm('درخواست‌های انتخاب‌شده رد شوند؟')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>
