(function () {
    'use strict';
    var cfg = window.scSurveyWizard || {};
    var questions = cfg.questions || [];
    var step = 0;
    var answers = {};

    var container = document.getElementById('sc-survey-step-container');
    var prevBtn = document.getElementById('sc-survey-prev');
    var nextBtn = document.getElementById('sc-survey-next');
    var submitBtn = document.getElementById('sc-survey-submit');
    var form = document.getElementById('sc-survey-wizard-form');
    var progressFill = document.getElementById('scSurveyProgressFill');
    var progressText = document.getElementById('scSurveyProgressText');
    var thankPanel = document.getElementById('sc-survey-thankyou-panel');

    if (!container || !form) {
        return;
    }

    function evaluateConditional(c, dep) {
        if (!c || !c.enabled || !c.question_id) {
            return true;
        }
        var depStr = Array.isArray(dep) ? dep.join(',') : String(dep == null ? '' : dep);
        var op = c.operator || 'equals';
        var val = c.value || '';
        if (op === 'not_equals') return depStr !== val;
        if (op === 'contains') return depStr.indexOf(val) !== -1;
        if (op === 'gt' || op === 'gte' || op === 'lt' || op === 'lte') {
            var depNum = parseFloat(depStr);
            var valNum = parseFloat(val);
            if (!isNaN(depNum) && !isNaN(valNum) && depStr.trim() !== '' && val.trim() !== '') {
                if (op === 'gt') return depNum > valNum;
                if (op === 'gte') return depNum >= valNum;
                if (op === 'lt') return depNum < valNum;
                return depNum <= valNum;
            }
            var depNorm = depStr.replace(/-/g, '/');
            var valNorm = val.replace(/-/g, '/');
            if (op === 'gt') return depNorm > valNorm;
            if (op === 'gte') return depNorm >= valNorm;
            if (op === 'lt') return depNorm < valNorm;
            return depNorm <= valNorm;
        }
        return depStr === val;
    }

    function visibleQuestions() {
        return questions.filter(function (q) {
            var c = q.settings && q.settings.conditional;
            if (!c || !c.enabled || !c.question_id) return true;
            return evaluateConditional(c, answers[c.question_id]);
        });
    }

    var STAR_SVG = '<svg class="sc-star-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';

    function bindStarRating(containerEl) {
        var wrap = containerEl.querySelector('.sc-star-rating-input');
        if (!wrap) return;
        var stars = wrap.querySelectorAll('.sc-star-btn');
        var hidden = wrap.querySelector('input[type="hidden"]');
        var label = wrap.querySelector('.sc-star-rating-label');
        var max = stars.length;

        function paint(value) {
            var v = parseInt(value, 10) || 0;
            stars.forEach(function (btn) {
                var n = parseInt(btn.getAttribute('data-value'), 10);
                btn.classList.toggle('is-on', n <= v);
                btn.classList.toggle('is-active', n === v && v > 0);
            });
            if (label) {
                if (v > 0) {
                    label.innerHTML = '<span class="sc-star-rating-score">' + v + '</span><span class="sc-star-rating-of"> از ' + max + '</span>';
                } else {
                    label.textContent = 'برای امتیازدهی روی ستاره‌ها بزنید';
                }
            }
        }

        stars.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var val = btn.getAttribute('data-value');
                if (hidden) hidden.value = val;
                paint(val);
            });
            btn.addEventListener('mouseenter', function () {
                paint(btn.getAttribute('data-value'));
            });
        });

        wrap.addEventListener('mouseleave', function () {
            paint(hidden ? hidden.value : 0);
        });

        paint(hidden ? hidden.value : 0);
    }

    function getDefaultDateValue(existingVal) {
        if (existingVal) {
            return existingVal;
        }
        return cfg.todayShamsi || '';
    }

    function initDateField(q) {
        var dateEl = form.querySelector('[name="answers[' + q.id + ']"]');
        if (!dateEl) {
            return;
        }
        if (!dateEl.value && cfg.todayShamsi) {
            dateEl.value = cfg.todayShamsi;
        }
        if (dateEl.value) {
            answers[q.id] = dateEl.value;
        }
        if (typeof window.initPersianDatePicker === 'function') {
            window.initPersianDatePicker();
        }
        if (!dateEl.value && cfg.todayShamsi) {
            dateEl.value = cfg.todayShamsi;
            answers[q.id] = cfg.todayShamsi;
        }
    }

    function renderStep() {
        var vis = visibleQuestions();
        if (!vis.length) return;
        if (step >= vis.length) step = vis.length - 1;
        if (step < 0) step = 0;
        var q = vis[step];
        var html = '<div class="sc-survey-step"><h3>' + escapeHtml(q.question_text) + (q.settings && q.settings.required ? ' <span class="req">*</span>' : '') + '</h3>';
        var name = 'answers[' + q.id + ']';
        var val = answers[q.id];

        switch (q.question_type) {
            case 'textarea':
                html += '<textarea name="' + name + '" rows="4" class="sc-survey-input">' + escapeHtml(val || '') + '</textarea>';
                break;
            case 'number':
                html += '<input type="number" name="' + name + '" class="sc-survey-input" value="' + escapeAttr(val || '') + '">';
                break;
            case 'single_choice':
                (q.options.choices || []).forEach(function (ch) {
                    html += '<label class="sc-survey-choice"><input type="radio" name="' + name + '" value="' + escapeAttr(ch) + '"' + (val === ch ? ' checked' : '') + '> ' + escapeHtml(ch) + '</label>';
                });
                break;
            case 'multiple_choice':
                (q.options.choices || []).forEach(function (ch) {
                    var checked = Array.isArray(val) && val.indexOf(ch) !== -1;
                    html += '<label class="sc-survey-choice"><input type="checkbox" name="' + name + '[]" value="' + escapeAttr(ch) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(ch) + '</label>';
                });
                break;
            case 'yes_no':
                html += '<label class="sc-survey-choice"><input type="radio" name="' + name + '" value="yes"' + (val === 'yes' ? ' checked' : '') + '> بله</label>';
                html += '<label class="sc-survey-choice"><input type="radio" name="' + name + '" value="no"' + (val === 'no' ? ' checked' : '') + '> خیر</label>';
                break;
            case 'rating':
            case 'scale':
                var max = (q.options && q.options.max) ? parseInt(q.options.max, 10) : 5;
                if (isNaN(max) || max < 1) max = 5;
                if (max > 10) max = 10;
                html += '<div class="sc-star-rating-input" data-max="' + max + '">';
                html += '<input type="hidden" name="' + name + '" value="' + escapeAttr(val || '') + '">';
                html += '<div class="sc-star-rating-stars">';
                for (var i = 1; i <= max; i++) {
                    html += '<button type="button" class="sc-star-btn" data-value="' + i + '" aria-label="' + i + ' از ' + max + '">' + STAR_SVG + '</button>';
                }
                html += '</div>';
                html += '<div class="sc-star-rating-label">' + (val ? ('<span class="sc-star-rating-score">' + val + '</span><span class="sc-star-rating-of"> از ' + max + '</span>') : 'برای امتیازدهی روی ستاره‌ها بزنید') + '</div>';
                html += '</div>';
                break;
            case 'date':
                html += '<input type="text" name="' + name + '" class="sc-survey-input persian-date-input sc-survey-date-input" value="' + escapeAttr(getDefaultDateValue(val)) + '" placeholder="انتخاب تاریخ" readonly>';
                break;
            case 'file':
                html += '<input type="file" name="survey_file_' + q.id + '" class="sc-survey-file">';
                break;
            default:
                html += '<input type="text" name="' + name + '" class="sc-survey-input" value="' + escapeAttr(val || '') + '">';
        }
        html += '</div>';
        container.innerHTML = html;

        bindStarRating(container);

        if (q.question_type === 'date') {
            if (!val && cfg.todayShamsi) {
                answers[q.id] = cfg.todayShamsi;
            }
            setTimeout(function () {
                initDateField(q);
            }, 30);
        }

        var pct = Math.round(((step + 1) / vis.length) * 100);
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressText) progressText.textContent = 'مرحله ' + (step + 1) + ' از ' + vis.length;

        if (prevBtn) prevBtn.disabled = step === 0;
        if (nextBtn) nextBtn.style.display = step < vis.length - 1 ? 'inline-block' : 'none';
        if (submitBtn) submitBtn.style.display = step === vis.length - 1 ? 'inline-block' : 'none';
    }

    function saveCurrent() {
        var vis = visibleQuestions();
        var q = vis[step];
        if (!q) return true;
        if (q.question_type === 'multiple_choice') {
            var boxes = form.querySelectorAll('[name="answers[' + q.id + '][]"]:checked');
            answers[q.id] = Array.prototype.map.call(boxes, function (b) { return b.value; });
        } else if (q.question_type === 'file') {
            return true;
        } else if (q.question_type === 'rating' || q.question_type === 'scale') {
            var hidden = form.querySelector('.sc-star-rating-input input[type="hidden"][name="answers[' + q.id + ']"]');
            answers[q.id] = hidden ? hidden.value : '';
        } else {
            var el = form.querySelector('[name="answers[' + q.id + ']"]');
            answers[q.id] = el ? el.value : '';
        }
        if (q.settings && q.settings.required) {
            var v = answers[q.id];
            if (q.question_type === 'file') {
                var f = form.querySelector('[name="survey_file_' + q.id + '"]');
                if (!f || !f.files || !f.files.length) { alert('این سوال اجباری است.'); return false; }
            } else if (v === '' || v === null || (Array.isArray(v) && !v.length)) {
                alert('لطفاً به این سوال پاسخ دهید.');
                return false;
            }
        }
        return true;
    }

    function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escapeAttr(s) { return escapeHtml(s).replace(/"/g,'&quot;'); }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () { if (step > 0) { step--; renderStep(); } });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () { if (!saveCurrent()) return; step++; renderStep(); });
    }

    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (e.target && e.target.tagName === 'TEXTAREA') return;
        e.preventDefault();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!saveCurrent()) return;
        var fd = new FormData(form);
        fd.append('action', 'sc_submit_survey_wizard');
        fd.append('nonce', cfg.nonce);
        Object.keys(answers).forEach(function (k) {
            if (Array.isArray(answers[k])) {
                answers[k].forEach(function (v) { fd.append('answers[' + k + '][]', v); });
            } else if (answers[k] !== undefined && answers[k] !== '') {
                fd.set('answers[' + k + ']', answers[k]);
            }
        });
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    form.style.display = 'none';
                    var nav = document.querySelector('.sc-survey-nav');
                    if (nav) nav.style.display = 'none';
                    var header = document.querySelector('.sc-survey-header-inline, .sc-survey-progress-card');
                    if (header) header.style.display = 'none';
                    if (thankPanel) {
                        thankPanel.style.display = 'block';
                        var customEl = document.getElementById('sc-survey-thankyou-custom');
                        var msg = (res.data && res.data.thank_you_message) ? String(res.data.thank_you_message).trim() : (cfg.thankYouMessage || '');
                        if (customEl && msg) {
                            customEl.innerHTML = msg.replace(/\n/g, '<br>');
                            customEl.style.display = 'block';
                        }
                    }
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'خطا در ثبت');
                }
            })
            .catch(function () { alert('خطا در ارتباط با سرور'); });
    });

    // ============================================================
    // Mobile Verification Flow (for public surveys)
    // ============================================================
    var verificationCard = document.getElementById('sc-survey-mobile-verification');
    var verifiedPhone = null;

    function startSurveyAfterVerification(phone) {
        verifiedPhone = phone;

        // Hide verification card
        if (verificationCard) {
            verificationCard.style.display = 'none';
        }

        // Show the form and start wizard
        if (form) {
            form.style.display = '';
        }

        // If guest info fields exist, make sure they are visible
        var guestInfo = document.querySelector('.sc-survey-guest-info');
        if (guestInfo) {
            guestInfo.style.display = '';
        }

        // Pre-fill the phone if guest field exists
        var phoneInput = form.querySelector('input[name="guest_phone"]');
        if (phoneInput && phone) {
            phoneInput.value = phone;
        }

        renderStep();
    }

    if (verificationCard) {
        // Hide the form initially until verification is done
        if (form) {
            form.style.display = 'none';
        }

        var sendBtn = document.getElementById('sc-survey-send-code-btn');
        var verifyBtn = document.getElementById('sc-survey-verify-code-btn');
        var resendBtn = document.getElementById('sc-survey-resend-code-btn');
        var phoneInput = document.getElementById('sc-survey-guest-phone-input');
        var codeInput = document.getElementById('sc-survey-verification-code-input');
        var statusEl = document.getElementById('sc-survey-verification-status');
        var step1 = document.getElementById('sc-survey-mobile-step-1');
        var step2 = document.getElementById('sc-survey-mobile-step-2');

        function setStatus(msg, isError) {
            if (!statusEl) return;
            statusEl.textContent = msg;
            statusEl.className = 'sc-survey-verification-status ' + (isError ? 'error' : 'success');
        }

        if (sendBtn && phoneInput) {
            sendBtn.addEventListener('click', function () {
                var phone = phoneInput.value.trim();
                if (!phone) {
                    alert('لطفاً شماره موبایل را وارد کنید.');
                    return;
                }

                sendBtn.disabled = true;
                sendBtn.textContent = 'در حال ارسال...';

                var data = new FormData();
                data.append('action', 'sc_survey_guest_send_mobile_code');
                data.append('survey_id', cfg.surveyId || (new URLSearchParams(window.location.search)).get('survey_id') || 0);
                data.append('phone', phone);
                data.append('nonce', cfg.nonce);

                fetch(cfg.ajaxUrl, {
                    method: 'POST',
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'ارسال کد تأیید';

                    if (res.success) {
                        step1.style.display = 'none';
                        step2.style.display = '';
                        setStatus('کد ارسال شد. لطفاً آن را وارد کنید.', false);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'خطا در ارسال کد');
                    }
                })
                .catch(() => {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'ارسال کد تأیید';
                    alert('خطا در ارتباط با سرور');
                });
            });
        }

        if (verifyBtn && codeInput) {
            verifyBtn.addEventListener('click', function () {
                var phone = phoneInput ? phoneInput.value.trim() : '';
                var code = codeInput.value.trim();

                if (!code) {
                    alert('لطفاً کد تأیید را وارد کنید.');
                    return;
                }

                verifyBtn.disabled = true;
                verifyBtn.textContent = 'در حال بررسی...';

                var data = new FormData();
                data.append('action', 'sc_survey_guest_verify_mobile_code');
                data.append('survey_id', cfg.surveyId || 0);
                data.append('phone', phone);
                data.append('code', code);
                data.append('nonce', cfg.nonce);

                fetch(cfg.ajaxUrl, {
                    method: 'POST',
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'تأیید کد';

                    if (res.success) {
                        setStatus('تأیید شد! در حال بارگذاری نظرسنجی...', false);
                        // Proceed to survey
                        setTimeout(function () {
                            startSurveyAfterVerification(phone);
                        }, 600);
                    } else {
                        setStatus(res.data && res.data.message ? res.data.message : 'کد صحیح نیست.', true);
                    }
                })
                .catch(() => {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'تأیید کد';
                    setStatus('خطا در ارتباط با سرور', true);
                });
            });
        }

        if (resendBtn) {
            resendBtn.addEventListener('click', function () {
                step2.style.display = 'none';
                step1.style.display = '';
                if (statusEl) statusEl.textContent = '';
                if (codeInput) codeInput.value = '';
            });
        }
    } else {
        // No mobile verification required — start normally
        renderStep();
    }
})();
