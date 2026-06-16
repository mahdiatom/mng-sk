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

    function slotMatchesSelection(slot, chapter, coachId) {
        if (!chapter || !coachId) {
            return false;
        }
        var rowChapter = String(slot.chapter || '').trim();
        var rowCoach = parseInt(slot.coach_id || '0', 10);
        if (rowChapter !== '' && rowChapter !== chapter) {
            return false;
        }
        if (rowCoach > 0 && rowCoach !== coachId) {
            return false;
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

    window.scInitPrivateBookingForm = function (config) {
        config = config || {};
        var data = config.data || {};
        var ajaxUrl = config.ajaxUrl || '';
        var ajaxNonce = config.ajaxNonce || '';
        var prefill = config.prefill || {};

        var courseSel = document.getElementById('sc_private_course_id');
        var chapterSel = document.getElementById('sc_private_chapter');
        var coachSel = document.getElementById('sc_private_coach_id');
        var slotsWrap = document.getElementById('sc_private_slots_wrap');
        var sessionsSel = document.getElementById('sc_private_sessions_count');
        var sessionsWrap = document.getElementById('sc_private_sessions_wrap');
        var startDateEl = document.getElementById('sc_private_start_date_shamsi');
        var pricePreview = document.getElementById('sc_private_price_preview');
        var slotCheckTimer = null;

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

        function renderSessionCards(sessionsWrap, sessionsSel, sessionOptions, selectedValue) {
            if (!sessionsWrap || !sessionsSel) {
                return;
            }
            sessionsWrap.innerHTML = '';
            if (!sessionOptions || !sessionOptions.length) {
                sessionsWrap.innerHTML = '<p class="sc-private-panel-hint">گزینه‌ای برای این دوره تعریف نشده است.</p>';
                return;
            }

            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var coachId = coachSel ? parseInt(coachSel.value || '0', 10) : 0;

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
                if (String(selectedValue || '') === String(count) || String(sessionsSel.value || '') === String(count)) {
                    radio.checked = true;
                    sessionsSel.value = String(count);
                    label.classList.add('is-checked');
                }
                radio.addEventListener('change', function () {
                    sessionsSel.value = String(count);
                    sessionsSel.disabled = false;
                    sessionsWrap.querySelectorAll('.sc-private-session-card').forEach(function (card) {
                        card.classList.remove('is-checked');
                    });
                    label.classList.add('is-checked');
                    updatePricePreview();
                    scheduleSlotAvailabilityCheck();
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
                sessionsWrap.appendChild(label);
            });

            if (!sessionsSel.value && sessionOptions.length === 1) {
                sessionsSel.value = String(sessionOptions[0]);
                var firstCard = sessionsWrap.querySelector('.sc-private-session-card');
                if (firstCard) {
                    firstCard.classList.add('is-checked');
                    var firstRadio = firstCard.querySelector('input[type="radio"]');
                    if (firstRadio) {
                        firstRadio.checked = true;
                    }
                }
            }
        }

        function renderSlots(chapter, coachId, selectedIds) {
            if (!slotsWrap) {
                return;
            }
            slotsWrap.innerHTML = '';
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;
            if (!info || !chapter || !coachId) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا شعبه و مربی را انتخاب کنید.</div>';
                return;
            }
            var rows = (info.slots || []).filter(function (slot) {
                return slotMatchesSelection(slot, chapter, coachId);
            });
            if (!rows.length) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty" style="color:#d63638;">برای این شعبه/مربی اسلات زمانی تعریف نشده است.</div>';
                return;
            }

            rows.forEach(function (slot) {
                var label = document.createElement('label');
                label.className = 'sc-private-slot-card sc-private-slot-item';
                label.dataset.slotId = String(slot.id);

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'schedule_slot_ids[]';
                cb.value = String(slot.id);
                if ((selectedIds || []).indexOf(parseInt(slot.id, 10)) >= 0) {
                    cb.checked = true;
                }
                cb.addEventListener('change', function () {
                    syncSlotCardState(label);
                    scheduleSlotAvailabilityCheck();
                });

                var inner = document.createElement('span');
                inner.className = 'sc-private-slot-card-inner';
                inner.innerHTML =
                    '<span class="sc-private-slot-card-top">' +
                        '<span class="sc-private-slot-card-icon" aria-hidden="true">🕒</span>' +
                        '<span class="sc-private-slot-status-badge is-available sc-private-slot-status">آزاد</span>' +
                    '</span>' +
                    '<span class="sc-private-slot-card-label sc-private-slot-label">' + slot.label + '</span>';

                label.appendChild(cb);
                label.appendChild(inner);
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

        function scheduleSlotAvailabilityCheck() {
            clearTimeout(slotCheckTimer);
            slotCheckTimer = setTimeout(runSlotAvailabilityCheck, 350);
        }

        function runSlotAvailabilityCheck() {
            if (!slotsWrap) {
                return;
            }
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var coachId = coachSel ? parseInt(coachSel.value || '0', 10) : 0;
            var sessions = sessionsSel ? parseInt(sessionsSel.value || '0', 10) : 0;
            var allCards = slotsWrap.querySelectorAll('.sc-private-slot-card');

            if (!cid || !chapter || !coachId || sessions <= 0 || !ajaxUrl) {
                allCards.forEach(function (card) {
                    var cb = card.querySelector('input[type="checkbox"]');
                    if (cb) {
                        cb.disabled = false;
                    }
                    var badge = card.querySelector('.sc-private-slot-status-badge');
                    if (badge) {
                        badge.textContent = 'آزاد';
                        badge.className = 'sc-private-slot-status-badge is-available sc-private-slot-status';
                    }
                    syncSlotCardState(card);
                });
                return;
            }

            var body = new URLSearchParams();
            body.append('action', 'sc_private_check_slots');
            body.append('nonce', ajaxNonce);
            body.append('course_id', String(cid));
            body.append('chapter', chapter);
            body.append('coach_id', String(coachId));
            body.append('enrollment_sessions', String(sessions));
            body.append('start_date_shamsi', String(startDateEl ? startDateEl.value : ''));
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
                if (!json || !json.success || !json.data || !json.data.slots) {
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
                    if (slotInfo && slotInfo.full) {
                        cb.checked = false;
                        cb.disabled = true;
                        if (badge) {
                            badge.textContent = 'تکمیل ظرفیت';
                            badge.className = 'sc-private-slot-status-badge is-full sc-private-slot-status';
                        }
                    } else {
                        cb.disabled = false;
                        if (badge) {
                            badge.textContent = 'آزاد';
                            badge.className = 'sc-private-slot-status-badge is-available sc-private-slot-status';
                        }
                    }
                    syncSlotCardState(card);
                });
            }).catch(function () {});
        }

        function onCourseChange() {
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;

            if (chapterSel) {
                resetSelect(chapterSel, info ? 'شعبه را انتخاب کنید' : 'ابتدا دوره را انتخاب کنید', !info);
            }
            if (coachSel) {
                resetSelect(coachSel, 'ابتدا شعبه را انتخاب کنید', true);
            }
            if (sessionsSel) {
                resetSelect(sessionsSel, info ? 'تعداد جلسات' : 'ابتدا دوره را انتخاب کنید', !info);
            }
            if (sessionsWrap) {
                renderSessionCards(sessionsWrap, sessionsSel, info ? info.session_options : [], 0);
            }
            if (slotsWrap) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا دوره، شعبه و مربی را انتخاب کنید.</div>';
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
                }
            }

            if (sessionsSel) {
                (info.session_options || []).forEach(function (n) {
                    var opt = document.createElement('option');
                    opt.value = String(n);
                    opt.textContent = String(n) + ' جلسه';
                    sessionsSel.appendChild(opt);
                });
                sessionsSel.disabled = false;
            }
            if (sessionsWrap) {
                renderSessionCards(sessionsWrap, sessionsSel, info.session_options || [], prefill.enrollment_sessions || 0);
            }
            updatePricePreview();
        }

        function refreshSessionCards() {
            if (!sessionsWrap || !sessionsSel) {
                return;
            }
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var info = cid && data[cid] ? data[cid] : null;
            if (!info) {
                return;
            }
            var selected = parseInt(sessionsSel.value || '0', 10);
            renderSessionCards(sessionsWrap, sessionsSel, info.session_options || [], selected);
        }

        function onChapterChange() {
            var cid = courseSel ? parseInt(courseSel.value || '0', 10) : 0;
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var info = cid && data[cid] ? data[cid] : null;
            if (coachSel) {
                resetSelect(coachSel, chapter ? 'مربی را انتخاب کنید' : 'ابتدا شعبه را انتخاب کنید', !chapter);
            }
            if (slotsWrap) {
                slotsWrap.innerHTML = '<div class="sc-private-slot-empty">ابتدا مربی را انتخاب کنید.</div>';
            }
            if (!info || !chapter || !coachSel) {
                updatePricePreview();
                return;
            }
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
                onCoachChange();
            } else {
                refreshSessionCards();
                updatePricePreview();
            }
        }

        function onCoachChange() {
            var chapter = chapterSel ? String(chapterSel.value || '') : '';
            var coachId = coachSel ? parseInt(coachSel.value || '0', 10) : 0;
            renderSlots(chapter, coachId, prefill.slot_ids || getSelectedSlotIds());
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
                updatePricePreview();
                scheduleSlotAvailabilityCheck();
            });
        }
        if (startDateEl) {
            startDateEl.addEventListener('change', scheduleSlotAvailabilityCheck);
            startDateEl.addEventListener('blur', scheduleSlotAvailabilityCheck);
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
            if (prefill.enrollment_sessions && sessionsSel) {
                sessionsSel.value = String(prefill.enrollment_sessions);
            }
            if (sessionsWrap && sessionsSel) {
                var cid = parseInt(String(prefill.course_id || '0'), 10);
                var info = cid && data[cid] ? data[cid] : null;
                renderSessionCards(sessionsWrap, sessionsSel, info ? info.session_options : [], prefill.enrollment_sessions || 0);
            }
            updatePricePreview();
        }
    };
}(window, document));
