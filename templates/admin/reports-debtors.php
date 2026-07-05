<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$chapter_categories_table = $wpdb->prefix . 'sc_chapter_categories';

// دریافت فیلترها
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_chapter'])) : '';
$filter_group_raw = isset($_GET['filter_group']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_group'])) : '';
$filter_group = function_exists('sc_finance_normalize_group_filter')
    ? sc_finance_normalize_group_filter($filter_course, $filter_group_raw)
    : '';

// دریافت لیست دوره‌ها، شعبه‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$chapters = $wpdb->get_results("SELECT name FROM $chapter_categories_table ORDER BY name ASC");
$debtors_course_groups_map = function_exists('sc_finance_course_groups_map_for_ui')
    ? sc_finance_course_groups_map_for_ui($courses)
    : [];
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause برای دریافت اعضای بدهکار
$where_conditions = ['m.is_active = 1'];
$where_values = [];

// فیلتر کاربر
if ($filter_member > 0) {
    $where_conditions[] = "m.id = %d";
    $where_values[] = $filter_member;
}

// فیلتر ثبت‌نام (دوره / شعبه / گروه)
$mc_conditions = ["status = 'active'"];
$mc_values = [];
$needs_mc_filter = false;

if ($filter_course > 0) {
    $mc_conditions[] = 'course_id = %d';
    $mc_values[] = $filter_course;
    $needs_mc_filter = true;
}
if ($filter_chapter !== '') {
    $mc_conditions[] = 'chapter = %s';
    $mc_values[] = $filter_chapter;
    $needs_mc_filter = true;
}
if ($filter_group !== '') {
    if ($filter_group === '__none__') {
        $mc_conditions[] = "COALESCE(group_name, '') = ''";
    } else {
        $mc_conditions[] = 'group_name = %s';
        $mc_values[] = $filter_group;
    }
    $needs_mc_filter = true;
}

if ($needs_mc_filter) {
    $mc_where = implode(' AND ', $mc_conditions);
    $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE $mc_where)";
    $where_values = array_merge($where_values, $mc_values);
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت اعضا
$query = "SELECT m.* 
          FROM $members_table m 
          WHERE $where_clause 
          ORDER BY m.last_name ASC, m.first_name ASC";

if (!empty($where_values)) {
    $members = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $members = $wpdb->get_results($query);
}

// محاسبه بدهی برای هر کاربر
$debtors = [];
foreach ($members as $member) {
    // محاسبه کل مبلغ و تعداد صورت حساب‌های پرداخت نشده
    $sum_count_debt = debt_user($member->id);
    $debt_amount = $sum_count_debt[0];
    $debt_count = $sum_count_debt[1];
    
    // فقط اگر بدهی داشته باشد، به لیست اضافه می‌کنیم
    if ($debt_amount > 0) {
        $member->debt_amount = $debt_amount;
        $member->debt_count = $debt_count;
        
        // دریافت دوره‌های فعال
        $member_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title 
             FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
             ORDER BY c.title ASC",
            $member->id
        ));
        $member->active_courses = $member_courses;
        
        $debtors[] = $member;
    }
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_items = count($debtors);
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;
$debtors = array_slice($debtors, $offset, $per_page);

