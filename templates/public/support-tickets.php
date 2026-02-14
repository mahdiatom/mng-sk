<?php
if (!defined('ABSPATH')) exit;
$current_user_id = get_current_user_id();
$member_id = isset($player->id) ? (int) $player->id : 0;
$view_ticket_id = isset($_GET['view_ticket']) ? absint($_GET['view_ticket']) : 0;

// Process POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_ticket_action'])) {
    if (!wp_verify_nonce($_POST['sc_ticket_nonce'] ?? '', 'sc_ticket_action')) {
        echo '<p class="woocommerce-error">خطای امنیتی. لطفا دوباره تلاش کنید.</p>';
    } else {
        $action = sanitize_text_field($_POST['sc_ticket_action']);
        if ($action === 'create') {
            $subject = sanitize_text_field($_POST['ticket_subject'] ?? '');
            $department = sanitize_text_field($_POST['ticket_department'] ?? 'manager');
            $coach_id = ($department === 'coach') ? absint($_POST['ticket_coach_id'] ?? 0) : 0;
            $message = wp_kses_post($_POST['ticket_message'] ?? '');
            $attachments = function_exists('sc_support_handle_attachments') ? sc_support_handle_attachments('ticket_attachments') : [];
            $result = sc_support_create_ticket($current_user_id, $department, $coach_id, $subject, $message, $attachments);
            if (is_wp_error($result)) {
                echo '<p class="woocommerce-error">' . esc_html($result->get_error_message()) . '</p>';
            } else {
                wp_safe_redirect(wc_get_account_endpoint_url('sc-support-tickets') . '?view_ticket=' . $result);
                exit;
            }
        } elseif ($action === 'reply') {
            $ticket_id = absint($_POST['ticket_id'] ?? 0);
            $ticket = sc_support_get_ticket($ticket_id);
            if (!$ticket || !sc_support_can_view_ticket($ticket, $current_user_id)) {
                echo '<p class="woocommerce-error">تیکت یافت نشد یا دسترسی ندارید.</p>';
            } else {
                $message = wp_kses_post($_POST['reply_message'] ?? '');
                $attachments = function_exists('sc_support_handle_attachments') ? sc_support_handle_attachments('reply_attachments') : [];
                $result = sc_support_add_message($ticket_id, 'user', $current_user_id, $message, $attachments);
                if (is_wp_error($result)) {
                    echo '<p class="woocommerce-error">' . esc_html($result->get_error_message()) . '</p>';
                } else {
                    wp_safe_redirect(wc_get_account_endpoint_url('sc-support-tickets') . '?view_ticket=' . $ticket_id);
                    exit;
                }
            }
        } elseif ($action === 'close') {
            $ticket_id = absint($_POST['ticket_id'] ?? 0);
            $ticket = sc_support_get_ticket($ticket_id);
            if (!$ticket || !sc_support_can_close_ticket($ticket, $current_user_id)) {
                echo '<p class="woocommerce-error">امکان بستن این تیکت را ندارید.</p>';
            } else {
                sc_support_close_ticket($ticket_id, $current_user_id);
                wp_safe_redirect(wc_get_account_endpoint_url('sc-support-tickets'));
                exit;
            }
        }
    }
}

if ($view_ticket_id > 0) {
    $ticket = sc_support_get_ticket($view_ticket_id);
    if (!$ticket || !sc_support_can_view_ticket($ticket, $current_user_id)) {
        echo '<p class="woocommerce-error">تیکت یافت نشد.</p>';
        $view_ticket_id = 0;
    }
}

