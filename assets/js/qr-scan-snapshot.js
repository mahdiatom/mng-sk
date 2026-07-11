(function (window) {
    'use strict';

    var vazirFontPromise = null;

    function getSnapshotConfig() {
        return window.scQrScanSnapshotConfig || {};
    }

    function ensureVazirFont() {
        if (vazirFontPromise) {
            return vazirFontPromise;
        }
        var cfg = getSnapshotConfig();
        var url = cfg.vazirFontUrl || '';
        if (!url || typeof FontFace === 'undefined' || !document.fonts) {
            vazirFontPromise = Promise.resolve(false);
            return vazirFontPromise;
        }
        vazirFontPromise = new FontFace('Vazir', 'url("' + url + '")', {
            style: 'normal',
            weight: '400 700'
        }).load().then(function (face) {
            document.fonts.add(face);
            return document.fonts.ready.then(function () { return true; });
        }).catch(function () {
            return false;
        });
        return vazirFontPromise;
    }

    function getReaderRoot(readerId) {
        return document.getElementById(readerId || 'sc-attendance-qr-reader');
    }

    function captureFromMedia(el, maxWidth) {
        if (!el) return '';
        maxWidth = maxWidth || 720;
        var vw = el.videoWidth || el.width || 0;
        var vh = el.videoHeight || el.height || 0;
        if (!vw || !vh) return '';
        var scale = vw > maxWidth ? maxWidth / vw : 1;
        var w = Math.max(1, Math.round(vw * scale));
        var h = Math.max(1, Math.round(vh * scale));
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        if (!ctx) return '';
        try {
            ctx.drawImage(el, 0, 0, w, h);
            return canvas.toDataURL('image/jpeg', 0.78);
        } catch (e) {
            return '';
        }
    }

    function captureFrame(readerId, maxWidth) {
        var root = getReaderRoot(readerId);
        if (!root) return '';
        var videos = root.querySelectorAll('video');
        for (var i = 0; i < videos.length; i++) {
            var shot = captureFromMedia(videos[i], maxWidth);
            if (shot) return shot;
        }
        var canvases = root.querySelectorAll('canvas');
        for (var j = 0; j < canvases.length; j++) {
            var shot2 = captureFromMedia(canvases[j], maxWidth);
            if (shot2) return shot2;
        }
        var allVideos = document.querySelectorAll('video');
        for (var k = 0; k < allVideos.length; k++) {
            var shot3 = captureFromMedia(allVideos[k], maxWidth);
            if (shot3) return shot3;
        }
        return '';
    }

    function drawOverlayText(ctx, img, lines, useVazir) {
        var barH = Math.max(72, Math.round(img.height * 0.22));
        ctx.fillStyle = 'rgba(15, 23, 42, 0.72)';
        ctx.fillRect(0, img.height - barH, img.width, barH);
        var fontSize = Math.max(14, Math.min(22, Math.round(img.width / 28)));
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'top';
        var family = useVazir
            ? 'Vazir, IRANYekanXFaNum, Tahoma, sans-serif'
            : 'Tahoma, "Segoe UI", sans-serif';
        ctx.font = '600 ' + fontSize + 'px ' + family;
        var pad = Math.round(fontSize * 0.7);
        var y = img.height - barH + pad;
        var x = img.width - pad;
        (lines || []).forEach(function (line) {
            if (!line) return;
            ctx.fillText(String(line), x, y);
            y += fontSize + Math.round(fontSize * 0.35);
        });
    }

    function applyOverlay(dataUrl, lines, quality) {
        return ensureVazirFont().then(function (useVazir) {
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
                    drawOverlayText(ctx, img, lines, !!useVazir);
                    try {
                        resolve(canvas.toDataURL('image/jpeg', quality || 0.72));
                    } catch (e) {
                        resolve(dataUrl);
                    }
                };
                img.onerror = function () { resolve(''); };
                img.src = dataUrl;
            });
        });
    }

    function captureFrontCamera(maxWidth) {
        return new Promise(function (resolve) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                resolve('');
                return;
            }
            var constraints = {
                audio: false,
                video: {
                    facingMode: { ideal: 'user' },
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            };
            navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
                var video = document.createElement('video');
                video.setAttribute('playsinline', 'true');
                video.muted = true;
                video.srcObject = stream;
                var done = false;
                function cleanup() {
                    try {
                        stream.getTracks().forEach(function (t) { t.stop(); });
                    } catch (e) {}
                    video.srcObject = null;
                }
                function finish(data) {
                    if (done) return;
                    done = true;
                    cleanup();
                    resolve(data || '');
                }
                video.onloadedmetadata = function () {
                    video.play().then(function () {
                        setTimeout(function () {
                            finish(captureFromMedia(video, maxWidth || 640));
                        }, 350);
                    }).catch(function () {
                        finish('');
                    });
                };
                setTimeout(function () { finish(''); }, 4000);
            }).catch(function () {
                resolve('');
            });
        });
    }

    /**
     * Capture scan camera (+ optional front), apply overlay.
     * @returns {Promise<{rear:string,front:string}>}
     */
    function prepareScanPhotos(opts) {
        opts = opts || {};
        var rearRaw = captureFrame(opts.readerId || 'sc-attendance-qr-reader', opts.maxWidth || 720);
        var lines = opts.lines || [];
        var wantFront = !!opts.captureFront;

        return applyOverlay(rearRaw, lines, 0.72).then(function (rear) {
            if (!wantFront) {
                return { rear: rear || '', front: '' };
            }
            return captureFrontCamera(640).then(function (frontRaw) {
                if (!frontRaw) {
                    return { rear: rear || '', front: '' };
                }
                var frontLines = (lines || []).slice();
                frontLines.push('دوربین جلو');
                return applyOverlay(frontRaw, frontLines, 0.72).then(function (front) {
                    return { rear: rear || '', front: front || '' };
                });
            });
        });
    }

    function nowLabelFa() {
        try {
            return new Date().toLocaleString('fa-IR', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        } catch (e) {
            return new Date().toISOString();
        }
    }

    // Preload Vazir as soon as scanner page loads
    ensureVazirFont();

    window.scQrScanSnapshot = {
        captureFrame: captureFrame,
        captureFrontCamera: captureFrontCamera,
        applyOverlay: applyOverlay,
        prepareScanPhotos: prepareScanPhotos,
        nowLabelFa: nowLabelFa,
        ensureVazirFont: ensureVazirFont
    };
})(window);
