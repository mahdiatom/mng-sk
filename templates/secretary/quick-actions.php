<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
    wp_die('دسترسی ندارید.', 'خطای دسترسی', ['response' => 403]);
}

$chapters = function_exists('sc_secretary_get_effective_chapters') ? sc_secretary_get_effective_chapters() : [];
$courses = function_exists('sc_secretary_get_branch_courses') ? sc_secretary_get_branch_courses() : [];
$filter_chapter = function_exists('sc_secretary_get_filter_chapter') ? sc_secretary_get_filter_chapter() : 'all';
$default_chapter = ($filter_chapter !== 'all') ? $filter_chapter : (isset($chapters[0]) ? $chapters[0] : '');

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
]);
?>
<div class="wrap sc-secretary-quick-actions-wrap">
    <h1 class="wp-heading-inline">اقدامات سریع</h1>
    <hr class="wp-header-end">
    <p class="description">ثبت‌نام بازیکن در دوره و صدور صورت‌حساب برای شعبه(های) مجاز شما.</p>

    <h2 class="nav-tab-wrapper sc-secretary-qa-tabs">
        <a href="#sc-qa-existing" class="nav-tab nav-tab-active" data-tab="existing">افزودن کاربر موجود به دوره</a>
        <a href="#sc-qa-new" class="nav-tab" data-tab="new">ثبت‌نام کاربر جدید + دوره</a>
    </h2>

    <div id="sc-qa-existing" class="sc-secretary-qa-panel postbox" style="margin-top:16px;padding:16px;">
        <table class="form-table">
            <tr>
                <th><label for="sc_qa_member_search">بازیکن</label></th>
                <td>
                    <input type="text" id="sc_qa_member_search" class="regular-text" placeholder="جستجو نام یا موبایل..." autocomplete="off" />
                    <input type="hidden" id="sc_qa_member_id" value="" />
                    <div id="sc_qa_member_results" class="sc-qa-search-results"></div>
                </td>
            </tr>
            <tr>
                <th><label for="sc_qa_existing_chapter">شعبه</label></th>
                <td>
                    <select id="sc_qa_existing_chapter">
                        <?php foreach ($chapters as $ch) : ?>
                            <option value="<?php echo esc_attr($ch); ?>" <?php selected($default_chapter, $ch); ?>><?php echo esc_html($ch); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sc_qa_existing_course">دوره</label></th>
                <td>
                    <select id="sc_qa_existing_course">
                        <option value="">— انتخاب دوره —</option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sc_qa_existing_payment">وضعیت پرداخت</label></th>
                <td>
                    <select id="sc_qa_existing_payment">
                        <option value="pending">در انتظار پرداخت</option>
                        <option value="processing">پرداخت شده</option>
                        <option value="completed">تایید پرداخت</option>
                    </select>
                </td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" id="sc_qa_existing_submit">ثبت</button></p>
        <div id="sc_qa_existing_message" class="sc-qa-message" aria-live="polite"></div>
    </div>

    <div id="sc-qa-new" class="sc-secretary-qa-panel postbox" style="display:none;margin-top:16px;padding:16px;">
        <table class="form-table">
            <tr>
                <th><label for="sc_qa_new_mobile">موبایل</label></th>
                <td><input type="text" id="sc_qa_new_mobile" class="regular-text" placeholder="09xxxxxxxxx" maxlength="11" /></td>
            </tr>
            <tr>
                <th><label for="sc_qa_new_first">نام</label></th>
                <td><input type="text" id="sc_qa_new_first" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="sc_qa_new_last">نام خانوادگی</label></th>
                <td><input type="text" id="sc_qa_new_last" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="sc_qa_new_chapter">شعبه</label></th>
                <td>
                    <select id="sc_qa_new_chapter">
                        <?php foreach ($chapters as $ch) : ?>
                            <option value="<?php echo esc_attr($ch); ?>" <?php selected($default_chapter, $ch); ?>><?php echo esc_html($ch); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sc_qa_new_course">دوره</label></th>
                <td>
                    <select id="sc_qa_new_course">
                        <option value="">— انتخاب دوره —</option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sc_qa_new_payment">وضعیت پرداخت</label></th>
                <td>
                    <select id="sc_qa_new_payment">
                        <option value="pending">در انتظار پرداخت</option>
                        <option value="processing">پرداخت شده</option>
                        <option value="completed">تایید پرداخت</option>
                    </select>
                </td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" id="sc_qa_new_submit">ثبت‌نام و فعال‌سازی</button></p>
        <div id="sc_qa_new_message" class="sc-qa-message" aria-live="polite"></div>
    </div>
</div>
<style>
.sc-qa-search-results { position:relative; max-width:400px; }
.sc-qa-search-results ul { list-style:none; margin:4px 0 0; padding:0; border:1px solid #c3c4c7; background:#fff; max-height:200px; overflow:auto; }
.sc-qa-search-results li { padding:8px 10px; cursor:pointer; border-bottom:1px solid #f0f0f1; }
.sc-qa-search-results li:hover { background:#f6f7f7; }
.sc-qa-message { margin-top:12px; }
.sc-qa-message.is-error { color:#b32d2e; }
.sc-qa-message.is-success { color:#00a32a; }
</style>
