<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
global $wpdb;
$is_coach = !empty($GLOBALS['sc_private_notes_is_coach']);
$list_page = $is_coach ? 'sc-coach-private-notes' : 'sc-private-notes';
$add_page = $is_coach ? 'sc-coach-add-private-note' : 'sc-add-private-note';
$view_page = 'sc-private-notes-view';
$list_url = admin_url('admin.php?page=' . $list_page);
$add_url = admin_url('admin.php?page=' . $add_page);
$members_table = $wpdb->prefix . 'sc_members';

if ($is_coach && function_exists('sc_support_get_coach_id_by_user_id')) {
    $coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
    $allowed_member_ids = $coach_id > 0 ? sc_private_notes_get_member_ids_for_coach($coach_id) : [];
    if (!empty($allowed_member_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_member_ids), '%d'));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $members_table WHERE id IN ($placeholders) ORDER BY last_name ASC, first_name ASC",
            ...$allowed_member_ids
        ));
    } else {
        $members = [];
    }
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';

$today_gregorian = current_time('Y-m-d');
$ten_days_before_gregorian = gmdate('Y-m-d', strtotime('-10 days', strtotime($today_gregorian)));
$default_from_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($ten_days_before_gregorian) : $ten_days_before_gregorian;
$default_to_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;

$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $default_from_display;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $default_to_display;

$filter_date_from = $filter_date_from_shamsi !== ''
    ? sc_shamsi_to_gregorian_date($filter_date_from_shamsi)
    : $ten_days_before_gregorian;
$filter_date_to = $filter_date_to_shamsi !== ''
    ? sc_shamsi_to_gregorian_date($filter_date_to_shamsi)
    : $today_gregorian;

$result = sc_private_notes_query_admin([
    'member_id' => $filter_member,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'search' => $search,
    'per_page' => 20,
    'page' => $page,
]);
$rows = $result['rows'];

