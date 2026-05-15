<?php
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$view_note_id    = isset($_GET['view_note']) ? absint($_GET['view_note']) : 0;
$base_url        = wc_get_account_endpoint_url('sc-private-notes');

/* ---------------------------------------------------------------
 * صفحه‌ی جزئیات یک یادداشت / گفتگو
 * --------------------------------------------------------------- */
if ($view_note_id > 0) {
    $thread = sc_private_notes_get_thread($view_note_id);

    // یادداشت‌های قدیمی (تک‌رکوردی) که از نسخه‌های قبل باقی مانده‌اند
    if (!$thread || (int) $thread->user_id !== (int) $current_user_id) {
        $legacy = sc_private_notes_get($view_note_id);
        if (!$legacy || (int) $legacy->user_id !== (int) $current_user_id) {
            ?>
            <div class="woocommerce-MyAccount-content sc-private-notes-content sc-private-notes-user">
                <div class="sc-pn-empty">
                    <span class="sc-pn-empty-icon" aria-hidden="true"></span>
                    <p class="sc-pn-empty-text">یادداشت موردنظر یافت نشد یا دسترسی به آن ندارید.</p>
                    <a class="sc-pn-btn sc-pn-btn-primary" href="<?php echo esc_url($base_url); ?>">بازگشت به لیست یادداشت‌ها</a>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="woocommerce-MyAccount-content sc-private-notes-content sc-private-notes-user">
            <a href="<?php echo esc_url($base_url); ?>" class="sc-pn-back-link">← بازگشت به لیست یادداشت‌ها</a>

            <div class="sc-pn-detail-card">
                <div class="sc-pn-detail-header">
                    <h2 class="sc-pn-detail-title">جزئیات یادداشت</h2>
                    <span class="sc-pn-detail-meta">
                        <span class="sc-pn-meta-icon" aria-hidden="true">📅</span>
                        <?php echo esc_html(sc_date_shamsi($legacy->created_at, 'l d F Y - H:i')); ?>
                    </span>
                </div>
                <div class="sc-pn-detail-body"><?php echo wp_kses_post(wpautop($legacy->content)); ?></div>
            </div>
        </div>
        <?php
        return;
    }

    // ترد فعال (با چندین پیام)
    $msg_s                  = isset($_GET['msg_s']) ? sanitize_text_field(wp_unslash($_GET['msg_s'])) : '';
    $msg_date_from_shamsi   = isset($_GET['msg_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_from_shamsi'])) : '';
    $msg_date_to_shamsi     = isset($_GET['msg_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_to_shamsi'])) : '';
    $today_gregorian        = current_time('Y-m-d');
    $one_year_ago_gregorian = gmdate('Y-m-d', strtotime('-1 year', strtotime($today_gregorian)));
    $today_shamsi           = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;
    $one_year_ago_shamsi    = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($one_year_ago_gregorian) : $one_year_ago_gregorian;

    $has_active_filter = ($msg_s !== '' || $msg_date_from_shamsi !== '' || $msg_date_to_shamsi !== '');

    if ($msg_date_from_shamsi === '' && $msg_date_to_shamsi === '') {
        $msg_date_from_shamsi = $one_year_ago_shamsi;
        $msg_date_to_shamsi   = $today_shamsi;
    } elseif ($msg_date_from_shamsi === '') {
        $msg_date_from_shamsi = $msg_date_to_shamsi;
    } elseif ($msg_date_to_shamsi === '') {
        $msg_date_to_shamsi = $msg_date_from_shamsi;
    }

    $msg_filter = [];
    if ($msg_s !== '') {
        $msg_filter['search'] = $msg_s;
    }
    if ($msg_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $msg_filter['date_from'] = sc_shamsi_to_gregorian_date($msg_date_from_shamsi);
    }
    if ($msg_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $msg_filter['date_to'] = sc_shamsi_to_gregorian_date($msg_date_to_shamsi);
    }

    $messages           = sc_private_notes_get_thread_messages((int) $thread->id, $msg_filter);
    $detail_clear_url   = add_query_arg('view_note', (int) $thread->id, $base_url);
    $thread_subject     = trim((string) $thread->subject) !== '' ? (string) $thread->subject : ('پرونده #' . (int) $thread->id);
    $thread_updated_at  = isset($thread->updated_at) && $thread->updated_at ? sc_date_shamsi($thread->updated_at, 'Y/m/d - H:i') : '';
    $messages_count     = is_array($messages) ? count($messages) : 0;
    ?>
    <div class="woocommerce-MyAccount-content sc-private-notes-content sc-private-notes-user">
        <a href="<?php echo esc_url($base_url); ?>" class="sc-pn-back-link">← بازگشت به لیست یادداشت‌ها</a>

        <div class="sc-pn-detail-card sc-pn-thread-head-card">
            <div class="sc-pn-detail-header">
                <h2 class="sc-pn-detail-title"><?php echo esc_html($thread_subject); ?></h2>
                <div class="sc-pn-detail-meta-row">
                    <?php if ($thread_updated_at !== '') : ?>
                        <span class="sc-pn-detail-meta">
                            <span class="sc-pn-meta-icon" aria-hidden="true">🕒</span>
                            آخرین بروزرسانی: <?php echo esc_html($thread_updated_at); ?>
                        </span>
                    <?php endif; ?>
                    <span class="sc-pn-detail-meta">
                        <span class="sc-pn-meta-icon" aria-hidden="true">💬</span>
                        <?php echo (int) $messages_count; ?> پیام
                    </span>
                </div>
            </div>
        </div>

        <form method="get" action="<?php echo esc_url($base_url); ?>" class="sc-pn-filter-card">
            <input type="hidden" name="view_note" value="<?php echo (int) $thread->id; ?>">
            <div class="sc-pn-filter-grid">
                <div class="sc-pn-field sc-pn-field-search">
                    <label for="sc-pn-msg-s" class="sc-pn-label">جستجو در پیام‌ها</label>
                    <div class="sc-pn-input-wrap">
                        <span class="sc-pn-input-icon" aria-hidden="true">🔍</span>
                        <input type="search" name="msg_s" id="sc-pn-msg-s" class="sc-pn-input"
                               value="<?php echo esc_attr($msg_s); ?>"
                               placeholder="کلمه یا عبارت...">
                    </div>
                </div>
                <div class="sc-pn-field">
                    <label class="sc-pn-label">از تاریخ</label>
                    <input type="text" name="msg_date_from_shamsi" class="sc-pn-input persian-date-input"
                           placeholder="از تاریخ"
                           value="<?php echo esc_attr($msg_date_from_shamsi); ?>" readonly>
                </div>
                <div class="sc-pn-field">
                    <label class="sc-pn-label">تا تاریخ</label>
                    <input type="text" name="msg_date_to_shamsi" class="sc-pn-input persian-date-input"
                           placeholder="تا تاریخ"
                           value="<?php echo esc_attr($msg_date_to_shamsi); ?>" readonly>
                </div>
            </div>
            <div class="sc-pn-filter-actions">
                <button type="submit" class="sc-pn-btn sc-pn-btn-primary">
                    <span aria-hidden="true">🔍</span> اعمال فیلتر
                </button>
                <a href="<?php echo esc_url($detail_clear_url); ?>" class="sc-pn-btn sc-pn-btn-ghost">
                    پاک کردن فیلتر
                </a>
            </div>
        </form>

        <?php if (empty($messages)) : ?>
            <div class="sc-pn-empty sc-pn-empty-inline">
                <span class="sc-pn-empty-icon" aria-hidden="true"></span>
                <p class="sc-pn-empty-text">پیامی با این فیلترها یافت نشد.</p>
            </div>
        <?php else : ?>
            <div class="sc-pn-thread-list">
                <?php foreach ((array) $messages as $msg) :
                    $attachment_ids = !empty($msg->attachment_ids) ? json_decode($msg->attachment_ids, true) : [];
                    $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : [];
                ?>
                    <article class="sc-pn-message">
                        <div class="sc-pn-message-header">
                            <span class="sc-pn-message-avatar" aria-hidden="true">📌</span>
                            <div class="sc-pn-message-meta">
                                <span class="sc-pn-message-author">یادداشت باشگاه</span>
                                <span class="sc-pn-message-date"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d - H:i')); ?></span>
                            </div>
                        </div>
                        <div class="sc-pn-message-body"><?php echo wp_kses_post(wpautop($msg->content)); ?></div>

                        <?php if (!empty($attachment_ids)) : ?>
                            <div class="sc-pn-message-attachments">
                                <div class="sc-pn-attachments-title">
                                    <span aria-hidden="true">📎</span> پیوست‌ها
                                </div>
                                <ul class="sc-pn-attachments-list">
                                    <?php foreach ($attachment_ids as $aid) :
                                        if ($aid <= 0) {
                                            continue;
                                        }
                                        $att_title = get_the_title($aid) ?: ('فایل #' . $aid);
                                    ?>
                                        <li>
                                            <a class="sc-pn-attachment-link"
                                               href="<?php echo esc_url(sc_private_notes_attachment_download_url($aid, (int) $msg->id)); ?>"
                                               target="_blank" rel="noopener">
                                                <span class="sc-pn-attachment-icon" aria-hidden="true">📥</span>
                                                <span class="sc-pn-attachment-name"><?php echo esc_html($att_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const interval = setInterval(function() {
            const el = document.querySelector('.sc-private-notes-user .sc-pn-detail-title');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                clearInterval(interval);
            }
        }, 100);
    });
    </script>
    <?php
    return;
}

