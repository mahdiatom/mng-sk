<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('فقط مدیر به آمار دسترسی دارد.');
}
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';

$surveys = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_surveys ORDER BY id DESC");
$survey_id = sc_survey_resolve_page_survey_id(isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0, $surveys);
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_players = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

$today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
$filter_request = sc_survey_apply_default_date_filters($_GET);
$filter_date_from_shamsi = $filter_request['filter_date_from_shamsi'] ?? $today_shamsi;
$filter_date_to_shamsi = $filter_request['filter_date_to_shamsi'] ?? $today_shamsi;
$filters = sc_survey_parse_response_filters($filter_request);
$query_filters = $filters;
$stats = $survey_id ? sc_survey_get_question_stats($survey_id, $query_filters) : ['total' => 0, 'questions' => []];
$active_survey = $survey_id ? sc_get_survey($survey_id) : null;

$page_slug = 'sc-survey-stats';
$show_export = false;
$filter_title = 'فیلتر آمار';
$filter_description = 'پیش‌فرض: پاسخ‌های ثبت‌شده در تاریخ امروز. برای بازهٔ دیگر، تاریخ‌ها را تغییر دهید.';

// Professional color palette
$chart_colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

function sc_render_star_rating($value, $max = 5) {
    $value = (int) $value;
    $html = '<div class="sc-star-rating-static">';
    for ($i = 1; $i <= $max; $i++) {
        $class = $i <= $value ? 'filled' : '';
        $html .= '<span class="sc-star ' . $class . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}
?>
<div class="wrap sc-members-list-wrap sc-survey-stats-wrap sc-survey-admin-shell">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">آمار نظرسنجی</h1>
            <p class="sc-members-list-desc">نمودارهای پاسخ‌ها بر اساس فیلترهای انتخاب‌شده. پیش‌فرض: پاسخ‌های امروز.</p>
        </div>
    </div>

    <?php include SC_TEMPLATES_ADMIN_DIR . 'partials/survey-filters.php'; ?>

    <?php if (empty($surveys)) : ?>
        <div class="sc-users-export-card sc-members-list-table-card"><p>هنوز نظرسنجی ثبت نشده است.</p></div>
    <?php elseif ($survey_id) : ?>
        <div class="sc-dashboard-stats sc-survey-stats-summary">
            <div class="sc-stat-box">
                <h3>تعداد پاسخ</h3>
                <div><?php echo (int) $stats['total']; ?></div>
            </div>
            <div class="sc-stat-box">
                <h3>تعداد سوالات</h3>
                <div><?php echo count($stats['questions']); ?></div>
            </div>
        </div>

        <?php if (empty($stats['questions'])) : ?>
            <div class="sc-users-export-card sc-survey-stat-card sc-members-list-table-card">
                <p class="description">این نظرسنجی سوالی ندارد.</p>
            </div>
        <?php else : ?>
            <p class="description" style="margin:0 0 16px;">
                <strong><?php echo esc_html($active_survey ? $active_survey->title : ''); ?></strong>
            </p>

            <div class="sc-survey-stats-grid">
                <?php foreach ($stats['questions'] as $idx => $item) :
                    $q = $item['question'];
                    $chart_id = 'scSurveyChart' . $idx;
                    $is_rating = in_array($q->question_type, ['rating', 'scale'], true);
                    $q_opts = sc_survey_decode_json($q->options_json);
                    $rating_max = isset($q_opts['max']) ? (int) $q_opts['max'] : 5;
                    ?>
                    <div class="sc-survey-stat-card">
                        <div class="sc-survey-stat-header">
                            <h3><?php echo esc_html(wp_strip_all_tags($q->question_text)); ?></h3>
                            <span class="sc-survey-stat-type"><?php echo esc_html(sc_get_survey_question_types()[$q->question_type] ?? $q->question_type); ?></span>
                        </div>

                        <?php if (!empty($item['distribution'])) : ?>
                            <?php if ($is_rating) : ?>
                                <!-- Star rating visual summary -->
                                <div class="sc-rating-summary">
                                    <?php
                                    $max = $rating_max > 0 ? $rating_max : 5;
                                    $total = array_sum($item['distribution']);
                                    foreach ($item['distribution'] as $val => $count) :
                                        $pct = $total > 0 ? round(($count / $total) * 100) : 0;
                                        ?>
                                        <div class="sc-rating-row">
                                            <div class="sc-rating-stars"><?php echo sc_render_star_rating($val, $max); ?></div>
                                            
                                            <div class="sc-rating-count"><?php echo (int) $count; ?> نفر </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="sc-survey-chart-container">
                                    <canvas id="<?php echo esc_attr($chart_id); ?>" height="160"></canvas>
                                </div>
                            <?php endif; ?>

                            <?php if (!$is_rating) : ?>
                            <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                if (typeof Chart === 'undefined') return;
                                var el = document.getElementById('<?php echo esc_js($chart_id); ?>');
                                if (!el) return;

                                new Chart(el, {
                                    type: 'doughnut',
                                    data: {
                                        labels: <?php echo wp_json_encode(array_keys($item['distribution']), JSON_UNESCAPED_UNICODE); ?>,
                                        datasets: [{
                                            label: 'تعداد پاسخ',
                                            data: <?php echo wp_json_encode(array_values($item['distribution'])); ?>,
                                            backgroundColor: <?php echo wp_json_encode(array_slice($chart_colors, 0, count($item['distribution']))); ?>,
                                            borderColor: '<?php echo $chart_colors[0]; ?>',
                                            borderWidth: 2
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: true,
                                                position: 'bottom',
                                                labels: { padding: 15, font: { size: 12 } }
                                            }
                                        }
                                    }
                                });
                            });
                            </script>
                            <?php endif; ?>

                        <?php elseif (!empty($item['text_answers'])) : ?>
                            <div class="sc-survey-text-preview">
                                <p class="description">پاسخ‌های متنی (<?php echo count($item['text_answers']); ?> مورد)</p>
                            </div>
                        <?php else : ?>
                            <div class="sc-survey-no-data">
                                <p>هنوز پاسخی برای این سوال ثبت نشده است.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
