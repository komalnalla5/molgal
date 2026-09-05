<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CAPTCHA is session-specific and must never be served from page cache.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie', false);

require_once __DIR__ . '/config.php';

$siteId = defined('SITE_ID') ? (int) SITE_ID : 0;

if ($siteId <= 0) {
    http_response_code(500);
    exit('Site configuration is invalid.');
}

$settingsStmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM contact_page_settings
     WHERE site_id = ?
       AND status = 'active'
     LIMIT 1"
);

if (!$settingsStmt) {
    http_response_code(500);
    exit('Unable to load the contact page.');
}

mysqli_stmt_bind_param($settingsStmt, 'i', $siteId);
mysqli_stmt_execute($settingsStmt);
$settingsResult = mysqli_stmt_get_result($settingsStmt);
$settings = mysqli_fetch_assoc($settingsResult);
mysqli_stmt_close($settingsStmt);

if (!$settings) {
    http_response_code(404);
    exit('Contact page settings were not found or are inactive.');
}

$bannerTitle = !empty($settings['banner_title'])
    ? (string) $settings['banner_title']
    : 'Contact Us';

$bannerBreadcrumb = !empty($settings['banner_breadcrumb'])
    ? (string) $settings['banner_breadcrumb']
    : 'Contact Us';

$formHeading = !empty($settings['form_heading'])
    ? (string) $settings['form_heading']
    : 'Contact Us';

$formSubheading = !empty($settings['form_subheading'])
    ? trim((string) $settings['form_subheading'])
    : 'Your email address will not be published. Required fields are marked *';

$submitButtonText = !empty($settings['submit_button_text'])
    ? (string) $settings['submit_button_text']
    : 'Send Messages';

$bannerImage = 'assets/img/contact-banner.webp';

if (!empty($settings['banner_image'])) {
    $configuredBanner = trim((string) $settings['banner_image']);

    if (preg_match('#^https?://#i', $configuredBanner)) {
        $bannerImage = $configuredBanner;
    } elseif (strpos($configuredBanner, 'uploads/') === 0) {
        $bannerImage = defined('ADMIN_BASE_URL')
            ? rtrim(ADMIN_BASE_URL, '/') . '/' . ltrim($configuredBanner, '/')
            : $configuredBanner;
    } else {
        $bannerImage = $configuredBanner;
    }
}

function decodeContactJson($value)
{
    if (empty($value)) {
        return [];
    }

    $decoded = json_decode((string) $value, true);

    return is_array($decoded) ? $decoded : [];
}

function contactEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderContactValue($value)
{
    $value = trim((string) $value);
    $escapedValue = contactEscape($value);

    if ($value === '') {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '<a href="mailto:' . $escapedValue . '">' .
            $escapedValue . '</a>';
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return '<a href="' . $escapedValue .
            '" target="_blank" rel="noopener noreferrer">' .
            $escapedValue . '</a>';
    }

    if (preg_match('/^[+0-9()\-\s]{7,}$/', $value)) {
        $phone = preg_replace('/[^+0-9]/', '', $value);

        return '<a href="tel:' . contactEscape($phone) . '">' .
            $escapedValue . '</a>';
    }

    return $escapedValue;
}

function contactFieldType($type)
{
    $allowedTypes = ['text', 'email', 'tel', 'url', 'number'];

    return in_array($type, $allowedTypes, true) ? $type : 'text';
}

$contactBlocks = decodeContactJson($settings['contact_blocks'] ?? '');
$locations = decodeContactJson($settings['locations'] ?? '');
$formFields = decodeContactJson($settings['form_fields'] ?? '');

/*
|--------------------------------------------------------------------------
| One-time server-side CAPTCHA challenge
|--------------------------------------------------------------------------
| A token-keyed challenge supports multiple browser tabs and expires after
| 15 minutes. The correct answer never appears in the HTML.
*/
if (!isset($_SESSION['contact_captchas']) ||
    !is_array($_SESSION['contact_captchas'])) {
    $_SESSION['contact_captchas'] = [];
}

$captchaNow = time();

