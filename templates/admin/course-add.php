<?php
$title = '';
$description = '';
$price = '';
$capacity = '';
$start_date = '';
$end_date = '';
$is_active = 1;

if ($course && isset($_GET['course_id'])) {
    $title = $course->title ?? '';
    $description = $course->description ?? '';
    $price = $course->price ?? '';
    $capacity = $course->capacity ?? '';
    $start_date = $course->start_date ?? '';
    $end_date = $course->end_date ?? '';
    $is_active = $course->is_active ?? 1;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo isset($_GET['course_id']) ? 'بروزرسانی دوره' : 'افزودن دوره جدید'; ?>
    </h1>
    <?php 
    if (isset($_GET['course_id'])) {
        ?>
        <a href="<?php echo admin_url('admin.php?page=sc-add-course'); ?>" class="page-title-action">افزودن دوره جدید</a>
        <?php 
    }
    ?>

    <form action="" method="POST">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="title">عنوان دوره <span style="color:red;">*</span></label></th>
                    <td><input name="title" type="text" id="title" value="<?php echo esc_attr($title ?? ''); ?>" class="regular-text" required></td>
                </tr>

                <tr>
                    <th scope="row"><label for="description">توضیحات</label></th>
                    <td>
                        <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea($description ?? ''); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="price">قیمت (تومان) <span style="color:red;">*</span></label></th>
                    <td>
                        <input name="price" type="number" id="price" value="<?php echo esc_attr($price ?? ''); ?>" class="regular-text" step="0.01" min="0" required>
                        <p class="description">مبلغ دوره به تومان</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="capacity">ظرفیت</label></th>
                    <td>
                        <input name="capacity" type="number" id="capacity" value="<?php echo esc_attr($capacity ?? ''); ?>" class="regular-text" min="1">
                        <p class="description">تعداد مجاز ثبت‌نام. در صورت خالی بودن، نامحدود خواهد بود.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="start_date">تاریخ شروع</label></th>
                    <td>
                        <input name="start_date" type="date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>" class="regular-text">
                        <p class="description">برای دوره‌های متناوب می‌توانید خالی بگذارید.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="end_date">تاریخ پایان</label></th>
                    <td>
                        <input name="end_date" type="date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>" class="regular-text">
                        <p class="description">برای دوره‌های متناوب می‌توانید خالی بگذارید.</p>
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
            <button type="submit" name="submit_course" class="button button-primary">
                <?php echo isset($_GET['course_id']) ? 'بروزرسانی دوره' : 'ثبت دوره جدید'; ?>
            </button>
        </p>
    </form>
</div>

