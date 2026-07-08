<?php
/**
 * فرمت‌کننده‌های خروجی API عمومی
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string|null $path
 * @return string|null
 */
function sc_public_api_media_url($path) {
    $path = is_string($path) ? trim($path) : '';
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return esc_url_raw($path);
    }
    return esc_url_raw(home_url('/' . ltrim($path, '/')));
}

/**
 * @param object $row
 * @return array<string,mixed>
 */
function sc_public_api_format_branch($row) {
    $lat = isset($row->latitude) && $row->latitude !== null && $row->latitude !== '' ? (float) $row->latitude : null;
    $lng = isset($row->longitude) && $row->longitude !== null && $row->longitude !== '' ? (float) $row->longitude : null;

    return [
        'id' => (int) $row->id,
        'name' => isset($row->name) ? (string) $row->name : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'address' => isset($row->address) ? (string) $row->address : '',
        'phone' => isset($row->phone) ? (string) $row->phone : '',
        'location' => ($lat !== null && $lng !== null) ? [
            'latitude' => $lat,
            'longitude' => $lng,
        ] : null,
        'image_url' => sc_public_api_media_url(isset($row->image) ? (string) $row->image : ''),
        'sort_order' => isset($row->sort_order) ? (int) $row->sort_order : 0,
        'is_active' => !isset($row->is_active) || (int) $row->is_active === 1,
    ];
}

/**
 * @return array<string,string>
 */
function sc_public_api_member_type_labels() {
    return [
        'normal' => 'بازیکن عادی',
        'team' => 'بازیکن تیم',
    ];
}

/**
 * @return array<string,string>
 */
function sc_public_api_gender_labels() {
    return [
        'male' => 'پسر',
        'female' => 'دختر',
        'boy' => 'پسر',
        'girl' => 'دختر',
        'both' => 'همه',
    ];
}

/**
 * @return array<string,string>
 */
function sc_public_api_course_type_labels() {
    return [
        'group' => 'گروهی',
        'private' => 'خصوصی/نیمه‌خصوصی',
    ];
}

/**
 * @return array<string,string>
 */
function sc_public_api_event_type_labels() {
    return [
        'event' => 'رویداد',
        'competition' => 'مسابقه',
    ];
}

/**
 * @param object $row
 * @param bool   $include_honors
 * @return array<string,mixed>
 */
function sc_public_api_format_coach($row, $include_honors = false) {
    $first = isset($row->first_name) ? (string) $row->first_name : '';
    $last = isset($row->last_name) ? (string) $row->last_name : '';
    $gender = isset($row->gender) ? (string) $row->gender : '';
    $gender_labels = sc_public_api_gender_labels();

    $item = [
        'id' => (int) $row->id,
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => trim($first . ' ' . $last),
        'photo_url' => sc_public_api_media_url(isset($row->personal_photo) ? (string) $row->personal_photo : ''),
        'gender' => $gender,
        'gender_label' => isset($gender_labels[$gender]) ? $gender_labels[$gender] : $gender,
        'specialization' => isset($row->specialization) ? (string) $row->specialization : '',
        'coaching_level' => isset($row->coaching_level) ? (string) $row->coaching_level : '',
        'coaching_experience' => isset($row->coaching_experience) ? (int) $row->coaching_experience : null,
        'sports_history' => isset($row->sports_history) ? (string) $row->sports_history : '',
        'registration_url' => sc_public_api_enrollment_url(),
    ];

    if ($include_honors) {
        $item['honors'] = sc_public_api_fetch_coach_honors((int) $row->id);
    }

    return $item;
}

/**
 * @param object $row
 * @param bool   $include_honors
 * @return array<string,mixed>
 */
function sc_public_api_format_player($row, $include_honors = false) {
    $first = isset($row->first_name) ? (string) $row->first_name : '';
    $last = isset($row->last_name) ? (string) $row->last_name : '';
    $gender = isset($row->gender) ? (string) $row->gender : '';
    $member_type = isset($row->member_type) ? (string) $row->member_type : 'normal';
    $gender_labels = sc_public_api_gender_labels();
    $type_labels = sc_public_api_member_type_labels();
    $has_honors = isset($row->has_honors) ? (string) $row->has_honors : 'no';

    $item = [
        'id' => (int) $row->id,
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => trim($first . ' ' . $last),
        'photo_url' => sc_public_api_media_url(isset($row->personal_photo) ? (string) $row->personal_photo : ''),
        'gender' => $gender,
        'gender_label' => isset($gender_labels[$gender]) ? $gender_labels[$gender] : $gender,
        'member_type' => $member_type,
        'member_type_label' => isset($type_labels[$member_type]) ? $type_labels[$member_type] : $member_type,
        'team' => isset($row->team_player) ? (string) $row->team_player : '',
        'skill_level' => isset($row->skill_level) ? (string) $row->skill_level : '',
        'sports_history' => isset($row->sports_history) ? (string) $row->sports_history : '',
        'has_honors' => $has_honors === 'yes' ? 'yes' : 'no',
    ];

    if ($include_honors) {
        $item['honors'] = sc_public_api_fetch_member_honors((int) $row->id);
    }

    return $item;
}

