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
$coaches_table_mv = $wpdb->prefix . 'sc_coaches';
$honors_table = $wpdb->prefix . 'sc_honors';
$honor_categories_table = $wpdb->prefix . 'sc_honor_categories';

$player = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $player_id));

if (!$player) {
    wp_die('بازیکن یافت نشد.');
}

// دریافت دوره‌های بازیکن
$player_courses = $wpdb->get_results($wpdb->prepare(
    "SELECT c.title, c.price, mc.status, mc.course_status_flags, mc.created_at AS enrolled_at, c.id,
            mc.total_sessions, mc.remaining_sessions, mc.coach_id,
            ch.first_name AS coach_first_name, ch.last_name AS coach_last_name
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON c.id = mc.course_id
     LEFT JOIN $coaches_table_mv ch ON ch.id = mc.coach_id
     WHERE mc.member_id = %d AND mc.status = 'active'
     ORDER BY mc.created_at DESC",
    $player_id
));
// دریافت افتخاراات بازیکن 
$honors_player = $wpdb->get_results($wpdb->prepare(
    "SELECT h.name , hc.name as name_cat  , h.file_url , h.description, h.created_at
     FROM $honors_table h
     INNER JOIN $honor_categories_table hc ON hc.id = h.category_id   
     WHERE member_id = %d 
     ORDER BY h.created_at DESC",
    $player_id
) ,ARRAY_A);

$player_field_rules = function_exists('sc_get_player_info_field_rules') ? sc_get_player_info_field_rules() : [];
$player_custom_fields = function_exists('sc_get_player_info_custom_fields') ? sc_get_player_info_custom_fields() : [];
$player_builtin_fields = function_exists('sc_get_player_info_builtin_fields') ? sc_get_player_info_builtin_fields() : [];
$member_extra_fields = !empty($player->member_extra_fields) ? json_decode((string) $player->member_extra_fields, true) : [];
if (!is_array($member_extra_fields)) {
    $member_extra_fields = [];
}
$player_image_fields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo'];
$player_textarea_fields = ['medical_condition', 'sports_history', 'additional_info'];
$player_view_skip_fields = array_merge($player_image_fields, $player_textarea_fields, ['health_verified', 'info_verified']);

