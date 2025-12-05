# راهنمای نصب Composer در Windows

## روش 1: نصب Composer (پیشنهادی)

### مرحله 1: دانلود Composer Installer

1. به آدرس زیر بروید:
   **https://getcomposer.org/download/**

2. روی دکمه **"Composer-Setup.exe"** کلیک کنید و فایل را دانلود کنید

3. فایل `Composer-Setup.exe` را اجرا کنید

4. در مراحل نصب:
   - مسیر PHP را انتخاب کنید: `C:\xampp\php\php.exe`
   - Composer به صورت خودکار به PATH اضافه می‌شود

### مرحله 2: بررسی نصب

1. Command Prompt یا PowerShell را باز کنید
2. دستور زیر را تایپ کنید:
   ```
   composer --version
   ```
3. اگر نسخه Composer نمایش داده شد، نصب موفق بوده است

### مرحله 3: نصب PhpSpreadsheet

1. Command Prompt را باز کنید
2. به پوشه افزونه بروید:
   ```
   cd "C:\xampp\htdocs\ai.com\wp-content\plugins\AI sportclub"
   ```
3. دستور زیر را اجرا کنید:
   ```
   composer install
   ```

---

## روش 2: استفاده از Composer.phar (بدون نصب Composer)

### مرحله 1: دانلود composer.phar

1. به آدرس زیر بروید:
   **https://getcomposer.org/download/**

2. روی لینک **"composer.phar"** کلیک کنید و فایل را دانلود کنید

3. فایل `composer.phar` را در پوشه افزونه قرار دهید:
   ```
   C:\xampp\htdocs\ai.com\wp-content\plugins\AI sportclub\composer.phar
   ```

### مرحله 2: نصب PhpSpreadsheet

1. Command Prompt را باز کنید
2. به پوشه افزونه بروید:
   ```
   cd "C:\xampp\htdocs\ai.com\wp-content\plugins\AI sportclub"
   ```
3. دستور زیر را اجرا کنید:
   ```
   C:\xampp\php\php.exe composer.phar install
   ```

---

## روش 3: دانلود دستی PhpSpreadsheet

اگر نمی‌توانید Composer را نصب کنید، می‌توانید PhpSpreadsheet را به صورت دستی دانلود کنید:

### مرحله 1: دانلود PhpSpreadsheet

1. به آدرس زیر بروید:
   **https://github.com/PHPOffice/PhpSpreadsheet/releases**

2. آخرین نسخه (مثلاً `1.29.0`) را پیدا کنید

3. فایل `Source code (zip)` را دانلود کنید

### مرحله 2: استخراج و نصب

1. فایل ZIP را استخراج کنید

2. پوشه `src` را از فایل استخراج شده کپی کنید

3. در پوشه افزونه، ساختار زیر را ایجاد کنید:
   ```
   vendor/
   └── phpoffice/
       └── phpspreadsheet/
           └── src/
               └── PhpSpreadsheet/
   ```

4. محتویات پوشه `src` از فایل دانلود شده را به `vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/` کپی کنید

5. فایل `autoload.php` را در پوشه `vendor` ایجاد کنید (کد آن در ادامه آمده است)

### مرحله 3: ایجاد autoload.php

در پوشه `vendor` فایل `autoload.php` را با محتوای زیر ایجاد کنید:

```php
<?php
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
```

---

## بررسی نصب موفق

پس از نصب، فایل زیر باید وجود داشته باشد:
```
vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php
```

اگر این فایل وجود دارد، نصب موفق بوده است! ✅

---

## مشکل دارید؟

اگر با مشکلی مواجه شدید:
- مطمئن شوید PHP extension `zip` فعال است
- مطمئن شوید دسترسی نوشتن در پوشه افزونه را دارید
- لاگ خطاهای PHP را بررسی کنید






