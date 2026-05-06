<?php
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$view_note_id = isset($_GET['view_note']) ? absint($_GET['view_note']) : 0;
$base_url = wc_get_account_endpoint_url('sc-private-notes');

if ($view_note_id > 0) {
    $thread = sc_private_notes_get_thread($view_note_id);
    if (!$thread || (int) $thread->user_id !== (int) $current_user_id) {
        $legacy = sc_private_notes_get($view_note_id);
        if (!$legacy || (int) $legacy->user_id !== (int) $current_user_id) {
            echo '<div class="woocommerce-error">یادداشت یافت نشد.</div>';
            return;
        }
        ?>
        <div class="woocommerce-MyAccount-content sc-private-notes-content">
            <div class="sc-private-note-detail-card">
                <a href="<?php echo esc_url($base_url); ?>" class="sc-private-note-back-link">← بازگشت به لیست</a>
                <p class="sc-private-note-detail-meta"><?php echo esc_html(sc_date_shamsi($legacy->created_at, 'Y/m/d - H:i')); ?></p>
                <div class="sc-private-note-detail-body"><?php echo wp_kses_post(wpautop($legacy->content)); ?></div>
            </div>
        </div>
        <?php
    } else {
        $messages = sc_private_notes_get_thread_messages((int) $thread->id);
        ?>
        <div class="woocommerce-MyAccount-content sc-private-notes-content">
            <div class="sc-private-note-detail-card">
                <a href="<?php echo esc_url($base_url); ?>" class="sc-private-note-back-link">← بازگشت به لیست</a>
                <?php foreach ((array) $messages as $msg) : ?>
                    <p class="sc-private-note-detail-meta"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d - H:i')); ?></p>
                    <div class="sc-private-note-detail-body"><?php echo wp_kses_post(wpautop($msg->content)); ?></div>
                    <?php $attachment_ids = !empty($msg->attachment_ids) ? json_decode($msg->attachment_ids, true) : []; $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : []; ?>
                    <?php if (!empty($attachment_ids)) : ?><div class="sc-private-note-detail-attachments"><strong>پیوست‌ها:</strong><ul>
                        <?php foreach ($attachment_ids as $aid) : if ($aid <= 0) continue; ?>
                            <li><a href="<?php echo esc_url(sc_private_notes_attachment_download_url($aid, (int) $msg->id)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('فایل #' . $aid)); ?></a></li>
                        <?php endforeach; ?>
                    </ul></div><?php endif; ?>
                    <hr>
                <?php endforeach; ?>
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
    <h2 class="sc-private-notes-heading">پرونده‌های یادداشت من</h2>
    <?php if (empty($notes)) : ?>
        <div class="sc-private-notes-empty">هنوز یادداشتی برای شما ثبت نشده است.</div>
    <?php else : ?>
        <table class="shop_table shop_table_responsive my_account_orders">
            <thead><tr><th>پرونده</th><th>آخرین بروزرسانی</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($notes as $note) : ?>
                <tr>
                    <td><?php echo esc_html(trim((string) $note->subject) !== '' ? (string) $note->subject : ('پرونده #' . (int) $note->id)); ?></td>
                    <td><?php echo esc_html(sc_date_shamsi($note->updated_at, 'Y/m/d H:i')); ?></td>
                    <td><a class="button" href="<?php echo esc_url(add_query_arg('view_note', (int) $note->id, $base_url)); ?>">مشاهده گفتگو</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
