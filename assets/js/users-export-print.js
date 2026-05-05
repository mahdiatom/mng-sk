(function () {
    'use strict';

    var root = document.getElementById('sc-users-export-print-root');
    if (!root) {
        return;
    }

    var raw = root.getAttribute('data-print') || '{}';
    var payload = {};
    try {
        payload = JSON.parse(raw);
    } catch (e) {
        payload = {};
    }

    var rows = Array.isArray(payload.rows) ? payload.rows : [];
    var fields = Array.isArray(payload.fields) ? payload.fields : [];
    var labels = payload.labels || {};
    var title = payload.title || 'خروجی اطلاعات کاربران';
    var pageSize = payload.page_size || 'A4';
    var cardsPerPage = parseInt(payload.cards_per_page, 10) || 2;

    document.body.setAttribute('data-page-size', pageSize);
    document.body.setAttribute('data-cards', String(cardsPerPage));

    var header = document.createElement('div');
    header.className = 'sc-print-header';
    header.innerHTML =
        '<div class="sc-print-title">' + escapeHtml(title) + '</div>' +
        '<div class="sc-print-actions">' +
        '<button type="button" id="sc-print-btn">چاپ / PDF</button>' +
        '</div>';

    var grid = document.createElement('div');
    grid.className = 'sc-print-grid';

    rows.forEach(function (row) {
        var card = document.createElement('div');
        card.className = 'sc-card';
        fields.forEach(function (field) {
            var value = row[field] == null ? '-' : String(row[field]);
            if (field === 'personal_photo') {
                var photo = document.createElement('div');
                photo.className = 'sc-card-field sc-card-photo';
                if (value && value !== '-') {
                    photo.innerHTML =
                        '<strong>' + escapeHtml(labels[field] || field) + ':</strong><br>' +
                        '<img src="' + escapeAttr(value) + '" alt="photo">';
                } else {
                    photo.innerHTML = '<strong>' + escapeHtml(labels[field] || field) + ':</strong> -';
                }
                card.appendChild(photo);
                return;
            }
            var fieldEl = document.createElement('div');
            fieldEl.className = 'sc-card-field';
            fieldEl.innerHTML = '<strong>' + escapeHtml(labels[field] || field) + ':</strong> ' + escapeHtml(value);
            card.appendChild(fieldEl);
        });
        grid.appendChild(card);
    });

    root.innerHTML = '';
    root.appendChild(header);
    root.appendChild(grid);

    var printBtn = document.getElementById('sc-print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }
})();
