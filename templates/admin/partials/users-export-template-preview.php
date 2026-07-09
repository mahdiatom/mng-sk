<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sc-row sc-export-template-preview-row">
    <label>پیش‌نمایش زنده کارت</label>
    <div class="sc-export-template-preview-member-picker">
        <input type="search" class="sc-export-preview-member-search" placeholder="جستجوی بازیکن برای پیش‌نمایش..." autocomplete="off">
        <div class="sc-export-preview-member-results" style="display:none;"></div>
        <div class="sc-export-preview-member-selected">
            <span class="sc-export-preview-member-label">داده نمونه: اطلاعات پیش‌فرض</span>
            <button type="button" class="button-link sc-export-preview-member-clear" style="display:none;">حذف انتخاب</button>
        </div>
        <input type="hidden" class="sc-export-preview-member-id" value="">
    </div>
    <div class="sc-export-template-preview-box">
        <style class="sc-export-preview-inline-css"></style>
        <div class="sc-export-template-preview-scale">
            <div class="sc-export-template-preview-card-host"></div>
        </div>
    </div>
    <div class="sc-export-template-html-preview">
        <label>کد HTML کارت (فقط نمایش)</label>
        <textarea class="sc-export-template-html-code" rows="10" readonly dir="ltr" spellcheck="false" placeholder="پس از تنظیم قالب، HTML تولیدشده اینجا نمایش داده می‌شود..."></textarea>
    </div>
</div>