$legacy_rows = [];
if (!$is_coach) {
    $legacy_rows = $wpdb->get_results("SELECT n.*, TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS member_name FROM {$wpdb->prefix}sc_private_notes n LEFT JOIN {$wpdb->prefix}sc_members m ON m.id = n.member_id ORDER BY n.created_at DESC LIMIT 20");
}

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($search !== '') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = trim($m->first_name . ' ' . $m->last_name) . ' - ' . ($m->national_id ?: '-');
            break;
        }
    }
}
?>
<div class="wrap sc-private-notes-wrap sc-pn-list-wrap<?php echo $is_coach ? ' sc-coach-panel-wrap' : ''; ?>">
    <div class="sc-pn-list-header">
        <div class="sc-pn-list-header-text">
            <h1 class="sc-pn-list-title">پرونده‌های یادداشت خصوصی</h1>
            <p class="sc-pn-list-desc"><?php echo $is_coach ? 'یادداشت‌های خصوصی بازیکنان شما' : 'مدیریت پرونده‌ها و یادداشت‌های خصوصی بازیکنان'; ?></p>
        </div>
        <div class="sc-pn-list-header-actions">
            <a href="<?php echo esc_url($add_url); ?>" class="sc-pn-list-add-btn">افزودن یادداشت</a>
        </div>
    </div>

    <div class="sc-pn-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-pn-list-filters-toolbar">
            <button type="button" class="sc-pn-list-filters-toggle" id="sc-pn-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-pn-filters-panel">
                <span class="sc-pn-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <span class="sc-pn-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-pn-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-pn-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($list_url); ?>" class="sc-pn-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-pn-list-filters-panel" id="sc-pn-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search"><input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی..."></div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                                <?php foreach ($members as $member) :
                                    $label = trim((string) $member->first_name . ' ' . (string) $member->last_name);
                                    if ($label === '') {
                                        $label = 'کاربر #' . (int) $member->id;
                                    }
                                    $label = $label . ' - ' . ($member->national_id ?: '-');
                                    ?>
                                    <div class="sc-dropdown-option sc-visible"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($label)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($label); ?>')"><?php echo esc_html($label); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">جستجو</label>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." class="sc-filter-control">
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range sc-pn-date-range">
                        <input type="text" id="filter_date_from_shamsi" name="filter_date_from_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_from_shamsi); ?>" placeholder="از تاریخ" readonly>
                        <input type="text" id="filter_date_to_shamsi" name="filter_date_to_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_to_shamsi); ?>" placeholder="تا تاریخ" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                </div>
            </div>

            <div class="sc-pn-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($list_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-pn-list-table-card">
        <div class="sc-pn-list-summary"><span><?php echo (int) $result['total']; ?> پرونده</span></div>
        <div class="sc-pn-table-scroll">
            <table class="wp-list-table widefat striped sc-pn-table">
                <thead>
                    <tr>
                        <th class="manage-column">ردیف</th>
                        <th class="manage-column">کاربر</th>
                        <th class="manage-column">نام پرونده</th>
                        <th class="manage-column">آخرین پیام</th>
                        <th class="manage-column">تعداد پیام</th>
                        <th class="manage-column">آخرین فعالیت</th>
                        <th class="manage-column">مشاهده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="7" class="sc-pn-empty">موردی یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php $idx = (($result['page'] - 1) * $result['per_page']) + 1; ?>
                        <?php foreach ($rows as $row) :
                            $view_url = add_query_arg(['page' => $view_page, 'thread_id' => (int) $row->id], admin_url('admin.php'));
                            $member_name = $row->member_name ?: ('کاربر #' . (int) $row->member_id);
                            $initials = '؟';
                            $parts = preg_split('/\s+/u', trim($member_name));
                            if (!empty($parts[0])) {
                                $initials = mb_substr($parts[0], 0, 1);
                                if (!empty($parts[1])) {
                                    $initials .= mb_substr($parts[1], 0, 1);
                                }
                            }
                            $subject = trim((string) $row->subject) !== '' ? (string) $row->subject : ('پرونده #' . (int) $row->id);
                            ?>
                            <tr>
                                <td data-label="ردیف"><?php echo (int) $idx++; ?></td>
                                <td data-label="کاربر">
                                    <div class="sc-pn-user-cell">
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                        <strong><?php echo esc_html($member_name); ?></strong>
                                    </div>
                                </td>
                                <td data-label="نام پرونده"><?php echo esc_html($subject); ?></td>
                                <td data-label="آخرین پیام"><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $row->last_message_excerpt), 12)); ?></td>
                                <td data-label="تعداد پیام"><span class="sc-badge sc-badge--soft"><?php echo (int) $row->messages_count; ?></span></td>
                                <td data-label="آخرین فعالیت"><?php echo esc_html(sc_date_shamsi($row->updated_at, 'Y/m/d H:i')); ?></td>
                                <td data-label="مشاهده"><a class="sc-pn-action-link" href="<?php echo esc_url($view_url); ?>">جزئیات</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['total_pages'] > 1) : ?>
            <div class="tablenav bottom sc_paginate">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '‹',
                        'next_text' => '›',
                        'total' => $result['total_pages'],
                        'current' => $result['page'],
                        'add_args' => [
                            'page' => $list_page,
                            'filter_member' => $filter_member,
                            'filter_date_from_shamsi' => $filter_date_from_shamsi,
                            'filter_date_to_shamsi' => $filter_date_to_shamsi,
                            's' => $search,
                        ],
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$is_coach) : ?>
    <div class="sc-pn-list-table-card sc-pn-legacy-card">
        <h2 class="sc-pn-card-title">یادداشت‌های قدیمی (Legacy)</h2>
        <div class="sc-pn-table-scroll">
            <table class="wp-list-table widefat striped sc-pn-table">
                <thead>
                    <tr>
                        <th class="manage-column">کاربر</th>
                        <th class="manage-column">متن</th>
                        <th class="manage-column">تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($legacy_rows)) : ?>
                        <tr><td colspan="3" class="sc-pn-empty">موردی وجود ندارد.</td></tr>
                    <?php else : foreach ($legacy_rows as $legacy) : ?>
                        <tr>
                            <td data-label="کاربر"><?php echo esc_html($legacy->member_name ?: ('کاربر #' . (int) $legacy->member_id)); ?></td>
                            <td data-label="متن"><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $legacy->content), 16)); ?></td>
                            <td data-label="تاریخ"><?php echo esc_html(sc_date_shamsi($legacy->created_at, 'Y/m/d H:i')); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($){
    var $toggle = $('#sc-pn-filters-toggle');
    var $panel = $('#sc-pn-filters-panel');
    var $card = $toggle.closest('.sc-pn-list-filters-card');
    var $label = $toggle.find('.sc-pn-list-filters-toggle-label');
    $toggle.on('click', function(){
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
