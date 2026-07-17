<?php
/**
 * Player coach rating template
 *
 * @var object $player sc_members row
 */
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

$member_id = (int) $player->id;
$message = '';
$message_type = 'success';

if (
    isset($_POST['sc_save_coach_rating'])
    && isset($_POST['sc_coach_rating_nonce'])
    && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_coach_rating_nonce'])), 'sc_save_coach_rating')
) {
    $target = [
        'coach_id'   => isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0,
        'course_id'  => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,
        'chapter'    => isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '',
        'group_name' => isset($_POST['group_name']) ? sanitize_text_field(wp_unslash($_POST['group_name'])) : '',
        'coach_role' => isset($_POST['coach_role']) ? sanitize_key(wp_unslash($_POST['coach_role'])) : 'primary',
    ];
    $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? wp_unslash($_POST['comment']) : '';

    $result = sc_coach_rating_save($member_id, $target, $rating, $comment);
    $message = $result['message'] ?? '';
    $message_type = !empty($result['success']) ? 'success' : 'error';
}

$targets = sc_coach_rating_get_rateable_coaches_for_member($member_id);
$ratings_map = sc_coach_rating_get_member_ratings_map($member_id);
$pending_count = sc_coach_rating_member_pending_count($member_id);
$completed_count = count($ratings_map);