/**
 * @param object $row
 * @return array<string,mixed>
 */
function sc_public_api_format_honor($row) {
    $member_id = !empty($row->member_id) ? (int) $row->member_id : 0;
    $coach_id = !empty($row->coach_id) ? (int) $row->coach_id : 0;
    $owner_type = 'unknown';
    $owner = null;

    if ($member_id > 0) {
        $owner_type = 'member';
        $owner = [
            'id' => $member_id,
            'first_name' => isset($row->member_first_name) ? (string) $row->member_first_name : '',
            'last_name' => isset($row->member_last_name) ? (string) $row->member_last_name : '',
            'full_name' => trim((string) ($row->member_first_name ?? '') . ' ' . (string) ($row->member_last_name ?? '')),
        ];
    } elseif ($coach_id > 0) {
        $owner_type = 'coach';
        $owner = [
            'id' => $coach_id,
            'first_name' => isset($row->coach_first_name) ? (string) $row->coach_first_name : '',
            'last_name' => isset($row->coach_last_name) ? (string) $row->coach_last_name : '',
            'full_name' => trim((string) ($row->coach_first_name ?? '') . ' ' . (string) ($row->coach_last_name ?? '')),
        ];
    }

    $created_at = isset($row->created_at) ? (string) $row->created_at : '';
    $created_at_shamsi = ($created_at !== '' && function_exists('sc_date_shamsi'))
        ? sc_date_shamsi($created_at, 'Y/m/d H:i')
        : '';

    return [
        'id' => (int) $row->id,
        'title' => isset($row->name) ? (string) $row->name : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'category' => [
            'id' => isset($row->category_id) ? (int) $row->category_id : 0,
            'name' => isset($row->category_name) ? (string) $row->category_name : '',
        ],
        'file_url' => !empty($row->file_url) ? (string) $row->file_url : null,
        'created_at' => $created_at,
        'created_at_shamsi' => $created_at_shamsi,
        'owner_type' => $owner_type,
        'owner' => $owner,
    ];
}

/**
 * @param int $member_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_fetch_member_honors($member_id) {
    if ($member_id <= 0 || !function_exists('sc_honors_api_query_rows')) {
        return [];
    }
    $result = sc_honors_api_query_rows([
        'member_id' => $member_id,
        'owner_type' => 'member',
        'status' => 'approved',
        'page' => 1,
        'per_page' => 100,
    ]);
    $items = [];
    foreach ($result['items'] as $row) {
        $items[] = sc_public_api_format_honor($row);
    }
    return $items;
}

/**
 * @param int $coach_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_fetch_coach_honors($coach_id) {
    if ($coach_id <= 0 || !function_exists('sc_honors_api_query_rows')) {
        return [];
    }
    $result = sc_honors_api_query_rows([
        'coach_id' => $coach_id,
        'owner_type' => 'coach',
        'status' => 'approved',
        'page' => 1,
        'per_page' => 100,
    ]);
    $items = [];
    foreach ($result['items'] as $row) {
        $items[] = sc_public_api_format_honor($row);
    }
    return $items;
}

/**
 * @param object $row
 * @param string $course_type
 * @param bool   $detailed
 * @return array<string,mixed>
 */
