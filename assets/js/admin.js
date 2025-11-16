jQuery(document).ready(function ($) {

    let image_frame;

    $(document).on('click', '.sc-upload-btn', function (e) {
        e.preventDefault();

        let targetInput = $(this).data('target');

        // اگر قبلاً باز شده باشد
        if (image_frame) {
            image_frame.open();
            return;
        }

        image_frame = wp.media({
            title: 'انتخاب تصویر',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'استفاده از تصویر' }
        });

        image_frame.on('select', function () {
            let attachment = image_frame.state().get('selection').first().toJSON();
            $(targetInput).val(attachment.url);
        });

        image_frame.open();
    });

});
