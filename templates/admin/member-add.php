<?php 
        $first_name = '';
        $last_name = '';
        $father_name = '';
        $national_id = '';
        $player_phone = '';
        $father_phone = '';
        $mother_phone = '';
        $landline_phone = '';
        $birth_date_shamsi = '';
        $birth_date_gregorian = '';
        $personal_photo = '';
        $id_card_photo = '';
        $sport_insurance_photo = '';
        $insurance_expiry_date_shamsi = '';
        $medical_condition = '';
        $sports_history = '';
        $health_verified = 0;
        $info_verified = 0;
        $is_active = 1;
        $additional_info = '';
        
        // اگر بازیکن جدید است، تاریخ امروز را به عنوان پیش‌فرض بگذار
        if (!isset($_GET['player_id'])) {
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $today_shamsi = $today_jalali[0] . '/' . 
                           str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                           str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
            
            // فقط اگر خالی است
            if (empty($birth_date_shamsi)) {
                $birth_date_shamsi = $today_shamsi;
            }
            if (empty($insurance_expiry_date_shamsi)) {
                $insurance_expiry_date_shamsi = $today_shamsi;
            }
        }

if($player && $_GET['player_id'] ){
        $first_name              = $player->first_name ?? '';
        $last_name               = $player->last_name ?? '';
        $father_name             = $player->father_name ?? '';
        $national_id             = $player->national_id ?? '';
        $player_phone            = $player->player_phone ?? '';
        $father_phone            = $player->father_phone ?? '';
        $mother_phone            = $player->mother_phone ?? '';
        $landline_phone          = $player->landline_phone ?? '';
        $birth_date_shamsi       = $player->birth_date_shamsi ?? '';
        $birth_date_gregorian    = $player->birth_date_gregorian ?? '';
        
        // اگر تاریخ تولد خالی است، تاریخ امروز را به عنوان پیش‌فرض بگذار
        if (empty($birth_date_shamsi) && !isset($_GET['player_id'])) {
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $birth_date_shamsi = $today_jalali[0] . '/' . 
                               str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                               str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        }
        $personal_photo          = $player->personal_photo ?? '';
        $id_card_photo           = $player->id_card_photo ?? '';
        $sport_insurance_photo   = $player->sport_insurance_photo ?? '';
        $insurance_expiry_date_shamsi = $player->insurance_expiry_date_shamsi ?? '';
        $medical_condition       = $player->medical_condition ?? '';
        $sports_history          = $player->sports_history ?? '';
        $health_verified         = $player->health_verified ?? 0;
        $info_verified           = $player->info_verified ?? 0;
        $is_active               = $player->is_active ?? 1;
        $additional_info         = $player->additional_info ?? '';
    }
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo isset($_GET['player_id']) ? 'بروزرسانی اطلاعات بازیکن' : 'ثبت بازکین جدید'; ?>
            </h1>
    <?php 
        if(isset($_GET['player_id'])){
            ?>
            <a href="<?php echo admin_url('admin.php?page=sc-add-member'); ?>" class="page-title-action">افزودن بازیکن جدید</a>
            <?php 

        }
        
    ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php //wp_nonce_field('sk_add_player', 'sk_player_nonce'); ?>

        <table class="form-table">
            <tbody>

                
                <tr>
                    <th scope="row"><label for="first_name">نام</label></th>
                    <td><input name="first_name" type="text" id="first_name" value="<?php echo $first_name; ?> " class="regular-text" required></td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="last_name">نام خانوادگی</label></th>
                    <td><input name="last_name" type="text" id="last_name" value="<?php echo $last_name; ?>" class="regular-text" required></td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="father_name">نام پدر</label></th>
                    <td><input name="father_name" type="text" id="father_name" value="<?php echo $father_name; ?>" class="regular-text"></td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="national_id">کد ملی</label></th>
                    <td><input name="national_id" type="text" id="national_id" value="<?php echo $national_id; ?>" class="regular-text" required maxlength="10"></td>
                </tr>
           
                <tr>
                    <th scope="row"><label for="player_phone">شماره موبایل بازیکن</label></th>
                    <td><input name="player_phone" type="text" id="player_phone" value="<?php echo $player_phone; ?>" class="regular-text"></td>
                </tr>

              
                <tr>
                    <th scope="row"><label for="father_phone">شماره موبایل پدر</label></th>
                    <td><input name="father_phone" type="text" id="father_phone" value="<?php echo $father_phone; ?>" class="regular-text"></td>
                </tr>

             
                <tr>
                    <th scope="row"><label for="mother_phone">شماره موبایل مادر</label></th>
                    <td><input name="mother_phone" type="text" id="mother_phone" value="<?php echo $mother_phone; ?>" class="regular-text"></td>
                </tr>

             
                <tr>
                    <th scope="row"><label for="landline_phone">تلفن ثابت</label></th>
                    <td><input name="landline_phone" type="text" id="landline_phone" value="<?php echo $landline_phone; ?>" class="regular-text"></td>
                </tr>

              
                <tr>
                    <th scope="row"><label for="birth_date_shamsi">تاریخ تولد (شمسی)</label></th>
                    <td>
                        <input name="birth_date_shamsi" type="text" id="birth_date_shamsi" value="<?php echo esc_attr($birth_date_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلاً 1400/02/15" readonly>
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="birth_date_gregorian">تاریخ تولد (میلادی)</label></th>
                    <td><input name="birth_date_gregorian" type="date" id="birth_date_gregorian" value="<?php echo $birth_date_gregorian; ?>"></td>
                </tr>

              
                <tr>
                    <th scope="row"><label for="personal_photo">عکس پرسنلی</label></th>
                    <td>
                        <?php
                            
                            ?>
                            <input type="text" name="personal_photo" id="personal_photo_txt" class="regular-text" value="<?php echo $personal_photo; ?>" placeholder="آدرس تصویر یا آپلود کنید">
                            <button type="button" class="button-secondary sc-upload-btn" id="btn_personal_photo">انتخاب تصویر</button>

                            <?php if (!empty($personal_photo)) : ?>
                                <div style="margin-top:10px;">
                                    <img src="<?php   echo esc_url($personal_photo); ?>" alt="" style="max-width:300px;border:1px solid #ccc;border-radius:6px;">
                                </div>
                            <?php endif; ?>

                            
                    </td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="id_card_photo">عکس کارت ملی</label></th>
                    <td>
                            <input type="text" name="id_card_photo" id="id_card_photo_txt" value="<?php  echo esc_attr($id_card_photo);?>" class="regular-text" placeholder="آدرس تصویر یا آپلود کنید" />
                            <input type="button" class="button-secondary sc-upload-btn" id="btn_id_card_photo" value="انتخاب تصویر">
                            <?php if (!empty($id_card_photo)) : ?>
                                <div style="margin-top:10px;">
                                    <img src="<?php   echo esc_url($id_card_photo); ?>" alt="" style="max-width:300px;border:1px solid #ccc;border-radius:6px;">
                                </div>
                            <?php endif; ?>
                    </td>
                </tr>

              
                <tr>
                    <th scope="row"><label for="sport_insurance_photo">عکس بیمه ورزشی</label></th>
                    <td>
                        <input type="text" name="sport_insurance_photo" id="sport_insurance_photo_txt" value="<?php  echo esc_attr($sport_insurance_photo);?>" class="regular-text" placeholder="آدرس تصویر یا آپلود کنید" />
                            <input type="button" class="button-secondary sc-upload-btn" id="btn_sport_insurance_photo" value="انتخاب تصویر">
                            <?php if (!empty($sport_insurance_photo)) : ?>
                                <div style="margin-top:10px;">
                                    <img src="<?php   echo esc_url($sport_insurance_photo); ?>" alt="" style="max-width:300px;border:1px solid #ccc;border-radius:6px;">
                                </div>
                            <?php endif; ?>
                    </td>
                </tr>

              
                <tr>
                    <th scope="row"><label for="insurance_expiry_date_shamsi">تاریخ انقضا بیمه (شمسی)</label></th>
                    <td>
                        <input name="insurance_expiry_date_shamsi" type="text" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلاً 1403/12/29" readonly>
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>

             
                <tr>
                    <th scope="row"><label for="medical_condition">مشکلات پزشکی</label></th>
                    <td><textarea name="medical_condition" id="medical_condition" rows="4" class="large-text"><?php echo $medical_condition; ?></textarea></td>
                </tr>

               
                <tr>
                    <th scope="row"><label for="sports_history">سوابق ورزشی</label></th>
                    <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo $sports_history; ?></textarea></td>
                </tr>

               
                <tr>
                    <th scope="row">وضعیت سلامت تأیید شده</th>
                    <td><label><input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1"> بله</label></td>
                </tr>

              
                <tr>
                    <th scope="row">اطلاعات تأیید شده</th>
                    <td><label><input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1"> بله</label></td>
                </tr>

               
                <tr>
                    <th scope="row">فعال</th>
                    <td><label class="switch" ><input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1"><span class="slider round"></span> بله</label></td>
                </tr>

                <tr>
                    <th scope="row"><label for="additional_info">توضیحات اضافی</label></th>
                    <td><textarea name="additional_info" id="additional_info" rows="3" class="large-text"><?php echo $additional_info; ?></textarea></td>
                </tr>

            </tbody>
        </table>

        <!-- بخش آکاردئونی دوره‌ها -->
        <div class="sc-courses-accordion" style="margin-top: 20px;">
            <div class="sc-accordion-header" style="background: #f0f0f1; padding: 15px; border: 1px solid #ddd; cursor: pointer; border-radius: 4px 4px 0 0;" onclick="toggleCoursesAccordion()">
                <h3 style="margin: 0; display: inline-block;">دوره‌های بازیکن</h3>
                <span id="courses-accordion-icon" style="float: right; font-size: 20px;">▼</span>
            </div>
            <div id="sc-courses-content" style="display: none; border: 1px solid #ddd; border-top: none; padding: 20px; background: #fff; border-radius: 0 0 4px 4px;">
                <?php
                global $wpdb;
                $courses_table = $wpdb->prefix . 'sc_courses';
                $member_courses_table = $wpdb->prefix . 'sc_member_courses';
                
                // دریافت دوره‌های فعال
                $courses = $wpdb->get_results(
                    "SELECT * FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
                );
                
                // دریافت دوره‌های فعلی بازیکن با وضعیت آن‌ها
                $player_courses_active = [];
                $player_courses_flags = [];
                if ($player && isset($_GET['player_id'])) {
                    $player_id = absint($_GET['player_id']);
                    $player_courses_data = $wpdb->get_results($wpdb->prepare(
                        "SELECT course_id, status, course_status_flags FROM $member_courses_table WHERE member_id = %d",
                        $player_id
                    ), ARRAY_A);
                    if ($player_courses_data) {
                        foreach ($player_courses_data as $pc) {
                            if ($pc['status'] === 'active') {
                                $player_courses_active[$pc['course_id']] = true;
                            }
                            // پردازش course_status_flags (مثلاً "paused,completed" یا JSON)
                            $flags = [];
                            if (!empty($pc['course_status_flags'])) {
                                $flags = explode(',', $pc['course_status_flags']);
                                $flags = array_map('trim', $flags);
                            }
                            $player_courses_flags[$pc['course_id']] = $flags;
                        }
                    }
                }
                
                if (empty($courses)) {
                    echo '<div style="padding: 20px; text-align: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
                    echo '<p style="margin: 0 0 10px 0; color: #856404;">هنوز دوره‌ای ثبت نشده است.</p>';
                    echo '<a href="' . admin_url('admin.php?page=sc-add-course') . '" target="_blank" class="button button-primary">افزودن دوره جدید</a>';
                    echo '</div>';
                } else {
                    echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">';
                    foreach ($courses as $course) {
                        $is_active = isset($player_courses_active[$course->id]);
                        $current_flags = isset($player_courses_flags[$course->id]) ? $player_courses_flags[$course->id] : [];
                        $is_paused = in_array('paused', $current_flags);
                        $is_completed = in_array('completed', $current_flags);
                        $is_canceled = in_array('canceled', $current_flags);
                        
                        $enrolled = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $member_courses_table WHERE course_id = %d AND status = 'active'",
                            $course->id
                        ));
                        $capacity_text = $course->capacity ? "($enrolled/{$course->capacity})" : "(نامحدود)";
                        $capacity_warning = ($course->capacity && $enrolled >= $course->capacity) ? ' style="color: #d63638; font-weight: bold;"' : '';
                        
                        echo '<div style="padding: 15px; margin-bottom: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '<div style="display: flex; align-items: flex-start; gap: 15px;">';
                        
                        // Checkbox برای فعال/غیرفعال
                        echo '<div style="flex-shrink: 0; margin-top: 5px;">';
                        echo '<input type="checkbox" name="courses[]" value="' . esc_attr($course->id) . '" id="course_cb_' . esc_attr($course->id) . '" ' . ($is_active ? 'checked' : '') . '>';
                        echo '</div>';
                        
                        // اطلاعات دوره
                        echo '<div style="flex: 1;">';
                        echo '<label for="course_cb_' . esc_attr($course->id) . '" style="cursor: pointer; display: block; margin-bottom: 10px;">';
                        echo '<strong>' . esc_html($course->title) . '</strong>';
                        $formatted_price = function_exists('wc_price') 
                            ? wc_price($course->price) 
                            : number_format($course->price, 0, '.', ',') . ' تومان';
                        echo '<span style="color: #666; margin: 0 10px;">- ' . $formatted_price . '</span>';
                        echo '<span' . $capacity_warning . '>' . $capacity_text . '</span>';
                        if ($course->description) {
                            echo '<p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">' . esc_html(wp_trim_words($course->description, 20)) . '</p>';
                        }
                        echo '</label>';
                        
                        // Checkbox های وضعیت‌های اضافی
                        echo '<div id="course_status_' . esc_attr($course->id) . '" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; ' . ($is_active ? '' : 'display: none;') . '">';
                        echo '<label style="font-size: 12px; color: #666; display: block; margin-bottom: 8px;">وضعیت‌های اضافی:</label>';
                        echo '<div style="display: flex; gap: 15px; flex-wrap: wrap;">';
                        
                        // Checkbox برای paused
                        echo '<label style="display: flex; align-items: center; cursor: pointer; font-size: 13px;">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][paused]" value="1" ' . ($is_paused ? 'checked' : '') . ' ' . ($is_active ? '' : 'disabled') . ' style="margin-left: 5px;">';
                        echo '<span>متوقف شده</span>';
                        echo '</label>';
                        
                        // Checkbox برای completed
                        echo '<label style="display: flex; align-items: center; cursor: pointer; font-size: 13px;">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][completed]" value="1" ' . ($is_completed ? 'checked' : '') . ' ' . ($is_active ? '' : 'disabled') . ' style="margin-left: 5px;">';
                        echo '<span>تمام شده</span>';
                        echo '</label>';
                        
                        // Checkbox برای canceled
                        echo '<label style="display: flex; align-items: center; cursor: pointer; font-size: 13px;">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][canceled]" value="1" ' . ($is_canceled ? 'checked' : '') . ' ' . ($is_active ? '' : 'disabled') . ' style="margin-left: 5px;">';
                        echo '<span>لغو شده</span>';
                        echo '</label>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '<p class="description" style="margin-top: 10px;">بازیکن می‌تواند در چند دوره شرکت کند. تیک اول دوره را فعال/غیرفعال می‌کند و تیک‌های دیگر وضعیت‌های اضافی هستند.</p>';
                    
                    // JavaScript برای فعال/غیرفعال کردن checkbox های وضعیت
                 
                }
                ?>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="submit_player" class="button button-primary">
                <?php echo isset($_GET['player_id']) ? 'بروزرسانی اطلاعات بازیکن' : 'ثبت بازکین جدید'; ?>
            </button>
        </p>

    </form>
</div>
