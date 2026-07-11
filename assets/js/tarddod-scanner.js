(function ($) {
    'use strict';

    var cfg = window.scTarddodQr || {};
    var scanner = null;
    var inFlightHashes = {};
    var lastHandledHash = '';
    var lastHandledAt = 0;
    var sounds = {};
    var cameraState = { picks: [], index: 0, switching: false };
    var lastPartialHintAt = 0;

    function getHashLength() {
        return parseInt(cfg.hashLength, 10) || 64;
    }

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
        return /front|user|selfie|face|webcam|integrated|usb|رو|جلو|دوربین\s*جلو|facetime/i.test(label || '');
    }

    function cameraPickKey(pick) {
        if (!pick) return '';
        if (pick.mode === 'device') return 'device:' + pick.cameraId;
        return pick.mode;
    }

    function buildCameraPicks(cameras) {
        var picks = [];
        var usedIds = {};
        var mobile = isMobileDevice();

        function addPick(pick) {
            var key = cameraPickKey(pick);
            if (!key || picks.some(function (p) { return cameraPickKey(p) === key; })) return;
            picks.push(pick);
            if (pick.mode === 'device') usedIds[pick.cameraId] = true;
        }

        if (!mobile) {
            (cameras || []).forEach(function (camera) {
                if (isFrontCameraLabel(camera.label) || !isRearCameraLabel(camera.label)) {
                    addPick({ mode: 'device', cameraId: camera.id, label: camera.label || 'وب‌کم' });
                }
            });
            if (cameras && cameras.length && !picks.length) {
                addPick({ mode: 'device', cameraId: cameras[0].id, label: cameras[0].label || 'وب‌کم' });
            }
            addPick({ mode: 'user', label: 'وب‌کم' });
            return picks;
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
                addPick({ mode: 'device', cameraId: camera.id, label: camera.label || 'دوربین دیگر' });
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
        if (deviceRear >= 0) return deviceRear;
        var environment = picks.findIndex(function (pick) { return pick.mode === 'environment'; });
        return environment >= 0 ? environment : 0;
    }

    function findDefaultCameraIndex(picks) {
        if (!isMobileDevice()) {
            var webcamIdx = picks.findIndex(function (pick) {
                return pick.mode === 'user' || pick.label === 'وب‌کم' || pick.mode === 'device';
            });
            return webcamIdx >= 0 ? webcamIdx : 0;
        }
        return findDefaultRearIndex(picks);
    }

    function getCurrentCameraPick() {
        if (!cameraState.picks.length) {
            return isMobileDevice()
                ? { mode: 'environment', label: 'دوربین عقب' }
                : { mode: 'user', label: 'وب‌کم' };
        }
        return cameraState.picks[cameraState.index] || cameraState.picks[0];
    }

    function getScannerConfig(cameraPick) {
        var mobile = isMobileDevice();
        var config = {
            fps: mobile ? 10 : 15,
            qrbox: function (w, h) {
                var ratio = mobile ? 0.88 : 0.85;
                var edge = Math.floor(Math.min(w, h) * ratio);
                return { width: edge, height: edge };
            },
            aspectRatio: 1.0,
            disableFlip: true,
            experimentalFeatures: { useBarCodeDetectorIfSupported: false },
            videoConstraints: mobile
                ? { width: { ideal: 640, max: 1280 }, height: { ideal: 480, max: 720 } }
                : { width: { ideal: 960, max: 1280 }, height: { ideal: 720, max: 720 } }
        };
        if (cameraPick && (cameraPick.mode === 'environment' || cameraPick.mode === 'user')) {
            config.videoConstraints.facingMode = { ideal: cameraPick.mode };
        }
        return config;
    }

    function buildCameraConstraints(cameraPick) {
        if (!cameraPick || cameraPick.mode === 'environment') return { facingMode: { ideal: 'environment' } };
        if (cameraPick.mode === 'user') return { facingMode: { ideal: 'user' } };
        return { deviceId: { exact: cameraPick.cameraId } };
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

    function extractHashHex(payload) {
        return (payload || '').replace(/^SC[12]:/i, '').replace(/[^a-fA-F0-9]/g, '');
    }

    function prepareScanPayload(raw) {
        return (raw || '').trim();
    }

    function isCompletePlayerPayload(payload) {
        if (/^SC2:/i.test(payload)) {
            return extractHashHex(payload).length === getHashLength();
        }
        return extractHashHex(payload).length === getHashLength();
    }

    function startScannerStream(cameraPick) {
        return scanner.start(buildCameraConstraints(cameraPick), getScannerConfig(cameraPick), handleScan, function () {});
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

    function preloadSounds() {
        var soundKeys = {
            success: 'soundSuccess',
            error: 'soundError',
            duplicate: 'soundDuplicate',
            disabled: 'soundDisabled'
        };
        Object.keys(soundKeys).forEach(function (type) {
            var url = cfg[soundKeys[type]] || '';
            if (!url) return;
            sounds[type] = new Audio(url);
            sounds[type].preload = 'auto';
        });
    }

    function playSound(type) {
        if (!sounds[type]) return;
        try {
            sounds[type].currentTime = 0;
            sounds[type].play().catch(function () {});
        } catch (e) {}
    }

    function unlockAudio() {
        Object.keys(sounds).forEach(function (key) {
            try {
                var a = sounds[key];
                if (!a) return;
                var vol = a.volume;
                a.volume = 0;
                a.play().then(function () {
                    a.pause();
                    a.currentTime = 0;
                    a.volume = vol;
                }).catch(function () { a.volume = vol; });
            } catch (e) {}
        });
    }

    function showToast(message, type) {
        var $box = $('#sc-attendance-qr-toast');
        if (!$box.length) return;
        $box.removeClass('is-success is-error is-duplicate is-info')
            .addClass('is-visible is-' + (type || 'info'))
            .find('.sc-attendance-qr-toast__text')
            .text(message || '');
    }

    function subjectTypeLabel(type) {
        return type === 'staff' ? 'پرسنل' : 'بازیکن';
    }

    function prependLog(item) {
        var $log = $('#sc-attendance-qr-log');
        if (!$log.length) return;
        var time = new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        var cls = item.ok ? (item.duplicate ? 'duplicate' : 'success') : 'error';
        var html = '<li class="sc-attendance-qr-log__item sc-attendance-qr-log__item--' + cls + '">' +
            '<span class="sc-attendance-qr-log__time">' + time + '</span>' +
            '<span class="sc-attendance-qr-log__name">' + (item.name || '—') + '</span>' +
            '<span class="sc-attendance-qr-log__msg">' + (item.message || '') + '</span></li>';
        $log.prepend(html);
        if ($log.children().length > 15) $log.children().last().remove();
    }

    function appendTableRow(rec) {
        var $tbody = $('#sc-tarddod-records-table tbody');
        if (!$tbody.length || !rec) return;
        var rowNum = $tbody.find('tr').length + 1;
        var typeLabel = subjectTypeLabel(rec.subject_type || '');
        var html = '<tr><td>' + rowNum + '</td><td>' + (rec.subject_name || '—') + '</td><td>' + typeLabel + '</td><td>' + (rec.created_at || '') + '</td></tr>';
        $tbody.append(html);
    }

    function incrementScanCount() {
        var count = parseInt($('#sc-attendance-qr-count').text(), 10) || 0;
        $('#sc-attendance-qr-count').text(count + 1);
    }

    function shouldIgnoreScan(hash, now) {
        if (inFlightHashes[hash]) return true;
        return hash === lastHandledHash && (now - lastHandledAt) < getDuplicateCooldown();
    }

    function markHandled(hash, now) {
        lastHandledHash = hash;
        lastHandledAt = now || Date.now();
    }

    function getActiveSessionMeta() {
        var $sel = $('#sc-tarddod-session-select');
        var selected = parseInt($sel.val(), 10) || 0;
        var title = cfg.sessionTitle || '';
        if ($sel.length && selected > 0) {
            var optText = $sel.find('option:selected').text() || '';
            if (optText) {
                title = optText.split('—')[0].trim() || optText.trim();
            }
        }
        return {
            id: selected > 0 ? selected : (parseInt(cfg.sessionId, 10) || 0),
            title: title
        };
    }

    function getActiveSessionId() {
        return getActiveSessionMeta().id;
    }

    function handleScan(decodedText) {
        var now = Date.now();
        var payload = prepareScanPayload(decodedText);
        if (!payload) {
            return;
        }

        var hashLen = extractHashHex(payload).length;
        if (hashLen > 0 && hashLen < getHashLength()) {
            if (now - lastPartialHintAt > 1800) {
                showToast('در حال خواندن QR... کارت را ثابت و کامل جلوی دوربین بگیرید', 'info');
                lastPartialHintAt = now;
            }
            return;
        }

        if (!isCompletePlayerPayload(payload) || shouldIgnoreScan(payload, now)) {
            return;
        }

        inFlightHashes[payload] = true;
        markHandled(payload, now);

        var localMember = lookupMember(payload);
        var sessionMeta = getActiveSessionMeta();
        var sessionId = sessionMeta.id;
        if (!sessionId) {
            playSound('error');
            showToast('جلسه‌ای انتخاب نشده است.', 'error');
            delete inFlightHashes[payload];
            return;
        }

        showToast('در حال ثبت...', 'info');

        var linesPreview = [
            localMember && localMember.name ? localMember.name : 'فرد',
            sessionMeta.title ? ('جلسه: ' + sessionMeta.title) : '',
            window.scQrScanSnapshot ? window.scQrScanSnapshot.nowLabelFa() : ''
        ];

        function postScan(photos) {
            photos = photos || { rear: '', front: '' };
            $.post(cfg.ajaxUrl, {
                action: 'sc_tarddod_scan',
                nonce: cfg.nonce,
                session_id: sessionId,
                qr_payload: payload,
                photo_data: photos.rear || '',
                photo_data_front: photos.front || ''
            }).done(function (res) {
                if (res && res.success) {
                    var code = res.data && res.data.code ? res.data.code : 'created';
                    var name = res.data && res.data.subject_name ? res.data.subject_name : (localMember ? localMember.name : '');
                    var typeLabel = subjectTypeLabel(res.data && res.data.subject_type ? res.data.subject_type : 'member');

                    if (code === 'duplicate') {
                        playSound('duplicate');
                        showToast(name + ' — قبلاً ثبت شده', 'duplicate');
                        prependLog({ ok: true, duplicate: true, name: name, message: typeLabel });
                        return;
                    }

                    playSound('success');
                    showToast(name + ' — تردد ثبت شد ✓', 'success');
                    prependLog({ ok: true, duplicate: false, name: name, message: typeLabel });
                    incrementScanCount();
                    appendTableRow({
                        subject_name: name,
                        subject_type: res.data.subject_type,
                        created_at: new Date().toLocaleString('fa-IR')
                    });
                    return;
                }

                var errCode = (res && res.data && res.data.code) ? res.data.code : '';
                var errMsg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت';
                var errName = (res && res.data && res.data.subject_name) ? res.data.subject_name : (localMember ? localMember.name : '');

                if (errCode === 'qr_disabled') {
                    playSound('disabled');
                } else if (errCode === 'invalid_qr') {
                    playSound('error');
                    errMsg = errMsg || 'کد QR نامعتبر است. QR را کامل جلوی دوربین بگیرید.';
                } else if (errCode === 'unknown_qr') {
                    playSound('error');
                    errMsg = errMsg || 'بازیکن مرتبط با این QR یافت نشد.';
                } else if (errCode === 'session_closed' || errCode === 'session_draft') {
                    playSound('error');
                } else {
                    playSound('error');
                }

                showToast((errName ? errName + ' — ' : '') + errMsg, 'error');
                prependLog({ ok: false, name: errName, message: errMsg });
            }).fail(function (xhr) {
                playSound('error');
                var msg = 'خطا در ارتباط با سرور';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                showToast(msg, 'error');
                prependLog({ ok: false, name: '', message: msg });
            }).always(function () {
                delete inFlightHashes[payload];
            });
        }

        if (cfg.snapshotEnabled && window.scQrScanSnapshot) {
            window.scQrScanSnapshot.prepareScanPhotos({
                readerId: 'sc-attendance-qr-reader',
                lines: linesPreview,
                captureFront: !!cfg.snapshotFrontEnabled,
                maxWidth: 720
            }).then(postScan).catch(function () {
                postScan({ rear: '', front: '' });
            });
        } else {
            postScan({ rear: '', front: '' });
        }
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
        return scanner.stop().then(function () {
            scanner.clear();
            $('#sc-attendance-qr-reader').empty();
            return startScannerStream(cameraPick);
        });
    }

    function switchCamera() {
        if (!scanner || cameraState.switching || cameraState.picks.length < 2) return;
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
        scanner.stop().then(function () { scanner.clear(); }).catch(function () {});
        scanner = null;
        $('#sc-attendance-qr-reader').empty();
        $('#sc-attendance-qr-start').prop('disabled', false).text('شروع اسکن');
        $('#sc-attendance-qr-stop').prop('disabled', true);
    }

    function beginScannerStream(cameras) {
        cameraState.picks = buildCameraPicks(cameras);
        cameraState.index = findDefaultCameraIndex(cameraState.picks);
        var mobile = isMobileDevice();

        return startScannerStream(getCurrentCameraPick()).catch(function () {
            if (!mobile) {
                var userIdx = cameraState.picks.findIndex(function (pick) {
                    return pick.mode === 'user' || pick.label === 'وب‌کم';
                });
                if (userIdx >= 0 && userIdx !== cameraState.index) {
                    cameraState.index = userIdx;
                    return startScannerStream(getCurrentCameraPick());
                }
                var deviceIdx = cameraState.picks.findIndex(function (pick) { return pick.mode === 'device'; });
                if (deviceIdx >= 0 && deviceIdx !== cameraState.index) {
                    cameraState.index = deviceIdx;
                    return startScannerStream(getCurrentCameraPick());
                }
                throw new Error('وب‌کم در دسترس نیست');
            }

            var envIdx = cameraState.picks.findIndex(function (pick) { return pick.mode === 'environment'; });
            if (envIdx >= 0 && envIdx !== cameraState.index) {
                cameraState.index = envIdx;
                return startScannerStream(getCurrentCameraPick());
            }
            var frontIdx = cameraState.picks.findIndex(function (pick) { return pick.mode === 'user'; });
            if (frontIdx >= 0 && frontIdx !== cameraState.index) {
                cameraState.index = frontIdx;
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
        if (!getActiveSessionId()) {
            showToast('جلسه‌ای انتخاب نشده است.', 'error');
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
        if (!$('.sc-tarddod-register-wrap #sc-attendance-qr-panel').length) return;
        preloadSounds();
        $('#sc-attendance-qr-start').on('click', startScanner);
        $('#sc-attendance-qr-switch').on('click', switchCamera);
        $('#sc-attendance-qr-stop').on('click', stopScanner);
        $(window).on('beforeunload', stopScanner);
    });
})(jQuery);
