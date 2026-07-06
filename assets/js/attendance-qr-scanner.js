(function ($) {
    'use strict';

    var cfg = window.scAttendanceQr || {};
    var scanner = null;
    var inFlightHashes = {};
    var lastHandledHash = '';
    var lastHandledAt = 0;
    var sounds = {};
    var optimisticLogged = {};
    var cameraState = {
        picks: [],
        index: 0,
        switching: false
    };

    function getDuplicateCooldown() {
        return parseInt(cfg.cooldownMs, 10) || 300;
    }

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent || '')
            || (navigator.maxTouchPoints > 1 && window.matchMedia('(max-width: 1024px)').matches);
    }

    function isRearCameraLabel(label) {
        return /back|rear|environment|world|wide|trás|trasera|arrière|hinten|rück|عقب|پشت|دوربین\s*عقب/i.test(label || '');
    }

    function isFrontCameraLabel(label) {
        return /front|user|selfie|رو|جلو|دوربین\s*جلو|facetime/i.test(label || '');
    }

    function cameraPickKey(pick) {
        if (!pick) {
            return '';
        }
        if (pick.mode === 'device') {
            return 'device:' + pick.cameraId;
        }
        return pick.mode;
    }

    function buildCameraPicks(cameras) {
        var picks = [];
        var usedIds = {};

        function addPick(pick) {
            var key = cameraPickKey(pick);
            if (!key || picks.some(function (p) { return cameraPickKey(p) === key; })) {
                return;
            }
            picks.push(pick);
            if (pick.mode === 'device') {
                usedIds[pick.cameraId] = true;
            }
        }

        (cameras || []).forEach(function (camera) {
            if (isRearCameraLabel(camera.label)) {
                addPick({ mode: 'device', cameraId: camera.id, label: 'دوربین عقب' });
            }
        });

        addPick({ mode: 'environment', label: 'دوربین عقب' });

        (cameras || []).forEach(function (camera) {
            if (isFrontCameraLabel(camera.label)) {
                addPick({ mode: 'device', cameraId: camera.id, label: 'دوربین جلو' });
            }
        });

        addPick({ mode: 'user', label: 'دوربین جلو' });

        (cameras || []).forEach(function (camera) {
            if (!usedIds[camera.id]) {
                addPick({
                    mode: 'device',
                    cameraId: camera.id,
                    label: camera.label ? camera.label : 'دوربین دیگر'
                });
            }
        });

        if (!picks.length) {
            addPick({ mode: 'environment', label: 'دوربین عقب' });
            addPick({ mode: 'user', label: 'دوربین جلو' });
        }

        return picks;
    }

    function findDefaultRearIndex(picks) {
        var deviceRear = picks.findIndex(function (pick) {
            return pick.mode === 'device' && pick.label === 'دوربین عقب';
        });
        if (deviceRear >= 0) {
            return deviceRear;
        }
        var environment = picks.findIndex(function (pick) {
            return pick.mode === 'environment';
        });
        if (environment >= 0) {
            return environment;
        }
        return 0;
    }

    function getCurrentCameraPick() {
        if (!cameraState.picks.length) {
            return { mode: 'environment', label: 'دوربین عقب' };
        }
        return cameraState.picks[cameraState.index] || cameraState.picks[0];
    }

    function getScannerConfig(cameraPick) {
        var mobile = isMobileDevice();
        var config = {
            // fps بالا باعث لگ پیش‌نمایش روی گوشی می‌شود؛ ۱۰ مثل نسخهٔ اولیه روان‌تر است.
            fps: mobile ? 10 : 12,
            qrbox: function (viewfinderWidth, viewfinderHeight) {
                var edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * (mobile ? 0.88 : 0.82));
                return { width: edge, height: edge };
            },
            aspectRatio: mobile ? 1.0 : 1.3333333,
            disableFlip: true,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: !mobile
            },
            videoConstraints: mobile
                ? { width: { ideal: 640, max: 1280 }, height: { ideal: 480, max: 720 } }
                : { width: { ideal: 1280, max: 1920 }, height: { ideal: 720, max: 1080 } }
        };

        if (cameraPick && (cameraPick.mode === 'environment' || cameraPick.mode === 'user')) {
            config.videoConstraints.facingMode = { ideal: cameraPick.mode };
        }

        return config;
    }

    function buildCameraConstraints(cameraPick) {
        if (!cameraPick || cameraPick.mode === 'environment') {
            return { facingMode: { ideal: 'environment' } };
        }
        if (cameraPick.mode === 'user') {
            return { facingMode: { ideal: 'user' } };
        }
        return { deviceId: { exact: cameraPick.cameraId } };
    }

    function startScannerStream(cameraPick) {
        var config = getScannerConfig(cameraPick);
        return scanner.start(buildCameraConstraints(cameraPick), config, handleScan, function () {});
    }

    function createScannerInstance() {
        var options = { verbose: false };
        if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
            options.formatsToSupport = [Html5QrcodeSupportedFormats.QR_CODE];
        }
        return new Html5Qrcode('sc-attendance-qr-reader', options);
    }

    function setSwitchButtonState(active) {
        $('#sc-attendance-qr-switch').prop('disabled', !active || cameraState.switching || cameraState.picks.length < 2);
    }

    function lookupMember(payload) {
        var map = cfg.memberMap || {};
        if (map[payload]) {
            return map[payload];
        }
        var hash = payload.replace(/^SC1:/i, '');
        if (map[hash]) {
            return map[hash];
        }
        if (map['SC1:' + hash]) {
            return map['SC1:' + hash];
        }
        return null;
    }

    function preloadSounds() {
        var soundKeys = {
            success: 'soundSuccess',
            error: 'soundError',
            duplicate: 'soundDuplicate',
            notInCourse: 'soundNotInCourse'
        };
        Object.keys(soundKeys).forEach(function (type) {
            var url = cfg[soundKeys[type]] || '';
            if (!url) {
                return;
            }
            sounds[type] = new Audio(url);
            sounds[type].preload = 'auto';
        });
    }

    function playSound(type) {
        if (!sounds[type]) {
            return;
        }
        try {
            sounds[type].currentTime = 0;
            sounds[type].play().catch(function () {});
        } catch (e) {}
    }

    function unlockAudio() {
        Object.keys(sounds).forEach(function (key) {
            try {
                var a = sounds[key];
                if (!a) {
                    return;
                }
                var vol = a.volume;
                a.volume = 0;
                a.play().then(function () {
                    a.pause();
                    a.currentTime = 0;
                    a.volume = vol;
                }).catch(function () {
                    a.volume = vol;
                });
            } catch (e) {}
        });
    }

    function showToast(message, type) {
        var $box = $('#sc-attendance-qr-toast');
        if (!$box.length) {
            return;
        }
        $box.removeClass('is-success is-error is-duplicate is-not-in-course is-info')
            .addClass('is-visible is-' + (type || 'info'))
            .find('.sc-attendance-qr-toast__text')
            .text(message || '');
    }

    function incrementScanCount() {
        var count = parseInt($('#sc-attendance-qr-count').text(), 10) || 0;
        $('#sc-attendance-qr-count').text(count + 1);
    }

    function prependLog(item) {
        var $log = $('#sc-attendance-qr-log');
        if (!$log.length) {
            return;
        }
        var time = new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        var cls = item.ok ? (item.duplicate ? 'duplicate' : 'success') : 'error';
        var html = '<li class="sc-attendance-qr-log__item sc-attendance-qr-log__item--' + cls + '">' +
            '<span class="sc-attendance-qr-log__time">' + time + '</span>' +
            '<span class="sc-attendance-qr-log__name">' + (item.name || '—') + '</span>' +
            '<span class="sc-attendance-qr-log__msg">' + (item.message || '') + '</span>' +
            '</li>';
        $log.prepend(html);
        if ($log.children().length > 12) {
            $log.children().last().remove();
        }
    }

    function isMemberAlreadyPresent(memberId) {
        return $('#sc-attendance-recorded-tbody tr[data-member-id="' + memberId + '"]').length > 0;
    }

    function renumberAttendanceTable($tbody) {
        $tbody.find('tr.sc-attendance-member-row').each(function (i) {
            $(this).find('td:first').text(i + 1);
        });
    }

    function updateAttendanceListCounts() {
        var pendingCount = $('#sc-attendance-pending-tbody tr.sc-attendance-member-row').length;
        var recordedCount = $('#sc-attendance-recorded-tbody tr.sc-attendance-member-row').length;
        $('#sc-attendance-pending-count').text(pendingCount + ' نفر');
        $('#sc-attendance-recorded-count').text(recordedCount + ' نفر');

        if (pendingCount > 0) {
            $('#sc-attendance-pending-table').show();
            $('#sc-attendance-pending-empty').hide();
            $('#sc-attendance-save-pending-btn').prop('disabled', false);
        } else {
            $('#sc-attendance-pending-table').hide();
            $('#sc-attendance-pending-empty').show();
            $('#sc-attendance-save-pending-btn').prop('disabled', true);
        }

        if (recordedCount > 0) {
            $('#sc-attendance-recorded-table').show();
            $('#sc-attendance-recorded-empty').hide();
            $('#sc-attendance-save-recorded-btn').prop('disabled', false);
        } else {
            $('#sc-attendance-recorded-table').hide();
            $('#sc-attendance-recorded-empty').show();
            $('#sc-attendance-save-recorded-btn').prop('disabled', true);
        }
    }

    function moveMemberRowToRecorded($row) {
        if (!$row || !$row.length) {
            return;
        }
        if ($row.closest('#sc-attendance-recorded-tbody').length) {
            return;
        }
        var memberId = $row.attr('data-member-id');
        $row.attr('data-list-type', 'recorded');
        $row.find('.sc-attendance-clear-btn').remove();
        $row.find('input[type="radio"]').each(function () {
            var val = $(this).val();
            $(this).attr('name', 'attendance_recorded[' + memberId + ']');
        });
        $('#sc-attendance-recorded-tbody').append($row);
        renumberAttendanceTable($('#sc-attendance-pending-tbody'));
        renumberAttendanceTable($('#sc-attendance-recorded-tbody'));
        updateAttendanceListCounts();
    }

    function markMemberPresent(memberId) {
        if (!memberId) {
            return;
        }
        var $row = $('tr.sc-attendance-member-row[data-member-id="' + memberId + '"]');
        var $radio = $row.find('input[type="radio"][value="present"]');
        if ($radio.length) {
            $radio.prop('checked', true).trigger('change');
            $row.find('.sc-attendance-record-method')
                .removeClass('sc-attendance-record-method--manual sc-attendance-record-method--empty')
                .addClass('sc-attendance-record-method--qr')
                .text('اسکن QR');
            moveMemberRowToRecorded($row);
            $row.addClass('sc-attendance-member-row--qr-scanned');
            setTimeout(function () {
                $row.removeClass('sc-attendance-member-row--qr-scanned');
            }, 1200);
        }
    }

    function shouldIgnoreScan(hash, now) {
        if (inFlightHashes[hash]) {
            return true;
        }
        return hash === lastHandledHash && (now - lastHandledAt) < getDuplicateCooldown();
    }

    function markHandled(hash, now) {
        lastHandledHash = hash;
        lastHandledAt = now || Date.now();
    }

    function applyInstantSuccess(member, message) {
        markMemberPresent(member.id);
        playSound('success');
        showToast((member.name || 'بازیکن') + ' — ' + (message || 'ثبت شد ✓'), 'success');
        if (!optimisticLogged[member.id]) {
            prependLog({ ok: true, duplicate: false, name: member.name, message: message || 'ثبت فوری' });
            incrementScanCount();
            optimisticLogged[member.id] = true;
        }
    }

    function handleScan(decodedText) {
        var now = Date.now();
        var payload = (decodedText || '').trim();
        if (!payload || shouldIgnoreScan(payload, now)) {
            return;
        }

        inFlightHashes[payload] = true;
        markHandled(payload, now);

        var localMember = lookupMember(payload);
        if (localMember && !isMemberAlreadyPresent(localMember.id)) {
            applyInstantSuccess(localMember, 'ثبت شد ✓');
        } else if (!localMember) {
            showToast('در حال ثبت...', 'info');
        }

        $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_scan',
            nonce: cfg.nonce,
            qr_payload: payload,
            course_id: cfg.courseId,
            attendance_date: cfg.attendanceDate,
            chapter_name: cfg.chapterName,
            group_name: cfg.groupName
        }).done(function (res) {
            if (res && res.success) {
                var code = res.data && res.data.code ? res.data.code : 'created';
                var name = res.data && res.data.member_name ? res.data.member_name : (localMember ? localMember.name : '');
                var memberId = res.data && res.data.member_id ? res.data.member_id : (localMember ? localMember.id : 0);

                if (memberId && cfg.memberMap) {
                    cfg.memberMap[payload] = { id: memberId, name: name };
                }

                if (code === 'duplicate') {
                    playSound('duplicate');
                    showToast(name + ' — قبلاً ثبت شده', 'duplicate');
                    prependLog({ ok: true, duplicate: true, name: name, message: res.data.message });
                    return;
                }

                if (!localMember || !optimisticLogged[memberId]) {
                    applyInstantSuccess({ id: memberId, name: name }, res.data.message || 'ثبت شد ✓');
                }
                return;
            }

            var errCode = (res && res.data && res.data.code) ? res.data.code : '';
            var errMsg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت';
            var errName = (res && res.data && res.data.member_name) ? res.data.member_name : '';

            if (errCode === 'not_in_course') {
                playSound('notInCourse');
                showToast((errName ? errName + ' — ' : '') + errMsg, 'not-in-course');
            } else {
                playSound('error');
                showToast(errMsg, 'error');
            }
            prependLog({ ok: false, name: errName, message: errMsg });
        }).fail(function () {
            playSound('error');
            showToast('خطا در ارتباط با سرور', 'error');
        }).always(function () {
            delete inFlightHashes[payload];
        });
    }

    function setScannerFullscreen(active) {
        var mobile = isMobileDevice();
        $('body').toggleClass('sc-attendance-qr-fullscreen', active && mobile);
        $('#sc-attendance-qr-panel')
            .toggleClass('is-scanning-active', active)
            .toggleClass('is-scanning-mobile', active && mobile)
            .toggleClass('is-scanning-desktop', active && !mobile);
    }

    function restartWithCameraPick(cameraPick) {
        if (!scanner) {
            return Promise.reject(new Error('اسکنر فعال نیست'));
        }

        return scanner.stop().then(function () {
            scanner.clear();
            $('#sc-attendance-qr-reader').empty();
            return startScannerStream(cameraPick);
        });
    }

    function switchCamera() {
        if (!scanner || cameraState.switching || cameraState.picks.length < 2) {
            return;
        }

        cameraState.switching = true;
        setSwitchButtonState(true);

        cameraState.index = (cameraState.index + 1) % cameraState.picks.length;
        var nextPick = getCurrentCameraPick();

        restartWithCameraPick(nextPick).then(function () {
            showToast(nextPick.label + ' فعال شد', 'info');
        }).catch(function (err) {
            showToast('تغییر دوربین ناموفق بود: ' + (err.message || err), 'error');
        }).finally(function () {
            cameraState.switching = false;
            setSwitchButtonState(true);
        });
    }

    function stopScanner() {
        setScannerFullscreen(false);
        cameraState.picks = [];
        cameraState.index = 0;
        cameraState.switching = false;
        setSwitchButtonState(false);

        if (!scanner) {
            $('#sc-attendance-qr-start').prop('disabled', false).text('شروع اسکن');
            $('#sc-attendance-qr-stop').prop('disabled', true);
            return;
        }

        scanner.stop().then(function () {
            scanner.clear();
        }).catch(function () {});
        scanner = null;
        $('#sc-attendance-qr-reader').empty();
        $('#sc-attendance-qr-start').prop('disabled', false).text('شروع اسکن');
        $('#sc-attendance-qr-stop').prop('disabled', true);
    }

    function beginScannerStream(cameras) {
        cameraState.picks = buildCameraPicks(cameras);
        cameraState.index = findDefaultRearIndex(cameraState.picks);

        var initialPick = getCurrentCameraPick();

        return startScannerStream(initialPick).catch(function () {
            var envIdx = cameraState.picks.findIndex(function (pick) {
                return pick.mode === 'environment';
            });
            if (envIdx >= 0 && envIdx !== cameraState.index) {
                cameraState.index = envIdx;
                return startScannerStream(getCurrentCameraPick());
            }
            var userIdx = cameraState.picks.findIndex(function (pick) {
                return pick.mode === 'user';
            });
            if (userIdx >= 0 && userIdx !== cameraState.index) {
                cameraState.index = userIdx;
                return startScannerStream(getCurrentCameraPick());
            }
            throw new Error('دوربین عقب در دسترس نیست');
        }).then(function () {
            setSwitchButtonState(true);
            showToast(getCurrentCameraPick().label + ' فعال است — QR را جلوی دوربین بگیرید', 'info');
        });
    }

    function startScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            showToast('کتابخانه اسکن بارگذاری نشده است.', 'error');
            return;
        }
        unlockAudio();
        setScannerFullscreen(true);
        $('#sc-attendance-qr-start').prop('disabled', true).text('در حال اسکن...');
        $('#sc-attendance-qr-stop').prop('disabled', false);
        setSwitchButtonState(false);

        scanner = createScannerInstance();

        Html5Qrcode.getCameras().then(function (cameras) {
            return beginScannerStream(cameras);
        }).catch(function (err) {
            showToast('دسترسی به دوربین ممکن نیست: ' + (err.message || err), 'error');
            stopScanner();
        });
    }

    $(function () {
        if (!$('#sc-attendance-qr-panel').length) {
            return;
        }
        preloadSounds();

        $('input[name="sc_attendance_mode"]').on('change', function () {
            var mode = $(this).val();
            $('.sc-attendance-mode-panel').hide();
            if (mode === 'qr') {
                $('#sc-attendance-mode-qr').show();
            } else {
                stopScanner();
                $('#sc-attendance-mode-list').show();
            }
        });

        $('#sc-attendance-qr-start').on('click', function () {
            startScanner();
        });
        $('#sc-attendance-qr-switch').on('click', function () {
            switchCamera();
        });
        $('#sc-attendance-qr-stop').on('click', function () {
            stopScanner();
        });

        $(window).on('beforeunload', function () {
            stopScanner();
        });
    });
})(jQuery);
