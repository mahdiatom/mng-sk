<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
$coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}

$coach_players_list_table = isset($GLOBALS['coach_players_list_table']) ? $GLOBALS['coach_players_list_table'] : null;
if (!$coach_players_list_table) {
    require_once SC_TEMPLATES_ADMIN_DIR . 'coach-players-list-table.php';
    $coach_players_list_table = new Coach_Players_List_Table($coach_id);
    $coach_players_list_table->prepare_items();
}

// مقادیر انتخابی فیلترها
$selected_course      = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$selected_status      = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
$selected_profile     = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
$selected_member_type = isset($_GET['filter_member_type']) ? sanitize_text_field($_GET['filter_member_type']) : 'all';
$selected_insurance   = isset($_GET['filter_insurance']) ? sanitize_text_field($_GET['filter_insurance']) : 'all';
$search_value         = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// دوره‌های مربی برای dropdown فیلتر
$coach_courses = method_exists($coach_players_list_table, 'get_coach_courses')
    ? $coach_players_list_table->get_coach_courses()
    : [];

// ساخت URL خروجی اکسل با حفظ فیلترها
$export_url = admin_url('admin.php?page=sc-coach-my-players&sc_export=excel&export_type=members_coach');
if (isset($_GET['player_status']) && $_GET['player_status'] !== 'all') {
    $export_url = add_query_arg('player_status', $_GET['player_status'], $export_url);
}
if ($selected_course > 0) {
    $export_url = add_query_arg('filter_course', $selected_course, $export_url);
}
if ($selected_status !== 'all') {
    $export_url = add_query_arg('filter_status', $selected_status, $export_url);
}
if ($selected_profile !== 'all') {
    $export_url = add_query_arg('filter_profile', $selected_profile, $export_url);
}
if ($selected_member_type !== 'all') {
    $export_url = add_query_arg('filter_member_type', $selected_member_type, $export_url);
}
if ($selected_insurance !== 'all') {
    $export_url = add_query_arg('filter_insurance', $selected_insurance, $export_url);
}
if ($search_value !== '') {
    $export_url = add_query_arg('s', $search_value, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');
?>
<div class="wrap sc-coach-panel-wrap sc-coach-players-wrap">
    <div class="sc-coach-panel-header sc-coach-players-header">
        <h1 class="sc-coach-panel-title">بازیکن‌های من</h1>
    </div>
    <p class="sc-coach-panel-desc" style="margin-top: -4px; margin-bottom: 16px;">
        می‌توانید با کلیک روی «مشاهده» اطلاعات بازیکنان دوره‌های خود را ببینید. امکان حذف یا ویرایش اطلاعات بازیکن برای مربی فعال نیست.
    </p>

    <form method="get" class="sc-coach-players-filter-form">
        <input type="hidden" name="page" value="sc-coach-my-players" />

        <div class="sc-filter-grid">

            <!-- جستجو -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="sc-coach-players-search">جستجو</label>
                <input type="search"
                       id="sc-coach-players-search"
                       name="s"
                       value="<?php echo esc_attr($search_value); ?>"
                       class="sc-filter-control"
                       placeholder="نام، نام خانوادگی، کد ملی یا شماره تماس">
            </div>

            <!-- دوره -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_course">دوره</label>
                <select name="filter_course" id="filter_course" class="sc-filter-control">
                    <option value="0">همه دوره‌ها</option>
                    <?php if (!empty($coach_courses)) : foreach ($coach_courses as $course) : ?>
                        <option value="<?php echo esc_attr($course->id); ?>" <?php selected($selected_course, $course->id); ?>>
                            <?php echo esc_html($course->title); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <!-- وضعیت -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_status">وضعیت بازیکن</label>
                <select name="filter_status" id="filter_status" class="sc-filter-control">
                    <option value="all" <?php selected($selected_status, 'all'); ?>>همه وضعیت‌ها</option>
                    <option value="active" <?php selected($selected_status, 'active'); ?>>فعال</option>
                    <option value="inactive" <?php selected($selected_status, 'inactive'); ?>>غیرفعال</option>
                </select>
            </div>

            <!-- تکمیل پروفایل -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_profile">وضعیت پروفایل</label>
                <select name="filter_profile" id="filter_profile" class="sc-filter-control">
                    <option value="all" <?php selected($selected_profile, 'all'); ?>>همه پروفایل‌ها</option>
                    <option value="completed" <?php selected($selected_profile, 'completed'); ?>>تکمیل شده</option>
                    <option value="incomplete" <?php selected($selected_profile, 'incomplete'); ?>>ناقص</option>
                </select>
            </div>

            <!-- نوع بازیکن -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_member_type">نوع بازیکن</label>
                <select name="filter_member_type" id="filter_member_type" class="sc-filter-control">
                    <option value="all" <?php selected($selected_member_type, 'all'); ?>>همه انواع</option>
                    <option value="normal" <?php selected($selected_member_type, 'normal'); ?>>بازیکن عادی</option>
                    <option value="team" <?php selected($selected_member_type, 'team'); ?>>بازیکن تیم</option>
                </select>
            </div>

            <!-- وضعیت بیمه -->
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_insurance">وضعیت بیمه</label>
                <select name="filter_insurance" id="filter_insurance" class="sc-filter-control">
                    <option value="all" <?php selected($selected_insurance, 'all'); ?>>همه</option>
                    <option value="active" <?php selected($selected_insurance, 'active'); ?>>بیمه فعال</option>
                    <option value="expired" <?php selected($selected_insurance, 'expired'); ?>>بیمه منقضی</option>
                    <option value="none" <?php selected($selected_insurance, 'none'); ?>>بدون بیمه</option>
                </select>
            </div>

        </div>

        <p class="submit sc-coach-players-filter-actions">
            <input type="submit" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-my-players')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            <a href="<?php echo esc_url($export_url); ?>" class="button export_excel_btn button_export">📊 خروجی Excel</a>
        </p>

        <div class="sc-coach-panel-card sc-coach-players-table-card" style="margin-top: 0;">
            <?php $coach_players_list_table->display(); ?>
        </div>
    </form>
</div>
