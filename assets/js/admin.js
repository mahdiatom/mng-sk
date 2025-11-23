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

    // ---------- نمایش پاپ آپ اطلاعات بازیکن ----------
    $(document).on('click', '.view-player', function(e){
        e.preventDefault();

        console.log("ok");

        let playerId = $(this).data('id');

        $.ajax({
            url: ajaxurl,
            type: 'post',
            data: {
                action: 'get_player_details',
                id: playerId
            },
            success: function(res){
                if(res.success){

                    let p = res.data;
                    console.log(p);

                    $(".sk-modal-content").html(`
                        <p><strong>شناسه:</strong> ${p.id}</p>
                        <p><strong>نام:</strong> ${p.first_name}</p>
                        <p><strong>نام خانوادگی:</strong> ${p.last_name}</p>
                        <p><strong>نام پدر:</strong> ${p.father_name}</p>
                        <p><strong>کد ملی:</strong> ${p.national_id}</p>
                        <p><strong>موبایل بازیکن:</strong> ${p.player_phone}</p>
                        <p><strong>موبایل پدر:</strong> ${p.father_phone}</p>
                        <p><strong>موبایل مادر:</strong> ${p.mother_phone}</p>
                        <p><strong>تلفن ثابت:</strong> ${p.landline_phone}</p>
                        <p><strong>تاریخ تولد (شمسی):</strong> ${p.birth_date_shamsi}</p>
                        <p><strong>تاریخ تولد (میلادی):</strong> ${p.birth_date_gregorian}</p>
                        <p><strong>وضعیت پزشکی:</strong> ${p.medical_condition}</p>
                        <p><strong>سوابق ورزشی:</strong> ${p.sports_history}</p>
                        <p><strong>تأیید سلامت:</strong> ${p.health_verified}</p>
                        <p><strong>تأیید اطلاعات:</strong> ${p.info_verified}</p>
                        <p><strong>فعال:</strong> ${p.is_active}</p>
                        <p><strong>اطلاعات اضافی:</strong> ${p.additional_info}</p>
                        <p><strong>تاریخ ایجاد:</strong> ${p.created_at}</p>
                        <p><strong>تاریخ بروزرسانی:</strong> ${p.updated_at}</p>
                      
                        <p class="p_img"><strong>عکس شخصی:</strong> </p><img class="photo" src="${p.personal_photo}">
                        <p class="p_img"><strong>عکس کارت ملی:</strong> </p><img class="photo" src="${p.id_card_photo}">
                        <p class="p_img"><strong>عکس بیمه ورزشی:</strong></p><img class="photo" src="${p.sport_insurance_photo}">
                    `);

                    // باز کردن مدال
                    $('#myModal').fadeIn();
                }
            }
        });
    });

    // ---------- بستن مدال ----------
    $('.close').on('click', function(){
        $('#myModal').fadeOut();
    });

    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $('#myModal').fadeOut();
        }
    });

    // باز کردن آکاردئون دوره‌ها در صورت وجود دوره
    var hasCourses = $('#sc-courses-content input[type="checkbox"]').length > 0;
    if (hasCourses) {
        $('#sc-courses-content').show();
        $('#courses-accordion-icon').text('▲');
    }

});

// تابع toggle برای آکاردئون دوره‌ها
function toggleCoursesAccordion() {
    var content = document.getElementById('sc-courses-content');
    var icon = document.getElementById('courses-accordion-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▲';
    } else {
        content.style.display = 'none';
        icon.textContent = '▼';
    }
}
