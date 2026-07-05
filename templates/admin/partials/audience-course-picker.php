<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var string $picker_id @var string $input_name @var string $wrapper_class @var string $description @var string $placeholder @var array $options @var array $selected_items @var bool $store_course_id_only */
?>
<div class="sc-audience-course-picker <?php echo esc_attr($wrapper_class); ?>"
     id="<?php echo esc_attr($picker_id); ?>"
     data-input-name="<?php echo esc_attr($input_name); ?>"
     data-store-course-id-only="<?php echo $store_course_id_only ? '1' : '0'; ?>">
    <div class="sc-audience-course-tags" aria-live="polite">
        <?php if (empty($selected_items)) : ?>
            <span class="sc-audience-course-tags-empty">هنوز دوره‌ای انتخاب نشده است.</span>
        <?php else : ?>
            <?php foreach ($selected_items as $item) : ?>
                <span class="sc-audience-course-tag"
                      data-value="<?php echo esc_attr((string) $item['picker_value']); ?>"
                      data-store-value="<?php echo esc_attr((string) $item['value']); ?>">
                    <span class="sc-audience-course-tag__label"><?php echo esc_html((string) $item['label']); ?></span>
                    <button type="button" class="sc-audience-course-tag__remove" aria-label="حذف">&times;</button>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="sc-searchable-dropdown sc-audience-course-dropdown">
        <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
            <span class="sc-dropdown-placeholder"><?php echo esc_html($placeholder); ?></span>
            <span class="sc-dropdown-arrow">▼</span>
        </div>
        <div class="sc-dropdown-menu" role="listbox">
            <div class="sc-dropdown-search">
                <input type="text" class="sc-search-input sc-audience-course-search" placeholder="جستجوی نام دوره، شعبه یا گروه..." autocomplete="off">
            </div>
            <div class="sc-dropdown-options">
                <?php
                $display_count = 0;
                $max_display = 12;
                foreach ($options as $opt) :
                    $option_value = (string) ($opt['value'] ?? '');
                    if ($option_value === '') {
                        continue;
                    }
                    $option_label = (string) ($opt['label'] ?? $option_value);
                    $search_blob = (string) ($opt['search'] ?? strtolower($option_label));
                    $course_type = (string) ($opt['course_type'] ?? 'group');
                    $store_value = $store_course_id_only
                        ? (string) (int) ($opt['course_id'] ?? absint($option_value))
                        : $option_value;
                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                    $display_count++;
                    ?>
                    <div class="sc-dropdown-option sc-audience-course-option <?php echo esc_attr($display_class); ?>"
                         data-value="<?php echo esc_attr($option_value); ?>"
                         data-store-value="<?php echo esc_attr($store_value); ?>"
                         data-search="<?php echo esc_attr($search_blob); ?>"
                         data-label="<?php echo esc_attr($option_label); ?>"
                         data-course-type="<?php echo esc_attr($course_type); ?>">
                        <span class="sc-audience-course-option__label"><?php echo esc_html($option_label); ?></span>
                        <span class="sc-audience-course-option__type"><?php echo esc_html(function_exists('sc_attendance_course_type_label') ? sc_attendance_course_type_label($course_type) : ''); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="sc-audience-course-hidden-inputs">
        <?php foreach ($selected_items as $item) : ?>
            <input type="hidden" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr((string) $item['value']); ?>">
        <?php endforeach; ?>
    </div>

    <?php if ($description !== '') : ?>
        <p class="description sc-audience-course-picker__desc"><?php echo esc_html($description); ?></p>
    <?php endif; ?>
</div>
