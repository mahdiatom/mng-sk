<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$faq_table = $wpdb->prefix . 'sc_faq';
$faqs = $wpdb->get_results("SELECT * FROM $faq_table ORDER BY id ASC");
?>
<div class="sc-panel-section sc-panel-faq">
    <?php
    if (function_exists('sc_panel_render_section_hero')) {
        sc_panel_render_section_hero(
            'سوالات متداول باشگاه',
            'پاسخ پرسش‌های رایج درباره خدمات و قوانین باشگاه را در این بخش ببینید.',
            'faq'
        );
    }
    ?>

    <div class="sc-panel-section__body">
        <?php if ($faqs) : ?>
            <div class="sc-panel-faq-list" role="list">
                <?php foreach ($faqs as $i => $faq) : ?>
                    <article class="sc-panel-faq-item" role="listitem">
                        <button type="button" class="sc-panel-faq-item__question" aria-expanded="false">
                            <span class="sc-panel-faq-item__index"><?php echo (int) ($i + 1); ?></span>
                            <span class="sc-panel-faq-item__text"><?php echo wp_kses_post($faq->question); ?></span>
                            <span class="sc-panel-faq-item__toggle" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </span>
                        </button>
                        <div class="sc-panel-faq-item__answer" hidden>
                            <div class="sc-panel-faq-item__answer-inner">
                                <?php echo wp_kses_post($faq->answer); ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="sc-panel-empty">
                <span class="sc-panel-empty__icon" aria-hidden="true"></span>
                <p>هنوز هیچ پرسشی برای باشگاه ثبت نشده است.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sc-panel-faq-item__question').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.sc-panel-faq-item');
            var answer = item ? item.querySelector('.sc-panel-faq-item__answer') : null;
            if (!item || !answer) {
                return;
            }
            var isOpen = item.classList.contains('is-open');
            document.querySelectorAll('.sc-panel-faq-item.is-open').forEach(function (openItem) {
                if (openItem === item) {
                    return;
                }
                openItem.classList.remove('is-open');
                var openBtn = openItem.querySelector('.sc-panel-faq-item__question');
                var openAnswer = openItem.querySelector('.sc-panel-faq-item__answer');
                if (openBtn) {
                    openBtn.setAttribute('aria-expanded', 'false');
                }
                if (openAnswer) {
                    openAnswer.hidden = true;
                }
            });
            if (isOpen) {
                item.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                answer.hidden = true;
            } else {
                item.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                answer.hidden = false;
            }
        });
    });
});
</script>
