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
    "SELECT id, first_name, last_name, settlement_type, settlement_amount, is_active 
     FROM $coaches_table 
     ORDER BY last_name ASC, first_name ASC"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">مدیریت مربیان</h1>
    <hr class="wp-header-end">
    </div>
    <div class="wrap">

    
    <div class="card" style="margin: 20px 0; max-width: 100%;">
        <h2>لیست مربیان</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ردیف</th>
                    <th>نام و نام خانوادگی</th>
                    <th>نوع دستمزد</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coaches)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px;">
                            <p>هیچ مربی ثبت نشده است.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $row = 1; ?>
                    <?php foreach ($coaches as $coach): ?>
                        <?php
                        $wallet_balance = sc_get_coach_wallet_balance($coach->id);
                        ?>
                        <tr>
                            <td><?php echo $row++; ?></td>
                            <td><strong><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></strong></td>
                            <td>
                                <?php if ($coach->settlement_type === 'fixed'): ?>
                                    <span style="color: #00a32a;">ثابت</span>
                                <?php else: ?>
                                    <span style="color: #2271b1;">درصدی</span>
                                <?php endif; ?>
                            </td>
                           
                            <td>
                                <?php if ($coach->is_active): ?>
                                    <span style="color: #00a32a;">فعال</span>
                                <?php else: ?>
                                    <span style="color: #d63638;">غیرفعال</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>موجودی:</strong> <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان<br>
                                <a href="<?php echo admin_url('admin.php?page=sc-coach-management-wallet&coach_id=' . $coach->id); ?>" class="button button-small">مدیریت کیف پول</a>
                                <a href="<?php echo admin_url('admin.php?page=sc-add-coach&coach_id=' . $coach->id); ?>" class="button button-small">ویرایش</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
