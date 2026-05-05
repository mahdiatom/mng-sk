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
    var template = payload.template && typeof payload.template === 'object' ? payload.template : null;
    var templateLayout = template && template.layout ? template.layout : {};
    var photoPosition = templateLayout.photo_position || 'left';
    var rightFields = Array.isArray(templateLayout.right_fields) ? templateLayout.right_fields : [];
    var leftFields = Array.isArray(templateLayout.left_fields) ? templateLayout.left_fields : [];

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
        var hasPhoto = fields.indexOf('personal_photo') !== -1 && row.personal_photo && row.personal_photo !== '-';
        if (hasPhoto) {
            card.classList.add('has-photo');
            if (photoPosition === 'right') {
                card.classList.add('photo-right');
            }
        }
        var rawFields = fields.filter(function (f) { return f !== 'personal_photo'; });
        var used = {};
        var orderedFields = [];

        if (rightFields.length || leftFields.length) {
            rightFields.forEach(function (f) {
                if (rawFields.indexOf(f) !== -1 && !used[f]) {
                    orderedFields.push(f);
                    used[f] = true;
                }
            });
            leftFields.forEach(function (f) {
                if (rawFields.indexOf(f) !== -1 && !used[f]) {
                    orderedFields.push(f);
                    used[f] = true;
                }
            });
            rawFields.forEach(function (f) {
                if (!used[f]) {
                    orderedFields.push(f);
                }
            });
        } else {
            orderedFields = rawFields.slice();
        }

        if (hasPhoto) {
            var photoField = document.createElement('div');
            photoField.className = 'sc-card-field sc-card-photo';
            photoField.innerHTML =
                '<strong>' + escapeHtml(labels.personal_photo || 'عکس پرسنلی') + ':</strong><br>' +
                '<img src="' + escapeAttr(String(row.personal_photo)) + '" alt="photo">';
            card.appendChild(photoField);
        }

        var content = document.createElement('div');
        content.className = 'sc-card-content';

        orderedFields.forEach(function (field) {
            var value = row[field] == null ? '-' : String(row[field]);
            var fieldEl = document.createElement('div');
            fieldEl.className = 'sc-card-field';
            fieldEl.innerHTML = '<strong>' + escapeHtml(labels[field] || field) + ':</strong> ' + escapeHtml(value);
            content.appendChild(fieldEl);
        });

        card.appendChild(content);
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
