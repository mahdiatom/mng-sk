<?php
/**
 * اسکریپت نصب خودکار PhpSpreadsheet
 * این فایل را یک بار اجرا کنید تا PhpSpreadsheet نصب شود
 * 
 * دستور اجرا: php install-phpspreadsheet.php
 */

// تنظیم مسیر افزونه
$plugin_dir = __DIR__;
$vendor_dir = $plugin_dir . '/vendor';

// بررسی اینکه آیا PhpSpreadsheet قبلاً نصب شده است
if (file_exists($vendor_dir . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php')) {
    echo "✓ PhpSpreadsheet قبلاً نصب شده است.\n";
    exit(0);
}

echo "شروع نصب PhpSpreadsheet...\n\n";

// ایجاد پوشه vendor
if (!file_exists($vendor_dir)) {
    mkdir($vendor_dir, 0755, true);
    echo "✓ پوشه vendor ایجاد شد.\n";
}

// URL دانلود PhpSpreadsheet (از GitHub Releases)
$phpspreadsheet_version = '1.29.0';
$download_url = "https://github.com/PHPOffice/PhpSpreadsheet/archive/refs/tags/{$phpspreadsheet_version}.zip";
$zip_file = $plugin_dir . '/phpspreadsheet.zip';
$extract_dir = $plugin_dir . '/phpspreadsheet_temp';

echo "در حال دانلود PhpSpreadsheet...\n";

// دانلود فایل
$zip_content = @file_get_contents($download_url);
if ($zip_content === false) {
    echo "✗ خطا: امکان دانلود PhpSpreadsheet وجود ندارد.\n";
    echo "لطفاً به صورت دستی از آدرس زیر دانلود کنید:\n";
    echo $download_url . "\n";
    exit(1);
}

file_put_contents($zip_file, $zip_content);
echo "✓ دانلود با موفقیت انجام شد.\n";

// استخراج فایل ZIP
if (!class_exists('ZipArchive')) {
    echo "✗ خطا: کلاس ZipArchive در PHP موجود نیست.\n";
    echo "لطفاً extension zip را در php.ini فعال کنید.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($zip_file) === TRUE) {
    $zip->extractTo($extract_dir);
    $zip->close();
    echo "✓ فایل استخراج شد.\n";
} else {
    echo "✗ خطا: امکان استخراج فایل ZIP وجود ندارد.\n";
    exit(1);
}

// انتقال فایل‌ها به vendor
$source_dir = $extract_dir . '/PhpSpreadsheet-' . $phpspreadsheet_version . '/src';
$target_dir = $vendor_dir . '/phpoffice/phpspreadsheet/src';

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// کپی فایل‌ها
function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

copyDirectory($source_dir, $target_dir);
echo "✓ فایل‌ها به vendor منتقل شدند.\n";

// ایجاد autoload.php ساده
$autoload_content = <<<'PHP'
<?php
/**
 * Simple autoloader for PhpSpreadsheet
 */
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $base_dir = __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
PHP;

file_put_contents($vendor_dir . '/autoload.php', $autoload_content);
echo "✓ فایل autoload.php ایجاد شد.\n";

// پاک کردن فایل‌های موقت
if (file_exists($zip_file)) {
    unlink($zip_file);
}
if (file_exists($extract_dir)) {
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
    deleteDirectory($extract_dir);
}

echo "\n✓ نصب PhpSpreadsheet با موفقیت انجام شد!\n";
echo "اکنون می‌توانید از قابلیت خروجی Excel استفاده کنید.\n";




