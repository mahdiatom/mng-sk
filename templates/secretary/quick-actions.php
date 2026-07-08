<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_user_can_quick_actions') || !sc_user_can_quick_actions()) {
    wp_die('دسترسی ندارید.', 'خطای دسترسی', ['response' => 403]);
}

$is_branch_scoped = function_exists('sc_quick_actions_is_branch_scoped') && sc_quick_actions_is_branch_scoped();
$chapters = function_exists('sc_quick_actions_get_chapters') ? sc_quick_actions_get_chapters() : [];
$filter_chapter = function_exists('sc_secretary_get_filter_chapter') ? sc_secretary_get_filter_chapter() : 'all';
$default_chapter = ($filter_chapter !== 'all') ? $filter_chapter : (isset($chapters[0]) ? $chapters[0] : '');
$courses = function_exists('sc_secretary_get_quick_action_courses')
    ? sc_secretary_get_quick_action_courses($default_chapter)
    : (function_exists('sc_secretary_get_branch_courses') ? sc_secretary_get_branch_courses() : []);

$sounds = [];
if (function_exists('sc_attendance_qr_get_sound_url')) {
    foreach (['success', 'error', 'debt_warning'] as $sound_type) {
        $sounds[$sound_type] = sc_attendance_qr_get_sound_url($sound_type);
    }
}

wp_enqueue_script(
    'sc-secretary-quick-actions',
    SC_ASSETS_URL . 'js/secretary-quick-actions.js',
    ['jquery'],
    defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0',
    true
);
wp_localize_script('sc-secretary-quick-actions', 'scSecretaryQuick', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('sc_secretary_quick_actions'),
    'chapters' => $chapters,
    'defaultChapter' => $default_chapter,
    'sounds' => $sounds,
    'labels' => [
        'check' => 'بررسی اطلاعات',
        'confirm' => 'تایید و ثبت در دوره',
    ],
]);
?>
<div class="wrap sc-members-list-wrap sc-secretary-quick-actions-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">اقدامات سریع</h1>
            <p class="sc-members-list-desc"><?php if ($is_branch_scoped) : ?>ثبت‌نام بازیکن در دوره و صدور صورت‌حساب برای شعبه(های) مجاز شما. در افزودن به دوره، همه بازیکنان سایت قابل جستجو هستند؛ فقط دوره‌هایی با برنامه هفتگی فعال برای شعبه نمایش داده می‌شوند.<?php else : ?>ثبت‌نام بازیکن در دوره و صدور صورت‌حساب — دسترسی به همه شعب و دوره‌های فعال.<?php endif; ?></p>
        </div>
    </div>

    <nav class="nav-tab-wrapper sc-secretary-qa-tabs">
        <a href="#sc-qa-existing" class="nav-tab nav-tab-active" data-tab="existing">افزودن بازیکن موجود به دوره</a>
        <a href="#sc-qa-new" class="nav-tab" data-tab="new">ثبت‌نام بازیکن جدید + دوره</a>
    </nav>

    <div id="sc-qa-existing" class="sc-secretary-qa-panel sc-members-list-table-card">
        <div class="sc-secretary-qa-panel-head">
            <h2>افزودن بازیکن موجود به دوره</h2>
            <p>نام، موبایل یا کد ملی را جستجو کنید و بازیکن را از لیست انتخاب کنید، سپس شعبه و دوره را مشخص کنید.</p>
        </div>
        <table class="form-table sc-secretary-qa-form-table">
            <tr>
                <th scope="row"><label for="sc_qa_member_search">جستجوی بازیکن</label></th>
                <td>
                    <input type="text" id="sc_qa_member_search" class="regular-text sc-secretary-qa-control" placeholder="نام، موبایل یا کد ملی را تایپ کنید..." autocomplete="off" />
                    <input type="hidden" id="sc_qa_member_id" value="" />
                    <p class="description">حداقل ۱ کاراکتر تایپ کنید؛ سپس روی نام بازیکن در لیست کلیک کنید.</p>
                    <div id="sc_qa_member_selected" class="sc-qa-member-selected" style="display:none;"></div>
                    <div id="sc_qa_member_results" class="sc-qa-search-results"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_existing_chapter">شعبه</label></th>
                <td>
                    <select id="sc_qa_existing_chapter" class="sc-secretary-qa-control">
                        <?php foreach ($chapters as $ch) : ?>
                            <option value="<?php echo esc_attr($ch); ?>" <?php selected($default_chapter, $ch); ?>><?php echo esc_html($ch); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo $is_branch_scoped ? 'با تغییر شعبه، لیست دوره‌ها بر اساس برنامه هفتگی همان شعبه به‌روز می‌شود.' : 'با تغییر شعبه، لیست دوره‌های فعال همان شعبه به‌روز می‌شود.'; ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_existing_course">دوره</label></th>
                <td>
                    <select id="sc_qa_existing_course" class="sc-secretary-qa-control">
                        <option value="">— انتخاب دوره —</option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_existing_payment">وضعیت پرداخت</label></th>
                <td>
                    <select id="sc_qa_existing_payment" class="sc-secretary-qa-control">
                        <option value="pending">در انتظار پرداخت</option>
                        <option value="processing">پرداخت شده</option>
                        <option value="completed">تایید پرداخت</option>
                    </select>
                </td>
            </tr>
        </table>
        <div class="sc-secretary-qa-actions">
            <button type="button" class="button button-primary sc-qa-submit" id="sc_qa_existing_submit">بررسی اطلاعات</button>
            <p class="description sc-qa-submit-hint">ابتدا «بررسی اطلاعات» را بزنید؛ پس از تأیید، دکمه به «تایید و ثبت در دوره» تغییر می‌کند.</p>
        </div>
        <div id="sc_qa_existing_validation" class="sc-qa-validation" aria-live="polite" style="display:none;"></div>
        <div id="sc_qa_existing_message" class="sc-qa-message" aria-live="polite"></div>
    </div>

    <div id="sc-qa-new" class="sc-secretary-qa-panel sc-members-list-table-card" style="display:none;">
        <div class="sc-secretary-qa-panel-head">
            <h2>ثبت‌نام بازیکن جدید + دوره</h2>
            <p>بازیکن جدید را با موبایل ثبت کنید و هم‌زمان در دوره شعبه خود فعال کنید.</p>
        </div>
        <table class="form-table sc-secretary-qa-form-table">
            <tr>
                <th scope="row"><label for="sc_qa_new_mobile">موبایل</label></th>
                <td><input type="text" id="sc_qa_new_mobile" class="regular-text sc-secretary-qa-control" placeholder="09xxxxxxxxx" maxlength="11" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_new_first">نام</label></th>
                <td><input type="text" id="sc_qa_new_first" class="regular-text sc-secretary-qa-control" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_new_last">نام خانوادگی</label></th>
                <td><input type="text" id="sc_qa_new_last" class="regular-text sc-secretary-qa-control" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_new_chapter">شعبه</label></th>
                <td>
                    <select id="sc_qa_new_chapter" class="sc-secretary-qa-control">
                        <?php foreach ($chapters as $ch) : ?>
                            <option value="<?php echo esc_attr($ch); ?>" <?php selected($default_chapter, $ch); ?>><?php echo esc_html($ch); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_new_course">دوره</label></th>
                <td>
                    <select id="sc_qa_new_course" class="sc-secretary-qa-control">
                        <option value="">— انتخاب دوره —</option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_qa_new_payment">وضعیت پرداخت</label></th>
                <td>
                    <select id="sc_qa_new_payment" class="sc-secretary-qa-control">
                        <option value="pending">در انتظار پرداخت</option>
                        <option value="processing">پرداخت شده</option>
                        <option value="completed">تایید پرداخت</option>
                    </select>
                </td>
            </tr>
        </table>
        <div class="sc-secretary-qa-actions">
            <button type="button" class="button button-primary sc-qa-submit" id="sc_qa_new_submit">بررسی اطلاعات</button>
            <p class="description sc-qa-submit-hint">ابتدا بررسی کنید، سپس در صورت موفقیت ثبت نهایی را تأیید کنید.</p>
        </div>
        <div id="sc_qa_new_validation" class="sc-qa-validation" aria-live="polite" style="display:none;"></div>
        <div id="sc_qa_new_message" class="sc-qa-message" aria-live="polite"></div>
    </div>
