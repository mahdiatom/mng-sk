<?php
if (!defined('ABSPATH')) exit;

$player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
if ($player_id <= 0) {
    wp_die('شناسه بازیکن معتبر نیست.');
}

$can_view_member = current_user_can('manage_options')
    || (function_exists('sc_user_can_staff_admin_panel') && sc_user_can_staff_admin_panel());
if (!$can_view_member) {
    if (function_exists('sc_secretary_die_access_denied')) {
        sc_secretary_die_access_denied('مشاهده اطلاعات بازیکن');
    }
    wp_die('دسترسی غیرمجاز.');
}

if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_can_access_member') && !sc_secretary_can_access_member($player_id)) {
    if (function_exists('sc_secretary_die_access_denied')) {
        sc_secretary_die_access_denied('مشاهده این بازیکن', 'این بازیکن متعلق به شعبه(های) شما نیست؛ با نقش منشی فقط بازیکنان شعبه خودتان را می‌توانید ببینید.');
    }
    wp_die('شما به این بازیکن دسترسی ندارید.', 'خطای دسترسی', ['response' => 403]);
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
            mc.total_sessions, mc.remaining_sessions, mc.coach_id, mc.chapter,
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

$player_full_name = trim((string) ($player->first_name ?? '') . ' ' . (string) ($player->last_name ?? ''));
$player_initials = '';
if (!empty($player->first_name)) {
    $player_initials .= mb_substr((string) $player->first_name, 0, 1);
}
if (!empty($player->last_name)) {
    $player_initials .= mb_substr((string) $player->last_name, 0, 1);
}
if ($player_initials === '') {
    $player_initials = '؟';
}
$back_url = (function_exists('wc_current_user_has_role') && wc_current_user_has_role('coach'))
    ? admin_url('admin.php?page=sc-coach-my-players')
    : admin_url('admin.php?page=sc-members');