function sc_public_api_format_course($row, $course_type, $detailed = false) {
    $type_labels = sc_public_api_course_type_labels();
    $course_id = (int) $row->id;

    $item = [
        'id' => $course_id,
        'title' => isset($row->title) ? (string) $row->title : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'image_url' => sc_public_api_media_url(isset($row->image) ? (string) $row->image : ''),
        'course_type' => $course_type,
        'course_type_label' => isset($type_labels[$course_type]) ? $type_labels[$course_type] : $course_type,
        'sessions_count' => isset($row->sessions_count) ? (int) $row->sessions_count : null,
        'registration_url' => $course_type === 'private'
            ? sc_public_api_private_classes_url()
            : sc_public_api_enrollment_url(),
        'dates' => [
            'start_gregorian' => !empty($row->start_date) ? (string) $row->start_date : null,
            'end_gregorian' => !empty($row->end_date) ? (string) $row->end_date : null,
            'start_shamsi' => (!empty($row->start_date) && function_exists('sc_date_shamsi_date_only'))
                ? sc_date_shamsi_date_only($row->start_date)
                : null,
            'end_shamsi' => (!empty($row->end_date) && function_exists('sc_date_shamsi_date_only'))
                ? sc_date_shamsi_date_only($row->end_date)
                : null,
        ],
    ];

    if ($detailed) {
        $item['chapters'] = sc_public_api_course_chapters($course_id);
        $item['coaches'] = sc_public_api_course_coaches($course_id);
        $item['weekly_schedule'] = sc_public_api_course_weekly_schedule($course_id);
    } else {
        $item['chapters'] = sc_public_api_course_chapter_names($course_id);
        $item['weekdays'] = sc_public_api_course_weekday_summary($course_id);
    }

    return $item;
}

/**
 * @param int $course_id
 * @return array<int,string>
 */
