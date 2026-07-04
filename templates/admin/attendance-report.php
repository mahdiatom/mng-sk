<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';

$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id 
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);

// تاریخ پیش‌فرض: امروز (شمسی)
$today = new DateTime(current_time('Y-m-d'));
$jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
$today_shamsi = $jalali[0] . '/' . str_pad((string) $jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $jalali[2], 2, '0', STR_PAD_LEFT);

$nonce = wp_create_nonce('sc_attendance_report_player');
?>

<div class="wrap sc-att-report-wrap">
    <div class="sc-att-list-header-inner">
        <div class="sc-att-list-header-text">
            <h1 class="sc-att-list-title">گزارش حضور بازیکن</h1>
            <p class="sc-att-list-desc">گزارش حضور و غیاب یک بازیکن در بازه تاریخ انتخابی</p>
        </div>
        <div class="sc-att-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-add')); ?>" class="sc-att-btn-secondary">ثبت حضور و غیاب</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-list')); ?>" class="sc-att-btn-primary">لیست حضور و غیاب</a>
        </div>
    </div>

    <div class="sc-att-report-layout">
        <div class="sc-att-report-filters-card">
            <h2 class="sc-att-card-title">فیلتر گزارش</h2>

            <div class="sc-att-report-field">
                <label class="sc-att-report-label" for="report_member_id">کاربر</label>
                <div class="sc-searchable-dropdown">
                    <input type="hidden" name="member_id" id="report_member_id" value="">
                    <div class="sc-dropdown-toggle">
                        <span class="sc-dropdown-placeholder">انتخاب کاربر</span>
                        <span class="sc-dropdown-selected" style="display:none;"></span>
                        <span class="sc-dropdown-arrow">▼</span>
                    </div>
                    <div class="sc-dropdown-menu">
                        <div class="sc-dropdown-search">
                            <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                        </div>
                        <div class="sc-dropdown-options">
                            <?php
                            $display_count = 0;
                            $max_display = 10;
                            foreach ($members as $member) :
                                $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                $display_count++;
                                $search_text = strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id);
                                $label = $member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id;
                                ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                     data-value="<?php echo esc_attr($member->id); ?>"
                                     data-search="<?php echo esc_attr($search_text); ?>"
                                     onclick="scSelectMember(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($label); ?>')">
                                    <span><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></span>
                                    <small><?php echo esc_html($member->national_id); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-att-report-field">
                <label class="sc-att-report-label" for="report_date_from_shamsi">از تاریخ (شمسی)</label>
                <input type="text" id="report_date_from_shamsi" class="sc-att-report-input persian-date-input" value="<?php echo esc_attr($today_shamsi); ?>" placeholder="۱۴۰۳/۰۱/۰۱" readonly>
            </div>

            <div class="sc-att-report-field">
                <label class="sc-att-report-label" for="report_date_to_shamsi">تا تاریخ (شمسی)</label>
                <input type="text" id="report_date_to_shamsi" class="sc-att-report-input persian-date-input" value="<?php echo esc_attr($today_shamsi); ?>" placeholder="۱۴۰۳/۰۱/۰۱" readonly>
            </div>

            <button type="button" id="report_btn_view" class="sc-att-btn-primary sc-att-report-submit">مشاهده گزارش</button>
        </div>

        <div class="sc-att-report-result-card" id="report-result-wrap">
            <div id="report-result">
                <div class="sc-att-report-placeholder">
                    <div class="sc-att-report-placeholder-icon">📋</div>
                    <h2>گزارش حضور بازیکن</h2>
                    <p>کاربر را انتخاب کنید و بازه تاریخ را مشخص کنید، سپس روی «مشاهده گزارش» کلیک کنید.</p>
                </div>
            </div>
            <div id="report-loading" class="sc-att-report-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p>در حال بارگذاری...</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#report_btn_view').on('click', function() {
        var memberId = $('#report_member_id').val();
        var dateFrom = $('#report_date_from_shamsi').val().trim();
        var dateTo = $('#report_date_to_shamsi').val().trim();

        if (!memberId || memberId === '0') {
            alert('لطفاً یک کاربر انتخاب کنید.');
            return;
        }
        if (!dateFrom || !dateTo) {
            alert('لطفاً بازه تاریخ را وارد کنید.');
            return;
        }

        $('#report-result').hide();
        $('#report-loading').show();

        $.post(ajaxurl, {
            action: 'sc_attendance_report_player',
            nonce: '<?php echo esc_js($nonce); ?>',
            member_id: memberId,
            date_from_shamsi: dateFrom,
            date_to_shamsi: dateTo
        })
        .done(function(r) {
            $('#report-loading').hide();
            if (r.success && r.data && r.data.html) {
                $('#report-result').html(r.data.html).show();
            } else {
                $('#report-result').html(
                    '<div class="notice notice-error"><p>' + (r.data && r.data.message ? r.data.message : 'خطا در دریافت گزارش.') + '</p></div>'
                ).show();
            }
        })
        .fail(function() {
            $('#report-loading').hide();
            $('#report-result').html(
                '<div class="notice notice-error"><p>خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.</p></div>'
            ).show();
        });
    });
});
</script>
