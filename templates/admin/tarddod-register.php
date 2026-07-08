<?php
if (!defined('ABSPATH')) exit;
$session_id = isset($_GET['session_id']) ? absint($_GET['session_id']) : 0;
$sessions = sc_tarddod_get_open_sessions_for_select();
if (!$session_id && !empty($sessions)) {
    $session_id = (int) $sessions[0]->id;
}
$selected = $session_id ? sc_tarddod_get_session($session_id) : null;
$records = $session_id ? sc_tarddod_get_session_records($session_id) : [];
$panel_id = 'sc-tarddod-register-filters';
$toggle_id = 'sc-tarddod-register-filters-toggle';
$clear_url = admin_url('admin.php?page=sc-tarddod-register');
?>
<div class="wrap sc-members-list-wrap sc-tarddod-wrap sc-tarddod-register-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">ثبت تردد (اسکن QR)</h1>
            <p class="sc-members-list-desc">جلسه را انتخاب کنید و QR بازیکن (SC1) یا پرسنل (SC2) را اسکن کنید. QR با لوگوی وسط هم پشتیبانی می‌شود — کارت را ثابت و کامل جلوی دوربین بگیرید.</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-sessions')); ?>" class="button">جلسات</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-tarddod-session-add')); ?>" class="button button-primary sc-members-list-add-btn">افزودن جلسه</a>
        </div>
    </div>

    <?php
    sc_members_list_filter_card_open([
        'page'                  => 'sc-tarddod-register',
        'panel_id'              => $panel_id,
        'toggle_id'             => $toggle_id,
        'clear_url'             => $clear_url,
        'active_filters_count'  => $session_id > 0 ? 1 : 0,
        'filters_open'          => true,
        'form_class'            => 'sc-tarddod-session-picker-form',
    ]);
    ?>
            <div class="sc-filter-grid">
                <div class="sc-filter-field sc-filter-field--wide">
                    <label class="sc-filter-label" for="sc-tarddod-session-select">جلسه فعال</label>
                    <select name="session_id" id="sc-tarddod-session-select" class="sc-filter-control">
                        <?php if (empty($sessions)) : ?>
                            <option value="">جلسه باز وجود ندارد</option>
                        <?php else : foreach ($sessions as $s) :
                            $label = $s->title . ' — ' . (function_exists('sc_date_shamsi') ? sc_date_shamsi($s->session_date, 'Y/m/d') : $s->session_date);
                            if ($s->session_time) {
                                $label .= ' ' . substr((string) $s->session_time, 0, 5);
                            }
                            ?>
                            <option value="<?php echo esc_attr((string) $s->id); ?>" <?php selected($session_id, (int) $s->id); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
    <?php sc_members_list_filter_card_close(['clear_url' => $clear_url, 'submit_label' => 'اعمال جلسه']); ?>

    <?php
    $session_description = $selected ? trim((string) ($selected->description ?? '')) : '';
    $session_location = $selected ? trim((string) ($selected->location ?? '')) : '';
    if ($selected && ($session_description !== '' || $session_location !== '')) : ?>
        <p class="sc-tarddod-session-meta">
            <?php if ($session_description !== '') : ?><?php echo esc_html($session_description); ?><?php endif; ?>
            <?php if ($session_location !== '') : ?><?php echo $session_description !== '' ? ' — ' : ''; ?><?php echo esc_html($session_location); ?><?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!$session_id || !$selected) : ?>
        <div class="sc-members-list-table-card">
            <p class="sc-reports-empty">ابتدا یک جلسه با وضعیت «باز» از بخش افزودن جلسه ایجاد کنید.</p>
        </div>
    <?php else : ?>
        <div id="sc-attendance-qr-panel" class="sc-attendance-qr-panel sc-tarddod-qr-panel">
            <div class="sc-attendance-qr-panel__main">
                <div class="sc-attendance-qr-camera-wrap">
                    <div id="sc-attendance-qr-reader" class="sc-attendance-qr-reader"></div>
                    <div id="sc-attendance-qr-toast" class="sc-attendance-qr-toast" aria-live="polite">
                        <span class="sc-attendance-qr-toast__text">برای شروع، «شروع اسکن» را بزنید</span>
                    </div>
                </div>
                <div class="sc-attendance-qr-actions">
                    <button type="button" class="button button-primary button-large" id="sc-attendance-qr-start">شروع اسکن</button>
                    <button type="button" class="button button-large" id="sc-attendance-qr-switch" disabled>تغییر دوربین</button>
                    <button type="button" class="button button-large" id="sc-attendance-qr-stop" disabled>توقف</button>
                </div>
            </div>
            <aside class="sc-attendance-qr-sidebar">
                <div class="sc-attendance-qr-stat">
                    <span class="sc-attendance-qr-stat__label">ثبت‌شده در این جلسه</span>
                    <strong class="sc-attendance-qr-stat__value" id="sc-attendance-qr-count"><?php echo count($records); ?></strong>
                </div>
                <h3 class="sc-attendance-qr-log-title">آخرین ثبت‌ها</h3>
                <ul id="sc-attendance-qr-log" class="sc-attendance-qr-log">
                    <?php foreach (array_slice($records, 0, 15) as $rec) : ?>
                        <li class="sc-attendance-qr-log__item sc-attendance-qr-log__item--success">
                            <span class="sc-attendance-qr-log__time"><?php echo esc_html(substr($rec->created_at, 11, 5)); ?></span>
                            <span class="sc-attendance-qr-log__name"><?php echo esc_html($rec->subject_name); ?></span>
                            <span class="sc-attendance-qr-log__msg"><?php echo esc_html(sc_tarddod_subject_type_label($rec->subject_type)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>

        <div class="sc-members-list-table-card sc-tarddod-live-list">
            <h2>لیست حاضرین جلسه</h2>
            <table class="wp-list-table widefat fixed striped sc-tarddod-records-table" id="sc-tarddod-records-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام</th>
                        <th>نوع</th>
                        <th>زمان ثبت</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)) : ?>
                    <tr><td colspan="4" class="sc-reports-empty">هنوز کسی ثبت نشده است.</td></tr>
                <?php else :
                    $i = 1;
                    foreach ($records as $rec) :
                        $type_badge = ($rec->subject_type ?? '') === 'staff' ? 'sc-badge--purple' : 'sc-badge--soft';
                        ?>
                    <tr>
                        <td><?php echo (int) $i++; ?></td>
                        <td><strong><?php echo esc_html($rec->subject_name); ?></strong></td>
                        <td><span class="sc-badge <?php echo esc_attr($type_badge); ?>"><?php echo esc_html(sc_tarddod_subject_type_label($rec->subject_type)); ?></span></td>
                        <td><?php echo esc_html($rec->created_at); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php sc_members_list_filter_toggle_script($toggle_id, $panel_id); ?>
