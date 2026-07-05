(function ($) {
    'use strict';

    var cfg = window.scAttendanceQr || {};
    var scanner = null;
    var inFlightHashes = {};
    var lastHandledHash = '';
    var lastHandledAt = 0;
    var sounds = {};

    function getDuplicateCooldown() {
        return parseInt(cfg.cooldownMs, 10) || 800;
    }

    function getScannerConfig(cameraId) {
        var config = {
            fps: 30,
            qrbox: function (viewfinderWidth, viewfinderHeight) {
                var edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.9);
                return { width: edge, height: edge };
            },
            aspectRatio: 1.7777778,
            disableFlip: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        };
        config.videoConstraints = {
            width: { ideal: 1280 },
            height: { ideal: 720 }
        };
        if (!cameraId) {
            config.videoConstraints.facingMode = 'environment';
        }
        return config;
    }

    function buildCameraConstraints(cameraId) {
        if (cameraId) {
            return { deviceId: { exact: cameraId } };
        }
        return { facingMode: 'environment' };
    }

    function createScannerInstance() {
        var options = { verbose: false };
        if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
            options.formatsToSupport = [Html5QrcodeSupportedFormats.QR_CODE];
        }
        return new Html5Qrcode('sc-attendance-qr-reader', options);
    }

    function preloadSounds() {
        ['success', 'error', 'duplicate'].forEach(function (type) {
            var url = cfg['sound' + type.charAt(0).toUpperCase() + type.slice(1)] || '';
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
        $box.removeClass('is-success is-error is-duplicate is-info')
            .addClass('is-visible is-' + (type || 'info'))
            .find('.sc-attendance-qr-toast__text')
            .text(message || '');
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
        var count = parseInt($('#sc-attendance-qr-count').text(), 10) || 0;
        if (item.ok && !item.duplicate) {
            $('#sc-attendance-qr-count').text(count + 1);
        }
    }

    function markMemberPresent(memberId) {
        if (!memberId) {
            return;
        }
        var $radio = $('input[type="radio"][name="attendance[' + memberId + ']"][value="present"]');
        if ($radio.length) {
            $radio.prop('checked', true).trigger('change');
            var $row = $radio.closest('tr');
            $row.addClass('sc-attendance-member-row--qr-scanned');
            setTimeout(function () {
                $row.removeClass('sc-attendance-member-row--qr-scanned');
            }, 1800);
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

    function handleScan(decodedText) {
        var now = Date.now();
        var hash = (decodedText || '').trim();
        if (!hash || shouldIgnoreScan(hash, now)) {
            return;
        }

        inFlightHashes[hash] = true;

        $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_scan',
            nonce: cfg.nonce,
            qr_payload: hash,
            course_id: cfg.courseId,
            attendance_date: cfg.attendanceDate,
            chapter_name: cfg.chapterName,
            group_name: cfg.groupName
        }).done(function (res) {
            if (res && res.success) {
                var code = res.data && res.data.code ? res.data.code : 'created';
                var name = res.data && res.data.member_name ? res.data.member_name : '';
                if (code === 'duplicate') {
                    playSound('duplicate');
                    showToast(name + ' — قبلاً ثبت شده', 'duplicate');
                    prependLog({ ok: true, duplicate: true, name: name, message: res.data.message });
                } else {
                    playSound('success');
                    showToast(name + ' — ثبت شد ✓', 'success');
                    prependLog({ ok: true, duplicate: false, name: name, message: res.data.message });
                    markMemberPresent(res.data.member_id);
                }
            } else {
                playSound('error');
                var errMsg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ثبت';
                var errName = (res && res.data && res.data.member_name) ? res.data.member_name : '';
                showToast(errMsg, 'error');
                prependLog({ ok: false, name: errName, message: errMsg });
            }
        }).fail(function () {
            playSound('error');
            showToast('خطا در ارتباط با سرور', 'error');
        }).always(function () {
            delete inFlightHashes[hash];
            markHandled(hash, Date.now());
        });
    }

    function stopScanner() {
        if (!scanner) {
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

    function startScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            showToast('کتابخانه اسکن بارگذاری نشده است.', 'error');
            return;
        }
        unlockAudio();
        $('#sc-attendance-qr-start').prop('disabled', true).text('در حال اسکن...');
        $('#sc-attendance-qr-stop').prop('disabled', false);

        scanner = createScannerInstance();

        Html5Qrcode.getCameras().then(function (cameras) {
            var cameraId = null;
            if (cameras && cameras.length) {
                var back = cameras.find(function (c) {
                    return /back|rear|environment|عقب/i.test((c.label || ''));
                });
                cameraId = back ? back.id : cameras[cameras.length - 1].id;
            }
            var config = getScannerConfig(cameraId);

            return scanner.start(buildCameraConstraints(cameraId), config, handleScan, function () {});
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
        $('#sc-attendance-qr-stop').on('click', function () {
            stopScanner();
        });

        $(window).on('beforeunload', function () {
            stopScanner();
        });
    });
})(jQuery);
