<?php

if ( ! defined('ABSPATH') ) exit;
//این فایل پاپ اپ برای اطلاعات بازیکن است که در صفحه لیست اعضا در اکشن می
// جدول در procces_table_data (هوک load) آماده می‌شود؛ اینجا دوباره prepare نکنید (اکشن گروهی دوبار اجرا می‌شد)
global $title, $player_list_table;
if (!isset($player_list_table) || !($player_list_table instanceof Player_List_Table)) {
    $player_list_table = new Player_List_Table();
    $player_list_table->prepare_items();
}

// بارگذاری داده‌ها برای فیلتر searchable
global $wpdb;
        $members_table = $wpdb->prefix . 'sc_members';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $all_players = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
        $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");

        $team_options = $wpdb->get_col(
            "SELECT DISTINCT team_player FROM $members_table WHERE team_player IS NOT NULL AND TRIM(team_player) <> '' ORDER BY team_player ASC"
        );
        $level_options = $wpdb->get_col(
            "SELECT DISTINCT skill_level FROM $members_table WHERE skill_level IS NOT NULL AND TRIM(skill_level) <> '' ORDER BY skill_level ASC"
        );

        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_profile = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
        $filter_member_type = isset($_GET['filter_member_type']) ? sanitize_text_field($_GET['filter_member_type']) : 'all';
        $filter_team = isset($_GET['filter_team']) ? sanitize_text_field(wp_unslash($_GET['filter_team'])) : '';
        $filter_skill_level = isset($_GET['filter_skill_level']) ? sanitize_text_field(wp_unslash($_GET['filter_skill_level'])) : '';
        $filter_identity = isset($_GET['filter_identity']) ? sanitize_text_field($_GET['filter_identity']) : 'all';
        $filter_insurance = isset($_GET['filter_insurance']) ? sanitize_text_field($_GET['filter_insurance']) : 'all';
        $filter_player = isset($_GET['filter_player']) ? absint($_GET['filter_player']) : 0;

        $active_filters_count = 0;
        if ($filter_player > 0) {
            $active_filters_count++;
        }
        if ($filter_course > 0) {
            $active_filters_count++;
        }
        if ($filter_status !== 'all') {
            $active_filters_count++;
        }
        if ($filter_profile !== 'all') {
            $active_filters_count++;
        }
        if ($filter_member_type !== 'all') {
            $active_filters_count++;
        }
        if ($filter_team !== '') {
            $active_filters_count++;
        }
        if ($filter_skill_level !== '') {
            $active_filters_count++;
        }
        if ($filter_identity !== 'all') {
            $active_filters_count++;
        }
        if ($filter_insurance !== 'all') {
            $active_filters_count++;
        }
        $filters_open = $active_filters_count > 0;

        $export_url = admin_url('admin.php?page=sc-members&sc_export=excel&export_type=members');
        if ($filter_player > 0) {
            $export_url = add_query_arg('filter_player', $filter_player, $export_url);
        }
        if ($filter_course > 0) {
            $export_url = add_query_arg('filter_course', $filter_course, $export_url);
        }
        if ($filter_status !== 'all') {
            $export_url = add_query_arg('filter_status', $filter_status, $export_url);
        }
        if ($filter_profile !== 'all') {
            $export_url = add_query_arg('filter_profile', $filter_profile, $export_url);
        }
        if ($filter_member_type !== 'all') {
            $export_url = add_query_arg('filter_member_type', $filter_member_type, $export_url);
        }
        if ($filter_team !== '') {
            $export_url = add_query_arg('filter_team', $filter_team, $export_url);
        }
        if ($filter_skill_level !== '') {
            $export_url = add_query_arg('filter_skill_level', $filter_skill_level, $export_url);
        }
        if ($filter_identity !== 'all') {
            $export_url = add_query_arg('filter_identity', $filter_identity, $export_url);
        }
        if ($filter_insurance !== 'all') {
            $export_url = add_query_arg('filter_insurance', $filter_insurance, $export_url);
        }
        $export_url = wp_nonce_url($export_url, 'sc_export_excel');

        $selected_player_text = 'همه بازیکنان';
        if ($filter_player > 0) {
            foreach ($all_players as $p) {
                if ((int) $p->id === $filter_player) {
                    $selected_player_text = $p->first_name . ' ' . $p->last_name . ' - ' . $p->national_id;
                    break;
                }
            }
        }
        ?>

        <div class="wrap sc-members-list-wrap">
            <div class="sc-members-list-header">
                <div class="sc-members-list-header-text">
                    <h1 class="sc-members-list-title">لیست بازیکن‌ها</h1>
                    <p class="sc-members-list-desc">برای مشاهده اکشن‌ها روی نام کاربر بروید (حذف، مشاهده، ویرایش).</p>
                </div>
                <div class="sc-members-list-header-actions">
                    <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="page-title-action sc-members-list-add-btn">افزودن بازیکن</a>
                    <a href="<?php echo esc_url($export_url); ?>" class="sc-members-list-export-btn">خروجی Excel</a>
                </div>
            </div>

            <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
                <div class="sc-members-list-filters-toolbar">
                    <button type="button"
                            class="sc-members-list-filters-toggle"
                            id="sc-members-filters-toggle"
                            aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                            aria-controls="sc-members-filters-panel">
                        <span class="sc-members-list-filters-toggle-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                            <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                        </span>
                        <?php if ($active_filters_count > 0) : ?>
                            <span class="sc-members-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                        <?php endif; ?>
                        <span class="sc-members-list-filters-chevron" aria-hidden="true"></span>
                    </button>
                    <?php if ($active_filters_count > 0) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-members')); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
                    <?php endif; ?>
                </div>

                <form method="get" action="" class="form_fillter_list_player sc-members-list-filters-panel" id="sc-members-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
                    <input type="hidden" name="page" value="sc-members">

                    <div class="sc-filter-grid">

                        <div class="sc-filter-field">
                            <label class="sc-filter-label">جستجوی بازیکن</label>
                            <div class="sc-searchable-dropdown">
                                <input type="hidden" name="filter_player" id="filter_player" value="<?php echo esc_attr($filter_player); ?>">
                                <div class="sc-dropdown-toggle">
                                    <span class="sc-dropdown-placeholder" <?php if ($filter_player) echo 'style="display:none"'; ?>>همه بازیکنان</span>
                                    <span class="sc-dropdown-selected" <?php if (!$filter_player) echo 'style="display:none"'; ?>><?php echo esc_html($selected_player_text); ?></span>
                                    <span class="sc-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-dropdown-menu">
                                    <div class="sc-dropdown-search">
                                        <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                                    </div>
                                    <div class="sc-dropdown-options">
                                        <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه بازیکنان" onclick="scSelectMemberFilter(this,'0','همه بازیکنان')">همه بازیکنان</div>
                                        <?php
                                        $display_count = 0;
                                        $max_display = 15;
                                        foreach ($all_players as $player) :
                                            $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                            $display_count++;
                                        ?>
                                            <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                                 data-value="<?php echo esc_attr($player->id); ?>"
                                                 data-search="<?php echo esc_attr(strtolower($player->first_name . ' ' . $player->last_name . ' ' . $player->national_id)); ?>"
                                                 onclick="scSelectMemberFilter(this,'<?php echo esc_js($player->id); ?>','<?php echo esc_js($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>')">
                                                <?php echo esc_html($player->first_name . ' ' . $player->last_name . ' - ' . $player->national_id); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_course">دوره</label>
                            <select name="filter_course" id="filter_course" class="sc-filter-control">
                                <option value="0">همه دوره‌ها</option>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                        <?php echo esc_html($course->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_status">وضعیت</label>
                            <select name="filter_status" id="filter_status" class="sc-filter-control">
                                <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                                <option value="active" <?php selected($filter_status, 'active'); ?>>فعال</option>
                                <option value="inactive" <?php selected($filter_status, 'inactive'); ?>>غیرفعال</option>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_profile">تکمیل پروفایل</label>
                            <select name="filter_profile" id="filter_profile" class="sc-filter-control">
                                <option value="all" <?php selected($filter_profile, 'all'); ?>>همه</option>
                                <option value="completed" <?php selected($filter_profile, 'completed'); ?>>تکمیل شده</option>
                                <option value="incomplete" <?php selected($filter_profile, 'incomplete'); ?>>ناقص</option>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_member_type">نوع بازیکن</label>
                            <select name="filter_member_type" id="filter_member_type" class="sc-filter-control">
                                <option value="all" <?php selected($filter_member_type, 'all'); ?>>همه انواع</option>
                                <option value="normal" <?php selected($filter_member_type, 'normal'); ?>>عادی</option>
                                <option value="team" <?php selected($filter_member_type, 'team'); ?>>تیم</option>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_team">تیم</label>
                            <select name="filter_team" id="filter_team" class="sc-filter-control">
                                <option value="">همه تیم‌ها</option>
                                <?php foreach ($team_options as $team_name) : ?>
                                    <option value="<?php echo esc_attr($team_name); ?>" <?php selected($filter_team, $team_name); ?>>
                                        <?php echo esc_html($team_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_skill_level">سطح</label>
                            <select name="filter_skill_level" id="filter_skill_level" class="sc-filter-control">
                                <option value="">همه سطح‌ها</option>
                                <?php foreach ($level_options as $lvl) : ?>
                                    <option value="<?php echo esc_attr($lvl); ?>" <?php selected($filter_skill_level, $lvl); ?>>
                                        <?php echo esc_html($lvl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_identity">وضعیت احراز</label>
                            <select name="filter_identity" id="filter_identity" class="sc-filter-control">
                                <option value="all" <?php selected($filter_identity, 'all'); ?>>همه</option>
                                <option value="verified" <?php selected($filter_identity, 'verified'); ?>>تأیید شده</option>
                                <option value="pending" <?php selected($filter_identity, 'pending'); ?>>در انتظار بررسی</option>
                            </select>
                        </div>

                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_insurance">وضعیت بیمه</label>
                            <select name="filter_insurance" id="filter_insurance" class="sc-filter-control">
                                <option value="all" <?php selected($filter_insurance, 'all'); ?>>همه</option>
                                <option value="active" <?php selected($filter_insurance, 'active'); ?>>بیمه فعال</option>
                                <option value="expired" <?php selected($filter_insurance, 'expired'); ?>>بیمه منقضی</option>
                                <option value="none" <?php selected($filter_insurance, 'none'); ?>>بدون بیمه</option>
                            </select>
                        </div>

                    </div>

                    <div class="sc-members-list-filters-actions">
                        <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-members')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                    </div>
                </form>
            </div>

            <div class="sc-members-list-table-card">
                <form method="get">
                    <input type="hidden" name="page" value="sc-members">
                    <?php
                    $sc_members_preserve = [
                        'filter_course'      => $filter_course,
                        'filter_status'      => $filter_status,
                        'filter_profile'     => $filter_profile,
                        'filter_member_type' => $filter_member_type,
                        'filter_team'        => $filter_team,
                        'filter_skill_level' => $filter_skill_level,
                        'filter_identity'    => $filter_identity,
                        'filter_insurance'   => $filter_insurance,
                    ];
                    foreach ($sc_members_preserve as $fk => $fv) {
                        if ($fk === 'filter_course' && (int) $fv <= 0) {
                            continue;
                        }
                        if (in_array($fk, ['filter_status', 'filter_profile', 'filter_member_type', 'filter_identity', 'filter_insurance'], true) && ($fv === 'all' || $fv === '')) {
                            continue;
                        }
                        if (($fk === 'filter_team' || $fk === 'filter_skill_level') && $fv === '') {
                            continue;
                        }
                        echo '<input type="hidden" name="' . esc_attr($fk) . '" value="' . esc_attr((string) $fv) . '" />';
                    }
                    if (!empty($_GET['filter_player'])) {
                        echo '<input type="hidden" name="filter_player" value="' . esc_attr((string) absint($_GET['filter_player'])) . '" />';
                    }
                    if (!empty($_GET['player_status']) && $_GET['player_status'] !== 'all') {
                        echo '<input type="hidden" name="player_status" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['player_status']))) . '" />';
                    }
                    if (!empty($_GET['s'])) {
                        echo '<input type="hidden" name="s" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['s']))) . '" />';
                    }
                    $player_list_table->views();
                    $player_list_table->display();
                    ?>
                </form>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function ($) {
            var $toggle = $('#sc-members-filters-toggle');
            var $panel = $('#sc-members-filters-panel');
            var $card = $toggle.closest('.sc-members-list-filters-card');
            var $label = $toggle.find('.sc-members-list-filters-toggle-label');

            $toggle.on('click', function () {
                var isOpen = $card.hasClass('is-open');
                if (isOpen) {
                    $card.removeClass('is-open');
                    $panel.attr('hidden', true);
                    $toggle.attr('aria-expanded', 'false');
                    $label.text($label.data('label-closed'));
                } else {
                    $card.addClass('is-open');
                    $panel.removeAttr('hidden');
                    $toggle.attr('aria-expanded', 'true');
                    $label.text($label.data('label-open'));
                }
            });

            function scMembersBulkConfirm(e, actionSelector) {
                var action = $(actionSelector).val();
                if (action !== 'delete') {
                    return true;
                }
                var checked = $('input[name="player[]"]:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    alert('لطفاً حداقل یک بازیکن را انتخاب کنید.');
                    return false;
                }
                e.preventDefault();
                var form = $(e.target).closest('form');
                var msg = checked === 1
                    ? 'آیا از حذف بازیکن انتخاب‌شده اطمینان دارید؟ این عمل قابل بازگشت نیست.'
                    : 'آیا از حذف ' + checked + ' بازیکن انتخاب‌شده اطمینان دارید؟ این عمل قابل بازگشت نیست.';
                if (typeof scConfirm === 'function') {
                    scConfirm({ type: 'danger', message: msg }).then(function (ok) {
                        if (ok) {
                            form.off('submit').submit();
                        }
                    });
                } else if (window.confirm(msg)) {
                    form.off('submit').submit();
                }
                return false;
            }
            $('#doaction, #doaction2').on('click', function (e) {
                var selector = $(this).attr('id') === 'doaction2' ? '#bulk-action-selector-bottom' : '#bulk-action-selector-top';
                return scMembersBulkConfirm(e, selector);
            });
        });
        </script>





<!-- The Modal -->
<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <p class="sk-modal-content hide_before_data"></p>
    <p class="sk-modal-content_courses hide_before_data_courses"></p>
    <div class="sc-modal-body">
            <div class="sc-modal-loading" style="text-align: center; padding: 40px;">
                <div class="sc-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>

  
  </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // ---------- نمایش پاپ آپ اطلاعات بازیکن ----------
    $(document).on('click', '.view-player', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        let playerId = $(this).data('id');
        
        if (!playerId) {
            alert('خطا: شناسه بازیکن پیدا نشد');
            return;
        }
        
        let $modal = $('#myModal');
        
        if (!$modal.length) {
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
        let $loading = $modal.find('.sc-modal-loading');
        let $CourseList = $modal.find('.sc-modal-users-list');
        
        $loading.show();
        $CourseList.hide().empty();
        
        $modal.css({
            'display': 'flex',
            'visibility': 'visible'
        }).addClass('show-modal');


        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $.ajax({
            url: ajaxUrl,
            type: 'post',
            data: {
                action: 'get_player_details',
                id: playerId
            },
            success: function(res){
                $loading.hide();
                
                if(res.success){
                    let p = res.data;
                     $(".hide_before_data").removeClass('hide_before_data');
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
                $loading.hide();
                alert('خطا در دریافت اطلاعات بازیکن. لطفاً دوباره تلاش کنید.');
            }
        });
    });

    // ---------- بستن مدال ----------
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $(".sk-modal-content ").addClass('hide_before_data');
        $('#myModal').fadeOut();
        
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $(".sk-modal-content").addClass('hide_before_data');
            $('#myModal').fadeOut();
            
        }
    });
});