function sc_public_api_course_chapter_names($course_id) {
    if ($course_id <= 0) {
        return [];
    }
    if (function_exists('sc_get_course_chapters')) {
        $chapters = sc_get_course_chapters($course_id);
        $names = [];
        foreach ((array) $chapters as $ch) {
            $name = is_object($ch) ? (string) ($ch->chapter_name ?? '') : '';
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if (!empty($names)) {
            return array_values(array_unique($names));
        }
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $chapter = $wpdb->get_var($wpdb->prepare(
        "SELECT chapter FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));
    return $chapter ? [(string) $chapter] : [];
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_course_chapters($course_id) {
    $names = sc_public_api_course_chapter_names($course_id);
    $items = [];
    foreach ($names as $name) {
        $items[] = ['name' => $name];
    }
    return $items;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_course_coaches($course_id) {
    global $wpdb;
    if ($course_id <= 0) {
        return [];
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT c.id, c.first_name, c.last_name, c.personal_photo, c.specialization, c.coaching_level,
                cc.chapter_name
         FROM $cc cc
         INNER JOIN $coaches_table c ON c.id = cc.coach_id
         WHERE cc.course_id = %d AND c.is_active = 1
         ORDER BY cc.chapter_name ASC, c.last_name ASC, c.first_name ASC",
        $course_id
    ));

    $items = [];
    foreach ((array) $rows as $row) {
        $first = (string) $row->first_name;
        $last = (string) $row->last_name;
        $items[] = [
            'id' => (int) $row->id,
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => trim($first . ' ' . $last),
            'photo_url' => sc_public_api_media_url((string) $row->personal_photo),
            'specialization' => (string) $row->specialization,
            'coaching_level' => (string) $row->coaching_level,
            'chapter' => (string) $row->chapter_name,
        ];
    }
    return $items;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_course_weekly_schedule($course_id) {
    if ($course_id <= 0 || !function_exists('sc_get_course_weekly_schedule_rows')) {
        return [];
    }

    $labels = function_exists('sc_course_weekday_labels_ir')
        ? sc_course_weekday_labels_ir()
        : [];

    $rows = sc_get_course_weekly_schedule_rows($course_id);
    $items = [];
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    foreach ((array) $rows as $row) {
        $coach_id = isset($row->coach_id) ? (int) $row->coach_id : 0;
        $coach = null;
        if ($coach_id > 0) {
            $coach_row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, first_name, last_name FROM $coaches_table WHERE id = %d LIMIT 1",
                $coach_id
            ));
            if ($coach_row) {
                $coach = [
                    'id' => (int) $coach_row->id,
                    'full_name' => trim((string) $coach_row->first_name . ' ' . (string) $coach_row->last_name),
                ];
            }
        }

        $weekday = (int) $row->weekday;
        $items[] = [
            'id' => (int) $row->id,
            'weekday' => $weekday,
            'weekday_label' => isset($labels[$weekday]) ? $labels[$weekday] : (string) $weekday,
            'time_start' => substr((string) $row->time_start, 0, 5),
            'time_end' => substr((string) $row->time_end, 0, 5),
            'chapter' => isset($row->chapter_name) ? (string) $row->chapter_name : '',
            'coach' => $coach,
            'group_name' => isset($row->group_name) ? (string) $row->group_name : '',
        ];
    }

    return $items;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_public_api_course_weekday_summary($course_id) {
    $schedule = sc_public_api_course_weekly_schedule($course_id);
    $seen = [];
    $days = [];
    foreach ($schedule as $slot) {
        $wd = (int) $slot['weekday'];
        if ($wd < 1 || isset($seen[$wd])) {
            continue;
        }
        $seen[$wd] = true;
        $days[] = [
            'weekday' => $wd,
            'weekday_label' => (string) $slot['weekday_label'],
        ];
    }
    return $days;
}

/**
 * @param object $row
 * @param bool   $detailed
 * @return array<string,mixed>
 */
function sc_public_api_format_event($row, $detailed = false) {
    $type_labels = sc_public_api_event_type_labels();
    $event_type = isset($row->event_type) ? (string) $row->event_type : 'event';
    $today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';

    $holding_shamsi = isset($row->holding_date_shamsi) ? (string) $row->holding_date_shamsi : '';
    $start_shamsi = isset($row->start_date_shamsi) ? (string) $row->start_date_shamsi : '';
    $end_shamsi = isset($row->end_date_shamsi) ? (string) $row->end_date_shamsi : '';

    $is_upcoming = false;
    $is_past = false;
    if ($holding_shamsi !== '' && $today_shamsi !== '' && function_exists('sc_compare_shamsi_dates')) {
        $cmp = sc_compare_shamsi_dates($today_shamsi, $holding_shamsi);
        $is_upcoming = $cmp < 0;
        $is_past = $cmp > 0;
    }

    $lat = isset($row->event_location_lat) && $row->event_location_lat !== null && $row->event_location_lat !== ''
        ? (float) $row->event_location_lat : null;
    $lng = isset($row->event_location_lng) && $row->event_location_lng !== null && $row->event_location_lng !== ''
        ? (float) $row->event_location_lng : null;

    $item = [
        'id' => (int) $row->id,
        'name' => isset($row->name) ? (string) $row->name : '',
        'event_type' => $event_type,
        'event_type_label' => isset($type_labels[$event_type]) ? $type_labels[$event_type] : $event_type,
        'chapter' => isset($row->chapter) ? (string) $row->chapter : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'image_url' => sc_public_api_media_url(isset($row->image) ? (string) $row->image : ''),
        'registration_url' => sc_public_api_events_url(),
        'dates' => [
            'holding_shamsi' => $holding_shamsi !== '' ? $holding_shamsi : null,
            'holding_gregorian' => !empty($row->holding_date_gregorian) ? (string) $row->holding_date_gregorian : null,
            'start_shamsi' => $start_shamsi !== '' ? $start_shamsi : null,
            'end_shamsi' => $end_shamsi !== '' ? $end_shamsi : null,
            'start_gregorian' => !empty($row->start_date_gregorian) ? (string) $row->start_date_gregorian : null,
            'end_gregorian' => !empty($row->end_date_gregorian) ? (string) $row->end_date_gregorian : null,
        ],
        'event_time' => isset($row->event_time) ? (string) $row->event_time : '',
        'location' => [
            'name' => isset($row->event_location) ? (string) $row->event_location : '',
            'address' => isset($row->event_location_address) ? (string) $row->event_location_address : '',
            'coordinates' => ($lat !== null && $lng !== null) ? [
                'latitude' => $lat,
                'longitude' => $lng,
            ] : null,
        ],
        'age_limit' => [
            'enabled' => !empty($row->has_age_limit),
            'min_age' => isset($row->min_age) ? (int) $row->min_age : null,
            'max_age' => isset($row->max_age) ? (int) $row->max_age : null,
        ],
        'is_upcoming' => $is_upcoming,
        'is_past' => $is_past,
    ];

    if ($detailed) {
        global $wpdb;
        $fields_table = $wpdb->prefix . 'sc_event_fields';
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $fields_table WHERE event_id = %d ORDER BY id ASC",
            (int) $row->id
        ));
        $field_items = [];
        foreach ((array) $fields as $field) {
            $field_items[] = [
                'id' => (int) $field->id,
                'name' => (string) $field->field_name,
                'type' => (string) $field->field_type,
                'options' => !empty($field->field_options) ? (string) $field->field_options : '',
                'is_required' => !empty($field->is_required),
            ];
        }
        $item['registration_fields'] = $field_items;
    }

    return $item;
}