</div>
<style>
body[class*="sc-secretary-quick-actions"] .sc-secretary-quick-actions-wrap,
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-quick-actions-wrap {
    margin-block: 16px 24px;
    margin-inline-end: 20px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-tabs {
    margin: 0 0 18px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-panel.sc-members-list-table-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    padding: 20px 22px 18px;
    margin: 0 0 18px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-panel-head h2 {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 800;
    color: #1e1b2e;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-panel-head p {
    margin: 0 0 16px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.7;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-form-table th {
    width: 160px;
    padding: 12px 0;
    font-weight: 700;
    color: #374151;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-form-table td {
    padding: 12px 0;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-control {
    min-width: 280px;
    max-width: 100%;
    border-radius: 10px !important;
    border-color: #d1d5db !important;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-secretary-qa-actions {
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-submit-hint {
    margin: 8px 0 0;
    color: #6b7280;
    font-size: 12px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-submit.is-checking {
    opacity: 0.85;
    cursor: wait;
    position: relative;
    padding-right: 28px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-submit.is-checking::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    width: 14px;
    height: 14px;
    margin-top: -7px;
    border: 2px solid rgba(255,255,255,0.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: sc-qa-spin 0.7s linear infinite;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-submit.is-ready {
    background: #047857 !important;
    border-color: #047857 !important;
    box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.15);
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-submit.is-error-flash {
    background: #b32d2e !important;
    border-color: #b32d2e !important;
}
@keyframes sc-qa-spin {
    to { transform: rotate(360deg); }
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-results {
    position: relative;
    max-width: 420px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-results ul {
    list-style: none;
    margin: 6px 0 0;
    padding: 0;
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 12px;
    max-height: 220px;
    overflow: auto;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-results li {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-results li:hover {
    background: #f8fafc;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-hint,
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-empty {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 13px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-search-error {
    margin: 6px 0 0;
    color: #b32d2e;
    font-weight: 600;
    font-size: 13px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-member-selected {
    margin-top: 8px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-selected-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    color: #065f46;
    font-size: 13px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-clear-member {
    border: none;
    background: transparent;
    color: #047857;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 0 2px;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item {
    padding: 6px 0 6px 22px;
    position: relative;
    font-size: 13px;
    line-height: 1.6;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item::before {
    content: '•';
    position: absolute;
    right: 0;
    font-weight: 700;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item--success { color: #047857; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item--error { color: #b32d2e; font-weight: 700; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item--warning { color: #b45309; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-validation-item--info { color: #1d4ed8; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-message {
    margin-top: 12px;
    font-weight: 600;
}
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-message.is-error { color: #b32d2e; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-message.is-success { color: #00a32a; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-message.is-info { color: #1d4ed8; }
body[class*="_page_sc-secretary-quick-actions"] .sc-qa-message.is-warning { color: #b45309; }
</style>
