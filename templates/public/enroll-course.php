<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// دریافت متغیرهای فیلتر و صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'latest');
$chapter = isset($chapter) ? $chapter : (isset($_GET['chapter']) ? sanitize_text_field(wp_unslash($_GET['chapter'])) : 'all');
$current_page = isset($current_page) ? $current_page : (isset($_GET['pag']) ? absint($_GET['pag']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_courses = isset($total_courses) ? $total_courses : 0;
$course_search = isset($course_search) ? $course_search : (isset($_GET['course_search']) ? sanitize_text_field(wp_unslash($_GET['course_search'])) : '');
$filter_coach = isset($filter_coach) ? (int) $filter_coach : (isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0);
$enroll_filter_coaches = isset($enroll_filter_coaches) ? (array) $enroll_filter_coaches : [];
$capacity_waitlist_pending = isset($capacity_waitlist_pending) ? (array) $capacity_waitlist_pending : [];


// استفاده از تنظیمات WooCommerce برای فرمت قیمت
$decimal_places = 0;
$decimal_separator = '.';
$thousand_separator = ',';

global $wpdb;
$chapter_table = $wpdb->prefix . 'sc_chapter_categories';
$chapters = $wpdb->get_results(
                    "SELECT * FROM $chapter_table ORDER BY id ASC"
                );

if (function_exists('wc_get_price_decimals')) {
    $decimal_places = wc_get_price_decimals();
}
if (function_exists('wc_get_price_decimal_separator')) {
    $decimal_separator = wc_get_price_decimal_separator();
}
if (function_exists('wc_get_price_thousand_separator')) {
    $thousand_separator = wc_get_price_thousand_separator();
}
$sc_waitlist_ajax_nonce = wp_create_nonce('sc_course_capacity_waitlist');
?>

<div class="sc-enroll-course-page sc-account-list-page">
    <div class="sc-invoices-page-header sc-enroll-page-header">
        <div class="sc-invoices-page-icon">📚</div>
        <div>
            <h2 class="sc-invoices-page-title">ثبت‌نام در دوره</h2>
            <p class="sc-invoices-page-subtitle">دوره مورد نظر را انتخاب کنید، شعبه و مربی را مشخص کنید و ثبت‌نام را تکمیل نمایید</p>
        </div>
    </div>
    
    <div class="sc-invoices-filters sc-enroll-course-filters sc-enroll-filters-panel">
        <div class="sc-enroll-panel-title sc-enroll-filters-title">فیلتر دوره‌ها</div>
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-enroll-course')); ?>" class="sc-invoices-filter-form sc-enroll-filters-form">
            <input type="hidden" name="pag" value="1">
            
            <div class="sc-invoices-filter-field sc-enroll-filter-field">
                <label for="filter_status" class="sc-enroll-field-label">وضعیت</label>
                <select name="filter_status" id="filter_status" class="sc-invoices-filter-control sc-enroll-select">
                    <option value="latest" <?php selected($filter_status, 'latest'); ?>>آخرین دوره‌ها</option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>>دوره‌های ثبت نام شده</option>
                    <option value="paused" <?php selected($filter_status, 'paused'); ?>>دوره‌های متوقف شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>دوره‌های به اتمام رسیده</option>
                    <option value="canceled" <?php selected($filter_status, 'canceled'); ?>>دوره‌های لغو شده</option>
                    <option value="expired" <?php selected($filter_status, 'expired'); ?>>مهلت ثبت نام تمام شده</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه دوره‌ها</option>
                </select>
            </div>
            <div class="sc-invoices-filter-field sc-enroll-filter-field">
                <label for="chapter" class="sc-enroll-field-label">شعبه</label>
                <select name="chapter" id="chapter" class="sc-invoices-filter-control sc-enroll-select">
                    <option value="all" <?php selected($chapter, 'all'); ?>>همه شعبه‌ها</option>
                    <?php foreach ($chapters as $ch) : ?>
                        <option value="<?php echo esc_attr($ch->name); ?>" <?php selected($chapter, $ch->name); ?>><?php echo esc_html($ch->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($enroll_filter_coaches)) : ?>
            <div class="sc-invoices-filter-field sc-enroll-filter-field">
                <label for="filter_coach" class="sc-enroll-field-label">مربی</label>
                <select name="filter_coach" id="filter_coach" class="sc-invoices-filter-control sc-enroll-select">
                    <option value="0" <?php selected($filter_coach, 0); ?>>همه مربی‌ها</option>
                    <?php foreach ($enroll_filter_coaches as $fc) :
                        $fc_label = trim((string) $fc->first_name . ' ' . (string) $fc->last_name);
                        if ($fc_label === '') {
                            $fc_label = 'مربی #' . (int) $fc->id;
                        }
                        ?>
                        <option value="<?php echo (int) $fc->id; ?>" <?php selected($filter_coach, (int) $fc->id); ?>><?php echo esc_html($fc_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="sc-invoices-filter-field sc-enroll-filter-field sc-enroll-filter-field--search">
                <label for="course_search" class="sc-enroll-field-label">جستجو</label>
                <input type="search" name="course_search" id="course_search" class="sc-invoices-filter-control sc-enroll-select sc-enroll-search-input" value="<?php echo esc_attr($course_search); ?>" placeholder="نام یا توضیح دوره...">
            </div>
            
            <div class="sc-invoices-filter-actions sc-enroll-filter-submit">
                <button type="submit" class="button button-primary sc-invoices-filter-submit sc-enroll-filter-btn">اعمال فیلتر</button>
            </div>
        </form>
    </div>
    
    <?php if (empty($courses)) : ?>
        <div class="sc-invoices-empty">
            <?php if ($filter_status === 'latest') : ?>
                در حال حاضر دوره‌ای برای ثبت نام موجود نیست.
            <?php elseif ($filter_status === 'all') : ?>
                در حال حاضر دوره‌ای موجود نیست.
            <?php elseif (!empty($course_search)) : ?>
                دوره‌ای با این جستجو یافت نشد.
            <?php else : ?>
                دوره‌ای با این وضعیت یافت نشد.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <form method="POST" action="" class="sc-enroll-course-form sc-enroll-course-form-packages">
        <?php wp_nonce_field('sc_enroll_course', 'sc_enroll_course_nonce'); ?>
        <input type="hidden" name="enrollment_sessions" id="sc-enrollment-sessions-field" value="">
        
        <div class="sc-invoices-list sc-enroll-courses-list">
            <?php foreach ($courses as $index => $course) : 
                $is_enrolled = isset($enrolled_courses_data[$course->id]);
                $course_status = null;
                $status_label = '';
                $status_color = '';
                $status_bg = '';
                $tooltip_message = '';
                
                if ($is_enrolled) {
                    $course_data = $enrolled_courses_data[$course->id];
                    if ($course_data['is_under_review']) {
                        $course_status = 'under_review';
                        $status_label = 'در انتظار بررسی';
                        $status_color = '#856404';
                        $status_bg = '#fff3cd';
                        $tooltip_message = 'شما برای این دوره ثبت‌نام کرده‌اید و صورت حساب آن در حال بررسی است. پس از تایید مدیر و تبدیل به پرداخت شده، دوره فعال خواهد شد.';
                    } elseif ($course_data['is_pending_payment']) {
                        $course_status = 'pending_payment';
                        $status_label = 'در انتظار پرداخت';
                        $status_color = '#856404';
                        $status_bg = '#fff3cd';
                        $tooltip_message = 'شما برای این دوره ثبت‌نام کرده‌اید و صورت حساب آن در انتظار پرداخت است. لطفاً به بخش صورت حساب‌ها مراجعه کنید.';
                    } elseif ($course_data['is_canceled']) {
                        $course_status = 'canceled';
                        $status_label = 'لغو شده';
                        $status_color = '#d63638';
                        $status_bg = '#ffeaea';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت لغو شده است. در صورتی که نیاز به ثبت نام و فعال شدن این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } elseif ($course_data['is_completed']) {
                        $course_status = 'completed';
                        $status_label = 'تمام شده';
                        $status_color = '#666';
                        $status_bg = '#f5f5f5';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت تمام شده است. در صورتی که نیاز به ثبت نام مجدد در این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } elseif ($course_data['is_paused']) {
                        $course_status = 'paused';
                        $status_label = 'متوقف شده';
                        $status_color = '#f0a000';
                        $status_bg = '#fff8e1';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت متوقف شده است. در صورتی که نیاز به فعال شدن مجدد این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } else {
                        $course_status = 'active';
                        $status_label = 'ثبت‌نام شده';
                        $status_color = '#00a32a';
                        $status_bg = '#d4edda';
                        $tooltip_message = 'تبریک شما اکنون در این دوره ثبت نام کردید و عضو کاربران فعال هستید';
                    }
                }
                
                $course_packages = function_exists('sc_get_course_packages') ? sc_get_course_packages($course->id) : [];
                $has_course_packages = !empty($course_packages);
                $formatted_price = '';
                if ($has_course_packages) {
                    $prices_only = array_map(function ($pkg_row) {
                        return (float) $pkg_row->price;
                    }, $course_packages);
                    $min_price = !empty($prices_only) ? min($prices_only) : 0;
                    if (function_exists('wc_price')) {
                        $formatted_price = 'پکیج‌ها - از ' . wc_price($min_price);
                    } else {
                        $formatted_price = 'پکیج‌ها - از ' . number_format($min_price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    }
                } elseif (function_exists('wc_price')) {
                    $formatted_price = wc_price($course->price);
                } else {
                    $formatted_price = number_format((float)$course->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }
                
                // محاسبه ظرفیت (شعبه/مربی یا legacy)
                $capacity_info = function_exists('sc_get_course_enrollment_capacity_info')
                    ? sc_get_course_enrollment_capacity_info((int) $course->id)
                    : ['show' => !empty($course->capacity), 'remaining' => 0, 'is_full' => false, 'unlimited' => empty($course->capacity)];
                $remaining = isset($capacity_info['remaining']) ? (int) $capacity_info['remaining'] : 0;
                $is_capacity_full = !empty($capacity_info['is_full']);
                $show_capacity = !empty($capacity_info['show']);
                
                $branch_cfg = isset($enroll_branch_configs[(int) $course->id]) ? $enroll_branch_configs[(int) $course->id] : null;
                $is_date_expired = false;
                $today_shamsi = sc_get_today_shamsi();
                
                // اگر تاریخ شروع و پایان وجود داشته باشد
                if (!empty($course->start_date) || !empty($course->end_date)) {
                    $start_date_shamsi = !empty($course->start_date) ? sc_date_shamsi_date_only($course->start_date) : '';
                    $end_date_shamsi = !empty($course->end_date) ? sc_date_shamsi_date_only($course->end_date) : '';
                    
                    // اگر تاریخ پایان وارد شده باشد و تاریخ امروز بعد از تاریخ پایان باشد
                    if (!empty($end_date_shamsi)) {
                        if (sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
                            $is_date_expired = true;
                        }
                    }
                    
                    // اگر تاریخ شروع وارد شده باشد و تاریخ امروز قبل از تاریخ شروع باشد
                    if (!empty($start_date_shamsi) && !$is_date_expired) {
                        if (sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
                            $is_date_expired = true;
                        }
                    }
                }
                
                // اگر ظرفیت تکمیل شده باشد، برچسب و tooltip اضافه می‌کنیم
                if ($is_capacity_full && !$is_enrolled) {
                    $status_label = 'ظرفیت تکمیل شده';
                    $status_color = '#d63638';
                    $status_bg = '#ffeaea';
                    $tooltip_message = 'ظرفیت دوره تکمیل شده است برای  ثبت نام در این دوره با مدیر باشگاه ارتباط بگیرید.';
                    $course_status = 'capacity_full';
                }
                
                // اگر تاریخ تمام شده باشد، برچسب و tooltip اضافه می‌کنیم
                if ($is_date_expired && !$is_enrolled) {
                    $status_label = 'زمان ثبت‌نام تمام شده';
                    $status_color = '#d63638';
                    $status_bg = '#ffeaea';
                    $tooltip_message = 'زمان ثبت نام این دوره تمام شده است.';
                    $course_status = 'date_expired';
                }

                $waitlist_subscribed = in_array((int) $course->id, array_map('intval', $capacity_waitlist_pending), true);
            ?>
                <article class="sc-account-card sc-enroll-course-card sc-course-accordion-item" id="course_item_<?php echo esc_attr($course->id); ?>" data-course-id="<?php echo esc_attr($course->id); ?>" data-has-packages="<?php echo $has_course_packages ? '1' : '0'; ?>">
    <div class="sc-enroll-course-card-head sc_radio_detailes_courses">
        <input type="radio" 
               name="course_id" 
               id="course_<?php echo esc_attr($course->id); ?>" 
               value="<?php echo esc_attr($course->id); ?>" 
               class="sc-course-radio"
               <?php echo ($is_enrolled || $is_capacity_full || $is_date_expired) ? 'disabled' : ''; ?>
               required>
        
        <label for="course_<?php echo esc_attr($course->id); ?>" 
               class="sc-course-accordion-header" 
               >
            
            <div class="sc-course-header-content">
                <div class="sc-course-title">
                    <strong><?php echo esc_html($course->title); ?></strong>
                </div>
                
                <div class="sc-course-meta">
                    <?php if ($status_label) : ?>
                        <span class="sc-course-status <?php echo esc_attr($course_status); ?>"><?php echo esc_html($status_label); ?></span>
                    <?php endif; ?>
                    <span class="sc-course-price"><?php echo $formatted_price; ?></span>
                    
                    <?php if ($has_course_packages) : ?>
                        <span class="sc-course-sessions"><strong>نوع قیمت:</strong> پکیج جلسه‌ای</span>
                    <?php elseif ($course->sessions_count) : ?>
                        <span class="sc-course-sessions"><strong>تعداد جلسات:</strong> <?php echo esc_html($course->sessions_count); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($show_capacity) : ?>
                        <span class="sc-course-capacity <?php echo ($is_capacity_full && !$is_enrolled) ? 'full' : ''; ?>">
                            <strong>ظرفیت باقی مانده:</strong> <?php echo esc_html($remaining); ?> نفر
                        </span>
                    <?php endif; ?>
                    
                    
                </div>
                <?php if ($tooltip_message) : ?>
                <div class="detiles_course">
                  <?php echo esc_attr($tooltip_message); ?>
                </div>
                 <?php endif; ?>
            </div>
        </label>
    </div>

    <?php if ($is_capacity_full && !$is_enrolled && !$is_date_expired && $show_capacity) : ?>
        <div class="sc-course-capacity-waitlist-outer" style="margin:0 0 10px;padding:12px 16px;background:#f0f6fc;border:1px solid #c3d9e8;border-radius:6px;">
            <button type="button"
                    class="sc_button sc-course-waitlist-btn"
                    data-course-id="<?php echo esc_attr((string) (int) $course->id); ?>"
                    <?php disabled($waitlist_subscribed); ?>>
                <?php echo $waitlist_subscribed ? esc_html('درخواست شما ثبت شده است') : esc_html('اطلاع‌رسانی در صورت خالی شدن ظرفیت'); ?>
            </button>
            <span class="sc-course-waitlist-msg" style="margin-right:12px;font-size:14px;" aria-live="polite"></span>
        </div>
    <?php endif; ?>

    <div class="sc-course-accordion-content">
        <?php if ($course->description) : ?>
            <p><?php echo nl2br(esc_html($course->description)); ?></p>
        <?php else : ?>
            <p class="sc-no-description">توضیحاتی برای این دوره ثبت نشده است.</p>
        <?php endif; ?>
        <?php if ($has_course_packages && !$is_enrolled && !$is_capacity_full && !$is_date_expired) : ?>
            <div class="sc-course-packages-enroll sc-enroll-panel">
                <div class="sc-enroll-panel-title">انتخاب پکیج</div>
                <div class="sc-enroll-pkg-options">
                    <?php foreach ($course_packages as $pkg) : ?>
                        <label class="sc-enroll-pkg-option">
                            <input type="radio"
                                   class="sc-enroll-pkg-radio"
                                   name="sc_pkg_course_<?php echo esc_attr($course->id); ?>"
                                   value="<?php echo esc_attr((int) $pkg->sessions_count); ?>"
                                   data-course-id="<?php echo esc_attr($course->id); ?>">
                            <span class="sc-enroll-pkg-sessions"><?php echo esc_html((int) $pkg->sessions_count); ?> جلسه</span>
                            <span class="sc-enroll-pkg-price"><?php echo function_exists('wc_price') ? wp_kses_post(wc_price((float) $pkg->price)) : esc_html(number_format((float) $pkg->price, 0, '.', ',')) . ' تومان'; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="sc-enroll-pkg-live-price" data-course-id="<?php echo esc_attr($course->id); ?>"></div>
            </div>
        <?php endif; ?>
        <?php
        $show_branch_ui = !$is_enrolled && !$is_capacity_full && !$is_date_expired
            && !empty($branch_cfg['chapters']);
        if ($show_branch_ui) :
        ?>
            <div class="sc-enroll-branch-coach-inner sc-enroll-panel" data-course-id="<?php echo esc_attr($course->id); ?>" style="display:none;">
                <div class="sc-enroll-panel-title">انتخاب شعبه و مربی</div>
                <div class="sc-enroll-fields">
                    <div class="sc-enroll-chapter-wrap sc-enroll-field-wrap"></div>
                    <div class="sc-enroll-coach-wrap sc-enroll-field-wrap"></div>
                </div>
                <div class="sc-enroll-schedule-wrap"></div>
                <div class="sc-enroll-checkout-anchor"></div>
            </div>
        <?php endif; ?>
        <?php if ($show_branch_ui === false && !$is_enrolled && !$is_capacity_full && !$is_date_expired) : ?>
            <div class="sc-enroll-checkout-anchor sc-enroll-checkout-anchor--standalone"></div>
        <?php endif; ?>
    </div>
</article>
            <?php endforeach; ?>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php 
        $per_page = isset($per_page) ? (int) $per_page : 10;
        if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin: 20px 10px 50px 0px;">
                <div class="tablenav-pages">
                <?php
                $pagination_add = [];
                if ($filter_status !== 'latest') {
                    $pagination_add['filter_status'] = $filter_status;
                }
                if ($chapter !== 'all') {
                    $pagination_add['chapter'] = $chapter;
                }
                if ($filter_coach > 0) {
                    $pagination_add['filter_coach'] = $filter_coach;
                }
                if ($course_search !== '') {
                    $pagination_add['course_search'] = $course_search;
                }

                $page_links = paginate_links([
                    'base' => add_query_arg(['pag' => '%#%']),
                    'format' => '',
                    'add_args' => $pagination_add,
                    'prev_text' => '< قبلی ',
                    'next_text' => ' بعدی >',
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                
                if ($page_links) {
                    echo $page_links;
                }
                ?>
                </div>
            </div>
        <?php endif; ?>

        <input type="hidden" name="enrollment_chapter" id="sc-enrollment-chapter-field" value="">
        <input type="hidden" name="enrollment_coach_id" id="sc-enrollment-coach-field" value="0">

        <div id="sc-enroll-global-checkout" class="sc-enroll-checkout-panel sc-enroll-panel" hidden>
            <div class="sc-enroll-panel-title sc-enroll-checkout-title">تکمیل ثبت‌نام</div>
            <div class="sc-enroll-discount-row">
                <label for="sc_invoice_discount_code" class="sc-enroll-field-label">کد تخفیف (اختیاری)</label>
                <div class="sc-enroll-discount-actions">
                    <input type="text" name="sc_invoice_discount_code" id="sc_invoice_discount_code" class="sc-invoices-filter-control sc-enroll-discount-input" autocomplete="off" placeholder="مثال: SUMMER1404">
                    <button type="button" class="button sc-account-btn-compact sc-enroll-discount-preview-btn" id="sc-preview-discount-course"><?php esc_html_e('بررسی کد', 'sportclub-manager'); ?></button>
                </div>
                <p class="sc-enroll-discount-hint">پس از انتخاب دوره و شعبه/مربی، می‌توانید کد را بررسی کنید.</p>
                <div id="sc-discount-preview-course" class="sc-enroll-discount-preview" aria-live="polite"></div>
            </div>
            <div class="sc-enroll-submit-row">
                <button type="submit" name="sc_enroll_course" class="button button-primary sc-enroll-submit-btn sc-account-btn-compact">
                    ثبت نام و ایجاد صورت حساب
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php if (!empty($enroll_branch_configs)) : ?>
<script type="application/json" id="sc-enroll-branch-configs"><?php echo wp_json_encode($enroll_branch_configs, JSON_UNESCAPED_UNICODE); ?></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // اسکرول به عنوان فقط وقتی کاربر هنوز نزدیک بالای صفحه است؛
    // در غیر این صورت با تأخیر، scrollIntoView با اولین تعامل (مثلاً انتخاب دوره) هم‌زمان می‌شود و «پرش به بالا» ایجاد می‌کند.
    (function scEnrollScrollTitleIfNeeded() {
        var tries = 0;
        var maxTries = 40;
        var interval = setInterval(function () {
            tries++;
            var el = document.querySelector('.sc-enroll-page-header');
            if (el) {
                var y = window.scrollY || document.documentElement.scrollTop || 0;
                if (y < 120) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                clearInterval(interval);
            } else if (tries >= maxTries) {
                clearInterval(interval);
            }
        }, 100);
    })();

    const form = document.querySelector('.sc-enroll-course-form-packages');
    const checkoutPanel = document.getElementById('sc-enroll-global-checkout');
    const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    const waitlistNonce = '<?php echo esc_js($sc_waitlist_ajax_nonce); ?>';

    document.querySelectorAll('.sc-course-waitlist-btn').forEach(function (btn) {
        if (btn.disabled) {
            return;
        }
        btn.addEventListener('click', function () {
            const cid = parseInt(btn.getAttribute('data-course-id') || '0', 10);
            const wrap = btn.closest('.sc-course-capacity-waitlist-outer');
            const msg = wrap ? wrap.querySelector('.sc-course-waitlist-msg') : null;
            if (!cid) {
                return;
            }
            btn.disabled = true;
            if (msg) {
                msg.textContent = 'در حال ثبت...';
            }
            const p = new URLSearchParams();
            p.append('action', 'sc_course_capacity_waitlist_subscribe');
            p.append('nonce', waitlistNonce);
            p.append('course_id', String(cid));
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: p.toString()
            }).then(function (r) { return r.json(); }).then(function (json) {
                if (json && json.success) {
                    if (msg) {
                        msg.textContent = (json.data && json.data.message) ? json.data.message : 'ثبت شد.';
                    }
                    btn.textContent = 'درخواست شما ثبت شده است';
                } else {
                    btn.disabled = false;
                    if (msg) {
                        msg.textContent = (json && json.data && json.data.message) ? json.data.message : 'خطا';
                    }
                }
            }).catch(function () {
                btn.disabled = false;
                if (msg) {
                    msg.textContent = 'خطا در ارتباط با سرور';
                }
            });
        });
    });

    if (!form) {
        return;
    }
    const selectedSessionsInput = document.getElementById('sc-enrollment-sessions-field');
    const nonce = '<?php echo esc_js(wp_create_nonce('sc_enroll_package')); ?>';
    const discountNonce = '<?php echo esc_js(wp_create_nonce('sc_discount_preview')); ?>';
    let selectedCourseId = 0;
    let branchConfigs = {};
    try {
        var cfgNode = document.getElementById('sc-enroll-branch-configs');
        if (cfgNode) {
            branchConfigs = JSON.parse(cfgNode.textContent || '{}');
        }
    } catch (err) {
        branchConfigs = {};
    }

    function isEnrollCheckoutReady(courseId) {
        if (!courseId) {
            return false;
        }
        var courseItem = document.getElementById('course_item_' + courseId);
        var courseRadio = document.getElementById('course_' + courseId);
        if (!courseItem || !courseRadio || courseRadio.disabled) {
            return false;
        }
        if (courseItem.getAttribute('data-has-packages') === '1') {
            var checkedPkg = form.querySelector('input[name="sc_pkg_course_' + courseId + '"]:checked');
            if (!checkedPkg) {
                return false;
            }
        }
        var cfg = branchConfigs[courseId];
        if (cfg && cfg.chapters && cfg.chapters.length) {
            var chField = document.getElementById('sc-enrollment-chapter-field');
            if (!chField || !chField.value) {
                return false;
            }
        }
        return true;
    }

    function updateEnrollCheckoutPanel() {
        if (!checkoutPanel || !form) {
            return;
        }
        var checkedCourse = form.querySelector('input[name="course_id"]:checked');
        if (!checkedCourse) {
            checkoutPanel.hidden = true;
            return;
        }
        var courseId = parseInt(checkedCourse.value || '0', 10);
        if (!isEnrollCheckoutReady(courseId)) {
            checkoutPanel.hidden = true;
            return;
        }
        var courseItem = document.getElementById('course_item_' + courseId);
        if (!courseItem) {
            checkoutPanel.hidden = true;
            return;
        }
        var anchor = courseItem.querySelector('.sc-enroll-branch-coach-inner .sc-enroll-checkout-anchor')
            || courseItem.querySelector('.sc-enroll-checkout-anchor');
        if (!anchor) {
            checkoutPanel.hidden = true;
            return;
        }
        anchor.appendChild(checkoutPanel);
        checkoutPanel.hidden = false;
    }

    function renderEnrollBranchCoach(courseId) {
        document.querySelectorAll('.sc-enroll-branch-coach-inner').forEach(function (el) {
            el.style.display = 'none';
            var chWrap = el.querySelector('.sc-enroll-chapter-wrap');
            var coWrap = el.querySelector('.sc-enroll-coach-wrap');
            var schWrap = el.querySelector('.sc-enroll-schedule-wrap');
            if (chWrap) {
                chWrap.innerHTML = '';
            }
            if (coWrap) {
                coWrap.innerHTML = '';
            }
            if (schWrap) {
                schWrap.innerHTML = '';
            }
        });

        var chField = document.getElementById('sc-enrollment-chapter-field');
        var coField = document.getElementById('sc-enrollment-coach-field');
        if (chField) {
            chField.value = '';
        }
        if (coField) {
            coField.value = '0';
        }

        var courseItem = document.getElementById('course_item_' + courseId);
        if (!courseItem) {
            updateEnrollCheckoutPanel();
            return;
        }
        var panel = courseItem.querySelector('.sc-enroll-branch-coach-inner');
        if (!panel) {
            updateEnrollCheckoutPanel();
            return;
        }
        var chWrap = panel.querySelector('.sc-enroll-chapter-wrap');
        var coWrap = panel.querySelector('.sc-enroll-coach-wrap');
        var schWrap = panel.querySelector('.sc-enroll-schedule-wrap');
        if (!chWrap || !coWrap || !chField || !coField) {
            updateEnrollCheckoutPanel();
            return;
        }

        var cfg = branchConfigs[courseId];
        if (!cfg || !cfg.chapters || !cfg.chapters.length) {
            updateEnrollCheckoutPanel();
            return;
        }
        panel.style.display = 'block';

        function coachLabelById(coachId) {
            var label = '';
            cfg.chapters.forEach(function (ch) {
                (ch.coaches || []).forEach(function (c) {
                    if (parseInt(c.id, 10) === coachId) {
                        label = c.label;
                    }
                });
            });
            return label;
        }

        function renderSchedule(chapterName) {
            if (!schWrap) {
                return;
            }
            schWrap.innerHTML = '';
            if (!chapterName) {
                return;
            }
            var coachId = parseInt(coField.value || '0', 10);
            var rows = (cfg.schedule || []).filter(function (r) {
                var rowCoach = parseInt(r.coach_id || 0, 10);
                var chapterOk = !r.chapter || r.chapter === chapterName;
                var coachOk = !rowCoach || !coachId || rowCoach === coachId;
                return chapterOk && coachOk;
            });
            if (!rows.length) {
                return;
            }
            var html = '<div class="sc-enroll-schedule-title">برنامه هفتگی این انتخاب</div><ul class="sc-enroll-schedule-list">';
            rows.forEach(function (r) {
                var coachName = parseInt(r.coach_id || 0, 10) ? coachLabelById(parseInt(r.coach_id, 10)) : '';
                html += '<li>' + r.day + ' ' + r.start + ' تا ' + r.end + (coachName ? ' — ' + coachName : '') + '</li>';
            });
            html += '</ul>';
            schWrap.innerHTML = html;
        }

        function renderCoach(chapterName, selectedCoachId) {
            renderCoachInner(chapterName, selectedCoachId);
            renderSchedule(chapterName);
        }

        function renderCoachInner(chapterName, selectedCoachId) {
            coWrap.innerHTML = '';
            coField.value = '0';
            if (!chapterName) {
                return;
            }
            var coaches = [];
            cfg.chapters.forEach(function (ch) {
                if (ch.name === chapterName) {
                    coaches = ch.coaches || [];
                }
            });
            if (!coaches.length) {
                coWrap.innerHTML = '<div class="sc-enroll-coach-note">مربی برای این شعبه تعریف نشده — ثبت‌نام بدون مربی انجام می‌شود.</div>';
                updateEnrollCheckoutPanel();
                return;
            }
            if (coaches.length === 1) {
                coWrap.innerHTML = '<div class="sc-enroll-static-field"><span class="sc-enroll-field-label">مربی:</span><span class="sc-enroll-field-value">' + coaches[0].label + '</span></div>';
                coField.value = String(coaches[0].id);
                updateEnrollCheckoutPanel();
                return;
            }
            var html = '<label class="sc-enroll-select-field"><span class="sc-enroll-field-label">مربی</span><select class="sc-enroll-select sc-enroll-coach-select"><option value="0">انتخاب مربی (اختیاری)</option>';
            coaches.forEach(function (c) {
                html += '<option value="' + c.id + '"' + (selectedCoachId === c.id ? ' selected' : '') + '>' + c.label + '</option>';
            });
            html += '</select></label>';
            coWrap.innerHTML = html;
            var sel = coWrap.querySelector('.sc-enroll-coach-select');
            if (sel) {
                coField.value = sel.value || '0';
                sel.addEventListener('change', function () {
                    coField.value = sel.value || '0';
                    renderSchedule(chapterName);
                    updateEnrollCheckoutPanel();
                });
            }
            updateEnrollCheckoutPanel();
        }

        if (cfg.chapters.length === 1) {
            chWrap.innerHTML = '<div class="sc-enroll-static-field"><span class="sc-enroll-field-label">شعبه:</span><span class="sc-enroll-field-value">' + cfg.chapters[0].name + '</span></div>';
            chField.value = cfg.chapters[0].name;
            renderCoach(cfg.chapters[0].name, 0);
            updateEnrollCheckoutPanel();
            return;
        }

        var chHtml = '<label class="sc-enroll-select-field"><span class="sc-enroll-field-label">شعبه</span><select class="sc-enroll-select sc-enroll-chapter-select"><option value="">انتخاب شعبه</option>';
        cfg.chapters.forEach(function (ch) {
            chHtml += '<option value="' + ch.name + '">' + ch.name + '</option>';
        });
        chHtml += '</select></label>';
        chWrap.innerHTML = chHtml;
        var chSel = chWrap.querySelector('.sc-enroll-chapter-select');
        if (chSel) {
            chSel.addEventListener('change', function () {
                chField.value = chSel.value || '';
                renderCoach(chSel.value || '', 0);
                updateEnrollCheckoutPanel();
            });
        }
        updateEnrollCheckoutPanel();
    }

    document.querySelectorAll('.sc-course-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            selectedCourseId = parseInt(this.value || '0', 10);
            renderEnrollBranchCoach(selectedCourseId);
            if (selectedSessionsInput) {
                selectedSessionsInput.value = '';
            }
            updateEnrollCheckoutPanel();
        });
    });

    document.querySelectorAll('.sc-enroll-pkg-radio').forEach(function (pkgRadio) {
        pkgRadio.addEventListener('change', function () {
            const courseId = parseInt(this.getAttribute('data-course-id') || '0', 10);
            const sessions = parseInt(this.value || '0', 10);
            const courseRadio = document.getElementById('course_' + courseId);
            if (courseRadio && !courseRadio.disabled) {
                courseRadio.checked = true;
                selectedCourseId = courseId;
                renderEnrollBranchCoach(courseId);
            }
            if (selectedSessionsInput) {
                selectedSessionsInput.value = String(sessions);
            }

            const live = document.querySelector('.sc-enroll-pkg-live-price[data-course-id="' + courseId + '"]');
            if (!live || !courseId || !sessions) {
                return;
            }
            live.textContent = 'در حال محاسبه...';
            const params = new URLSearchParams();
            params.append('action', 'sc_enroll_course_package_preview');
            params.append('nonce', nonce);
            params.append('course_id', String(courseId));
            params.append('sessions', String(sessions));

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (res) {
                return res.json();
            }).then(function (json) {
                if (json && json.success && json.data) {
                    live.innerHTML = '<strong>مبلغ انتخابی:</strong> ' + json.data.amount_html;
                } else {
                    live.textContent = 'خطا در دریافت قیمت پکیج';
                }
            }).catch(function () {
                live.textContent = 'خطا در ارتباط با سرور';
            });
            updateEnrollCheckoutPanel();
        });
    });

    form.addEventListener('submit', function (e) {
        const checkedCourse = form.querySelector('input[name="course_id"]:checked');
        if (!checkedCourse) {
            return;
        }
        const courseId = checkedCourse.value;
        const courseItem = document.getElementById('course_item_' + courseId);
        const hasPackages = courseItem && courseItem.getAttribute('data-has-packages') === '1';
        if (hasPackages) {
            const checkedPkg = form.querySelector('input[name="sc_pkg_course_' + courseId + '"]:checked');
            if (selectedSessionsInput) {
                selectedSessionsInput.value = checkedPkg ? checkedPkg.value : '';
            }
        }

        var cfg = branchConfigs[courseId];
        if (cfg && cfg.chapters && cfg.chapters.length > 1) {
            var chVal = document.getElementById('sc-enrollment-chapter-field');
            if (!chVal || !chVal.value) {
                e.preventDefault();
                alert('لطفاً شعبه دوره را انتخاب کنید.');
                return false;
            }
        }
        return true;
    });

    const previewBtn = document.getElementById('sc-preview-discount-course');
    const previewBox = document.getElementById('sc-discount-preview-course');
    const discountInput = document.getElementById('sc_invoice_discount_code');
    if (previewBtn && previewBox && discountInput) {
        previewBtn.addEventListener('click', function () {
            const checkedCourse = form.querySelector('input[name="course_id"]:checked');
            if (!checkedCourse) {
                previewBox.innerHTML = '<span style="color:#d63638;">ابتدا یک دوره انتخاب کنید.</span>';
                return;
            }
            const courseId = parseInt(checkedCourse.value || '0', 10);
            const courseItem = document.getElementById('course_item_' + courseId);
            const hasPackages = courseItem && courseItem.getAttribute('data-has-packages') === '1';
            let sessions = 0;
            if (hasPackages) {
                const checkedPkg = form.querySelector('input[name="sc_pkg_course_' + courseId + '"]:checked');
                sessions = checkedPkg ? parseInt(checkedPkg.value || '0', 10) : 0;
            }
            const code = (discountInput.value || '').trim();
            const chapterField = document.getElementById('sc-enrollment-chapter-field');
            const enrollmentChapter = chapterField ? (chapterField.value || '') : '';
            previewBox.textContent = 'در حال بررسی...';
            const params = new URLSearchParams();
            params.append('action', 'sc_preview_sc_discount_course');
            params.append('nonce', discountNonce);
            params.append('course_id', String(courseId));
            params.append('enrollment_sessions', String(sessions));
            params.append('enrollment_chapter', enrollmentChapter);
            params.append('code', code);
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (res) { return res.json(); }).then(function (json) {
                if (json && json.success && json.data) {
                    previewBox.innerHTML = json.data.summary_html || '';
                } else {
                    previewBox.innerHTML = '<span style="color:#d63638;">' + (json && json.data && json.data.message ? json.data.message : 'کد نامعتبر است.') + '</span>';
                }
            }).catch(function () {
                previewBox.textContent = 'خطا در ارتباط با سرور';
            });
        });
    }
});
</script>