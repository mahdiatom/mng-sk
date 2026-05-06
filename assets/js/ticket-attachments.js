/**
 * تیکت پشتیبانی: آپلود پیوست در پس‌زمینه با نوار پیشرفت (کاربر، مدیر، مربی)
 * ظاهر حرفه‌ای مشابه بخش اطلاعات بازیکن
 */
(function($) {
    'use strict';

    var MAX_FILES = 5;
    var MAX_SIZE = 5 * 1024 * 1024; // 5MB
    var ALLOWED_EXT = ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg', 'tiff', 'tif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
    var ACCEPT = '.' + ALLOWED_EXT.join(',.');

    function getExt(name) {
        var i = name.lastIndexOf('.');
        return i >= 0 ? name.substring(i + 1).toLowerCase() : '';
    }

    function isAllowedExt(ext) {
        return ALLOWED_EXT.indexOf(ext) !== -1;
    }

    function showUploadError($progressText, msg, $progressWrap) {
        $progressText.text(msg || 'خطا در آپلود');
        if ($progressWrap) $progressWrap.removeClass('sc-uploading').addClass('sc-upload-error');
    }

    function initZone($zone) {
        var inputName = $zone.data('input-name') || 'reply_attachment_ids';
        var nonce = $zone.data('nonce') || '';
        var action = $zone.data('action') || 'sc_upload_ticket_attachment';
        var nonceKey = $zone.data('nonce-key') || 'sc_ticket_upload_nonce';
        var ajaxUrl = (typeof scTicketAttach !== 'undefined' && scTicketAttach.ajaxurl) ? scTicketAttach.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        if (!ajaxUrl || !nonce) return;

        var $area = $zone.find('.sc-ticket-upload-area');
        var $fileInput = $zone.find('.sc-ticket-file-input-hidden');
        var $progressWrap = $zone.find('.sc-ticket-upload-progress-wrap');
        var $progressBar = $zone.find('.sc-ticket-upload-progress .sc-upload-bar');
        var $progressText = $zone.find('.sc-ticket-upload-progress .sc-upload-text');
        var $uploadedList = $zone.find('.sc-ticket-uploaded-list');
        var $hiddenContainer = $zone.find('.sc-ticket-attachment-ids-hidden');

        var uploadedIds = [];
        var queue = [];
        var uploading = false;

        function addHiddenInput(id) {
            $hiddenContainer.append($('<input type="hidden">').attr('name', inputName + '[]').val(id));
        }

        function removeHiddenInput(id) {
            $hiddenContainer.find('input[value="' + id + '"]').remove();
        }

        function renderList() {
            $uploadedList.empty();
            uploadedIds.forEach(function(item) {
                var $item = $('<div class="sc-ticket-uploaded-item">')
                    .append($('<span class="sc-ticket-uploaded-name">').text(item.name))
                    .append($('<button type="button" class="sc-ticket-uploaded-remove">').text('×').attr('data-id', item.id));
                $uploadedList.append($item);
            });
        }

        function uploadOne(file, done) {
            var formData = new FormData();
            formData.append('action', action);
            formData.append(nonceKey, nonce);
            formData.append('file', file);

            $progressWrap.show().addClass('sc-uploading');
            $progressBar.css('width', '0%');
            $progressText.text('در حال آپلود «' + (file.name.length > 25 ? file.name.substring(0, 22) + '…' : file.name) + '»...');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var pct = Math.round((e.loaded / e.total) * 100);
                            $progressBar.css('width', pct + '%');
                        }
                    }, false);
                    return xhr;
                }
            })
            .done(function(res) {
                if (res.success && res.data && res.data.id) {
                    uploadedIds.push({ id: res.data.id, name: res.data.name || file.name });
                    addHiddenInput(res.data.id);
                    $uploadedList.find('.uploading').first().remove();
                    renderList();
                    $progressBar.css('width', '100%');
                    $progressText.text('آپلود شد');
                    $progressWrap.removeClass('sc-uploading').addClass('sc-upload-done');
                } else {
                    var errMsg = (res.data && res.data.message) ? res.data.message : 'خطا در آپلود فایل.';
                    $uploadedList.find('.uploading').first().remove();
                    showUploadError($progressText, errMsg, $progressWrap);
                }
            })
            .fail(function(xhr) {
                var msg = 'خطا در ارتباط با سرور.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                } else if (xhr.status === 413) {
                    msg = 'حجم فایل بیش از حد مجاز است (حداکثر ۵ مگابایت).';
                } else if (xhr.status === 0) {
                    msg = 'اتصال برقرار نشد. اتصال اینترنت را بررسی کنید.';
                }
                $uploadedList.find('.uploading').first().remove();
                showUploadError($progressText, msg, $progressWrap);
            })
            .always(function() {
                uploading = false;
                if (typeof done === 'function') done();
                processQueue();
            });
        }

        function processQueue() {
            if (uploading || queue.length === 0) return;
            if (uploadedIds.length >= MAX_FILES) {
                queue = [];
                $progressWrap.hide();
                return;
            }
            var file = queue.shift();
            uploading = true;
            uploadOne(file, function() {
                if (queue.length > 0) setTimeout(processQueue, 300);
                else $progressWrap.delay(1500).fadeOut(200);
            });
        }

        function addFiles(files) {
            var added = 0;
            var errors = [];
            for (var i = 0; i < files.length && (uploadedIds.length + queue.length + added) < MAX_FILES; i++) {
                var file = files[i];
                var ext = getExt(file.name);
                if (!ext || !isAllowedExt(ext)) {
                    errors.push('فرمت «' + (ext || 'بدون پسوند') + '» مجاز نیست: ' + file.name);
                    continue;
                }
                if (file.size > MAX_SIZE) {
                    errors.push('حجم بیش از ۵ مگابایت: ' + file.name);
                    continue;
                }
                queue.push(file);
                added++;

                // نمایش فوری نام فایل
                $uploadedList.append(
                    $('<div class="sc-ticket-uploaded-item uploading">')
                        .text('در حال بارگذاری  ' + file.name)
                );
            }
            if (uploadedIds.length + queue.length + added >= MAX_FILES && files.length > added) {
                errors.push('حداکثر ' + MAX_FILES + ' فایل مجاز است.');
            }
            if (errors.length > 0 && window.alert) {
                alert(errors.join('\n'));
            }
            processQueue();
        }

        $area.on('click', function(e) {
            // Prevent double file dialog when native file input is clicked.
            if (e.target === $fileInput[0] || $(e.target).closest('.sc-ticket-file-input-hidden').length) {
                return;
            }
            if (!$(e.target).closest('.sc-ticket-uploaded-remove').length) {
                $fileInput[0].click();
            }
        });

        $area.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('sc-upload-dragover');
        });
        $area.on('dragleave drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('sc-upload-dragover');
            if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files && e.originalEvent.dataTransfer.files.length) {
                addFiles(e.originalEvent.dataTransfer.files);
            }
        });

        $fileInput.on('change', function() {
            if (this.files && this.files.length) {

                // 👇 نمایش فوری به کاربر
                $progressWrap.show().addClass('sc-uploading');
                $progressBar.css('width', '5%');
                $progressText.text('در حال آماده‌سازی فایل...');

                addFiles(this.files);
            }
            this.value = '';
        });

        $uploadedList.on('click', '.sc-ticket-uploaded-remove', function() {
            var id = $(this).data('id');
            uploadedIds = uploadedIds.filter(function(item) { return item.id !== id; });
            removeHiddenInput(id);
            $uploadedList.find('.uploading').remove();
            renderList();
        });
    }

    $(function() {
        $('.sc-ticket-attachment-zone').each(function() {
            initZone($(this));
        });
    });

})(jQuery);
