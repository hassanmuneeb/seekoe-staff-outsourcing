<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function sanitize_string(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}

function flatten_fields(array $fields, string $prefix = ''): array
{
    $flat = [];

    foreach ($fields as $key => $value) {
        $label = $prefix === '' ? (string) $key : $prefix . ' > ' . (string) $key;

        if (is_array($value)) {
            $flat += flatten_fields($value, $label);
            continue;
        }

        $flat[$label] = sanitize_string((string) $value);
    }

    return $flat;
}

function normalize_files_array(array $files): array
{
    $normalized = [];

    foreach ($files as $field => $fileSet) {
        if (!isset($fileSet['name'])) {
            continue;
        }

        if (is_array($fileSet['name'])) {
            foreach ($fileSet['name'] as $index => $name) {
                $normalized[] = [
                    'field' => $field,
                    'name' => (string) $name,
                    'type' => (string) ($fileSet['type'][$index] ?? ''),
                    'tmp_name' => (string) ($fileSet['tmp_name'][$index] ?? ''),
                    'error' => (int) ($fileSet['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($fileSet['size'][$index] ?? 0),
                ];
            }
            continue;
        }

        $normalized[] = [
            'field' => $field,
            'name' => (string) $fileSet['name'],
            'type' => (string) ($fileSet['type'] ?? ''),
            'tmp_name' => (string) ($fileSet['tmp_name'] ?? ''),
            'error' => (int) ($fileSet['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileSet['size'] ?? 0),
        ];
    }

    return $normalized;
}

function build_subject(array $flatFields): string
{
    $formName = $flatFields['_seekoe_form_name'] ?? '';
    $pageTitle = $flatFields['_seekoe_page_title'] ?? 'Seekoe Website';
    $jobTitle = $flatFields['hidden'] ?? '';
    $company = $flatFields['company_name'] ?? '';

    if ($jobTitle !== '') {
        return 'New Career Application: ' . $jobTitle;
    }

    if ($formName === 'consultation-request') {
        return 'New Consultation Request from Seekoe';
    }

    if ($company !== '') {
        return 'New Lead Request: ' . $company;
    }

    return 'New Website Submission: ' . $pageTitle;
}

function build_text_body(array $flatFields, array $storedFiles): string
{
    $lines = ["New submission received from the Seekoe website.", ''];

    foreach ($flatFields as $key => $value) {
        if ($value === '') {
            continue;
        }

        $lines[] = $key . ': ' . $value;
    }

    if ($storedFiles) {
        $lines[] = '';
        $lines[] = 'Uploaded files:';
        foreach ($storedFiles as $file) {
            $lines[] = '- ' . $file['original_name'];
        }
    }

    return implode("\n", $lines);
}

function build_html_body(array $flatFields, array $storedFiles): string
{
    $rows = '';
    foreach ($flatFields as $key => $value) {
        if ($value === '') {
            continue;
        }

        $rows .= '<tr><th style="text-align:left;padding:8px;border:1px solid #d7dfeb;background:#f5f8fc;">' .
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8') .
            '</th><td style="padding:8px;border:1px solid #d7dfeb;">' .
            nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) .
            '</td></tr>';
    }

    $filesHtml = '';
    if ($storedFiles) {
        $fileItems = '';
        foreach ($storedFiles as $file) {
            $fileItems .= '<li>' . htmlspecialchars($file['original_name'], ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $filesHtml = '<p><strong>Uploaded files</strong></p><ul>' . $fileItems . '</ul>';
    }

    return '<div style="font-family:Arial,sans-serif;color:#17345c;">' .
        '<p>New submission received from the Seekoe website.</p>' .
        '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;max-width:760px;">' .
        $rows .
        '</table>' .
        $filesHtml .
        '</div>';
}

$recipient = getenv('SEEKOE_FORM_TO_EMAIL') ?: 'info@seekoe.com';
$senderDomain = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'seekoe.com'));
$senderDomain = preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $senderDomain) ? $senderDomain : 'seekoe.com';
$fromEmail = getenv('SEEKOE_FORM_FROM_EMAIL') ?: 'no-reply@' . $senderDomain;
$replyTo = sanitize_string((string) ($_POST['email'] ?? ''));

$flatFields = flatten_fields($_POST);

if ($flatFields['item_19__fluent_sf'] ?? '' !== '') {
    respond(200, [
        'success' => true,
        'message' => 'Your submission has been received.',
    ]);
}

$required = ['email'];
$missing = [];

foreach ($required as $field) {
    if (($flatFields[$field] ?? '') === '') {
        $missing[] = 'The ' . $field . ' field is required.';
    }
}

if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    $missing[] = 'Please enter a valid email address.';
}

if ($missing) {
    respond(422, [
        'success' => false,
        'message' => 'Please review the highlighted fields.',
        'errors' => $missing,
    ]);
}

$uploadRoot = __DIR__ . '/storage/form-submissions';
if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
    respond(500, [
        'success' => false,
        'message' => 'Unable to prepare form storage.',
    ]);
}