?>
<div class="wrap sc-member-view-wrap">
    <div class="sc-member-view-header">
        <div class="sc-member-view-header-text">
            <h1 class="sc-member-view-title">مشاهده اطلاعات بازیکن</h1>
            <p class="sc-member-view-desc">جزئیات پروفایل، دوره‌ها و لینک‌های کاربردی بازیکن</p>
        </div>
        <div class="sc-member-view-header-actions">
            <a href="<?php echo esc_url($back_url); ?>" class="sc-member-view-back-btn">بازگشت به لیست</a>
            <?php if (!function_exists('wc_current_user_has_role') || !wc_current_user_has_role('coach')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-member&player_id=' . $player_id)); ?>" class="sc-member-view-edit-btn">ویرایش</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sc-member-view-profile-card">
        <div class="sc-member-view-profile-main">
            <?php if (!empty($player->personal_photo)) : ?>
                <span class="sc-member-view-avatar"><img src="<?php echo esc_url($player->personal_photo); ?>" alt=""></span>
            <?php else : ?>
                <span class="sc-member-view-avatar sc-member-view-avatar--initials" aria-hidden="true"><?php echo esc_html($player_initials); ?></span>
            <?php endif; ?>
            <div class="sc-member-view-profile-info">
                <h2 class="sc-member-view-profile-name"><?php echo esc_html($player_full_name !== '' ? $player_full_name : 'بدون نام'); ?></h2>
                <div class="sc-member-view-profile-meta">
                    <?php if (!empty($player->player_phone)) : ?>
                        <span><?php echo esc_html($player->player_phone); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($player->national_id)) : ?>
                        <span><?php echo esc_html($player->national_id); ?></span>
                    <?php endif; ?>
                </div>
                <div class="sc-member-view-profile-badges">
                    <span class="sc-badge <?php echo !empty($player->is_active) ? 'sc-badge--success' : 'sc-badge--muted'; ?>">
                        <?php echo !empty($player->is_active) ? 'فعال' : 'غیرفعال'; ?>
                    </span>
                    <span class="sc-badge <?php echo ($player->member_type ?? '') === 'team' ? 'sc-badge--purple' : 'sc-badge--soft'; ?>">
                        <?php echo ($player->member_type ?? '') === 'team' ? 'بازیکن تیم' : 'بازیکن عادی'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php
    if (function_exists('sc_attendance_qr_render_member_card')) {
        sc_attendance_qr_render_member_card($player_id, 'admin', 420);
    }
    ?>

    <div class="sc-member-view-card info_user_player">
        <h2 class="sc-member-view-section-title">اطلاعات پایه</h2>
        <table class="form-table sc-member-view-table">
            <tbody>
                <?php foreach ($player_builtin_fields as $field_key => $field_meta) : ?>
                    <?php
                    if (in_array($field_key, $player_view_skip_fields, true) || !sc_player_info_is_field_visible($field_key, $player_field_rules)) {
                        continue;
                    }
                    $field_label = $field_meta['label'] ?? $field_key;
                    ?>
                    <tr>
                        <th><?php echo esc_html($field_label); ?></th>
                        <td><?php echo $field_key === 'first_name' || $field_key === 'last_name' ? '<strong>' . sc_player_info_format_builtin_view_value($player, $field_key) . '</strong>' : sc_player_info_format_builtin_view_value($player, $field_key); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'personal', $member_extra_fields); ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'contact', $member_extra_fields); ?>
                <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'documents', $member_extra_fields, ['image']); ?>
                <tr>
                    <th>وضعیت</th>
                    <td>
                        <span class="sc-badge <?php echo !empty($player->is_active) ? 'sc-badge--success' : 'sc-badge--danger'; ?>">
                            <?php echo !empty($player->is_active) ? 'فعال' : 'غیرفعال'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>نوع عضو</th>
                    <td>
                        <span class="sc-badge <?php echo ($player->member_type ?? '') === 'team' ? 'sc-badge--purple' : 'sc-badge--soft'; ?>">
                            <?php echo ($player->member_type ?? '') === 'team' ? 'بازیکن تیم' : 'بازیکن عادی'; ?>
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
                    <td><?php echo !empty($player->disable_auto_invoice) ? '<span class="sc-badge sc-badge--danger">بله</span>' : '<span class="sc-badge sc-badge--success">خیر</span>'; ?></td>
                </tr>
                <tr>
                    <th>سطح بازیکن</th>
                    <td><?php echo esc_html($player->skill_level ?: 'تعیین نشده'); ?></td>
                </tr>
            <?php if (!empty($player->team_player)) : ?>
                <tr>
                    <th>نام تیم</th>
                    <td><?php echo esc_html($player->team_player); ?></td>
                </tr>
            <?php endif; ?>
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
            <h2 class="sc-member-view-section-title">تصاویر</h2>
            <div class="sc-member-view-photos">
                <?php foreach ($visible_photo_fields as $image_field_key => $image_label) : ?>
                    <div class="sc-member-view-photo-item">
                        <strong><?php echo esc_html($image_label); ?></strong>
                        <a href="<?php echo esc_url($player->{$image_field_key}); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url($player->{$image_field_key}); ?>" alt="<?php echo esc_attr($image_label); ?>">
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
                    <div class="sc-member-view-photo-item">
                        <strong><?php echo esc_html($doc_field['label'] ?? $doc_key); ?></strong>
                        <a href="<?php echo esc_url($doc_url); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url($doc_url); ?>" alt="<?php echo esc_attr($doc_field['label'] ?? $doc_key); ?>">
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
            <h2 class="sc-member-view-section-title">توضیحات</h2>
            <table class="form-table sc-member-view-table">
                <tbody>
                    <?php foreach ($player_textarea_fields as $textarea_key) : ?>
                        <?php if (!sc_player_info_is_field_visible($textarea_key, $player_field_rules)) { continue; } ?>
                        <tr>
                            <th><?php echo esc_html($player_builtin_fields[$textarea_key]['label'] ?? $textarea_key); ?></th>
                            <td><?php echo sc_player_info_format_builtin_view_value($player, $textarea_key); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php sc_render_admin_player_custom_fields_view_rows($player_custom_fields, 'additional', $member_extra_fields); ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($player_courses)) : ?>
            <h2 class="sc-member-view-section-title course_active_palyer">دوره‌های بازیکن</h2>
            <div class="sc-member-view-table-scroll">
                <table class="wp-list-table widefat striped sc-member-view-data-table sc-member-view-courses-table">
                    <thead>
                        <tr>
                            <th>نام دوره</th>
                            <th>شعبه</th>
                            <th>مربی</th>
                            <th>قیمت</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت‌نام</th>
                            <th>جلسات دوره</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($player_courses as $pc) :
                            $flags = [];
                            if (!empty($pc->course_status_flags)) {
                                $flags = array_filter(array_map('trim', explode(',', $pc->course_status_flags)));
                            }
                            $status_label = $pc->status === 'active' ? 'فعال' : 'غیرفعال';
                            if (in_array('paused', $flags)) {
                                $status_label .= ' (متوقف شده)';
                            }
                            if (in_array('completed', $flags)) {
                                $status_label .= ' (تمام شده)';
                            }
                            if (in_array('canceled', $flags)) {
                                $status_label .= ' (لغو شده)';
                            }
                            $formatted_price = function_exists('wc_price') ? wc_price($pc->price) : number_format($pc->price, 0, '.', ',') . ' تومان';
                            $chapter_mv = isset($pc->chapter) ? trim((string) $pc->chapter) : '';
                            $coach_name_mv = trim((string) ($pc->coach_first_name ?? '') . ' ' . (string) ($pc->coach_last_name ?? ''));
                            ?>
                            <tr>
                                <td data-label="نام دوره"><strong><?php echo esc_html($pc->title); ?></strong></td>
                                <td data-label="شعبه"><?php
                                    echo $chapter_mv !== ''
                                        ? esc_html($chapter_mv)
                                        : '<span class="sc-member-view-muted">—</span>';
                                ?></td>
                                <td data-label="مربی"><?php
                                    echo $coach_name_mv !== ''
                                        ? esc_html($coach_name_mv)
                                        : '<span class="sc-member-view-muted">—</span>';
                                ?></td>
                                <td data-label="قیمت"><?php echo $formatted_price; ?></td>
                                <td data-label="وضعیت">
                                    <span class="sc-badge <?php echo $pc->status === 'active' ? 'sc-badge--success' : 'sc-badge--danger'; ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td data-label="تاریخ ثبت‌نام"><?php echo esc_html($pc->enrolled_at ? sc_date_shamsi($pc->enrolled_at, 'Y/m/d') : '-'); ?></td>
                                <td data-label="جلسات دوره">
                                    <div class="sc-member-view-sessions">
                                        <div class="total_sessions">
                                            <span class="key">کل جلسات:</span>
                                            <span class="val"><?php echo (int) $pc->total_sessions; ?></span>
                                        </div>
                                        <div class="remaining_sessions">
                                            <span class="key">باقی‌مانده:</span>
                                            <span class="val"><?php echo (int) $pc->remaining_sessions; ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <h2 class="sc-member-view-section-title">دوره‌های بازیکن</h2>
            <p class="sc-member-view-empty">این بازیکن در هیچ دوره‌ای ثبت‌نام نکرده است.</p>
        <?php endif; ?>
        
        <?php
        if (!empty($honors_player)) : ?>
            <h2 class="sc-member-view-section-title course_active_palyer">افتخارات بازیکن</h2>
            <table class="wp-list-table widefat fixed striped sc-member-view-data-table">
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
            <h2 class="sc-member-view-section-title">افتخارات بازیکن</h2>
            <p class="sc-member-view-empty">این بازیکن تا کنون هیچ افتخاری ارسال نکرده است.</p>
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
        <div class="sc-member-view-links">
            <h2 class="sc-member-view-section-title">لینک‌های کاربردی بازیکن</h2>
            <div class="sc-member-view-links-grid">
                <?php foreach ($quick_links as $quick_link) : ?>
                    <a href="<?php echo esc_url($quick_link['url']); ?>" class="sc-member-view-link-card">
                        <div class="sc-member-view-link-card-top">
                            <strong><?php echo esc_html($quick_link['label']); ?></strong>
                            <span><?php echo esc_html($quick_link['icon']); ?></span>
                        </div>
                        <p><?php echo esc_html($quick_link['desc']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
