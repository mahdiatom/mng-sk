<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$attendances_table = $wpdb->prefix . 'sc_attendances';
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';

// دریافت تب فعال
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'individual';

// پردازش حذف (فقط برای تب اول)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['attendance_id']) && $active_tab === 'individual') {
    check_admin_referer('delete_attendance_' . $_GET['attendance_id']);
    
    $attendance_id = absint($_GET['attendance_id']);
    $deleted = $wpdb->delete(
        $attendances_table,
        ['id' => $attendance_id],
        ['%d']
    );
    
    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>حضور و غیاب با موفقیت حذف شد.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف حضور و غیاب.</p></div>';
    }
}

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ==================== تب 1: لیست حضور و غیاب کاربران ====================
if ($active_tab === 'individual') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // اگر filter_date_from_shamsi_2 یا filter_date_to_shamsi_2 موجود بود
    if (isset($_GET['filter_date_from_shamsi_2']) && !empty($_GET['filter_date_from_shamsi_2'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi_2']));
    }
    if (isset($_GET['filter_date_to_shamsi_2']) && !empty($_GET['filter_date_to_shamsi_2'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi_2']));
    }
    
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';

    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];

    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }

    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }

    if ($filter_status !== 'all') {
        $where_conditions[] = "a.status = %s";
        $where_values[] = $filter_status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // دریافت تعداد کل رکوردها برای pagination
    $total_query = "SELECT COUNT(*) FROM $attendances_table a WHERE $where_clause";
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($total_query);
    }

    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // دریافت لیست حضور و غیاب‌ها
    $query_values = $where_values;
    $query = "SELECT a.*, 
                     m.first_name, m.last_name, m.national_id,
                     c.title as course_title
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              INNER JOIN $courses_table c ON a.course_id = c.id
              WHERE $where_clause
              ORDER BY a.attendance_date DESC, a.created_at DESC
              LIMIT %d OFFSET %d";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    if (!empty($query_values)) {
        $attendances = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $attendances = $wpdb->get_results($query);
    }

    // محاسبه تعداد صفحات
    $total_pages = ceil($total_items / $per_page);
}