$submissionId = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
$submissionDir = $uploadRoot . '/' . date('Y/m');
if (!is_dir($submissionDir) && !mkdir($submissionDir, 0775, true) && !is_dir($submissionDir)) {
    respond(500, [
        'success' => false,
        'message' => 'Unable to prepare upload storage.',
    ]);
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
$storedFiles = [];
$attachments = [];

foreach (normalize_files_array($_FILES) as $file) {
    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
        continue;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(422, [
            'success' => false,
            'message' => 'One of the uploaded files could not be processed.',
        ]);
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        respond(422, [
            'success' => false,
            'message' => 'Please keep uploaded files under 10 MB each.',
        ]);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        respond(422, [
            'success' => false,
            'message' => 'Unsupported file type uploaded.',
        ]);
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($file['name']));
    $targetPath = $submissionDir . '/' . $submissionId . '-' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        respond(500, [
            'success' => false,
            'message' => 'We could not save one of the uploaded files.',
        ]);
    }

    $storedFiles[] = [
        'field' => $file['field'],
        'original_name' => $file['name'],
        'stored_name' => basename($targetPath),
        'path' => $targetPath,
        'size' => filesize($targetPath) ?: $file['size'],
    ];

    $attachments[] = [
        'name' => $file['name'],
        'path' => $targetPath,
        'type' => mime_content_type($targetPath) ?: 'application/octet-stream',
    ];
}

$record = [
    'submission_id' => $submissionId,
    'submitted_at' => gmdate('c'),
    'fields' => $flatFields,
    'files' => $storedFiles,
];

file_put_contents(
    $submissionDir . '/' . $submissionId . '.json',
    json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$subject = build_subject($flatFields);
$textBody = build_text_body($flatFields, $storedFiles);
$htmlBody = build_html_body($flatFields, $storedFiles);

$headers = [
    'MIME-Version: 1.0',
    'From: Seekoe Forms <' . $fromEmail . '>',
];

if ($replyTo !== '') {
    $headers[] = 'Reply-To: ' . $replyTo;
}

if ($attachments) {
    $mixedBoundary = 'mixed_' . md5((string) microtime(true));
    $altBoundary = 'alt_' . md5((string) microtime(true) . 'alt');

    $body = '--' . $mixedBoundary . "\r\n";
    $body .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n";
    $body .= '--' . $altBoundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= '--' . $altBoundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= '--' . $altBoundary . "--\r\n";

    foreach ($attachments as $attachment) {
        $fileContent = chunk_split(base64_encode((string) file_get_contents($attachment['path'])));
        $body .= '--' . $mixedBoundary . "\r\n";
        $body .= 'Content-Type: ' . $attachment['type'] . '; name="' . addslashes($attachment['name']) . '"' . "\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . addslashes($attachment['name']) . '"' . "\r\n\r\n";
        $body .= $fileContent . "\r\n";
    }

    $body .= '--' . $mixedBoundary . "--\r\n";
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
} else {
    $altBoundary = 'alt_' . md5((string) microtime(true));
    $body = '--' . $altBoundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= '--' . $altBoundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= '--' . $altBoundary . "--\r\n";
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';
}

$sent = mail($recipient, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    respond(500, [
        'success' => false,
        'message' => 'Your submission was saved, but the email could not be sent. Please verify PHP mail configuration.',
    ]);
}

respond(200, [
    'success' => true,
    'message' => ($flatFields['hidden'] ?? '') !== ''
        ? 'Your application has been sent. We will review it and get back to you.'
        : 'Your request has been sent successfully. We will follow up by email soon.',
]);
