<?php
if (!defined('ABSPATH')) {
    exit;
}

$members = isset($members) && is_array($members) ? $members : [];
$wrap_id = isset($args['wrap_id']) ? sanitize_html_class((string) $args['wrap_id']) : 'sc-audience-preview-add-wrap';
$dropdown_id = isset($args['dropdown_id']) ? sanitize_html_class((string) $args['dropdown_id']) : 'sc-audience-preview-add-dropdown';
$options_id = isset($args['options_id']) ? sanitize_html_class((string) $args['options_id']) : 'sc-audience-preview-add-options';
$hidden_inputs_id = isset($args['hidden_inputs_id']) ? sanitize_html_class((string) $args['hidden_inputs_id']) : 'sc-audience-preview-add-inputs';
$mode = isset($args['mode']) ? sanitize_key((string) $args['mode']) : 'member';
$label = isset($args['label']) ? (string) $args['label'] : 'افزودن کاربر به لیست پیش‌نمایش';
$description = isset($args['description']) ? (string) $args['description'] : '';
?>
<div class="sc-audience-preview-add-wrap sc-filter-block"
     id="<?php echo esc_attr($wrap_id); ?>"
     data-preview-add-mode="<?php echo esc_attr($mode); ?>"
     data-hidden-inputs-id="<?php echo esc_attr($hidden_inputs_id); ?>"
     style="display:none;">
    <label class="sc-audience-preview-add-label"><?php echo esc_html($label); ?></label>
    <?php if ($description !== '') : ?>
        <p class="description sc-audience-preview-add-desc"><?php echo esc_html($description); ?></p>
    <?php endif; ?>
    <div class="sc-users-member-dropdown sc-audience-preview-add-dropdown" id="<?php echo esc_attr($dropdown_id); ?>">
        <button type="button" class="button sc-users-dropdown-toggle">جستجو و انتخاب کاربر...</button>
        <div class="sc-users-dropdown-menu" style="display:none;">
            <input type="text" class="sc-users-search-input sc-audience-preview-add-search" placeholder="جستجو بر اساس نام یا کد ملی...">
            <div class="sc-users-dropdown-options" id="<?php echo esc_attr($options_id); ?>">
                <?php foreach ($members as $member) :
                    $name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
                    $display = $name !== '' ? $name : ('کاربر #' . (int) $member->id);
                    $national_id = (string) ($member->national_id ?: (string) (int) $member->id);
                    $search = strtolower($display . ' ' . $national_id);
                    $type_label = ((string) ($member->member_type ?? '') === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
                    $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
                    ?>
                    <div class="sc-users-dropdown-option"
                         data-id="<?php echo (int) $member->id; ?>"
                         data-label="<?php echo esc_attr($display . ' - ' . $national_id); ?>"
                         data-search="<?php echo esc_attr($search); ?>"
                         data-name="<?php echo esc_attr($display); ?>"
                         data-national-id="<?php echo esc_attr($national_id); ?>"
                         data-type="<?php echo esc_attr($type_label); ?>"
                         data-team="<?php echo esc_attr((string) ($member->team_player ?? '-')); ?>"
                         data-level="<?php echo esc_attr((string) ($member->skill_level ?? '-')); ?>"
                         data-status="<?php echo esc_attr($status_label); ?>">
                        <?php echo esc_html($display . ' - ' . $national_id); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div id="<?php echo esc_attr($hidden_inputs_id); ?>" class="sc-audience-preview-add-inputs"></div>
</div>
