<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

$player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
if ($player_id <= 0) {
    wp_die('شناسه بازیکن معتبر نیست.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'sc_members';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$courses_table = $wpdb->prefix . 'sc_courses';

$player = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $player_id));

if (!$player) {
    wp_die('بازیکن یافت نشد.');
}

// دریافت دوره‌های بازیکن
$player_courses = $wpdb->get_results($wpdb->prepare(
    "SELECT c.title, c.price, mc.status, mc.course_status_flags, mc.created_at as enrolled_at
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON c.id = mc.course_id
     WHERE mc.member_id = %d
     ORDER BY mc.created_at DESC",
    $player_id
));

?>
<div class="wrap">
    <h1 class="wp-heading-inline">مشاهده اطلاعات بازیکن</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-members')); ?>" class="page-title-action">← بازگشت به لیست بازیکنان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-member&player_id=' . $player_id)); ?>" class="page-title-action">ویرایش</a>
    <hr class="wp-header-end">

    <div class="sc-member-view-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 24px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        
        <h2 style="margin-top: 0; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">اطلاعات شخصی</h2>
        
        <table class="form-table" style="margin-top: 0;">
            <tbody>
                <tr>
                    <th style="width: 200px;">نام</th>
                    <td><strong><?php echo esc_html($player->first_name); ?></strong></td>
                </tr>
                <tr>
                    <th>نام خانوادگی</th>
                    <td><strong><?php echo esc_html($player->last_name); ?></strong></td>
                </tr>
                <tr>
                    <th>نام پدر</th>
                    <td><?php echo esc_html($player->father_name ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>کد ملی</th>
                    <td><?php echo esc_html($player->national_id); ?></td>
                </tr>
                <tr>
                    <th>تلفن بازیکن</th>
                    <td><?php echo esc_html($player->player_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تلفن پدر</th>
                    <td><?php echo esc_html($player->father_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تلفن مادر</th>
                    <td><?php echo esc_html($player->mother_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تلفن ثابت</th>
                    <td><?php echo esc_html($player->landline_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تاریخ تولد (شمسی)</th>
                    <td><?php echo esc_html($player->birth_date_shamsi ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تاریخ تولد (میلادی)</th>
                    <td><?php echo esc_html($player->birth_date_gregorian ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>تاریخ انقضای بیمه (شمسی)</th>
                    <td><?php echo esc_html($player->insurance_expiry_date_shamsi ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>وضعیت</th>
                    <td>
                        <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: 600; <?php echo $player->is_active ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                            <?php echo $player->is_active ? 'فعال' : 'غیرفعال'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>نوع عضو</th>
                    <td>
                        <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: #dbeafe; color: #1e40af; font-weight: 600;">
                            <?php echo $player->member_type === 'team' ? 'بازیکن تیم' : 'بازیکن عادی'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>اطلاعات تأیید شده</th>
                    <td><?php echo $player->info_verified ? '<span style="color: #059669;">✓ بله</span>' : '<span style="color: #dc2626;">✗ خیر</span>'; ?></td>
                </tr>
                <tr>
                    <th>سلامت تأیید شده</th>
                    <td><?php echo $player->health_verified ? '<span style="color: #059669;">✓ بله</span>' : '<span style="color: #dc2626;">✗ خیر</span>'; ?></td>
                </tr>
                <tr>
                    <th>غیرفعال کردن صورت حساب خودکار</th>
                    <td><?php echo $player->disable_auto_invoice ? '<span style="color: #dc2626;">✓ بله</span>' : '<span style="color: #059669;">✗ خیر</span>'; ?></td>
                </tr>
                <tr>
                    <th>سطح بازیکن</th>
                    <td><?php echo esc_html($player->skill_level ?: '-'); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($player->personal_photo || $player->id_card_photo || $player->sport_insurance_photo) : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">تصاویر</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 16px;">
                <?php if ($player->personal_photo) : ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px;">عکس شخصی:</strong>
                        <a href="<?php echo esc_url($player->personal_photo); ?>" target="_blank">
                            <img src="<?php echo esc_url($player->personal_photo); ?>" alt="عکس شخصی" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($player->id_card_photo) : ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px;">عکس کارت ملی:</strong>
                        <a href="<?php echo esc_url($player->id_card_photo); ?>" target="_blank">
                            <img src="<?php echo esc_url($player->id_card_photo); ?>" alt="عکس کارت ملی" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($player->sport_insurance_photo) : ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px;">عکس بیمه ورزشی:</strong>
                        <a href="<?php echo esc_url($player->sport_insurance_photo); ?>" target="_blank">
                            <img src="<?php echo esc_url($player->sport_insurance_photo); ?>" alt="عکس بیمه ورزشی" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($player->medical_condition || $player->sports_history || $player->additional_info) : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">توضیحات</h2>
            <table class="form-table" style="margin-top: 0;">
                <tbody>
                    <?php if ($player->medical_condition) : ?>
                        <tr>
                            <th style="width: 200px;">وضعیت پزشکی</th>
                            <td><?php echo nl2br(esc_html($player->medical_condition)); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($player->sports_history) : ?>
                        <tr>
                            <th>سوابق ورزشی</th>
                            <td><?php echo nl2br(esc_html($player->sports_history)); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($player->additional_info) : ?>
                        <tr>
                            <th>توضیحات اضافی</th>
                            <td><?php echo nl2br(esc_html($player->additional_info)); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($player_courses)) : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">دوره‌های بازیکن</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
                <thead>
                    <tr>
                        <th>نام دوره</th>
                        <th style="width: 120px;">قیمت</th>
                        <th style="width: 120px;">وضعیت</th>
                        <th style="width: 150px;">تاریخ ثبت‌نام</th>
                    </tr>
                </thead>
                <tbody>
                    
                    <?php foreach ($player_courses as $pc) : 
                        $flags = [];
                        if (!empty($pc->course_status_flags)) {
                            $flags = array_filter(array_map('trim', explode(',', $pc->course_status_flags)));
                        }
                        $status_label = $pc->status === 'active' ? 'فعال' : 'غیرفعال';
                        if (in_array('paused', $flags)) $status_label = ' (متوقف شده)';
                        if (in_array('completed', $flags)) $status_label = ' (تمام شده)';
                        if (in_array('canceled', $flags)) $status_label = ' (لغو شده)';
                        $formatted_price = function_exists('wc_price') ? wc_price($pc->price) : number_format($pc->price, 0, '.', ',') . ' تومان';
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($pc->title); ?></strong></td>
                            <td><?php echo $formatted_price; ?></td>
                            <td>
                                <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: 600; <?php echo $pc->status === 'active' ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($pc->enrolled_at ? sc_date_shamsi($pc->enrolled_at, 'Y/m/d') : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">دوره‌های بازیکن</h2>
            <p style="margin-top: 16px; color: #646970;">این بازیکن در هیچ دوره‌ای ثبت‌نام نکرده است.</p>
        <?php endif; ?>

    </div>
</div>
