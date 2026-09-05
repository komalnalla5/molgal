<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

function formRedirect($location)
{
    header('Location: ' . $location);
    exit;
}

function sendWebsiteFormMail(
    $recipient,
    $subject,
    array $messageLines,
    $replyEmail,
    $fromLabel
) {
    if (!function_exists('mail')) {
        return false;
    }

    $mailHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $mailHost = preg_replace('/:\d+$/', '', $mailHost);
    $mailHost = preg_replace('/^www\./i', '', $mailHost);
    $mailHost = preg_replace('/[^a-z0-9.-]/', '', $mailHost);

    /* localhost is not a valid sender domain, so use the live domain locally. */
    $fromDomain = strpos($mailHost, '.') !== false
        ? $mailHost
        : 'molgal.com';
    $fromEmail = 'noreply@' . $fromDomain;

    $headers = [
        'From: ' . $fromLabel . ' <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    if (filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . str_replace(
            ["\r", "\n"],
            '',
            $replyEmail
        );
    }

    return @mail(
        $recipient,
        $subject,
        implode("\r\n", $messageLines),
        implode("\r\n", $headers),
        '-f' . $fromEmail
    );
}

function handleContactSubmission(mysqli $conn, $siteId)
{
    /* Hidden spam field must stay empty. */
    if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
        formRedirect('contact.php?sent=1&mail=0');
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

    /* Consume immediately so the same challenge cannot be reused. */
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
        formRedirect('contact.php?error=captcha');
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
        throw new InvalidArgumentException('The contact page is inactive.');
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
            $label = (string) ($field['label'] ?? $fieldName);
            throw new InvalidArgumentException(
                'Required field is empty: ' . $label
            );
        }

        if (($field['type'] ?? '') === 'tel' && $value !== '') {
            $countryCode = trim(
                (string) ($_POST[$fieldName . '_country_code'] ?? '')
            );

            if (!preg_match('/^\+[1-9][0-9]{0,3}$/', $countryCode)) {
                throw new InvalidArgumentException(
                    'Please enter a valid country code, for example +91.'
                );
            }

            if (!preg_match('/^[0-9().\-\s]+$/', $value)) {
                throw new InvalidArgumentException(
                    'Please enter a valid phone number.'
                );
            }

            $phoneDigits = preg_replace('/\D/', '', $value);
            $phoneLength = strlen($phoneDigits);
            if ($phoneLength < 7 || $phoneLength > 15) {
                throw new InvalidArgumentException(
                    'Phone number must contain 7 to 15 digits.'
                );
            }

            $value = $countryCode . ' ' . $value;
        }

        if (($field['type'] ?? '') === 'email' && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException(
                    'Please enter a valid email address.'
                );
            }
            $replyEmail = $value;
        }

        $formData[$fieldName] = $value;
    }

    /* Compatibility fallback for older settings without configured fields. */
    if (empty($configuredFields)) {
        $reservedFields = [
            'form_type',
            'website_url',
            'captcha_token',
            'captcha_answer',
            'csrf_token',
        ];

        foreach ($_POST as $fieldName => $value) {
            if (in_array($fieldName, $reservedFields, true) || !is_scalar($value)) {
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
        throw new InvalidArgumentException(
            'No contact form data was received.'
        );
    }

    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($formJson === false) {
        throw new RuntimeException('Contact data could not be encoded.');
    }

    /* Supports both the current unread/read enum and an older active enum. */
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
        throw new RuntimeException('Contact submission could not be prepared.');
    }

    $insertStmt->bind_param(
        'isss',
        $siteId,
        $formJson,
        $ipAddress,
        $submissionStatus
    );
    if (!$insertStmt->execute()) {
        throw new RuntimeException('Contact submission could not be saved.');
    }

    $submissionId = (int) $insertStmt->insert_id;
    $insertStmt->close();

    $recipient = trim((string) ($settings['notify_email'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = 'bala@mubychem.com';
    }

    $siteName = trim((string) ($settings['site_name'] ?? 'Molgal'));
    $domain = trim((string) ($settings['domain'] ?? 'molgal.com'));
    $messageLines = [
        'A new contact form submission was received.',
        '',
        'Submission ID: ' . $submissionId,
        'Website: ' . $domain,
        'Received: ' . date('d M Y, h:i A'),
        '',
    ];

    foreach ($formData as $fieldName => $value) {
        $messageLines[] =
            ucwords(str_replace('_', ' ', $fieldName)) . ': ' . $value;
    }

    $mailSent = sendWebsiteFormMail(
        $recipient,
        'New Contact Form Submission - ' . $siteName,
        $messageLines,
        $replyEmail,
        'Molgal Contact Form'
    );

    if (!$mailSent) {
        error_log(
            'Contact submission #' . $submissionId .
            ' was saved, but notification mail failed for ' . $recipient
        );
    }

    formRedirect(
        'contact.php?sent=1&mail=' . ($mailSent ? '1' : '0')
    );
}

function handleHomepageEnquiry(mysqli $conn, $siteId)
{
    $_SESSION['enquiry_old_input'] = [
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'email'      => trim((string) ($_POST['email'] ?? '')),
        'product_id' => (int) ($_POST['product_id'] ?? 0),
    ];

    if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
        unset($_SESSION['enquiry_old_input']);
        $_SESSION['enquiry_public_success'] =
            'Thank you for your enquiry. Our team will contact you shortly.';
        formRedirect('index.php#enquiry-form');
    }

    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['enquiry_csrf_token'] ?? '');
    if (
        $postedToken === '' ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        throw new InvalidArgumentException(
            'Your form session expired. Please refresh the page and try again.'
        );
    }

    $settingsStmt = $conn->prepare(
        "SELECT efs.name_required,
                efs.email_required,
                efs.product_required,
                efs.notify_email,
                efs.success_message,
                efs.status,
                o.site_name,
                o.domain
         FROM enquiry_form_settings AS efs
         INNER JOIN oursites AS o ON o.id = efs.site_id
         WHERE efs.site_id = ?
           AND o.status = 'active'
           AND o.deleted_at IS NULL
         LIMIT 1"
    );
    if (!$settingsStmt) {
        throw new RuntimeException('Enquiry settings could not be prepared.');
    }

    $settingsStmt->bind_param('i', $siteId);
    $settingsStmt->execute();
    $settings = $settingsStmt->get_result()->fetch_assoc();
    $settingsStmt->close();

    if (!$settings) {
        throw new RuntimeException(
            'Homepage enquiry settings were not found for this website.'
        );
    }

    if (($settings['status'] ?? 'active') !== 'active') {
        throw new InvalidArgumentException(
            'The enquiry form is currently unavailable.'
        );
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (!empty($settings['name_required']) && $name === '') {
        throw new InvalidArgumentException('Please enter your name.');
    }
    if (strlen($name) > 150) {
        throw new InvalidArgumentException(
            'Name cannot exceed 150 characters.'
        );
    }

    if (!empty($settings['email_required']) && $email === '') {
        throw new InvalidArgumentException('Please enter your email address.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            'Please enter a valid email address.'
        );
    }
    if (strlen($email) > 255) {
        throw new InvalidArgumentException(
            'Email cannot exceed 255 characters.'
        );
    }

    if (!empty($settings['product_required']) && $productId <= 0) {
        throw new InvalidArgumentException('Please select a product.');
    }

    $product = null;
    if ($productId > 0) {
        $productStmt = $conn->prepare(
            "SELECT id, brand_name, product_code, product_name
             FROM products
             WHERE id = ?
               AND site_id = ?
               AND status = 'active'
               AND is_deleted = 0
             LIMIT 1"
        );
        if (!$productStmt) {
            throw new RuntimeException(
                'Product verification could not be prepared.'
            );
        }

        $productStmt->bind_param('ii', $productId, $siteId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        $productStmt->close();

        if (!$product) {
            throw new InvalidArgumentException(
                'The selected product is unavailable. Please select another product.'
            );
        }
    }

    $productCode = trim((string) ($product['product_code'] ?? ''));
    $productLabel = trim(
        (string) ($product['brand_name'] ?? '') . ' ' . $productCode
    );
    if ($productLabel === '' && $product) {
        $productLabel = trim((string) ($product['product_name'] ?? 'Product'));
    }

    $formData = [
        'Name'    => $name,
        'Email'   => $email,
        'Product' => $productLabel,
    ];
    $formJson = json_encode(
        $formData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($formJson === false) {
        throw new RuntimeException('Enquiry data could not be encoded.');
    }

    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $productIdForDatabase = $product ? (int) $product['id'] : null;

    $insertStmt = $conn->prepare(
        "INSERT INTO product_enquiries
            (site_id, product_id, name, email, product_code, form_data,
             ip_address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'unread')"
    );
    if (!$insertStmt) {
        throw new RuntimeException('Enquiry could not be prepared for saving.');
    }

    $insertStmt->bind_param(
        'iisssss',
        $siteId,
        $productIdForDatabase,
        $name,
        $email,
        $productCode,
        $formJson,
        $ipAddress
    );
    if (!$insertStmt->execute()) {
        throw new RuntimeException('Enquiry could not be saved.');
    }

    $enquiryId = (int) $insertStmt->insert_id;
    $insertStmt->close();

    $recipient = trim((string) ($settings['notify_email'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = 'bala@mubychem.com';
    }

    $websiteName = trim((string) ($settings['site_name'] ?? 'Website'));
    $websiteDomain = trim((string) ($settings['domain'] ?? ''));
    $messageLines = [
        'A new product enquiry was received.',
        '',
        'Enquiry ID: ' . $enquiryId,
        'Website: ' . $websiteDomain,
        'Name: ' . ($name !== '' ? $name : '-'),
        'Email: ' . ($email !== '' ? $email : '-'),
        'Product: ' . ($productLabel !== '' ? $productLabel : '-'),
        'Received: ' . date('d M Y, h:i A'),
    ];

    $mailSent = sendWebsiteFormMail(
        $recipient,
        'New Product Enquiry - ' . $websiteName,
        $messageLines,
        $email,
        'Website Enquiry'
    );

    if (!$mailSent) {
        error_log(
            'Product enquiry #' . $enquiryId .
            ' was saved, but notification mail failed for ' . $recipient
        );
    }

    unset($_SESSION['enquiry_old_input']);
    $_SESSION['enquiry_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['enquiry_public_success'] = trim(
        (string) ($settings['success_message'] ?? '')
    ) ?: 'Thank you for your enquiry. Our team will contact you shortly.';

    formRedirect('index.php#enquiry-form');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    formRedirect('index.php');
}

$formType = strtolower(trim((string) ($_POST['form_type'] ?? '')));

/* Temporary compatibility while old cached form HTML is still in browsers. */
if (!in_array($formType, ['contact', 'enquiry'], true)) {
    if (isset($_POST['captcha_token']) || isset($_POST['captcha_answer'])) {
        $formType = 'contact';
    } elseif (isset($_POST['csrf_token']) || isset($_POST['product_id'])) {
        $formType = 'enquiry';
    }
}

try {
    require_once __DIR__ . '/config.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    mysqli_set_charset($conn, 'utf8mb4');

    $siteId = defined('SITE_ID') ? (int) SITE_ID : 0;
    if ($siteId <= 0) {
        throw new RuntimeException('Website ID could not be detected.');
    }

    if ($formType === 'contact') {
        handleContactSubmission($conn, $siteId);
    }

    if ($formType === 'enquiry') {
        handleHomepageEnquiry($conn, $siteId);
    }

    throw new InvalidArgumentException('Invalid form type.');
} catch (Throwable $exception) {
    error_log(
        'Molgal ' . ($formType ?: 'unknown') .
        ' form error: ' . $exception->getMessage()
    );

    $publicMessage = $exception instanceof InvalidArgumentException
        ? $exception->getMessage()
        : 'Your form could not be submitted. Please try again later.';

    if ($formType === 'contact') {
        $_SESSION['contact_public_error'] = $publicMessage;
        formRedirect('contact.php?error=1');
    }

    $_SESSION['enquiry_public_error'] = $publicMessage;
    formRedirect('index.php#enquiry-form');
}
