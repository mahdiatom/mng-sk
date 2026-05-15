<?php
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();
$view_note_id = isset($_GET['view_note']) ? absint($_GET['view_note']) : 0;
$base_url = wc_get_account_endpoint_url('sc-private-notes');

/** همان استایل کادر فیلتر/جدول صفحه «حضور و غیاب من» */
$pn_filter_form_style = 'margin:20px 0; background:#fff; padding:15px; border:1px solid #ddd; border-radius:6px;';
$pn_table_wrap_style = 'overflow-x:auto; background:#fff; padding:15px; border:1px solid #ddd; border-radius:6px;';

if ($view_note_id > 0) {
    $thread = sc_private_notes_get_thread($view_note_id);
    if (!$thread || (int) $thread->user_id !== (int) $current_user_id) {
        $legacy = sc_private_notes_get($view_note_id);
        if (!$legacy || (int) $legacy->user_id !== (int) $current_user_id) {
            echo '<div class="woocommerce-error">یادداشت یافت نشد.</div>';
            return;
        }
        ?>
        <div class="wrap wrap_attendace_user sc-private-notes-user">
            <a href="<?php echo esc_url($base_url); ?>" class="sc_button">بازگشت به لیست</a>
            <div style="<?php echo esc_attr($pn_table_wrap_style); ?> margin-top:15px;">
                <p><strong>تاریخ:</strong> <?php echo esc_html(sc_date_shamsi($legacy->created_at, 'Y/m/d - H:i')); ?></p>
                <div class="sc-private-note-detail-body"><?php echo wp_kses_post(wpautop($legacy->content)); ?></div>
            </div>
        </div>
        <?php
    } else {
        $msg_s = isset($_GET['msg_s']) ? sanitize_text_field(wp_unslash($_GET['msg_s'])) : '';
        $msg_date_from_shamsi = isset($_GET['msg_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_from_shamsi'])) : '';
        $msg_date_to_shamsi = isset($_GET['msg_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_to_shamsi'])) : '';
        $today_gregorian = current_time('Y-m-d');
        $today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;
        if ($msg_date_from_shamsi === '' && $msg_date_to_shamsi === '') {
            $msg_date_from_shamsi = $today_shamsi;
            $msg_date_to_shamsi = $today_shamsi;
        } elseif ($msg_date_from_shamsi === '') {
            $msg_date_from_shamsi = $msg_date_to_shamsi !== '' ? $msg_date_to_shamsi : $today_shamsi;
        } elseif ($msg_date_to_shamsi === '') {
            $msg_date_to_shamsi = $msg_date_from_shamsi !== '' ? $msg_date_from_shamsi : $today_shamsi;
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
        $messages = sc_private_notes_get_thread_messages((int) $thread->id, $msg_filter);
        $detail_clear_url = add_query_arg('view_note', (int) $thread->id, $base_url);
        ?>
        <div class="wrap wrap_attendace_user sc-private-notes-user">
            <h2><?php echo esc_html(trim((string) $thread->subject) !== '' ? (string) $thread->subject : ('پرونده #' . (int) $thread->id)); ?></h2>
            <a href="<?php echo esc_url($base_url); ?>" class="sc_button">بازگشت به لیست</a>

            <form method="get" action="<?php echo esc_url($base_url); ?>" style="<?php echo esc_attr($pn_filter_form_style); ?>">
                <input type="hidden" name="view_note" value="<?php echo (int) $thread->id; ?>">
                <div class="field_filter_attendace">
                    <p>
                        <label for="sc-pn-msg-s">جستجو در متن پیام‌ها:</label><br>
                        <input type="search" name="msg_s" id="sc-pn-msg-s" class="sc_attendamce_select" style="width:100%;max-width:100%;box-sizing:border-box;" value="<?php echo esc_attr($msg_s); ?>" placeholder="کلمه یا عبارت...">
                    </p>
                    <span class="range_date">بازه تاریخ پیام:
                        <p class="field_attednce_date">
                            <input type="text" name="msg_date_from_shamsi" class="persian-date-input sc_attendamce_select" placeholder="از تاریخ" value="<?php echo esc_attr($msg_date_from_shamsi); ?>" readonly>
                            <span> تا </span>
                            <input type="text" name="msg_date_to_shamsi" class="persian-date-input sc_attendamce_select" placeholder="تا تاریخ" value="<?php echo esc_attr($msg_date_to_shamsi); ?>" readonly>
                        </p>
                    </span><br>
                </div>
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url($detail_clear_url); ?>" class="sc_button">پاک کردن فیلتر</a>
            </form>

            <?php if (empty($messages)) : ?>
                <div class="notice notice-info"><p>پیامی با این فیلترها یافت نشد.</p></div>
            <?php else : ?>
                <div style="<?php echo esc_attr($pn_table_wrap_style); ?> margin-top:15px;">
                    <?php foreach ((array) $messages as $msg) : ?>
                        <p><strong><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d - H:i')); ?></strong></p>
                        <div class="sc-private-note-detail-body"><?php echo wp_kses_post(wpautop($msg->content)); ?></div>
                        <?php
                        $attachment_ids = !empty($msg->attachment_ids) ? json_decode($msg->attachment_ids, true) : [];
                        $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : [];
                        ?>
                        <?php if (!empty($attachment_ids)) : ?>
                            <div class="sc-private-note-detail-attachments"><strong>پیوست‌ها:</strong><ul>
                                <?php foreach ($attachment_ids as $aid) : if ($aid <= 0) {
                                    continue;
                                } ?>
                                    <li><a href="<?php echo esc_url(sc_private_notes_attachment_download_url($aid, (int) $msg->id)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('فایل #' . $aid)); ?></a></li>
                                <?php endforeach; ?>
                            </ul></div>
                        <?php endif; ?>
                        <hr>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return;
    }
}