foreach ($_SESSION['contact_captchas'] as $storedToken => $challenge) {
    $createdAt = (int) ($challenge['created_at'] ?? 0);

    if ($createdAt <= 0 || ($captchaNow - $createdAt) > 900) {
        unset($_SESSION['contact_captchas'][$storedToken]);
    }
}

// Keep the session small if the page is repeatedly refreshed.
if (count($_SESSION['contact_captchas']) >= 10) {
    $_SESSION['contact_captchas'] = array_slice(
        $_SESSION['contact_captchas'],
        -9,
        null,
        true
    );
}

$captchaFirstNumber = random_int(2, 9);
$captchaSecondNumber = random_int(1, 9);
$captchaToken = bin2hex(random_bytes(16));

$_SESSION['contact_captchas'][$captchaToken] = [
    'answer' => $captchaFirstNumber + $captchaSecondNumber,
    'created_at' => $captchaNow,
];

$visibleLocations = array_values(
    array_filter(
        $locations,
        function ($location) {
            return is_array($location) && (
                !empty($location['title']) ||
                !empty($location['address']) ||
                !empty($location['map_embed'])
            );
        }
    )
);

$sent = (
    ($_GET['sent'] ?? '') === '1' ||
    ($_GET['submitted'] ?? '') === '1' ||
    ($_GET['success'] ?? '') === '1'
);