$export_url = admin_url('admin.php?page=sc-reports-debtors&sc_export=excel&export_type=debtors');
if ($filter_member > 0) {
    $export_url = add_query_arg('filter_member', $filter_member, $export_url);
}
if ($filter_course > 0) {
    $export_url = add_query_arg('filter_course', $filter_course, $export_url);
}
if ($filter_chapter !== '') {
    $export_url = add_query_arg('filter_chapter', $filter_chapter, $export_url);
}
if ($filter_group !== '') {
    $export_url = add_query_arg('filter_group', $filter_group, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');

$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($all_members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($filter_course > 0) {
    $active_filters_count++;
}
if ($filter_chapter !== '') {
    $active_filters_count++;
}
if ($filter_group !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">بدهکاران</h1>
            <p class="sc-reports-list-desc">لیست بازیکنانی که صورت‌حساب پرداخت‌نشده دارند.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-reports-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-reports-debtors-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-reports-debtors-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-debtors')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
        <form method="GET" action="" class="sc-reports-list-filters-panel" id="sc-reports-debtors-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-reports-debtors">
            <div class="sc-filter-grid sc-debtors-filter-grid">
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
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه کاربران" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                                <?php
                                $display_count = 0;
                                $max_display = 15;
                                foreach ($all_members as $member_option) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($member_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member_option->id); ?>','<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_chapter">شعبه</label>
                    <select name="filter_chapter" id="filter_chapter" class="sc-filter-control">
                        <option value="">همه شعبه‌ها</option>
                        <?php foreach ($chapters as $chapter_item) :
                            $chapter_name = isset($chapter_item->name) ? (string) $chapter_item->name : '';
                            if ($chapter_name === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($chapter_name); ?>" <?php selected($filter_chapter, $chapter_name); ?>>
                                <?php echo esc_html($chapter_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field sc-finance-group-field" id="sc-debtors-group-field"<?php echo ($filter_course > 0 && isset($debtors_course_groups_map[$filter_course])) ? '' : ' hidden'; ?>>
                    <label class="sc-filter-label" for="filter_group">گروه</label>
                    <select name="filter_group" id="filter_group" class="sc-filter-control">
                        <option value="">همه گروه‌ها</option>
                        <option value="__none__" <?php selected($filter_group, '__none__'); ?>>بدون گروه</option>
                        <?php
                        if ($filter_course > 0 && !empty($debtors_course_groups_map[$filter_course])) :
                            foreach ($debtors_course_groups_map[$filter_course] as $gname) :
                                ?>
                                <option value="<?php echo esc_attr($gname); ?>" <?php selected($filter_group, $gname); ?>><?php echo esc_html($gname); ?></option>
                            <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                </div>
            </div>
            <div class="sc-reports-list-filters-actions">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($export_url); ?>" class="button button_export">خروجی Excel</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-debtors')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-reports-list-table-card">
    <?php if (empty($debtors)) : ?>
        <div class="sc-reports-empty">هیچ بدهکاری یافت نشد.</div>
    <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>نام و نام خانوادگی</th>
                        <th>دوره‌های فعال</th>
                        <th>مبلغ</th>
                        <th>تعداد بدهی‌ها</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($debtors as $index => $debtor) :
                        $row_number = $start_number + $index + 1;
                        $full_name = trim($debtor->first_name . ' ' . $debtor->last_name);
                        $photo = !empty($debtor->personal_photo) ? $debtor->personal_photo : '';
                        $initials = '';
                        if (!empty($debtor->first_name)) {
                            $initials .= mb_substr((string) $debtor->first_name, 0, 1);
                        }
                        if (!empty($debtor->last_name)) {
                            $initials .= mb_substr((string) $debtor->last_name, 0, 1);
                        }
                        if ($initials === '') {
                            $initials = '؟';
                        }
                        $course_names = [];
                        if (!empty($debtor->active_courses)) {
                            foreach ($debtor->active_courses as $course) {
                                $course_names[] = $course->title;
                            }
                        }
                        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '—';
                    ?>
                        <tr class="is-debt-row">
                            <td><?php echo (int) $row_number; ?></td>
                            <td>
                                <span class="sc-member-identity">
                                    <?php if ($photo) : ?>
                                        <span class="sc-member-avatar"><img src="<?php echo esc_url($photo); ?>" alt="" loading="lazy"></span>
                                    <?php else : ?>
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                    <?php endif; ?>
                                    <span class="sc-member-identity-text">
                                        <span class="sc-member-name"><?php echo esc_html($full_name); ?></span>
                                        <?php if (!empty($debtor->national_id)) : ?>
                                            <span class="sc-member-meta"><span class="sc-member-meta-item"><?php echo esc_html($debtor->national_id); ?></span></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td><?php echo esc_html($courses_text); ?></td>
                            <td><span class="sc-reports-amount-debit"><?php echo esc_html(number_format($debtor->debt_amount, 0, '.', ',')); ?> تومان</span></td>
                            <td><span class="sc-badge sc-badge--danger"><?php echo (int) $debtor->debt_count; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg(['paged' => '%#%']),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
    <?php endif; ?>
    </div>
</div>
<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-reports-debtors-filters-toggle');
    var $panel = $('#sc-reports-debtors-filters-panel');
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

    var debtorsCourseGroups = <?php echo wp_json_encode($debtors_course_groups_map, JSON_UNESCAPED_UNICODE); ?>;
    var selectedGroup = <?php echo wp_json_encode($filter_group, JSON_UNESCAPED_UNICODE); ?>;

    function refreshDebtorsGroupField() {
        var $field = $('#sc-debtors-group-field');
        var $sel = $('#filter_group');
        if (!$field.length || !$sel.length) {
            return;
        }
        var courseId = parseInt($('#filter_course').val(), 10) || 0;
        var groups = debtorsCourseGroups[courseId] || debtorsCourseGroups[String(courseId)] || [];
        $sel.find('option').not('[value=""], [value="__none__"]').remove();
        if (!courseId || !groups.length) {
            $field.attr('hidden', true);
            $sel.val('');
            return;
        }
        $field.removeAttr('hidden');
        groups.forEach(function (name) {
            $sel.append($('<option></option>').val(name).text(name));
        });
        if (selectedGroup && $sel.find('option[value="' + selectedGroup.replace(/"/g, '\\"') + '"]').length) {
            $sel.val(selectedGroup);
        } else {
            $sel.val('');
        }
    }

    $('#filter_course').on('change', function () {
        selectedGroup = '';
        refreshDebtorsGroupField();
    });
    refreshDebtorsGroupField();
});
</script>
