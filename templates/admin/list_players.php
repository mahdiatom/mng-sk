<?php
global $title ,$player_list_table;

            ?>
            <div class="wrap">
            <h1 class="wp-heading-inline">لیست بازیکن ها</h1>
            <a href="<?php echo admin_url('admin.php?page=sc-add-member'); ?>" class="page-title-action">افزودن بازیکن</a>
            </div>
            <?php
            echo '<div class="wrap">';
                echo '<form  Method="get" >';
                    echo '<input type="hidden" name="page" value="sc-members">';
                    $player_list_table->search_box('جستجو بازیکن' , 'search_player');
                    $player_list_table->views();
                    $player_list_table->display();
                echo '</form>';
            echo '</div>';




?>

<!-- The Modal -->
<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <p class="sk-modal-content"></p>
  </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('Player modal JS loaded');
    
    // ---------- نمایش پاپ آپ اطلاعات بازیکن ----------
    $(document).on('click', '.view-player', function(e){
        e.preventDefault();
        e.stopPropagation();
        console.log('View player clicked');
        
        let playerId = $(this).data('id');
        console.log('Player ID:', playerId);
        
        if (!playerId) {
            console.error('Player ID not found');
            alert('خطا: شناسه بازیکن پیدا نشد');
            return;
        }
        
        let $modal = $('#myModal');
        console.log('Modal found:', $modal.length);
        
        if (!$modal.length) {
            console.error('Modal not found');
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
        
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        console.log('AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'post',
            data: {
                action: 'get_player_details',
                id: playerId
            },
            success: function(res){
                console.log('AJAX Success Response:', res);
                if(res.success){
                    let p = res.data;
                    $(".sk-modal-content").html(
                        '<p><strong>شناسه:</strong> ' + p.id + '</p>' +
                        '<p><strong>نام:</strong> ' + (p.first_name || '-') + '</p>' +
                        '<p><strong>نام خانوادگی:</strong> ' + (p.last_name || '-') + '</p>' +
                        '<p><strong>نام پدر:</strong> ' + (p.father_name || '-') + '</p>' +
                        '<p><strong>کد ملی:</strong> ' + (p.national_id || '-') + '</p>' +
                        '<p><strong>موبایل بازیکن:</strong> ' + (p.player_phone || '-') + '</p>' +
                        '<p><strong>موبایل پدر:</strong> ' + (p.father_phone || '-') + '</p>' +
                        '<p><strong>موبایل مادر:</strong> ' + (p.mother_phone || '-') + '</p>' +
                        '<p><strong>تلفن ثابت:</strong> ' + (p.landline_phone || '-') + '</p>' +
                        '<p><strong>تاریخ تولد (شمسی):</strong> ' + (p.birth_date_shamsi || '-') + '</p>' +
                        '<p><strong>تاریخ تولد (میلادی):</strong> ' + (p.birth_date_gregorian || '-') + '</p>' +
                        '<p><strong>وضعیت پزشکی:</strong> ' + (p.medical_condition || '-') + '</p>' +
                        '<p><strong>سوابق ورزشی:</strong> ' + (p.sports_history || '-') + '</p>' +
                        '<p><strong>تأیید سلامت:</strong> ' + (p.health_verified ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>تأیید اطلاعات:</strong> ' + (p.info_verified ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>فعال:</strong> ' + (p.is_active ? 'بله' : 'خیر') + '</p>' +
                        '<p><strong>اطلاعات اضافی:</strong> ' + (p.additional_info || '-') + '</p>' +
                        '<p><strong>تاریخ ایجاد:</strong> ' + (p.created_at || '-') + '</p>' +
                        '<p><strong>تاریخ بروزرسانی:</strong> ' + (p.updated_at || '-') + '</p>' +
                        (p.personal_photo ? '<p class="p_img"><strong>عکس شخصی:</strong></p><img class="photo" src="' + p.personal_photo + '">' : '') +
                        (p.id_card_photo ? '<p class="p_img"><strong>عکس کارت ملی:</strong></p><img class="photo" src="' + p.id_card_photo + '">' : '') +
                        (p.sport_insurance_photo ? '<p class="p_img"><strong>عکس بیمه ورزشی:</strong></p><img class="photo" src="' + p.sport_insurance_photo + '">' : '')
                    );
                    $('#myModal').fadeIn();
                } else {
                    alert('خطا در دریافت اطلاعات بازیکن');
                }
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                alert('خطا در دریافت اطلاعات بازیکن. لطفاً دوباره تلاش کنید.');
            }
        });
    });

    // ---------- بستن مدال ----------
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $('#myModal').fadeOut();
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $('#myModal').fadeOut();
        }
    });
});
</script>

    <?php

