<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';
$edit_page = $is_coach ? 'sc-coach-program-edit' : 'sc-program-edit';
$assign_page = $is_coach ? 'sc-coach-programs-assign' : 'sc-programs-assign';
$library_page = $is_coach ? 'sc-coach-program-library' : 'sc-program-library';
$templates_page = $is_coach ? 'sc-coach-program-templates' : 'sc-program-templates';
$report_page = $is_coach ? 'sc-coach-programs-report' : 'sc-programs-report';

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$member_id = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$page_num = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$ctx = sc_program_staff_context();
if ($ctx['is_coach'] && function_exists('sc_private_notes_get_member_ids_for_coach')) {
    $allowed = sc_private_notes_get_member_ids_for_coach($ctx['coach_id']);
    if (!empty($allowed)) {
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $members_table WHERE id IN ($ph) ORDER BY last_name, first_name",
            ...$allowed
        ));
    } else {
        $members = [];
    }
} elseif ($ctx['is_secretary'] && function_exists('sc_secretary_get_branch_members_for_picker')) {
    $members = sc_secretary_get_branch_members_for_picker();
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name, first_name LIMIT 2000");
}

$selected_member = null;
$selected_label = '';
if ($member_id > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $member_id) {
            $selected_member = $m;
            $selected_label = trim(($m->first_name ?: '') . ' ' . ($m->last_name ?: '')) . ' — ' . ($m->national_id ?: $m->id);
            break;
        }
    }
    if (!$selected_member || !sc_program_can_access_member($member_id)) {
        $member_id = 0;
        $selected_member = null;
    }
}

$rows = [];
$total = 0;
$total_pages = 1;
if ($member_id > 0) {
    $result = sc_program_query_admin([
        'search' => $search,
        'member_id' => $member_id,
        'page' => $page_num,
        'per_page' => 20,
    ]);
    $rows = $result['rows'];
    $total = $result['total'];
    $total_pages = max(1, (int) ceil($total / 20));
}
?>
<div class="wrap sc-members-list-wrap sc-programs-wrap sc-prog-pro">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">برنامه‌های تخصصی اعضا</h1>
            <p class="sc-members-list-desc">ابتدا بازیکن را انتخاب کنید؛ سپس برنامه‌های او را ببینید و ویرایش کنید</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $assign_page)); ?>" class="sc_button sc_button--primary">اختصاص برنامه</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $templates_page)); ?>" class="sc_button">قالب‌ها</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $library_page)); ?>" class="sc_button">کتابخانه</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $report_page)); ?>" class="sc_button">گزارش</a>
        </div>
    </div>

    <?php if (isset($_GET['assigned'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(absint($_GET['assigned']) . ' برنامه با موفقیت اختصاص داده شد.'); ?></p></div>
    <?php endif; ?>

    <div class="sc-members-list-filters-card is-open">
        <form method="get" class="sc-members-list-filters-panel">
            <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">
            <div class="sc-filter-grid">
                <div class="sc-filter-field sc-filter-field--grow">
                    <label class="sc-filter-label">انتخاب بازیکن</label>
                    <div class="sc-prog-member-picker" id="sc-prog-member-picker">
                        <button type="button" class="sc-prog-member-picker__toggle sc-prog-input" id="sc-prog-member-toggle">
                            <?php echo $selected_label !== '' ? esc_html($selected_label) : 'جستجو و انتخاب بازیکن...'; ?>
                        </button>
                        <div class="sc-prog-member-picker__menu" id="sc-prog-member-menu" hidden>
                            <input type="search" class="sc-prog-input" id="sc-prog-member-search" placeholder="نام یا کد ملی...">
                            <div class="sc-prog-member-picker__options">
                                <?php foreach ($members as $member) :
                                    $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                                    $label = $name . ' — ' . ($member->national_id ?: $member->id);
                                    $search_attr = strtolower($name . ' ' . ($member->national_id ?: ''));
                                    ?>
                                    <button type="button"
                                            class="sc-prog-member-option<?php echo ((int) $member->id === $member_id) ? ' is-selected' : ''; ?>"
                                            data-id="<?php echo (int) $member->id; ?>"
                                            data-label="<?php echo esc_attr($label); ?>"
                                            data-search="<?php echo esc_attr($search_attr); ?>">
                                        <?php echo esc_html($label); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" name="filter_member" id="sc-prog-filter-member" value="<?php echo (int) $member_id; ?>">
                    </div>
                </div>
                <?php if ($member_id > 0) : ?>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label">جستجو در برنامه‌ها</label>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" class="sc-filter-control sc-prog-input" placeholder="عنوان برنامه">
                    </div>
                <?php endif; ?>
                <div class="sc-filter-field sc-filter-actions">
                    <button type="submit" class="sc_button sc_button--primary">اعمال</button>
                    <?php if ($member_id > 0) : ?>
                        <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>">پاک کردن</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if ($member_id <= 0) : ?>
        <div class="sc-prog-empty-hero">
            <div class="sc-prog-empty-hero__icon" aria-hidden="true"></div>
            <h3>بازیکنی انتخاب نشده</h3>
            <p>برای مشاهده برنامه‌های تخصصی، ابتدا یک بازیکن را از فیلتر بالا انتخاب کنید.</p>
        </div>
    <?php else : ?>
        <div class="sc-prog-selected-bar">
            <div>
                <strong><?php echo esc_html($selected_label); ?></strong>
                <span class="sc-prog-hint"><?php echo (int) $total; ?> برنامه</span>
            </div>
            <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $assign_page)); ?>">اختصاص برنامه جدید</a>
        </div>

        <div class="sc-prog-card-grid">
            <?php if (empty($rows)) : ?>
                <div class="sc-prog-empty-hero sc-prog-empty-hero--soft">
                    <p>برای این بازیکن برنامه‌ای ثبت نشده است.</p>
                </div>
            <?php else : ?>
                <?php foreach ($rows as $row) :
                    $prog = sc_program_progress((int) $row->id, true);
                    $edit_url = admin_url('admin.php?page=' . $edit_page . '&program_id=' . (int) $row->id);
                    $start_s = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->start_date) : $row->start_date;
                    $end_s = !empty($row->end_date) && function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->end_date) : ($row->end_date ?: '—');
                    ?>
                    <article class="sc-prog-entity-card">
                        <div class="sc-prog-entity-card__accent"></div>
                        <div class="sc-prog-entity-card__body">
                            <div class="sc-prog-entity-card__top">
                                <h3 class="sc-prog-entity-card__title"><?php echo esc_html($row->title); ?></h3>
                                <span class="sc-prog-badge"><?php echo (int) $prog['percent']; ?>٪</span>
                            </div>
                            <p class="sc-prog-entity-card__meta">از <?php echo esc_html($start_s); ?> تا <?php echo esc_html($end_s); ?></p>
                            <div class="sc-program-progress">
                                <div class="sc-program-progress-bar"><span style="width:<?php echo (int) $prog['percent']; ?>%"></span></div>
                                <small><?php echo esc_html($prog['done'] . ' از ' . $prog['total'] . ' تا امروز'); ?></small>
                            </div>
                        </div>
                        <div class="sc-prog-entity-card__foot">
                            <a class="sc_button sc_button--primary" href="<?php echo esc_url($edit_url); ?>">ویرایش</a>
                            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $report_page . '&program_id=' . (int) $row->id)); ?>">گزارش</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page_num,
                    'total' => $total_pages,
                ]);
                ?>
            </div></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
