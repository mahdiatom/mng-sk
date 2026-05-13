<?php

if ( ! defined('ABSPATH') ) exit;
//این فایل پاپ اپ برای اطلاعات بازیکن است که در صفحه لیست اعضا در اکشن می
global $title ,$player_list_table;
 $playerListTable = new Player_List_Table();
    $playerListTable->prepare_items();

            ?>
            <div class="wrap">
            <h1 class="wp-heading-inline">لیست بازیکن ها</h1>
            <a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">افزودن بازیکن</a>
        </div>

        <div class="notice_custom_list_member">
            <p>برای مشاهده اکشن‌ها روی نام کاربر بروید (حذف، مشاهده، ویرایش).</p>
        </div>

        <?php
        // بارگذاری داده‌ها برای فیلتر searchable
        global $wpdb;
        $members_table = $wpdb->prefix . 'sc_members';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $all_players = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
        $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");

        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_profile = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
        $filter_member_type = isset($_GET['filter_member_type']) ? sanitize_text_field($_GET['filter_member_type']) : 'all';
        ?>

        <!-- فرم فیلتر جدید -->
        <form method="get" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
            <input type="hidden" name="page" value="sc-members">

            <div class="sc-filter-grid">

                <!-- جستجوی کاربر (searchable) -->
                <div class="sc-filter-field">
                    <label class="sc-filter-label">جستجوی بازیکن</label>
                    <div class="sc-searchable-dropdown">
                        <?php
                        $selected_player_text = 'همه بازیکنان';
                        $filter_player = isset($_GET['filter_player']) ? absint($_GET['filter_player']) : 0;
                        if ($filter_player > 0) {
                            foreach ($all_players as $p) {
                                if ($p->id == $filter_player) {
                                    $selected_player_text = $p->first_name . ' ' . $p->last_name . ' - ' . $p->national_id;
                                    break;
                                }
                            }
                        }
                        ?>
                        <input type="hidden" name="filter_player" id="filter_player" value="<?php echo esc_attr($filter_player); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_player) echo 'style="display:none"'; ?>>همه بازیکنان</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_player) echo 'style="display:none"'; ?>><?php echo esc_html($selected_player_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه بازیکنان" onclick="scSelectMemberFilter(this,'0','همه بازیکنان')">همه بازیکنان</div>
                                <?php
                                $display_count = 0;
                                $max_display = 15;
                                foreach ($all_players as $player) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($player->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($player->first_name . ' ' . $player->last_name . ' ' . $player->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($player->id); ?>','<?php echo esc_js($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>')">
                                        <?php echo esc_html($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- دوره -->
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

                <!-- وضعیت -->
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="active" <?php selected($filter_status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($filter_status, 'inactive'); ?>>غیرفعال</option>
                    </select>
                </div>

                <!-- تکمیل پروفایل -->
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_profile">تکمیل پروفایل</label>
                    <select name="filter_profile" id="filter_profile" class="sc-filter-control">
                        <option value="all" <?php selected($filter_profile, 'all'); ?>>همه</option>
                        <option value="completed" <?php selected($filter_profile, 'completed'); ?>>تکمیل شده</option>
                        <option value="incomplete" <?php selected($filter_profile, 'incomplete'); ?>>ناقص</option>
                    </select>
                </div>

                <!-- نوع بازیکن -->
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_member_type">نوع بازیکن</label>
                    <select name="filter_member_type" id="filter_member_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_member_type, 'all'); ?>>همه انواع</option>
                        <option value="normal" <?php selected($filter_member_type, 'normal'); ?>>عادی</option>
                        <option value="team" <?php selected($filter_member_type, 'team'); ?>>تیم</option>
                    </select>
                </div>

            </div>

            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo admin_url('admin.php?page=sc-members'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                <?php
                $export_url = admin_url('admin.php?page=sc-members&sc_export=excel&export_type=members');
                if ($filter_player > 0) $export_url = add_query_arg('filter_player', $filter_player, $export_url);
                if ($filter_course > 0) $export_url = add_query_arg('filter_course', $filter_course, $export_url);
                if ($filter_status !== 'all') $export_url = add_query_arg('filter_status', $filter_status, $export_url);
                if ($filter_profile !== 'all') $export_url = add_query_arg('filter_profile', $filter_profile, $export_url);
                if ($filter_member_type !== 'all') $export_url = add_query_arg('filter_member_type', $filter_member_type, $export_url);
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button_export">📊 خروجی Excel</a>
            </p>
        </form>

        <?php
        echo '<div class="wrap">';
            echo '<form method="get">';
                echo '<input type="hidden" name="page" value="sc-members">';
                // حذف search_box قدیمی چون حالا داخل فیلتر داریم
                $player_list_table->views();
                $player_list_table->display();
            echo '</form>';
        echo '</div>';
        ?>

        <!-- اسکریپت برای searchable dropdown (اگر قبلاً لود نشده) -->
        <script>
        // تابع scSelectMemberFilter باید از قبل در admin.js یا مشابه لود شده باشد
        // در صورت نیاز می‌توانید آن را اینجا هم تعریف کنید.
        </script>




?>

<!-- The Modal -->
<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <p class="sk-modal-content hide_before_data"></p>
    <p class="sk-modal-content_courses hide_before_data_courses"></p>
    <div class="sc-modal-body">
            <div class="sc-modal-loading" style="text-align: center; padding: 40px;">
                <div class="sc-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>

  
  </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // ---------- نمایش پاپ آپ اطلاعات بازیکن ----------
    $(document).on('click', '.view-player', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        let playerId = $(this).data('id');
        
        if (!playerId) {
            alert('خطا: شناسه بازیکن پیدا نشد');
            return;
        }
        
        let $modal = $('#myModal');
        
        if (!$modal.length) {
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
        let $loading = $modal.find('.sc-modal-loading');
        let $CourseList = $modal.find('.sc-modal-users-list');
        
        $loading.show();
        $CourseList.hide().empty();
        
        $modal.css({
            'display': 'flex',
            'visibility': 'visible'
        }).addClass('show-modal');


        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $.ajax({
            url: ajaxUrl,
            type: 'post',
            data: {
                action: 'get_player_details',
                id: playerId
            },
            success: function(res){
                $loading.hide();
                
                if(res.success){
                    let p = res.data;
                     $(".hide_before_data").removeClass('hide_before_data');
                    $(".sk-modal-content").html(
                        '<p><strong>شناسه:</strong> ' + p.id + '</p>' +
                        '<p><strong>نام:</strong> ' + (p.first_name || '-') + '</p>' +
                        '<p><strong>نام خانوادگی:</strong> ' + (p.last_name || '-') + '</p>' +
                        '<p><strong>نام پدر:</strong> ' + (p.father_name || '-') + '</p>' +
                        '<p><strong>کد ملی:</strong> ' + (p.national_id || '-') + '</p>' +
                        '<p><strong>موبایل بازیکن:</strong> ' + (p.player_phone || '-') + '</p>' +
                        '<p><strong>موبایل پدر:</strong> ' + (p.father_phone || '-') + '</p>' +
                        '<p><strong>موبایل مادر:</strong> ' + (p.mother_phone || '-') + '</p>' +
                        '<p><strong>تلفن ثابت:</strong> ' + (p.landline_phone || '-') + '</p>' +
                        '<p><strong>تاریخ تولد (شمسی):</strong> ' + (p.birth_date_shamsi || '-') + '</p>' +
                        '<p><strong>تاریخ تولد (میلادی):</strong> ' + (p.birth_date_gregorian || '-') + '</p>' +
                        '<p><strong>وضعیت پزشکی:</strong> ' + (p.medical_condition || '-') + '</p>' +
                        '<p><strong>سوابق ورزشی:</strong> ' + (p.sports_history || '-') + '</p>' +
                        '<p><strong>تأیید سلامت:</strong> ' + (p.health_verified ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>تأیید اطلاعات:</strong> ' + (p.info_verified ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>فعال:</strong> ' + (p.is_active ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>اطلاعات اضافی:</strong> ' + (p.additional_info || '-') + '</p>' +
                        '<p><strong>تاریخ ایجاد:</strong> ' + (p.created_at || '-') + '</p>' +
                        '<p><strong>تاریخ بروزرسانی:</strong> ' + (p.updated_at || '-') + '</p>' +
                        (p.personal_photo ? '<p class="p_img"><strong>عکس شخصی:</strong></p><img class="photo" src="' + p.personal_photo + '">' : '') +
                        (p.id_card_photo ? '<p class="p_img"><strong>عکس کارت ملی:</strong></p><img class="photo" src="' + p.id_card_photo + '">' : '') +
                        (p.sport_insurance_photo ? '<p class="p_img"><strong>عکس بیمه ورزشی:</strong></p><img class="photo" src="' + p.sport_insurance_photo + '">' : '')
                    );
                    $('#myModal').fadeIn();
                    
                } else {
                    alert('خطا در دریافت اطلاعات بازیکن');
                }
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                $loading.hide();
                alert('خطا در دریافت اطلاعات بازیکن. لطفاً دوباره تلاش کنید.');
            }
        });
    });

    // ---------- بستن مدال ----------
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $(".sk-modal-content ").addClass('hide_before_data');
        $('#myModal').fadeOut();
        
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $(".sk-modal-content").addClass('hide_before_data');
            $('#myModal').fadeOut();
            
        }
    });
});


//برای دوره های بازیکن 

jQuery(document).ready(function($) {
    console.log('Player modal JS loaded');
    
    // ---------- نمایش پاپ آپ اطلاعات بازیکن ----------
    $(document).on('click', '.view-player', function(e){
        e.preventDefault();
        e.stopPropagation();
        console.log('View player clicked');
        
        let playerId = $(this).data('id');
        console.log('Player ID:', playerId);
        
        if (!playerId) {
            console.error('Player ID not found');
            alert('خطا: شناسه بازیکن پیدا نشد');
            return;
        }
        
        let $modal = $('#myModal');
        console.log('Modal found:', $modal.length);
        
        if (!$modal.length) {
            console.error('Modal not found');
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
       
      

        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        console.log('AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'post',
            data: {
                action: 'get_player_details_courses',
                id: playerId
            },
            success: function(res){
                console.log('AJAX Success Response:', res);
            
                
               if(res.success && res.data){
                    let courses = res.data || [];
                    let $modal = $('#myModal');
                    let $CourseList = $modal.find('.sk-modal-content_courses');
                    if(courses.length > 0){
                         $(".sk-modal-content_courses").removeClass('hide_before_data_courses');
                        let p = res.data;
                    let html = '<div class="CourseList" >';    
                    
                    
                    html +=  '<div class="sc-users-summary" style="margin-bottom: 20px; padding: 15px; background-color: #f0f6fc; border-radius: 4px;"><h4>دوره های فعال بازیکن : </h4>' ;     
                    $.each(p, function(index=0, user){
                          html +=  '<p>' + p[index].title +'</p>' ;     
                        
                        
         
                         });

                         html += '</tbody></table></div>';
                         $CourseList.html(html).fadeIn(300);
                    } else {
                        let errorMsg = res.data.message || 'هیچ کاربر فعالی در این دوره یافت نشد.';
                        $CourseList.html('<p style="text-align: center; padding: 40px; color: #666;">' + errorMsg + '</p>').fadeIn(300);
                    }
                } else {
                    $CourseList.html('<p style="text-align: center; padding: 40px; color: #666;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>').fadeIn(300);
                }
            },
            error: function(xhr, status, error){
                alert('خطا در دریافت اطلاعات بازیکن. لطفاً دوباره تلاش کنید.');
            }
        });
    });

    // ---------- بستن مدال ----------
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $(".sk-modal-content_courses").addClass('hide_before_data_courses');
        $('#myModal').fadeOut();
        
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $(".sk-modal-content_courses").addClass('hide_before_data_courses');
            $('#myModal').fadeOut();
            
        }
    });
    
});


</script>

    <?php

