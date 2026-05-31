<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var string $page_slug @var int $survey_id @var array $filters @var array $surveys @var array $all_players @var array $courses @var bool $show_export @var string $filter_title */
$page_slug = isset($page_slug) ? $page_slug : 'sc-survey-data';
$survey_id = isset($survey_id) ? absint($survey_id) : 0;
$filters = isset($filters) ? $filters : [];
$show_export = !empty($show_export);
$filter_title = isset($filter_title) ? $filter_title : 'فیلتر';
$filter_description = isset($filter_description) ? $filter_description : 'فقط فیلترهای مرتبط با نظرسنجی. در حالت پیش‌فرض همه پاسخ‌ها نمایش داده می‌شوند.';

$filter_player = isset($filters['filter_player']) ? absint($filters['filter_player']) : 0;
$filter_course = isset($filters['filter_course']) ? absint($filters['filter_course']) : 0;
if (!isset($filter_date_from_shamsi)) {
    $filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
}
if (!isset($filter_date_to_shamsi)) {
    $filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
}

$selected_player_text = 'همه پاسخ‌دهندگان';
if ($filter_player > 0 && !empty($all_players)) {
    foreach ($all_players as $p) {
        if ((int) $p->id === $filter_player) {
            $selected_player_text = trim($p->first_name . ' ' . $p->last_name) . ' - ' . $p->national_id;
            break;
        }
    }
}
?>
<form method="get" action="" class=" sc-survey-filter-form">
    <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">

    <div class="sc-users-export-card">
        <h2><?php echo esc_html($filter_title); ?></h2>
        <p class="description"><?php echo esc_html($filter_description); ?></p>

        <div class="sc-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="sc-survey-filter-id">نظرسنجی</label>
                <select name="survey_id" id="sc-survey-filter-id" class="sc-filter-control">
                    <?php if (empty($surveys)) : ?>
                        <option value="">نظرسنجی ثبت نشده</option>
                    <?php else : ?>
                        <?php foreach ($surveys as $s) : ?>
                            <option value="<?php echo esc_attr($s->id); ?>" <?php selected($survey_id, (int) $s->id); ?>><?php echo esc_html($s->title); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label">پاسخ‌دهنده</label>
                <div class="sc-searchable-dropdown">
                    <input type="hidden" name="filter_player" id="filter_player" value="<?php echo esc_attr($filter_player); ?>">
                    <div class="sc-dropdown-toggle">
                        <span class="sc-dropdown-placeholder" <?php if ($filter_player) echo 'style="display:none"'; ?>>همه پاسخ‌دهندگان</span>
                        <span class="sc-dropdown-selected" <?php if (!$filter_player) echo 'style="display:none"'; ?>><?php echo esc_html($selected_player_text); ?></span>
                        <span class="sc-dropdown-arrow">▼</span>
                    </div>
                    <div class="sc-dropdown-menu">
                        <div class="sc-dropdown-search">
                            <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                        </div>
                        <div class="sc-dropdown-options">
                            <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه" onclick="scSelectMemberFilter(this,'0','همه پاسخ‌دهندگان')">همه پاسخ‌دهندگان</div>
                            <?php
                            $display_count = 0;
                            foreach ($all_players as $player) :
                                $display_class = ($display_count < 15) ? 'sc-visible' : 'sc-hidden';
                                $display_count++;
                                ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                     data-value="<?php echo esc_attr($player->id); ?>"
                                     data-search="<?php echo esc_attr(strtolower($player->first_name . ' ' . $player->last_name . ' ' . $player->national_id)); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($player->id); ?>','<?php echo esc_js($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>')">
                                    <?php echo esc_html($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_course">دوره</label>
                <select name="filter_course" id="filter_course" class="sc-filter-control">
                    <option value="0">همه دوره‌ها</option>
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, (int) $course->id); ?>><?php echo esc_html($course->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_date_from_shamsi">از تاریخ پاسخ</label>
                <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi"
                       class="persian-date-input sc-filter-control sc-no-default-date"
                       value="<?php echo esc_attr($filter_date_from_shamsi); ?>" placeholder="از تاریخ" readonly>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_date_to_shamsi">تا تاریخ پاسخ</label>
                <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi"
                       class="persian-date-input sc-filter-control sc-no-default-date"
                       value="<?php echo esc_attr($filter_date_to_shamsi); ?>" placeholder="تا تاریخ" readonly>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <?php if ($survey_id) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug . '&survey_id=' . $survey_id)); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            <?php else : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug)); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            <?php endif; ?>
            <?php if ($show_export && $survey_id) :
                $export_get = isset($filter_request) ? $filter_request : $_GET;
                $export_url = wp_nonce_url(add_query_arg(array_merge($export_get, ['sc_export_survey' => 1]), admin_url('admin.php')), 'sc_export_survey_' . $survey_id);
                $pdf_export_url = wp_nonce_url(add_query_arg(array_merge($export_get, ['sc_export_survey_pdf' => 1]), admin_url('admin.php')), 'sc_export_survey_' . $survey_id);
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button_export">📊 خروجی Excel</a>
                <a href="<?php echo esc_url($pdf_export_url); ?>" class="button button_export sc-survey-pdf-export-btn" target="_blank" rel="noopener">📄 خروجی PDF</a>
            <?php endif; ?>
        </p>
    </div>
</form>
