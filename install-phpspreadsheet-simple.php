<?php
/**
 * اسکریپت نصب ساده PhpSpreadsheet
 * این فایل را از طریق مرورگر یا خط فرمان PHP اجرا کنید
 * 
 * روش 1: از طریق مرورگر
 * http://yoursite.com/wp-content/plugins/AI sportclub/install-phpspreadsheet-simple.php
 * 
 * روش 2: از طریق خط فرمان
 * php install-phpspreadsheet-simple.php
 */

// بررسی اینکه آیا در WordPress هستیم یا نه
if (!defined('ABSPATH')) {
    // اگر از خط فرمان اجرا می‌شود
    define('ABSPATH', dirname(__FILE__) . '/../../');
}

$plugin_dir = __DIR__;
$vendor_dir = $plugin_dir . '/vendor';

// بررسی اینکه آیا PhpSpreadsheet قبلاً نصب شده است
if (file_exists($vendor_dir . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php')) {
    echo "✓ PhpSpreadsheet قبلاً نصب شده است.\n";
    exit(0);
}

echo "<h2>نصب PhpSpreadsheet</h2>\n";
echo "<p>در حال نصب...</p>\n";

// ایجاد پوشه vendor
if (!file_exists($vendor_dir)) {
    mkdir($vendor_dir, 0755, true);
    echo "<p>✓ پوشه vendor ایجاد شد.</p>\n";
}

// استفاده از Composer Installer
$composer_installer_url = 'https://getcomposer.org/installer';
$composer_installer_file = $plugin_dir . '/composer-installer.php';
$composer_phar = $plugin_dir . '/composer.phar';

// اگر composer.phar وجود ندارد، دانلود کنیم
if (!file_exists($composer_phar)) {
    echo "<p>در حال دانلود Composer...</p>\n";
    
    // دانلود composer.phar
    $composer_phar_content = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');
    if ($composer_phar_content === false) {
        echo "<p style='color: red;'>✗ خطا: امکان دانلود Composer وجود ندارد.</p>\n";
        echo "<p>لطفاً به صورت دستی Composer را نصب کنید:</p>\n";
        echo "<ol>\n";
        echo "<li>از <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org</a> Composer را دانلود کنید</li>\n";
        echo "<li>فایل composer.phar را در پوشه افزونه قرار دهید</li>\n";
        echo "<li>یا از طریق خط فرمان: <code>php composer.phar install</code></li>\n";
        echo "</ol>\n";
        exit(1);
    }
    
    file_put_contents($composer_phar, $composer_phar_content);
    echo "<p>✓ Composer دانلود شد.</p>\n";
}

// اجرای composer install
echo "<p>در حال نصب وابستگی‌ها...</p>\n";
chdir($plugin_dir);
exec("php composer.phar install 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "<p style='color: green;'>✓ نصب با موفقیت انجام شد!</p>\n";
    echo "<p>اکنون می‌توانید از قابلیت خروجی Excel استفاده کنید.</p>\n";
} else {
    echo "<p style='color: red;'>✗ خطا در نصب:</p>\n";
    echo "<pre>" . implode("\n", $output) . "</pre>\n";
    echo "<p>لطفاً به صورت دستی دستور زیر را در خط فرمان اجرا کنید:</p>\n";
    echo "<pre>cd \"" . $plugin_dir . "\"\nphp composer.phar install</pre>\n";
}