$per_page = 12;
$page = isset($_GET['note_page']) ? max(1, absint($_GET['note_page'])) : 1;
$note_s = isset($_GET['note_s']) ? sanitize_text_field(wp_unslash($_GET['note_s'])) : '';
$list_result = sc_private_notes_query_user_threads($current_user_id, [
    'search' => $note_s,
    'per_page' => $per_page,
    'page' => $page,
]);
$total_pages = max(1, (int) $list_result['total_pages']);
if ($page > $total_pages) {
    $page = $total_pages;
    $list_result = sc_private_notes_query_user_threads($current_user_id, [
        'search' => $note_s,
        'per_page' => $per_page,
        'page' => $page,
    ]);
}
$notes = $list_result['rows'];
?>
<div class="wrap wrap_attendace_user sc-private-notes-user">
    <h2>پرونده‌های یادداشت من</h2>

    <form method="get" action="<?php echo esc_url($base_url); ?>" class="form_list_filter_note">
        <div class="field_filter_attendace">
            <p>
                <label for="sc-pn-note-s">جستجو در نام پرونده یا متن پیام‌ها:</label><br>
                <input type="search" name="note_s" id="sc-pn-note-s" class="sc_privet_note_select" style="width:100%;max-width:100%;box-sizing:border-box;" value="<?php echo esc_attr($note_s); ?>" placeholder="کلمه یا عبارت...">
            </p>
        </div>
        <button type="submit" class="sc_button button-primary">جستجو</button>
        <?php if ($note_s !== '') : ?>
            <a href="<?php echo esc_url(remove_query_arg(['note_s', 'note_page'], $base_url)); ?>" class="sc_button">پاک کردن</a>
        <?php endif; ?>
    </form>

    <?php if (empty($notes)) : ?>
        <div class="notice notice-info">
            <p><?php echo esc_html($note_s !== '' ? 'موردی با این جستجو یافت نشد.' : 'هنوز یادداشتی برای شما ثبت نشده است.'); ?></p>
        </div>
    <?php else : ?>
        <div style="<?php echo esc_attr($pn_table_wrap_style); ?>">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="text-align:center;">پرونده</th>
                        <th style="text-align:center;">آخرین بروزرسانی</th>
                        <th style="text-align:center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $note) : ?>
                        <tr>
                            <td style="text-align:center;"><?php echo esc_html(trim((string) $note->subject) !== '' ? (string) $note->subject : ('پرونده #' . (int) $note->id)); ?></td>
                            <td style="text-align:center;"><?php echo esc_html(sc_date_shamsi($note->updated_at, 'Y/m/d H:i')); ?></td>
                            <td style="text-align:center;"><a class="sc_button" href="<?php echo esc_url(add_query_arg(['view_note' => (int) $note->id], $base_url)); ?>">مشاهده گفتگو</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="sc-private-notes-pagination" style="<?php echo esc_attr($pn_table_wrap_style); ?> margin-top:15px;">
                <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                    <?php
                    $p_url = add_query_arg(['note_page' => $p], $base_url);
                    if ($note_s !== '') {
                        $p_url = add_query_arg('note_s', $note_s, $p_url);
                    }
                    ?>
                    <?php if ($p === $page) : ?>
                        <span class="current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url($p_url); ?>"><?php echo (int) $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
