/**
 * کپی لینک ثبت‌نام عمومی رویداد — وانیلا، فقط وابسته به sc-confirm-js (بدون jQuery)
 */
(function () {
    'use strict';

    function showCopied() {
        if (typeof window.scConfirm === 'function') {
            window.scConfirm({
                type: 'success',
                title: 'کپی شد',
                message: 'لینک در کلیپ‌بورد کپی شد.',
                confirmText: 'باشه',
                hideCancel: true
            });
        } else {
            window.alert('لینک کپی شد!');
        }
    }

    function copyWithExecCommand(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', 'readonly');
        ta.setAttribute('aria-hidden', 'true');
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, text.length);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e1) {
            ok = false;
        }
        if (ta.parentNode) {
            ta.parentNode.removeChild(ta);
        }
        return ok;
    }

    function runCopy(url) {
        if (
            window.navigator.clipboard
            && window.isSecureContext === true
            && typeof window.navigator.clipboard.writeText === 'function'
        ) {
            window.navigator.clipboard.writeText(url).then(showCopied).catch(function () {
                if (copyWithExecCommand(url)) {
                    showCopied();
                } else {
                    window.alert('کپی ناموفق بود. لینک:\n' + url);
                }
            });
            return;
        }
        if (copyWithExecCommand(url)) {
            showCopied();
        } else {
            window.alert('کپی ناموفق بود. لینک:\n' + url);
        }
    }

    document.addEventListener(
        'click',
        function (e) {
            var t = e.target;
            if (!t || typeof t.closest !== 'function') {
                return;
            }
            var btn = t.closest('.sc-copy-public-event-link');
            if (!btn) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            var url = btn.getAttribute('data-url');
            if (!url) {
                return;
            }
            runCopy(url);
        },
        true
    );
})();
