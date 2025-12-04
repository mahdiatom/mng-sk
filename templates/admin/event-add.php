<?php
$name = '';
$description = '';
$price = '';
$start_date = '';
$end_date = '';
$image = '';
$has_age_limit = 0;
$min_age = '';
$max_age = '';
$capacity = '';
$event_time = '';
$event_location = '';
$event_location_address = '';
$event_location_lat = '';
$event_location_lng = '';
$is_active = 1;

if ($event && isset($_GET['event_id'])) {
    $name = $event->name ?? '';
    $description = $event->description ?? '';
    $price = $event->price ?? '';
    $start_date = $event->start_date_gregorian ?? '';
    $end_date = $event->end_date_gregorian ?? '';
    $image = $event->image ?? '';
    $has_age_limit = $event->has_age_limit ?? 0;
    $min_age = $event->min_age ?? '';
    $max_age = $event->max_age ?? '';
    $capacity = $event->capacity ?? '';
    $event_time = $event->event_time ?? '';
    $event_location = $event->event_location ?? '';
    $event_location_address = $event->event_location_address ?? '';
    $event_location_lat = $event->event_location_lat ?? '';
    $event_location_lng = $event->event_location_lng ?? '';
    $is_active = $event->is_active ?? 1;
}
?>
<div class="wrap">
    <?php
    // نمایش پیام‌های موفقیت/خطا
    if (isset($_GET['sc_status'])) {
        $status = sanitize_text_field($_GET['sc_status']);
        switch ($status) {
            case 'event_add_true':
                echo '<div class="notice notice-success is-dismissible"><p>✅ رویداد با موفقیت ثبت شد.</p></div>';
                break;
            case 'event_add_error':
                echo '<div class="notice notice-error is-dismissible"><p>❌ خطا در ثبت رویداد. لطفاً دوباره تلاش کنید.</p></div>';
                break;
            case 'event_updated':
                echo '<div class="notice notice-success is-dismissible"><p>✅ رویداد با موفقیت بروزرسانی شد.</p></div>';
                break;
            case 'event_update_error':
                echo '<div class="notice notice-error is-dismissible"><p>❌ خطا در بروزرسانی رویداد. لطفاً دوباره تلاش کنید.</p></div>';
                break;
            case 'security_error':
                echo '<div class="notice notice-error is-dismissible"><p>❌ خطای امنیتی. لطفاً دوباره تلاش کنید.</p></div>';
                break;
        }
    }
    ?>
    <h1 class="wp-heading-inline">
        <?php echo isset($_GET['event_id']) ? 'بروزرسانی رویداد / مسابقه' : 'ثبت رویداد / مسابقه جدید'; ?>
    </h1>
    <?php 
    if (isset($_GET['event_id'])) {
        ?>
        <a href="<?php echo admin_url('admin.php?page=sc-add-event'); ?>" class="page-title-action">ثبت رویداد جدید</a>
        <?php 
    }
    ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('sc_event_form', 'sc_event_nonce'); ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="name">نام رویداد / مسابقه <span style="color:red;">*</span></label></th>
                    <td><input name="name" type="text" id="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
                </tr>

                <tr>
                    <th scope="row"><label for="description">توضیحات</label></th>
                    <td>
                        <?php
                        wp_editor($description, 'description', [
                            'textarea_name' => 'description',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => false,
                            'quicktags' => true
                        ]);
                        ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="price">قیمت <span style="color:red;">*</span></label></th>
                    <td>
                        <input type="text" 
                               name="price" 
                               id="price" 
                               value="<?php echo $price > 0 ? number_format($price, 0, '.', ',') : ''; ?>" 
                               class="regular-text" 
                               placeholder="0"
                               style="width: 300px;"
                               dir="ltr"
                               inputmode="numeric"
                               required>
                        <input type="hidden" name="price_raw" id="price_raw" value="<?php echo esc_attr($price ?? 0); ?>">
                        <p class="description">قیمت رویداد / مسابقه به تومان (با جدا کردن سه رقم سه رقم)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="start_date_shamsi">تاریخ شروع (شمسی)</label></th>
                    <td>
                        <?php
                        $start_date_shamsi = '';
                        if (!empty($start_date)) {
                            $start_date_shamsi = sc_date_shamsi_date_only($start_date);
                        } else {
                            $today_timestamp = time();
                            $today_date = getdate($today_timestamp);
                            $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
                            $start_date_shamsi = $today_jalali[0] . '/' . 
                                                 ($today_jalali[1] < 10 ? '0' . $today_jalali[1] : $today_jalali[1]) . '/' . 
                                                 ($today_jalali[2] < 10 ? '0' . $today_jalali[2] : $today_jalali[2]);
                        }
                        ?>
                        <input name="start_date_shamsi" type="text" id="start_date_shamsi" 
                               value="<?php echo esc_attr($start_date_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تاریخ شروع (شمسی)" 
                               readonly
                               style="width: 300px; padding: 5px;">
                        <input type="hidden" name="start_date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="end_date_shamsi">تاریخ پایان (شمسی)</label></th>
                    <td>
                        <?php
                        $end_date_shamsi = '';
                        if (!empty($end_date)) {
                            $end_date_shamsi = sc_date_shamsi_date_only($end_date);
                        } else {
                            $today_timestamp = time();
                            $today_date = getdate($today_timestamp);
                            $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
                            $end_date_shamsi = $today_jalali[0] . '/' . 
                                               ($today_jalali[1] < 10 ? '0' . $today_jalali[1] : $today_jalali[1]) . '/' . 
                                               ($today_jalali[2] < 10 ? '0' . $today_jalali[2] : $today_jalali[2]);
                        }
                        ?>
                        <input name="end_date_shamsi" type="text" id="end_date_shamsi" 
                               value="<?php echo esc_attr($end_date_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تاریخ پایان (شمسی)" 
                               readonly
                               style="width: 300px; padding: 5px;">
                        <input type="hidden" name="end_date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="image">عکس رویداد</label></th>
                    <td>
                        <input type="url" id="image_url" name="image" value="<?php echo esc_attr($image); ?>" class="regular-text" placeholder="آدرس عکس">
                        <button type="button" class="button" id="upload_image_button">انتخاب عکس</button>
                        <?php if (!empty($image)) : ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo esc_url($image); ?>" alt="عکس رویداد" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        <?php endif; ?>
                        <p class="description">آدرس URL عکس رویداد یا استفاده از دکمه انتخاب</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">شرط سنی</th>
                    <td>
                        <label>
                            <input type="checkbox" name="has_age_limit" id="has_age_limit" value="1" <?php checked($has_age_limit, 1); ?>>
                            اعمال محدودیت سنی
                        </label>
                        <div id="age_limit_fields" style="margin-top: 15px; <?php echo $has_age_limit ? '' : 'display: none;'; ?>">
                            <label for="min_age" style="display: inline-block; margin-left: 20px;">
                                حداقل سن:
                                <input type="number" name="min_age" id="min_age" value="<?php echo esc_attr($min_age); ?>" min="0" max="100" style="width: 80px;">
                            </label>
                            <label for="max_age" style="display: inline-block; margin-left: 20px;">
                                حداکثر سن:
                                <input type="number" name="max_age" id="max_age" value="<?php echo esc_attr($max_age); ?>" min="0" max="100" style="width: 80px;">
                            </label>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="capacity">ظرفیت</label></th>
                    <td>
                        <input type="number" name="capacity" id="capacity" value="<?php echo esc_attr($capacity); ?>" min="0" class="regular-text">
                        <p class="description">تعداد حداکثر شرکت‌کنندگان (خالی = نامحدود)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="event_time">زمان مسابقه / رویداد</label></th>
                    <td>
                        <?php
                        wp_editor($event_time, 'event_time', [
                            'textarea_name' => 'event_time',
                            'textarea_rows' => 5,
                            'media_buttons' => false,
                            'teeny' => false,
                            'quicktags' => true
                        ]);
                        ?>
                        <p class="description">توضیحات زمان برگزاری مسابقه / رویداد (مثلاً: گروه سنی نوجوان از ساعت ۸ - بزرگسالان از ساعت ۱۰)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="event_location">مکان مسابقه / رویداد</label></th>
                    <td>
                        <input type="text" name="event_location" id="event_location" value="<?php echo esc_attr($event_location); ?>" class="regular-text" placeholder="نام مکان">
                        <p class="description">نام مکان برگزاری مسابقه / رویداد</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="event_location_address">آدرس مسابقه / رویداد</label></th>
                    <td>
                        <textarea name="event_location_address" id="event_location_address" rows="3" class="large-text" placeholder="آدرس کامل"><?php echo esc_textarea($event_location_address); ?></textarea>
                        <p class="description">آدرس کامل محل برگزاری</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>لوکیشن مسابقه / رویداد (Google Maps)</label></th>
                    <td>
                        <div style="margin-bottom: 10px;">
                            <label for="event_location_lat" style="display: inline-block; margin-left: 20px;">
                                عرض جغرافیایی (Latitude):
                                <input type="text" name="event_location_lat" id="event_location_lat" value="<?php echo esc_attr($event_location_lat); ?>" class="regular-text" placeholder="مثال: 35.6892">
                            </label>
                            <label for="event_location_lng" style="display: inline-block; margin-left: 20px;">
                                طول جغرافیایی (Longitude):
                                <input type="text" name="event_location_lng" id="event_location_lng" value="<?php echo esc_attr($event_location_lng); ?>" class="regular-text" placeholder="مثال: 51.3890">
                            </label>
                        </div>
                        <p class="description">
                            برای دریافت مختصات، به 
                            <a href="https://www.google.com/maps" target="_blank">Google Maps</a> 
                            بروید، روی مکان مورد نظر کلیک راست کنید و "مختصات" را انتخاب کنید.
                        </p>
                        <?php if (!empty($event_location_lat) && !empty($event_location_lng)) : ?>
                            <div id="map_preview" style="margin-top: 15px; width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px;">
                                <iframe
                                    width="100%"
                                    height="100%"
                                    frameborder="0"
                                    style="border:0; border-radius: 4px;"
                                    src="https://www.google.com/maps?q=<?php echo esc_attr($event_location_lat); ?>,<?php echo esc_attr($event_location_lng); ?>&output=embed"
                                    allowfullscreen>
                                </iframe>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">وضعیت</th>
                    <td>
                        <label class="switch">
                            <input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1">
                            <span class="slider round"></span> فعال
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo isset($_GET['event_id']) ? 'بروزرسانی' : 'ثبت'; ?>">
        </p>
    </form>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {
    // مدیریت شرط سنی
    $('#has_age_limit').on('change', function() {
        if ($(this).is(':checked')) {
            $('#age_limit_fields').slideDown();
        } else {
            $('#age_limit_fields').slideDown();
        }
    });

    // انتخاب عکس
    $('#upload_image_button').on('click', function(e) {
        e.preventDefault();
        var imageUploader = wp.media({
            title: 'انتخاب عکس رویداد',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            $('#image_url').val(attachment.url);
            if ($('#image_preview').length === 0) {
                $('#image_url').after('<div id="image_preview" style="margin-top: 10px;"><img src="' + attachment.url + '" alt="عکس رویداد" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;"></div>');
            } else {
                $('#image_preview img').attr('src', attachment.url);
            }
        });

        imageUploader.open();
    });

    // تبدیل تاریخ شمسی به میلادی
    $('#start_date_shamsi, #end_date_shamsi').on('change', function() {
        var inputId = $(this).attr('id');
        var shamsiDate = $(this).val();
        
        if (shamsiDate) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                if (inputId === 'start_date_shamsi') {
                    $('#start_date').val(gregorianDate);
                } else if (inputId === 'end_date_shamsi') {
                    $('#end_date').val(gregorianDate);
                }
            }
        } else {
            if (inputId === 'start_date_shamsi') {
                $('#start_date').val('');
            } else if (inputId === 'end_date_shamsi') {
                $('#end_date').val('');
            }
        }
    });
    
    // تبدیل اولیه اگر تاریخ وجود دارد
    if ($('#start_date_shamsi').val()) {
        $('#start_date_shamsi').trigger('change');
    }
    if ($('#end_date_shamsi').val()) {
        $('#end_date_shamsi').trigger('change');
    }
    
    // فرمت کردن قیمت به صورت سه رقم سه رقم (روش مستقیم)
    var $priceInput = $('#price');
    var $priceRaw = $('#price_raw');
    
    $priceInput.on('input', function() {
        var $this = $(this);
        var value = $this.val();
        
        // حذف تمام کاماها و کاراکترهای غیر عددی
        var cleaned = value.replace(/,/g, '').replace(/\D/g, '');
        
        // اگر خالی است
        if (cleaned === '' || cleaned === '0') {
            $this.val('');
            $priceRaw.val('0');
            return;
        }
        
        // فرمت کردن با کاما (سه رقم سه رقم)
        var formatted = parseInt(cleaned, 10).toLocaleString('en-US');
        $this.val(formatted);
        
        // ذخیره مقدار خالص در hidden input
        $priceRaw.val(cleaned);
    });
    
    // هنگام blur
    $priceInput.on('blur', function() {
        var value = $(this).val();
        var cleaned = value.replace(/,/g, '');
        if (cleaned === '' || cleaned === '0') {
            $(this).val('');
            $priceRaw.val('0');
        }
    });
    
    // قبل از submit
    $priceInput.closest('form').on('submit', function() {
        var rawValue = $priceRaw.val() || '0';
        $priceInput.val(rawValue);
    });
    
    // فرمت کردن مقدار اولیه در صورت وجود
    if ($priceInput.val()) {
        $priceInput.trigger('input');
    }
});
</script>

