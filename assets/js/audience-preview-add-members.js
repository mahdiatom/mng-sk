(function ($) {
    'use strict';

    var MODE_CONFIG = {
        cert: {
            checkboxClass: 'sc-cert-preview-member-check',
            selectAllId: 'sc-cert-preview-select-all',
            metaLabel: 'کاربران',
            columns: ['name', 'national_id', 'type', 'team', 'level', 'status']
        },
        users: {
            checkboxClass: 'sc-users-preview-member-check',
            selectAllId: 'sc-users-preview-select-all',
            metaLabel: 'کاربران',
            columns: ['name', 'national_id', 'type', 'team', 'level', 'status']
        },
        bulk: {
            checkboxClass: 'sc-bulk-preview-member-check',
            selectAllId: 'sc-bulk-preview-select-all',
            metaLabel: 'کاربران',
            columns: ['name', 'national_id', 'type', 'team', 'level', 'status']
        },
        invoice: {
            checkboxClass: 'sc-invoice-preview-member-check',
            selectAllId: 'sc-invoice-preview-select-all',
            metaLabel: 'کاربران',
            columns: ['name', 'national_id', 'type', 'team', 'level', 'status']
        },
        private: {
            checkboxClass: 'sc-private-notes-preview-member-check',
            selectAllId: 'sc-private-notes-preview-select-all',
            metaLabel: 'کاربران',
            columns: ['name', 'national_id', 'type', 'team', 'level', 'status']
        },
        notif: {
            checkboxClass: 'sc-notif-preview-member-check',
            selectAllId: 'sc-notif-preview-select-all',
            metaLabel: 'مخاطبین',
            columns: ['name', 'national_id', 'type'],
            recipientMode: true
        },
        bale: {
            checkboxClass: 'sc-bale-preview-member-check',
            selectAllId: 'sc-bale-preview-select-all',
            metaLabel: 'مخاطبین',
            columns: ['name', 'national_id', 'type'],
            recipientMode: true
        }
    };

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getMemberIdFromRow($row, modeCfg) {
        if (modeCfg.recipientMode) {
            var rid = String($row.find('.' + modeCfg.checkboxClass).attr('data-recipient-id') || '');
            var match = rid.match(/^member_(\d+)$/);
            return match ? parseInt(match[1], 10) : 0;
        }
        return parseInt($row.find('.' + modeCfg.checkboxClass).attr('data-member-id'), 10) || 0;
    }

    function memberExistsInPreview($result, memberId, modeCfg) {
        var exists = false;
        $result.find('.' + modeCfg.checkboxClass).each(function () {
            if (modeCfg.recipientMode) {
                if (String($(this).attr('data-recipient-id') || '') === 'member_' + memberId) {
                    exists = true;
                    return false;
                }
            } else if (parseInt($(this).attr('data-member-id'), 10) === memberId) {
                exists = true;
                return false;
            }
        });
        return exists;
    }

    function ensurePreviewTable($result, modeCfg) {
        var $table = $result.find('table.sc-bulk-preview-table');
        if ($table.length) {
            return $table;
        }

        $result.find('p.description').remove();
        $result.prepend('<div class="sc-bulk-preview-meta">تعداد ' + modeCfg.metaLabel + ' فیلتر شده: <strong>0</strong></div>');

        var headers = ['<th style="width:64px;"><label><input type="checkbox" id="' + modeCfg.selectAllId + '" checked> انتخاب</label></th>'];
        if (modeCfg.columns.indexOf('name') !== -1) {
            headers.push('<th>نام</th>');
        }
        if (modeCfg.columns.indexOf('national_id') !== -1) {
            headers.push('<th>کد ملی</th>');
        }
        if (modeCfg.columns.indexOf('type') !== -1) {
            headers.push('<th>نوع</th>');
        }
        if (modeCfg.columns.indexOf('team') !== -1) {
            headers.push('<th>تیم</th>');
        }
        if (modeCfg.columns.indexOf('level') !== -1) {
            headers.push('<th>سطح</th>');
        }
        if (modeCfg.columns.indexOf('status') !== -1) {
            headers.push('<th>وضعیت</th>');
        }

        $result.append(
            '<table class="wp-list-table widefat striped sc-bulk-preview-table">' +
            '<thead><tr>' + headers.join('') + '</tr></thead>' +
            '<tbody></tbody></table>'
        );
        return $result.find('table.sc-bulk-preview-table');
    }

    function buildRowHtml(option, modeCfg) {
        var id = parseInt($(option).attr('data-id'), 10);
        var name = $(option).attr('data-name') || '';
        var nationalId = $(option).attr('data-national-id') || '-';
        var typeLabel = $(option).attr('data-type') || 'بازیکن عادی';
        var team = $(option).attr('data-team') || '-';
        var level = $(option).attr('data-level') || '-';
        var status = $(option).attr('data-status') || 'فعال';
        var label = $(option).attr('data-label') || name;

        var checkbox;
        if (modeCfg.recipientMode) {
            checkbox = '<input type="checkbox" class="' + modeCfg.checkboxClass + '" data-recipient-id="member_' + id + '" checked>';
        } else {
            checkbox = '<input type="checkbox" class="' + modeCfg.checkboxClass + '" data-member-id="' + id + '" data-member-label="' + escapeHtml(label) + '" checked>';
        }

        var cells = ['<td>' + checkbox + '</td>'];
        if (modeCfg.columns.indexOf('name') !== -1) {
            cells.push('<td>' + escapeHtml(name) + '</td>');
        }
        if (modeCfg.columns.indexOf('national_id') !== -1) {
            cells.push('<td>' + escapeHtml(nationalId) + '</td>');
        }
        if (modeCfg.columns.indexOf('type') !== -1) {
            cells.push('<td>' + escapeHtml(typeLabel) + '</td>');
        }
        if (modeCfg.columns.indexOf('team') !== -1) {
            cells.push('<td>' + escapeHtml(team) + '</td>');
        }
        if (modeCfg.columns.indexOf('level') !== -1) {
            cells.push('<td>' + escapeHtml(level) + '</td>');
        }
        if (modeCfg.columns.indexOf('status') !== -1) {
            cells.push('<td>' + escapeHtml(status) + '</td>');
        }

        return '<tr data-sc-preview-added="1">' + cells.join('') + '</tr>';
    }

    function updatePreviewCount($result, modeCfg) {
        var count = $result.find('.' + modeCfg.checkboxClass).length;
        var $meta = $result.find('.sc-bulk-preview-meta strong').first();
        if ($meta.length) {
            $meta.text(String(count));
        }
    }

  function syncHiddenInputs($wrap, includedIds) {
        var inputsId = $wrap.attr('data-hidden-inputs-id') || 'sc-audience-preview-add-inputs';
        var $inputs = $('#' + inputsId);
        if (!$inputs.length) {
            return;
        }
        $inputs.empty();
        includedIds.forEach(function (id) {
            $inputs.append('<input type="hidden" name="included_member_ids[]" value="' + id + '">');
        });
    }

    function initWrap($wrap) {
        if (!$wrap.length || $wrap.data('scPreviewAddInit')) {
            return;
        }
        $wrap.data('scPreviewAddInit', true);

        var mode = $wrap.attr('data-preview-add-mode') || 'member';
        var modeCfg = MODE_CONFIG[mode] || MODE_CONFIG.member;
        var $dropdown = $wrap.find('.sc-audience-preview-add-dropdown');
        var $toggle = $dropdown.find('.sc-users-dropdown-toggle');
        var $menu = $dropdown.find('.sc-users-dropdown-menu');
        var $search = $dropdown.find('.sc-audience-preview-add-search');
        var $options = $wrap.find('.sc-users-dropdown-options');
        var includedIds = [];

        function getResultContainer() {
            var $sibling = $wrap.siblings('.sc-bulk-preview-result').first();
            if ($sibling.length) {
                return $sibling;
            }
            return $wrap.closest('.inside, .sc-users-export-card, .postbox').find('.sc-bulk-preview-result').first();
        }

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $menu.slideToggle(150, function () {
                if (window.scAudienceDropdownStack) {
                    window.scAudienceDropdownStack.sync($dropdown);
                }
            });
            setTimeout(function () {
                if (window.scAudienceDropdownStack) {
                    window.scAudienceDropdownStack.sync($dropdown);
                }
            }, 220);
            setTimeout(function () {
                $search.trigger('focus');
            }, 200);
        });

        $search.on('input', function () {
            var term = ($(this).val() || '').toLowerCase().trim();
            $options.find('.sc-users-dropdown-option').each(function () {
                var text = ($(this).attr('data-search') || '').toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $options.on('click', '.sc-users-dropdown-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var memberId = parseInt($(this).attr('data-id'), 10);
            if (!memberId) {
                return;
            }

            var $result = getResultContainer();
            if (!$result.length) {
                return;
            }

            if (memberExistsInPreview($result, memberId, modeCfg)) {
                alert('این کاربر قبلاً در لیست پیش‌نمایش وجود دارد.');
                return;
            }

            var $table = ensurePreviewTable($result, modeCfg);
            $table.find('tbody').append(buildRowHtml(this, modeCfg));

            if (includedIds.indexOf(memberId) === -1) {
                includedIds.push(memberId);
            }
            syncHiddenInputs($wrap, includedIds);
            updatePreviewCount($result, modeCfg);

            $search.val('');
            $options.find('.sc-users-dropdown-option').show();
            $menu.slideUp(150);

            $(document).trigger('sc-audience-preview-member-added', [{
                memberId: memberId,
                mode: mode,
                wrap: $wrap[0],
                result: $result[0]
            }]);
        });

        $(document).on('click.scAudiencePreviewAdd', function (e) {
            if (!$(e.target).closest($dropdown).length) {
                $menu.slideUp(150, function () {
                    if (window.scAudienceDropdownStack) {
                        window.scAudienceDropdownStack.sync($dropdown);
                    }
                });
            }
        });

        $wrap.data('scPreviewAddReset', function () {
            includedIds = [];
            syncHiddenInputs($wrap, includedIds);
        });
    }

    window.scAudiencePreviewAdd = {
        show: function (resultSelector) {
            var $result = $(resultSelector);
            if (!$result.length) {
                return;
            }
            var $wrap = $result.siblings('.sc-audience-preview-add-wrap').first();
            if (!$wrap.length) {
                $wrap = $result.parent().find('.sc-audience-preview-add-wrap').first();
            }
            if (!$wrap.length) {
                $wrap = $result.next('.sc-audience-preview-add-wrap');
            }
            if ($wrap.length) {
                initWrap($wrap);
                $wrap.show();
            }
        },
        reset: function (resultSelector) {
            var $result = $(resultSelector);
            var $wrap = $result.siblings('.sc-audience-preview-add-wrap').first();
            if (!$wrap.length) {
                $wrap = $result.parent().find('.sc-audience-preview-add-wrap').first();
            }
            if ($wrap.length && typeof $wrap.data('scPreviewAddReset') === 'function') {
                $wrap.data('scPreviewAddReset')();
            }
        },
        initAll: function () {
            $('.sc-audience-preview-add-wrap').each(function () {
                initWrap($(this));
            });
        }
    };

    $(function () {
        window.scAudiencePreviewAdd.initAll();
    });
})(jQuery);
