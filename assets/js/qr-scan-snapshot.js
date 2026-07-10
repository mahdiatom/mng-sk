(function (window) {
    'use strict';

    function getVideoEl(readerId) {
        var root = document.getElementById(readerId || 'sc-attendance-qr-reader');
        if (!root) return null;
        return root.querySelector('video');
    }

    function captureFrame(readerId, maxWidth) {
        var video = getVideoEl(readerId);
        if (!video || !video.videoWidth) return '';
        maxWidth = maxWidth || 720;
        var vw = video.videoWidth;
        var vh = video.videoHeight;
        var scale = vw > maxWidth ? maxWidth / vw : 1;
        var w = Math.max(1, Math.round(vw * scale));
        var h = Math.max(1, Math.round(vh * scale));
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        if (!ctx) return '';
        ctx.drawImage(video, 0, 0, w, h);
        try {
            return canvas.toDataURL('image/jpeg', 0.85);
        } catch (e) {
            return '';
        }
    }

    function applyOverlay(dataUrl, lines, quality) {
        return new Promise(function (resolve) {
            if (!dataUrl) {
                resolve('');
                return;
            }
            var img = new Image();
            img.onload = function () {
                var canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(dataUrl);
                    return;
                }
                ctx.drawImage(img, 0, 0);
                var barH = Math.max(72, Math.round(img.height * 0.22));
                ctx.fillStyle = 'rgba(15, 23, 42, 0.72)';
                ctx.fillRect(0, img.height - barH, img.width, barH);

                var fontSize = Math.max(14, Math.min(22, Math.round(img.width / 28)));
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'top';
                ctx.font = '600 ' + fontSize + 'px Tahoma, "Segoe UI", sans-serif';

                var pad = Math.round(fontSize * 0.7);
                var y = img.height - barH + pad;
                var x = img.width - pad;
                (lines || []).forEach(function (line) {
                    if (!line) return;
                    ctx.fillText(String(line), x, y);
                    y += fontSize + Math.round(fontSize * 0.35);
                });

                try {
                    resolve(canvas.toDataURL('image/jpeg', quality || 0.72));
                } catch (e) {
                    resolve(dataUrl);
                }
            };
            img.onerror = function () {
                resolve('');
            };
            img.src = dataUrl;
        });
    }

    function uploadSnapshot(opts) {
        if (!opts || !opts.ajaxUrl || !opts.nonce || !opts.recordId || !opts.photoData) {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            if (typeof jQuery === 'undefined') {
                resolve(null);
                return;
            }
            jQuery.post(opts.ajaxUrl, {
                action: 'sc_qr_scan_snapshot_save',
                nonce: opts.nonce,
                context: opts.context || 'attendance',
                record_id: opts.recordId,
                photo_data: opts.photoData
            }).done(function (res) {
                resolve(res && res.success ? res.data : null);
            }).fail(function () {
                resolve(null);
            });
        });
    }

    function nowLabelFa() {
        try {
            return new Date().toLocaleString('fa-IR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        } catch (e) {
            return new Date().toISOString();
        }
    }

    window.scQrScanSnapshot = {
        captureFrame: captureFrame,
        applyOverlay: applyOverlay,
        uploadSnapshot: uploadSnapshot,
        nowLabelFa: nowLabelFa
    };
})(window);
