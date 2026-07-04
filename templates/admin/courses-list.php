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

$active_filters_count = 0;
if ($filter_chapter !== '') {
    $active_filters_count++;
}
if ($filter_course_type !== 'all' && $filter_course_type !== '') {
    $active_filters_count++;
}
if ($filter_capacity_status !== 'all' && $filter_capacity_status !== '') {
    $active_filters_count++;
}
if ($search_s !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$form_action = admin_url('admin.php');
?>
<div class="wrap sc-courses-list-wrap">
    <div class="sc-courses-list-header">
        <div class="sc-courses-list-header-text">
            <h1 class="sc-courses-list-title">لیست دوره‌ها</h1>
            <p class="sc-courses-list-desc">برای مشاهده اکشن‌ها روی عنوان دوره بروید (ویرایش، کاربران، حذف).</p>
        </div>
        <div class="sc-courses-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-course')); ?>" class="page-title-action sc-courses-list-add-btn">افزودن دوره</a>
        </div>
    </div>

    <div class="sc-courses-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-courses-list-filters-toolbar">
            <button type="button"
                    class="sc-courses-list-filters-toggle"
                    id="sc-courses-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-courses-filters-panel">
                <span class="sc-courses-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-courses-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-courses-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-courses-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-courses')); ?>" class="sc-courses-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="<?php echo esc_url($form_action); ?>" class="sc-courses-list-filters-panel" id="sc-courses-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-courses">
            <?php if (!empty($_GET['orderby'])) : ?>
                <input type="hidden" name="orderby" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['orderby']))); ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['order'])) :
                $ord = strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';
                ?>
                <input type="hidden" name="order" value="<?php echo esc_attr($ord); ?>">
            <?php endif; ?>

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_chapter">شعبه</label>
                    <select name="filter_chapter" id="filter_chapter" class="sc-filter-control">
                        <option value="">همه شعبه‌ها</option>
                        <?php if (!empty($chapters_filter)) : foreach ($chapters_filter as $ch) :
                            $nm = isset($ch->name) ? (string) $ch->name : '';
                            if ($nm === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($nm); ?>" <?php selected($filter_chapter, $nm); ?>><?php echo esc_html($nm); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="sc_courses_list_search">جستجو</label>
                    <input type="search" id="sc_courses_list_search" name="s" value="<?php echo esc_attr($search_s); ?>" class="sc-filter-control" placeholder="عنوان یا توضیحات دوره…">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course_type">نوع کلاس</label>
                    <select name="filter_course_type" id="filter_course_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_course_type, 'all'); ?>>همه</option>
                        <option value="group" <?php selected($filter_course_type, 'group'); ?>>گروهی</option>
                        <option value="private" <?php selected($filter_course_type, 'private'); ?>>خصوصی</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="course_status">وضعیت کلاس</label>
                    <select name="course_status" id="course_status" class="sc-filter-control">
                        <option value="all" <?php selected($course_status, 'all'); ?>>همه</option>
                        <option value="active" <?php selected($course_status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($course_status, 'inactive'); ?>>غیرفعال</option>
                        <option value="trash" <?php selected($course_status, 'trash'); ?>>زباله‌دان</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_capacity_status">وضعیت ظرفیت</label>
                    <select name="filter_capacity_status" id="filter_capacity_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_capacity_status, 'all'); ?>>همه</option>
                        <option value="available" <?php selected($filter_capacity_status, 'available'); ?>>دارای ظرفیت</option>
                        <option value="full" <?php selected($filter_capacity_status, 'full'); ?>>ظرفیت کامل</option>
                    </select>
                </div>
            </div>

            <div class="sc-courses-list-filters-actions">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-courses')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-courses-list-table-card">
        <form method="get" action="<?php echo esc_url($form_action); ?>">
            <input type="hidden" name="page" value="sc-courses">
            <?php
            if ($filter_chapter !== '') {
                echo '<input type="hidden" name="filter_chapter" value="' . esc_attr($filter_chapter) . '">';
            }
            if ($filter_course_type !== 'all' && $filter_course_type !== '') {
                echo '<input type="hidden" name="filter_course_type" value="' . esc_attr($filter_course_type) . '">';
            }
            if ($filter_capacity_status !== 'all' && $filter_capacity_status !== '') {
                echo '<input type="hidden" name="filter_capacity_status" value="' . esc_attr($filter_capacity_status) . '">';
            }
            if ($course_status !== 'all' && $course_status !== '') {
                echo '<input type="hidden" name="course_status" value="' . esc_attr($course_status) . '">';
            }
            if ($search_s !== '') {
                echo '<input type="hidden" name="s" value="' . esc_attr($search_s) . '">';
            }
            if (!empty($_GET['orderby'])) {
                echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['orderby']))) . '">';
            }
            if (!empty($_GET['order'])) {
                $ord = strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';
                echo '<input type="hidden" name="order" value="' . esc_attr($ord) . '">';
            }
            $courses_list_table->views();
            $courses_list_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-courses-filters-toggle');
    var $panel = $('#sc-courses-filters-panel');
    var $card = $toggle.closest('.sc-courses-list-filters-card');
    var $label = $toggle.find('.sc-courses-list-filters-toggle-label');

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
});
</script>

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
