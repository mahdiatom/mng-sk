<?php
/**
 * اسکریپت اجرای composer install
 * این فایل را از طریق مرورگر یا خط فرمان اجرا کنید
 */

// تنظیم مسیر افزونه
$plugin_dir = __DIR__;
$composer_phar = $plugin_dir . '/composer.phar';

echo "<h2>نصب PhpSpreadsheet</h2>\n";
echo "<p>در حال اجرای composer install...</p>\n";

// بررسی وجود composer.phar
if (!file_exists($composer_phar)) {
    echo "<p style='color: red;'>✗ فایل composer.phar یافت نشد.</p>\n";
    echo "<p>لطفاً فایل composer.phar را از <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org</a> دانلود کنید و در پوشه افزونه قرار دهید.</p>\n";
    exit(1);
}

// تغییر به پوشه افزونه
chdir($plugin_dir);

// اجرای composer install
echo "<p>در حال نصب وابستگی‌ها...</p>\n";
echo "<pre style='background: #f0f0f1; padding: 10px; direction: ltr; text-align: left;'>\n";

// استفاده از PHP که در XAMPP نصب شده
$php_path = 'C:\\xampp\\php\\php.exe';
if (!file_exists($php_path)) {
    // تلاش برای پیدا کردن PHP
    $php_path = 'php';
}

$command = '"' . $php_path . '" "' . $composer_phar . '" install 2>&1';
exec($command, $output, $return_var);

// نمایش خروجی
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}

echo "</pre>\n";

if ($return_var === 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ نصب با موفقیت انجام شد!</p>\n";
    echo "<p>اکنون می‌توانید از قابلیت خروجی Excel استفاده کنید.</p>\n";
    
    // بررسی وجود فایل‌ها
    $spreadsheet_file = $plugin_dir . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
    if (file_exists($spreadsheet_file)) {
        echo "<p style='color: green;'>✓ فایل‌های PhpSpreadsheet با موفقیت نصب شدند.</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ هشدار: فایل‌های PhpSpreadsheet یافت نشدند. لطفاً دستور را در Command Prompt اجرا کنید.</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ خطا در نصب</p>\n";
    echo "<p>لطفاً دستور زیر را در Command Prompt اجرا کنید:</p>\n";
    echo "<pre style='background: #f0f0f1; padding: 10px;'>";
    echo "cd \"" . $plugin_dir . "\"\n";
    echo "C:\\xampp\\php\\php.exe composer.phar install\n";
    echo "</pre>\n";
}










