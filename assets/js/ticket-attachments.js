/**
 * تیکت پشتیبانی: آپلود پیوست در پس‌زمینه با نوار پیشرفت (کاربر، مدیر، مربی)
 * ظاهر حرفه‌ای مشابه بخش اطلاعات بازیکن
 */
(function($) {
    'use strict';

    var MAX_FILES = 5;
    var MAX_SIZE = 5 * 1024 * 1024; // 5MB
    var ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx';

    function initZone($zone) {
        var inputName = $zone.data('input-name') || 'reply_attachment_ids';
        var nonce = $zone.data('nonce') || '';
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
            formData.append('action', 'sc_upload_ticket_attachment');
            formData.append('sc_ticket_upload_nonce', nonce);
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
                    renderList();
                    $progressBar.css('width', '100%');
                    $progressText.text('آپلود شد');
                    $progressWrap.removeClass('sc-uploading').addClass('sc-upload-done');
                } else {
                    $progressText.text(res.data && res.data.message ? res.data.message : 'خطا در آپلود');
                    $progressWrap.removeClass('sc-uploading').addClass('sc-upload-error');
                }
            })
            .fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'خطا در ارتباط با سرور';
                $progressText.text(msg);
                $progressWrap.removeClass('sc-uploading').addClass('sc-upload-error');
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
            for (var i = 0; i < files.length && (uploadedIds.length + queue.length + added) < MAX_FILES; i++) {
                var file = files[i];
                if (file.size > MAX_SIZE) {
                    if (window.alert) alert('فایل «' + file.name + '» بیش از ۵ مگابایت است و نادیده گرفته شد.');
                    continue;
                }
                queue.push(file);
                added++;
            }
            if (added < files.length && uploadedIds.length + queue.length >= MAX_FILES && window.alert) {
                alert('حداکثر ' + MAX_FILES + ' فایل مجاز است.');
            }
            processQueue();
        }

        $area.on('click', function(e) {
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
                addFiles(this.files);
            }
            this.value = '';
        });

        $uploadedList.on('click', '.sc-ticket-uploaded-remove', function() {
            var id = $(this).data('id');
            uploadedIds = uploadedIds.filter(function(item) { return item.id !== id; });
            removeHiddenInput(id);
            renderList();
        });
    }

    $(function() {
        $('.sc-ticket-attachment-zone').each(function() {
            initZone($(this));
        });
    });

})(jQuery);
