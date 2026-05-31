<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $audience @var array $target_config @var array $members @var array $courses @var array $events @var array $teams @var array $levels */
$target_type = isset($audience['target_type']) ? $audience['target_type'] : 'all';
$restriction = isset($audience['restriction']) ? (array) $audience['restriction'] : ['enabled' => 0, 'allowed_gender' => 'both', 'allowed_teams' => [], 'allowed_levels' => []];
$selected_member_ids = [];
if ($target_type === 'specific') {
    $selected_member_ids = array_map('absint', (array) ($target_config['member_ids'] ?? $target_config['recipient_ids'] ?? []));
}
$selected_course_ids = array_map('intval', (array) ($target_config['course_ids'] ?? []));
$selected_event_ids = array_map('intval', (array) ($target_config['event_ids'] ?? []));
$selected_team_names = (array) ($target_config['team_names'] ?? []);
$selected_level_names = (array) ($target_config['level_names'] ?? []);
?>
<div class="sc-users-export-card sc-survey-audience-card">
    <h2>مخاطبان نظرسنجی</h2>
    <p class="description">برای کدام دسته از مخاطبین میخواهید این نظرسنجی را انجام دهید؟</p>

    <div class="sc-row sc-survey-include-row"> 
        <label class="sc-inline-check"><input type="checkbox" name="include_players" id="include_players" value="1" <?php checked($audience['include_players'] ?? 1, 1); ?>> بازیکنان</label>
        <label class="sc-inline-check"><input type="checkbox" name="include_coaches" id="include_coaches" value="1" <?php checked($audience['include_coaches'] ?? 1, 1); ?>> مربیان</label>
    </div>

    <div class="sc-row sc-survey-player-only" id="sc-survey-player-target-row">
        <label for="sc-survey-target-type">نوع انتخاب</label>
        <select name="target_type" id="sc-survey-target-type">
            <option value="all" <?php selected($target_type, 'all'); ?>>همه کاربران</option>
            <option value="free_users" <?php selected($target_type, 'free_users'); ?>>کاربران آزاد</option>
            <option value="specific" <?php selected($target_type, 'specific'); ?>>انتخاب کاربران خاص</option>
            <option value="course" <?php selected($target_type, 'course'); ?>>بر اساس دوره</option>
            <option value="event" <?php selected($target_type, 'event'); ?>>بر اساس رویداد</option>
            <option value="team" <?php selected($target_type, 'team'); ?>>بر اساس تیم</option>
            <option value="level" <?php selected($target_type, 'level'); ?>>بر اساس سطح</option>
            <option value="team_level" <?php selected($target_type, 'team_level'); ?>>تیم + سطح</option>
        </select>
    </div>

    <div class="sc-row sc-survey-player-only" id="sc-survey-player-status-row">
        <label for="sc-survey-member-status">وضعیت کاربر</label>
        <select name="member_status" id="sc-survey-member-status">
            <option value="all" <?php selected($target_config['member_status'] ?? 'all', 'all'); ?>>همه</option>
            <option value="active" <?php selected($target_config['member_status'] ?? '', 'active'); ?>>فقط فعال</option>
            <option value="inactive" <?php selected($target_config['member_status'] ?? '', 'inactive'); ?>>فقط غیرفعال</option>
        </select>
    </div>

    <div class="sc-row sc-survey-player-only" id="sc-survey-player-type-row">
        <label for="sc-survey-member-type">دسته‌بندی بازیکن</label>
        <select name="member_type" id="sc-survey-member-type">
            <option value="all" <?php selected($target_config['member_type'] ?? 'all', 'all'); ?>>همه</option>
            <option value="normal" <?php selected($target_config['member_type'] ?? '', 'normal'); ?>>بازیکن عادی</option>
            <option value="team" <?php selected($target_config['member_type'] ?? '', 'team'); ?>>بازیکن تیم</option>
        </select>
    </div>

    <div class="sc-filter-block" id="sc-survey-filter-specific">
        <label>انتخاب کاربران</label>
        <div id="sc-survey-selected-count" class="sc-selected-count"><?php echo esc_html(count($selected_member_ids)); ?> کاربر انتخاب شده</div>
        <div id="sc-survey-selected-tags" class="sc-selected-tags"></div>
        <div class="sc-users-member-dropdown" id="sc-survey-member-dropdown">
            <div class="sc-users-dropdown-toggle">
                <span class="sc-users-dropdown-placeholder">جستجو با نام یا کد ملی...</span>
                <span class="sc-users-dropdown-arrow">▼</span>
            </div>
            <div class="sc-users-dropdown-menu">
                <div class="sc-users-dropdown-search">
                    <input type="text" class="sc-users-search-input" placeholder="جستجوی نام یا کد ملی...">
                </div>
                <div class="sc-users-dropdown-options" id="sc-survey-member-options">
                    <?php foreach ($members as $member) :
                        $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                        $search = strtolower($name . ' ' . ($member->national_id ?: ''));
                        ?>
                        <div class="sc-users-dropdown-option"
                             data-id="<?php echo (int) $member->id; ?>"
                             data-label="<?php echo esc_attr($name . ' - ' . ($member->national_id ?: $member->id)); ?>"
                             data-search="<?php echo esc_attr($search); ?>">
                            <?php echo esc_html($name . ' - ' . ($member->national_id ?: $member->id)); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div id="sc-survey-member-hidden-inputs">
            <?php foreach ($selected_member_ids as $mid) : ?>
                <input type="hidden" name="member_ids[]" value="<?php echo esc_attr($mid); ?>">
            <?php endforeach; ?>
        </div>
    </div>

    <div class="sc-filter-block" id="sc-survey-filter-course">
        <label for="sc-survey-course-ids">دوره‌ها</label>
        <select name="course_ids[]" id="sc-survey-course-ids" multiple size="7">
            <?php foreach ($courses as $course) : ?>
                <option value="<?php echo (int) $course->id; ?>" <?php echo in_array((int) $course->id, $selected_course_ids, true) ? 'selected' : ''; ?>><?php echo esc_html($course->title); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="sc-filter-block" id="sc-survey-filter-event">
        <label for="sc-survey-event-ids">رویدادها</label>
        <select name="event_ids[]" id="sc-survey-event-ids" multiple size="7">
            <?php foreach ($events as $event) : ?>
                <option value="<?php echo (int) $event->id; ?>" <?php echo in_array((int) $event->id, $selected_event_ids, true) ? 'selected' : ''; ?>><?php echo esc_html($event->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="sc-filter-block" id="sc-survey-filter-team">
        <label for="sc-survey-team-names">تیم‌ها</label>
        <select name="team_names[]" id="sc-survey-team-names" multiple size="7">
            <?php foreach ($teams as $team) : ?>
                <option value="<?php echo esc_attr($team->name); ?>" <?php echo in_array($team->name, $selected_team_names, true) ? 'selected' : ''; ?>><?php echo esc_html($team->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="sc-filter-block" id="sc-survey-filter-level">
        <label for="sc-survey-level-names">سطح‌ها</label>
        <select name="level_names[]" id="sc-survey-level-names" multiple size="7">
            <?php foreach ($levels as $level) : ?>
                <option value="<?php echo esc_attr($level->name); ?>" <?php echo in_array($level->name, $selected_level_names, true) ? 'selected' : ''; ?>><?php echo esc_html($level->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="sc-users-export-card sc-survey-restriction-card">
    <h2>اعمال محدودیت</h2>
    <div class="sc-row">
        <label class="sc-inline-check">
            <input type="checkbox" name="restriction_enabled" id="sc-survey-restriction-enabled" value="1" <?php checked(!empty($restriction['enabled'])); ?>>
          فعال سازی محدویت
        </label>
    </div>
    <div id="sc-survey-restrictions-box" class="sc-survey-restrictions-box" <?php echo empty($restriction['enabled']) ? 'style="display:none;"' : ''; ?>>
        <div class="sc-row">
            <label for="sc-survey-allowed-gender">جنسیت مجاز</label>
            <select name="allowed_gender" id="sc-survey-allowed-gender">
                <option value="both" <?php selected($restriction['allowed_gender'] ?? 'both', 'both'); ?>>هر دو</option>
                <option value="male" <?php selected($restriction['allowed_gender'] ?? '', 'male'); ?>>مرد</option>
                <option value="female" <?php selected($restriction['allowed_gender'] ?? '', 'female'); ?>>زن</option>
            </select>
        </div>
        <div class="sc-row">
            <label>تیم‌های مجاز</label>
            <div class="sc-checkbox-grid">
                <?php foreach ($teams as $team) : ?>
                    <label><input type="checkbox" name="allowed_teams[]" value="<?php echo esc_attr($team->name); ?>" <?php checked(in_array($team->name, (array) ($restriction['allowed_teams'] ?? []), true)); ?>> <?php echo esc_html($team->name); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sc-row">
            <label>سطح‌های مجاز</label>
            <div class="sc-checkbox-grid">
                <?php foreach ($levels as $level) : ?>
                    <label><input type="checkbox" name="allowed_levels[]" value="<?php echo esc_attr($level->name); ?>" <?php checked(in_array($level->name, (array) ($restriction['allowed_levels'] ?? []), true)); ?>> <?php echo esc_html($level->name); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
