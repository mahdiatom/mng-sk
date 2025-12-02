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

// دریافت فیلترها
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_debt_status = isset($_GET['filter_debt_status']) ? sanitize_text_field($_GET['filter_debt_status']) : 'all';
$filter_insurance_status = isset($_GET['filter_insurance_status']) ? sanitize_text_field($_GET['filter_insurance_status']) : 'all';
$filter_profile_status = isset($_GET['filter_profile_status']) ? sanitize_text_field($_GET['filter_profile_status']) : 'all';

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause
$where_conditions = ['m.is_active = 1'];
$where_values = [];

// فیلتر کاربر
if ($filter_member > 0) {
    $where_conditions[] = "m.id = %d";
    $where_values[] = $filter_member;
}

// فیلتر دوره
if ($filter_course > 0) {
    $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')";
    $where_values[] = $filter_course;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت اعضا (بدون LIMIT برای امکان فیلتر کردن)
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
foreach ($members as $member) {
    // محاسبه کل مبلغ صورت حساب‌های پرداخت نشده
    $debt_query = "SELECT SUM(amount) as total_debt 
                   FROM $invoices_table 
                   WHERE member_id = %d 
                   AND status IN ('pending')";
    
    $debt_result = $wpdb->get_var($wpdb->prepare($debt_query, $member->id));
    $member->debt_amount = $debt_result ? floatval($debt_result) : 0;
    $member->has_debt = $member->debt_amount > 0;
    
    // بررسی وضعیت بیمه
    if (!empty($member->insurance_expiry_date_shamsi)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $today_shamsi = $today_jalali[0] . '/' . 
                       str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                       str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        
        $expiry_parts = explode('/', $member->insurance_expiry_date_shamsi);
        $today_parts = explode('/', $today_shamsi);
        
        if (count($expiry_parts) === 3 && count($today_parts) === 3) {
            $expiry_year = (int)$expiry_parts[0];
            $expiry_month = (int)$expiry_parts[1];
            $expiry_day = (int)$expiry_parts[2];
            
            $today_year = (int)$today_parts[0];
            $today_month = (int)$today_parts[1];
            $today_day = (int)$today_parts[2];
            
            $is_expired = false;
            if ($expiry_year < $today_year) {
                $is_expired = true;
            } elseif ($expiry_year == $today_year) {
                if ($expiry_month < $today_month) {
                    $is_expired = true;
                } elseif ($expiry_month == $today_month) {
                    if ($expiry_day < $today_day) {
                        $is_expired = true;
                    }
                }
            }
            
            $member->insurance_active = !$is_expired;
        } else {
            $member->insurance_active = false;
        }
    } else {
        $member->insurance_active = false;
    }
    
    // بررسی تکمیل پروفایل
    $member->profile_completed = sc_check_profile_completed($member->id);
    
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
}

// اعمال فیلترهای بعد از دریافت داده‌ها
$filtered_members = [];
foreach ($members as $member) {
    // فیلتر وضعیت بدهی
    if ($filter_debt_status !== 'all') {
        if ($filter_debt_status === 'has_debt' && !$member->has_debt) {
            continue;
        }
        if ($filter_debt_status === 'no_debt' && $member->has_debt) {
            continue;
        }
    }
    
    // فیلتر وضعیت بیمه
    if ($filter_insurance_status !== 'all') {
        if ($filter_insurance_status === 'active' && !$member->insurance_active) {
            continue;
        }
        if ($filter_insurance_status === 'expired' && $member->insurance_active) {
            continue;
        }
    }
    
    // فیلتر وضعیت پروفایل
    if ($filter_profile_status !== 'all') {
        if ($filter_profile_status === 'completed' && !$member->profile_completed) {
            continue;
        }
        if ($filter_profile_status === 'incomplete' && $member->profile_completed) {
            continue;
        }
    }
    
    $filtered_members[] = $member;
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_items = count($filtered_members);
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;
$members = array_slice($filtered_members, $offset, $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه - کاربران فعال</h1>
    <hr class="wp-header-end">
    
    <!-- فیلترها -->
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-reports-active-users">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="filter_member">کاربر</label>
                </th>
                <td>
                    <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
                        <?php 
                        $selected_member_text = 'همه کاربران';
                        if ($filter_member > 0) {
                            foreach ($all_members as $m) {
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
                                foreach ($all_members as $member_option) : 
                                    $is_selected = ($filter_member == $member_option->id);
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                         data-value="<?php echo esc_attr($member_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectMemberFilter(this, '<?php echo esc_js($member_option->id); ?>', '<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
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
                    <label for="filter_debt_status">وضعیت بدهی</label>
                </th>
                <td>
                    <select name="filter_debt_status" id="filter_debt_status" style="width: 300px; padding: 5px;">
                        <option value="all" <?php selected($filter_debt_status, 'all'); ?>>همه</option>
                        <option value="has_debt" <?php selected($filter_debt_status, 'has_debt'); ?>>دارد</option>
                        <option value="no_debt" <?php selected($filter_debt_status, 'no_debt'); ?>>ندارد</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_insurance_status">بیمه</label>
                </th>
                <td>
                    <select name="filter_insurance_status" id="filter_insurance_status" style="width: 300px; padding: 5px;">
                        <option value="all" <?php selected($filter_insurance_status, 'all'); ?>>همه</option>
                        <option value="active" <?php selected($filter_insurance_status, 'active'); ?>>فعال</option>
                        <option value="expired" <?php selected($filter_insurance_status, 'expired'); ?>>منقضی</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_profile_status">وضعیت پروفایل</label>
                </th>
                <td>
                    <select name="filter_profile_status" id="filter_profile_status" style="width: 300px; padding: 5px;">
                        <option value="all" <?php selected($filter_profile_status, 'all'); ?>>همه</option>
                        <option value="completed" <?php selected($filter_profile_status, 'completed'); ?>>تکمیل</option>
                        <option value="incomplete" <?php selected($filter_profile_status, 'incomplete'); ?>>ناقص</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo admin_url('admin.php?page=sc-reports-active-users'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
    
    <!-- لیست کاربران -->
    <?php if (empty($members)) : ?>
        <div class="notice notice-info">
            <p>هیچ کاربر فعالی یافت نشد.</p>
        </div>
    <?php else : ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>نام و نام خانوادگی</th>
                        <th>دوره‌های فعال</th>
                        <th>شماره تماس</th>
                        <th>مقدار بدهی</th>
                        <th>وضعیت پروفایل</th>
                        <th>بیمه</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($members as $index => $member) : 
                        $row_number = $start_number + $index + 1;
                        
                        // دوره‌های فعال
                        $course_names = [];
                        if (!empty($member->active_courses)) {
                            foreach ($member->active_courses as $course) {
                                $course_names[] = $course->title;
                            }
                        }
                        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
                    ?>
                        <tr>
                            <td><?php echo $row_number; ?></td>
                            <td>
                                <strong><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></strong>
                            </td>
                            <td><?php echo esc_html($courses_text); ?></td>
                            <td><?php echo esc_html($member->player_phone ?: '-'); ?></td>
                            <td>
                                <?php if ($member->has_debt) : ?>
                                    <span style="color: #d63638; font-weight: bold;">
                                        <?php echo number_format($member->debt_amount, 0, '.', ','); ?> تومان
                                    </span>
                                <?php else : ?>
                                    <span style="color: #00a32a;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($member->profile_completed) : ?>
                                    <span style="color: #00a32a; font-weight: bold;">✓ تکمیل</span>
                                <?php else : ?>
                                    <span style="color: #d63638; font-weight: bold;">✗ ناقص</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($member->insurance_expiry_date_shamsi)) : ?>
                                    <?php if ($member->insurance_active) : ?>
                                        <span style="color: #00a32a; font-weight: bold;">✓ فعال</span>
                                    <?php else : ?>
                                        <span style="color: #d63638; font-weight: bold;">✗ منقضی</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
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
                            'base' => add_query_arg(['paged' => '%#%']),
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
</div>

<!-- JavaScript for dropdown -->
<script type="text/javascript">
// تابع انتخاب کاربر در فیلتر
function scSelectMemberFilter(element, memberId, memberText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');
    
    // تنظیم مقدار
    $hiddenInput.val(memberId);
    if (memberId == '0') {
        $placeholder.show();
        $selected.hide();
    } else {
        $placeholder.hide();
        $selected.text(memberText).show();
    }
    
    // بستن منو
    $menu.slideUp(200);
    
    // حذف انتخاب قبلی و اضافه کردن انتخاب جدید
    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    jQuery(element).addClass('sc-selected').css('background', '#f0f6fc');
    
    // اضافه کردن چکمارک
    $dropdown.find('.sc-dropdown-option span').remove();
    jQuery(element).append('<span style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

jQuery(document).ready(function($) {
    // مدیریت باز و بسته شدن dropdown
    $('.sc-dropdown-toggle').on('click', function(e) {
        e.stopPropagation();
        var $menu = $(this).siblings('.sc-dropdown-menu');
        var isOpen = $menu.is(':visible');
        
        // بستن همه dropdown‌ها
        $('.sc-dropdown-menu').slideUp(200);
        
        if (!isOpen) {
            $menu.slideDown(200);
            setTimeout(function() {
                $menu.find('.sc-search-input').focus();
            }, 250);
        }
    });
    
    // جستجو در dropdown
    $('.sc-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var $options = $(this).closest('.sc-dropdown-menu').find('.sc-dropdown-option');
        var visibleCount = 0;
        var maxVisible = 10;
        
        $options.closest('.sc-dropdown-options').find('div:not(.sc-dropdown-option)').remove();
        
        if (searchTerm === '') {
            $options.each(function(index) {
                if (index < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
        } else {
            $options.each(function() {
                var searchText = $(this).attr('data-search') || '';
                var matches = searchText.includes(searchTerm);
                
                if (matches && visibleCount < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
            
            if (visibleCount === 0) {
                $options.closest('.sc-dropdown-options').append(
                    '<div style="padding: 15px; text-align: center; color: #757575; border-bottom: 1px solid #f0f0f1;">نتیجه‌ای یافت نشد</div>'
                );
            }
        }
    });
    
    // بستن dropdown با کلیک خارج
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sc-searchable-dropdown').length) {
            $('.sc-dropdown-menu').slideUp(200);
        }
    });
    
    $('.sc-dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });
});
</script>

<style>
.sc-dropdown-option:hover {
    background: #f0f6fc !important;
}
.sc-dropdown-option.sc-selected {
    background: #f0f6fc;
}
.sc-searchable-dropdown {
    direction: rtl;
}
.sc-dropdown-menu::-webkit-scrollbar {
    width: 8px;
}
.sc-dropdown-menu::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.sc-dropdown-menu::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.sc-dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

