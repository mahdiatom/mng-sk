<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$codes = [];
$usage_tbl = $wpdb->prefix . 'sc_discount_code_usages';
$codes_tbl = $wpdb->prefix . 'sc_discount_codes';

if (function_exists('sc_sc_discount_tables_ready') && sc_sc_discount_tables_ready()) {
    $codes = $wpdb->get_results("SELECT * FROM $codes_tbl ORDER BY id DESC");
}

?>
<div class="wrap sc-discount-codes-page-header sc-finance-page-header">
    <h1 class="wp-heading-inline">کدهای تخفیف صورت‌حساب</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-discount-code')); ?>" class="page-title-action">افزودن کد تخفیف</a>
    <hr class="wp-header-end">
    <p class="sc-discount-codes-subtitle">این کدها فقط برای ثبت‌نام کاربر در دوره / رویداد از طریق حساب کاربری اعمال می‌شوند و با کوپن فروشگاه ووکامرس متفاوت است.</p>
</div>
<div class="wrap sc-discount-codes-page-body sc-finance-page-body">
    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible"><p>کد حذف شد.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['sc_err'])) : ?>
        <div class="notice notice-error"><p>خطا در عملیات (کد: <?php echo esc_html(sanitize_text_field(wp_unslash($_GET['sc_err']))); ?>).</p></div>
    <?php endif; ?>

    <div class="sc-discount-codes-list-panel sc-finance-panel postbox">
        <div class="postbox-header"><h2>لیست کدهای تخفیف</h2></div>
        <div class="inside">
    <?php if (empty($codes)) : ?>
        <p class="sc-discount-codes-empty">هنوز کدی ثبت نشده است.</p>
    <?php else : ?>
        <div class="back_table_list sc-discount-codes-table-wrap">
        <table class="wp-list-table widefat fixed striped sc-discount-codes-table">
            <thead>
                <tr>
                    <th scope="col">کد</th>
                    <th scope="col">نوع</th>
                    <th scope="col">مقدار</th>
                    <th scope="col">وضعیت</th>
                    <th scope="col">شروع</th>
                    <th scope="col">پایان</th>
                    <th scope="col">استفاده</th>
                    <th scope="col">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($codes as $c) :
                    $used = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $usage_tbl WHERE discount_code_id = %d",
                        $c->id
                    ));
                    $type_label = ($c->discount_type === 'fixed') ? 'مبلغ ثابت' : 'درصد';
                    $val_show = ($c->discount_type === 'fixed')
                        ? number_format((float) $c->discount_value, 0, '.', ',')
                        : number_format((float) $c->discount_value, 2, '.', '');
                    $edit_url = admin_url('admin.php?page=sc-add-discount-code&discount_id=' . absint($c->id));
                    $del_url = wp_nonce_url(
                        admin_url('admin.php?page=sc-discount-codes&action=delete&discount_id=' . absint($c->id)),
                        'sc_delete_discount_' . absint($c->id)
                    );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($c->code); ?></strong></td>
                        <td><?php echo esc_html($type_label); ?></td>
                        <td><?php echo esc_html($val_show); ?><?php echo $c->discount_type === 'percent' ? '%' : ''; ?></td>
                        <td><?php echo ((int) $c->is_active) ? '<span class="sc-status-active">فعال</span>' : '<span class="sc-status-inactive">غیرفعال</span>'; ?></td>
                        <td><?php echo $c->starts_at ? esc_html($c->starts_at) : '—'; ?></td>
                        <td><?php echo $c->ends_at ? esc_html($c->ends_at) : '—'; ?></td>
                        <td><?php echo esc_html((string) $used); ?></td>
                        <td class="sc-discount-codes-actions">
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">ویرایش</a>
                            <a href="<?php echo esc_url($del_url); ?>" class="button button-small sc-btn-delete" onclick="return scConfirmInline(event, { type: 'warning', message: 'حذف این کد تخفیف؟' });">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
        </div>
    </div>
</div>
