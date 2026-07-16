<?php
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$programs = function_exists('sc_program_query_for_user') ? sc_program_query_for_user($user_id, ['include_inactive' => true]) : [];
$today = function_exists('sc_program_today_ymd') ? sc_program_today_ymd() : current_time('Y-m-d');
$active_program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : 0;

$active_list = [];
$inactive_list = [];
foreach ($programs as $p) {
    if (($p->status ?? 'active') === 'active') {
        $active_list[] = $p;
    } else {
        $inactive_list[] = $p;
    }
}
if ($active_program_id <= 0 && !empty($active_list)) {
    $active_program_id = (int) $active_list[0]->id;
}
// اگر برنامه غیرفعال انتخاب شده، به اولین فعال برگرد
$active_ok = false;
foreach ($active_list as $p) {
    if ((int) $p->id === $active_program_id) {
        $active_ok = true;
        break;
    }
}
if (!$active_ok && !empty($active_list)) {
    $active_program_id = (int) $active_list[0]->id;
}

$active_program = null;
foreach ($active_list as $p) {
    if ((int) $p->id === $active_program_id) {
        $active_program = $p;
        break;
    }
}
?>
<div class="sc-my-programs-wrap sc-programs-wrap sc-prog-pro" data-today="<?php echo esc_attr($today); ?>">
    <header class="sc-my-programs-hero">
        <h2 class="sc-my-programs-title">برنامه‌های تخصصی من</h2>
        <p class="sc-my-programs-desc">برنامه را انتخاب کنید، روزها را مرور کنید و فقط تمرین‌های امروز را تیک بزنید.</p>
    </header>

    <?php if (empty($programs)) : ?>
        <div class="sc-prog-empty-hero">
            <div class="sc-prog-empty-hero__icon" aria-hidden="true"></div>
            <h3>هنوز برنامه‌ای ندارید</h3>
            <p>به‌محض اختصاص برنامه تخصصی از سمت مربی، اینجا نمایش داده می‌شود.</p>
        </div>
    <?php else : ?>
        <div class="sc-my-program-selector" role="tablist" aria-label="انتخاب برنامه">
            <?php foreach ($active_list as $p) :
                $prog = sc_program_progress((int) $p->id, true);
                ?>
                <button type="button"
                        class="sc-my-program-pill<?php echo (int) $p->id === $active_program_id ? ' is-active' : ''; ?>"
                        data-program="<?php echo (int) $p->id; ?>"
                        data-selectable="1"
                        aria-selected="<?php echo (int) $p->id === $active_program_id ? 'true' : 'false'; ?>">
                    <span class="sc-my-program-pill__title"><?php echo esc_html($p->title); ?></span>
                    <span class="sc-my-program-pill__meta"><?php echo (int) $prog['percent']; ?>٪ تکمیل</span>
                </button>
            <?php endforeach; ?>
            <?php foreach ($inactive_list as $p) : ?>
                <button type="button"
                        class="sc-my-program-pill is-disabled"
                        data-program="<?php echo (int) $p->id; ?>"
                        data-selectable="0"
                        aria-disabled="true"
                        title="این برنامه غیرفعال است">
                    <span class="sc-my-program-pill__title"><?php echo esc_html($p->title); ?></span>
                    <span class="sc-my-program-pill__meta">غیرفعال</span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="sc-my-program-stage" id="sc-my-program-stage">
            <?php if ($active_program) : ?>
                <?php echo sc_program_player_render_program_card($active_program, $today); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php elseif (!empty($inactive_list) && empty($active_list)) : ?>
                <div class="sc-prog-empty-hero sc-prog-empty-hero--soft">
                    <p>همه برنامه‌های شما فعلاً غیرفعال هستند. با مربی هماهنگ کنید.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
