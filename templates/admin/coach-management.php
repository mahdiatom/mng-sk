<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';

// دریافت لیست مربیان
$coaches = $wpdb->get_results(
    "SELECT id, first_name, last_name, settlement_type, settlement_amount, is_active, personal_photo, mobile_phone, national_id
     FROM $coaches_table 
     ORDER BY last_name ASC, first_name ASC"
);
?>

<div class="wrap sc-cm-wrap">
    <div class="sc-cm-header">
        <div class="sc-cm-header-text">
            <h1 class="sc-cm-title">مدیریت مربیان</h1>
            <p class="sc-cm-desc">مشاهده موجودی کیف پول و دسترسی سریع به ویرایش و مدیریت مالی مربیان</p>
        </div>
        <div class="sc-cm-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coaches')); ?>" class="sc-cm-btn-secondary">لیست مربیان</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-management-wallet')); ?>" class="sc-cm-btn-primary">کیف پول مربیان</a>
        </div>
    </div>

    <div class="sc-cm-table-card">
        <div class="sc-cm-summary"><span><?php echo count($coaches); ?> مربی</span></div>
        <div class="sc-cm-table-scroll">
            <table class="wp-list-table widefat striped sc-cm-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>نام و نام خانوادگی</th>
                        <th>نوع دستمزد</th>
                        <th>وضعیت</th>
                        <th>موجودی کیف پول</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coaches)) : ?>
                        <tr>
                            <td colspan="6" class="sc-cm-empty">هیچ مربی ثبت نشده است.</td>
                        </tr>
                    <?php else : ?>
                        <?php $row = 1; ?>
                        <?php foreach ($coaches as $coach) :
                            $wallet_balance = sc_get_coach_wallet_balance($coach->id);
                            $full_name = trim($coach->first_name . ' ' . $coach->last_name);
                            $photo = !empty($coach->personal_photo) ? $coach->personal_photo : '';
                            $initials = '';
                            if (!empty($coach->first_name)) {
                                $initials .= mb_substr((string) $coach->first_name, 0, 1);
                            }
                            if (!empty($coach->last_name)) {
                                $initials .= mb_substr((string) $coach->last_name, 0, 1);
                            }
                            if ($initials === '') {
                                $initials = 'م';
                            }
                            $settlement_badge = 'sc-badge--soft';
                            $settlement_label = 'درصدی';
                            if ($coach->settlement_type === 'fixed') {
                                $settlement_badge = 'sc-badge--success';
                                $settlement_label = 'ثابت';
                            } elseif ($coach->settlement_type === 'both') {
                                $settlement_badge = 'sc-badge--purple';
                                $settlement_label = 'ثابت + درصدی';
                            }
                            ?>
                            <tr>
                                <td data-label="ردیف"><?php echo (int) $row++; ?></td>
                                <td data-label="نام و نام خانوادگی">
                                    <span class="sc-member-identity">
                                        <?php if ($photo) : ?>
                                            <span class="sc-member-avatar"><img src="<?php echo esc_url($photo); ?>" alt="" loading="lazy"></span>
                                        <?php else : ?>
                                            <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                        <?php endif; ?>
                                        <span class="sc-member-identity-text">
                                            <span class="sc-member-name"><?php echo esc_html($full_name); ?></span>
                                            <?php if (!empty($coach->mobile_phone) || !empty($coach->national_id)) : ?>
                                                <span class="sc-member-meta">
                                                    <?php if (!empty($coach->mobile_phone)) : ?>
                                                        <span class="sc-member-meta-item"><?php echo esc_html($coach->mobile_phone); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($coach->mobile_phone) && !empty($coach->national_id)) : ?>
                                                        <span class="sc-member-meta-dot"></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($coach->national_id)) : ?>
                                                        <span class="sc-member-meta-item"><?php echo esc_html($coach->national_id); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="نوع دستمزد"><span class="sc-badge <?php echo esc_attr($settlement_badge); ?>"><?php echo esc_html($settlement_label); ?></span></td>
                                <td data-label="وضعیت">
                                    <?php if ($coach->is_active) : ?>
                                        <span class="sc-badge sc-badge--success">فعال</span>
                                    <?php else : ?>
                                        <span class="sc-badge sc-badge--muted">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="موجودی کیف پول"><strong class="sc-cm-amount"><?php echo esc_html(sc_format_amount_display($wallet_balance)); ?></strong> تومان</td>
                                <td data-label="عملیات" class="sc-cm-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-management-wallet&coach_id=' . $coach->id)); ?>" class="sc-cm-action-primary">مدیریت کیف پول</a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-coach&coach_id=' . $coach->id)); ?>" class="sc-cm-action-link">ویرایش</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
