<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: contact.php');
        exit;
    }

    $configFile = __DIR__ . '/config.php';

    if (!is_file($configFile)) {
        throw new RuntimeException('config.php was not found.');
    }

    require_once $configFile;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    mysqli_set_charset($conn, 'utf8mb4');

    /* Hidden spam field must stay empty. */
    if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
        header('Location: contact.php?sent=1&mail=0');
        exit;
    }

    /* One-time, server-side CAPTCHA verification. */
    $captchaToken = strtolower(
        trim((string) ($_POST['captcha_token'] ?? ''))
    );
    $captchaAnswer = trim((string) ($_POST['captcha_answer'] ?? ''));
    $captchaChallenges = $_SESSION['contact_captchas'] ?? [];
    $captchaChallenge = is_array($captchaChallenges)
        ? ($captchaChallenges[$captchaToken] ?? null)
        : null;

    // Consume the challenge immediately so the same answer cannot be reused.
    if (isset($_SESSION['contact_captchas'][$captchaToken])) {
        unset($_SESSION['contact_captchas'][$captchaToken]);
    }

    $captchaCreatedAt = is_array($captchaChallenge)
        ? (int) ($captchaChallenge['created_at'] ?? 0)
        : 0;
    $captchaExpectedAnswer = is_array($captchaChallenge)
        ? (string) ($captchaChallenge['answer'] ?? '')
        : '';
    $captchaIsValid = strlen($captchaToken) === 32
        && ctype_xdigit($captchaToken)
        && $captchaCreatedAt > 0
        && (time() - $captchaCreatedAt) <= 900
        && preg_match('/^[0-9]{1,2}$/', $captchaAnswer)
        && hash_equals($captchaExpectedAnswer, $captchaAnswer);

    if (!$captchaIsValid) {
        $_SESSION['contact_public_error'] =
            'Security answer is incorrect or expired. Please try again.';

        header('Location: contact.php?error=captcha');
        exit;
    }

    $siteId = defined('SITE_ID') ? (int) SITE_ID : 0;

    if ($siteId <= 0) {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./', '', $host);

        if ($host === '') {
            throw new RuntimeException('Unable to detect the website domain.');
        }

        $siteStmt = $conn->prepare(
            "SELECT id
             FROM oursites
             WHERE REPLACE(
                 REPLACE(
                     REPLACE(
                         REPLACE(LOWER(domain), 'https://', ''),
                         'http://', ''
                     ),
                     'www.', ''
                 ),
                 '/', ''
             ) = ?
             LIMIT 1"
        );

        if (!$siteStmt) {
            throw new RuntimeException('Website lookup could not be prepared.');
        }

        $siteStmt->bind_param('s', $host);
        $siteStmt->execute();
        $siteRow = $siteStmt->get_result()->fetch_assoc();
        $siteStmt->close();

        $siteId = (int) ($siteRow['id'] ?? 0);
    }

    if ($siteId <= 0) {
        throw new RuntimeException('Website ID could not be detected.');
    }

    $settingsStmt = $conn->prepare(
        "SELECT cps.form_fields,
                cps.notify_email,
                cps.form_heading,
                cps.status,
                o.site_name,
                o.domain
         FROM contact_page_settings AS cps
         LEFT JOIN oursites AS o ON o.id = cps.site_id
         WHERE cps.site_id = ?
         LIMIT 1"
    );

    if (!$settingsStmt) {
        throw new RuntimeException('Contact settings could not be prepared.');
    }

    $settingsStmt->bind_param('i', $siteId);
    $settingsStmt->execute();
    $settings = $settingsStmt->get_result()->fetch_assoc();
    $settingsStmt->close();

    if (!$settings) {
        throw new RuntimeException('Contact page settings were not found.');
    }

    if (($settings['status'] ?? 'active') !== 'active') {
        throw new RuntimeException('The contact page is inactive.');
    }

    $configuredFields = json_decode(
        (string) ($settings['form_fields'] ?? ''),
        true
    );

    if (!is_array($configuredFields)) {
        $configuredFields = [];
    }

    $formData = [];
    $replyEmail = '';

    foreach ($configuredFields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $fieldName = preg_replace(
            '/[^a-zA-Z0-9_]/',
            '',
            (string) ($field['name'] ?? '')
        );

        if ($fieldName === '') {
            continue;
        }

        $value = $_POST[$fieldName] ?? '';

        if (is_array($value)) {
            $value = implode(', ', array_map('trim', $value));
        }

        $value = trim((string) $value);

        if (!empty($field['required']) && $value === '') {
            $label = $field['label'] ?? $fieldName;
            throw new RuntimeException('Required field is empty: ' . $label);
        }

        if (($field['type'] ?? '') === 'tel' && $value !== '') {
            $countryCodeField = $fieldName . '_country_code';
            $countryCode = trim(
                (string) ($_POST[$countryCodeField] ?? '')
            );

            if (!preg_match('/^\+[1-9][0-9]{0,3}$/', $countryCode)) {
                throw new RuntimeException(
                    'Please enter a valid country code, for example +91.'
                );
            }

            if (!preg_match('/^[0-9().\-\s]+$/', $value)) {
                throw new RuntimeException(
                    'Please enter a valid phone number.'
                );
            }

            $phoneDigits = preg_replace('/\D/', '', $value);
            $phoneLength = strlen($phoneDigits);

            if ($phoneLength < 7 || $phoneLength > 15) {
                throw new RuntimeException(
                    'Phone number must contain 7 to 15 digits.'
                );
            }

            $value = $countryCode . ' ' . $value;
        }

        if (($field['type'] ?? '') === 'email' && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            $replyEmail = $value;
        }

        $formData[$fieldName] = $value;
    }

    if (empty($configuredFields)) {
        foreach ($_POST as $fieldName => $value) {
            if ($fieldName === 'website_url' || !is_scalar($value)) {
                continue;
            }

            $cleanName = preg_replace(
                '/[^a-zA-Z0-9_]/',
                '',
                (string) $fieldName
            );

            if ($cleanName === '') {
                continue;
            }

            $cleanValue = trim((string) $value);
            $formData[$cleanName] = $cleanValue;

            if (
                stripos($cleanName, 'email') !== false &&
                filter_var($cleanValue, FILTER_VALIDATE_EMAIL)
            ) {
                $replyEmail = $cleanValue;
            }
        }
    }

    if (empty($formData)) {
        throw new RuntimeException('No contact form data was received.');
    }

    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($formJson === false) {
        throw new RuntimeException('Contact data could not be encoded.');
    }

    $statusResult = $conn->query(
        "SHOW COLUMNS FROM contact_submissions LIKE 'status'"
    );

    if (!$statusResult) {
        throw new RuntimeException('Submission status could not be checked.');
    }

    $statusColumn = $statusResult->fetch_assoc();
    $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));
    $submissionStatus = strpos($statusType, "'unread'") !== false
        ? 'unread'
        : 'active';

    $ipAddress = substr(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        45
    );

    $insertStmt = $conn->prepare(
        "INSERT INTO contact_submissions
            (site_id, form_data, ip_address, status)
         VALUES (?, ?, ?, ?)"
    );

    if (!$insertStmt) {
        throw new RuntimeException('Submission could not be prepared.');
    }

    $insertStmt->bind_param(
        'isss',
        $siteId,
        $formJson,
        $ipAddress,
        $submissionStatus
    );

    if (!$insertStmt->execute()) {
        throw new RuntimeException('Submission could not be saved.');
    }

    $submissionId = (int) $insertStmt->insert_id;
    $insertStmt->close();

    /* Database save succeeds even when hosting mail is unavailable. */
    $recipient = trim((string) ($settings['notify_email'] ?? ''));

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = 'bala@mubychem.com';
    }

    $siteName = trim((string) ($settings['site_name'] ?? 'Molgal'));
    $domain = trim((string) ($settings['domain'] ?? 'molgal.com'));
    $subject = 'New Contact Form Submission - ' . $siteName;

    $messageLines = [
        'A new contact form submission was received.',
        '',
        'Submission ID: ' . $submissionId,
        'Website: ' . $domain,
        'Received: ' . date('d M Y, h:i A'),
        ''
    ];

    foreach ($formData as $fieldName => $value) {
        $messageLines[] =
            ucwords(str_replace('_', ' ', $fieldName)) . ': ' . $value;
    }

    $mailHost = strtolower(
        (string) ($_SERVER['HTTP_HOST'] ?? 'molgal.com')
    );
    $mailHost = preg_replace('/^www\./i', '', $mailHost);
    $mailHost = preg_replace('/[^a-z0-9.-]/', '', $mailHost);
    $fromEmail = 'noreply@' . ($mailHost ?: 'molgal.com');

    $headers = [
        'From: Molgal Contact Form <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];

    if ($replyEmail !== '') {
        $headers[] = 'Reply-To: ' . str_replace(
            ["\r", "\n"],
            '',
            $replyEmail
        );
    }

    $mailSent = false;

    if (function_exists('mail')) {
        $mailSent = @mail(
            $recipient,
            $subject,
            implode("\r\n", $messageLines),
            implode("\r\n", $headers),
            '-f' . $fromEmail
        );
    }

    if (!$mailSent) {
        error_log(
            'Contact submission #' . $submissionId .
            ' was saved, but mail failed for ' . $recipient
        );
    }

    header(
        'Location: contact.php?sent=1&mail=' .
        ($mailSent ? '1' : '0')
    );
    exit;

} catch (Throwable $exception) {
    error_log('Molgal contact form error: ' . $exception->getMessage());

    if (!headers_sent()) {
        header('Location: contact.php?error=1');
        exit;
    }

    http_response_code(500);
    echo 'Contact form could not be submitted.';
    exit;
}