/* ---------------------------------------------------------------
 * صفحه‌ی لیست یادداشت‌ها
 * --------------------------------------------------------------- */
$per_page = 12;
$page     = isset($_GET['note_page']) ? max(1, absint($_GET['note_page'])) : 1;
$note_s   = isset($_GET['note_s']) ? sanitize_text_field(wp_unslash($_GET['note_s'])) : '';

$list_result = sc_private_notes_query_user_threads($current_user_id, [
    'search'   => $note_s,
    'per_page' => $per_page,
    'page'     => $page,
]);
$total_pages = max(1, (int) $list_result['total_pages']);
if ($page > $total_pages) {
    $page = $total_pages;
    $list_result = sc_private_notes_query_user_threads($current_user_id, [
        'search'   => $note_s,
        'per_page' => $per_page,
        'page'     => $page,
    ]);
}
$notes       = $list_result['rows'];
$total_count = isset($list_result['total']) ? (int) $list_result['total'] : count((array) $notes);
?>
<div class="woocommerce-MyAccount-content sc-private-notes-content sc-private-notes-user">
    <div class="sc-pn-page-header">
        <div class="sc-pn-page-titles">
            <h2 class="sc-pn-page-title">یادداشت‌های من</h2>
            <p class="sc-pn-page-subtitle">گفتگوها و یادداشت‌های خصوصی شما با کادر باشگاه</p>
        </div>
        <?php if ($total_count > 0) : ?>
            <span class="sc-pn-count-badge"><?php echo (int) $total_count; ?> پرونده</span>
        <?php endif; ?>
    </div>

    <form method="get" action="<?php echo esc_url($base_url); ?>" class="sc-pn-filter-card sc-pn-filter-card--list">
        <div class="sc-pn-filter-grid sc-pn-filter-grid--single">
            <div class="sc-pn-field sc-pn-field-search">
                <label for="sc-pn-note-s" class="sc-pn-label">جستجو در پرونده‌ها</label>
                <div class="sc-pn-input-wrap">
                    <span class="sc-pn-input-icon" aria-hidden="true">🔍</span>
                    <input type="search" name="note_s" id="sc-pn-note-s" class="sc-pn-input"
                           value="<?php echo esc_attr($note_s); ?>"
                           placeholder="جستجو در عنوان پرونده یا متن پیام‌ها...">
                </div>
            </div>
        </div>
        <div class="sc-pn-filter-actions">
            <button type="submit" class="sc-pn-btn sc-pn-btn-primary">
                <span aria-hidden="true">🔍</span> جستجو
            </button>
            <?php if ($note_s !== '') : ?>
                <a href="<?php echo esc_url(remove_query_arg(['note_s', 'note_page'], $base_url)); ?>" class="sc-pn-btn sc-pn-btn-ghost">
                    پاک کردن
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($notes)) : ?>
        <div class="sc-pn-empty">
            <span class="sc-pn-empty-icon" aria-hidden="true"></span>
            <p class="sc-pn-empty-text">
                <?php echo esc_html($note_s !== '' ? 'موردی با این جستجو یافت نشد.' : 'هنوز یادداشتی برای شما ثبت نشده است.'); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="sc-pn-threads-grid">
            <?php foreach ($notes as $note) :
                $note_subject       = trim((string) $note->subject) !== '' ? (string) $note->subject : ('پرونده #' . (int) $note->id);
                $note_updated       = isset($note->updated_at) && $note->updated_at ? sc_date_shamsi($note->updated_at, 'Y/m/d H:i') : '';
                $note_view_url      = add_query_arg(['view_note' => (int) $note->id], $base_url);
                $note_message_count = isset($note->messages_count) ? (int) $note->messages_count : 0;
                $note_excerpt       = '';
                if (isset($note->last_message) && $note->last_message !== '') {
                    $note_excerpt = wp_strip_all_tags((string) $note->last_message);
                    $note_excerpt = function_exists('wp_trim_words') ? wp_trim_words($note_excerpt, 18, '…') : mb_substr($note_excerpt, 0, 120);
                }
            ?>
                <article class="sc-pn-thread-card">
                    <div class="sc-pn-thread-card-head">
                        <span class="sc-pn-thread-card-icon" aria-hidden="true">📁</span>
                        <h3 class="sc-pn-thread-card-title">
                            <a href="<?php echo esc_url($note_view_url); ?>"><?php echo esc_html($note_subject); ?></a>
                        </h3>
                    </div>

                    <?php if ($note_excerpt !== '') : ?>
                        <p class="sc-pn-thread-card-excerpt"><?php echo esc_html($note_excerpt); ?></p>
                    <?php endif; ?>

                    <div class="sc-pn-thread-card-meta">
                        <?php if ($note_updated !== '') : ?>
                            <span class="sc-pn-thread-card-meta-item">
                                <span aria-hidden="true">🕒</span> <?php echo esc_html($note_updated); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($note_message_count > 0) : ?>
                            <span class="sc-pn-thread-card-meta-item">
                                <span aria-hidden="true">💬</span> <?php echo (int) $note_message_count; ?> پیام
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="sc-pn-thread-card-actions">
                        <a class="sc-pn-btn sc-pn-btn-primary sc-pn-btn-block" href="<?php echo esc_url($note_view_url); ?>">
                            مشاهده گفتگو
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="sc-pn-pagination" aria-label="صفحه‌بندی یادداشت‌ها">
                <?php for ($p = 1; $p <= $total_pages; $p++) :
                    $p_url = add_query_arg(['note_page' => $p], $base_url);
                    if ($note_s !== '') {
                        $p_url = add_query_arg('note_s', $note_s, $p_url);
                    }
                ?>
                    <?php if ($p === $page) : ?>
                        <span class="sc-pn-pagination-item current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a class="sc-pn-pagination-item" href="<?php echo esc_url($p_url); ?>"><?php echo (int) $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-private-notes-user .sc-pn-page-title');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
