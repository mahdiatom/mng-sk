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
$jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
$today_shamsi = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

$nonce = wp_create_nonce('sc_attendance_report_player');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارش حضور بازیکن</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-add')); ?>" class="page-title-action">ثبت حضور و غیاب</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-list')); ?>" class="page-title-action">لیست حضور و غیاب</a>
    <hr class="wp-header-end ">

    <div class="report_player_attendance">
        <!-- فیلترها -->
        <div>
            <div class="postbox" style="margin-top: 0;">
                <div class="postbox-header">
                    <h2 class=" filter_head_player">فیلتر گزارش</h2>
                </div>
                <div class="inside" style="padding: 15px;">
                    <p style="margin-bottom: 10px;"><label for="report_member_id">کاربر:</label></p>
                    <div class="sc-searchable-dropdown" style="width: 100%;">
                        <input type="hidden" name="member_id" id="report_member_id" value="">
                        <div class="sc-dropdown-toggle" style="width: 100%;">
                            <span class="sc-dropdown-placeholder">انتخاب کاربر</span>
                            <span class="sc-dropdown-selected" style="display:none;"></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu" style="width: 100%; max-height: 400px; overflow-y: auto;">
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
                                        <small style="display: block; color: #666; font-size: 11px;"><?php echo esc_html($member->national_id); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <p style="margin: 15px 0 8px 0;"><label for="report_date_from_shamsi">از تاریخ (شمسی):</label></p>
                    <input type="text" 
                           id="report_date_from_shamsi" 
                           class="regular-text persian-date-input" 
                           value="<?php echo esc_attr($today_shamsi); ?>" 
                           placeholder="۱۴۰۳/۰۱/۰۱" 
                           style="width: 100%;" 
                           readonly>

                    <p style="margin: 15px 0 8px 0;"><label for="report_date_to_shamsi">تا تاریخ (شمسی):</label></p>
                    <input type="text" 
                           id="report_date_to_shamsi" 
                           class="regular-text persian-date-input" 
                           value="<?php echo esc_attr($today_shamsi); ?>" 
                           placeholder="۱۴۰۳/۰۱/۰۱" 
                           style="width: 100%;" 
                           readonly>

                    <p style="margin-top: 18px;">
                        <button type="button" id="report_btn_view" class="button button-primary" style="width: 100%;">
                            مشاهده گزارش
                        </button>
                    </p>
                </div>
            </div>
        </div>

        <!-- نتیجه (AJAX) -->
        <div>
            <div id="report-result-wrap">
                <div id="report-result">
                    <div class="postbox" style="margin-top: 0;">
                        <div class="inside" style="padding: 40px; text-align: center;">
                            <div style="font-size: 48px; opacity: 0.3; margin-bottom: 15px;">📋</div>
                            <h2 style="margin: 0 0 10px 0; color: #666;" >گزارش حضور بازیکن</h2>
                            <p style="color: #999; margin: 0;">کاربر را انتخاب کنید و بازه تاریخ را مشخص کنید، سپس روی «مشاهده گزارش» کلیک کنید.</p>
                        </div>
                    </div>
                </div>
                <div id="report-loading" style="display: none; padding: 30px; text-align: center;">
                    <span class="spinner is-active" style="float: none; margin: 0 auto 10px; display: block;"></span>
                    <p style="margin: 0; color: #666;">در حال بارگذاری...</p>
                </div>
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
        .fail(function(xhr, status, err) {
            $('#report-loading').hide();
            $('#report-result').html(
                '<div class="notice notice-error"><p>خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.</p></div>'
            ).show();
        });
    });
});
</script>
