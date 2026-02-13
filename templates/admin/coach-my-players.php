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
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">بازیکن‌های من</h1>
        <p class="sc-coach-panel-desc">بازیکنان ثبت‌نام‌کرده در دوره‌های شما. می‌توانید با کلیک روی «ویرایش» اطلاعات آن‌ها را ویرایش کنید.</p>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="sc-coach-my-players" />
        <?php $coach_players_list_table->search_box('جستجو بازیکن', 'search_coach_player'); ?>
        <div class="sc-coach-panel-card" style="margin-top: 12px;">
            <?php $coach_players_list_table->display(); ?>
        </div>
    </form>
</div>
