<?php
/**
 * UI helpers — فیلتر و لیست (مثل لیست بازیکنان)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $classes
 * @return string
 */
function sc_members_list_ui_body_class($classes) {
    if (!is_admin()) {
        return $classes;
    }
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $tarddod_pages = ['sc-tarddod-register', 'sc-tarddod-records', 'sc-tarddod-sessions', 'sc-tarddod-session-add'];
    if (in_array($page, $tarddod_pages, true)) {
        $classes .= ' sc-members-list-ui-root';
    }
    return $classes;
}
add_filter('admin_body_class', 'sc_members_list_ui_body_class');

/**
 * @param array $args {
 *   @type string $page
 *   @type string $panel_id
 *   @type string $toggle_id
 *   @type string $clear_url
 *   @type int    $active_filters_count
 *   @type bool   $filters_open
 *   @type string $form_class
 * }
 */
function sc_members_list_filter_card_open(array $args) {
    $page = (string) ($args['page'] ?? '');
    $panel_id = (string) ($args['panel_id'] ?? 'sc-list-filters-panel');
    $toggle_id = (string) ($args['toggle_id'] ?? 'sc-list-filters-toggle');
    $clear_url = (string) ($args['clear_url'] ?? '');
    $active = max(0, (int) ($args['active_filters_count'] ?? 0));
    $filters_open = !empty($args['filters_open']) || $active > 0;
    $form_class = trim('sc-members-list-filters-panel ' . (string) ($args['form_class'] ?? ''));
    ?>
    <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-members-list-filters-toggle"
                    id="<?php echo esc_attr($toggle_id); ?>"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr($panel_id); ?>">
                <span class="sc-members-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active > 0) : ?>
                    <span class="sc-members-list-filters-badge"><?php echo (int) $active; ?></span>
                <?php endif; ?>
                <span class="sc-members-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active > 0 && $clear_url !== '') : ?>
                <a href="<?php echo esc_url($clear_url); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
        <form method="get" action="" class="<?php echo esc_attr($form_class); ?>" id="<?php echo esc_attr($panel_id); ?>"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="<?php echo esc_attr($page); ?>">
    <?php
}

/**
 * @param array $args
 */
function sc_members_list_filter_card_close(array $args = []) {
    $clear_url = (string) ($args['clear_url'] ?? '');
    $submit_label = (string) ($args['submit_label'] ?? 'اعمال فیلتر');
    ?>
            <div class="sc-members-list-filters-actions">
                <input type="submit" class="button button-primary" value="<?php echo esc_attr($submit_label); ?>">
                <?php if ($clear_url !== '') : ?>
                    <a href="<?php echo esc_url($clear_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
}

/**
 * @param string $toggle_id
 * @param string $panel_id
 */
function sc_members_list_filter_toggle_script($toggle_id, $panel_id) {
    ?>
    <script type="text/javascript">
    jQuery(function ($) {
        var $toggle = $('#<?php echo esc_js($toggle_id); ?>');
        var $panel = $('#<?php echo esc_js($panel_id); ?>');
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
    });
    </script>
    <?php
}

/**
 * @param array  $base_args
 * @param int    $paged
 * @param int    $total_pages
 */
function sc_members_list_render_pagination(array $base_args, $paged, $total_pages) {
    $paged = max(1, (int) $paged);
    $total_pages = max(1, (int) $total_pages);
    if ($total_pages <= 1) {
        return;
    }
    echo '<div class="tablenav"><div class="tablenav-pages">';
    echo paginate_links([
        'base'      => add_query_arg(array_merge($base_args, ['paged' => '%#%']), admin_url('admin.php')),
        'format'    => '',
        'current'   => $paged,
        'total'     => $total_pages,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]);
    echo '</div></div>';
}
