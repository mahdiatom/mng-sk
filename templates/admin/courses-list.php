<?php

if ( ! defined('ABSPATH') ) exit;
//این فایل برای نمایش پاپ اپ است در صفحه لیست دوره ها
global $courses_list_table, $wpdb;

$chapter_table = $wpdb->prefix . 'sc_chapter_categories';
$chapters_filter = $wpdb->get_results("SELECT `name` FROM `$chapter_table` ORDER BY id ASC");

$filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
$filter_course_type = isset($_GET['filter_course_type']) ? sanitize_text_field(wp_unslash($_GET['filter_course_type'])) : 'all';
$filter_capacity_status = isset($_GET['filter_capacity_status']) ? sanitize_text_field(wp_unslash($_GET['filter_capacity_status'])) : 'all';
$course_status = isset($_GET['course_status']) ? sanitize_text_field(wp_unslash($_GET['course_status'])) : 'all';
$search_s = isset($_GET['s']) ? wp_unslash((string) $_GET['s']) : '';
?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست دوره‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-course'); ?>" class="page-title-action">افزودن دوره</a>
</div>
<?php
echo '<div class="wrap">';
echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="form_fillter_attendance form_fillter_attendance_tab1">';
echo '<input type="hidden" name="page" value="sc-courses">';
if (!empty($_GET['orderby'])) {
    echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['orderby']))) . '">';
}
if (!empty($_GET['order'])) {
    $ord = strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';
    echo '<input type="hidden" name="order" value="' . esc_attr($ord) . '">';
}

echo '<div class="sc-filter-grid">';

echo '<div class="sc-filter-field">';
echo '<label class="sc-filter-label" for="filter_chapter">شعبه</label>';
echo '<select name="filter_chapter" id="filter_chapter" class="sc-filter-control">';
echo '<option value="">همه شعبه‌ها</option>';
if (!empty($chapters_filter)) {
    foreach ($chapters_filter as $ch) {
        $nm = isset($ch->name) ? (string) $ch->name : '';
        if ($nm === '') {
            continue;
        }
        echo '<option value="' . esc_attr($nm) . '"' . selected($filter_chapter, $nm, false) . '>' . esc_html($nm) . '</option>';
    }
}
echo '</select></div>';

echo '<div class="sc-filter-field">';
echo '<label class="sc-filter-label" for="sc_courses_list_search">جستجو</label>';
echo '<input type="search" id="sc_courses_list_search" name="s" value="' . esc_attr($search_s) . '" class="sc-filter-control" placeholder="عنوان یا توضیحات دوره…">';
echo '</div>';

echo '<div class="sc-filter-field">';
echo '<label class="sc-filter-label" for="filter_course_type">نوع کلاس</label>';
echo '<select name="filter_course_type" id="filter_course_type" class="sc-filter-control">';
echo '<option value="all"' . selected($filter_course_type, 'all', false) . '>همه</option>';
echo '<option value="group"' . selected($filter_course_type, 'group', false) . '>گروهی</option>';
echo '<option value="private"' . selected($filter_course_type, 'private', false) . '>خصوصی</option>';
echo '</select></div>';

echo '<div class="sc-filter-field">';
echo '<label class="sc-filter-label" for="course_status">وضعیت کلاس</label>';
echo '<select name="course_status" id="course_status" class="sc-filter-control">';
echo '<option value="all"' . selected($course_status, 'all', false) . '>همه</option>';
echo '<option value="active"' . selected($course_status, 'active', false) . '>فعال</option>';
echo '<option value="inactive"' . selected($course_status, 'inactive', false) . '>غیرفعال</option>';
echo '<option value="trash"' . selected($course_status, 'trash', false) . '>زباله‌دان</option>';
echo '</select></div>';

echo '<div class="sc-filter-field">';
echo '<label class="sc-filter-label" for="filter_capacity_status">وضعیت ظرفیت</label>';
echo '<select name="filter_capacity_status" id="filter_capacity_status" class="sc-filter-control">';
echo '<option value="all"' . selected($filter_capacity_status, 'all', false) . '>همه</option>';
echo '<option value="available"' . selected($filter_capacity_status, 'available', false) . '>دارای ظرفیت</option>';
echo '<option value="full"' . selected($filter_capacity_status, 'full', false) . '>ظرفیت کامل</option>';
echo '</select></div>';

echo '</div>';

echo '<p class="submit">';
echo '<input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">';
echo ' <a href="' . esc_url(admin_url('admin.php?page=sc-courses')) . '" class="button delete_fillter">پاک کردن فیلترها</a>';
echo '</p>';

