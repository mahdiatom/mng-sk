(function ($) {
    'use strict';

    var cfg = window.scProgramsAdmin || {};

    function ajax(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = cfg.nonce || '';
        return $.post(cfg.ajaxUrl || cfg.ajaxurl || window.ajaxurl || '', data);
    }

    function toast(msg, isError) {
        var $s = $('#sc-prog-save-status');
        if (!$s.length) {
            window.alert(msg);
            return;
        }
        $s.prop('hidden', false).text(msg).toggleClass('is-error', !!isError);
        clearTimeout($s.data('toastTimer'));
        $s.data('toastTimer', setTimeout(function () {
            if ($s.text() === msg) {
                $s.text('').prop('hidden', true);
            }
        }, 3500));
    }

    /* -------- Schedule mode (fixed / weekly / manual) -------- */
    function syncScheduleMode($root) {
        var mode = String($root.find('[name="schedule_mode"]').val() || 'fixed');
        $root.find('.sc-schedule-field').hide();
        $root.find('.sc-schedule-' + mode).show();
        $root.find('[data-schedule-field]').each(function () {
            var fields = String($(this).attr('data-schedule-field') || '').split(/\s+/);
            $(this).toggle(fields.indexOf(mode) !== -1);
        });
    }

    /* -------- Library media fields + upload -------- */
    function syncLibMedia($form) {
        var t = String($form.find('[name="media_type"], #sc-lib-media-type').val() || 'none');
        $form.find('.sc-lib-media-url-wrap').toggle(t === 'aparat' || t === 'url');
        $form.find('.sc-lib-media-file-wrap').toggle(t === 'file');
        $form.find('[data-media-field]').each(function () {
            var fields = String($(this).attr('data-media-field') || '').split(/\s+/);
            $(this).toggle(fields.indexOf(t) !== -1);
        });
    }

    function bindLibraryForm() {
        var $form = $('#sc-program-library-form');
        if (!$form.length) {
            return;
        }
        syncLibMedia($form);
        $form.on('change', '[name="media_type"]', function () {
            syncLibMedia($form);
        });

        var $fileInput = $('#sc-lib-file-input');
        if ($fileInput.length && !$form.find('.sc-prog-upload-btn').length) {
            $fileInput.after('<button type="button" class="sc_button sc-prog-upload-btn">آپلود فایل</button>');
        }

        $form.on('click', '.sc-prog-upload-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var file = $fileInput[0] && $fileInput[0].files && $fileInput[0].files[0];
            if (!file) {
                window.alert('فایل را انتخاب کنید.');
                return;
            }
            var fd = new FormData();
            fd.append('action', 'sc_program_upload_file');
            fd.append('nonce', cfg.uploadNonce || '');
            fd.append('file', file);
            $btn.prop('disabled', true).text('در حال آپلود…');
            $.ajax({
                url: cfg.ajaxUrl || cfg.ajaxurl || window.ajaxurl,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false
            }).done(function (res) {
                if (res && res.success && res.data) {
                    $('#sc-lib-file-path').val(res.data.file_path || '');
                    $('#sc-lib-file-name').val(res.data.file_name || '');
                    $('#sc-lib-file-status').text(res.data.file_name || 'آپلود شد');
                    $form.find('[name="media_type"]').val('file');
                    syncLibMedia($form);
                } else {
                    window.alert((res && res.data && res.data.message) || 'آپلود ناموفق');
                }
            }).fail(function () {
                window.alert('خطای آپلود');
            }).always(function () {
                $btn.prop('disabled', false).text('آپلود فایل');
            });
        });
    }

    /* -------- Template days board -------- */
    function TemplateDaysBoard($root) {
        this.$root = $root;
        this.templateId = parseInt($('#sc-prog-days-board').data('template-id'), 10) || 0;
        this.days = [];
        this.pendingLib = null;
        this._loadBoot();
        this._bind();
        this.render();
    }

    TemplateDaysBoard.prototype._loadBoot = function () {
        var raw = $('#sc-prog-days-boot').text() || '[]';
        var parsed = [];
        try {
            parsed = JSON.parse(raw) || [];
        } catch (e) {
            parsed = [];
        }
        if (!Array.isArray(parsed)) {
            parsed = [];
        }
        this.days = parsed.map(function (d, i) {
            return {
                _uid: 'd' + i + '_' + (d.day_id || Date.now()),
                day_id: d.day_id || 0,
                day_offset: typeof d.day_offset === 'number' ? d.day_offset : i,
                title: d.title || ('روز ' + (i + 1)),
                items: (Array.isArray(d.items) ? d.items : []).map(function (it, j) {
                    return {
                        _uid: 'i' + j + '_' + (it.id || Math.random().toString(36).slice(2, 8)),
                        id: it.id || 0,
                        library_id: parseInt(it.library_id || it.library_item_id, 10) || 0,
                        title: it.title || 'تمرین'
                    };
                })
            };
        });
    };

    TemplateDaysBoard.prototype._bind = function () {
        var self = this;

        $(document).on('click.scProgDays', '#sc-prog-add-day-btn', function (e) {
            e.preventDefault();
            self.addDay();
        });
        $(document).on('click.scProgDays', '#sc-prog-save-days-btn', function (e) {
            e.preventDefault();
            self.save();
        });

        this.$root.on('click', '.sc-prog-day-remove', function (e) {
            e.preventDefault();
            self.removeDay($(this).closest('.sc-prog-day-col').data('uid'));
        });
        this.$root.on('input', '.sc-prog-day-title-input', function () {
            var day = self.findDay($(this).closest('.sc-prog-day-col').data('uid'));
            if (day) {
                day.title = $(this).val();
            }
        });
        this.$root.on('click', '.sc-prog-day-item__remove', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $item = $(this).closest('.sc-prog-day-item');
            self.removeItem($item.closest('.sc-prog-day-col').data('uid'), $item.data('uid'));
        });

        this.$root.on('click', '.sc-prog-lib-item', function (e) {
            if ($(this).data('skipClick') || $(this).hasClass('is-dragging')) {
                return;
            }
            e.preventDefault();
            self.pendingLib = {
                library_id: parseInt($(this).data('library-id'), 10) || 0,
                title: String($(this).data('title') || $(this).find('.sc-prog-lib-item__title').text() || 'تمرین')
            };
            if (!self.pendingLib.library_id) {
                return;
            }
            $('#sc-prog-day-picker-label').text('تمرین «' + self.pendingLib.title + '» به کدام روز اضافه شود؟');
            self.openDayPicker();
        });

        $(document).on('click.scProgDays', '#sc-prog-day-picker [data-close-modal], #sc-prog-day-picker .sc-prog-modal__backdrop', function () {
            self.closeDayPicker();
        });
        $(document).on('click.scProgDays', '#sc-prog-day-picker-list button[data-day-uid]', function () {
            if (self.pendingLib) {
                self.addItemToDay($(this).data('day-uid'), self.pendingLib);
            }
            self.closeDayPicker();
        });

        this.$root.on('input', '#sc-program-library-search', function () {
            var q = String($(this).val() || '').toLowerCase();
            self.$root.find('.sc-prog-lib-item').each(function () {
                var t = String($(this).data('title') || $(this).text() || '').toLowerCase();
                $(this).toggle(!q || t.indexOf(q) !== -1);
            });
        });
    };

    TemplateDaysBoard.prototype.findDay = function (uid) {
        for (var i = 0; i < this.days.length; i++) {
            if (String(this.days[i]._uid) === String(uid)) {
                return this.days[i];
            }
        }
        return null;
    };

    TemplateDaysBoard.prototype.addDay = function () {
        this.days.push({
            _uid: 'd_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
            day_id: 0,
            day_offset: this.days.length,
            title: 'روز ' + (this.days.length + 1),
            items: []
        });
        this.render();
    };

    TemplateDaysBoard.prototype.removeDay = function (uid) {
        if (!window.confirm('این روز حذف شود؟')) {
            return;
        }
        this.days = this.days.filter(function (d) {
            return String(d._uid) !== String(uid);
        });
        this.render();
    };

    TemplateDaysBoard.prototype.removeItem = function (dayUid, itemUid) {
        var day = this.findDay(dayUid);
        if (!day) {
            return;
        }
        day.items = day.items.filter(function (it) {
            return String(it._uid) !== String(itemUid);
        });
        this.render();
    };

    TemplateDaysBoard.prototype.addItemToDay = function (dayUid, lib) {
        var day = this.findDay(dayUid);
        if (!day || !lib || !lib.library_id) {
            return;
        }
        day.items.push({
            _uid: 'i_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
            id: 0,
            library_id: lib.library_id,
            title: lib.title || 'تمرین'
        });
        this.render();
        toast('تمرین به روز اضافه شد — در انتها ذخیره کنید');
    };

    TemplateDaysBoard.prototype.openDayPicker = function () {
        var $list = $('#sc-prog-day-picker-list').empty();
        if (!this.days.length) {
            $list.append('<p class="sc-prog-hint">ابتدا یک روز اضافه کنید.</p>');
        } else {
            this.days.forEach(function (d, i) {
                $list.append(
                    $('<button type="button"/>')
                        .attr('data-day-uid', d._uid)
                        .text((d.title || ('روز ' + (i + 1))) + ' · ' + (d.items.length || 0) + ' تمرین')
                );
            });
        }
        $('#sc-prog-day-picker').prop('hidden', false);
    };

    TemplateDaysBoard.prototype.closeDayPicker = function () {
        this.pendingLib = null;
        $('#sc-prog-day-picker').prop('hidden', true);
    };

    TemplateDaysBoard.prototype.render = function () {
        var $board = $('#sc-prog-days-board').empty();
        var self = this;
        if (!this.days.length) {
            $board.append(
                '<div class="sc-prog-empty-hero sc-prog-empty-hero--soft">' +
                '<p>هنوز روزی نیست. «روز جدید» را بزنید، سپس تمرین‌ها را بکشید یا کلیک کنید.</p></div>'
            );
            return;
        }
        this.days.forEach(function (day, idx) {
            var $col = $('<div class="sc-prog-day-col"/>').attr('data-uid', day._uid);
            var $head = $('<div class="sc-prog-day-col__head"/>');
            $head.append($('<span class="sc-prog-day-col__badge"/>').text(idx + 1));
            $head.append(
                $('<input type="text" class="sc-prog-day-title-input"/>')
                    .val(day.title || ('روز ' + (idx + 1)))
                    .attr('placeholder', 'عنوان روز')
            );
            $head.append(
                $('<button type="button" class="sc-prog-day-remove sc_button sc_button--danger" title="حذف روز"/>').html('&times;')
            );
            var $ul = $('<ul class="sc-prog-day-items"/>').attr('data-day-uid', day._uid);
            if (!day.items.length) {
                $ul.append('<li class="sc-prog-day-empty">رها کردن تمرین اینجا</li>');
            } else {
                day.items.forEach(function (it) {
                    var $li = $('<li class="sc-prog-day-item"/>')
                        .attr('data-uid', it._uid)
                        .attr('data-library-id', it.library_id || 0);
                    $li.append($('<span class="sc-prog-day-item__grip" aria-hidden="true"/>').text('⋮⋮'));
                    $li.append($('<span class="sc-prog-day-item__title"/>').text(it.title || 'تمرین'));
                    $li.append($('<button type="button" class="sc-prog-day-item__remove" title="حذف"/>').html('&times;'));
                    $ul.append($li);
                });
            }
            $col.append($head).append($ul);
            $board.append($col);
        });
        this._initDnD();
    };

    TemplateDaysBoard.prototype._initDnD = function () {
        var self = this;

        // HTML5: library → day
        this.$root.find('.sc-prog-lib-item').attr('draggable', true).off('.progDnD')
            .on('dragstart.progDnD', function (e) {
                var payload = {
                    library_id: parseInt($(this).data('library-id'), 10) || 0,
                    title: String($(this).data('title') || $(this).find('.sc-prog-lib-item__title').text() || 'تمرین')
                };
                e.originalEvent.dataTransfer.setData('application/sc-lib', JSON.stringify(payload));
                e.originalEvent.dataTransfer.effectAllowed = 'copy';
                $(this).addClass('is-dragging');
                self._dragMoved = false;
            })
            .on('drag.progDnD', function () {
                self._dragMoved = true;
            })
            .on('dragend.progDnD', function () {
                var $el = $(this);
                $el.removeClass('is-dragging');
                self.$root.find('.sc-prog-day-col').removeClass('is-dragover');
                if (self._dragMoved) {
                    $el.data('skipClick', true);
                    setTimeout(function () { $el.removeData('skipClick'); }, 250);
                }
            });

        this.$root.find('.sc-prog-day-col').off('.progDnD')
            .on('dragover.progDnD', function (e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'copy';
                $(this).addClass('is-dragover');
            })
            .on('dragleave.progDnD', function (e) {
                if (!$.contains(this, e.relatedTarget)) {
                    $(this).removeClass('is-dragover');
                }
            })
            .on('drop.progDnD', function (e) {
                e.preventDefault();
                $(this).removeClass('is-dragover');
                var raw = e.originalEvent.dataTransfer.getData('application/sc-lib');
                if (!raw) {
                    return;
                }
                try {
                    self.addItemToDay($(this).data('uid'), JSON.parse(raw));
                } catch (err) { /* ignore */ }
            });

        // Sortable between days
        var $cols = this.$root.find('.sc-prog-day-items');
        $cols.each(function () {
            if ($(this).hasClass('ui-sortable')) {
                $(this).sortable('destroy');
            }
        });
        if ($.fn.sortable) {
            $cols.sortable({
                connectWith: '.sc-prog-day-items',
                items: '> .sc-prog-day-item',
                placeholder: 'sc-prog-dnd-placeholder',
                handle: '.sc-prog-day-item__grip, .sc-prog-day-item__title',
                tolerance: 'pointer',
                receive: function () { self._syncFromDom(); },
                update: function () { self._syncFromDom(); },
                over: function () { $(this).closest('.sc-prog-day-col').addClass('is-dragover'); },
                out: function () { $(this).closest('.sc-prog-day-col').removeClass('is-dragover'); },
                stop: function () {
                    self.$root.find('.sc-prog-day-col').removeClass('is-dragover');
                    self._syncFromDom();
                }
            });
        }
    };

    TemplateDaysBoard.prototype._syncFromDom = function () {
        var self = this;
        var next = [];
        this.$root.find('.sc-prog-day-col').each(function () {
            var uid = $(this).data('uid');
            var day = self.findDay(uid);
            if (!day) {
                return;
            }
            var items = [];
            $(this).find('.sc-prog-day-item').each(function () {
                items.push({
                    _uid: $(this).data('uid') || ('i_' + Math.random().toString(36).slice(2, 8)),
                    library_id: parseInt($(this).data('library-id'), 10) || 0,
                    title: $(this).find('.sc-prog-day-item__title').text() || 'تمرین'
                });
            });
            day.title = $(this).find('.sc-prog-day-title-input').val() || day.title;
            day.items = items;
            next.push(day);
        });
        this.days = next;
        this.$root.find('.sc-prog-day-empty').remove();
        this.$root.find('.sc-prog-day-items').each(function () {
            if (!$(this).children('.sc-prog-day-item').length) {
                $(this).append('<li class="sc-prog-day-empty">رها کردن تمرین اینجا</li>');
            }
        });
    };

    TemplateDaysBoard.prototype.payload = function () {
        return this.days.map(function (d, i) {
            return {
                day_offset: i,
                title: d.title || ('روز ' + (i + 1)),
                library_ids: (d.items || []).map(function (it) {
                    return parseInt(it.library_id, 10) || 0;
                }).filter(function (id) {
                    return id > 0;
                })
            };
        });
    };

    TemplateDaysBoard.prototype.save = function () {
        var self = this;
        if (!this.templateId) {
            toast('ابتدا مشخصات قالب را ذخیره کنید.', true);
            return;
        }
        this._syncFromDom();
        var $btn = $('#sc-prog-save-days-btn').prop('disabled', true);
        toast('در حال ذخیره…');
        ajax('sc_program_template_save_days', {
            template_id: this.templateId,
            days_json: JSON.stringify(this.payload())
        }).done(function (res) {
            if (res && res.success) {
                toast((res.data && res.data.message) || 'ذخیره شد');
                if (res.data && Array.isArray(res.data.days)) {
                    $('#sc-prog-days-boot').text(JSON.stringify(res.data.days));
                    self._loadBoot();
                    self.render();
                }
            } else {
                toast((res && res.data && res.data.message) || 'خطا در ذخیره', true);
            }
        }).fail(function () {
            toast('خطای ارتباط با سرور', true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    };

    /* -------- Member picker (programs list) -------- */
    function bindMemberPicker() {
        var $wrap = $('#sc-prog-member-picker');
        if (!$wrap.length) {
            return;
        }
        var $toggle = $('#sc-prog-member-toggle');
        var $menu = $('#sc-prog-member-menu');
        var $search = $('#sc-prog-member-search');
        var $hidden = $('#sc-prog-filter-member');
        var $form = $wrap.closest('form');

        $toggle.on('click', function (e) {
            e.preventDefault();
            var open = $menu.prop('hidden');
            $menu.prop('hidden', !open);
            if (open) {
                $search.val('').trigger('input').trigger('focus');
            }
        });
        $(document).on('click', function (e) {
            if (!$wrap.is(e.target) && !$wrap.has(e.target).length) {
                $menu.prop('hidden', true);
            }
        });
        $search.on('input', function () {
            var q = String($(this).val() || '').toLowerCase();
            $menu.find('.sc-prog-member-option').each(function () {
                var t = String($(this).attr('data-search') || $(this).text() || '').toLowerCase();
                $(this).toggle(!q || t.indexOf(q) !== -1);
            });
        });
        $menu.on('click', '.sc-prog-member-option', function () {
            var id = $(this).data('id');
            var label = $(this).data('label') || $(this).text();
            $hidden.val(id);
            $toggle.text(label);
            $menu.find('.sc-prog-member-option').removeClass('is-selected');
            $(this).addClass('is-selected');
            $menu.prop('hidden', true);
            if ($form.length) {
                $form.trigger('submit');
            }
        });
    }

    /* -------- Member program: AJAX tick + progress bar -------- */
    function updateProgressUI(progress) {
        progress = progress || {};
        var pct = parseInt(progress.percent, 10) || 0;
        var done = parseInt(progress.done, 10) || 0;
        var total = parseInt(progress.total, 10) || 0;
        $('#sc-prog-progress-percent').text(pct + '٪');
        $('#sc-prog-progress-bar').css('width', pct + '%');
        $('#sc-prog-progress-label').text(done + ' از ' + total + ' آیتم تا امروز');
        $('#sc-prog-progress-hero').toggleClass('is-updated', true);
        setTimeout(function () {
            $('#sc-prog-progress-hero').removeClass('is-updated');
        }, 600);
    }

    function toggleStatus(msg, isError) {
        var $s = $('#sc-prog-toggle-status');
        if (!$s.length) {
            return;
        }
        $s.prop('hidden', false).text(msg).toggleClass('is-error', !!isError);
        clearTimeout($s.data('t'));
        $s.data('t', setTimeout(function () {
            $s.prop('hidden', true).text('');
        }, 2200));
    }

    function bindMemberToggleAjax() {
        var $wrap = $('.sc-prog-edit-page[data-sc-program-target="member"]');
        if (!$wrap.length) {
            return;
        }
        $wrap.on('change', '.sc-prog-tick-input', function () {
            var $input = $(this);
            var $item = $input.closest('.sc-prog-day-item');
            var itemId = parseInt($input.data('item-id'), 10) || 0;
            var done = $input.is(':checked') ? 1 : 0;
            if (!itemId) {
                return;
            }
            $input.prop('disabled', true);
            $item.toggleClass('is-done', !!done);
            $.post(cfg.ajaxUrl || cfg.ajaxurl || window.ajaxurl, {
                action: 'sc_program_toggle_completion',
                nonce: cfg.toggleNonce || '',
                item_id: itemId,
                done: done
            }).done(function (res) {
                if (res && res.success) {
                    var isDone = !!(res.data && res.data.done);
                    $input.prop('checked', isDone);
                    $item.toggleClass('is-done', isDone);
                    if (res.data && res.data.progress) {
                        updateProgressUI(res.data.progress);
                    }
                    toggleStatus(isDone ? 'تیک ثبت شد' : 'تیک برداشته شد');
                } else {
                    $input.prop('checked', !done);
                    $item.toggleClass('is-done', !done);
                    toggleStatus((res && res.data && res.data.message) || 'خطا در ثبت تیک', true);
                }
            }).fail(function () {
                $input.prop('checked', !done);
                $item.toggleClass('is-done', !done);
                toggleStatus('خطای ارتباط', true);
            }).always(function () {
                $input.prop('disabled', false);
            });
        });
    }

    /* -------- Member program edit DnD (AJAX add / reorder) -------- */
    function bindMemberProgramDnD() {
        var $wrap = $('.sc-programs-wrap[data-sc-program-target="member"]');
        if (!$wrap.length) {
            return;
        }
        var target = 'member';

        $wrap.find('.sc-dnd-library-item, .sc-prog-lib-item').attr('draggable', true)
            .on('dragstart', function (e) {
                e.originalEvent.dataTransfer.setData('text/plain', String($(this).data('library-id') || ''));
                $(this).addClass('is-dragging');
            })
            .on('dragend', function () {
                $(this).removeClass('is-dragging');
            });

        $wrap.find('.sc-program-day-drop, .sc-prog-day-col').on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('is-dragover');
        }).on('dragleave', function () {
            $(this).removeClass('is-dragover');
        }).on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('is-dragover');
            var dayId = parseInt($(this).data('day-id'), 10) || 0;
            var libId = parseInt(e.originalEvent.dataTransfer.getData('text/plain'), 10) || 0;
            if (!dayId || !libId) {
                return;
            }
            ajax('sc_program_add_library_to_day', {
                target: target,
                day_id: dayId,
                library_id: libId
            }).done(function (res) {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    window.alert((res && res.data && res.data.message) || 'افزودن ناموفق');
                }
            });
        });

        if ($.fn.sortable) {
            $wrap.find('.sc-dnd-day-items').sortable({
                connectWith: '.sc-dnd-day-items',
                items: '> li[data-item-id]',
                placeholder: 'sc-prog-dnd-placeholder',
                update: function () {
                    var dayId = parseInt($(this).data('day-id'), 10) || 0;
                    var ids = [];
                    $(this).children('li[data-item-id]').each(function () {
                        ids.push(parseInt($(this).data('item-id'), 10) || 0);
                    });
                    if (!dayId) {
                        return;
                    }
                    ajax('sc_program_reorder_day_items', {
                        target: target,
                        day_id: dayId,
                        item_ids: ids
                    });
                }
            });
        }

        $wrap.on('input', '#sc-program-library-search', function () {
            var q = String($(this).val() || '').toLowerCase();
            $wrap.find('.sc-dnd-library-item, .sc-prog-lib-item').each(function () {
                var t = String($(this).text() || '').toLowerCase();
                $(this).toggle(!q || t.indexOf(q) !== -1);
            });
        });
    }

    function bindReportChart() {
        var $canvas = $('#sc-program-trend-chart');
        if (!$canvas.length || typeof Chart === 'undefined') {
            return;
        }
        var data = window.scProgramReportChart || {};
        if (!data.labels) {
            return;
        }
        new Chart($canvas[0].getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'تکمیل',
                    data: data.values || [],
                    backgroundColor: 'rgba(115, 103, 240, 0.75)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    $(function () {
        var $scheduleRoots = $('#sc-program-template-form, .sc-prog-schedule-root, .sc-programs-wrap');
        $scheduleRoots.each(function () {
            var $r = $(this);
            if ($r.find('[name="schedule_mode"]').length) {
                syncScheduleMode($r);
                $r.on('change', '[name="schedule_mode"]', function () {
                    syncScheduleMode($r);
                });
            }
        });

        if ($('#sc-prog-days-board').length && $('#sc-prog-days-boot').length) {
            new TemplateDaysBoard($('#sc-prog-days-workspace').length ? $('#sc-prog-days-workspace') : $('.sc-programs-wrap'));
        }

        bindLibraryForm();
        bindMemberPicker();
        bindMemberToggleAjax();
        bindMemberProgramDnD();
        bindReportChart();
    });
})(jQuery);
