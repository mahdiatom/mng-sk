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
            if (!in_array($department, ['manager', 'coach', 'site_support'], true)) {
                $department = 'manager';
            }
            $coach_id = ($department === 'coach') ? absint($_POST['ticket_coach_id'] ?? 0) : 0;
            $message = wp_kses_post($_POST['ticket_message'] ?? '');
            $attachments = [];
            if (!empty($_POST['ticket_attachment_ids']) && function_exists('sc_support_validate_attachment_ids')) {
                $raw = is_array($_POST['ticket_attachment_ids']) ? $_POST['ticket_attachment_ids'] : explode(',', (string) $_POST['ticket_attachment_ids']);
                $attachments = sc_support_validate_attachment_ids($raw, 5);
            }
            if (empty($attachments) && function_exists('sc_support_handle_attachments')) {
                $attachments = sc_support_handle_attachments('ticket_attachments');
            }
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
                $attachments = [];
                if (!empty($_POST['reply_attachment_ids']) && function_exists('sc_support_validate_attachment_ids')) {
                    $raw = is_array($_POST['reply_attachment_ids']) ? $_POST['reply_attachment_ids'] : explode(',', (string) $_POST['reply_attachment_ids']);
                    $attachments = sc_support_validate_attachment_ids($raw, 5);
                }
                if (empty($attachments) && function_exists('sc_support_handle_attachments')) {
                    $attachments = sc_support_handle_attachments('reply_attachments');
                }
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
$coaches = sc_support_get_coaches_for_member($member_id);
?>
<div class="woocommerce-MyAccount-content sc-support-tickets-content">
<?php if ($view_ticket_id > 0 && isset($ticket)) : ?>
    <div class="sc-ticket-detail-card">
        <a href="<?php echo esc_url($base_url); ?>" class="sc-ticket-back-link">← بازگشت به لیست تیکت‌ها</a>
        <h2 class="sc-ticket-detail-title">تیکت #<?php echo esc_html($ticket->id); ?> – <?php echo esc_html($ticket->subject); ?></h2>
        <div class="sc-ticket-detail-meta">
            <span class="sc-ticket-meta-badge sc-ticket-status-<?php echo esc_attr($ticket->status); ?>"><?php echo esc_html(sc_support_status_label($ticket->status)); ?></span>
            <span class="sc-ticket-meta-text">بخش: <?php echo esc_html(sc_support_department_label($ticket->department, $ticket->coach_id)); ?></span>
            <span class="sc-ticket-meta-text">آخرین به‌روزرسانی: <?php echo esc_html(sc_date_shamsi($ticket->updated_at, 'Y/m/d H:i')); ?></span>
        </div>

        <div class="sc-ticket-messages">
            <?php
            $messages = sc_support_get_messages($ticket->id);
            foreach ($messages as $msg) :
                $is_user = ($msg->sender_type === 'user');
                $label = $is_user ? 'شما' : (($msg->sender_type === 'admin') ? 'مدیر' : 'مربی');
            ?>
            <div class="sc-ticket-msg <?php echo $is_user ? 'sc-ticket-msg-user' : 'sc-ticket-msg-staff'; ?>">
                <div class="sc-ticket-msg-header">
                    <strong class="sc-ticket-msg-sender"><?php echo esc_html($label); ?></strong>
                    <span class="sc-ticket-msg-date"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d H:i')); ?></span>
                </div>
                <div class="sc-ticket-msg-body"><?php echo wp_kses_post(wpautop($msg->message)); ?></div>
                <?php
                $attachment_ids_raw = isset($msg->attachment_ids) ? $msg->attachment_ids : '';
                if ($attachment_ids_raw !== '' && $attachment_ids_raw !== null) {
                    $ids = json_decode($attachment_ids_raw, true);
                    if (is_array($ids) && count($ids) > 0) {
                        echo '<div class="sc-ticket-msg-attachments">';
                        foreach ($ids as $aid) {
                            $aid = (int) $aid;
                            if ($aid <= 0) continue;
                            $url = sc_support_attachment_download_url($aid, $ticket->id);
                            $name = get_the_title($aid) ?: 'پیوست';
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="sc-ticket-attachment-link">' . esc_html($name) . '</a>';
                        }
                        echo '</div>';
                    }
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($ticket->status !== 'closed') : ?>
        <div class="sc-ticket-reply-form-wrap">
            <form method="post" class="sc-ticket-reply-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="reply">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <p class="sc-ticket-field">
                    <label for="reply_message">پاسخ شما</label>
                    <textarea name="reply_message" id="reply_message" rows="4" required placeholder="متن پاسخ خود را بنویسید..."></textarea>
                </p>
                <div class="sc-ticket-field sc-ticket-attachment-zone" data-input-name="reply_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_ticket_upload_attachment')); ?>">
                    <label>پیوست (اختیاری)</label>
                    <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                        <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx" multiple>
                        <span class="sc-file-upload-icon">📎</span>
                        <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                        <span class="sc-file-upload-hint">حداکثر ۵ فایل، هر کدام ۵ مگابایت. فرمت‌های مجاز: تصویر (jpg, png, gif, webp, bmp, ico, svg, tiff, heic و...)، PDF، ورد، اکسل</span>
                    </div>
                    <div class="sc-ticket-upload-progress-wrap" style="display:none;">
                        <div class="sc-upload-progress sc-ticket-upload-progress">
                            <div class="sc-upload-bar"></div>
                            <span class="sc-upload-text"></span>
                        </div>
                    </div>
                    <div class="sc-ticket-uploaded-list"></div>
                    <div class="sc-ticket-attachment-ids-hidden"></div>
                </div>
                <div class="sc-form-actions">
                    <button type="submit" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-submit">ارسال پاسخ</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($ticket->status === 'closed') : ?>
        <div class="sc-ticket-closed-notice">
            <p>این تیکت بسته شده است. با ارسال پاسخ جدید، تیکت مجدداً باز می‌شود.</p>
            <form method="post" class="sc-ticket-reopen-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="reply">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <p class="sc-ticket-field"><textarea name="reply_message" rows="3" placeholder="متن پاسخ برای باز کردن تیکت..."></textarea></p>
                <div class="sc-ticket-field sc-ticket-attachment-zone" data-input-name="reply_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_ticket_upload_attachment')); ?>">
                    <label>پیوست (اختیاری)</label>
                    <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                        <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx" multiple>
                        <span class="sc-file-upload-icon">📎</span>
                        <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                        <span class="sc-file-upload-hint">حداکثر ۵ فایل، هر کدام ۵ مگابایت. فرمت‌های مجاز: تصویر، PDF، ورد، اکسل</span>
                    </div>
                    <div class="sc-ticket-upload-progress-wrap" style="display:none;"><div class="sc-upload-progress sc-ticket-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div></div>
                    <div class="sc-ticket-uploaded-list"></div>
                    <div class="sc-ticket-attachment-ids-hidden"></div>
                </div>
                <div class="sc-form-actions"><button type="submit" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-submit">ارسال و باز کردن تیکت</button></div>
            </form>
        </div>
        <?php else : ?>
        <div class="sc-ticket-close-form-wrap">
            <form method="post" class="sc-ticket-close-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="close">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <div class="sc-form-actions">
                    <button type="submit" class="sc-ticket-btn sc-ticket-btn-close" onclick="return confirm('آیا از بستن این تیکت اطمینان دارید؟');">بستن تیکت</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

<?php else : ?>
    <?php wc_print_notices(); ?>

    <!-- نمای لیست تیکت‌ها (پیش‌فرض) -->
    <div id="sc-support-list-view" class="sc-support-list-view">
        <div class="sc-support-heading-row">
            <h2 class="sc-support-heading">تیکت‌های پشتیبانی</h2>
            <button type="button" id="sc-support-btn-new-ticket" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-new">ارسال تیکت جدید</button>
        </div>
        <div class="sc-support-toolbar">
            <ul class="sc-support-tabs" aria-label="فیلتر وضعیت">
                <li><a href="#" class="sc-support-tab active" data-filter="all">همه</a></li>
                <li><a href="#" class="sc-support-tab" data-filter="pending_reply">در انتظار پاسخ</a></li>
                <li><a href="#" class="sc-support-tab" data-filter="answered">پاسخ داده شده</a></li>
                <li><a href="#" class="sc-support-tab" data-filter="closed">بسته شده</a></li>
            </ul>
            <form id="sc-support-search-form" class="sc-support-search-form">
                <input type="hidden" name="filter_status" id="sc-support-filter-status" value="all">
                <input type="search" name="s" id="sc-support-search-input" placeholder="جستجو (موضوع یا شناسه)...">
                <button type="submit" class="sc-ticket-btn sc-ticket-btn-secondary">جستجو</button>
                <button type="button" class="sc-ticket-btn sc-ticket-btn-outline sc-support-clear-search" style="display:none;">پاک کردن</button>
            </form>
        </div>
        <div id="sc-support-ajax-container">
            <div class="sc-support-loading-placeholder">در حال بارگذاری...</div>
        </div>
    </div>

    <!-- نمای فرم ارسال تیکت جدید (مخفی در ابتدا) -->
    <div id="sc-support-form-view" class="sc-support-form-view" style="display:none;">
        <div class="sc-ticket-form-card sc-ticket-form-card-full">
            <a href="#" id="sc-support-back-to-list" class="sc-ticket-back-link">← بازگشت به لیست تیکت‌ها</a>
            <h2 class="sc-ticket-form-title">ارسال تیکت جدید</h2>
            <form method="post" class="sc-ticket-new-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="create">

                <p class="sc-ticket-field">
                    <label for="ticket_subject">موضوع <span class="required">*</span></label>
                    <input type="text" name="ticket_subject" id="ticket_subject" required maxlength="255" placeholder="موضوع تیکت را وارد کنید">
                </p>

                <p class="sc-ticket-field">
                    <label for="ticket_department">بخش مورد نظر <span class="required">*</span></label>
                    <select name="ticket_department" id="ticket_department">
                        <option value="manager">مدیر باشگاه</option>
                        <option value="site_support">پشتیبانی سایت</option>
                        <?php if (!empty($coaches)) : ?>
                        <option value="coach">مربی باشگاه</option>
                        <?php endif; ?>
                    </select>
                </p>

                <?php if (!empty($coaches)) : ?>
                <p class="sc-ticket-field sc-ticket-coach-row" id="ticket_coach_wrap" style="display:none;">
                    <label for="ticket_coach_id">مربی <span class="required">*</span></label>
                    <select name="ticket_coach_id" id="ticket_coach_id">
                        <option value="0">انتخاب کنید</option>
                        <?php foreach ($coaches as $co) : ?>
                        <option value="<?php echo (int) $co['coach_id']; ?>"><?php echo esc_html($co['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <?php endif; ?>

                <p class="sc-ticket-field">
                    <label for="ticket_message">متن پیام <span class="required">*</span></label>
                    <textarea name="ticket_message" id="ticket_message" rows="5" required placeholder="متن پیام خود را بنویسید..."></textarea>
                </p>

                <div class="sc-ticket-field sc-ticket-attachment-zone" data-input-name="ticket_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_ticket_upload_attachment')); ?>" data-nonce-key="sc_ticket_upload_nonce">
                    <label>پیوست (اختیاری)</label>
                    <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                        <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx" multiple>
                        <span class="sc-file-upload-icon">📎</span>
                        <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                        <span class="sc-file-upload-hint">حداکثر ۵ فایل، هر کدام ۵ مگابایت. فرمت‌های مجاز: تصویر (jpg, png, gif, webp, bmp, ico, svg, tiff, heic و...)، PDF، ورد، اکسل</span>
                    </div>
                    <div class="sc-ticket-upload-progress-wrap" style="display:none;">
                        <div class="sc-upload-progress sc-ticket-upload-progress">
                            <div class="sc-upload-bar"></div>
                            <span class="sc-upload-text"></span>
                        </div>
                    </div>
                    <div class="sc-ticket-uploaded-list"></div>
                    <div class="sc-ticket-attachment-ids-hidden"></div>
                </div>

                <div class="sc-form-actions">
                    <button type="submit" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-submit">ارسال تیکت</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function($) {
        var baseUrl = '<?php echo esc_js($base_url); ?>';
        var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        var filterStatus = 'all';
        var searchQuery = '';
        var currentPage = 1;

        function loadTickets() {
            var $container = $('#sc-support-ajax-container');
            $container.css('opacity', '0.6');
            $.post(ajaxUrl, {
                action: 'sc_support_tickets_filter',
                filter_status: filterStatus,
                s: searchQuery,
                ticket_page: currentPage
            }, function(res) {
                $container.css('opacity', '1');
                if (res && res.success && res.data) {
                    renderTickets(res.data);
                } else {
                    $container.html('<div class="sc-support-empty-state"><p class="sc-support-empty-text">خطا در بارگذاری.</p></div>');
                }
            }).fail(function() {
                $container.css('opacity', '1');
                $container.html('<div class="sc-support-empty-state"><p class="sc-support-empty-text">خطا در بارگذاری.</p></div>');
            });
        }

        function renderTickets(data) {
            var html = '';
            if (!data.items || data.items.length === 0) {
                html = '<div class="sc-support-empty-state"><span class="sc-support-empty-icon" aria-hidden="true"></span><p class="sc-support-empty-text">' + (data.empty_message || '') + '</p></div>';
            } else {
                html = '<div class="sc-support-grid">';
                $.each(data.items, function(i, t) {
                    html += '<article class="sc-ticket-card sc-ticket-status-' + (t.status || '') + '"><div class="sc-ticket-card-inner">';
                    html += '<h3 class="sc-ticket-card-title"><a href="' + (t.view_url || '') + '">' + (t.subject || '') + '</a></h3>';
                    html += '<div class="sc-ticket-card-meta"><span class="sc-ticket-card-date">' + (t.updated_at || '') + '</span>';
                    html += '<span class="sc-ticket-card-badge sc-ticket-badge-' + (t.status || '') + '">' + (t.status_label || '') + '</span>';
                    html += '<span class="sc-ticket-card-dept">' + (t.department_label || '') + '</span></div>';
                    html += '<div class="sc-ticket-card-actions"><a href="' + (t.view_url || '') + '" class="sc-ticket-btn sc-ticket-btn-primary">مشاهده</a></div>';
                    html += '</div></article>';
                });
                html += '</div>';
                if (data.total_pages > 1) {
                    html += '<nav class="sc-support-pagination" aria-label="صفحه‌بندی">';
                    for (var p = 1; p <= data.total_pages; p++) {
                        if (p === data.page) {
                            html += '<span class="sc-support-page-current">' + p + '</span> ';
                        } else {
                            html += '<a href="#" class="sc-support-page-link" data-page="' + p + '">' + p + '</a> ';
                        }
                    }
                    html += '</nav>';
                }
            }
            $('#sc-support-ajax-container').html(html);
        }

        function setTabActive() {
            $('.sc-support-tab').removeClass('active').css({'background':'#f0f0f1','color':'#1d2327'});
            $('.sc-support-tab[data-filter="' + filterStatus + '"]').addClass('active').css({'background':'#2271b1','color':'#fff'});
            $('#sc-support-filter-status').val(filterStatus);
        }

        $('#sc-support-list-view').on('click', '.sc-support-tab', function(e) {
            e.preventDefault();
            filterStatus = $(this).data('filter');
            currentPage = 1;
            setTabActive();
            loadTickets();
        });

        $('#sc-support-search-form').on('submit', function(e) {
            e.preventDefault();
            searchQuery = $('#sc-support-search-input').val().trim();
            currentPage = 1;
            loadTickets();
            if (searchQuery) $('.sc-support-clear-search').show();
        });

        $('#sc-support-ajax-container').on('click', '.sc-support-page-link', function(e) {
            e.preventDefault();
            currentPage = $(this).data('page');
            loadTickets();
        });

        $('.sc-support-clear-search').on('click', function() {
            $('#sc-support-search-input').val('');
            searchQuery = '';
            currentPage = 1;
            loadTickets();
            $(this).hide();
        });

        $('#sc-support-btn-new-ticket').on('click', function() {
            $('#sc-support-list-view').hide();
            $('#sc-support-form-view').show();
        });

        $('#sc-support-back-to-list').on('click', function(e) {
            e.preventDefault();
            $('#sc-support-form-view').hide();
            $('#sc-support-list-view').show();
        });

        <?php if (!empty($coaches)) : ?>
        var dept = document.getElementById('ticket_department');
        var wrap = document.getElementById('ticket_coach_wrap');
        var sel = document.getElementById('ticket_coach_id');
        if (dept && wrap && sel) {
            function toggleCoach() {
                var isCoach = dept.value === 'coach';
                wrap.style.display = isCoach ? 'block' : 'none';
                sel.required = isCoach;
            }
            $(dept).on('change', toggleCoach);
            toggleCoach();
        }
        <?php endif; ?>

        setTabActive();
        loadTickets();
    })(jQuery);
    </script>
<?php endif; ?>
</div>
