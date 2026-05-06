<?php
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$view_note_id = isset($_GET['view_note']) ? absint($_GET['view_note']) : 0;
$base_url = wc_get_account_endpoint_url('sc-private-notes');

if ($view_note_id > 0) {
    $note = sc_private_notes_get($view_note_id);
    if (!$note || (int) $note->user_id !== (int) $current_user_id) {
        echo '<div class="woocommerce-error">یادداشت یافت نشد.</div>';
    } else {
        ?>
        <div class="woocommerce-MyAccount-content sc-private-notes-content">
            <div class="sc-private-note-detail-card">
                <a href="<?php echo esc_url($base_url); ?>" class="sc-private-note-back-link">← بازگشت به لیست</a>
                <h2 class="sc-private-note-detail-title"><?php echo esc_html($note->title); ?></h2>
                <p class="sc-private-note-detail-meta"><?php echo esc_html(sc_date_shamsi($note->created_at, 'Y/m/d - H:i')); ?></p>
                <div class="sc-private-note-detail-body"><?php echo wp_kses_post(wpautop($note->content)); ?></div>
                <?php
                $attachment_ids = !empty($note->attachment_ids) ? json_decode($note->attachment_ids, true) : [];
                $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : [];
                if (!empty($attachment_ids)) :
                ?>
                    <div class="sc-private-note-detail-attachments">
                        <strong>پیوست‌ها:</strong>
                        <ul>
                            <?php foreach ($attachment_ids as $aid) : ?>
                                <?php if ($aid <= 0) continue; ?>
                                <?php $url = sc_private_notes_attachment_download_url($aid, (int) $note->id); ?>
                                <li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('فایل #' . $aid)); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return;
    }
}

$per_page = 12;
$page = isset($_GET['note_page']) ? max(1, absint($_GET['note_page'])) : 1;
$total = sc_private_notes_count_user_notes($current_user_id);
$notes = sc_private_notes_get_user_notes($current_user_id, [
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page,
]);
$total_pages = max(1, (int) ceil($total / $per_page));
?>
<div class="woocommerce-MyAccount-content sc-private-notes-content">
    <h2 class="sc-private-notes-heading">یادداشت‌های من</h2>
    <?php if (empty($notes)) : ?>
        <div class="sc-private-notes-empty">هنوز یادداشتی برای شما ثبت نشده است.</div>
    <?php else : ?>
        <div class="sc-private-notes-grid">
            <?php foreach ($notes as $note) : ?>
                <article class="sc-private-note-card">
                    <h3 class="sc-private-note-card-title"><?php echo esc_html($note->title); ?></h3>
                    <div class="sc-private-note-card-date"><?php echo esc_html(sc_date_shamsi($note->created_at, 'Y/m/d')); ?></div>
                    <div class="sc-private-note-card-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($note->content), 25)); ?></div>
                    <a class="sc-private-note-card-link" href="<?php echo esc_url(add_query_arg('view_note', (int) $note->id, $base_url)); ?>">مشاهده جزئیات</a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($total_pages > 1) : ?>
            <nav class="sc-private-notes-pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                    <?php if ($p === $page) : ?>
                        <span class="current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url(add_query_arg('note_page', $p, $base_url)); ?>"><?php echo (int) $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
