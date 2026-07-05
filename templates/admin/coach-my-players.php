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

$selected_course      = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$selected_status      = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
$selected_profile     = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
$selected_member_type = isset($_GET['filter_member_type']) ? sanitize_text_field($_GET['filter_member_type']) : 'all';
$selected_insurance   = isset($_GET['filter_insurance']) ? sanitize_text_field($_GET['filter_insurance']) : 'all';
$search_value         = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$coach_courses = method_exists($coach_players_list_table, 'get_coach_courses')
    ? $coach_players_list_table->get_coach_courses()
    : [];

$active_filters_count = 0;
if ($selected_course > 0) {
    $active_filters_count++;
}
if ($selected_status !== 'all') {
    $active_filters_count++;
}
if ($selected_profile !== 'all') {
    $active_filters_count++;
}
if ($selected_member_type !== 'all') {
    $active_filters_count++;
}
if ($selected_insurance !== 'all') {
    $active_filters_count++;
}
if ($search_value !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

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
<div class="wrap sc-members-list-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">بازیکن‌های من</h1>
            <p class="sc-members-list-desc">می‌توانید با کلیک روی «مشاهده» اطلاعات بازیکنان دوره‌های خود را ببینید. امکان حذف یا ویرایش اطلاعات بازیکن برای مربی فعال نیست.</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-members-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-members-list-filters-toggle"
                    id="sc-coach-players-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-coach-players-filters-panel">
                <span class="sc-members-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-members-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-members-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-my-players')); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-members-list-filters-panel" id="sc-coach-players-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coach-my-players">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="sc-coach-players-search">جستجو</label>
                    <input type="search"
                           id="sc-coach-players-search"
                           name="s"
                           value="<?php echo esc_attr($search_value); ?>"
                           class="sc-filter-control"
                           placeholder="نام، نام خانوادگی، کد ملی یا شماره تماس">
                </div>

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

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت بازیکن</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($selected_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="active" <?php selected($selected_status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($selected_status, 'inactive'); ?>>غیرفعال</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_profile">وضعیت پروفایل</label>
                    <select name="filter_profile" id="filter_profile" class="sc-filter-control">
                        <option value="all" <?php selected($selected_profile, 'all'); ?>>همه پروفایل‌ها</option>
                        <option value="completed" <?php selected($selected_profile, 'completed'); ?>>تکمیل شده</option>
                        <option value="incomplete" <?php selected($selected_profile, 'incomplete'); ?>>ناقص</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_member_type">نوع بازیکن</label>
                    <select name="filter_member_type" id="filter_member_type" class="sc-filter-control">
                        <option value="all" <?php selected($selected_member_type, 'all'); ?>>همه انواع</option>
                        <option value="normal" <?php selected($selected_member_type, 'normal'); ?>>بازیکن عادی</option>
                        <option value="team" <?php selected($selected_member_type, 'team'); ?>>بازیکن تیم</option>
                    </select>
                </div>

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

            <div class="sc-members-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-my-players')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-members-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc-coach-my-players">
            <?php
            $preserve = [
                'filter_course'      => $selected_course,
                'filter_status'      => $selected_status,
                'filter_profile'     => $selected_profile,
                'filter_member_type' => $selected_member_type,
                'filter_insurance'   => $selected_insurance,
            ];
            foreach ($preserve as $fk => $fv) {
                if ($fk === 'filter_course' && (int) $fv <= 0) {
                    continue;
                }
                if (in_array($fk, ['filter_status', 'filter_profile', 'filter_member_type', 'filter_insurance'], true) && ($fv === 'all' || $fv === '')) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($fk) . '" value="' . esc_attr((string) $fv) . '" />';
            }
            if ($search_value !== '') {
                echo '<input type="hidden" name="s" value="' . esc_attr($search_value) . '" />';
            }
            if (!empty($_GET['player_status']) && $_GET['player_status'] !== 'all') {
                echo '<input type="hidden" name="player_status" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['player_status']))) . '" />';
            }
            $coach_players_list_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-coach-players-filters-toggle');
    var $panel = $('#sc-coach-players-filters-panel');
    var $card = $toggle.closest('.sc-members-list-filters-card');
    var $label = $toggle.find('.sc-members-list-filters-toggle-label');

    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });
});
</script>