$courses_list_table->views();
$courses_list_table->display();
echo '</form>';
echo '</div>';
?>

<!-- Modal for Course Users -->
<div id="scCourseUsersModal" class="sc-modal" >
    <div class="sc-modal-content">
        <div class="sc-modal-header">
            <h2 class="sc-modal-title">کاربران فعال دوره</h2>
            <span class="sc-modal-close">&times;</span>
        </div>
        <div class="sc-modal-body">
            <div class="sc-modal-loading">
                <div class="sc-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>
            <div class="sc-modal-users-list" style="display: none;"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $(document).on('click', '.view-course-users', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        let courseId = $(this).data('id');
        
        if (!courseId) {
            alert('خطا: شناسه دوره پیدا نشد');
            return;
        }
        
        let $modal = $('#scCourseUsersModal');
        
        if (!$modal.length) {
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
        
        let $loading = $modal.find('.sc-modal-loading');
        let $usersList = $modal.find('.sc-modal-users-list');
        
        $loading.show();
        $usersList.hide().empty();
        
        $modal.css({
            'display': 'flex',
            'visibility': 'visible'
        }).addClass('show-modal');
        
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_course_active_users',
                course_id: courseId
            },
            success: function(res){
                $loading.hide();
                
                if(res.success && res.data){
                    let users = res.data.users || [];
                    let count = res.data.count || 0;
                    
                    if(users.length > 0){
                        let courseId = res.data.course_id || courseId;
                        let exportUrl = '<?php echo wp_nonce_url(admin_url('admin.php?page=sc-courses'), 'sc_export_excel'); ?>&sc_export=excel&export_type=course_users&course_id=' + courseId;
                        
                        let html = '<div class="sc-users-summary" style="margin-bottom: 20px; padding: 15px; background-color: #f0f6fc; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<strong>تعداد کاربران فعال: ' + count + ' نفر</strong>';
                        html += '<a href="' + exportUrl + '" class="button button-secondary button_export" >📊 خروجی Excel</a>';
                        html += '</div>';
                        html += '<div class="sc-users-table-container" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                        html += '<th style="width: 50px;">ردیف</th><th>نام</th><th>نام خانوادگی</th><th>کد ملی</th>';
                        html += '<th>شماره تماس</th><th>نام پدر</th><th>شماره تماس پدر</th><th>مربی</th><th>تاریخ ثبت‌نام</th>';
                        html += '</tr></thead><tbody>';
                        
                        $.each(users, function(index, user){
                            html += '<tr><td>' + (index + 1) + '</td>';
                            html += '<td>' + (user.first_name || '-') + '</td>';
                            html += '<td>' + (user.last_name || '-') + '</td>';
                            html += '<td>' + (user.national_id || '-') + '</td>';
                            html += '<td>' + (user.player_phone || '-') + '</td>';
                            html += '<td>' + (user.father_name || '-') + '</td>';
                            html += '<td>' + (user.father_phone || '-') + '</td>';
                            html += '<td>' + (user.coach_name || '-') + '</td>';
                            html += '<td>' + (user.enrollment_date_shamsi || user.enrollment_date || '-') + '</td></tr>';
                        });
                        
                        html += '</tbody></table></div>';
                        $usersList.html(html).fadeIn(300);
                    } else {
                        let errorMsg = res.data.message || 'هیچ کاربر فعالی در این دوره یافت نشد.';
                        $usersList.html('<p style="text-align: center; padding: 40px; color: #666;">' + errorMsg + '</p>').fadeIn(300);
                    }
                } else {
                    $usersList.html('<p style="text-align: center; padding: 40px; color: #666;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>').fadeIn(300);
                }
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                $loading.hide();
                $usersList.html('<p style="text-align: center; padding: 40px; color: #d63638;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p><p style="text-align: center; color: #666; font-size: 12px;">خطا: ' + error + ' (Status: ' + xhr.status + ')</p>').fadeIn(300);
            }
        });
    });
    
    $(document).on('click', '.sc-modal-close', function(e){
        e.preventDefault();
        e.stopPropagation();
        let $modal = $('#scCourseUsersModal');
        $modal.removeClass('show-modal');
        $modal.css({
            'display': 'none',
            'visibility': 'hidden'
        });
    });
    
    $(document).on('click', '#scCourseUsersModal', function(e){
        if($(e.target).is('#scCourseUsersModal')){
            let $modal = $(this);
            $modal.removeClass('show-modal');
            $modal.css({
                'display': 'none',
                'visibility': 'hidden'
            });
        }
    });
    
    $(document).on('click', '.sc-modal-content', function(e){
        e.stopPropagation();
    });
});
</script>



