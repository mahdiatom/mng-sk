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
    var exportMode = payload.export_mode === 'cards_zip' ? 'cards_zip' : 'pdf';
    var zipFilename = payload.zip_filename || 'cards_export';
    var templateLayout = template && template.layout ? template.layout : {};
    var imageFields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo', 'attendance_qr'];
    var fieldsWithoutDisplayLabels = imageFields.concat(['attendance_qr_short_code', 'attendance_qr_all_codes']);
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
    var contentFontSize = template && template.content_font_size ? parseInt(template.content_font_size, 10) : 13;
    if (Number.isNaN(contentFontSize) || contentFontSize < 8) {
        contentFontSize = 13;
    }
    document.body.style.setProperty('--sc-export-font-size', contentFontSize + 'px');

    var defaultImageSizes = {
        personal_photo: { width: 150, height: 190 },
        id_card_photo: { width: 260, height: 150 },
        sport_insurance_photo: { width: 260, height: 150 },
        attendance_qr: { width: 180, height: 180 }
    };

    var header = document.createElement('div');
    header.className = 'sc-print-header';
    header.innerHTML =
        '<div class="sc-print-title">' + escapeHtml(title) + '</div>' +
        '<div class="sc-print-actions">' +
        '<button type="button" id="sc-print-btn">چاپ / PDF</button>' +
        '</div>';

    var grid = document.createElement('div');
    grid.className = 'sc-print-grid';

    rows.forEach(function (row, rowIndex) {
        var card = document.createElement('div');
        card.className = 'sc-card sc-export-card';
        card.id = 'sc-export-card-' + rowIndex;
        card.setAttribute('data-card-index', String(rowIndex));
        card.setAttribute('data-card-filename', buildCardFilename(row, rowIndex));
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
        content.className = 'sc-card-content sc-export-card-content';
        content.id = 'sc-export-card-content-' + rowIndex;
        content.style.gridTemplateColumns = buildLayoutGridTemplateColumns(
            template && template.column_widths ? template.column_widths : null,
            contentColumnsCount
        );

        if (!imageOnlyMode) {
            columnsFields.forEach(function (fieldsInCol, colIndex) {
                var col = document.createElement('div');
                col.className = 'sc-card-col sc-export-card-col';
                col.id = 'sc-export-card-col-' + rowIndex + '-' + (colIndex + 1);
                col.setAttribute('data-column', 'column_' + (colIndex + 1));
                applyColumnPadding(col, 'column_' + (colIndex + 1));
                fieldsInCol.forEach(function (field) {
                    col.appendChild(buildField(field, row[field], rowIndex));
                });
                if (!fieldsInCol.length) {
                    col.appendChild(buildField('-', '-', rowIndex));
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
                infoItem.className = 'sc-image-only-info-item sc-export-image-only-info-item';
                infoItem.setAttribute('data-field', field);
                if (shouldRenderFieldLabel(field)) {
                    infoItem.innerHTML = '<span class="sc-card-field-label">' + escapeHtml(labels[field] || field) + ':</span> <span class="sc-card-field-value">' + escapeHtml(value) + '</span>';
                } else {
                    infoItem.innerHTML = '<span class="sc-card-field-value">' + escapeHtml(value) + '</span>';
                }
                infoRow.appendChild(infoItem);
            });
            if (infoRow.childNodes.length) {
                content.appendChild(infoRow);
            }
        }

        imageFields.forEach(function (imgField) {
            if (fields.indexOf(imgField) !== -1) {
                var imageFieldEl = document.createElement('div');
                imageFieldEl.className = 'sc-card-field sc-card-inline-image sc-card-inline-image--' + imgField + ' sc-export-field sc-export-field--' + imgField + ' sc-field-no-label';
                imageFieldEl.id = 'sc-export-field-' + rowIndex + '-' + imgField;
                imageFieldEl.setAttribute('data-field', imgField);
                if (row[imgField] && row[imgField] !== '-') {
                    imageFieldEl.innerHTML =
                        '<img class="sc-card-field-image" src="' + escapeAttr(String(row[imgField])) + '" alt="' + escapeAttr(labels[imgField] || imgField) + '">';
                    var imgNode = imageFieldEl.querySelector('img');
                    if (imgNode) {
                        applyImageStyle(imgNode);
                        applyImageDimensions(imgNode, imgField);
                    }
                } else if (imgField === 'personal_photo') {
                    imageFieldEl.innerHTML =
                        '<div class="sc-photo-placeholder sc-card-field-image">' +
                        '  <span class="sc-photo-placeholder-icon" aria-hidden="true">👤</span>' +
                        '  <span class="sc-photo-placeholder-text">عکس ندارد</span>' +
                        '</div>';
                    var placeholderNode = imageFieldEl.querySelector('.sc-photo-placeholder');
                    if (placeholderNode) {
                        applyImageStyle(placeholderNode);
                        applyImageDimensions(placeholderNode, imgField);
                    }
                } else if (imgField === 'attendance_qr') {
                    imageFieldEl.innerHTML =
                        '<div class="sc-photo-placeholder sc-export-field-placeholder">' +
                        '  <span class="sc-photo-placeholder-icon" aria-hidden="true">▦</span>' +
                        '  <span class="sc-photo-placeholder-text">QR موجود نیست</span>' +
                        '</div>';
                    var qrPlaceholder = imageFieldEl.querySelector('.sc-photo-placeholder');
                    if (qrPlaceholder) {
                        applyImageStyle(qrPlaceholder);
                        applyImageDimensions(qrPlaceholder, imgField);
                    }
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
                noteEl.className = 'sc-card-additional-note sc-export-card-footer';
                noteEl.id = 'sc-export-card-footer-' + rowIndex;
                noteEl.innerHTML = escapeHtml(footerText).replace(/\n/g, '<br>');
                card.appendChild(noteEl);
            }
        }
        applyCardSizing(card);
        applyCardBackground(card);
        grid.appendChild(card);
    });

    root.innerHTML = '';
    root.appendChild(header);
    root.appendChild(grid);

    var printBtn = document.getElementById('sc-print-btn');
    if (printBtn) {
        if (exportMode === 'cards_zip') {
            printBtn.textContent = 'دانلود ZIP تصاویر';
            printBtn.addEventListener('click', function () {
                whenCardsZipLibsReady(function () {
                    startCardsZipExport(header, zipFilename);
                });
            });
            whenCardsZipLibsReady(function () {
                startCardsZipExport(header, zipFilename);
            }, function () {
                showCardsZipProgress(header, 'کتابخانه‌های ساخت ZIP بارگذاری نشدند. صفحه را رفرش کنید.');
            });
        } else {
            printBtn.addEventListener('click', function () {
                window.print();
            });
        }
    }

    function shouldShowFieldLabels() {
        return !template || Number(template.show_field_labels) !== 0;
    }

    function shouldRenderFieldLabel(field) {
        if (fieldsWithoutDisplayLabels.indexOf(field) !== -1) {
            return false;
        }
        return shouldShowFieldLabels();
    }

    function buildCardFilename(row, index) {
        var name = row && row.full_name ? String(row.full_name).trim() : '';
        var phone = row && row.player_phone ? String(row.player_phone).replace(/\D/g, '') : '';
        if (name && phone) {
            return name + ' ' + phone;
        }
        if (name) {
            return name;
        }
        if (phone) {
            return phone;
        }
        return 'card_' + (index + 1);
    }

    function sanitizeZipFilename(name) {
        return String(name || 'card')
            .replace(/[\\/:*?"<>|]+/g, '_')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 120) || 'card';
    }

    function waitForCardImages() {
        var images = Array.prototype.slice.call(document.querySelectorAll('.sc-export-card img, .sc-card img'));
        return Promise.all(images.map(function (img) {
            if (img.complete) {
                return Promise.resolve();
            }
            return new Promise(function (resolve) {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            });
        }));
    }

    function showCardsZipProgress(progressHost, message) {
        if (!progressHost) {
            return;
        }
        var progress = document.getElementById('sc-cards-zip-progress');
        if (!progress) {
            progress = document.createElement('div');
            progress.id = 'sc-cards-zip-progress';
            progress.className = 'sc-cards-zip-progress';
            progressHost.appendChild(progress);
        }
        progress.textContent = message;
    }

    function whenCardsZipLibsReady(callback, onFail, attempts) {
        attempts = attempts || 0;
        if (typeof html2canvas !== 'undefined' && typeof JSZip !== 'undefined') {
            callback();
            return;
        }
        if (attempts > 50) {
            if (typeof onFail === 'function') {
                onFail();
            }
            return;
        }
        setTimeout(function () {
            whenCardsZipLibsReady(callback, onFail, attempts + 1);
        }, 100);
    }

    function startCardsZipExport(progressHost, filename) {
        if (typeof html2canvas === 'undefined' || typeof JSZip === 'undefined') {
            showCardsZipProgress(progressHost, 'کتابخانه‌های ساخت ZIP بارگذاری نشدند. صفحه را رفرش کنید.');
            return;
        }
        showCardsZipProgress(progressHost, 'در حال آماده‌سازی تصاویر کارت‌ها...');
        waitForCardImages().then(function () {
            var cards = Array.prototype.slice.call(document.querySelectorAll('.sc-export-card'));
            if (!cards.length) {
                showCardsZipProgress(progressHost, 'کارتی برای خروجی یافت نشد.');
                return;
            }
            var zip = new JSZip();
            var usedNames = {};
            var chain = Promise.resolve();
            cards.forEach(function (card, idx) {
                chain = chain.then(function () {
                    showCardsZipProgress(progressHost, 'در حال پردازش کارت ' + (idx + 1) + ' از ' + cards.length + '...');
                    return html2canvas(card, {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: null,
                        logging: false
                    }).then(function (canvas) {
                        var baseName = sanitizeZipFilename(card.getAttribute('data-card-filename') || ('card_' + (idx + 1)));
                        var uniqueName = baseName;
                        var counter = 2;
                        while (usedNames[uniqueName]) {
                            uniqueName = baseName + '_' + counter;
                            counter++;
                        }
                        usedNames[uniqueName] = true;
                        var dataUrl = canvas.toDataURL('image/png');
                        var base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
                        zip.file(uniqueName + '.png', base64, { base64: true });
                    });
                });
            });
            return chain.then(function () {
                showCardsZipProgress(progressHost, 'در حال فشرده‌سازی فایل ZIP...');
                return zip.generateAsync({ type: 'blob' });
            }).then(function (blob) {
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = sanitizeZipFilename(filename) + '.zip';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showCardsZipProgress(progressHost, 'فایل ZIP با موفقیت دانلود شد.');
            }).catch(function () {
                showCardsZipProgress(progressHost, 'خطا در ساخت فایل ZIP. لطفاً دوباره تلاش کنید.');
            });
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

    function buildField(field, value, rowIndex) {
        var fieldEl = document.createElement('div');
        fieldEl.className = 'sc-card-field sc-export-field' + (shouldRenderFieldLabel(field) ? '' : ' sc-field-no-label');
        if (field === '-') {
            fieldEl.innerHTML = '&nbsp;';
            return fieldEl;
        }
        fieldEl.id = 'sc-export-field-' + rowIndex + '-' + field;
        fieldEl.setAttribute('data-field', field);
        var cleanValue = value == null ? '-' : String(value);
        var labelHtml = shouldRenderFieldLabel(field)
            ? '<span class="sc-card-field-label">' + escapeHtml(labels[field] || field) + ':</span> '
            : '';
        fieldEl.innerHTML = labelHtml + '<span class="sc-card-field-value">' + escapeHtml(cleanValue) + '</span>';
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

    function resolveCardDimensionsFromTemplate() {
        var presets = {
            id_card: { width: 8.5, height: 5.4 },
            id_card_small: { width: 8.5, height: 4.5 },
            business_card: { width: 9.0, height: 5.0 },
            credit_card: { width: 8.56, height: 5.398 },
            a7: { width: 7.4, height: 10.5 }
        };
        var preset = template && template.card_size_preset ? String(template.card_size_preset) : 'id_card';
        if (preset !== 'custom' && presets[preset]) {
            return presets[preset];
        }
        return {
            width: template && template.card_width_cm ? parseFloat(template.card_width_cm) : 8.5,
            height: template && template.card_height_cm ? parseFloat(template.card_height_cm) : 5.4
        };
    }

    function applyCardSizing(card) {
        if (!template) {
            return;
        }
        var dims = resolveCardDimensionsFromTemplate();
        if (!Number.isNaN(dims.width) && dims.width > 0) {
            card.style.width = dims.width + 'cm';
        }
        if (!Number.isNaN(dims.height) && dims.height > 0) {
            card.style.height = dims.height + 'cm';
            card.style.minHeight = dims.height + 'cm';
        }
        card.style.boxSizing = 'border-box';
        var pt = parseFloat(template.card_padding_top);
        var pr = parseFloat(template.card_padding_right);
        var pb = parseFloat(template.card_padding_bottom);
        var pl = parseFloat(template.card_padding_left);
        if (Number.isNaN(pt)) { pt = 3; }
        if (Number.isNaN(pr)) { pr = 3; }
        if (Number.isNaN(pb)) { pb = 3; }
        if (Number.isNaN(pl)) { pl = 3; }
        card.style.padding = pt + 'mm ' + pr + 'mm ' + pb + 'mm ' + pl + 'mm';
        var fontSize = template.content_font_size ? parseInt(template.content_font_size, 10) : 13;
        if (Number.isNaN(fontSize) || fontSize < 8) {
            fontSize = 13;
        }
        card.style.fontSize = fontSize + 'px';
        var imageStyle = template.image_style === 'circle' ? 'circle' : 'rounded';
        card.setAttribute('data-image-style', imageStyle);
    }

    function applyColumnPadding(col, columnKey) {
        if (!template || !columnKey) {
            return;
        }
        var paddings = template.column_paddings && typeof template.column_paddings === 'object' ? template.column_paddings : {};
        var pad = paddings[columnKey] || { top: 0, right: 0, bottom: 0, left: 0 };
        var pt = parseFloat(pad.top);
        var pr = parseFloat(pad.right);
        var pb = parseFloat(pad.bottom);
        var pl = parseFloat(pad.left);
        if (Number.isNaN(pt)) { pt = 0; }
        if (Number.isNaN(pr)) { pr = 0; }
        if (Number.isNaN(pb)) { pb = 0; }
        if (Number.isNaN(pl)) { pl = 0; }
        col.style.padding = pt + 'mm ' + pr + 'mm ' + pb + 'mm ' + pl + 'mm';
    }

    function applyImageStyle(node) {
        if (!node) {
            return;
        }
        var style = template && template.image_style === 'circle' ? 'circle' : 'rounded';
        node.classList.add(style === 'circle' ? 'sc-image-style-circle' : 'sc-image-style-rounded');
    }

    function applyImageDimensions(node, imgField) {
        if (!node || !imgField) {
            return;
        }
        var sizes = null;
        if (template && template.image_sizes && template.image_sizes[imgField]) {
            sizes = template.image_sizes[imgField];
        } else if (defaultImageSizes[imgField]) {
            sizes = defaultImageSizes[imgField];
        }
        if (!sizes) {
            return;
        }
        var width = parseInt(sizes.width, 10);
        var height = parseInt(sizes.height, 10);
        if (!Number.isNaN(width) && width > 0) {
            node.style.maxWidth = width + 'px';
            node.style.width = width + 'px';
        }
        if (!Number.isNaN(height) && height > 0) {
            node.style.maxHeight = height + 'px';
            node.style.height = height + 'px';
        }
    }

    function buildLayoutGridTemplateColumns(columnWidths, columnsCount) {
        var parts = [];
        var total = 0;
        for (var i = 1; i <= columnsCount; i++) {
            var colKey = 'column_' + i;
            var weight = columnWidths && columnWidths[colKey] ? parseFloat(columnWidths[colKey]) : (100 / columnsCount);
            if (Number.isNaN(weight) || weight <= 0) {
                weight = 100 / columnsCount;
            }
            parts.push(weight);
            total += weight;
        }
        if (total <= 0) {
            return 'repeat(' + columnsCount + ', minmax(0, 1fr))';
        }
        return parts.map(function (weight) {
            return 'minmax(0, ' + weight + 'fr)';
        }).join(' ');
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