// ==================== تب 2: لیست گروه‌بندی شده بر اساس دوره و تاریخ ====================
if ($active_tab === 'grouped') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // اگر filter_date_from_shamsi_2 یا filter_date_to_shamsi_2 موجود بود
    if (isset($_GET['filter_date_from_shamsi_2']) && !empty($_GET['filter_date_from_shamsi_2'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi_2']));
    }
    if (isset($_GET['filter_date_to_shamsi_2']) && !empty($_GET['filter_date_to_shamsi_2'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi_2']));
    }

    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];

    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // دریافت لیست گروه‌بندی شده
    $query = "SELECT 
                a.course_id,
                a.attendance_date,
                c.title as course_title,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                COUNT(*) as total_count
              FROM $attendances_table a
              INNER JOIN $courses_table c ON a.course_id = c.id
              WHERE $where_clause
              GROUP BY a.course_id, a.attendance_date
              ORDER BY a.attendance_date DESC, c.title ASC";

    if (!empty($where_values)) {
        $grouped_attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $grouped_attendances = $wpdb->get_results($query);
    }

    // Pagination برای تب 2
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    $total_items = count($grouped_attendances);
    $total_pages = ceil($total_items / $per_page);
    $grouped_attendances = array_slice($grouped_attendances, $offset, $per_page);
}

// ==================== تب 3: لیست کلی حضور و غیاب ====================
if ($active_tab === 'overall') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // اگر filter_date_from_shamsi_3 یا filter_date_to_shamsi_3 موجود بود
    if (isset($_GET['filter_date_from_shamsi_3']) && !empty($_GET['filter_date_from_shamsi_3'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi_3']));
    }
    if (isset($_GET['filter_date_to_shamsi_3']) && !empty($_GET['filter_date_to_shamsi_3'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi_3']));
    }
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }
    
    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت لیست حضور و غیاب‌ها
    $query = "SELECT 
                a.member_id,
                a.attendance_date,
                a.status,
                m.first_name,
                m.last_name
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              WHERE $where_clause
              ORDER BY m.last_name ASC, m.first_name ASC, a.attendance_date ASC";
    
    if (!empty($where_values)) {
        $all_attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $all_attendances = $wpdb->get_results($query);
    }
    
    // ساخت ساختار داده برای نمایش
    $overall_data = [];
    $dates_list = [];
    
    // گروه‌بندی بر اساس member_id و تاریخ
    foreach ($all_attendances as $attendance) {
        $member_id = $attendance->member_id;
        $date_key = $attendance->attendance_date;
        
        if (!isset($overall_data[$member_id])) {
            $overall_data[$member_id] = [
                'name' => $attendance->first_name . ' ' . $attendance->last_name,
                'attendances' => []
            ];
        }
        
        $overall_data[$member_id]['attendances'][$date_key] = $attendance->status;
        
        // اضافه کردن تاریخ به لیست تاریخ‌ها (اگر قبلاً اضافه نشده)
        if (!in_array($date_key, $dates_list)) {
            $dates_list[] = $date_key;
        }
    }
    
    // مرتب‌سازی تاریخ‌ها
    sort($dates_list);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست حضور و غیاب</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-attendance-add'); ?>" class="page-title-action">ثبت حضور و غیاب</a>
    <hr class="wp-header-end">
    
    <!-- تب‌ها -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=sc-attendance-list&tab=individual" class="nav-tab <?php echo $active_tab === 'individual' ? 'nav-tab-active' : ''; ?>">
            لیست حضور و غیاب کاربران
        </a>
        <a href="?page=sc-attendance-list&tab=grouped" class="nav-tab <?php echo $active_tab === 'grouped' ? 'nav-tab-active' : ''; ?>">
            لیست بر اساس دوره و تاریخ
        </a>
        <a href="?page=sc-attendance-list&tab=overall" class="nav-tab <?php echo $active_tab === 'overall' ? 'nav-tab-active' : ''; ?>">
            لیست کلی حضور و غیاب
        </a>
    </h2>
    
    <?php if ($active_tab === 'individual') : ?>
        <!-- تب 1: لیست حضور و غیاب کاربران -->
        <!-- فیلترها -->
        <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" name="page" value="sc-attendance-list">
            <input type="hidden" name="tab" value="individual">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_course">دوره</label>
                    </th>
                    <td>
                        <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                            <option value="0">همه دوره‌ها</option>
                            <?php foreach ($courses as $course) : ?>
                                <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                    <?php echo esc_html($course->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="filter_member">کاربر</label>
                    </th>
                    <td>
                        <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
                            <?php 
                            $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
                            $selected_member_text = 'همه کاربران';
                            if ($filter_member > 0) {
                                foreach ($members as $m) {
                                    if ($m->id == $filter_member) {
                                        $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                            <div class="sc-dropdown-toggle" style="position: relative; cursor: pointer; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 35px 8px 12px; background: #fff; min-height: 30px; display: flex; align-items: center;">
                                <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $filter_member > 0 ? 'none' : 'inline'; ?>;">همه کاربران</span>
                                <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $filter_member > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                            </div>
                            <div class="sc-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-top: -1px;">
                                <div class="sc-dropdown-search" style="padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0; background: #fff;">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی..." style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                                </div>
                                <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                    <div class="sc-dropdown-option sc-visible" 
                                         data-value="0"
                                         data-search="همه کاربران"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $filter_member == 0 ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectMemberFilter(this, '0', 'همه کاربران')">
                                        همه کاربران
                                        <?php if ($filter_member == 0) : ?>
                                            <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $display_count = 0;
                                    $max_display = 10;
                                    foreach ($members as $member) : 
                                        $is_selected = ($filter_member == $member->id);
                                        $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    ?>
                                        <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                             data-value="<?php echo esc_attr($member->id); ?>"
                                             data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                             style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                             onclick="scSelectMemberFilter(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                            <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                            <?php if ($is_selected) : ?>
                                                <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        if ($is_selected) {
                                            $display_count++;
                                        } elseif ($display_count < $max_display) {
                                            $display_count++;
                                        }
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>بازه تاریخ (شمسی)</label>
                    </th>
                    <td>
                        <?php 
                        // تبدیل تاریخ‌های میلادی به شمسی برای نمایش
                        $filter_date_from_shamsi = '';
                        $filter_date_to_shamsi = '';
                        if (!empty($filter_date_from)) {
                            $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_from_shamsi = $today_jalali[0] . '/' . 
                                                       str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                       str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        if (!empty($filter_date_to)) {
                            $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_to_shamsi = $today_jalali[0] . '/' . 
                                                     str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                     str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        ?>
                        <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                               value="<?php echo esc_attr($filter_date_from_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="از تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                        <span>تا</span>
                        <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                               value="<?php echo esc_attr($filter_date_to_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تا تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="filter_status">وضعیت</label>
                    </th>
                    <td>
                        <select name="filter_status" id="filter_status" style="width: 300px; padding: 5px;">
                            <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                            <option value="present" <?php selected($filter_status, 'present'); ?>>حاضر</option>
                            <option value="absent" <?php selected($filter_status, 'absent'); ?>>غایب</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <?php
                // ساخت URL برای export Excel با حفظ فیلترها
                $export_url = admin_url('admin.php?page=sc-attendance-list&sc_export=excel&export_type=attendance');
                $export_url = add_query_arg('filter_course', isset($_GET['filter_course']) ? $_GET['filter_course'] : 0, $export_url);
                $export_url = add_query_arg('filter_member', isset($_GET['filter_member']) ? $_GET['filter_member'] : 0, $export_url);
                if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                    $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
                }
                if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                    $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
                }
                if (isset($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
                    $export_url = add_query_arg('filter_status', $_GET['filter_status'], $export_url);
                }
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                    📊 خروجی Excel
                </a>
                <a href="<?php echo admin_url('admin.php?page=sc-attendance-list&tab=individual'); ?>" class="button">پاک کردن فیلترها</a>
            </p>
        </form>
        
        <!-- لیست حضور و غیاب‌ها -->
        <?php if (empty($attendances)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ردیف</th>
                            <th>تاریخ</th>
                            <th>دوره</th>
                            <th>نام</th>
                            <th>نام خانوادگی</th>
                            <th>کد ملی</th>
                            <th>وضعیت</th>
                            <th style="width: 150px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $start_number = ($current_page - 1) * $per_page;
                        foreach ($attendances as $index => $attendance) : 
                            $row_number = $start_number + $index + 1;
                            $status_label = $attendance->status === 'present' ? 'حاضر' : 'غایب';
                            $status_color = $attendance->status === 'present' ? '#00a32a' : '#d63638';
                            $status_bg = $attendance->status === 'present' ? '#d4edda' : '#ffeaea';
                        ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td>
                                    <strong><?php echo sc_date_shamsi_date_only($attendance->attendance_date); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo sc_date_shamsi($attendance->attendance_date, 'l'); ?></small>
                                </td>
                                <td><?php echo esc_html($attendance->course_title); ?></td>
                                <td><?php echo esc_html($attendance->first_name); ?></td>
                                <td><?php echo esc_html($attendance->last_name); ?></td>
                                <td><?php echo esc_html($attendance->national_id); ?></td>
                                <td>
                                    <span style="
                                        padding: 5px 10px;
                                        border-radius: 4px;
                                        font-weight: bold;
                                        background-color: <?php echo $status_bg; ?>;
                                        color: <?php echo $status_color; ?>;
                                    ">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sc-attendance-add&course_id=' . $attendance->course_id . '&date=' . $attendance->attendance_date); ?>" 
                                       class="button button-small">ویرایش</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sc-attendance-list&tab=individual&action=delete&attendance_id=' . $attendance->id), 'delete_attendance_' . $attendance->id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید این حضور و غیاب را حذف کنید؟');"
                                       style="background-color: #d63638; color: #fff; border-color: #d63638;">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links([
                                'base' => add_query_arg(['paged' => '%#%', 'tab' => 'individual']),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ]);
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_tab === 'grouped') : ?>
        <!-- تب 2: لیست بر اساس دوره و تاریخ -->
        <!-- فیلترها -->
        <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" name="page" value="sc-attendance-list">
            <input type="hidden" name="tab" value="grouped">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_course">دوره</label>
                    </th>
                    <td>
                        <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                            <option value="0">همه دوره‌ها</option>
                            <?php foreach ($courses as $course) : ?>
                                <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                    <?php echo esc_html($course->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>بازه تاریخ</label>
                    </th>
                    <td>
                        <?php 
                        // تبدیل تاریخ‌های میلادی به شمسی برای نمایش
                        $filter_date_from_shamsi_2 = '';
                        $filter_date_to_shamsi_2 = '';
                        if (!empty($filter_date_from)) {
                            $filter_date_from_shamsi_2 = sc_date_shamsi_date_only($filter_date_from);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_from_shamsi_2 = $today_jalali[0] . '/' . 
                                                         str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                         str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        if (!empty($filter_date_to)) {
                            $filter_date_to_shamsi_2 = sc_date_shamsi_date_only($filter_date_to);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_to_shamsi_2 = $today_jalali[0] . '/' . 
                                                       str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                       str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        ?>
                        <input type="text" name="filter_date_from_shamsi_2" id="filter_date_from_shamsi_2" 
                               value="<?php echo esc_attr($filter_date_from_shamsi_2); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="از تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from_2" value="<?php echo esc_attr($filter_date_from); ?>">
                        <span>تا</span>
                        <input type="text" name="filter_date_to_shamsi_2" id="filter_date_to_shamsi_2" 
                               value="<?php echo esc_attr($filter_date_to_shamsi_2); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تا تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to_2" value="<?php echo esc_attr($filter_date_to); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo admin_url('admin.php?page=sc-attendance-list&tab=grouped'); ?>" class="button">پاک کردن فیلترها</a>
            </p>
        </form>
        
        <!-- لیست گروه‌بندی شده -->
        <?php if (empty($grouped_attendances)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ردیف</th>
                            <th>دوره</th>
                            <th>تاریخ</th>
                            <th>تعداد حاضر</th>
                            <th>تعداد غایب</th>
                            <th>کل</th>
                            <th style="width: 150px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $start_number = ($current_page - 1) * $per_page;
                        foreach ($grouped_attendances as $index => $group) : 
                            $row_number = $start_number + $index + 1;
                        ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td><strong><?php echo esc_html($group->course_title); ?></strong></td>
                                <td>
                                    <strong><?php echo sc_date_shamsi_date_only($group->attendance_date); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo sc_date_shamsi($group->attendance_date, 'l'); ?></small>
                                </td>
                                <td>
                                    <span style="
                                        padding: 5px 10px;
                                        border-radius: 4px;
                                        font-weight: bold;
                                        background-color: #d4edda;
                                        color: #00a32a;
                                    ">
                                        <?php echo esc_html($group->present_count); ?> نفر
                                    </span>
                                </td>
                                <td>
                                    <span style="
                                        padding: 5px 10px;
                                        border-radius: 4px;
                                        font-weight: bold;
                                        background-color: #ffeaea;
                                        color: #d63638;
                                    ">
                                        <?php echo esc_html($group->absent_count); ?> نفر
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($group->total_count); ?> نفر</strong>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sc-attendance-add&course_id=' . $group->course_id . '&date=' . $group->attendance_date); ?>" 
                                       class="button button-small">ویرایش</a>
                                    <?php
                                    // ساخت URL برای export Excel این روز
                                    $export_url = admin_url('admin.php?page=sc-attendance-list&sc_export=excel&export_type=attendance_overall');
                                    $export_url = add_query_arg('filter_course', $group->course_id, $export_url);
                                    $export_url = add_query_arg('filter_date_from', $group->attendance_date, $export_url);
                                    $export_url = add_query_arg('filter_date_to', $group->attendance_date, $export_url);
                                    $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                                    ?>
                                    <a href="<?php echo esc_url($export_url); ?>" 
                                       class="button button-small" 
                                       style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                                        📊 Excel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links([
                                'base' => add_query_arg(['paged' => '%#%', 'tab' => 'grouped']),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ]);
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($active_tab === 'overall') : ?>
        <!-- تب 3: لیست کلی حضور و غیاب -->
        <!-- فیلترها -->
        <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" name="page" value="sc-attendance-list">
            <input type="hidden" name="tab" value="overall">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_course">دوره</label>
                    </th>
                    <td>
                        <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                            <option value="0">همه دوره‌ها</option>
                            <?php foreach ($courses as $course) : ?>
                                <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                    <?php echo esc_html($course->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="filter_member">کاربر</label>
                    </th>
                    <td>
                        <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
                            <?php 
                            $selected_member_text = 'همه کاربران';
                            if ($filter_member > 0) {
                                foreach ($members as $m) {
                                    if ($m->id == $filter_member) {
                                        $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                            <div class="sc-dropdown-toggle" style="position: relative; cursor: pointer; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 35px 8px 12px; background: #fff; min-height: 30px; display: flex; align-items: center;">
                                <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $filter_member > 0 ? 'none' : 'inline'; ?>;">همه کاربران</span>
                                <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $filter_member > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                            </div>
                            <div class="sc-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-top: -1px;">
                                <div class="sc-dropdown-search" style="padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0; background: #fff;">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی..." style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                                </div>
                                <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                    <div class="sc-dropdown-option sc-visible" 
                                         data-value="0"
                                         data-search="همه کاربران"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $filter_member == 0 ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectMemberFilter(this, '0', 'همه کاربران')">
                                        همه کاربران
                                        <?php if ($filter_member == 0) : ?>
                                            <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $display_count = 0;
                                    $max_display = 10;
                                    foreach ($members as $member) : 
                                        $is_selected = ($filter_member == $member->id);
                                        $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    ?>
                                        <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                             data-value="<?php echo esc_attr($member->id); ?>"
                                             data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                             style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                             onclick="scSelectMemberFilter(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                            <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                            <?php if ($is_selected) : ?>
                                                <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        if ($is_selected) {
                                            $display_count++;
                                        } elseif ($display_count < $max_display) {
                                            $display_count++;
                                        }
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>بازه تاریخ</label>
                    </th>
                    <td>
                        <?php 
                        // تبدیل تاریخ‌های میلادی به شمسی برای نمایش
                        $filter_date_from_shamsi_3 = '';
                        $filter_date_to_shamsi_3 = '';
                        if (!empty($filter_date_from)) {
                            $filter_date_from_shamsi_3 = sc_date_shamsi_date_only($filter_date_from);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_from_shamsi_3 = $today_jalali[0] . '/' . 
                                                           str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                           str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        if (!empty($filter_date_to)) {
                            $filter_date_to_shamsi_3 = sc_date_shamsi_date_only($filter_date_to);
                        } else {
                            // تاریخ پیش‌فرض: امروز
                            $today = new DateTime();
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_to_shamsi_3 = $today_jalali[0] . '/' . 
                                                         str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                         str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                        ?>
                        <input type="text" name="filter_date_from_shamsi_3" id="filter_date_from_shamsi_3" 
                               value="<?php echo esc_attr($filter_date_from_shamsi_3); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="از تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from_3" value="<?php echo esc_attr($filter_date_from); ?>">
                        <span>تا</span>
                        <input type="text" name="filter_date_to_shamsi_3" id="filter_date_to_shamsi_3" 
                               value="<?php echo esc_attr($filter_date_to_shamsi_3); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تا تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to_3" value="<?php echo esc_attr($filter_date_to); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <?php
                // ساخت URL برای export Excel
                $export_url = admin_url('admin.php?page=sc-attendance-list&sc_export=excel&export_type=attendance_overall');
                $export_url = add_query_arg('filter_course', isset($_GET['filter_course']) ? $_GET['filter_course'] : 0, $export_url);
                $export_url = add_query_arg('filter_member', isset($_GET['filter_member']) ? $_GET['filter_member'] : 0, $export_url);
                if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                    $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
                }
                if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                    $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
                }
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                    📊 خروجی Excel
                </a>
                <a href="<?php echo admin_url('admin.php?page=sc-attendance-list&tab=overall'); ?>" class="button">پاک کردن فیلترها</a>
            </p>
        </form>
        
        <!-- جدول کلی حضور و غیاب -->
        <?php if (empty($overall_data) || empty($dates_list)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 200px; position: sticky; right: 0; background: #fff; z-index: 10; border-right: 2px solid #ddd;">نام و نام خانوادگی</th>
                            <?php foreach ($dates_list as $date) : ?>
                                <th style="min-width: 100px; text-align: center;"><?php echo esc_html(sc_date_shamsi_date_only($date)); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overall_data as $member_id => $member_data) : ?>
                            <tr>
                                <td style="position: sticky; right: 0; background: #fff; z-index: 10; border-right: 2px solid #ddd; font-weight: bold;">
                                    <?php echo esc_html($member_data['name']); ?>
                                </td>
                                <?php foreach ($dates_list as $date) : ?>
                                    <td style="text-align: center;">
                                        <?php 
                                        if (isset($member_data['attendances'][$date])) {
                                            $status = $member_data['attendances'][$date];
                                            if ($status === 'present') {
                                                echo '<span style="color: #00a32a; font-weight: bold; font-size: 18px;">✓</span>';
                                            } else {
                                                echo '<span style="color: #d63638; font-weight: bold; font-size: 18px;">✗</span>';
                                            }
                                        } else {
                                            echo '<span style="color: #999;">-</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>






