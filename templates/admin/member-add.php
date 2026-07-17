<?php 
if ( ! defined('ABSPATH') ) exit;
if(!isset($_GET['player_id'])){
    $sc_reg_blocked = function_exists('sc_registration_limit_can_create_user') && !sc_registration_limit_can_create_user();
?>
    <h1>افزودن بازیکن جدید</h1>
    <?php if ($sc_reg_blocked) : ?>
        <div class="sc-reg-limit-blocked-banner">
            <strong>ثبت بازیکن جدید غیرفعال است.</strong>
            <?php echo esc_html(function_exists('sc_registration_limit_admin_message') ? sc_registration_limit_admin_message() : 'ظرفیت کاربران سامانه تکمیل شده است.'); ?>
        </div>
        <p>
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=pro_features')); ?>">تنظیمات محدودیت ثبت‌نام</a>
        </p>
    <?php else : ?>
        <p>لطفا برای افزودن بازیکن جدید به بخش کاربران بروید</p>
        <a class="sc_button" href="<?php echo admin_url('user-new.php'); ?>">بخش کاربران</a>
    <?php endif; ?>
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
                if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_courses')) {
                    $courses = sc_secretary_get_branch_courses();
                }
                
                // دریافت دوره‌های فعلی بازیکن با وضعیت آن‌ها
                $player_courses_active = [];
                $player_courses_flags = [];
                $player_courses_enrollment_sessions = [];
                $player_course_chapter = [];
                $player_course_coach = [];
                $player_course_group = [];
                /** ثبت‌نام فعال: course_id => [ total_sessions, remaining_sessions ] */
                $player_courses_sessions = [];
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
                            if ($pc['status'] === 'active') {
                                $player_courses_sessions[$cid] = [
                                    'total_sessions' => (int) ($pc['total_sessions'] ?? 0),
                                    'remaining_sessions' => (int) ($pc['remaining_sessions'] ?? 0),
                                ];
                            }
                        }
                    }

                    // در ویرایش بازیکن فقط دوره‌های فعال او نمایش داده شود (با/بدون فلگ وضعیت)
                    $courses = array_values(array_filter((array) $courses, static function ($course) use ($player_courses_active) {
                        return isset($player_courses_active[(int) $course->id]);
                    }));
                    $listed_ids = [];
                    foreach ($courses as $listed_course) {
                        $listed_ids[(int) $listed_course->id] = true;
                    }

                    // اگر دورهٔ فعال در کاتالوگ نبود (مثلاً غیرفعال شده)، باز هم در ویرایش نشان بده
                    $missing_ids = array_values(array_filter(array_diff(
                        array_map('absint', array_keys($player_courses_active)),
                        array_map('absint', array_keys($listed_ids))
                    )));
                    if (!empty($missing_ids)) {
                        $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
                        $extra_sql = "SELECT * FROM $courses_table WHERE deleted_at IS NULL AND id IN ($placeholders) ORDER BY title ASC";
                        $extra_courses = $wpdb->get_results($wpdb->prepare($extra_sql, ...$missing_ids));
                        if (!empty($extra_courses)) {
                            $courses = array_merge($courses, $extra_courses);
                        }
                    }
                }
                
                if (empty($courses)) {
                    echo '<div style="padding: 20px; text-align: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
                    if ($edit_member_id) {
                        echo '<p style="margin: 0; color: #856404;">این بازیکن دوره فعالی ندارد (دوره‌های فعال با فلگ یا بدون فلگ).</p>';
                    } else {
                        echo '<p style="margin: 0 0 10px 0; color: #856404;">هنوز دوره‌ای ثبت نشده است.</p>';
                        echo '<a href="' . admin_url('admin.php?page=sc-add-course') . '" target="_blank" class="button button-primary">افزودن دوره جدید</a>';
                    }
                    echo '</div>';
                } else {
                    $coaches_table_ma = $wpdb->prefix . 'sc_coaches';
                    $coach_ids_needed = array_values(array_filter(array_map('absint', $player_course_coach)));
                    $coach_name_map = [];
                    if (!empty($coach_ids_needed)) {
                        $coach_placeholders = implode(',', array_fill(0, count($coach_ids_needed), '%d'));
                        $coach_rows = $wpdb->get_results($wpdb->prepare(
                            "SELECT id, first_name, last_name FROM $coaches_table_ma WHERE id IN ($coach_placeholders)",
                            ...$coach_ids_needed
                        ));
                        foreach ((array) $coach_rows as $crow) {
                            $coach_name_map[(int) $crow->id] = trim((string) $crow->first_name . ' ' . (string) $crow->last_name);
                        }
                    }

                    echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">';
                    foreach ($courses as $course) {
                        $cid = (int) $course->id;
                        $is_active = isset($player_courses_active[$cid]);
                        $current_flags = isset($player_courses_flags[$cid]) ? $player_courses_flags[$cid] : [];
                        $is_paused = in_array('paused', $current_flags, true);
                        $is_completed = in_array('completed', $current_flags, true);
                        $is_canceled = in_array('canceled', $current_flags, true);
                        $selected_chapter = isset($player_course_chapter[$cid]) ? (string) $player_course_chapter[$cid] : '';
                        $selected_coach = isset($player_course_coach[$cid]) ? (int) $player_course_coach[$cid] : 0;
                        $selected_group = isset($player_course_group[$cid]) ? (string) $player_course_group[$cid] : '';
                        $selected_pkg_sessions = isset($player_courses_enrollment_sessions[$cid]) ? (int) $player_courses_enrollment_sessions[$cid] : 0;
                        $coach_label = ($selected_coach > 0 && isset($coach_name_map[$selected_coach]))
                            ? $coach_name_map[$selected_coach]
                            : ($selected_coach > 0 ? ('#' . $selected_coach) : '—');
                        $group_label = $selected_group !== '' ? $selected_group : '—';
                        $package_label = '—';
                        if ($selected_pkg_sessions > 0 && function_exists('sc_get_course_package_by_sessions')) {
                            $pkg = sc_get_course_package_by_sessions($cid, $selected_pkg_sessions);
                            if ($pkg) {
                                $package_label = (int) $pkg->sessions_count . ' جلسه - ' . number_format((float) $pkg->price, 0, '.', ',') . ' تومان';
                            } else {
                                $package_label = $selected_pkg_sessions . ' جلسه';
                            }
                        } elseif (function_exists('sc_course_has_packages') && sc_course_has_packages($cid)) {
                            $package_label = 'پکیج ثبت نشده';
                        }

                        echo '<div style="padding: 15px; margin-bottom: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '<div style="margin-bottom: 10px;"><strong>' . esc_html($course->title) . '</strong></div>';

                        // فقط نمایش — در ویرایش چیزی از مربی/گروه/پکیج/شعبه ذخیره نمی‌شود
                        echo '<div style="margin-top:8px;padding:10px;background:#f0f6fc;border:1px solid #c3d9e8;border-radius:4px;">';
                        echo '<div style="margin-bottom:6px;"><strong>مربی:</strong> ' . esc_html($coach_label) . '</div>';
                        echo '<div style="margin-bottom:6px;"><strong>گروه:</strong> ' . esc_html($group_label) . '</div>';
                        if ($selected_chapter !== '') {
                            echo '<div style="margin-bottom:6px;"><strong>شعبه:</strong> ' . esc_html($selected_chapter) . '</div>';
                        }
                        echo '<div><strong>پکیج:</strong> ' . esc_html($package_label) . '</div>';
                        echo '</div>';

                        if (!empty($current_flags)) {
                            $flag_labels = [];
                            if ($is_paused) {
                                $flag_labels[] = 'متوقف شده';
                            }
                            if ($is_completed) {
                                $flag_labels[] = 'تمام شده';
                            }
                            if ($is_canceled) {
                                $flag_labels[] = 'لغو شده';
                            }
                            if ($flag_labels) {
                                echo '<p class="description" style="margin-top:8px;">وضعیت: ' . esc_html(implode('، ', $flag_labels)) . '</p>';
                            }
                        }

                        if (isset($player_courses_sessions[$cid])) {
                            $ps = $player_courses_sessions[$cid];
                            ?>
                        <div class="session_course_member" style="margin-top:10px;">
                            <div class="total_sessions">
                                <span class="key">کل جلسات دوره : </span>
                                <span class="val"><?php echo (int) $ps['total_sessions']; ?></span>
                            </div>
                            <div class="remaining_sessions">
                                <span class="key">جلسات باقی مانده : </span>
                                <input type="number" name="remaining_sessions[<?php echo esc_attr((string) $cid); ?>]" step="1"
                                    value="<?php echo (int) $ps['remaining_sessions']; ?>">
                            </div>
                        </div>
                            <?php
                        }

                        echo '</div>';
                    }
                    echo '</div>';
                    echo '<br><strong style="margin-top:10px; font-size:24px; font-weight:bold;">راهنما دوره های بازیکن : <br></strong>';
                    echo '<p class="description" style="margin-top: 10px; font-size:20px;">در این بخش فقط می‌توانید تعداد جلسات باقی‌مانده را تغییر دهید. مربی، گروه، شعبه و پکیج فقط نمایش داده می‌شوند و ذخیره نمی‌شوند.</p>';
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

    });
    </script>
</div>