$error = isset($_GET['error']) && $_GET['error'] !== '';
$mailWarning = $sent && (($_GET['mail'] ?? '') === '0');
$publicErrorMessage = trim(
    (string) ($_SESSION['contact_public_error'] ?? '')
);
unset($_SESSION['contact_public_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo contactEscape($bannerTitle); ?></title>

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/css/font-awesome-all.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .contact-page-sections {
            display: flex;
            flex-direction: column;
            gap: 34px;
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 42px 24px 64px;
        }

        .contact-info-section .info-boxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
            width: 100%;
            flex: none;
        }

        .contact-info-section .info-card {
            display: flex;
            flex: none;
            flex-direction: column;
            justify-content: flex-start;
            min-width: 0;
            min-height: 0;
            height: 100%;
            padding: 22px 18px;
            border: 1px solid #ececec;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 7px 22px rgba(15, 28, 55, 0.07);
            text-align: center;
        }

        .contact-info-section .info-card .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 13px;
            border-radius: 50%;
            font-size: 25px;
        }

        .contact-info-section .info-card h3 {
            margin: 0 0 8px;
            font-size: 19px;
            line-height: 1.3;
        }

        .contact-info-section .info-card p {
            margin: 0 0 4px !important;
            overflow-wrap: anywhere;
            line-height: 1.55;
        }

        .contact-form-section .contact-form {
            width: 100%;
            max-width: none;
            flex: none;
            padding: 32px;
            border: 1px solid #ece6e4;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 26px rgba(15, 28, 55, 0.08);
        }

        .contact-form-section .contact-form > h2,
        .location-section-heading h2 {
            margin-top: 0;
            color: #07294d;
            font-family: inherit;
            font-size: 24px;
            font-weight: 600;
            line-height: 1.3;
        }

        .contact-workspace-section {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
            align-items: start;
            gap: 24px;
        }

        .contact-workspace-section.single-column {
            grid-template-columns: 1fr;
        }

        .contact-workspace-section > section {
            min-width: 0;
        }

        .location-section {
            padding: 32px;
            border: 1px solid #ece6e4;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 26px rgba(15, 28, 55, 0.08);
        }

        .location-section-heading {
            max-width: 720px;
            margin: 0 0 18px;
            text-align: left;
        }

        .location-section-heading .section-kicker {
            display: block;
            margin-bottom: 5px;
            color: #873d33;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .location-section-heading h2 {
            margin: 0;
        }

        .location-tabs {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 10px;
            margin-bottom: 18px;
        }

        .location-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 48px;
            padding: 10px 20px;
            border: 1px solid #d8c8c4;
            border-radius: 10px;
            background: #ffffff;
            color: #263043;
            font: inherit;
            font-size: 15px;
            font-weight: 650;
            cursor: pointer;
            transition: background-color 0.2s ease, border-color 0.2s ease,
                color 0.2s ease, box-shadow 0.2s ease;
        }

        .location-tab:hover,
        .location-tab:focus-visible {
            border-color: #873d33;
            outline: none;
            box-shadow: 0 0 0 3px rgba(135, 61, 51, 0.12);
        }

        .location-tab.active {
            border-color: #873d33;
            background: #873d33;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(135, 61, 51, 0.2);
        }

        .location-card {
            overflow: hidden;
            border: 1px solid #e8e8e8;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: none;
        }

        .location-card-head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 22px 24px;
            border-bottom: 1px solid #eeeeee;
        }

        .location-card-head.no-address {
            align-items: center;
        }

        .location-card-head.no-address .location-card-title {
            margin-bottom: 0;
            font-size: 18px;
            line-height: 1.35;
        }

        .location-card-icon {
            display: inline-flex;
            flex: 0 0 42px;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(135, 61, 51, 0.11);
            color: #873d33;
            font-size: 18px;
        }

        .location-card-title {
            margin: 0 0 5px;
            color: #17233a;
            font-size: 20px;
            font-weight: 700;
        }

        .location-card-address {
            margin: 0;
            color: #667085;
            line-height: 1.6;
        }

        .location-map iframe {
            display: block;
            width: 100%;
            height: 488px;
            border: 0;
        }

        .location-map-empty {
            padding: 42px 24px;
            color: #667085;
            text-align: center;
        }

        .contact-form .contact-form-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 26px;
        }

        .contact-form .contact-field {
            min-width: 0;
        }

        .contact-form .contact-field.full {
            grid-column: 1 / -1;
        }

        .contact-form .contact-field-label {
            display: block;
            margin: 0 0 7px;
            color: #333333;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.35;
        }

        .contact-form .required-marker {
            margin-left: 3px;
            color: #c62828;
            font-size: 16px;
            font-weight: 700;
        }

        .contact-form .form-control,
        .contact-form .form-select {
            width: 100%;
            min-height: 41px;
            padding: 8px 15px;
            border: 1px solid #cccccc;
            border-radius: 10px;
            background-color: #ffffff;
            font: inherit;
        }

        .contact-form textarea.form-control {
            height: 150px;
            min-height: 150px;
            resize: vertical;
        }

        .contact-form .form-control:focus,
        .contact-form .form-select:focus {
            border-color: #873d33;
            box-shadow: 0 0 0 3px rgba(135, 61, 51, 0.12);
            outline: none;
        }

        .contact-form .field-validation-message {
            display: none;
            margin-top: 6px;
            color: #c62828;
            font-size: 12px;
            line-height: 1.35;
        }

        .contact-form .form-control[aria-invalid="true"],
        .contact-form .form-select[aria-invalid="true"] {
            border-color: #c62828;
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.10);
        }

        .contact-form .field-validation-message:not(:empty) {
            display: block;
        }

        .contact-form .phone-input-group {
            display: flex;
            align-items: stretch;
            gap: 10px;
        }

        .contact-form .phone-input-group .country-code-input {
            flex: 0 0 112px;
            width: 112px;
            text-align: center;
        }

        .contact-form .phone-input-group .phone-number-input {
            flex: 1 1 auto;
            min-width: 0;
        }

        .contact-form .captcha-box {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 14px;
            border: 1px solid #e0d3d0;
            border-radius: 10px;
            background: #faf7f6;
        }

        .contact-form .captcha-question {
            flex: 0 0 auto;
            min-width: 125px;
            color: #873d33;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .contact-form .captcha-answer {
            flex: 0 1 180px;
            max-width: 180px;
        }

        .contact-form .captcha-help {
            margin: 7px 0 0;
            color: #666666;
            font-size: 12px;
        }

        .contact-form .form-submit {
            width: auto;
            min-width: 145px;
            min-height: 41px;
            padding: 8px 18px;
            margin: 0;
            border-radius: 8px;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .contact-workspace-section {
                grid-template-columns: 1fr;
            }

            .contact-info-section .info-boxes {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .contact-form-section .contact-form {
                padding: 28px;
            }

            .location-section {
                padding: 28px;
            }
        }

        @media (max-width: 767px) {
            .contact-page-sections {
                gap: 28px;
                padding: 30px 16px 48px;
            }

            .contact-form .contact-form-fields {
                grid-template-columns: 1fr;
            }

            .contact-form .contact-field,
            .contact-form .contact-field.full {
                grid-column: 1;
            }

            .contact-form .phone-input-group .country-code-input {
                flex-basis: 96px;
                width: 96px;
            }

            .contact-form .captcha-box {
                align-items: stretch;
                flex-direction: column;
            }

            .contact-form .captcha-answer {
                flex-basis: auto;
                width: 100%;
                max-width: none;
            }

            .location-map iframe {
                height: 330px;
            }
        }

        @media (max-width: 520px) {
            .contact-info-section .info-boxes {
                grid-template-columns: 1fr;
            }

            .contact-form-section .contact-form {
                padding: 22px 18px;
            }

            .location-section {
                padding: 22px 18px;
            }

            .location-tab {
                width: 100%;
            }

            .location-card-head {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header-2.php'; ?>

<section
    class="hero-section"
    style="background-image:linear-gradient(rgba(0,0,0,.62),rgba(0,0,0,.62)),url('<?php echo contactEscape($bannerImage); ?>');"
>
    <div class="hero-content">
        <h1><?php echo contactEscape($bannerTitle); ?></h1>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center">
                <li class="breadcrumb-item">
                    <a href="index.php">
                        <i class="fa-solid fa-house me-2"></i>Home
                    </a>
                </li>

                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo contactEscape($bannerBreadcrumb); ?>
                </li>
            </ol>
        </nav>
    </div>
</section>

<main>
    <div class="contact-page-sections">
        <section class="contact-info-section" aria-label="Contact information">
            <div class="info-boxes">
            <?php foreach ($contactBlocks as $block): ?>
                <?php
                if (!is_array($block)) {
                    continue;
                }

                $blockTitle = trim((string) ($block['title'] ?? ''));
                $blockLines = isset($block['lines']) && is_array($block['lines'])
                    ? $block['lines']
                    : [];

                if ($blockTitle === '' && empty($blockLines)) {
                    continue;
                }

                $blockIcon = !empty($block['icon'])
                    ? (string) $block['icon']
                    : 'fa-solid fa-circle-info';
                ?>

                <article class="info-card">
                    <div class="icon" aria-hidden="true">
                        <i class="<?php echo contactEscape($blockIcon); ?>"></i>
                    </div>

                    <?php if ($blockTitle !== ''): ?>
                        <h3><?php echo contactEscape($blockTitle); ?></h3>
                    <?php endif; ?>

                    <?php foreach ($blockLines as $line): ?>
                        <?php if (trim((string) $line) === '') continue; ?>
                        <p class="mb-1"><?php echo renderContactValue($line); ?></p>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
            </div>
        </section>

        <section
            class="contact-workspace-section<?php echo empty($visibleLocations) ? ' single-column' : ''; ?>"
            aria-label="Contact form and locations"
        >
        <section class="contact-form-section" aria-labelledby="contactFormHeading">
        <div class="contact-form">
            <h2 id="contactFormHeading"><?php echo contactEscape($formHeading); ?></h2>

            <?php if ($formSubheading !== ''): ?>
                <p><?php echo contactEscape($formSubheading); ?></p>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    Thank you! Your message has been submitted successfully.
                </div>

                <?php if ($mailWarning): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Your enquiry has been saved, but the email notification
                        could not be sent by the server.
                    </div>
                <?php endif; ?>
            <?php elseif ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?php echo contactEscape(
                        $publicErrorMessage !== ''
                            ? $publicErrorMessage
                            : 'Something went wrong. Please check the required fields and try again.'
                    ); ?>
                </div>
            <?php endif; ?>

            <form
                action="submit_form.php"
                method="POST"
                id="contactPublicForm"
                class="contact-form-fields"
                novalidate
            >
                <input type="hidden" name="form_type" value="contact">

                <input
                    type="text"
                    name="website_url"
                    value=""
                    autocomplete="off"
                    tabindex="-1"
                    aria-hidden="true"
                    class="d-none"
                >

                <?php $visibleFieldIndex = 0; ?>
                <?php foreach ($formFields as $field): ?>
                    <?php
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

                    $type = strtolower((string) ($field['type'] ?? 'text'));
                    $label = !empty($field['label'])
                        ? (string) $field['label']
                        : ucwords(str_replace('_', ' ', $fieldName));
                    $placeholder = !empty($field['placeholder'])
                        ? (string) $field['placeholder']
                        : $label;
                    $required = !empty($field['required']);
                    $fieldId = 'field_' . $fieldName;
                    $fieldErrorId = $fieldId . '_error';

                    /* Reference design: first two fields share one row. */
                    $fullWidth = $type === 'textarea' || $visibleFieldIndex >= 2;
                    $visibleFieldIndex++;
                    ?>

                    <div class="contact-field<?php echo $fullWidth ? ' full' : ''; ?>">
                        <label
                            for="<?php echo contactEscape($fieldId); ?>"
                            class="contact-field-label"
                        >
                            <?php echo contactEscape($label); ?>

                            <?php if ($required): ?>
                                <span class="required-marker" aria-hidden="true">*</span>
                                <span class="visually-hidden"> required</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($type === 'textarea'): ?>
                            <textarea
                                id="<?php echo contactEscape($fieldId); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-control"
                                rows="6"
                                placeholder="<?php echo contactEscape($placeholder); ?>"
                                data-field-label="<?php echo contactEscape($label); ?>"
                                aria-required="<?php echo $required ? 'true' : 'false'; ?>"
                                aria-describedby="<?php echo contactEscape($fieldErrorId); ?>"
                                aria-invalid="false"
                                <?php echo $required ? 'required' : ''; ?>
                            ></textarea>

                        <?php elseif ($type === 'select'): ?>
                            <select
                                id="<?php echo contactEscape($fieldId); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-select"
                                data-field-label="<?php echo contactEscape($label); ?>"
                                aria-required="<?php echo $required ? 'true' : 'false'; ?>"
                                aria-describedby="<?php echo contactEscape($fieldErrorId); ?>"
                                aria-invalid="false"
                                <?php echo $required ? 'required' : ''; ?>
                            >
                                <option value="">
                                    <?php echo contactEscape($placeholder); ?>
                                </option>

                                <?php
                                $options = array_filter(
                                    array_map(
                                        'trim',
                                        explode(',', (string) ($field['options'] ?? ''))
                                    ),
                                    'strlen'
                                );
                                ?>

                                <?php foreach ($options as $option): ?>
                                    <option value="<?php echo contactEscape($option); ?>">
                                        <?php echo contactEscape($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($type === 'tel'): ?>
                            <?php
                            $countryCodeFieldId = $fieldId . '_country_code';
                            $countryCodeErrorId = $countryCodeFieldId . '_error';
                            ?>

                            <div class="phone-input-group" data-phone-group>
                                <input
                                    type="text"
                                    id="<?php echo contactEscape($countryCodeFieldId); ?>"
                                    name="<?php echo contactEscape($fieldName . '_country_code'); ?>"
                                    class="form-control country-code-input"
                                    value="+91"
                                    placeholder="+91"
                                    maxlength="5"
                                    inputmode="tel"
                                    autocomplete="tel-country-code"
                                    pattern="\+[1-9][0-9]{0,3}"
                                    title="Enter a country code such as +91"
                                    data-field-label="Country code"
                                    aria-label="Country code"
                                    aria-required="<?php echo $required ? 'true' : 'false'; ?>"
                                    aria-describedby="<?php echo contactEscape($countryCodeErrorId); ?>"
                                    aria-invalid="false"
                                    <?php echo $required ? 'required' : ''; ?>
                                >

                                <input
                                    type="tel"
                                    id="<?php echo contactEscape($fieldId); ?>"
                                    name="<?php echo contactEscape($fieldName); ?>"
                                    class="form-control phone-number-input"
                                    placeholder="<?php echo contactEscape($placeholder); ?>"
                                    inputmode="tel"
                                    autocomplete="tel-national"
                                    data-field-label="<?php echo contactEscape($label); ?>"
                                    aria-required="<?php echo $required ? 'true' : 'false'; ?>"
                                    aria-describedby="<?php echo contactEscape($fieldErrorId); ?>"
                                    aria-invalid="false"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                            </div>

                            <div
                                id="<?php echo contactEscape($countryCodeErrorId); ?>"
                                class="field-validation-message"
                                aria-live="polite"
                            ></div>

                        <?php else: ?>
                            <input
                                type="<?php echo contactEscape(contactFieldType($type)); ?>"
                                id="<?php echo contactEscape($fieldId); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-control"
                                placeholder="<?php echo contactEscape($placeholder); ?>"
                                data-field-label="<?php echo contactEscape($label); ?>"
                                aria-required="<?php echo $required ? 'true' : 'false'; ?>"
                                aria-describedby="<?php echo contactEscape($fieldErrorId); ?>"
                                aria-invalid="false"
                                <?php echo $required ? 'required' : ''; ?>
                            >
                        <?php endif; ?>

                        <div
                            id="<?php echo contactEscape($fieldErrorId); ?>"
                            class="field-validation-message"
                            aria-live="polite"
                        ></div>
                    </div>
                <?php endforeach; ?>

                <div class="contact-field full">
                    <label for="contactCaptchaAnswer" class="contact-field-label">
                        Security Check
                        <span class="required-marker" aria-hidden="true">*</span>
                        <span class="visually-hidden"> required</span>
                    </label>

                    <input
                        type="hidden"
                        name="captcha_token"
                        value="<?php echo contactEscape($captchaToken); ?>"
                    >

                    <div class="captcha-box">
                        <div class="captcha-question" aria-hidden="true">
                            <?php echo (int) $captchaFirstNumber; ?> +
                            <?php echo (int) $captchaSecondNumber; ?> = ?
                        </div>

                        <input
                            type="number"
                            id="contactCaptchaAnswer"
                            name="captcha_answer"
                            class="form-control captcha-answer"
                            min="0"
                            max="99"
                            step="1"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="Answer"
                            data-field-label="Security answer"
                            aria-label="Answer the security question"
                            aria-required="true"
                            aria-describedby="contactCaptchaAnswer_error contactCaptchaHelp"
                            aria-invalid="false"
                            required
                        >
                    </div>

                    <p id="contactCaptchaHelp" class="captcha-help">
                        Please solve this simple question before sending your enquiry.
                    </p>

                    <div
                        id="contactCaptchaAnswer_error"
                        class="field-validation-message"
                        aria-live="polite"
                    ></div>
                </div>

                <div class="contact-field full">
                    <button
                        type="submit"
                        class="form-submit"
                        id="contactSubmitButton"
                    >
                        <?php echo contactEscape($submitButtonText); ?>
                    </button>
                </div>
            </form>
        </div>
        </section>

        <?php if (!empty($visibleLocations)): ?>
        <section class="location-section" aria-labelledby="locationsHeading">
            <div class="location-section-heading">
                <span class="section-kicker">Find us</span>
                <h2 id="locationsHeading">Our Locations</h2>
            </div>

            <div class="location-tabs" role="tablist" aria-label="Our locations">
                <?php foreach ($visibleLocations as $locationIndex => $location): ?>
                    <?php
                    $locationNumber = $locationIndex + 1;
                    $locationTitle = trim((string) ($location['title'] ?? ''));

                    if ($locationTitle === '') {
                        $locationTitle = 'Location ' . $locationNumber;
                    }

                    $locationTabId = 'locationTab' . $locationNumber;
                    $locationPanelId = 'locationPanel' . $locationNumber;
                    $locationIsActive = $locationIndex === 0;
                    ?>

                    <button
                        type="button"
                        id="<?php echo contactEscape($locationTabId); ?>"
                        class="location-tab<?php echo $locationIsActive ? ' active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $locationIsActive ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo contactEscape($locationPanelId); ?>"
                        tabindex="<?php echo $locationIsActive ? '0' : '-1'; ?>"
                        data-location-tab="<?php echo contactEscape($locationPanelId); ?>"
                    >
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span><?php echo contactEscape($locationTitle); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="location-panels">
                <?php foreach ($visibleLocations as $locationIndex => $location): ?>
                    <?php
                    $locationNumber = $locationIndex + 1;
                    $locationTitle = trim((string) ($location['title'] ?? ''));

                    if ($locationTitle === '') {
                        $locationTitle = 'Location ' . $locationNumber;
                    }

                    $locationAddress = trim((string) ($location['address'] ?? ''));
                    $locationMap = trim((string) ($location['map_embed'] ?? ''));
                    $locationTabId = 'locationTab' . $locationNumber;
                    $locationPanelId = 'locationPanel' . $locationNumber;
                    $locationIsActive = $locationIndex === 0;
                    ?>

                    <article
                        id="<?php echo contactEscape($locationPanelId); ?>"
                        class="location-panel<?php echo $locationIsActive ? ' active' : ''; ?>"
                        role="tabpanel"
                        aria-labelledby="<?php echo contactEscape($locationTabId); ?>"
                        <?php echo $locationIsActive ? '' : 'hidden'; ?>
                    >
                        <div class="location-card">
                            <div class="location-card-head<?php echo $locationAddress === '' ? ' no-address' : ''; ?>">
                                <span class="location-card-icon" aria-hidden="true">
                                    <i class="fa-solid fa-location-dot"></i>
                                </span>

                                <div>
                                    <h3 class="location-card-title">
                                        <?php echo contactEscape($locationTitle); ?>
                                    </h3>

                                    <?php if ($locationAddress !== ''): ?>
                                        <p class="location-card-address">
                                            <?php echo contactEscape($locationAddress); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($locationMap !== ''): ?>
                                <div class="location-map">
                                    <iframe
                                        src="<?php echo contactEscape($locationMap); ?>"
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        title="<?php echo contactEscape($locationTitle); ?> map"
                                    ></iframe>
                                </div>
                            <?php else: ?>
                                <div class="location-map-empty">
                                    Map is not available for this location.
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var locationTabs = Array.prototype.slice.call(
        document.querySelectorAll('[data-location-tab]')
    );

    function activateLocationTab(selectedTab, moveFocus) {
        locationTabs.forEach(function (tab) {
            var isSelected = tab === selectedTab;
            var panelId = tab.getAttribute('data-location-tab');
            var panel = panelId ? document.getElementById(panelId) : null;

            tab.classList.toggle('active', isSelected);
            tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            tab.tabIndex = isSelected ? 0 : -1;

            if (panel) {
                panel.classList.toggle('active', isSelected);
                panel.hidden = !isSelected;
            }
        });

        if (moveFocus) {
            selectedTab.focus({ preventScroll: true });
        }
    }

    locationTabs.forEach(function (tab, tabIndex) {
        tab.addEventListener('click', function () {
            activateLocationTab(tab, false);
        });

        tab.addEventListener('keydown', function (event) {
            var nextIndex = tabIndex;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (tabIndex + 1) % locationTabs.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (tabIndex - 1 + locationTabs.length) % locationTabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = locationTabs.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activateLocationTab(locationTabs[nextIndex], true);
        });
    });

    var form = document.getElementById('contactPublicForm');
    var button = document.getElementById('contactSubmitButton');

    if (!form || !button) {
        return;
    }

    var fields = Array.prototype.slice.call(
        form.querySelectorAll('.form-control, .form-select')
    );

    function validationMessage(field) {
        var label = field.getAttribute('data-field-label') || 'This field';

        if (field.validity.valueMissing) {
            return label + ' is required.';
        }

        if (field.validity.customError) {
            return field.validationMessage;
        }

        if (
            field.validity.patternMismatch &&
            field.classList.contains('country-code-input')
        ) {
            return 'Enter a valid country code, for example +91.';
        }

        if (field.validity.typeMismatch && field.type === 'email') {
            return 'Please enter a valid email address.';
        }

        if (field.validity.typeMismatch) {
            return 'Please enter a valid ' + label.toLowerCase() + '.';
        }

        if (field.validity.badInput) {
            return 'Please enter a valid value for ' + label.toLowerCase() + '.';
        }

        return 'Please check ' + label.toLowerCase() + '.';
    }

    function updateFieldValidation(field, force) {
        var messageIds = (field.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(Boolean);
        var message = null;

        messageIds.some(function (messageId) {
            var candidate = document.getElementById(messageId);

            if (candidate && candidate.classList.contains('field-validation-message')) {
                message = candidate;
                return true;
            }

            return false;
        });
        var shouldShow = force || field.dataset.touched === '1';
        var invalid = !field.checkValidity();

        field.setAttribute('aria-invalid', invalid && shouldShow ? 'true' : 'false');

        if (message) {
            message.textContent = invalid && shouldShow
                ? validationMessage(field)
                : '';
        }

        return !invalid;
    }

    function syncPhoneGroup(group) {
        var countryCode = group.querySelector('.country-code-input');
        var phoneNumber = group.querySelector('.phone-number-input');

        if (!countryCode || !phoneNumber) {
            return;
        }

        var phoneValue = phoneNumber.value.trim();
        var countryValue = countryCode.value.trim();
        var countryCodeRequired = phoneNumber.required || phoneValue !== '';

        countryCode.required = countryCodeRequired;
        countryCode.setAttribute(
            'aria-required',
            countryCodeRequired ? 'true' : 'false'
        );

        countryCode.setCustomValidity('');
        phoneNumber.setCustomValidity('');

        if (
            countryCodeRequired &&
            !/^\+[1-9][0-9]{0,3}$/.test(countryValue)
        ) {
            countryCode.setCustomValidity(
                'Enter a valid country code, for example +91.'
            );
        }

        if (phoneValue !== '') {
            var phoneDigits = phoneValue.replace(/\D/g, '');

            if (!/^[0-9().\-\s]+$/.test(phoneValue)) {
                phoneNumber.setCustomValidity(
                    'Use only numbers, spaces, brackets, dots or hyphens.'
                );
            } else if (phoneDigits.length < 7 || phoneDigits.length > 15) {
                phoneNumber.setCustomValidity(
                    'Phone number must contain 7 to 15 digits.'
                );
            }
        }
    }

    var phoneGroups = Array.prototype.slice.call(
        form.querySelectorAll('[data-phone-group]')
    );

    phoneGroups.forEach(function (group) {
        var groupFields = group.querySelectorAll('input');

        groupFields.forEach(function (field) {
            field.addEventListener('input', function () {
                syncPhoneGroup(group);
            });

            field.addEventListener('change', function () {
                syncPhoneGroup(group);
            });
        });

        syncPhoneGroup(group);
    });

    fields.forEach(function (field) {
        field.addEventListener('blur', function () {
            field.dataset.touched = '1';
            updateFieldValidation(field, true);
        });

        field.addEventListener('input', function () {
            updateFieldValidation(field, false);
        });

        field.addEventListener('change', function () {
            field.dataset.touched = '1';
            updateFieldValidation(field, true);
        });
    });

    form.addEventListener('submit', function (event) {
        var firstInvalidField = null;

        phoneGroups.forEach(syncPhoneGroup);

        fields.forEach(function (field) {
            field.dataset.touched = '1';

            if (!updateFieldValidation(field, true) && !firstInvalidField) {
                firstInvalidField = field;
            }
        });

        if (firstInvalidField) {
            event.preventDefault();
            firstInvalidField.focus({ preventScroll: true });
            firstInvalidField.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            return;
        }

        button.disabled = true;
        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" ' +
            'role="status" aria-hidden="true"></span>Sending...';
    });

    /* Paste plain text only, so copied font formatting is never retained. */
    form.addEventListener('paste', function (event) {
        var field = event.target;

        if (!field.matches('input:not([type="file"]), textarea')) {
            return;
        }

        if (typeof field.setRangeText !== 'function') {
            return;
        }

        var clipboard = event.clipboardData || window.clipboardData;
        var text = clipboard.getData('text/plain');

        event.preventDefault();
        field.setRangeText(
            text,
            field.selectionStart,
            field.selectionEnd,
            'end'
        );
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
