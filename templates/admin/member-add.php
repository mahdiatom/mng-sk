<?php 
if ( ! defined('ABSPATH') ) exit;
if(!isset($_GET['player_id'])){
?>
    <h1>افزودن بازیکن جدید</h1>
    <p>لطفا برای افزودن بازیکن جدید به بخش کاربران بروید</p>
    <a class="sc_button" href="<?php echo admin_url('user-new.php'); ?>">بخش کاربران</a>
<?php
    exit;
}

        $first_name = '';
        $last_name = '';
        $father_name = '';
        $national_id = '';
        $player_phone = '';
        $father_phone = '';
        $mother_phone = '';
        $landline_phone = '';
        $province = '';
        $city = '';
        $gender = '';
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
        $identity_verified = 0;
        $is_active = 1;
        $disable_auto_invoice = 0;
        $member_type = 'normal';
        $additional_info = '';
        $team_player = '';
        $skill_level = '';
if (!isset($player)) {
    $player = false;
}

if ($player && !empty($_GET['player_id'])) {
        $first_name              = $player->first_name ?? '';
        $last_name               = $player->last_name ?? '';
        $father_name             = $player->father_name ?? '';
        $national_id             = $player->national_id ?? '';
        $player_phone            = $player->player_phone ?? '';
        $father_phone            = $player->father_phone ?? '';
        $mother_phone            = $player->mother_phone ?? '';
        $landline_phone          = $player->landline_phone ?? '';
        $province                = $player->province ?? '';
        $city                    = $player->city ?? '';
        $gender                  = $player->gender ?? '';
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
        $identity_verified       = $player->identity_verified ?? 0;
        $is_active               = $player->is_active ?? 1;
        $disable_auto_invoice               = $player->disable_auto_invoice ?? 1;
        $member_type             = isset($player->member_type) && $player->member_type === 'team' ? 'team' : 'normal';
        $additional_info         = $player->additional_info ?? '';
        $team_player             = $player->team_player ?? '';
        $skill_level             = $player->skill_level ?? '';

        function sc_today_shamsi() {
            $today = getdate(time());
            $jalali = gregorian_to_jalali(
                $today['year'],
                $today['mon'],
                $today['mday']
            );

            return $jalali[0] . '/' .
                str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
                str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
        }

        if ($player && !empty($_GET['player_id'])) {
            $birth_date_shamsi = $player->birth_date_shamsi ?? '';
            $insurance_expiry_date_shamsi = $player->insurance_expiry_date_shamsi ?? '';
        }
        // === ست کردن تاریخ امروز مثل start_date رویداد ===

        if (empty($birth_date_shamsi)) {
            $birth_date_shamsi = sc_today_shamsi();
        }

        if (empty($insurance_expiry_date_shamsi)) {
            $insurance_expiry_date_shamsi = sc_today_shamsi();
        }

    }
$player_field_rules = function_exists('sc_get_player_info_field_rules') ? sc_get_player_info_field_rules() : [];
$player_custom_fields = function_exists('sc_get_player_info_custom_fields') ? sc_get_player_info_custom_fields() : [];
$member_extra_fields = ($player && !empty($player->member_extra_fields))
    ? json_decode((string) $player->member_extra_fields, true)
    : [];
