jQuery(document).ready(function($) {

    // ---------- عکس پرسنلی ----------
    $('#btn_personal_photo').on('click', function(e){
        e.preventDefault();
        var frame_personal = wp.media({
            title: 'انتخاب تصویر پرسنلی',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'استفاده از تصویر' }
        });
        frame_personal.on('select', function(){
            var attachment = frame_personal.state().get('selection').first().toJSON();
            $('#personal_photo_txt').val(attachment.url);
        });
        frame_personal.open();
    });

    // ---------- عکس کارت ملی ----------
    $('#btn_id_card_photo').on('click', function(e){
        e.preventDefault();
        var frame_idcard = wp.media({
            title: 'انتخاب تصویر کارت ملی',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'استفاده از تصویر' }
        });
        frame_idcard.on('select', function(){
            var attachment = frame_idcard.state().get('selection').first().toJSON();
            $('#id_card_photo_txt').val(attachment.url);
        });
        frame_idcard.open();
    });

    // ---------- عکس بیمه ورزشی ----------
    $('#btn_sport_insurance_photo').on('click', function(e){
        e.preventDefault();
        var frame_insurance = wp.media({
            title: 'انتخاب تصویر بیمه ورزشی',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'استفاده از تصویر' }
        });
        frame_insurance.on('select', function(){
            var attachment = frame_insurance.state().get('selection').first().toJSON();
            $('#sport_insurance_photo_txt').val(attachment.url);
        });
        frame_insurance.open();
    });

});
