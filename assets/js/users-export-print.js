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
    var columnsCount = parseInt(payload.columns_count, 10) || 2;
    var template = payload.template && typeof payload.template === 'object' ? payload.template : null;
    var templateLayout = template && template.layout ? template.layout : {};
    var imageFields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo'];
    var layoutColumnsFromTemplate = template && template.layout_columns_count ? parseInt(template.layout_columns_count, 10) : 2;
    var contentColumnsCount = Math.max(1, Math.min(4, layoutColumnsFromTemplate || 2));
    var layoutColumns = templateLayout && templateLayout.columns && typeof templateLayout.columns === 'object'
        ? templateLayout.columns
        : null;
    var rightFields = Array.isArray(templateLayout.right_fields) ? templateLayout.right_fields : [];
    var leftFields = Array.isArray(templateLayout.left_fields) ? templateLayout.left_fields : [];

    document.body.setAttribute('data-page-size', pageSize);
    document.body.setAttribute('data-cards', String(cardsPerPage));
    document.body.setAttribute('data-columns', String(Math.max(1, Math.min(4, columnsCount))));
    var chosenFont = resolveExportFontFamily(template && template.content_font_family);
    document.body.style.setProperty('--sc-export-font-family', chosenFont);
    document.body.style.fontFamily = chosenFont;

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
        var imageOnlyMode = template && Number(template.image_only_mode) === 1;
        if (imageOnlyMode) {
            card.classList.add('image-only');
        }
        var rawFields = fields.filter(function (f) { return imageFields.indexOf(f) === -1; });
        var used = {};
        var columnsFields = [];
        for (var c = 1; c <= contentColumnsCount; c++) {
            columnsFields.push([]);
        }
        if (layoutColumns) {
            for (var i = 1; i <= contentColumnsCount; i++) {
                var colArr = Array.isArray(layoutColumns['column_' + i]) ? layoutColumns['column_' + i] : [];
                colArr.forEach(function (f) {
                    if (rawFields.indexOf(f) !== -1 && !used[f]) {
                        columnsFields[i - 1].push(f);
                        used[f] = true;
                    }
                });
            }
        } else {
            rightFields.forEach(function (f) {
                if (rawFields.indexOf(f) !== -1 && !used[f]) {
                    columnsFields[0].push(f);
                    used[f] = true;
                }
            });
            if (contentColumnsCount > 1) {
                leftFields.forEach(function (f) {
                    if (rawFields.indexOf(f) !== -1 && !used[f]) {
                        columnsFields[1].push(f);
                        used[f] = true;
                    }
                });
            }
        }
        rawFields.forEach(function (f) {
            if (!used[f]) {
                var minIdx = 0;
                for (var j = 1; j < columnsFields.length; j++) {
                    if (columnsFields[j].length < columnsFields[minIdx].length) {
                        minIdx = j;
                    }
                }
                columnsFields[minIdx].push(f);
            }
        });

        var content = document.createElement('div');
        content.className = 'sc-card-content';
        content.style.gridTemplateColumns = 'repeat(' + contentColumnsCount + ', minmax(0, 1fr))';

        if (!imageOnlyMode) {
            columnsFields.forEach(function (fieldsInCol) {
                var col = document.createElement('div');
                col.className = 'sc-card-col';
                fieldsInCol.forEach(function (field) {
                    col.appendChild(buildField(field, row[field]));
                });
                if (!fieldsInCol.length) {
                    col.appendChild(buildField('-', '-'));
                }
                content.appendChild(col);
            });
        } else {
            var infoRow = document.createElement('div');
            infoRow.className = 'sc-image-only-info-row';
            rawFields.forEach(function (field) {
                var value = row[field] == null ? '-' : String(row[field]);
                if (value === '-' || value.trim() === '') {
                    return;
                }
                var infoItem = document.createElement('span');
                infoItem.className = 'sc-image-only-info-item';
                infoItem.innerHTML = '<strong>' + escapeHtml(labels[field] || field) + ':</strong> ' + escapeHtml(value);
                infoRow.appendChild(infoItem);
            });
            if (infoRow.childNodes.length) {
                content.appendChild(infoRow);
            }
        }

        imageFields.forEach(function (imgField) {
            if (fields.indexOf(imgField) !== -1) {
                var imageFieldEl = document.createElement('div');
                imageFieldEl.className = 'sc-card-field sc-card-inline-image sc-card-inline-image--' + imgField;
                if (row[imgField] && row[imgField] !== '-') {
                    imageFieldEl.innerHTML =
                        '<strong>' + escapeHtml(labels[imgField] || imgField) + ':</strong>' +
                        '<img src="' + escapeAttr(String(row[imgField])) + '" alt="' + escapeAttr(labels[imgField] || imgField) + '">';
                } else if (imgField === 'personal_photo') {
                    imageFieldEl.innerHTML =
                        '<strong>' + escapeHtml(labels[imgField] || imgField) + ':</strong>' +
                        '<div class="sc-photo-placeholder">' +
                        '  <span class="sc-photo-placeholder-icon" aria-hidden="true">👤</span>' +
                        '  <span class="sc-photo-placeholder-text">عکس ندارد</span>' +
                        '</div>';
                } else {
                    return;
                }
                if (imageOnlyMode) {
                    content.appendChild(imageFieldEl);
                } else {
                    var targetColIndex = getFieldTargetColumnIndex(imgField);
                    var $cols = content.querySelectorAll('.sc-card-col');
                    var targetCol = $cols[targetColIndex] || $cols[0];
                    if (targetCol) {
                        targetCol.insertBefore(imageFieldEl, targetCol.firstChild);
                    } else {
                        content.appendChild(imageFieldEl);
                    }
                }
            }
        });

        card.appendChild(content);
        if (template && template.card_footer_text) {
            var footerText = String(template.card_footer_text).trim();
            if (footerText !== '') {
                var noteEl = document.createElement('div');
                noteEl.className = 'sc-card-additional-note';
                noteEl.innerHTML = escapeHtml(footerText).replace(/\n/g, '<br>');
                card.appendChild(noteEl);
            }
        }
        applyCardBackground(card);
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

    function buildField(field, value) {
        var fieldEl = document.createElement('div');
        fieldEl.className = 'sc-card-field';
        if (field === '-') {
            fieldEl.innerHTML = '&nbsp;';
            return fieldEl;
        }
        var cleanValue = value == null ? '-' : String(value);
        fieldEl.innerHTML = '<strong>' + escapeHtml(labels[field] || field) + ':</strong> ' + escapeHtml(cleanValue);
        return fieldEl;
    }

    function applyCardBackground(card) {
        var bgColor = template && template.background_color ? String(template.background_color) : '#ffffff';
        var bgImage = template && template.background_image ? String(template.background_image) : '';
        var bgOpacity = template && typeof template.background_opacity !== 'undefined' ? parseFloat(template.background_opacity) : 0.2;
        if (Number.isNaN(bgOpacity)) {
            bgOpacity = 0.2;
        }
        bgOpacity = Math.max(0, Math.min(1, bgOpacity));
        card.style.backgroundColor = bgImage ? 'transparent' : bgColor;
        if (bgImage) {
            var layer = document.createElement('div');
            layer.className = 'sc-card-background-layer';
            layer.style.backgroundImage = 'url("' + escapeAttr(bgImage) + '")';
            layer.style.opacity = String(bgOpacity);
            card.insertBefore(layer, card.firstChild);
        }
    }

    function getFieldTargetColumnIndex(fieldKey) {
        if (!layoutColumns) {
            return 0;
        }
        for (var i = 1; i <= contentColumnsCount; i++) {
            var colArr = Array.isArray(layoutColumns['column_' + i]) ? layoutColumns['column_' + i] : [];
            if (colArr.indexOf(fieldKey) !== -1) {
                return i - 1;
            }
        }
        return 0;
    }

    function resolveExportFontFamily(fontKey) {
        var key = String(fontKey || 'IRANYekanXFaNum');
        var map = {
            IRANYekanXFaNum: '"IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Vazir: '"Vazir", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Shabnam: '"Shabnam", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Morabba: '"Morabba", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Tahoma: 'Tahoma, Arial, sans-serif',
            Arial: 'Arial, Tahoma, sans-serif'
        };
        return map[key] || map.IRANYekanXFaNum;
    }
})();