if (!is_array($member_extra_fields)) {
    $member_extra_fields = [];
}
$sc_status = isset($_GET['sc_status']) ? sanitize_text_field($_GET['sc_status']) : '';
?>
<div class="wrap">
    <?php // نوتیف موفقیت/خطا فقط از sc_sprot_notices (admin_notices) نمایش داده می‌شود تا تکراری نباشد. ?>
    <h1 class="wp-heading-inline">
        <?php echo isset($_GET['player_id']) ? 'بروزرسانی اطلاعات بازیکن' : 'ثبت بازکین جدید'; ?>
            </h1>
    <?php
        if (isset($_GET['player_id'])) {
            ?><a href="<?php echo admin_url('user-new.php'); ?>" class="page-title-action">افزودن بازیکن جدید</a><?php
        }
    ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php //wp_nonce_field('sk_add_player', 'sk_player_nonce'); ?>

        <table class="form-table sc_form-table">
            <tbody>

                
                <?php if (sc_player_info_is_field_visible('first_name', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="first_name">نام</label></th>
                    <td><input name="first_name" type="text" id="first_name" value="<?php echo $first_name; ?> " class="regular-text"<?php echo sc_player_info_field_required_attr('first_name', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>

               
                <?php if (sc_player_info_is_field_visible('last_name', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="last_name">نام خانوادگی</label></th>
                    <td><input name="last_name" type="text" id="last_name" value="<?php echo $last_name; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('last_name', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>

               
                <?php if (sc_player_info_is_field_visible('father_name', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="father_name">نام پدر</label></th>
                    <td><input name="father_name" type="text" id="father_name" value="<?php echo $father_name; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('father_name', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>

               
                <tr>
                    <th scope="row"><label for="national_id">کد ملی</label></th>
                    <td><input name="national_id" type="text" id="national_id" value="<?php echo $national_id; ?>" class="regular-text" required maxlength="10"></td>
                </tr>
                
                <?php
                // دریافت user_id اگر وجود دارد
                $member_user_id = ($player && isset($player->user_id)) ? (int) $player->user_id : null;
                $member_username = '';
                if ($member_user_id) {
                    $member_user = get_userdata($member_user_id);
                    if ($member_user) {
                        $member_username = $member_user->user_login;
                    }
                }
                ?>
                
                <tr>
                    <th scope="row"><label for="username">نام کاربری</label></th>
                    <td>
                        <input name="username" type="text" id="username" value="<?php echo esc_attr($member_username); ?>" class="regular-text" placeholder="برای ایجاد کاربر WordPress" readonly>
                        <p class="description">نام کاربری به صورت پیش فرض  شماره تماس کاربر می باشد - غیرقابل تغییر </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="password">رمز عبور</label></th>
                    <td>
                        <input name="password" type="text" id="password" value="" class="regular-text" placeholder="رمز عبور جدید را وارد کنید.">
                        <p class="description"><?php echo $member_user_id ? 'برای تغییر رمز عبور، رمز جدید را وارد کنید (در صورت خالی بودن، تغییر نمی‌کند)' : 'رمز عبور برای ورود به سایت'; ?></p>
                    </td>
                </tr>
           
                <?php if (sc_player_info_is_field_visible('player_phone', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="player_phone">شماره موبایل بازیکن</label></th>
                    <td><input name="player_phone" type="text" id="player_phone" value="<?php echo $player_phone; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('player_phone', $player_field_rules); ?>>
                    <p class="description">فقط برای شماره بازیکن پیامک ارسال خواهد شد.</p>
          
                </td>
                           </tr>
                <?php endif; ?>

              
                <?php if (sc_player_info_is_field_visible('father_phone', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="father_phone">شماره موبایل پدر</label></th>
                    <td><input name="father_phone" type="text" id="father_phone" value="<?php echo $father_phone; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('father_phone', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>

             
                <?php if (sc_player_info_is_field_visible('mother_phone', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="mother_phone">شماره موبایل مادر</label></th>
                    <td><input name="mother_phone" type="text" id="mother_phone" value="<?php echo $mother_phone; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('mother_phone', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>

             
                <?php if (sc_player_info_is_field_visible('landline_phone', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="landline_phone">تلفن ثابت</label></th>
                    <td><input name="landline_phone" type="text" id="landline_phone" value="<?php echo $landline_phone; ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('landline_phone', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>
                <?php if (sc_player_info_is_field_visible('province', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="province">استان</label></th>
                    <td><input name="province" type="text" id="province" value="<?php echo esc_attr($province); ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('province', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>
                <?php if (sc_player_info_is_field_visible('city', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="city">شهر</label></th>
                    <td><input name="city" type="text" id="city" value="<?php echo esc_attr($city); ?>" class="regular-text"<?php echo sc_player_info_field_required_attr('city', $player_field_rules); ?>></td>
                </tr>
                <?php endif; ?>
                <?php sc_render_admin_player_custom_fields_rows($player_custom_fields, 'contact', $member_extra_fields); ?>
                <?php if (sc_player_info_is_field_visible('gender', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="gender">جنسیت</label></th>
                    <td>
                        <select name="gender" id="gender" class="regular-text"<?php echo sc_player_info_field_required_attr('gender', $player_field_rules); ?>>
                            <option value="">انتخاب کنید</option>
                            <option value="male" <?php selected($gender, 'male'); ?>>مرد</option>
                            <option value="female" <?php selected($gender, 'female'); ?>>زن</option>
                        </select>
                    </td>
                </tr>
                <?php endif; ?>

              
                <?php if (sc_player_info_is_field_visible('birth_date_shamsi', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="birth_date_shamsi">تاریخ تولد (شمسی)</label></th>
                    <td>
                        <input name="birth_date_shamsi" type="text" id="birth_date_shamsi" value="<?php echo esc_attr($birth_date_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلاً 1400/02/15" readonly<?php echo sc_player_info_field_required_attr('birth_date_shamsi', $player_field_rules); ?>>
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
                <?php endif; ?>
                

               
                <?php if (sc_player_info_is_field_visible('birth_date_gregorian', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="birth_date_gregorian">تاریخ تولد (میلادی)</label></th>
                    <td><input name="birth_date_gregorian" type="date" id="birth_date_gregorian" value="<?php echo $birth_date_gregorian; ?>"<?php echo sc_player_info_field_required_attr('birth_date_gregorian', $player_field_rules); ?>>
                    <p class="description">تاریخ میلادی به صورت خودکار از تاریخ شمسی تبدیل خواهد شد ( بعد از ذخیره اطلاعات کاربر )</p>

                </td>
                </tr>
                <?php endif; ?>
                <?php sc_render_admin_player_custom_fields_rows($player_custom_fields, 'personal', $member_extra_fields); ?>

              
                <?php if (sc_player_info_is_field_visible('personal_photo', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="personal_photo">عکس پرسنلی</label></th>
                    <td>
                        <?php
                            
                            ?>
                            <input type="text" name="personal_photo" id="personal_photo_txt" class="regular-text" value="<?php echo $personal_photo; ?>" placeholder="آدرس تصویر یا آپلود کنید"<?php echo sc_player_info_field_required_attr('personal_photo', $player_field_rules); ?>>
                            <button type="button" class="button-secondary sc-upload-btn" id="btn_personal_photo">انتخاب تصویر</button>

                            <?php if (!empty($personal_photo)) : ?>
                                <div class="img_photo_prev">
                                    <img src="<?php   echo esc_url($personal_photo); ?>">
                                </div>
                            <?php endif; ?>

                            
                    </td>
                </tr>
                <?php endif; ?>

               
                <?php if (sc_player_info_is_field_visible('id_card_photo', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="id_card_photo">عکس کارت ملی</label></th>
                    <td>
                            <input type="text" name="id_card_photo" id="id_card_photo_txt" value="<?php  echo esc_attr($id_card_photo);?>" class="regular-text" placeholder="آدرس تصویر یا آپلود کنید"<?php echo sc_player_info_field_required_attr('id_card_photo', $player_field_rules); ?> />
                            <input type="button" class="button-secondary sc-upload-btn" id="btn_id_card_photo" value="انتخاب تصویر">
                            <?php if (!empty($id_card_photo)) : ?>
                                <div class="img_photo_prev">
                                    <img src="<?php   echo esc_url($id_card_photo); ?>">
                                </div>
                            <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

              
                <?php if (sc_player_info_is_field_visible('sport_insurance_photo', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="sport_insurance_photo">عکس بیمه ورزشی</label></th>
                    <td>
                        <input type="text" name="sport_insurance_photo" id="sport_insurance_photo_txt" value="<?php  echo esc_attr($sport_insurance_photo);?>" class="regular-text" placeholder="آدرس تصویر یا آپلود کنید"<?php echo sc_player_info_field_required_attr('sport_insurance_photo', $player_field_rules); ?> />
                            <input type="button" class="button-secondary sc-upload-btn" id="btn_sport_insurance_photo" value="انتخاب تصویر">
                            <?php if (!empty($sport_insurance_photo)) : ?>
                                <div class="img_photo_prev" >
                                    <img src="<?php   echo esc_url($sport_insurance_photo); ?>">
                                </div>
                            <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php sc_render_admin_player_custom_fields_rows($player_custom_fields, 'documents', $member_extra_fields); ?>

              
                <?php if (sc_player_info_is_field_visible('insurance_expiry_date_shamsi', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="insurance_expiry_date_shamsi">تاریخ انقضا بیمه (شمسی)</label></th>
                    <td>
                        <input name="insurance_expiry_date_shamsi" type="text" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلاً 1403/12/29" readonly<?php echo sc_player_info_field_required_attr('insurance_expiry_date_shamsi', $player_field_rules); ?>>
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
                <?php endif; ?>

             
                <?php if (sc_player_info_is_field_visible('medical_condition', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="medical_condition">مشکلات پزشکی</label></th>
                    <td><textarea name="medical_condition" id="medical_condition" rows="4" class="large-text"<?php echo sc_player_info_field_required_attr('medical_condition', $player_field_rules); ?>><?php echo $medical_condition; ?></textarea></td>
                </tr>
                <?php endif; ?>

               
                <?php if (sc_player_info_is_field_visible('sports_history', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="sports_history">سوابق ورزشی</label></th>
                    <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"<?php echo sc_player_info_field_required_attr('sports_history', $player_field_rules); ?>><?php echo $sports_history; ?></textarea></td>
                </tr>
                <?php endif; ?>

               
                <?php if (sc_player_info_is_field_visible('health_verified', $player_field_rules)) : ?>
                <tr>
                    <th scope="row">وضعیت سلامت تأیید شده</th>
                    <td><label><input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1" > بله</label></td>
                </tr>
                <?php endif; ?>

              
                <?php if (sc_player_info_is_field_visible('info_verified', $player_field_rules)) : ?>
                <tr>
                    <th scope="row">اطلاعات تأیید شده</th>
                    <td><label><input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1"> بله</label></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row">احراز هویت تایید شده</th>
                    <td>
                        <label style="margin-left:10px;"><input name="identity_verified" type="checkbox" <?php checked($identity_verified, 1); ?> value="1"> بله</label>
                        <?php if (!empty($player) && !empty($player->id)) : ?>
                            <button type="button" class="button button-secondary" id="sc_reject_identity_btn" style="margin-right:10px;">عدم احراز هویت</button>
                        <?php endif; ?>
                    </td>
                </tr>

               
                <tr>
                    <th scope="row">فعال</th>
                    <td><label class="switch" ><input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1"><span class="slider round"></span> بله</label></td>
                </tr>
                <tr>
                    <th scope="row">غیرفعال کردن صورت حساب خودکار</th>
                    <td>
                        <label class="switch">
                            <input 
                                name="disable_auto_invoice" 
                                type="checkbox" 
                                <?php checked( isset($disable_auto_invoice) ? $disable_auto_invoice : 0, 1 ); ?> 
                                value="1"
                            >
                            <span class="slider round"></span> بله
                        </label>
                    </td>
                    <?php 
                          $team_table = $wpdb->prefix . 'sc_team_categories';
                $level_table = $wpdb->prefix . 'sc_level_categories';

                
                // دریافت تیم ها
                $teams = $wpdb->get_results(
                    "SELECT * FROM $team_table ORDER BY id ASC"
                );
                
                // دریافت سطح ها
                $levels = $wpdb->get_results(
                    "SELECT * FROM $level_table ORDER BY id ASC"
                );
                
                    ?>
                </tr>
    
                <tr>
                    <th scope="row"><label for="member_type">نوع عضو</label></th>
                    <td>
                        <select name="member_type" id="member_type" class="regular-text">
                            <option value="normal" <?php selected($member_type, 'normal'); ?>>بازیکن عادی</option>
                            <option value="team" <?php selected($member_type, 'team'); ?>>بازیکن تیم</option>
                        </select>
                        <p class="description">بازیکن عادی: صورت‌حساب دوره ایجاد می‌شود. بازیکن تیم: صورت‌حساب ایجاد نمی‌شود و هزینه هر جلسه از کیف پول کسر می‌شود.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="team">انتخاب تیم بازیکن </label></th>
                    <td>

                        <select name="team_player" id="team_player" class="regular-text">
                          <?php 
                          
                          foreach($teams as $team){ 
                            ?>
                            <option value="<?php echo  $team->name; ?>" <?php  selected($team_player,  $team->name); ?>><?php echo $team->name; ?></option>
                           <?php } ?>
                        </select>
                        <p class="description">برای افزودن تیم باید از قسمت تیم و سطح -> دسته بندی تیم ها ، تیم های باشگاه خود را اضافه کنید.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="member_type">انتخاب سطح بازیکن </label></th>
                    <td>

                        <select name="skill_level" id="skill_level" class="regular-text">
                          <?php 
                      
                          
                          foreach($levels as $level){ ?>
                            <option value="<?php echo $level->name; ?>" <?php selected($skill_level,  $level->name); ?>><?php echo $level->name; ?></option>
                           <?php } ?>
                        </select>
                        <p class="description">برای انتخاب سطح از بخش تیم و سطح ->دسته بندی سطح، سطح های باشگاه خودرا اضافه کنید</p>
                    </td>
                </tr>
                <?php if (sc_player_info_is_field_visible('additional_info', $player_field_rules)) : ?>
                <tr>
                    <th scope="row"><label for="additional_info">توضیحات اضافی</label></th>
                    <td><textarea name="additional_info" id="additional_info" rows="3" class="large-text"<?php echo sc_player_info_field_required_attr('additional_info', $player_field_rules); ?>><?php echo $additional_info; ?></textarea></td>
                </tr>
                <?php endif; ?>
                <?php sc_render_admin_player_custom_fields_rows($player_custom_fields, 'additional', $member_extra_fields); ?>

              

            </tbody>
        </table>

        <!-- بخش آکاردئونی دوره‌ها -->
        <div class="sc-courses-accordion" style="margin-top: 20px;">
            <div class="sc-accordion-header" style="cursor: default;">
                <h3>دوره‌های بازیکن</h3>
            </div>
            <div id="sc-courses-content" style="display: block;">
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
                $player_courses_enrollment_sessions = [];
                $player_course_chapter = [];
                $player_course_coach = [];
                $player_course_group = [];
                /** ثبت‌نام فعال بدون فلگ وضعیت (paused/completed/canceled): course_id => [ total_sessions, remaining_sessions ] */
                $player_courses_sessions = [];
                $member_branch_configs = [];
                $edit_member_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
                if ($edit_member_id) {
                    $player_courses_data = $wpdb->get_results($wpdb->prepare(
                        "SELECT course_id, status, course_status_flags, enrollment_sessions, total_sessions, remaining_sessions, chapter, coach_id, group_name FROM $member_courses_table WHERE member_id = %d",
                        $edit_member_id
                    ), ARRAY_A);
                    if ($player_courses_data) {
                        foreach ($player_courses_data as $pc) {
                            $cid = absint($pc['course_id']);
                            if (!$cid) {
                                continue;
                            }
                            if ($pc['status'] === 'active') {
                                $player_courses_active[$cid] = true;
                            }
                            $flags = [];
                            if (isset($pc['course_status_flags']) && $pc['course_status_flags'] !== '' && $pc['course_status_flags'] !== null) {
                                $flags = array_filter(array_map('trim', explode(',', (string) $pc['course_status_flags'])));
                            }
                            $player_courses_flags[$cid] = $flags;
                            $player_courses_enrollment_sessions[$cid] = isset($pc['enrollment_sessions']) ? (int) $pc['enrollment_sessions'] : 0;
                            $player_course_chapter[$cid] = isset($pc['chapter']) ? (string) $pc['chapter'] : '';
                            $player_course_coach[$cid] = isset($pc['coach_id']) ? (int) $pc['coach_id'] : 0;
                            $player_course_group[$cid] = isset($pc['group_name']) ? (string) $pc['group_name'] : '';
                            if ($pc['status'] === 'active' && empty($flags)) {
                                $player_courses_sessions[$cid] = [
                                    'total_sessions' => (int) ($pc['total_sessions'] ?? 0),
                                    'remaining_sessions' => (int) ($pc['remaining_sessions'] ?? 0),
                                ];
                            }
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
                            "SELECT COUNT(*)
                             FROM $member_courses_table
                             WHERE course_id = %d
                               AND status = 'active'
                               AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')",
                            $course->id
                        ));
                        $course_packages = function_exists('sc_get_course_packages') ? sc_get_course_packages($course->id) : [];
                        $has_course_packages = !empty($course_packages);
                        $selected_pkg_sessions = isset($player_courses_enrollment_sessions[$course->id]) ? (int) $player_courses_enrollment_sessions[$course->id] : 0;
                        $selected_chapter = isset($player_course_chapter[$course->id]) ? (string) $player_course_chapter[$course->id] : '';
                        $selected_coach = isset($player_course_coach[$course->id]) ? (int) $player_course_coach[$course->id] : 0;
                        if (function_exists('sc_get_course_enrollment_branch_config')) {
                            $member_branch_configs[(int) $course->id] = sc_get_course_enrollment_branch_config((int) $course->id);
                        }
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
                        if ($edit_member_id && isset($player_courses_sessions[$course->id])) {
                            $ps = $player_courses_sessions[$course->id];
                            ?>
                        <div class="session_course_member">
                            <div class="total_sessions">
                                <span class="key">کل جلسات دوره : </span>
                                <span class="val"><?php echo (int) $ps['total_sessions']; ?></span>
                            </div>
                            <div class="remaining_sessions">
                                <span class="key">جلسات باقی مانده : </span>
                                <input type="number" name="remaining_sessions[<?php echo esc_attr((string) $course->id); ?>]" min="0" step="1"
                                    value="<?php echo (int) $ps['remaining_sessions']; ?>">
                            </div>
                        </div>
                            <?php
                        }

                        if ($has_course_packages) {
                            echo '<div style="margin-top:8px;padding:8px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;">';
                            echo '<strong style="display:block;margin-bottom:6px;">پکیج ثبت‌نام</strong>';
                            echo '<select name="course_enrollment_package[' . esc_attr($course->id) . ']" style="min-width:220px;">';
                            echo '<option value="">انتخاب پکیج</option>';
                            foreach ($course_packages as $pkg) {
                                $sel = selected($selected_pkg_sessions, (int) $pkg->sessions_count, false);
                                echo '<option value="' . esc_attr((int) $pkg->sessions_count) . '" ' . $sel . '>';
                                echo esc_html((int) $pkg->sessions_count) . ' جلسه - ' . esc_html(number_format((float) $pkg->price, 0, '.', ',')) . ' تومان';
                                echo '</option>';
                            }
                            echo '</select>';
                            echo '<p class="description" style="margin-top:6px;">برای دوره‌های دارای پکیج، انتخاب این مقدار الزامی است.</p>';
                            echo '</div>';
                        }

                        echo '<div class="sc-member-course-branch-block" id="sc_member_branch_' . esc_attr((string) $course->id) . '" style="margin-top:10px;padding:10px;background:#f0f6fc;border:1px solid #c3d9e8;border-radius:4px;" data-course-id="' . esc_attr((string) $course->id) . '">';
                        echo '<strong style="display:block;margin-bottom:8px;">شعبه، مربی و گروه</strong>';
                        echo '<div class="sc-member-chapter-field" style="margin-bottom:8px;"></div>';
                        echo '<div class="sc-member-coach-field" style="margin-bottom:8px;"></div>';
                        echo '<div class="sc-member-group-field"></div>';
                        echo '</div>';

                        echo '</label>';
                        
                        // Checkbox های وضعیت‌های اضافی
                        echo '<div id="course_status_' . esc_attr($course->id) . '" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">';
                        echo '<label style="font-size: 18px; display: block; font-weight:bold;" > <p>وضعیت‌های اضافی: </p>';
                        echo '<div style="display: flex; gap: 15px; flex-wrap: wrap;">';
                        
                        echo '<label class="label_cheakbox_active_courses_user">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][paused]" value="1" ' . ($is_paused ? 'checked' : '') . ' style="margin-left: 5px;">';
                        echo '<span>متوقف شده</span>';
                        echo '</label>';
                        
                        echo '<label class="label_cheakbox_active_courses_user">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][completed]" value="1" ' . ($is_completed ? 'checked' : '') . ' style="margin-left: 5px;">';
                        echo '<span>تمام شده</span>';
                        echo '</label>';
                        
                        echo '<label class="label_cheakbox_active_courses_user">';
                        echo '<input type="checkbox" name="course_flags[' . esc_attr($course->id) . '][canceled]" value="1" ' . ($is_canceled ? 'checked' : '') . ' style="margin-left: 5px;">';
                        echo '<span>لغو شده</span>';
                        echo '</label>';
                        echo '</label>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '<br><strong style="margin-top:10px;  font-size:24px; font-weight:bold; ">راهنما دوره های بازیکن : <br></strong>';
                    echo '<p class="description" style="margin-top: 10px; font-size:20px; ">بازیکن می‌تواند در چند دوره شرکت کند. تیک اول دوره را فعال/غیرفعال می‌کند و تیک‌های دیگر وضعیت‌های اضافی هستند. - در صورت انتخاب وضعیت های اضافی صورت حساب برای آن دوره ایجاد نخواهد شد.</p>';
                    echo '<p class="description" style="margin-top: 10px;  font-size:20px; "> دوره فعال برای بازیکن به این معنا است که بازیکن در کلاس ها حاضر است و برای بازیکن به صورت ماهیانه صورتحساب ایجاد می شود.</p>';
                    if (!empty($member_branch_configs)) {
                        echo '<script type="application/json" id="sc-member-branch-configs">' . wp_json_encode($member_branch_configs, JSON_UNESCAPED_UNICODE) . '</script>';
                        echo '<script type="application/json" id="sc-member-branch-selected">' . wp_json_encode([
                            'chapters' => $player_course_chapter,
                            'coaches' => $player_course_coach,
                            'groups' => $player_course_group,
                        ], JSON_UNESCAPED_UNICODE) . '</script>';
                    }
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

    <!-- Rejection Modal -->
    <div id="sc_reject_identity_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; width:400px; max-width:90%; padding:20px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.3);">
            <h3 style="margin-top:0;">عدم احراز هویت</h3>
            <p>لطفاً علت رد احراز هویت را وارد کنید:</p>
            <textarea id="sc_reject_reason" rows="4" style="width:100%;"></textarea>
            <div style="margin-top:15px; text-align:left;">
                <button type="button" class="button" id="sc_reject_cancel">انصراف</button>
                <button type="button" class="button button-primary" id="sc_reject_send">ارسال پیامک و نوتیفیکیشن</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($){
        var memberId = <?php echo !empty($player) && !empty($player->id) ? (int)$player->id : 0; ?>;
        if (memberId > 0) {
            $('#sc_reject_identity_btn').on('click', function(){
                $('#sc_reject_identity_modal').css('display', 'flex');
            });
            $('#sc_reject_cancel').on('click', function(){
                $('#sc_reject_identity_modal').hide();
            });
            $('#sc_reject_send').on('click', function(){
                var reason = $('#sc_reject_reason').val().trim();
                if (!reason) {
                    alert('لطفاً علت را وارد کنید.');
                    return;
                }
                $.post(ajaxurl, {
                    action: 'sc_reject_identity',
                    member_id: memberId,
                    reason: reason,
                    _wpnonce: '<?php echo wp_create_nonce('sc_reject_identity'); ?>'
                }, function(resp){
                    if (resp.success) {
                        alert('پیامک و نوتیفیکیشن با موفقیت ارسال شد.');
                        $('#sc_reject_identity_modal').hide();
                        $('#sc_reject_reason').val('');
                    } else {
                        alert(resp.data || 'خطا در ارسال.');
                    }
                });
            });
        }

        var branchConfigs = {};
        var branchSelected = { chapters: {}, coaches: {}, groups: {} };
        try {
            var cfgEl = document.getElementById('sc-member-branch-configs');
            var selEl = document.getElementById('sc-member-branch-selected');
            if (cfgEl) {
                branchConfigs = JSON.parse(cfgEl.textContent || '{}');
            }
            if (selEl) {
                branchSelected = JSON.parse(selEl.textContent || '{}');
            }
        } catch (e) {
            branchConfigs = {};
        }

        function renderMemberBranchBlock(courseId) {
            var cfg = branchConfigs[courseId];
            var $block = $('#sc_member_branch_' + courseId);
            if (!$block.length || !cfg) {
                return;
            }
            var chapters = cfg.chapters || [];
            var selChapter = (branchSelected.chapters && branchSelected.chapters[courseId]) ? branchSelected.chapters[courseId] : '';
            var selCoach = (branchSelected.coaches && branchSelected.coaches[courseId]) ? parseInt(branchSelected.coaches[courseId], 10) : 0;
            var $chField = $block.find('.sc-member-chapter-field');
            var $coField = $block.find('.sc-member-coach-field');
            $chField.empty();
            $coField.empty();

            if (!chapters.length) {
                $chField.html('<span class="description">شعبه‌ای برای این دوره تعریف نشده است.</span>');
                return;
            }

            if (chapters.length === 1) {
                var onlyName = chapters[0].name;
                $chField.html('<span><strong>شعبه:</strong> ' + onlyName + '</span><input type="hidden" name="course_chapter[' + courseId + ']" value="' + onlyName + '">');
                selChapter = onlyName;
            } else {
                var html = '<label><strong>شعبه:</strong> <select name="course_chapter[' + courseId + ']" class="sc-member-chapter-select" data-course-id="' + courseId + '">';
                html += '<option value="">انتخاب شعبه</option>';
                chapters.forEach(function (ch) {
                    html += '<option value="' + ch.name + '"' + (selChapter === ch.name ? ' selected' : '') + '>' + ch.name + '</option>';
                });
                html += '</select></label>';
                $chField.html(html);
            }

            renderMemberCoachField(courseId, selChapter, selCoach);
            renderMemberGroupField(courseId);
        }

        function renderMemberGroupField(courseId) {
            var cfg = branchConfigs[courseId];
            var $groupField = $('#sc_member_branch_' + courseId + ' .sc-member-group-field');
            $groupField.empty();
            if (!cfg || !cfg.groups || !cfg.groups.has_grouping || !cfg.groups.groups || !cfg.groups.groups.length) {
                return;
            }
            var selGroup = (branchSelected.groups && branchSelected.groups[courseId]) ? branchSelected.groups[courseId] : '';
            var html = '<label><strong>گروه (اختیاری):</strong> <select name="course_group[' + courseId + ']" class="sc-member-group-select">';
            html += '<option value="">بدون گروه</option>';
            (cfg.groups.groups || []).forEach(function (g) {
                var name = g.name || '';
                if (!name) {
                    return;
                }
                html += '<option value="' + name + '"' + (selGroup === name ? ' selected' : '') + '>' + name + '</option>';
            });
            html += '</select></label>';
            if (selGroup) {
                var desc = '';
                (cfg.groups.groups || []).forEach(function (g) {
                    if (g.name === selGroup && g.description) {
                        desc = g.description;
                    }
                });
                if (desc) {
                    html += '<p class="description" style="margin-top:6px;">' + desc + '</p>';
                }
            }
            $groupField.html(html);
        }

        function renderMemberCoachField(courseId, chapterName, selectedCoachId) {
            var cfg = branchConfigs[courseId];
            var $coField = $('#sc_member_branch_' + courseId + ' .sc-member-coach-field');
            $coField.empty();
            if (!cfg || !chapterName) {
                return;
            }
            var coaches = [];
            (cfg.chapters || []).forEach(function (ch) {
                if (ch.name === chapterName) {
                    coaches = ch.coaches || [];
                }
            });
            if (!coaches.length) {
                $coField.html('<span class="description">مربی برای این شعبه تعریف نشده — ثبت‌نام بدون مربی.</span><input type="hidden" name="course_coach[' + courseId + ']" value="0">');
                return;
            }
            if (coaches.length === 1) {
                var c = coaches[0];
                $coField.html('<span><strong>مربی:</strong> ' + c.label + '</span><input type="hidden" name="course_coach[' + courseId + ']" value="' + c.id + '">');
                return;
            }
            var html = '<label><strong>مربی:</strong> <select name="course_coach[' + courseId + ']" class="sc-member-coach-select">';
            html += '<option value="0">انتخاب مربی (اختیاری)</option>';
            coaches.forEach(function (c) {
                html += '<option value="' + c.id + '"' + (selectedCoachId === c.id ? ' selected' : '') + '>' + c.label + '</option>';
            });
            html += '</select></label>';
            $coField.html(html);
        }

        Object.keys(branchConfigs).forEach(function (courseId) {
            renderMemberBranchBlock(courseId);
        });

        $(document).on('change', '.sc-member-chapter-select', function () {
            var courseId = $(this).data('course-id');
            renderMemberCoachField(courseId, $(this).val(), 0);
        });
    });
    </script>
</div>
