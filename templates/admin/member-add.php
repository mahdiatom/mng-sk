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
        $medical_condition = '';
        $sports_history = '';
        $health_verified = 0;
        $info_verified = 0;
        $is_active = 1;
        $additional_info = '';

if($player && $_GET['player_id'] ){
        $first_name              = $player->first_name;
        $last_name               = $player->last_name;
        $father_name             = $player->father_name;
        $national_id             = $player->national_id;
        $player_phone            = $player->player_phone;
        $father_phone            = $player->father_phone;
        $mother_phone            = $player->mother_phone;
        $landline_phone          = $player->landline_phone;
        $birth_date_shamsi       = $player->birth_date_shamsi;
        $birth_date_gregorian    = $player->birth_date_gregorian;
        $personal_photo          = $player->personal_photo;
        $id_card_photo           = $player->id_card_photo;
        $sport_insurance_photo   = $player->sport_insurance_photo;
        $medical_condition       = $player->medical_condition;
        $sports_history          = $player->sports_history;
        $health_verified         = $player->health_verified;
        $info_verified           = $player->info_verified;
        $is_active               = $player->is_active;
        $additional_info         = $player->additional_info;
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
                    <td><input name="birth_date_shamsi" type="text" id="birth_date_shamsi" value="<?php echo $birth_date_shamsi; ?>" class="regular-text" placeholder="مثلاً 1400/02/15"></td>
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

        <p class="submit">
            <button type="submit" name="submit_player" class="button button-primary">
                <?php echo isset($_GET['player_id']) ? 'بروزرسانی اطلاعات بازیکن' : 'ثبت بازکین جدید'; ?>
            </button>
        </p>

    </form>
</div>
