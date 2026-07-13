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

    <div class="sc-panel-section__body sc-panel-faq__body">
        <?php if ($faqs) : ?>
            <div class="sc-panel-faq-list" role="list">
                <?php foreach ($faqs as $i => $faq) : ?>
                    <?php $faq_n = (int) ($i + 1); ?>
                    <article class="sc-panel-faq-item" role="listitem">
                        <button
                            type="button"
                            class="sc-panel-faq-item__question"
                            aria-expanded="false"
                            aria-controls="sc-faq-answer-<?php echo $faq_n; ?>"
                            id="sc-faq-question-<?php echo $faq_n; ?>"
                        >
                            <span class="sc-panel-faq-item__icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M9.5 9.2a2.5 2.5 0 015 0c0 1.8-2.5 1.7-2.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <circle cx="12" cy="16.2" r="1" fill="currentColor"/>
                                </svg>
                            </span>
                            <span class="sc-panel-faq-item__main">
                                <span class="sc-panel-faq-item__label">پرسش <?php echo $faq_n; ?></span>
                                <span class="sc-panel-faq-item__text"><?php echo wp_kses_post($faq->question); ?></span>
                            </span>
                            <span class="sc-panel-faq-item__toggle" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </button>
                        <div
                            class="sc-panel-faq-item__answer"
                            id="sc-faq-answer-<?php echo $faq_n; ?>"
                            role="region"
                            aria-labelledby="sc-faq-question-<?php echo $faq_n; ?>"
                            aria-hidden="true"
                        >
                            <div class="sc-panel-faq-item__answer-panel">
                                <div class="sc-panel-faq-item__answer-inner">
                                    <?php echo wp_kses_post($faq->answer); ?>
                                </div>
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
    var list = document.querySelector('.sc-panel-faq-list');
    if (!list) {
        return;
    }

    function closeItem(item) {
        var btn = item.querySelector('.sc-panel-faq-item__question');
        var answer = item.querySelector('.sc-panel-faq-item__answer');
        item.classList.remove('is-open');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
        if (answer) {
            answer.setAttribute('aria-hidden', 'true');
        }
    }

    function openItem(item) {
        var btn = item.querySelector('.sc-panel-faq-item__question');
        var answer = item.querySelector('.sc-panel-faq-item__answer');
        item.classList.add('is-open');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        if (answer) {
            answer.setAttribute('aria-hidden', 'false');
        }
    }

    list.querySelectorAll('.sc-panel-faq-item__question').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.sc-panel-faq-item');
            if (!item) {
                return;
            }
            var isOpen = item.classList.contains('is-open');
            list.querySelectorAll('.sc-panel-faq-item.is-open').forEach(function (openItem) {
                if (openItem !== item) {
                    closeItem(openItem);
                }
            });
            if (isOpen) {
                closeItem(item);
            } else {
                openItem(item);
            }
        });
    });
});
</script>
