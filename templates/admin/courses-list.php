<?php
global $courses_list_table;
?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست دوره‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-course'); ?>" class="page-title-action">افزودن دوره</a>
</div>
<?php
echo '<div class="wrap">';
    echo '<form Method="get">';
        echo '<input type="hidden" name="page" value="sc-courses">';
        $courses_list_table->search_box('جستجو دوره', 'search_course');
        $courses_list_table->views();
        $courses_list_table->display();
    echo '</form>';
echo '</div>';
?>

<!-- Modal for Course Users -->
<div id="scCourseUsersModal" class="sc-modal" style="display: none !important; visibility: hidden !important;">
    <div class="sc-modal-content">
        <div class="sc-modal-header">
            <h2 class="sc-modal-title">کاربران فعال دوره</h2>
            <span class="sc-modal-close">&times;</span>
        </div>
        <div class="sc-modal-body">
            <div class="sc-modal-loading" style="text-align: center; padding: 40px;">
                <div class="sc-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>
            <div class="sc-modal-users-list" style="display: none;"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('SC Admin JS loaded inline');
    
    $(document).on('click', '.view-course-users', function(e){
        e.preventDefault();
        e.stopPropagation();
        console.log('View course users clicked');
        
        let courseId = $(this).data('id');
        console.log('Course ID:', courseId);
        
        if (!courseId) {
            console.error('Course ID not found');
            alert('خطا: شناسه دوره پیدا نشد');
            return;
        }
        
        let $modal = $('#scCourseUsersModal');
        console.log('Modal found:', $modal.length);
        
        if (!$modal.length) {
            console.error('Modal not found');
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
        console.log('AJAX URL:', ajaxUrl);
        console.log('Sending AJAX request...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_course_active_users',
                course_id: courseId
            },
            success: function(res){
                console.log('AJAX Success Response:', res);
                $loading.hide();
                
                if(res.success && res.data){
                    let users = res.data.users || [];
                    let count = res.data.count || 0;
                    
                    if(users.length > 0){
                        let courseId = res.data.course_id || courseId;
                        let exportUrl = '<?php echo wp_nonce_url(admin_url('admin.php?page=sc-courses'), 'sc_export_excel'); ?>&sc_export=excel&export_type=course_users&course_id=' + courseId;
                        
                        let html = '<div class="sc-users-summary" style="margin-bottom: 20px; padding: 15px; background-color: #f0f6fc; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<strong>تعداد کاربران فعال: ' + count + ' نفر</strong>';
                        html += '<a href="' + exportUrl + '" class="button button-secondary" style="background-color: #00a32a; border-color: #00a32a; color: #fff; text-decoration: none;">📊 خروجی Excel</a>';
                        html += '</div>';
                        html += '<div class="sc-users-table-container" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                        html += '<th style="width: 50px;">ردیف</th><th>نام</th><th>نام خانوادگی</th><th>کد ملی</th>';
                        html += '<th>شماره تماس</th><th>نام پدر</th><th>شماره تماس پدر</th><th>تاریخ ثبت‌نام</th>';
                        html += '</tr></thead><tbody>';
                        
                        $.each(users, function(index, user){
                            html += '<tr><td>' + (index + 1) + '</td>';
                            html += '<td>' + (user.first_name || '-') + '</td>';
                            html += '<td>' + (user.last_name || '-') + '</td>';
                            html += '<td>' + (user.national_id || '-') + '</td>';
                            html += '<td>' + (user.player_phone || '-') + '</td>';
                            html += '<td>' + (user.father_name || '-') + '</td>';
                            html += '<td>' + (user.father_phone || '-') + '</td>';
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