?>
<div class="wrap">
    <h1 class="wp-heading-inline">مشاهده اطلاعات بازیکن</h1>
    <a href="<?php echo (wc_current_user_has_role('coach')) ? esc_url(admin_url('admin.php?page=sc-coach-my-players')) : esc_url(admin_url('admin.php?page=sc-members')); ?>" class="page-title-action">← بازگشت به لیست بازیکنان</a>
    <?php if( !wc_current_user_has_role('coach')){ ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-member&player_id=' . $player_id)); ?>" class="page-title-action">ویرایش</a>
    <?php } ?>
    <hr class="wp-header-end">

    <div class="sc-member-view-card info_user_player" >
                
        <table class="form-table" style="margin-top: 0;">
            <tbody>
                <?php foreach ($player_builtin_fields as $field_key => $field_meta) : ?>
                    <?php
                    if (in_array($field_key, $player_view_skip_fields, true) || !sc_player_info_is_field_visible($field_key, $player_field_rules)) {
                        continue;
                    }
                    $field_label = $field_meta['label'] ?? $field_key;
                    ?>
                    <tr>
                        <th<?php echo $field_key === 'first_name' ? ' style="width: 200px;"' : ''; ?>><?php echo esc_html($field_label); ?></th>
                        <td><?php echo $field_key === 'first_name' || $field_key === 'last_name' ? '<strong>' . sc_player_info_format_builtin_view_value($player, $field_key) . '</strong>' : sc_player_info_format_builtin_view_value($player, $field_key); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'personal', $member_extra_fields); ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'contact', $member_extra_fields); ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'documents', $member_extra_fields, ['image']); ?>
                <tr>
                    <th>وضعیت</th>
                    <td>
                        <span class="sc_status_course_player" style=" <?php echo $player->is_active ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
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
                <?php if (sc_player_info_is_field_visible('info_verified', $player_field_rules)) : ?>
                <tr>
                    <th>اطلاعات تأیید شده</th>
                    <td><?php echo sc_player_info_format_builtin_view_value($player, 'info_verified'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (sc_player_info_is_field_visible('health_verified', $player_field_rules)) : ?>
                <tr>
                    <th>سلامت تأیید شده</th>
                    <td><?php echo sc_player_info_format_builtin_view_value($player, 'health_verified'); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>غیرفعال کردن صورت حساب خودکار</th>
                    <td><?php echo $player->disable_auto_invoice ? '<span style="color: #dc2626;">✓ بله</span>' : '<span style="color: #059669;">✗ خیر</span>'; ?></td>
                </tr>
                <tr>
                    <th>سطح بازیکن</th>
                    <td><?php echo esc_html($player->skill_level ?: 'تعیین نشده'); ?></td>
                </tr>
            <?php
            if($player->team_player){
            ?>
                <tr>
                    <th>نام تیم </th>
                    <td><?php echo esc_html($player->team_player ?: 'تعیین نشده'); ?></td>
                </tr>

                <?php } ?>
            </tbody>
        </table>

        <?php
        $visible_photo_fields = [];
        foreach ($player_image_fields as $image_field_key) {
            if (sc_player_info_is_field_visible($image_field_key, $player_field_rules) && !empty($player->{$image_field_key})) {
                $visible_photo_fields[$image_field_key] = $player_builtin_fields[$image_field_key]['label'] ?? $image_field_key;
            }
        }
        $visible_custom_doc_fields = array_filter($player_custom_fields, function ($field) {
            return is_array($field) && ($field['section'] ?? '') === 'documents' && !empty($field['visible']) && ($field['type'] ?? '') === 'image';
        });
        $has_custom_doc_images = false;
        foreach ($visible_custom_doc_fields as $doc_field) {
            $doc_key = $doc_field['key'] ?? '';
            if ($doc_key !== '' && !empty($member_extra_fields[$doc_key])) {
                $has_custom_doc_images = true;
                break;
            }
        }
        ?>
        <?php if (!empty($visible_photo_fields) || $has_custom_doc_images) : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">تصاویر</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 16px;">
                <?php foreach ($visible_photo_fields as $image_field_key => $image_label) : ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px;"><?php echo esc_html($image_label); ?>:</strong>
                        <a href="<?php echo esc_url($player->{$image_field_key}); ?>" target="_blank">
                            <img src="<?php echo esc_url($player->{$image_field_key}); ?>" alt="<?php echo esc_attr($image_label); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($visible_custom_doc_fields as $doc_field) :
                    $doc_key = $doc_field['key'] ?? '';
                    $doc_url = ($doc_key !== '' && !empty($member_extra_fields[$doc_key])) ? $member_extra_fields[$doc_key] : '';
                    if ($doc_url === '') {
                        continue;
                    }
                    ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px;"><?php echo esc_html($doc_field['label'] ?? $doc_key); ?>:</strong>
                        <a href="<?php echo esc_url($doc_url); ?>" target="_blank">
                            <img src="<?php echo esc_url($doc_url); ?>" alt="<?php echo esc_attr($doc_field['label'] ?? $doc_key); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $has_visible_text_fields = false;
        foreach ($player_textarea_fields as $textarea_key) {
            if (sc_player_info_is_field_visible($textarea_key, $player_field_rules)) {
                $has_visible_text_fields = true;
                break;
            }
        }
        $has_visible_additional_custom = false;
        foreach ($player_custom_fields as $custom_field) {
            if (is_array($custom_field) && ($custom_field['section'] ?? '') === 'additional' && !empty($custom_field['visible'])) {
                $has_visible_additional_custom = true;
                break;
            }
        }
        ?>
        <?php if ($has_visible_text_fields || $has_visible_additional_custom) : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">توضیحات</h2>
            <table class="form-table" style="margin-top: 0;">
                <tbody>
                    <?php foreach ($player_textarea_fields as $textarea_key) : ?>
                        <?php if (!sc_player_info_is_field_visible($textarea_key, $player_field_rules)) { continue; } ?>
                        <tr>
                            <th style="width: 200px;"><?php echo esc_html($player_builtin_fields[$textarea_key]['label'] ?? $textarea_key); ?></th>
                            <td><?php echo sc_player_info_format_builtin_view_value($player, $textarea_key); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'additional', $member_extra_fields); ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($player_courses)) : ?>
            <h2 class="course_active_palyer">دوره‌های بازیکن</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">نام دوره</th>
                        <th style="width: 140px;">مربی</th>
                        <th style="width: 120px;">قیمت</th>
                        <th style="width: 120px;">وضعیت</th>
                        <th style="width: 150px;">تاریخ ثبت‌نام</th>
                        <th style="width: 150px;">جلسات دوره </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($player_courses as $pc) : 
                        $flags = [];
                        if (!empty($pc->course_status_flags)) {
                            $flags = array_filter(array_map('trim', explode(',', $pc->course_status_flags)));
                        }
                        $status_label = $pc->status === 'active' ? 'فعال' : 'غیرفعال';
                        if (in_array('paused', $flags)) $status_label .= ' (متوقف شده)';
                        if (in_array('completed', $flags)) $status_label .= ' (تمام شده)';
                        if (in_array('canceled', $flags)) $status_label .= ' (لغو شده)';
                        $formatted_price = function_exists('wc_price') ? wc_price($pc->price) : number_format($pc->price, 0, '.', ',') . ' تومان';
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($pc->title); ?></strong></td>
                            <td><?php
                                $coach_name_mv = trim((string) ($pc->coach_first_name ?? '') . ' ' . (string) ($pc->coach_last_name ?? ''));
                                echo $coach_name_mv !== ''
                                    ? esc_html($coach_name_mv)
                                    : '<span style="color:#646970;">—</span>';
                            ?></td>
                            <td><?php echo $formatted_price; ?></td>
                            <td>
                                <span class="sc_status_course_player" style="<?php echo $pc->status === 'active' ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($pc->enrolled_at ? sc_date_shamsi($pc->enrolled_at, 'Y/m/d') : '-'); ?></td>
                            <td>
                                <div class="total_sessions">
                                    <span class="key">کل جلسات دوره : </span>
                                    <span class="val"><?php echo (int) $pc->total_sessions; ?></span>
                                </div>
                                <div class="remaining_sessions">
                                    <span class="key">جلسات باقی مانده : </span>
                                    <span class="val"><?php echo (int) $pc->remaining_sessions; ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">دوره‌های بازیکن</h2>
            <p style="margin-top: 16px; color: #646970;">این بازیکن در هیچ دوره‌ای ثبت‌نام نکرده است.</p>
        <?php endif; ?>
        
        <?php
        if (!empty($honors_player)) : ?>
            <h2 class="course_active_palyer">افتخارات بازیکن</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">عنوان</th>
                        <th style="width: 120px;">دسته </th>
                        <th style="width: 120px;">فایل</th>
                        <th style="width: 150px;">توضیحات</th>
                        <th style="width: 150px;">تاریخ ثبت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($honors_player as $h) : 
                      
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($h['name']); ?></strong></td>
                            <td><?php echo esc_html($h['name_cat']); ?></td>
                            <td>
                                  <a  href="<?php echo esc_html($h['file_url']); ?>"> <?php echo esc_html($h['file_url']) ? 'مشاهده' : '-' ?>    </a>
                            </td>
                            <td><?php echo esc_html($h['description'] ? $h['description'] : '-'); ?></td>
                            <td><?php echo esc_html($h['created_at'] ? sc_date_shamsi($h['created_at'], 'Y/m/d') : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <h2 style="margin-top: 32px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">افتخارات بازیکن</h2>
            <p style="margin-top: 16px; color: #646970;">این بازیکن تا کنون هیچ افتخاری ارسال نکرده است.</p>
        <?php endif; ?>

        <?php
        $player_user_id = isset($player->user_id) ? absint($player->user_id) : 0;
        $quick_links = [
            [
                'label' => 'تراکنش های کیف پول',
                'desc'  => 'نمایش همه تراکنش های کیف پول این بازیکن',
                'url'   => admin_url('admin.php?page=sc-wallet&filter_member=' . $player_id),
                'icon'  => '💰',
            ],
            [
                'label' => 'حضور و غیاب کاربر',
                'desc'  => 'لیست حضور و غیاب های ثبت شده برای بازیکن',
                'url'   => admin_url('admin.php?page=sc-attendance-list&tab=individual&filter_member=' . $player_id),
                'icon'  => '📅',
            ],
            [
                'label' => 'تراکنش های کاربر (صورت حساب ها)',
                'desc'  => 'تمام صورت حساب های این بازیکن',
                'url'   => admin_url('admin.php?page=sc-invoices&filter_member=' . $player_id),
                'icon'  => '🧾',
            ],
            [
                'label' => 'خرید اقلام از فروشگاه',
                'desc'  => 'سفارش های ووکامرس بازیکن در فروشگاه',
                'url'   => admin_url('admin.php?page=sc_orders&filter_member=' . $player_id),
                'icon'  => '🛒',
            ],
            [
                'label' => 'رویداد های ثبت نامی کاربر',
                'desc'  => 'ثبت نام های رویداد/مسابقات برای این بازیکن',
                'url'   => admin_url('admin.php?page=sc-event-registrations&filter_member=' . $player_id),
                'icon'  => '🏆',
            ],
            [
                'label' => 'یادداشت های خصوصی کاربر',
                'desc'  => 'یادداشت های خصوصی ثبت شده برای بازیکن',
                'url'   => admin_url('admin.php?page=sc-private-notes&filter_member=' . $player_id),
                'icon'  => '📝',
            ],
            [
                'label' => 'تیکت های کاربر',
                'desc'  => 'همه تیکت های پشتیبانی همین کاربر',
                'url'   => $player_user_id > 0
                    ? admin_url('admin.php?page=sc-support-tickets&filter_user_id=' . $player_user_id)
                    : admin_url('admin.php?page=sc-support-tickets'),
                'icon'  => '🎫',
            ],
            [
                'label' => 'بدهی کاربر',
                'desc'  => 'صورت حساب های بدهکار (در انتظار پرداخت)',
                'url'   => admin_url('admin.php?page=sc-invoices&filter_member=' . $player_id . '&filter_status=pending'),
                'icon'  => '⚠️',
            ],
        ];
        ?>
        <div style="margin-top: 36px;">
            <h2 style="margin: 0 0 14px;">لینک های کاربردی بازیکن</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(245px, 1fr)); gap: 14px;">
                <?php foreach ($quick_links as $quick_link) : ?>
                    <a href="<?php echo esc_url($quick_link['url']); ?>" style="display: block; text-decoration: none; padding: 14px 16px; background: #fff; border: 1px solid #d9e1ea; border-radius: 10px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); transition: all .2s ease;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                            <strong style="color: #1d2327; font-size: 14px;"><?php echo esc_html($quick_link['label']); ?></strong>
                            <span style="font-size: 18px;"><?php echo esc_html($quick_link['icon']); ?></span>
                        </div>
                        <p style="margin: 8px 0 0; color: #4b5563; font-size: 12px; line-height: 1.7;"><?php echo esc_html($quick_link['desc']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
