(function (window, document) {
    'use strict';

    function formatPrice(amount) {
        if (!amount || amount <= 0) {
            return '—';
        }
        try {
            return Number(amount).toLocaleString('fa-IR') + ' تومان';
        } catch (e) {
            return String(amount) + ' تومان';
        }
    }

    function resetSelect(sel, placeholder, disabled) {
        if (!sel) {
            return;
        }
        sel.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        sel.appendChild(opt);
        sel.disabled = !!disabled;
    }

    function slotMatchesSelection(slot, chapter, coachId, userFields) {
        userFields = userFields || { chapter: 1, coach: 1 };
        var rowChapter = String(slot.chapter || '').trim();
        var rowCoach = parseInt(slot.coach_id || '0', 10);

        if (userFields.chapter && chapter) {
            if (rowChapter !== '' && rowChapter !== chapter) {
                return false;
            }
        }
        if (userFields.coach && coachId) {
            if (rowCoach > 0 && rowCoach !== coachId) {
                return false;
            }
        }
        if (rowChapter === '' && rowCoach === 0) {
            return false;
        }
        return true;
    }

    function syncSlotCardState(card) {
        if (!card) {
            return;
        }
        var cb = card.querySelector('input[type="checkbox"]');
        card.classList.toggle('is-checked', !!(cb && cb.checked && !cb.disabled));
        card.classList.toggle('is-disabled', !!(cb && cb.disabled));
    }

    window.scInitPrivateBookingForm = function (initConfig) {
        initConfig = initConfig || {};
        var data = initConfig.data || {};
        var ajaxUrl = initConfig.ajaxUrl || '';
        var ajaxNonce = initConfig.ajaxNonce || '';
        var prefill = initConfig.prefill || {};
        var isAdmin = !!initConfig.isAdmin;
        var userFields = initConfig.userFields || { course: 1, chapter: 1, coach: 1, slots: 1, sessions: 1, start_date: 1 };

        var courseSel = document.getElementById('sc_private_course_id');
        var chapterSel = document.getElementById('sc_private_chapter');
        var coachSel = document.getElementById('sc_private_coach_id');
        var slotsWrap = document.getElementById('sc_private_slots_wrap');
        var sessionsSel = document.getElementById('sc_private_sessions_count');
        var sessionsHidden = document.getElementById('sc_private_enrollment_sessions_value');
        var sessionsWrap = document.getElementById('sc_private_sessions_wrap');
        var pickedSessionCount = 0;
        var startDateEl = document.getElementById('sc_private_start_date_shamsi');
        var pricePreview = document.getElementById('sc_private_price_preview');
        var slotsPreviewEl = document.getElementById('sc_private_slots_preview');
        var slotsPreviewRefreshBtn = document.getElementById('sc_private_slots_preview_refresh');
        var sessionsSchedulePreviewEl = document.getElementById('sc_private_sessions_schedule_preview');
        var submitBtn = document.getElementById('sc_private_submit_btn');
        var bookingForm = document.getElementById('sc-private-booking-form');
        var slotCheckTimer = null;
        var slotCheckSeq = 0;
        var slotsAvailabilitySummary = { full: 0, available: 0, checkedFull: 0 };

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
        function getCourseInfo() {
            var cid = getCourseId();
            return cid && data[cid] ? data[cid] : null;
        }

        function populateSessionSelectOptions(info, placeholder) {
            if (!sessionsSel || !info) {
                return;
            }
            placeholder = placeholder || 'تعداد جلسات';
            var keep = pickedSessionCount > 0 ? pickedSessionCount : getSelectedSessionCount();
            sessionsSel.innerHTML = '';
            var emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = placeholder;
            sessionsSel.appendChild(emptyOpt);
            (info.session_options || []).forEach(function (n) {
                var count = parseInt(n, 10);
                if (count <= 0) {
                    return;
                }
                var opt = document.createElement('option');
                opt.value = String(count);
                opt.textContent = count + ' جلسه';
                sessionsSel.appendChild(opt);
            });
            sessionsSel.disabled = false;
            if (keep > 0) {
                setSelectedSessionCount(keep, false);
            }
        }

        function setSelectedSessionCount(count, triggerRefresh) {
            count = parseInt(count, 10) || 0;
            pickedSessionCount = count > 0 ? count : 0;
            if (sessionsHidden) {
                sessionsHidden.value = pickedSessionCount > 0 ? String(pickedSessionCount) : '';
            }
            if (sessionsSel) {
                sessionsSel.disabled = false;
                if (pickedSessionCount > 0) {
                    var hasOption = Array.prototype.some.call(sessionsSel.options, function (optionEl) {
                        return optionEl.value === String(pickedSessionCount);
                    });
                    if (!hasOption) {
                        var dynamicOpt = document.createElement('option');
                        dynamicOpt.value = String(pickedSessionCount);
                        dynamicOpt.textContent = pickedSessionCount + ' جلسه';
                        sessionsSel.appendChild(dynamicOpt);
                    }
                    sessionsSel.value = String(pickedSessionCount);
                } else {
                    sessionsSel.value = '';
                }
            }
            if (sessionsWrap) {
                sessionsWrap.querySelectorAll('.sc-private-session-card').forEach(function (card) {
                    var radio = card.querySelector('input[type="radio"]');
                    var isMatch = radio && parseInt(radio.value, 10) === pickedSessionCount;
                    card.classList.toggle('is-checked', !!isMatch);
                    if (radio) {
                        radio.checked = !!isMatch;
                    }
                });
            }
            if (triggerRefresh !== false) {
                updatePricePreview();
                updateSessionsSchedulePreview(null);
                if (userFields.slots) {
                    refreshSlots();
                } else if (typeof scheduleSlotAvailabilityCheck === 'function') {
                    scheduleSlotAvailabilityCheck();
                }
            }
        }

        function getSelectedSessionCount() {
            if (pickedSessionCount > 0) {
                return pickedSessionCount;
            }
            if (sessionsWrap) {
                var checkedRadio = sessionsWrap.querySelector('input[name="sc_private_session_pick"]:checked');
                if (checkedRadio) {
                    var fromRadio = parseInt(checkedRadio.value, 10);
                    if (fromRadio > 0) {
                        pickedSessionCount = fromRadio;
                        if (sessionsHidden) {
                            sessionsHidden.value = String(fromRadio);
                        }
                        return fromRadio;
                    }
                }
            }
            if (sessionsHidden && sessionsHidden.value) {
                var fromHidden = parseInt(sessionsHidden.value, 10);
                if (fromHidden > 0) {
                    pickedSessionCount = fromHidden;
                    return fromHidden;
                }
            }
            if (sessionsSel && sessionsSel.value) {
                var fromSelect = parseInt(sessionsSel.value, 10);
                if (fromSelect > 0) {
                    pickedSessionCount = fromSelect;
                    if (sessionsHidden) {
                        sessionsHidden.value = String(fromSelect);
                    }
                    return fromSelect;
                }
            }
            return 0;
        }

        function getEffectiveSessionCount() {
            var selected = getSelectedSessionCount();
            if (selected > 0) {
                return selected;
            }
            if (!userFields.sessions) {
                var info = getCourseInfo();
                if (info && info.session_options && info.session_options.length) {
                    var mins = info.session_options.map(function (n) { return parseInt(n, 10); }).filter(function (n) { return n > 0; });
                    if (mins.length) {
                        return Math.min.apply(null, mins);
                    }
                }
                return 1;
            }
            return 0;
        }

        function needsSessionSelectionFirst() {
            return !!userFields.sessions && getSelectedSessionCount() <= 0;
        }

        function togglePreviewRefreshButton(show) {
            if (!slotsPreviewRefreshBtn) {
                return;
            }
            slotsPreviewRefreshBtn.hidden = !show;
        }

        function updateSlotsPreviewBanner(summary, previewSessions) {
            if (!slotsPreviewEl) {
                return;
            }
            summary = summary || { full: 0, available: 0 };
            var full = parseInt(summary.full || '0', 10);
            var available = parseInt(summary.available || '0', 10);
            var sessionsHint = previewSessions ? (' (برای ' + previewSessions + ' جلسه از تاریخ شروع)') : '';

            slotsPreviewEl.classList.remove('is-warning', 'is-ok', 'is-loading', 'is-muted');
            if (full <= 0 && available <= 0) {
                slotsPreviewEl.hidden = true;
                slotsPreviewEl.textContent = '';
                togglePreviewRefreshButton(false);
                return;
            }

            slotsPreviewEl.hidden = false;
            togglePreviewRefreshButton(true);
            if (full > 0 && available > 0) {
                slotsPreviewEl.classList.add('is-warning');
                slotsPreviewEl.innerHTML = '<strong>وضعیت رزرو زمان‌ها' + sessionsHint + ':</strong> '
                    + available + ' بازه قابل رزرو است و '
                    + '<span class="sc-private-slots-preview-full">' + full + ' بازه پر شده</span>'
                    + ' — بازه‌های پر شده را نمی‌توانید انتخاب کنید.';
            } else if (full > 0) {
                slotsPreviewEl.classList.add('is-warning');
                slotsPreviewEl.innerHTML = '<strong>وضعیت رزرو زمان‌ها' + sessionsHint + ':</strong> '
                    + 'همه بازه‌های نمایش‌داده‌شده پر شده‌اند و فعلاً قابل رزرو نیستند.';
            } else {
                slotsPreviewEl.classList.add('is-ok');
                slotsPreviewEl.innerHTML = '<strong>وضعیت رزرو زمان‌ها' + sessionsHint + ':</strong> '
                    + available + ' بازه برای رزرو در دسترس است.';
            }
        }

        function renderSlotConflictDetail(card, slotInfo) {
            var detailEl = card.querySelector('.sc-private-slot-conflict-detail');
            if (!detailEl) {
                return;
            }
            if (!slotInfo || !slotInfo.full) {
                detailEl.hidden = true;
                detailEl.innerHTML = '';
                return;
            }

            var html = '';
            if (isAdmin) {
                if (slotInfo.admin_message) {
                    html += '<p class="sc-private-slot-conflict-text">' + escapeHtml(slotInfo.admin_message) + '</p>';
                }
                if (slotInfo.admin_suggestion) {
                    html += '<p class="sc-private-slot-conflict-tip">' + escapeHtml(slotInfo.admin_suggestion) + '</p>';
                }
            } else if (slotInfo.user_suggestion) {
                html += '<p class="sc-private-slot-conflict-tip">' + escapeHtml(slotInfo.user_suggestion) + '</p>';
            }

            if (!html && slotInfo.conflicts && slotInfo.conflicts.length) {
                slotInfo.conflicts.forEach(function (conflict) {
                    if (conflict.message) {
                        html += '<p class="sc-private-slot-conflict-text">' + escapeHtml(conflict.message) + '</p>';
                    }
                });
            }

            if (html) {
                detailEl.hidden = false;
                detailEl.innerHTML = html;
            } else {
                detailEl.hidden = true;
                detailEl.innerHTML = '';
            }
        }

        function updateSessionsSchedulePreview(scheduleData) {
            if (!sessionsSchedulePreviewEl) {
                return;
            }
            if (!scheduleData || !scheduleData.items || !scheduleData.items.length) {
                sessionsSchedulePreviewEl.hidden = true;
                sessionsSchedulePreviewEl.innerHTML = '';
                return;
            }

            var sessionCount = parseInt(
                scheduleData.requested_sessions || scheduleData.sessions_count || scheduleData.items.length || '0',
                10
            );
            var titleBase = scheduleData.title || 'پیش‌نمایش جلسات آینده';
            var title = sessionCount > 0 ? (titleBase + ' (' + sessionCount + ' جلسه)') : titleBase;

            var html = '<div class="sc-private-schedule-preview-panel">';
            html += '<div class="sc-private-schedule-preview-title">' + escapeHtml(title) + '</div>';
            html += '<div class="sc-private-schedule-preview-grid">';
            scheduleData.items.forEach(function (item, index) {
                html += '<div class="sc-private-schedule-preview-card">'
                    + '<span class="sc-private-schedule-preview-card-index">' + (index + 1) + '</span>'
                    + '<span class="sc-private-schedule-preview-card-label">' + escapeHtml(item.label || '') + '</span>'
                    + '</div>';
            });
            html += '</div></div>';
            sessionsSchedulePreviewEl.hidden = false;
            sessionsSchedulePreviewEl.innerHTML = html;
        }

        function countCheckedFullSlots() {
            if (!slotsWrap) {
                return 0;
            }
            var count = 0;
            slotsWrap.querySelectorAll('.sc-private-slot-card input[type="checkbox"]:checked').forEach(function (cb) {
                if (cb.disabled || (cb.closest('.sc-private-slot-card') && cb.closest('.sc-private-slot-card').dataset.slotFull === '1')) {
                    count++;
                }
            });
            return count;
        }

        function updateSubmitState() {
            if (!submitBtn) {
                return;
            }
            var checkedFull = countCheckedFullSlots();
            slotsAvailabilitySummary.checkedFull = checkedFull;
            var block = checkedFull > 0;
            submitBtn.disabled = block;
            submitBtn.setAttribute('aria-disabled', block ? 'true' : 'false');
            if (block) {
                submitBtn.title = 'یک یا چند بازه زمانی انتخابی پر شده است.';
            } else {
                submitBtn.removeAttribute('title');
            }
        }

        function applySlotAvailabilityResults(json) {
            if (!slotsWrap) {
                return;
            }
            var allCards = slotsWrap.querySelectorAll('.sc-private-slot-card');
            var summary = (json && json.data && json.data.summary) ? json.data.summary : { full: 0, available: 0 };
            var previewSessions = summary.preview_sessions || getEffectiveSessionCount();

            if (!json || !json.success || !json.data || !json.data.slots) {
                updateSlotsPreviewBanner(null);
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                return;
            }

            allCards.forEach(function (card) {
                var cb = card.querySelector('input[type="checkbox"]');
                if (!cb) {
                    return;
                }
                var slotId = parseInt(cb.value, 10);
                var slotInfo = json.data.slots[slotId] || json.data.slots[String(slotId)];
                var badge = card.querySelector('.sc-private-slot-status-badge');
                if (slotInfo && slotInfo.unknown) {
                    card.dataset.slotFull = '0';
                    cb.disabled = false;
                    if (badge) {
                        badge.textContent = 'نیاز به تکمیل اطلاعات';
                        badge.className = 'sc-private-slot-status-badge is-unknown sc-private-slot-status';
                    }
                    renderSlotConflictDetail(card, null);
                } else if (slotInfo && slotInfo.full) {
                    card.dataset.slotFull = '1';
                    cb.checked = false;
                    cb.disabled = true;
                    if (badge) {
                        badge.textContent = 'پر شده';
                        badge.className = 'sc-private-slot-status-badge is-full sc-private-slot-status';
                    }
                    renderSlotConflictDetail(card, slotInfo);
                } else {
                    card.dataset.slotFull = '0';
                    cb.disabled = false;
                    if (badge) {
                        badge.textContent = 'قابل رزرو';
                        badge.className = 'sc-private-slot-status-badge is-available sc-private-slot-status';
                    }
                    renderSlotConflictDetail(card, null);
                }
                syncSlotCardState(card);
            });

            slotsAvailabilitySummary.full = parseInt(summary.full || '0', 10);
            slotsAvailabilitySummary.available = parseInt(summary.available || '0', 10);
            updateSlotsPreviewBanner(summary, previewSessions);
            updateSessionsSchedulePreview(json.data.session_schedule || null);
            updateSubmitState();
        }

        function getSelectedSlotIds() {
            if (!slotsWrap) {
                return [];
            }
            return Array.from(slotsWrap.querySelectorAll('input[name="schedule_slot_ids[]"]:checked:not(:disabled)')).map(function (el) {
                return parseInt(el.value, 10);
            }).filter(function (id) { return id > 0; });
        }

        function updatePricePreview() {
            if (!pricePreview) {
                return;
            }
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var coachId = coachSel ? parseInt(coachSel.value || '0', 10) : 0;
            var sessions = sessionsSel ? parseInt(sessionsSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;
            if (!info || !chapter || !coachId || sessions <= 0) {
                pricePreview.textContent = '—';
                return;
            }
            var amount = 0;
            if (info.variable_coach_pricing) {
                (info.chapters || []).forEach(function (ch) {
                    if (ch.name !== chapter) {
                        return;
                    }
                    (ch.coaches || []).forEach(function (co) {
                        if (parseInt(co.id, 10) === coachId) {
                            amount = parseFloat(co.price_per_session || 0) * sessions;
                        }
                    });
                });
            } else if ((info.packages || []).length) {
                (info.packages || []).forEach(function (pkg) {
                    if (parseInt(pkg.sessions, 10) === sessions) {
                        amount = parseFloat(pkg.price || 0);
                    }
                });
            } else if (parseFloat(info.price_per_session || 0) > 0) {
                amount = parseFloat(info.price_per_session) * sessions;
            } else {
                amount = parseFloat(info.price || 0);
            }
            pricePreview.textContent = amount > 0 ? formatPrice(amount) : '—';
        }

        function getSessionPackagePrice(info, count, chapter, coachId) {
            if (!info || count <= 0) {
                return 0;
            }
            if (info.variable_coach_pricing && chapter && coachId) {
                var amount = 0;
                (info.chapters || []).forEach(function (ch) {
                    if (ch.name !== chapter) {
                        return;
                    }
                    (ch.coaches || []).forEach(function (co) {
                        if (parseInt(co.id, 10) === coachId) {
                            amount = parseFloat(co.price_per_session || 0) * count;
                        }
                    });
                });
                return amount;
            }
            if ((info.packages || []).length) {
                var pkgPrice = 0;
                (info.packages || []).forEach(function (pkg) {
                    if (parseInt(pkg.sessions, 10) === count) {
                        pkgPrice = parseFloat(pkg.price || 0);
                    }
                });
                if (pkgPrice > 0) {
                    return pkgPrice;
                }
            }
            if (parseFloat(info.price_per_session || 0) > 0) {
                return parseFloat(info.price_per_session) * count;
            }
            return parseFloat(info.price || 0);
        }

        function renderSessionCards(sessionsWrapEl, sessionsSelectEl, sessionOptions, selectedValue) {
            if (!sessionsWrapEl || !sessionsSelectEl) {
                return;
            }
            sessionsWrapEl.innerHTML = '';
            if (!sessionOptions || !sessionOptions.length) {
                sessionsWrapEl.innerHTML = '<p class="sc-private-panel-hint">گزینه‌ای برای این دوره تعریف نشده است.</p>';
                return;
            }

            var info = getCourseInfo();
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var coachId = coachSel ? parseInt(coachSel.value || '0', 10) : 0;
            var activeValue = parseInt(selectedValue || getSelectedSessionCount() || '0', 10);

            sessionOptions.forEach(function (n) {
                var count = parseInt(n, 10);
                if (count <= 0) {
                    return;
                }
                var label = document.createElement('label');
                label.className = 'sc-private-session-card sc-enroll-pkg-option';
                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'sc_private_session_pick';
                radio.value = String(count);
                if (activeValue === count) {
                    radio.checked = true;
                    label.classList.add('is-checked');
                }
                radio.addEventListener('change', function () {
                    setSelectedSessionCount(count, true);
                });
                label.addEventListener('click', function () {
                    window.setTimeout(function () {
                        if (radio.checked) {
                            setSelectedSessionCount(count, true);
                        }
                    }, 0);
                });

                var sessionsSpan = document.createElement('span');
                sessionsSpan.className = 'sc-enroll-pkg-sessions sc-private-session-count';
                sessionsSpan.textContent = count + ' جلسه';

                var priceSpan = document.createElement('span');
                priceSpan.className = 'sc-enroll-pkg-price sc-private-session-price';
                var amount = getSessionPackagePrice(info, count, chapter, coachId);
                priceSpan.textContent = amount > 0 ? formatPrice(amount) : '—';

                label.appendChild(radio);
                label.appendChild(sessionsSpan);
                label.appendChild(priceSpan);
                sessionsWrapEl.appendChild(label);
            });

            if (activeValue > 0) {
                setSelectedSessionCount(activeValue, false);
            } else if (sessionOptions.length === 1) {
                setSelectedSessionCount(parseInt(sessionOptions[0], 10), false);
            }
        }

        function getCourseId() {
            return courseSel ? parseInt(courseSel.value || '0', 10) : 0;
        }

        function getChapterValue() {
            return chapterSel ? String(chapterSel.value || '').trim() : '';
        }

        function getCoachIdValue() {
            return coachSel ? parseInt(coachSel.value || '0', 10) : 0;
        }

        function collectSlotRows(cid, chapter, coachId) {
            var info = cid && data[cid] ? data[cid] : null;
            if (!info) {
                return [];
            }
            return (info.slots || []).filter(function (slot) {
                return slotMatchesSelection(slot, chapter, coachId, userFields);
            }).map(function (slot) {
                var copy = Object.assign({}, slot);
                copy.course_id = cid;
                copy.course_title = info.title || '';
                return copy;
            });
        }

        function populateCoachesForChapter(info, chapter) {
            if (!coachSel || !info || !chapter) {
                return;
            }
            resetSelect(coachSel, 'مربی را انتخاب کنید', false);
            var coaches = [];
            (info.chapters || []).forEach(function (ch) {
                if (ch.name === chapter) {
                    coaches = ch.coaches || [];
                }
            });
            coaches.forEach(function (co) {
                var opt = document.createElement('option');
                opt.value = String(co.id);
                opt.textContent = co.name;
                coachSel.appendChild(opt);
            });
            coachSel.disabled = false;
            if (coaches.length === 1) {
                coachSel.value = String(coaches[0].id);
            }
        }

        function populateCoachesForCourse(info) {
            if (!coachSel || !info) {
                return;
            }
            resetSelect(coachSel, 'مربی را انتخاب کنید', false);
            var seen = {};
            (info.chapters || []).forEach(function (ch) {
                (ch.coaches || []).forEach(function (co) {
                    var id = parseInt(co.id, 10);
                    if (!id || seen[id]) {
                        return;
                    }
                    seen[id] = true;
                    var opt = document.createElement('option');
                    opt.value = String(id);
                    opt.textContent = co.name;
                    coachSel.appendChild(opt);
                });
            });
            coachSel.disabled = coachSel.options.length <= 1;
        }

        function buildSlotCardLabel(slot) {
            var parts = [slot.label || ''];
            if (!userFields.course && slot.course_title) {
                parts.unshift(slot.course_title);
            }
            return parts.filter(Boolean).join(' — ');
        }

        function paintSlotCards(rows, selectedIds) {
            if (!slotsWrap) {
                return;
            }
            slotsWrap.innerHTML = '';
            if (!rows.length) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty" style="color:#d63638;">برای انتخاب شما بازه زمانی تعریف نشده است.</div>';
                updateSessionsSchedulePreview(null);
                return;
            }

            rows.forEach(function (slot) {
                var label = document.createElement('label');
                label.className = 'sc-private-slot-card sc-private-slot-item';
                label.dataset.slotId = String(slot.id);
                label.dataset.courseId = String(slot.course_id || getCourseId() || '');
                label.dataset.chapter = String(slot.chapter || '');
                label.dataset.coachId = String(slot.coach_id || '');
                label.dataset.slotFull = '0';

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'schedule_slot_ids[]';
                cb.value = String(slot.id);
                if ((selectedIds || []).indexOf(parseInt(slot.id, 10)) >= 0) {
                    cb.checked = true;
                }
                cb.addEventListener('change', function () {
                    syncSlotCardState(label);
                    updateSubmitState();
                    scheduleSlotAvailabilityCheck();
                });

                var inner = document.createElement('span');
                inner.className = 'sc-private-slot-card-inner';
                inner.innerHTML =
                    '<span class="sc-private-slot-card-top">' +
                        '<span class="sc-private-slot-card-icon" aria-hidden="true">🕒</span>' +
                        '<span class="sc-private-slot-status-badge is-available sc-private-slot-status">در حال بررسی...</span>' +
                    '</span>' +
                    '<span class="sc-private-slot-card-label sc-private-slot-label">' + escapeHtml(buildSlotCardLabel(slot)) + '</span>';

                var conflictDetail = document.createElement('div');
                conflictDetail.className = 'sc-private-slot-conflict-detail';
                conflictDetail.hidden = true;
                conflictDetail.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                });

                label.appendChild(cb);
                label.appendChild(inner);
                label.appendChild(conflictDetail);
                label.addEventListener('click', function (e) {
                    if (cb.disabled) {
                        e.preventDefault();
                    }
                });
                slotsWrap.appendChild(label);
                syncSlotCardState(label);
            });
            scheduleSlotAvailabilityCheck();
        }

        function refreshSlots() {
            if (!slotsWrap) {
                return;
            }
            var cid = getCourseId();
            var chapter = getChapterValue();
            var coachId = getCoachIdValue();
            var selectedIds = prefill.slot_ids || getSelectedSlotIds();

            if (userFields.course && !cid) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا دوره را انتخاب کنید.</div>';
                updateSlotsPreviewBanner(null);
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                return;
            }
            if (userFields.chapter && !chapter && chapterSel) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا شعبه را انتخاب کنید.</div>';
                updateSlotsPreviewBanner(null);
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                return;
            }
            if (userFields.coach && !coachId && coachSel) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا مربی را انتخاب کنید.</div>';
                updateSlotsPreviewBanner(null);
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                return;
            }
            if (needsSessionSelectionFirst()) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا تعداد جلسات را انتخاب کنید.</div>';
                updateSlotsPreviewBanner(null);
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                togglePreviewRefreshButton(false);
                return;
            }

            var rows = [];
            if (cid) {
                rows = collectSlotRows(cid, chapter, coachId);
            } else {
                Object.keys(data).forEach(function (key) {
                    var courseId = parseInt(key, 10);
                    if (courseId > 0) {
                        rows = rows.concat(collectSlotRows(courseId, '', 0));
                    }
                });
            }

            paintSlotCards(rows, selectedIds);
        }

        function renderSlots(chapter, coachId, selectedIds) {
            refreshSlots();
        }

        function scheduleSlotAvailabilityCheck(delayMs) {
            clearTimeout(slotCheckTimer);
            var wait = typeof delayMs === 'number' ? delayMs : 300;
            slotCheckTimer = setTimeout(function () {
                runSlotAvailabilityCheck(false);
            }, wait);
        }

        function runSlotAvailabilityCheck(forceNow) {
            if (!slotsWrap || !ajaxUrl) {
                return;
            }
            var allCards = slotsWrap.querySelectorAll('.sc-private-slot-card');
            if (!allCards.length) {
                updateSlotsPreviewBanner(null);
                updateSubmitState();
                togglePreviewRefreshButton(false);
                return;
            }

            var requestId = ++slotCheckSeq;

            if (slotsPreviewEl) {
                slotsPreviewEl.hidden = false;
                slotsPreviewEl.classList.add('is-loading');
                slotsPreviewEl.classList.remove('is-warning', 'is-ok', 'is-muted');
                slotsPreviewEl.textContent = 'در حال بررسی امکان رزرو بازه‌های زمانی...';
            }
            if (slotsPreviewRefreshBtn) {
                slotsPreviewRefreshBtn.classList.add('is-loading');
                slotsPreviewRefreshBtn.disabled = true;
            }

            var cid = getCourseId();
            var chapter = getChapterValue();
            var coachId = getCoachIdValue();
            var sessions = getEffectiveSessionCount();

            if (userFields.sessions && sessions <= 0) {
                if (slotsPreviewEl) {
                    slotsPreviewEl.hidden = false;
                    slotsPreviewEl.classList.remove('is-loading', 'is-warning', 'is-ok');
                    slotsPreviewEl.classList.add('is-muted');
                    slotsPreviewEl.textContent = 'ابتدا تعداد جلسات را انتخاب کنید تا وضعیت بازه‌ها و پیش‌نمایش جلسات بررسی شود.';
                }
                if (slotsPreviewRefreshBtn) {
                    slotsPreviewRefreshBtn.classList.remove('is-loading');
                    slotsPreviewRefreshBtn.disabled = true;
                    slotsPreviewRefreshBtn.hidden = true;
                }
                updateSessionsSchedulePreview(null);
                updateSubmitState();
                return;
            }

            var body = new URLSearchParams();
            body.append('action', 'sc_private_check_slots');
            body.append('nonce', ajaxNonce);
            body.append('course_id', String(cid || 0));
            body.append('chapter', chapter);
            body.append('coach_id', String(coachId || 0));
            body.append('enrollment_sessions', String(sessions));
            body.append('start_date_shamsi', String(startDateEl ? startDateEl.value : ''));
            if (isAdmin) {
                body.append('is_admin', '1');
            }
            getSelectedSlotIds().forEach(function (slotId) {
                body.append('selected_slot_ids[]', String(slotId));
            });
            allCards.forEach(function (card) {
                var cb = card.querySelector('input[type="checkbox"]');
                if (cb) {
                    body.append('schedule_slot_ids[]', String(cb.value));
                }
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (res) { return res.json(); }).then(function (json) {
                if (requestId !== slotCheckSeq) {
                    return;
                }
                if (slotsPreviewEl) {
                    slotsPreviewEl.classList.remove('is-loading');
                }
                if (slotsPreviewRefreshBtn) {
                    slotsPreviewRefreshBtn.classList.remove('is-loading');
                    slotsPreviewRefreshBtn.disabled = false;
                }
                applySlotAvailabilityResults(json);
            }).catch(function () {
                if (requestId !== slotCheckSeq) {
                    return;
                }
                if (slotsPreviewEl) {
                    slotsPreviewEl.classList.remove('is-loading');
                    slotsPreviewEl.hidden = false;
                    slotsPreviewEl.classList.add('is-muted');
                    slotsPreviewEl.textContent = 'بررسی امکان رزرو انجام نشد. دکمه «بروزرسانی پیش‌نمایش» را بزنید یا دوباره تلاش کنید.';
                }
                if (slotsPreviewRefreshBtn) {
                    slotsPreviewRefreshBtn.classList.remove('is-loading');
                    slotsPreviewRefreshBtn.disabled = false;
                }
                updateSubmitState();
            });
        }

        function onCourseChange() {
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;

            pickedSessionCount = 0;
            if (sessionsHidden) {
                sessionsHidden.value = '';
            }

            if (chapterSel) {
                resetSelect(chapterSel, info ? 'شعبه را انتخاب کنید' : 'ابتدا دوره را انتخاب کنید', !info);
            }
            if (coachSel) {
                resetSelect(coachSel, 'ابتدا شعبه را انتخاب کنید', true);
            }
            if (sessionsSel) {
                resetSelect(sessionsSel, info ? 'تعداد جلسات' : 'ابتدا دوره را انتخاب کنید', !info);
            }
            if (info && sessionsSel) {
                populateSessionSelectOptions(info);
            }
            if (sessionsWrap) {
                renderSessionCards(sessionsWrap, sessionsSel, info ? info.session_options : [], prefill.enrollment_sessions || 0);
            }
            if (slotsWrap) {
                if (!userFields.course) {
                    refreshSlots();
                } else if (!info) {
                    slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا دوره را انتخاب کنید.</div>';
                } else if (chapterSel) {
                    slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا شعبه' + (userFields.coach ? ' و مربی' : '') + (userFields.sessions ? ' و تعداد جلسات' : '') + ' را انتخاب کنید.</div>';
                } else if (coachSel && !userFields.chapter) {
                    populateCoachesForCourse(info);
                    refreshSlots();
                } else {
                    refreshSlots();
                }
            }
            if (pricePreview) {
                pricePreview.textContent = '—';
            }
            if (!info) {
                return;
            }

            if (chapterSel) {
                (info.chapters || []).forEach(function (ch) {
                    var opt = document.createElement('option');
                    opt.value = ch.name;
                    opt.textContent = ch.name;
                    chapterSel.appendChild(opt);
                });
                chapterSel.disabled = false;
                if ((info.chapters || []).length === 1) {
                    chapterSel.value = info.chapters[0].name;
                    onChapterChange();
                    return;
                }
            } else if (coachSel && userFields.coach) {
                populateCoachesForCourse(info);
            }

            refreshSlots();
            updatePricePreview();
        }

        function refreshSessionCards() {
            if (!sessionsWrap || !sessionsSel) {
                return;
            }
            var info = getCourseInfo();
            if (!info) {
                return;
            }
            populateSessionSelectOptions(info);
            renderSessionCards(sessionsWrap, sessionsSel, info.session_options || [], getSelectedSessionCount());
        }

        function onChapterChange() {
            var cid = getCourseId();
            var chapter = getChapterValue();
            var info = cid && data[cid] ? data[cid] : null;

            if (coachSel) {
                if (chapter) {
                    populateCoachesForChapter(info, chapter);
                } else {
                    resetSelect(coachSel, 'ابتدا شعبه را انتخاب کنید', true);
                }
            }

            refreshSlots();
            refreshSessionCards();
            updatePricePreview();
        }

        function onCoachChange() {
            refreshSlots();
            refreshSessionCards();
            updatePricePreview();
        }

        if (courseSel) {
            courseSel.addEventListener('change', onCourseChange);
        }
        if (chapterSel) {
            chapterSel.addEventListener('change', onChapterChange);
        }
        if (coachSel) {
            coachSel.addEventListener('change', onCoachChange);
        }
        if (sessionsSel) {
            sessionsSel.addEventListener('change', function () {
                setSelectedSessionCount(parseInt(sessionsSel.value || '0', 10), true);
            });
        }
        if (startDateEl) {
            startDateEl.addEventListener('change', function () {
                scheduleSlotAvailabilityCheck(80);
            });
            startDateEl.addEventListener('input', function () {
                scheduleSlotAvailabilityCheck(80);
            });
            startDateEl.addEventListener('blur', function () {
                scheduleSlotAvailabilityCheck(80);
            });
        }
        if (slotsPreviewRefreshBtn) {
            slotsPreviewRefreshBtn.addEventListener('click', function () {
                clearTimeout(slotCheckTimer);
                runSlotAvailabilityCheck(true);
            });
        }

        if (prefill.course_id) {
            onCourseChange();
            if (prefill.chapter && chapterSel) {
                chapterSel.value = prefill.chapter;
                onChapterChange();
            }
            if (prefill.coach_id && coachSel) {
                coachSel.value = String(prefill.coach_id);
                renderSlots(prefill.chapter || (chapterSel ? chapterSel.value : ''), prefill.coach_id, prefill.slot_ids || []);
            }
            if (prefill.enrollment_sessions) {
                setSelectedSessionCount(parseInt(prefill.enrollment_sessions, 10), false);
            }
            if (sessionsWrap && sessionsSel) {
                var info = getCourseInfo();
                if (info) {
                    populateSessionSelectOptions(info);
                    renderSessionCards(sessionsWrap, sessionsSel, info.session_options || [], prefill.enrollment_sessions || getSelectedSessionCount());
                }
            }
            if (prefill.enrollment_sessions && userFields.slots) {
                refreshSlots();
            }
            updatePricePreview();
        }

        if (slotsWrap && userFields.slots && !userFields.course) {
            refreshSlots();
        }

        if (bookingForm && userFields.slots) {
            bookingForm.addEventListener('submit', function (e) {
                if (userFields.sessions && getSelectedSessionCount() <= 0) {
                    e.preventDefault();
                    window.alert('لطفاً تعداد جلسات را انتخاب کنید.');
                    return false;
                }
                if (countCheckedFullSlots() > 0) {
                    e.preventDefault();
                    window.alert('یک یا چند بازه زمانی انتخابی پر شده است. لطفاً بازه دیگری انتخاب کنید.');
                    return false;
                }
                var hasChecked = getSelectedSlotIds().length > 0;
                if (userFields.slots && slotsWrap && slotsWrap.querySelectorAll('.sc-private-slot-card').length && !hasChecked) {
                    var anyAvailable = slotsWrap.querySelector('.sc-private-slot-card input[type="checkbox"]:not(:disabled)');
                    if (anyAvailable) {
                        e.preventDefault();
                        window.alert('لطفاً حداقل یک بازه زمانی قابل رزرو انتخاب کنید.');
                        return false;
                    }
                }
            });
        }
    };
}(window, document));
