<?php
/**
 * Export survey responses to Excel / PDF
 */

function sc_survey_is_file_question_for_export($question) {
    return isset($question->question_type) && $question->question_type === 'file';
}

function sc_survey_get_export_questions($survey_id) {
    return array_values(array_filter(
        sc_get_survey_questions($survey_id),
        function ($question) {
            return !sc_survey_is_file_question_for_export($question);
        }
    ));
}

function sc_survey_format_answer_for_export($question, $answer_row) {
    if (!$answer_row) {
        return '—';
    }

    $qtype = $question->question_type ?? '';
    $opts = sc_survey_decode_json($question->options_json ?? '');

    if (!empty($answer_row->answer_json)) {
        $decoded = json_decode($answer_row->answer_json, true);
        if ($qtype === 'yes_no') {
            $val = is_array($decoded) ? reset($decoded) : $decoded;
            return $val === 'yes' ? 'بله' : ($val === 'no' ? 'خیر' : (string) $val);
        }
        if (in_array($qtype, ['rating', 'scale'], true)) {
            $max = isset($opts['max']) ? (int) $opts['max'] : 5;
            $val = is_array($decoded) ? reset($decoded) : $decoded;
            return trim((string) $val) !== '' ? ((string) $val . ' از ' . $max) : '—';
        }
        if (is_array($decoded)) {
            return implode('، ', array_map('strval', $decoded));
        }
        return (string) $decoded;
    }

    $text = trim((string) ($answer_row->answer_text ?? ''));
    if ($text === '') {
        return '—';
    }
    if ($qtype === 'yes_no') {
        return $text === 'yes' ? 'بله' : ($text === 'no' ? 'خیر' : $text);
    }
    return $text;
}

function sc_survey_get_export_response_items($survey_id, $filters = [], $response_id = 0) {
    global $wpdb;

    $survey_id = absint($survey_id);
    $response_id = absint($response_id);
    $questions = sc_survey_get_export_questions($survey_id);
    $answers_table = $wpdb->prefix . 'sc_survey_answers';

    if ($response_id > 0) {
        $response = sc_survey_get_response($response_id);
        if (!$response || (int) $response->survey_id !== $survey_id || $response->status !== 'completed') {
            return [];
        }
        $responses = [$response];
    } else {
        $responses = sc_survey_build_responses_query($survey_id, $filters);
    }

    $items = [];
    foreach ((array) $responses as $response) {
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $answers_table WHERE response_id = %d",
            (int) $response->id
        ));
        $map = [];
        foreach ((array) $answers as $answer) {
            $map[(int) $answer->question_id] = $answer;
        }

        $answer_rows = [];
        foreach ($questions as $question) {
            $answer_rows[] = [
                'question' => wp_strip_all_tags($question->question_text),
                'type' => sc_get_survey_question_types()[$question->question_type] ?? $question->question_type,
                'answer' => sc_survey_format_answer_for_export($question, $map[(int) $question->id] ?? null),
            ];
        }

        $items[] = [
            'response_id' => (int) $response->id,
            'name' => sc_survey_response_display_name($response),
            'phone' => sc_survey_response_display_phone($response),
            'national_id' => !empty($response->national_id) ? (string) $response->national_id : '—',
            'completed_at' => !empty($response->completed_at) && function_exists('sc_date_shamsi')
                ? sc_date_shamsi($response->completed_at, 'Y/m/d H:i')
                : ($response->completed_at ?? '—'),
            'answers' => $answer_rows,
        ];
    }

    return $items;
}

