<?php
if (!defined('ABSPATH')) {
    exit;
}

$ws_days = isset($ws_days) && is_array($ws_days) ? $ws_days : [];
$ws_cells = isset($ws_cells) && is_array($ws_cells) ? $ws_cells : [];
$ws_show_meta = !empty($ws_show_meta);
$ws_rows = [];

foreach (range(1, 7) as $day_num) {
    $day_label = isset($ws_days[$day_num]) ? (string) $ws_days[$day_num] : (string) $day_num;
    if (empty($ws_cells[$day_num])) {
        continue;
    }
    foreach ($ws_cells[$day_num] as $slot) {
        $ws_rows[] = [
            'day' => $day_label,
            'time' => trim((string) ($slot['start'] ?? '') . ' – ' . (string) ($slot['end'] ?? '')),
            'title' => (string) ($slot['title'] ?? ''),
            'chapter' => (string) ($slot['chapter'] ?? ''),
            'coach_name' => (string) ($slot['coach_name'] ?? ''),
            'group_name' => (string) ($slot['group_name'] ?? ''),
            'type' => (string) ($slot['type'] ?? ''),
        ];
    }
}
?>
<?php if (empty($ws_rows)) : ?>
    <p class="sc-weekly-schedule-empty">برنامه هفتگی‌ای ثبت نشده است.</p>
<?php else : ?>
    <table class="sc-weekly-schedule-list-table">
        <thead>
            <tr>
                <th class="col-day">روز</th>
                <th class="col-time">ساعت</th>
                <?php if (!$ws_show_meta) : ?>
                    <th class="col-title">عنوان</th>
                <?php else : ?>
                    <th class="col-chapter">شعبه</th>
                    <th class="col-coach">مربی</th>
                    <th class="col-group">گروه</th>
                    <th class="col-type">نوع</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ws_rows as $row) : ?>
                <tr>
                    <td class="col-day"><?php echo esc_html($row['day']); ?></td>
                    <td class="col-time"><?php echo esc_html($row['time']); ?></td>
                    <?php if (!$ws_show_meta) : ?>
                        <td class="col-title"><?php echo esc_html($row['title'] !== '' ? $row['title'] : '—'); ?></td>
                    <?php else : ?>
                        <td class="col-chapter"><?php echo esc_html($row['chapter'] !== '' ? $row['chapter'] : '—'); ?></td>
                        <td class="col-coach"><?php echo esc_html($row['coach_name'] !== '' ? $row['coach_name'] : '—'); ?></td>
                        <td class="col-group"><?php echo esc_html($row['group_name'] !== '' ? $row['group_name'] : '—'); ?></td>
                        <td class="col-type"><?php echo esc_html($row['type'] !== '' ? $row['type'] : '—'); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
