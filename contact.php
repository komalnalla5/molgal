<?php
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

$visibleLocations = array_filter(
    $locations,
    function ($location) {
        return is_array($location) && (
            !empty($location['title']) ||
            !empty($location['address']) ||
            !empty($location['map_embed'])
        );
    }
);

$sent = (
    ($_GET['sent'] ?? '') === '1' ||
    ($_GET['submitted'] ?? '') === '1' ||
    ($_GET['success'] ?? '') === '1'
);

$error = isset($_GET['error']) && $_GET['error'] !== '';
$mailWarning = $sent && (($_GET['mail'] ?? '') === '0');
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

        .contact-form .form-control,
        .contact-form .form-select {
            width: 100%;
            min-height: 50px;
            padding: 8px 15px;
            border: 1px solid #cccccc;
            border-radius: 10px;
            background-color: #ffffff;
            font: inherit;
        }

        .contact-form textarea.form-control {
            min-height: 220px;
            resize: vertical;
        }

        .contact-form .form-submit {
            min-height: 64px;
            margin: 0;
        }

        @media (max-width: 767px) {
            .contact-form .contact-form-fields {
                grid-template-columns: 1fr;
            }

            .contact-form .contact-field,
            .contact-form .contact-field.full {
                grid-column: 1;
            }
        }
    </style>
<?php include __DIR__ . '/includes/header-2.php'; ?>
</head>
<body>


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
    <section class="contact-container">
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

        <div class="contact-form">
            <h2><?php echo contactEscape($formHeading); ?></h2>

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
                    Something went wrong. Please check the required fields and try again.
                </div>
            <?php endif; ?>

            <form
                action="submit_contact.php"
                method="POST"
                id="contactPublicForm"
                class="contact-form-fields"
            >
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

                    /* Reference design: first two fields share one row. */
                    $fullWidth = $type === 'textarea' || $visibleFieldIndex >= 2;
                    $visibleFieldIndex++;
                    ?>

                    <div class="contact-field<?php echo $fullWidth ? ' full' : ''; ?>">
                        <label
                            for="field_<?php echo contactEscape($fieldName); ?>"
                            class="visually-hidden"
                        >
                            <?php echo contactEscape($label); ?>
                        </label>

                        <?php if ($type === 'textarea'): ?>
                            <textarea
                                id="field_<?php echo contactEscape($fieldName); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-control"
                                rows="6"
                                placeholder="<?php echo contactEscape($placeholder); ?>"
                                <?php echo $required ? 'required' : ''; ?>
                            ></textarea>

                        <?php elseif ($type === 'select'): ?>
                            <select
                                id="field_<?php echo contactEscape($fieldName); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-select"
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

                        <?php else: ?>
                            <input
                                type="<?php echo contactEscape(contactFieldType($type)); ?>"
                                id="field_<?php echo contactEscape($fieldName); ?>"
                                name="<?php echo contactEscape($fieldName); ?>"
                                class="form-control"
                                placeholder="<?php echo contactEscape($placeholder); ?>"
                                <?php echo $required ? 'required' : ''; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

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
        <section class="container pb-5">
            <div class="text-center mb-4">
                <h2 class="fw-bold">Our Locations</h2>
            </div>

            <div class="row g-4">
                <?php foreach ($visibleLocations as $location): ?>
                    <div class="col-lg-6">
                        <article class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                            <div class="card-body p-4">
                                <h3 class="h5 fw-bold mb-2">
                                    <i class="fa-solid fa-location-dot text-primary me-2"></i>
                                    <?php echo contactEscape($location['title'] ?? 'Location'); ?>
                                </h3>

                                <?php if (!empty($location['address'])): ?>
                                    <p class="text-secondary mb-3">
                                        <?php echo contactEscape($location['address']); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($location['map_embed'])): ?>
                                    <div class="ratio ratio-16x9 rounded overflow-hidden">
                                        <iframe
                                            src="<?php echo contactEscape($location['map_embed']); ?>"
                                            loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"
                                            title="<?php echo contactEscape($location['title'] ?? 'Map'); ?>"
                                        ></iframe>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('contactPublicForm');
    var button = document.getElementById('contactSubmitButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function () {
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
