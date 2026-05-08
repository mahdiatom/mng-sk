(function () {
    'use strict';

    var activeResolver = null;
    var activeModal = null;
    var focusBeforeOpen = null;

    function getTypeConfig(type) {
        var map = {
            danger: { icon: '!', className: 'is-danger', title: 'هشدار مهم' },
            warning: { icon: '!', className: 'is-warning', title: 'تایید عملیات' },
            info: { icon: 'i', className: 'is-info', title: 'توجه' },
            success: { icon: '✓', className: 'is-success', title: 'تایید' }
        };
        return map[type] || map.warning;
    }

    function closeModal(confirmed) {
        if (!activeModal) {
            return;
        }

        var resolver = activeResolver;
        var modal = activeModal;
        activeResolver = null;
        activeModal = null;

        modal.classList.remove('is-open');
        setTimeout(function () {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 150);

        document.body.classList.remove('sc-confirm-open');
        if (focusBeforeOpen && typeof focusBeforeOpen.focus === 'function') {
            focusBeforeOpen.focus();
        }
        focusBeforeOpen = null;

        if (typeof resolver === 'function') {
            resolver(Boolean(confirmed));
        }
    }

    function buildModal(options) {
        var typeConfig = getTypeConfig(options.type);
        var title = options.title || typeConfig.title;
        var message = options.message || 'آیا مطمئن هستید؟';
        var confirmText = options.confirmText || 'تایید';
        var cancelText = options.cancelText || 'لغو';

        var wrapper = document.createElement('div');
        wrapper.className = 'sc-confirm-overlay';
        wrapper.innerHTML = ''
            + '<div class="sc-confirm-modal ' + typeConfig.className + '" role="dialog" aria-modal="true">'
            + '  <button type="button" class="sc-confirm-close" aria-label="بستن">×</button>'
            + '  <div class="sc-confirm-icon">' + typeConfig.icon + '</div>'
            + '  <h3 class="sc-confirm-title"></h3>'
            + '  <p class="sc-confirm-message"></p>'
            + '  <div class="sc-confirm-actions">'
            + '    <button type="button" class="button sc-confirm-cancel"></button>'
            + '    <button type="button" class="button button-primary sc-confirm-approve"></button>'
            + '  </div>'
            + '</div>';

        wrapper.querySelector('.sc-confirm-title').textContent = title;
        wrapper.querySelector('.sc-confirm-message').textContent = message;
        wrapper.querySelector('.sc-confirm-cancel').textContent = cancelText;
        wrapper.querySelector('.sc-confirm-approve').textContent = confirmText;

        wrapper.addEventListener('click', function (event) {
            var target = event.target;
            if (target.classList.contains('sc-confirm-overlay') || target.classList.contains('sc-confirm-close')) {
                closeModal(false);
            }
            if (target.classList.contains('sc-confirm-cancel')) {
                closeModal(false);
            }
            if (target.classList.contains('sc-confirm-approve')) {
                closeModal(true);
            }
        });

        wrapper.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(false);
            }
        });

        return wrapper;
    }

    function openConfirm(options) {
        if (activeResolver) {
            closeModal(false);
        }

        return new Promise(function (resolve) {
            focusBeforeOpen = document.activeElement;
            activeResolver = resolve;

            var modal = buildModal(options || {});
            activeModal = modal;
            document.body.appendChild(modal);
            document.body.classList.add('sc-confirm-open');

            requestAnimationFrame(function () {
                modal.classList.add('is-open');
                var approveBtn = modal.querySelector('.sc-confirm-approve');
                if (approveBtn) {
                    approveBtn.focus();
                }
            });
        });
    }

    function confirmInline(event, options) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        var source = event ? (event.currentTarget || event.target) : null;
        var submitter = event && event.submitter ? event.submitter : null;
        openConfirm(options || {}).then(function (confirmed) {
            if (!confirmed || !source) {
                return;
            }

            if (source.tagName === 'A') {
                var href = source.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
                return;
            }

            var form = source.tagName === 'FORM' ? source : source.closest('form');
            if (form) {
                var submitControl = submitter || (source.tagName === 'FORM' ? null : source);
                if (!submitControl && document.activeElement && form.contains(document.activeElement)) {
                    submitControl = document.activeElement;
                }
                if (submitControl && submitControl.name && typeof submitControl.value !== 'undefined' && !form[submitControl.name]) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitControl.name;
                    hidden.value = submitControl.value;
                    form.appendChild(hidden);
                }
                if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
                    HTMLFormElement.prototype.submit.call(form);
                } else {
                    form.submit();
                }
            }
        });

        return false;
    }

    window.scConfirm = openConfirm;
    window.scConfirmInline = confirmInline;
})();
