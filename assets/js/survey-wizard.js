(function () {
    'use strict';
    var cfg = window.scSurveyWizard || {};
    var questions = Array.isArray(cfg.questions) ? cfg.questions : [];
    var step = 0;
    var answers = {};

    var container = document.getElementById('sc-survey-step-container');
    var prevBtn = document.getElementById('sc-survey-prev');
    var nextBtn = document.getElementById('sc-survey-next');
    var submitBtn = document.getElementById('sc-survey-submit');
    var form = document.getElementById('sc-survey-wizard-form');
    var progressFill = document.getElementById('scSurveyProgressFill');
    var progressText = document.getElementById('scSurveyProgressText');
    var progressSteps = document.getElementById('scSurveyProgressSteps');
    var thankPanel = document.getElementById('sc-survey-thankyou-panel');

    if (!container || !form) {
        return;
    }

    if (prevBtn) prevBtn.style.display = '';
    if (nextBtn) nextBtn.style.display = '';

    function answerKey(qid) {
        return String(qid);
    }

    function getAnswer(qid) {
        var key = answerKey(qid);
        if (Object.prototype.hasOwnProperty.call(answers, key)) {
            return answers[key];
        }
        return undefined;
    }

    function setAnswer(qid, val) {
        answers[answerKey(qid)] = val;
    }

    function isConditionalEnabled(c) {
        if (!c || typeof c !== 'object') {
            return false;
        }
        return c.enabled === true || c.enabled === 1 || c.enabled === '1' || c.enabled === 'true';
    }

    function resolveDepQuestionId(c) {
        if (!c || c.question_id == null || c.question_id === '') {
            return 0;
        }
        var raw = String(c.question_id).trim();
        var m = raw.match(/^new_(\d+)$/);
        if (m) {
            var idx = parseInt(m[1], 10);
            if (!isNaN(idx) && questions[idx] && questions[idx].id) {
                return parseInt(questions[idx].id, 10) || 0;
            }
            return 0;
        }
        var n = parseInt(raw, 10);
        return isNaN(n) ? 0 : n;
    }

    function toEnglishDigits(str) {
        return String(str == null ? '' : str)
            .replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
            .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); });
    }

    function isYesNoToken(s) {
        var v = String(s || '').trim().toLowerCase();
        return ['yes', 'no', 'true', 'false', 'بله', 'بلی', 'آره', 'خیر', 'نه'].indexOf(v) !== -1
            || ['بله', 'بلی', 'آره', 'خیر', 'نه'].indexOf(String(s || '').trim()) !== -1;
    }

    function normalizeCompareValue(v, forNumeric) {
        var s = toEnglishDigits(v).trim();
        if (forNumeric) {
            return s;
        }
        // Only map yes/no words — never map bare 0/1 (breaks numeric conditions).
        var map = {
            'بله': 'yes',
            'بلی': 'yes',
            'آره': 'yes',
            'خیر': 'no',
            'نه': 'no',
            'yes': 'yes',
            'no': 'no',
            'true': 'yes',
            'false': 'no'
        };
        if (Object.prototype.hasOwnProperty.call(map, s)) {
            return map[s];
        }
        var lower = s.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(map, lower)) {
            return map[lower];
        }
        return s;
    }

    function evaluateConditional(c, dep) {
        if (dep === undefined || dep === null) {
            return false;
        }
        var op = (c && c.operator) ? String(c.operator) : 'equals';
        var isNumericOp = op === 'gt' || op === 'gte' || op === 'lt' || op === 'lte';
        var depStr = Array.isArray(dep)
            ? dep.map(function (x) { return normalizeCompareValue(x, isNumericOp); }).join(',')
            : normalizeCompareValue(dep, isNumericOp);
        var val = normalizeCompareValue(c && c.value != null ? c.value : '', isNumericOp);

        if (depStr === '' && op !== 'equals') {
            return false;
        }

        if (op === 'not_equals') {
            if (depStr === '') return false;
            return normalizeCompareValue(depStr, false) !== normalizeCompareValue(val, false);
        }
        if (op === 'contains') {
            return depStr !== '' && depStr.indexOf(val) !== -1;
        }
        if (isNumericOp) {
            if (depStr === '' || val === '') return false;
            var depNum = parseFloat(depStr);
            var valNum = parseFloat(val);
            if (!isNaN(depNum) && !isNaN(valNum)) {
                if (op === 'gt') return depNum > valNum;
                if (op === 'gte') return depNum >= valNum;
                if (op === 'lt') return depNum < valNum;
                return depNum <= valNum;
            }
            // Fallback string compare (e.g. dates)
            var depNorm = depStr.replace(/-/g, '/');
            var valNorm = val.replace(/-/g, '/');
            if (op === 'gt') return depNorm > valNorm;
            if (op === 'gte') return depNorm >= valNorm;
            if (op === 'lt') return depNorm < valNorm;
            return depNorm <= valNorm;
        }

        // equals (default) — soft yes/no matching
        if (isYesNoToken(depStr) || isYesNoToken(val)) {
            return normalizeCompareValue(depStr, false) === normalizeCompareValue(val, false);
        }
        return depStr === val;
    }

    function normalizeQuestionSettings() {
        questions.forEach(function (q) {
            if (!q || typeof q !== 'object') return;
            q.id = parseInt(q.id, 10) || 0;
            if (!q.settings || typeof q.settings !== 'object') {
                q.settings = {};
            }
            var c = q.settings.conditional;
            if (!c || typeof c !== 'object') {
                q.settings.conditional = { enabled: 0, question_id: 0, operator: 'equals', value: '' };
                return;
            }
            q.settings.conditional = {
                enabled: (c.enabled === true || c.enabled === 1 || c.enabled === '1' || c.enabled === 'true') ? 1 : 0,
                question_id: resolveDepQuestionId(c),
                operator: c.operator || 'equals',
                value: c.value == null ? '' : String(c.value)
            };
        });
    }

    normalizeQuestionSettings();

    function isQuestionVisible(q) {
        var c = q && q.settings ? q.settings.conditional : null;
        if (!isConditionalEnabled(c)) {
            return true;
        }
        var depId = parseInt(c.question_id, 10) || 0;
        if (!depId) {
            return false;
        }
        return evaluateConditional(c, getAnswer(depId));
    }

    function visibleQuestions() {
        return questions.filter(isQuestionVisible);
    }

    function hasValue(v) {
        if (Array.isArray(v)) {
            return v.length > 0;
        }
        return v !== '' && v !== null && v !== undefined;
    }

    var STAR_SVG = '<svg class="sc-star-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

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
                var qid = wrap.getAttribute('data-qid');
                if (!qid) {
                    var block = wrap.closest('.sc-survey-q-block');
                    qid = block ? block.getAttribute('data-qid') : '';
                }
                if (qid) {
                    setAnswer(qid, val);
                    updateProgressUi();
                }
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
        if (existingVal) return existingVal;
        return cfg.todayShamsi || '';
    }

    function initDateField(q) {
        var dateEl = form.querySelector('[name="answers[' + q.id + ']"]');
        if (!dateEl) return;
        if (!dateEl.value && cfg.todayShamsi) {
            dateEl.value = cfg.todayShamsi;
        }
        if (dateEl.value) {
            setAnswer(q.id, dateEl.value);
        }
        if (typeof window.initPersianDatePicker === 'function') {
            window.initPersianDatePicker();
        }
        if (!dateEl.value && cfg.todayShamsi) {
            dateEl.value = cfg.todayShamsi;
            setAnswer(q.id, cfg.todayShamsi);
        }
    }

    function updateProgressUi(vis) {
        if (!vis) vis = visibleQuestions();
        var total = vis.length;
        var current = total ? (step + 1) : 0;

        var pct = total ? Math.round((current / total) * 100) : 0;
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressText) {
            progressText.textContent = total ? ('سوال ' + current + ' از ' + total) : 'سوالی برای نمایش نیست';
        }

        if (progressSteps) {
            var html = '';
            vis.forEach(function (q, i) {
                var done = hasValue(getAnswer(q.id));
                var cls = 'sc-survey-progress-dot';
                if (done) cls += ' is-done';
                if (i === step) cls += ' is-current';
                html += '<span class="' + cls + '" data-step="' + i + '" aria-label="سوال ' + (i + 1) + '">' + (i + 1) + '</span>';
            });
            progressSteps.innerHTML = html;
        }

        // Keep nav buttons in sync when conditionals unlock/hide questions.
        if (prevBtn) prevBtn.disabled = step <= 0;
        if (nextBtn) nextBtn.style.display = (total > 0 && step < total - 1) ? 'inline-flex' : 'none';
        if (submitBtn) submitBtn.style.display = (total > 0 && step >= total - 1) ? 'inline-flex' : 'none';
    }

    function readAnswerFromDom(q) {
        if (!q) return undefined;
        if (q.question_type === 'multiple_choice') {
            var boxes = form.querySelectorAll('[name="answers[' + q.id + '][]"]:checked');
            return Array.prototype.map.call(boxes, function (b) { return b.value; });
        }
        if (q.question_type === 'file') {
            var f = form.querySelector('[name="survey_file_' + q.id + '"]');
            return (f && f.files && f.files.length) ? '__file__' : '';
        }
        if (q.question_type === 'rating' || q.question_type === 'scale') {
            var hidden = form.querySelector('.sc-star-rating-input input[type="hidden"][name="answers[' + q.id + ']"]');
            return hidden ? hidden.value : '';
        }
        if (q.question_type === 'single_choice' || q.question_type === 'yes_no') {
            var checked = form.querySelector('[name="answers[' + q.id + ']"]:checked');
            return checked ? checked.value : '';
        }
        var el = form.querySelector('[name="answers[' + q.id + ']"]');
        if (!el) return '';
        var raw = el.value;
        if (q.question_type === 'number') {
            return toEnglishDigits(raw).trim();
        }
        return raw;
    }

    function syncCurrentAnswer() {
        var vis = visibleQuestions();
        var q = vis[step];
        if (!q) return null;
        setAnswer(q.id, readAnswerFromDom(q));
        return q;
    }

    function renderStep() {
        var vis = visibleQuestions();
        if (!vis.length) {
            container.innerHTML = '<div class="sc-survey-step"><p class="description">سوالی برای نمایش با شرایط فعلی وجود ندارد.</p></div>';
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'inline-flex';
            updateProgressUi(vis);
            return;
        }
        if (step >= vis.length) step = vis.length - 1;
        if (step < 0) step = 0;

        var q = vis[step];
        var html = '<div class="sc-survey-step sc-survey-q-block" data-qid="' + q.id + '">';
        html += '<div class="sc-survey-q-block-head">';
        html += '<span class="sc-survey-q-block-num">سوال ' + (step + 1) + ' از ' + vis.length + '</span>';
        html += '<h3>' + escapeHtml(q.question_text) + (q.settings && q.settings.required ? ' <span class="req">*</span>' : '') + '</h3>';
        html += '</div><div class="sc-survey-q-block-body">';

        var name = 'answers[' + q.id + ']';
        var val = getAnswer(q.id);

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
        html += '</div></div>';
        container.innerHTML = html;

        bindStarRating(container);

        if (q.question_type === 'date') {
            if (!val && cfg.todayShamsi) {
                setAnswer(q.id, cfg.todayShamsi);
            }
            setTimeout(function () {
                initDateField(q);
                updateProgressUi();
            }, 30);
        }

        if (prevBtn) prevBtn.disabled = step === 0;
        if (nextBtn) nextBtn.style.display = step < vis.length - 1 ? 'inline-flex' : 'none';
        if (submitBtn) submitBtn.style.display = step === vis.length - 1 ? 'inline-flex' : 'none';

        updateProgressUi(vis);
    }

    function saveCurrent() {
        var q = syncCurrentAnswer();
        if (!q) return true;

        // Hidden conditional questions are never required.
        if (!isQuestionVisible(q)) {
            return true;
        }

        if (q.settings && q.settings.required) {
            var v = getAnswer(q.id);
            if (q.question_type === 'file') {
                var f = form.querySelector('[name="survey_file_' + q.id + '"]');
                if (!f || !f.files || !f.files.length) {
                    alert('این سوال اجباری است.');
                    return false;
                }
            } else if (!hasValue(v)) {
                alert('لطفاً به این سوال پاسخ دهید.');
                return false;
            }
        }
        return true;
    }

    function validateAllVisibleRequired() {
        syncCurrentAnswer();
        var vis = visibleQuestions();
        for (var i = 0; i < vis.length; i++) {
            var q = vis[i];
            if (!(q.settings && q.settings.required)) {
                continue;
            }
            var v = getAnswer(q.id);
            if (q.question_type === 'file') {
                var f = form.querySelector('[name="survey_file_' + q.id + '"]');
                if (!f || !f.files || !f.files.length) {
                    step = i;
                    renderStep();
                    alert('این سوال اجباری است.');
                    return false;
                }
            } else if (!hasValue(v)) {
                step = i;
                renderStep();
                alert('لطفاً به این سوال پاسخ دهید.');
                return false;
            }
        }
        return true;
    }

    function goNext() {
        if (!saveCurrent()) return;

        // Visibility may change after saving the current answer (conditionals).
        var visAfter = visibleQuestions();
        var block = container.querySelector('.sc-survey-q-block');
        var currentId = block ? block.getAttribute('data-qid') : null;

        var newIndex = step + 1;
        if (currentId != null) {
            newIndex = step + 1;
            for (var i = 0; i < visAfter.length; i++) {
                if (String(visAfter[i].id) === String(currentId)) {
                    newIndex = i + 1;
                    break;
                }
            }
        }

        if (newIndex >= visAfter.length) {
            step = Math.max(0, visAfter.length - 1);
            renderStep();
            return;
        }
        step = newIndex;
        renderStep();
    }

    function goPrev() {
        syncCurrentAnswer();
        var visAfter = visibleQuestions();
        var block = container.querySelector('.sc-survey-q-block');
        var currentId = block ? block.getAttribute('data-qid') : null;
        var newIndex = step - 1;
        if (currentId != null) {
            for (var i = 0; i < visAfter.length; i++) {
                if (String(visAfter[i].id) === String(currentId)) {
                    newIndex = i - 1;
                    break;
                }
            }
        }
        step = Math.max(0, newIndex);
        renderStep();
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            goPrev();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            goNext();
        });
    }

    function onAnswerEdited() {
        syncCurrentAnswer();
        // Clamp step if current question got hidden (shouldn't happen for current dep target).
        var vis = visibleQuestions();
        if (step >= vis.length) {
            step = Math.max(0, vis.length - 1);
            renderStep();
            return;
        }
        updateProgressUi(vis);
    }

    container.addEventListener('change', onAnswerEdited);
    container.addEventListener('input', onAnswerEdited);

    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (e.target && e.target.tagName === 'TEXTAREA') return;
        e.preventDefault();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!saveCurrent()) return;
        if (!validateAllVisibleRequired()) return;

        var vis = visibleQuestions();
        var visibleIds = {};
        vis.forEach(function (q) { visibleIds[String(q.id)] = true; });

        // Drop answers for questions that are not currently visible.
        Object.keys(answers).forEach(function (k) {
            if (!visibleIds[String(k)]) {
                delete answers[k];
            }
        });

        var fd = new FormData(form);
        fd.append('action', 'sc_submit_survey_wizard');
        fd.append('nonce', cfg.nonce);

        // Clear any leftover answer fields, then send only visible ones.
        Object.keys(answers).forEach(function (k) {
            fd.delete('answers[' + k + ']');
            fd.delete('answers[' + k + '][]');
        });
        // Also strip any answers[*] that may remain from the form DOM.
        if (typeof fd.keys === 'function') {
            var toDelete = [];
            fd.forEach(function (_val, key) {
                if (String(key).indexOf('answers[') === 0) {
                    toDelete.push(key);
                }
            });
            toDelete.forEach(function (key) { fd.delete(key); });
        }

        vis.forEach(function (q) {
            var val = getAnswer(q.id);
            if (Array.isArray(val)) {
                val.forEach(function (v) { fd.append('answers[' + q.id + '][]', v); });
            } else if (val !== undefined && val !== '') {
                fd.set('answers[' + q.id + ']', val);
            }
        });

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ثبت...';
        }

        fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'ثبت نهایی';
                }
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
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'ثبت نهایی';
                }
                alert('خطا در ارتباط با سرور');
            });
    });

    // Mobile verification
    var verificationCard = document.getElementById('sc-survey-mobile-verification');

    function startSurveyAfterVerification(phone) {
        if (verificationCard) verificationCard.style.display = 'none';
        if (form) form.style.display = '';
        var guestInfo = document.querySelector('.sc-survey-guest-info');
        if (guestInfo) guestInfo.style.display = '';
        var phoneInput = form.querySelector('input[name="guest_phone"]');
        if (phoneInput && phone) phoneInput.value = phone;
        step = 0;
        renderStep();
    }

    if (verificationCard) {
        if (form) form.style.display = 'none';

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
                data.append('survey_id', cfg.surveyId || 0);
                data.append('phone', phone);
                data.append('nonce', cfg.nonce);
                fetch(cfg.ajaxUrl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
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
                    .catch(function () {
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
                fetch(cfg.ajaxUrl, { method: 'POST', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'تأیید کد';
                        if (res.success) {
                            setStatus('تأیید شد! در حال بارگذاری نظرسنجی...', false);
                            setTimeout(function () {
                                startSurveyAfterVerification(phone);
                            }, 600);
                        } else {
                            setStatus(res.data && res.data.message ? res.data.message : 'کد صحیح نیست.', true);
                        }
                    })
                    .catch(function () {
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
        renderStep();
    }
})();