$rating_page_url = function_exists('sc_panel_endpoint_url')
    ? sc_panel_endpoint_url('sc-coach-rating')
    : (function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-coach-rating') : '');
?>
<div class="sc-coach-rating-page sc-account-list-page">
    <div class="sc-invoices-page-header sc-coach-rating-page-header">
        <div>
            <h2 class="sc-invoices-page-title">امتیاز به مربیان</h2>
            <p class="sc-invoices-page-desc">با ثبت امتیاز، به بهبود کیفیت آموزش باشگاه کمک می‌کنید.</p>
        </div>
    </div>

    <?php if ($message !== '') : ?>
        <div class="woocommerce-message woocommerce-message--<?php echo esc_attr($message_type); ?> woocommerce-<?php echo esc_attr($message_type); ?> sc-invoices-notice sc-coach-rating-notice">
            <?php echo esc_html($message); ?>
        </div>
    <?php endif; ?>

    <div class="sc-coach-rating-privacy-card">
        <div class="sc-coach-rating-privacy-icon" aria-hidden="true">🔒</div>
        <div class="sc-coach-rating-privacy-body">
            <h3>محیطی امن برای بیان نظر شما</h3>
            <p>
                امتیاز و توضیحاتی که ثبت می‌کنید، مستقیماً در اختیار مربیان قرار نمی‌گیرد و تنها مدیر باشگاه
                برای بهبود کیفیت آموزش به آن‌ها دسترسی دارد. لطفاً با خیال راحت و با صداقت کامل،
                امتیازی را وارد کنید که از نظر شما شایسته مربی است. اگر نکته‌ای برای بهبود دارید،
                آن را بنویسید — بازخورد شما ارزشمند است. از همراهی و مشارکت شما صمیمانه سپاسگزاریم.
            </p>
        </div>
    </div>

    <?php if (empty($targets)) : ?>
        <div class="sc-invoices-empty sc-coach-rating-empty-state">
            <div class="sc-coach-rating-empty-icon" aria-hidden="true">🧑‍🏫</div>
            <p>در حال حاضر مربی فعالی برای امتیازدهی یافت نشد. پس از ثبت‌نام در دوره، مربیان شما در این بخش نمایش داده می‌شوند.</p>
        </div>
    <?php else : ?>
        <div class="sc-coach-rating-quick-stats">
            <div class="sc-coach-rating-quick-stat">
                <span class="sc-coach-rating-quick-stat-value"><?php echo (int) $completed_count; ?></span>
                <span class="sc-coach-rating-quick-stat-label">ثبت‌شده</span>
            </div>
            <div class="sc-coach-rating-quick-stat">
                <span class="sc-coach-rating-quick-stat-value"><?php echo (int) $pending_count; ?></span>
                <span class="sc-coach-rating-quick-stat-label">در انتظار ثبت</span>
            </div>
            <div class="sc-coach-rating-quick-stat">
                <span class="sc-coach-rating-quick-stat-value"><?php echo count($targets); ?></span>
                <span class="sc-coach-rating-quick-stat-label">کل مربیان</span>
            </div>
        </div>

        <div class="sc-coach-rating-cards">
            <?php foreach ($targets as $target) :
                $target_key = sc_coach_rating_target_key($target);
                $existing = $ratings_map[$target_key] ?? null;
                $initials = mb_substr($target['coach_name'], 0, 1);
                ?>
                <article class="sc-coach-rating-card<?php echo $existing ? ' is-completed' : ''; ?>">
                    <div class="sc-coach-rating-card-head">
                        <?php if (!empty($target['coach_photo'])) : ?>
                            <span class="sc-coach-rating-avatar"><img src="<?php echo esc_url($target['coach_photo']); ?>" alt=""></span>
                        <?php else : ?>
                            <span class="sc-coach-rating-avatar sc-coach-rating-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                        <?php endif; ?>
                        <div class="sc-coach-rating-card-meta">
                            <h3 class="sc-coach-rating-card-name"><?php echo esc_html($target['coach_name']); ?></h3>
                            <div class="sc-coach-rating-card-tags">
                                <span class="sc-coach-rating-tag"><?php echo esc_html($target['role_label']); ?></span>
                                <span class="sc-coach-rating-tag is-soft"><?php echo esc_html($target['course_title']); ?></span>
                                <?php if (!empty($target['chapter'])) : ?>
                                    <span class="sc-coach-rating-tag is-soft"><?php echo esc_html($target['chapter']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($existing) : ?>
                        <div class="sc-coach-rating-completed-box">
                            <div class="sc-coach-rating-completed-score">
                                <?php echo sc_coach_rating_render_stars_html((int) $existing->rating); ?>
                                <strong><?php echo esc_html(number_format_i18n((int) $existing->rating)); ?> از ۵</strong>
                            </div>
                            <?php if (!empty($existing->comment)) : ?>
                                <p class="sc-coach-rating-completed-comment"><?php echo esc_html($existing->comment); ?></p>
                            <?php endif; ?>
                            <p class="sc-coach-rating-completed-note">
                                ✅ امتیاز شما ثبت شده است و امکان ویرایش وجود ندارد.
                            </p>
                        </div>
                    <?php else : ?>
                        <form method="post" class="sc-coach-rating-form">
                            <?php wp_nonce_field('sc_save_coach_rating', 'sc_coach_rating_nonce'); ?>
                            <input type="hidden" name="coach_id" value="<?php echo esc_attr((int) $target['coach_id']); ?>">
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((int) $target['course_id']); ?>">
                            <input type="hidden" name="chapter" value="<?php echo esc_attr($target['chapter']); ?>">
                            <input type="hidden" name="group_name" value="<?php echo esc_attr($target['group_name']); ?>">
                            <input type="hidden" name="coach_role" value="<?php echo esc_attr($target['coach_role']); ?>">

                            <div class="sc-coach-rating-stars-input" data-rating-input>
                                <span class="sc-coach-rating-stars-input-label">امتیاز شما</span>
                                <div class="sc-coach-rating-stars-picker" role="radiogroup" aria-label="امتیاز از ۱ تا ۵">
                                    <?php for ($star = 5; $star >= 1; $star--) : ?>
                                        <label class="sc-coach-rating-star-option">
                                            <input type="radio" name="rating" value="<?php echo (int) $star; ?>" required>
                                            <span class="sc-coach-rating-star-btn" data-value="<?php echo (int) $star; ?>">★</span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="sc-coach-rating-comment-field">
                                <label for="comment_<?php echo esc_attr(md5($target_key)); ?>">توضیحات (اختیاری)</label>
                                <textarea
                                    id="comment_<?php echo esc_attr(md5($target_key)); ?>"
                                    name="comment"
                                    rows="3"
                                    class="sc-coach-rating-control"
                                    placeholder="نظر، پیشنهاد یا نکته‌ای که فکر می‌کنید برای بهبود کیفیت آموزش مفید است..."></textarea>
                            </div>

                            <div class="sc-coach-rating-form-actions">
                                <button type="submit" name="sc_save_coach_rating" class="button button-primary sc-coach-rating-btn-primary">
                                    ثبت امتیاز
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-rating-input]').forEach(function (wrap) {
        var picker = wrap.querySelector('.sc-coach-rating-stars-picker');
        if (!picker) return;
        var buttons = Array.prototype.slice.call(picker.querySelectorAll('.sc-coach-rating-star-btn'));
        var inputs = Array.prototype.slice.call(picker.querySelectorAll('input[type="radio"]'));

        function paint(value) {
            buttons.forEach(function (btn) {
                var v = parseInt(btn.getAttribute('data-value'), 10) || 0;
                btn.classList.toggle('is-active', v <= value);
            });
        }

        buttons.forEach(function (btn, index) {
            btn.addEventListener('mouseenter', function () {
                paint(parseInt(btn.getAttribute('data-value'), 10) || 0);
            });
            btn.addEventListener('click', function () {
                if (inputs[index]) {
                    inputs[index].checked = true;
                }
                paint(parseInt(btn.getAttribute('data-value'), 10) || 0);
            });
        });

        picker.addEventListener('mouseleave', function () {
            var checked = picker.querySelector('input[type="radio"]:checked');
            paint(checked ? parseInt(checked.value, 10) : 0);
        });
    });
})();
</script>
