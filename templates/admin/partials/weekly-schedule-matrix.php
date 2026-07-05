<?php
if (!defined('ABSPATH')) {
    exit;
}

$ws_days = isset($ws_days) && is_array($ws_days) ? $ws_days : [];
$ws_cells = isset($ws_cells) && is_array($ws_cells) ? $ws_cells : [];
$ws_show_meta = !empty($ws_show_meta);
$ws_has_any = false;

foreach (range(1, 7) as $day_num) {
    if (!empty($ws_cells[$day_num])) {
        $ws_has_any = true;
        break;
    }
}
?>
<?php if (!$ws_has_any) : ?>
    <p class="sc-weekly-schedule-empty">برنامه هفتگی‌ای ثبت نشده است.</p>
<?php else : ?>
    <div class="sc-weekly-schedule-table-wrap">
        <table class="sc-weekly-schedule-table">
            <thead>
                <tr>
                    <?php foreach ($ws_days as $day_label) : ?>
                        <th><?php echo esc_html($day_label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach (range(1, 7) as $day_num) : ?>
                        <td>
                            <?php if (empty($ws_cells[$day_num])) : ?>
                                <span class="sc-weekly-schedule-dash">—</span>
                            <?php else : ?>
                                <?php foreach ($ws_cells[$day_num] as $slot) : ?>
                                    <div class="sc-weekly-schedule-slot">
                                        <div class="sc-weekly-schedule-slot-time">
                                            <?php echo esc_html(($slot['start'] ?? '') . ' – ' . ($slot['end'] ?? '')); ?>
                                        </div>
                                        <?php if (!$ws_show_meta && !empty($slot['title'])) : ?>
                                            <div class="sc-weekly-schedule-slot-title"><?php echo esc_html($slot['title']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ws_show_meta && !empty($slot['chapter'])) : ?>
                                            <div class="sc-weekly-schedule-slot-meta">شعبه: <?php echo esc_html($slot['chapter']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ws_show_meta && !empty($slot['coach_name'])) : ?>
                                            <div class="sc-weekly-schedule-slot-meta">مربی: <?php echo esc_html($slot['coach_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ws_show_meta && !empty($slot['group_name'])) : ?>
                                            <div class="sc-weekly-schedule-slot-meta">گروه: <?php echo esc_html($slot['group_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ws_show_meta && !empty($slot['type'])) : ?>
                                            <span class="sc-weekly-schedule-slot-badge"><?php echo esc_html($slot['type']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>