function sc_survey_export_discard_output_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function sc_export_survey_responses_to_excel($survey_id) {
    sc_check_phpspreadsheet();
    $survey_id = absint($survey_id);
    $survey = sc_get_survey($survey_id);
    if (!$survey) {
        wp_die('نظرسنجی یافت نشد.');
    }

    $filters = sc_survey_get_admin_response_filters($_GET);
    $questions = sc_survey_get_export_questions($survey_id);
    $items = sc_survey_get_export_response_items($survey_id, $filters);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('نظرسنجی');
    $sheet->setRightToLeft(true);

    $headers = ['ردیف', 'نام', 'موبایل', 'تاریخ پاسخ'];
    foreach ($questions as $q) {
        $headers[] = wp_strip_all_tags($q->question_text);
    }
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $header);
    }
    if (function_exists('sc_get_excel_header_style')) {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray(sc_get_excel_header_style());
    }

    $row = 2;
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $sheet->setCellValueByColumnAndRow(1, $row, $i);
        $sheet->setCellValueByColumnAndRow(2, $row, $item['name']);
        $sheet->setCellValueByColumnAndRow(3, $row, $item['phone']);
        $sheet->setCellValueByColumnAndRow(4, $row, $item['completed_at']);

        $col = 5;
        foreach ($item['answers'] as $answer_row) {
            $sheet->setCellValueByColumnAndRow($col++, $row, $answer_row['answer']);
        }
        $row++;
    }

    if (function_exists('sc_auto_size_columns')) {
        sc_auto_size_columns($sheet, count($headers));
    }

    sc_survey_export_discard_output_buffers();
    $filename = 'survey-' . $survey_id . '-' . gmdate('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function sc_export_survey_responses_to_pdf($survey_id, $response_id = 0) {
    $survey_id = absint($survey_id);
    $response_id = absint($response_id);
    $survey = sc_get_survey($survey_id);
    if (!$survey) {
        wp_die('نظرسنجی یافت نشد.');
    }

    $filters = sc_survey_get_admin_response_filters($_GET);
    $items = sc_survey_get_export_response_items($survey_id, $filters, $response_id);
    $is_single = $response_id > 0;
    $title = $is_single
        ? ('پاسخ نظرسنجی — ' . $survey->title)
        : ('خروجی PDF نظرسنجی — ' . $survey->title);
    $subtitle = $is_single
        ? 'جزئیات پاسخ یک کاربر'
        : (count($items) . ' پاسخ — هر کاربر در صفحه جدا');

    sc_survey_export_discard_output_buffers();
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($title); ?></title>
        <link rel="stylesheet" href="<?php echo esc_url(SC_ASSETS_URL . 'css/survey-export-print.css'); ?>">
    </head>
    <body class="sc-survey-export-print-page">
        <div class="sc-survey-print-header">
            <div class="sc-survey-print-title-wrap">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($subtitle); ?></p>
            </div>
            <div class="sc-survey-print-actions">
                <button type="button" onclick="window.print()">چاپ / PDF</button>
            </div>
        </div>

        <?php if (empty($items)) : ?>
            <div class="sc-survey-print-empty">پاسخی برای خروجی یافت نشد.</div>
        <?php else : ?>
            <?php foreach ($items as $index => $item) : ?>
                <section class="sc-survey-print-response">
                    <div class="sc-survey-print-response-head">
                        <h2><?php echo esc_html($item['name']); ?></h2>
                        <div class="sc-survey-print-meta"><strong>موبایل:</strong> <?php echo esc_html($item['phone'] !== '' ? $item['phone'] : '—'); ?></div>
                        <div class="sc-survey-print-meta"><strong>کد ملی:</strong> <?php echo esc_html($item['national_id']); ?></div>
                        <div class="sc-survey-print-meta"><strong>تاریخ پاسخ:</strong> <?php echo esc_html($item['completed_at']); ?></div>
                        <?php if (!$is_single) : ?>
                            <div class="sc-survey-print-meta"><strong>ردیف:</strong> <?php echo (int) ($index + 1); ?></div>
                        <?php endif; ?>
                    </div>

                    <table class="sc-survey-print-answers">
                        <tbody>
                        <?php foreach ($item['answers'] as $answer_row) : ?>
                            <tr>
                                <th><?php echo esc_html($answer_row['question']); ?></th>
                                <td><?php echo esc_html($answer_row['answer']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

function sc_survey_get_response_pdf_export_url($survey_id, $response_id, $query_args = []) {
    $survey_id = absint($survey_id);
    $response_id = absint($response_id);
    $args = array_merge((array) $query_args, [
        'page' => 'sc-survey-data',
        'survey_id' => $survey_id,
        'sc_export_survey_pdf' => 1,
        'response_id' => $response_id,
    ]);
    return wp_nonce_url(add_query_arg($args, admin_url('admin.php')), 'sc_export_survey_' . $survey_id);
}
