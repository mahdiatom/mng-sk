<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$saved = isset($saved) ? $saved : [];
$initial_target_type = isset($initial_target_type) ? $initial_target_type : 'all';
$is_coach = false;
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';
$teams_list = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name ASC");
$levels_list = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name ASC");
?>
        <div class="sc-bale-panel sc-bale-filter-panel sc-users-export-card sc-notification-bulk-filter-card postbox">
            <div class="postbox-header">
                <h2>۲) فیلتر مخاطبین</h2>
            </div>
            <div class="inside">
            <table class="form-table sc-notification-form-table">
            <tr>
                <th scope="row">نوع ارسال</th>
                <td>
                    <select name="target_type" id="target_type" class="sc-notification-select" style="min-width: 200px;">
                        <option value="all" <?php selected($initial_target_type, 'all'); ?>>همه</option>
                        <option value="free_users" <?php selected($initial_target_type, 'free_users'); ?>>کاربران آزاد (بدون هیچ دوره)</option>
                        <option value="specific" <?php selected($initial_target_type, 'specific'); ?>>ارسال به مخاطبین خاص</option>
                        <option value="course" <?php selected($initial_target_type, 'course'); ?>>ارسال به مخاطبین دوره </option>
                        <?php if (!$is_coach) : ?>
                        <option value="debtors" <?php selected($initial_target_type, 'debtors'); ?>>ارسال به مخاطبین بدهکاران</option>
                        <option value="event" <?php selected($initial_target_type, 'event'); ?>>ارسال به مخاطبین رویداد</option>
                        <option value="team" <?php selected($initial_target_type, 'team'); ?>>ارسال به تیم</option>
                        <option value="level" <?php selected($initial_target_type, 'level'); ?>>ارسال به سطح بازیکن</option>
                        <option value="team_level" <?php selected($initial_target_type, 'team_level'); ?>>
                            ارسال به تیم + سطح
                        </option>

                        <?php 
                    if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) { ?>
                        <option value="wallet_negative" <?php selected($initial_target_type, 'wallet_negative'); ?>>ارسال به مخاطبین با کیف پول منفی</option>
                     <?php } ?>
                        <option value="phone" <?php selected($initial_target_type, 'phone'); ?>>ارسال به شماره مخاطب خاص</option>
                        <?php endif; ?>
                    </select>
                        
                </td>
                
            </tr>

            <tr id="row-target-all" class="target-row">
                <th scope="row"><?php echo $is_coach ? 'بازیکنان دوره‌های من' : 'فیلتر مخاطبین (همه)'; ?></th>
                <td>
                    <?php if (!$is_coach) : ?>
                    <p>
                        <strong>نوع کاربر:</strong>
                        <select name="user_type" id="user_type" class="sc-notification-select" style="min-width: 180px; margin-right: 12px;">
                            <option value="all" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : 'all', 'all'); ?>>همه (بازیکن + مربی)</option>
                            <option value="player" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : '', 'player'); ?>>بازیکن</option>
                            <option value="coach" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : '', 'coach'); ?>>مربی</option>
                        </select>
                    </p>
                    <p>
                        <strong>محدوده دوره:</strong>
                        <select name="course_scope" id="course_scope" class="sc-notification-select" style="min-width: 180px; margin-right: 12px;">
                            <option value="all" <?php selected(isset($saved['course_scope']) ? $saved['course_scope'] : 'all', 'all'); ?>>همه</option>
                            <option value="specific" <?php selected(isset($saved['course_scope']) ? $saved['course_scope'] : '', 'specific'); ?>>دوره خاص</option>
                        </select>
                    </p>
                    <p id="row-course-ids-all" class="course-ids-row" style="display:none;">
                        <strong>انتخاب دوره:</strong><br>
                        <select name="course_ids[]" multiple size="6" style="min-width:300px;">
                            <?php foreach ($courses_list as $c) : ?>
                                <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, $saved['course_ids'])) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><small>Ctrl+Click برای انتخاب چند دوره</small>
                    </p>
                    <?php else : ?>
                    <p>
                        <strong>انتخاب دوره (فقط دوره‌های خودتان):</strong><br>
                        <select name="course_ids[]" multiple size="6" style="min-width:300px;">
                            <?php foreach ($courses_list as $c) : ?>
                                <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><small>Ctrl+Click برای انتخاب چند دوره. ارسال به بازیکنان فعال آن دوره‌ها.</small>
                    </p>
                    <input type="hidden" name="user_type" value="player">
                    <input type="hidden" name="course_scope" value="specific">
                    <?php endif; ?>
                </td>
            </tr>
            <tr id="row-target-specific" class="target-row" style="display:none;">
                <th scope="row">انتخاب اشخاص</th>
                <td>
                    <div id="recipient-list" class="sc-notification-recipient-tags"></div>
                    <div class="sc-searchable-dropdown sc-notification-recipient-dropdown">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder"><?php echo $is_coach ? 'جستجو یا انتخاب بازیکن برای افزودن...' : 'جستجو یا انتخاب بازیکن / مربی برای افزودن...'; ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                <?php
                                $opt_index = 0;
                                $max_visible = 10;
                                foreach ($members as $m) :
                                    $val = 'member_' . $m->id;
                                    $label = $m->first_name . ' ' . $m->last_name . ' (بازیکن)';
                                    $search = strtolower($m->first_name . ' ' . $m->last_name . ' ' . ($m->national_id ?: ''));
                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                    $opt_index++;
                                    
                                ?>
                                    <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($m->first_name . ' ' . $m->last_name . ' - ' . ($m->national_id ?: $m->id)); ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($coaches)) : ?>
                                <div class="sc-dropdown-option-group">مربی‌ها</div>
                                <?php foreach ($coaches as $c) :
                                    $val = 'coach_' . $c->id;
                                    $label = $c->first_name . ' ' . $c->last_name . ' (مربی)';
                                    $search = strtolower($c->first_name . ' ' . $c->last_name . ' ' . ($c->national_id ?: ''));
                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                    $opt_index++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($c->first_name . ' ' . $c->last_name . ' - ' . ($c->national_id ?: $c->id)); ?></div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید تا به مخاطبین اضافه شود.</p>
                    <input type="hidden" name="recipient_ids_str" id="recipient-ids-input" value="">
                </td>
            </tr>
            <tr id="row-target-course" class="target-row" style="display:none;">
                <th scope="row">انتخاب دوره</th>
                <td>
                    <select name="course_ids[]" id="course-ids-course" multiple size="8" style="min-width:350px;">
                        <?php foreach ($courses_list as $c) : ?>
                            <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br><small>Ctrl+Click برای انتخاب چند دوره. ارسال به اعضای فعال دوره.</small>
                </td>
            </tr>
            <tr id="row-target-free_users" class="target-row" style="display:none;">
                <th scope="row">کاربران آزاد</th>
                <td>
                    <p class="description">ارسال به بازیکنانی که تا امروز هیچ رکوردی در دوره‌ها نداشته‌اند.</p>
                </td>
            </tr>
            <tr id="row-target-exclude" style="display:none;">
                <th scope="row">استثنا از فیلتر (اختیاری)</th>
                <td>
                    <div id="exclude-recipient-list" class="sc-notification-recipient-tags"></div>
                    <div class="sc-searchable-dropdown sc-exclude-recipient-dropdown">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder">جستجو یا انتخاب مخاطب برای حذف از خروجی...</span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                <?php foreach ($members as $m) :
                                    $val = 'member_' . $m->id;
                                    $label = $m->first_name . ' ' . $m->last_name . ' (بازیکن)';
                                    $search = strtolower($m->first_name . ' ' . $m->last_name . ' ' . ($m->national_id ?: ''));
                                ?>
                                    <div class="sc-dropdown-option" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($m->first_name . ' ' . $m->last_name . ' - ' . ($m->national_id ?: $m->id)); ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($coaches)) : ?>
                                <div class="sc-dropdown-option-group">مربی‌ها</div>
                                <?php foreach ($coaches as $c) :
                                    $val = 'coach_' . $c->id;
                                    $label = $c->first_name . ' ' . $c->last_name . ' (مربی)';
                                    $search = strtolower($c->first_name . ' ' . $c->last_name . ' ' . ($c->national_id ?: ''));
                                ?>
                                    <div class="sc-dropdown-option" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($c->first_name . ' ' . $c->last_name . ' - ' . ($c->national_id ?: $c->id)); ?></div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="exclude_recipient_ids_str" id="exclude-recipient-ids-input" value="">
                </td>
            </tr>
            <?php if (!$is_coach) : ?>
            <tr id="row-target-debtors" class="target-row" style="display:none;">
                <th scope="row">بدهکاران</th>
                <td>
                    <p><strong>محدوده:</strong> اگر دوره انتخاب نکنید، به همه اعضایی که حداقل یک صورتحساب پرداخت‌نشده دارند ارسال می‌شود.</p>
                    <p>
                        <strong>فیلتر بر اساس دوره (اختیاری):</strong><br>
                        <select name="debtors_course_ids[]" id="debtors-course-ids" multiple size="6" style="min-width:300px;">
                            <?php foreach ($courses_list as $c) : ?>
                                <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><small>خالی = همه بدهکاران. با انتخاب دوره فقط بدهکاران آن دوره‌ها.</small>
                    </p>
                </td>
            </tr>
            <tr id="row-target-event" class="target-row" style="display:none;">
                <th scope="row">رویداد و شرکت‌کنندگان</th>
                <td>
                    <p><strong>انتخاب رویداد:</strong><br>
                        <select name="event_ids[]" id="event-ids-select" multiple size="6" style="min-width:350px;">
                            <?php if (!empty($events_list)) : foreach ($events_list as $e) : ?>
                                <option value="<?php echo $e->id; ?>" <?php echo (isset($saved['event_ids']) && in_array($e->id, (array)($saved['event_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($e->name); ?></option>
                            <?php endforeach; else : ?>
                                <option value="" disabled>رویدادی یافت نشد</option>
                            <?php endif; ?>
                        </select>
                        <br><small>Ctrl+Click برای انتخاب چند رویداد. ارسال به همه ثبت‌نام‌شدگان.</small>
                    </p>

                    <input type="hidden" name="event_recipient_ids_str" id="event-recipient-ids-input" value="">
                </td>
            </tr>
            <tr id="row-target-wallet_negative" class="target-row" style="display:none;">
                <th scope="row">موجودی کیف پول منفی</th>
                <td>
                    <p class="description">ارسال به اعضایی که موجودی کیف پول آن‌ها منفی است. در صورت غیرفعال بودن کیف پول، این گزینه مخاطبی ندارد.</p>
                </td>
            </tr>
            <tr id="row-target-phone" class="target-row" style="display:none;">
                <th scope="row">شماره موبایل</th>
                <td>
                    <div id="phone-list" class="sc-notification-recipient-tags"></div>
                    <div class="sc-phone-add-row" style="display: flex; gap: 8px; margin-top: 10px; align-items: center;">
                        <input type="text" id="phone-input" class="regular-text" placeholder="۰۹۱۲۳۴۵۶۷۸۹" style="max-width: 180px;">
                        <button type="button" id="phone-add-btn" class="button">افزودن شماره</button>
                    </div>
                    <p class="description">شماره موبایل را وارد کنید و «افزودن شماره» را بزنید. ارسال از طریق سفیر (هزینه‌دار) انجام می‌شود.</p>
                    <input type="hidden" name="phone_numbers_str" id="phone-numbers-input" value="">

                    <div class="sc-phone-excel-import" style="margin-top: 18px; padding-top: 16px; border-top: 1px dashed #ccd0d4;">
                        <p style="margin: 0 0 8px; font-weight: 600;">یا ارسال از لیست اکسل</p>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="file" name="phone_excel_file" id="phone-excel-file" accept=".xls,.xlsx,.csv">
                            <span id="phone-excel-preview" class="description" style="margin: 0;"></span>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            ستون <strong>اول</strong> فایل (A) باید شماره موبایل باشد. فرمت‌های xls، xlsx و csv — حداکثر ۵ مگابایت.
                            می‌توانید همزمان شماره دستی و فایل اکسل داشته باشید.
                        </p>
                    </div>
                </td>
            </tr>
            <tr id="row-target-team" class="target-row" style="display:none;">
                <th scope="row">انتخاب تیم</th>
                <td>
                    <select name="team_names[]" id="team-names-select" multiple size="6" style="min-width:300px;">
                        <?php foreach ($teams_list as $t) : ?>
                            <option value="<?php echo esc_attr($t->name); ?>" 
                                <?php echo (isset($saved['team_names']) && in_array($t->name, (array)$saved['team_names'])) ? 'selected' : ''; ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <br><small>Ctrl+Click برای انتخاب چند تیم. پیام برای بازیکنان همین تیم‌ها ارسال می‌شود.</small>
                </td>
            </tr>
            <tr id="row-target-level" class="target-row" style="display:none;">
                <th scope="row">انتخاب سطح</th>
                <td>
                    <select name="level_names[]" id="level-names-select" multiple size="6" style="min-width:300px;">
                        <?php foreach ($levels_list as $t) : ?>
                            <option value="<?php echo esc_attr($t->name); ?>" 
                                <?php echo (isset($saved['level_names']) && in_array($t->name, (array)$saved['level_names'])) ? 'selected' : ''; ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <br><small>Ctrl+Click برای انتخاب چند سطح. پیام برای بازیکنان همین تیم‌ها ارسال می‌شود.</small>
                </td>
            </tr>
            <tr id="row-target-team_level" class="target-row" style="display:none;">
                <th scope="row">تیم + سطح</th>
                <td>

                    <p><strong>انتخاب تیم:</strong></p>
                    <select name="team_names[]" id="team-level-team-select" multiple size="6" style="min-width:300px;">
                        <?php foreach ($teams_list as $t) : ?>
                            <option value="<?php echo esc_attr($t->name); ?>"
                                <?php echo (isset($saved['team_names']) && in_array($t->name, (array)$saved['team_names'])) ? 'selected' : ''; ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <br><br>

                    <p><strong>انتخاب سطح:</strong></p>
                    <select name="level_names[]" id="team-level-level-select" multiple size="6" style="min-width:300px;">
                        <?php foreach ($levels_list as $t) : ?>
                            <option value="<?php echo esc_attr($t->name); ?>"
                                <?php echo (isset($saved['level_names']) && in_array($t->name, (array)$saved['level_names'])) ? 'selected' : ''; ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <br>
                    <small>فقط بازیکنانی که هم در تیم انتخاب‌شده باشند و هم سطح انتخاب‌شده داشته باشند پیام را دریافت می‌کنند.</small>

                </td>
            </tr>
            <?php endif; ?>
            </table>
            <p id="sc-bale-preview-submit-wrap" class="submit sc-bale-preview-submit-wrap" style="display:none;">
                <button type="button" class="button button-secondary" id="sc-bale-preview-btn">پیش‌نمایش مخاطبین</button>
            </p>
            </div>
        </div>
        <div id="sc-bale-preview-bulk-cards" class="sc-bale-preview-section" style="display:none;">
            <div class="sc-bale-panel sc-bale-preview-panel sc-users-export-card postbox">
                <div class="postbox-header">
                    <h2>۳) پیش‌نمایش مخاطبین فیلترشده</h2>
                </div>
                <div class="inside">
                    <div id="sc-bale-preview-result" class="sc-bulk-preview-result back_table_list">
                        <p class="description">بعد از انتخاب فیلتر و حالت ارسال، روی «پیش‌نمایش مخاطبین» کلیک کنید.</p>
                    </div>
                </div>
            </div>
        </div>