//برای دوره های بازیکن 

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
                action: 'get_player_details_courses',
                id: playerId
            },
            success: function(res){
                console.log('AJAX Success Response:', res);
            
                
               if(res.success && res.data){
                    let courses = res.data || [];
                    let $modal = $('#myModal');
                    let $CourseList = $modal.find('.sk-modal-content_courses');
                    if(courses.length > 0){
                         $(".sk-modal-content_courses").removeClass('hide_before_data_courses');
                        let p = res.data;
                    let html = '<div class="CourseList" >';    
                    
                    
                    html +=  '<div class="sc-users-summary" style="margin-bottom: 20px; padding: 15px; background-color: #f0f6fc; border-radius: 4px;"><h4>دوره های فعال بازیکن : </h4>' ;     
                    $.each(p, function(index=0, user){
                          html +=  '<p>' + p[index].title +'</p>' ;     
                        
                        
         
                         });

                         html += '</tbody></table></div>';
                         $CourseList.html(html).fadeIn(300);
                    } else {
                        let errorMsg = res.data.message || 'هیچ کاربر فعالی در این دوره یافت نشد.';
                        $CourseList.html('<p style="text-align: center; padding: 40px; color: #666;">' + errorMsg + '</p>').fadeIn(300);
                    }
                } else {
                    $CourseList.html('<p style="text-align: center; padding: 40px; color: #666;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>').fadeIn(300);
                }
            },
            error: function(xhr, status, error){
                alert('خطا در دریافت اطلاعات بازیکن. لطفاً دوباره تلاش کنید.');
            }
        });
    });

    // ---------- بستن مدال ----------
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $(".sk-modal-content_courses").addClass('hide_before_data_courses');
        $('#myModal').fadeOut();
        
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $(".sk-modal-content_courses").addClass('hide_before_data_courses');
            $('#myModal').fadeOut();
            
        }
    });
    
});


</script>

    <?php

