(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var card = document.querySelector('.sc-player-qr-card');
        if (!card) {
            return;
        }

        var img = card.querySelector('.sc-player-qr-image');
        var downloadBtn = card.querySelector('.sc-player-qr-download');
        var printBtn = card.querySelector('.sc-player-qr-print');

        if (printBtn) {
            printBtn.addEventListener('click', function () {
                window.print();
            });
        }

        if (downloadBtn && downloadBtn.getAttribute('href')) {
            return;
        }

        if (downloadBtn && img && img.src && img.src.indexOf('data:image/png') === 0) {
            downloadBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var link = document.createElement('a');
                link.href = img.src;
                link.download = downloadBtn.getAttribute('data-filename') || 'attendance-qr.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    });
})();