$base_url = wc_get_account_endpoint_url('sc-support-tickets');
?>
<div class="woocommerce-MyAccount-content sc-support-tickets-content">
<?php if ($view_ticket_id > 0 && isset($ticket)) : ?>
    <a href="<?php echo esc_url($base_url); ?>" class="button">← بازگشت به لیست تیکت‌ها</a>
    <h2>تیکت #<?php echo esc_html($ticket->id); ?> – <?php echo esc_html($ticket->subject); ?></h2>
    <p>
        <span class="sc-ticket-meta">وضعیت: <?php echo esc_html(sc_support_status_label($ticket->status)); ?></span>
        <span class="sc-ticket-meta">بخش: <?php echo esc_html(sc_support_department_label($ticket->department, $ticket->coach_id)); ?></span>
        <span class="sc-ticket-meta">آخرین به‌روزرسانی: <?php echo esc_html(sc_date_shamsi($ticket->updated_at, 'Y/m/d H:i')); ?></span>
    </p>
    <div class="sc-ticket-messages" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 8px;">
        <?php
        $messages = sc_support_get_messages($ticket->id);
        foreach ($messages as $msg) :
            $is_user = ($msg->sender_type === 'user');
            $label = $is_user ? 'شما' : (($msg->sender_type === 'admin') ? 'مدیر' : 'مربی');
        ?>
        <div class="sc-ticket-msg <?php echo $is_user ? 'sc-msg-user' : 'sc-msg-staff'; ?>" style="margin-bottom: 15px; padding: 10px; background: <?php echo $is_user ? '#e8f4fc' : '#fff3e0'; ?>; border-radius: 6px;">
            <strong><?php echo esc_html($label); ?></strong>
            <span style="color:#666; font-size:12px;"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d H:i')); ?></span>
            <div style="margin-top:8px;"><?php echo wp_kses_post(wpautop($msg->message)); ?></div>
            <?php
            if (!empty($msg->attachment_ids)) {
                $ids = json_decode($msg->attachment_ids, true);
                if (is_array($ids)) {
                    echo '<div class="sc-msg-attachments" style="margin-top:8px;">';
                    foreach ($ids as $aid) {
                        $url = sc_support_attachment_download_url($aid, $ticket->id);
                        $name = get_the_title($aid) ?: 'پیوست';
                        echo '<a href="' . esc_url($url) . '" target="_blank" class="sc-attachment-link">' . esc_html($name) . '</a> ';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($ticket->status !== 'closed') : ?>
    <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="reply">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <p>
            <label for="reply_message">پاسخ شما</label><br>
            <textarea name="reply_message" id="reply_message" rows="4" class="input-text" required></textarea>
        </p>
        <p>
            <label>پیوست (اختیاری)</label><br>
            <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
            <span class="description">فرمت‌های مجاز: تصویر، PDF، ورد، اکسل. حداکثر ۵ فایل، هر کدام ۵ مگابایت.</span>
        </p>
        <p><button type="submit" class="button button-primary">ارسال پاسخ</button></p>
    </form>
    <?php endif; ?>
    <?php if ($ticket->status === 'closed') : ?>
    <p><em>این تیکت بسته شده است. با ارسال پاسخ جدید، تیکت مجدداً باز می‌شود.</em></p>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="reply">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <p><textarea name="reply_message" rows="3" placeholder="متن پاسخ برای باز کردن تیکت..."></textarea></p>
        <p><button type="submit" class="button">ارسال و باز کردن تیکت</button></p>
    </form>
    <?php else : ?>
    <form method="post" style="margin-top: 15px;">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="close">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <button type="submit" class="button" onclick="return confirm('آیا از بستن این تیکت اطمینان دارید؟');">بستن تیکت</button>
    </form>
    <?php endif; ?>

<?php else : ?>
    <h2>تیکت‌های پشتیبانی</h2>
    <p><a href="#new-ticket-form" class="button button-primary">ارسال تیکت جدید</a></p>
    <?php
    $tickets = sc_support_get_tickets_for_user($current_user_id, ['per_page' => 20]);
    if (empty($tickets)) :
        echo '<p>هنوز تیکتی ارسال نکرده‌اید.</p>';
    else :
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders"><thead><tr>';
        echo '<th>شناسه</th><th>موضوع</th><th>بخش</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead><tbody>';
        foreach ($tickets as $t) {
            echo '<tr>';
            echo '<td>' . esc_html($t->id) . '</td>';
            echo '<td>' . esc_html($t->subject) . '</td>';
            echo '<td>' . esc_html(sc_support_department_label($t->department, $t->coach_id)) . '</td>';
            echo '<td>' . esc_html(sc_support_status_label($t->status)) . '</td>';
            echo '<td>' . esc_html(sc_date_shamsi($t->updated_at, 'Y/m/d')) . '</td>';
            echo '<td><a href="' . esc_url($base_url . '?view_ticket=' . $t->id) . '" class="button">مشاهده</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    endif;
    ?>

    <div id="new-ticket-form" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h3>ارسال تیکت جدید</h3>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
            <input type="hidden" name="sc_ticket_action" value="create">
            <p>
                <label for="ticket_subject">موضوع <span class="required">*</span></label><br>
                <input type="text" name="ticket_subject" id="ticket_subject" class="input-text" required maxlength="255" style="width:100%; max-width:400px;">
            </p>
            <p>
                <label>بخش مورد نظر <span class="required">*</span></label><br>
                <label><input type="radio" name="ticket_department" value="manager" checked> مدیر باشگاه</label>
                <?php
                $coaches = sc_support_get_coaches_for_member($member_id);
                if (!empty($coaches)) {
                    foreach ($coaches as $co) {
                        echo '<br><label><input type="radio" name="ticket_department" value="coach" data-coach-id="' . (int)$co['coach_id'] . '"> مربی: ' . esc_html($co['name']) . '</label>';
                    }
                }
                ?>
            </p>
            <?php if (!empty($coaches)) : ?>
            <p id="ticket_coach_wrap" style="display:none;">
                <label for="ticket_coach_id">مربی</label><br>
                <select name="ticket_coach_id" id="ticket_coach_id">
                    <option value="0">انتخاب کنید</option>
                    <?php foreach ($coaches as $co) : ?>
                    <option value="<?php echo (int) $co['coach_id']; ?>"><?php echo esc_html($co['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <?php endif; ?>
            <p>
                <label for="ticket_message">متن پیام <span class="required">*</span></label><br>
                <textarea name="ticket_message" id="ticket_message" rows="5" class="input-text" required style="width:100%; max-width:500px;"></textarea>
            </p>
            <p>
                <label>پیوست (اختیاری)</label><br>
                <input type="file" name="ticket_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                <span class="description">فرمت‌های مجاز: تصویر، PDF، ورد، اکسل. حداکثر ۵ فایل، هر کدام ۵ مگابایت.</span>
            </p>
            <p><button type="submit" class="button button-primary">ارسال تیکت</button></p>
        </form>
    </div>
    <?php if (!empty($coaches)) : ?>
    <script>
    (function(){
        var deps = document.querySelectorAll('input[name="ticket_department"]');
        var wrap = document.getElementById('ticket_coach_wrap');
        var sel = document.getElementById('ticket_coach_id');
        if (!wrap || !sel) return;
        function toggle() {
            var isCoach = document.querySelector('input[name="ticket_department"]:checked').value === 'coach';
            wrap.style.display = isCoach ? 'block' : 'none';
            if (isCoach) sel.setAttribute('required', 'required'); else sel.removeAttribute('required');
        }
        deps.forEach(function(r){ r.addEventListener('change', toggle); });
        toggle();
    })();
    </script>
    <?php endif; ?>
<?php endif; ?>
</div>
