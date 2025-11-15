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
?>
<div class="wrap">
    <h1 class="wp-heading-inline">افزودن بازیکن جدید</h1>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php //wp_nonce_field('sk_add_player', 'sk_player_nonce'); ?>

        <table class="form-table">
            <tbody>

                <!-- first_name -->
                <tr>
                    <th scope="row"><label for="first_name">نام</label></th>
                    <td><input name="first_name" type="text" id="first_name" value="<?php echo $first_name; ?>" class="regular-text" required></td>
                </tr>

                <!-- last_name -->
                <tr>
                    <th scope="row"><label for="last_name">نام خانوادگی</label></th>
                    <td><input name="last_name" type="text" id="last_name" value="<?php echo $last_name; ?>" class="regular-text" required></td>
                </tr>

                <!-- father_name -->
                <tr>
                    <th scope="row"><label for="father_name">نام پدر</label></th>
                    <td><input name="father_name" type="text" id="father_name" value="<?php echo $father_name; ?>" class="regular-text"></td>
                </tr>

                <!-- national_id -->
                <tr>
                    <th scope="row"><label for="national_id">کد ملی</label></th>
                    <td><input name="national_id" type="text" id="national_id" value="<?php echo $national_id; ?>" class="regular-text" required maxlength="10"></td>
                </tr>

                <!-- player_phone -->
                <tr>
                    <th scope="row"><label for="player_phone">شماره موبایل بازیکن</label></th>
                    <td><input name="player_phone" type="text" id="player_phone" value="<?php echo $player_phone; ?>" class="regular-text"></td>
                </tr>

                <!-- father_phone -->
                <tr>
                    <th scope="row"><label for="father_phone">شماره موبایل پدر</label></th>
                    <td><input name="father_phone" type="text" id="father_phone" value="<?php echo $father_phone; ?>" class="regular-text"></td>
                </tr>

                <!-- mother_phone -->
                <tr>
                    <th scope="row"><label for="mother_phone">شماره موبایل مادر</label></th>
                    <td><input name="mother_phone" type="text" id="mother_phone" value="<?php echo $mother_phone; ?>" class="regular-text"></td>
                </tr>

                <!-- landline_phone -->
                <tr>
                    <th scope="row"><label for="landline_phone">تلفن ثابت</label></th>
                    <td><input name="landline_phone" type="text" id="landline_phone" value="<?php echo $landline_phone; ?>" class="regular-text"></td>
                </tr>

                <!-- birth_date_shamsi -->
                <tr>
                    <th scope="row"><label for="birth_date_shamsi">تاریخ تولد (شمسی)</label></th>
                    <td><input name="birth_date_shamsi" type="text" id="birth_date_shamsi" value="<?php echo $birth_date_shamsi; ?>" class="regular-text" placeholder="مثلاً 1400/02/15"></td>
                </tr>

                <!-- birth_date_gregorian -->
                <tr>
                    <th scope="row"><label for="birth_date_gregorian">تاریخ تولد (میلادی)</label></th>
                    <td><input name="birth_date_gregorian" type="date" id="birth_date_gregorian" value="<?php echo $birth_date_gregorian; ?>"></td>
                </tr>

                <!-- personal_photo -->
                <tr>
                    <th scope="row"><label for="personal_photo">عکس پرسنلی</label></th>
                    <td><input name="personal_photo" type="file" id="personal_photo"></td>
                </tr>

                <!-- id_card_photo -->
                <tr>
                    <th scope="row"><label for="id_card_photo">عکس کارت ملی</label></th>
                    <td><input name="id_card_photo" type="file" id="id_card_photo"></td>
                </tr>

                <!-- sport_insurance_photo -->
                <tr>
                    <th scope="row"><label for="sport_insurance_photo">عکس بیمه ورزشی</label></th>
                    <td><input name="sport_insurance_photo" type="file" id="sport_insurance_photo"></td>
                </tr>

                <!-- medical_condition -->
                <tr>
                    <th scope="row"><label for="medical_condition">مشکلات پزشکی</label></th>
                    <td><textarea name="medical_condition" id="medical_condition" rows="4" class="large-text"><?php echo $medical_condition; ?></textarea></td>
                </tr>

                <!-- sports_history -->
                <tr>
                    <th scope="row"><label for="sports_history">سوابق ورزشی</label></th>
                    <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo $sports_history; ?></textarea></td>
                </tr>

                <!-- health_verified -->
                <tr>
                    <th scope="row">وضعیت سلامت تأیید شده</th>
                    <td><label><input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1"> بله</label></td>
                </tr>

                <!-- info_verified -->
                <tr>
                    <th scope="row">اطلاعات تأیید شده</th>
                    <td><label><input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1"> بله</label></td>
                </tr>

                <!-- is_active -->
                <tr>
                    <th scope="row">فعال</th>
                    <td><label><input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1"> بله</label></td>
                </tr>

                <!-- additional_info -->
                <tr>
                    <th scope="row"><label for="additional_info">توضیحات اضافی</label></th>
                    <td><textarea name="additional_info" id="additional_info" rows="3" class="large-text"><?php echo $additional_info; ?></textarea></td>
                </tr>

            </tbody>
        </table>

        <p class="submit">
            <button type="submit" name="submit_player" class="button button-primary">ثبت بازیکن</button>
        </p>

    </form>
</div>
