<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';

try {
    $certificates = getCertificate($conn);
    $products     = getProduct($conn);
    $site         = getCurrentSite($conn);
    $siteIdParam  = SITE_ID;

    if (!$site) {
        throw new RuntimeException(
            'Site not found or inactive. Detected SITE_ID: ' . SITE_ID
        );
    }
} catch (Throwable $e) {
    error_log('Molgal index error: ' . $e->getMessage());

    die(
        '<h3>Website Error</h3><pre>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</pre>'
    );
}
$siteName = isset($site['name']) ? $site['name'] : '';
$subName  = isset($site['sub_name']) ? trim($site['sub_name']) : '';

$metaTitle = !empty($site['meta_title'])
    ? $site['meta_title']
    : $siteName;

$metaDescription = isset($site['meta_description'])
    ? $site['meta_description']
    : '';

$metaKeywords = isset($site['meta_keywords'])
    ? $site['meta_keywords']
    : '';

$canonicalLink = isset($site['canonical_link'])
    ? $site['canonical_link']
    : '';

$metaSchema = isset($site['meta_schema'])
    ? $site['meta_schema']
    : '';

$bannerImage = !empty($site['banner_img'])
    ? $site['banner_img']
    : 'assets/img/header-img.webp';

$bannerAlt = !empty($site['hero_banner_alt'])
    ? $site['hero_banner_alt']
    : $siteName . ' Hero Image';

$shortDescription = isset($site['short_description'])
    ? $site['short_description']
    : '';
    
$metaVerificationCode = isset($site['verification_code'])
    ? $site['verification_code']
    : '';

$google_tag_manager = isset($site['google_tag_manager'])
    ? $site['google_tag_manager']
    : '';

$brandSuper        = getBrandSuperscript($subName);
$superScript       = $brandSuper['symbol'];
$superScriptClass  = $brandSuper['class'];

// Build absolute URL for og:image
function toAbsoluteUrl($path) {
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
$bannerImageAbsolute = toAbsoluteUrl($bannerImage);

/*
|--------------------------------------------------------------------------
| Homepage enquiry form settings
|--------------------------------------------------------------------------
| The page keeps working safely if the table is not installed; the form only
| appears after this website has a settings row. Run the supplied SQL first.
*/
$enquirySettings = [
    'name_placeholder'    => 'Your Name',
    'name_required'       => 1,
    'email_placeholder'   => 'Email',
    'email_required'      => 1,
    'product_placeholder' => ($siteName !== '' ? $siteName : 'Select') . ' Products',
    'product_required'    => 1,
    'submit_button_text'  => 'Enquire Now',
    'notify_email'        => 'ask@mubychem.com',
    'success_message'     => 'Thank you for your enquiry. Our team will contact you shortly.',
    'status'              => 'active',
];
$enquirySettingsFound = false;

try {
    $enquirySettingsStmt = mysqli_prepare(
        $conn,
        "SELECT name_placeholder,
                name_required,
                email_placeholder,
                email_required,
                product_placeholder,
                product_required,
                submit_button_text,
                notify_email,
                success_message,
                status
         FROM enquiry_form_settings
         WHERE site_id = ?
         LIMIT 1"
    );

    if ($enquirySettingsStmt) {
        $enquirySiteId = (int) SITE_ID;
        mysqli_stmt_bind_param($enquirySettingsStmt, 'i', $enquirySiteId);
        mysqli_stmt_execute($enquirySettingsStmt);
        $savedEnquirySettings = mysqli_stmt_get_result($enquirySettingsStmt)->fetch_assoc();
        mysqli_stmt_close($enquirySettingsStmt);

        if (is_array($savedEnquirySettings)) {
            $enquirySettings = array_merge($enquirySettings, $savedEnquirySettings);
            $enquirySettingsFound = true;
        }
    }
} catch (Throwable $e) {
    error_log('Homepage enquiry settings error: ' . $e->getMessage());
}

/* Only active, non-deleted products belonging to this website are selectable. */
$enquiryProducts = [];
try {
    $enquiryProductsStmt = mysqli_prepare(
        $conn,
        "SELECT id, brand_name, product_code, product_name
         FROM products
         WHERE site_id = ?
           AND status = 'active'
           AND is_deleted = 0
         ORDER BY brand_name ASC, product_code ASC, product_name ASC"
    );

    if ($enquiryProductsStmt) {
        $enquirySiteId = (int) SITE_ID;
        mysqli_stmt_bind_param($enquiryProductsStmt, 'i', $enquirySiteId);
        mysqli_stmt_execute($enquiryProductsStmt);
        $enquiryProductsResult = mysqli_stmt_get_result($enquiryProductsStmt);
        $enquiryProducts = mysqli_fetch_all($enquiryProductsResult, MYSQLI_ASSOC);
        mysqli_stmt_close($enquiryProductsStmt);
    }
} catch (Throwable $e) {
    error_log('Homepage enquiry products error: ' . $e->getMessage());
}

if (
    empty($_SESSION['enquiry_csrf_token']) ||
    !is_string($_SESSION['enquiry_csrf_token']) ||
    strlen($_SESSION['enquiry_csrf_token']) !== 64
) {
    $_SESSION['enquiry_csrf_token'] = bin2hex(random_bytes(32));
}

$enquirySuccess  = (string) ($_SESSION['enquiry_public_success'] ?? '');
$enquiryError    = (string) ($_SESSION['enquiry_public_error'] ?? '');
$enquiryOldInput = is_array($_SESSION['enquiry_old_input'] ?? null)
    ? $_SESSION['enquiry_old_input']
    : [];

unset(
    $_SESSION['enquiry_public_success'],
    $_SESSION['enquiry_public_error'],
    $_SESSION['enquiry_old_input']
);

$showEnquiryForm =
    $enquirySettingsFound &&
    ($enquirySettings['status'] ?? 'active') === 'active';

?>
<!DOCTYPE html>
<html lang="en" style="overflow-y:scroll;">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description"
          content="<?php echo htmlspecialchars($metaDescription ?? $site['name']); ?>">
    <meta name="keywords"
          content="<?php echo htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($canonicalLink !== ''): ?>
    <link rel="canonical"
              href="<?php echo htmlspecialchars($canonicalLink, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
     <!-- social media -->
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description"  
    content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalLink, ENT_QUOTES, 'UTF-8'); ?>">  
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?php echo htmlspecialchars($bannerImageAbsolute, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($metaVerificationCode); ?>">
    <?php if ($google_tag_manager !== ''): ?>
        <script>
            <?php echo $google_tag_manager; ?>
        </script>
    <?php endif; ?> 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.core.css"
          rel="stylesheet">

    <?php if ($metaSchema !== ''): ?>
        <script type="application/ld+json">
           <?php echo $metaSchema; ?>
        </script>
    <?php endif; ?>

    <style>
<?php include __DIR__ . '/assets/css/style.css'; ?>
    </style>
<?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<!-- Banner section -->
<section class="a-banner">
    <img src="<?php echo htmlspecialchars($bannerImage, ENT_QUOTES, 'UTF-8'); ?>"
         alt="<?php echo htmlspecialchars($bannerAlt, ENT_QUOTES, 'UTF-8'); ?>"
         loading="lazy">

    <div class="rv-header-img-overlay">
        <div class="container">
            <div class="row">
                <div class="a-banner-content">
                    <div class="a-banner-title">
                        <h1 class="a-banner-title__text rv-text-anime">
                             <span class="word" style="display:inline-block;">
                                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?><?php if ($subName !== ''): ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subName, ENT_QUOTES,'UTF-8'); ?></sup><?php endif; ?>
                            </span>
                        </h1>

                        <div id="page-content" class="banner-title__text">
                            <span class="site-name">
                                 <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?><?php if ($subName !== ''): ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?>
                                    </sup>
                                <?php endif; ?>
                            </span>
                            <?php echo $shortDescription; ?>
                        </div>
                    </div>

                    <a href="about.php"
                       class="a-banner-btn wow fadeInUp"
                       style="visibility:visible; animation-name:fadeInUp;">
                        About us
                        <i class="fa fa-angle-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($showEnquiryForm): ?>
<!-- Dynamic homepage enquiry form -->
<section class="enquiry-form-section" id="enquiry-form">
    <div class="form-container">
        <?php if ($enquirySuccess !== ''): ?>
            <div class="enquiry-message enquiry-message-success" role="status">
                <?php echo htmlspecialchars($enquirySuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($enquiryError !== ''): ?>
            <div class="enquiry-message enquiry-message-error" role="alert">
                <?php echo htmlspecialchars($enquiryError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form class="enquiry-form" method="POST" action="submit_form.php">
            <input type="hidden" name="form_type" value="enquiry">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['enquiry_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="enquiry-honeypot" aria-hidden="true">
                <label for="enquiry_website_url">Website</label>
                <input type="text" id="enquiry_website_url" name="website_url"
                       tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <input type="text"
                       id="name"
                       name="name"
                       maxlength="150"
                       autocomplete="name"
                       aria-label="Name"
                       placeholder="<?php echo htmlspecialchars($enquirySettings['name_placeholder'], ENT_QUOTES, 'UTF-8'); ?>"
                       value="<?php echo htmlspecialchars((string) ($enquiryOldInput['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo !empty($enquirySettings['name_required']) ? 'required' : ''; ?>>
            </div>

            <div class="form-group">
                <input type="email"
                       id="email"
                       name="email"
                       maxlength="255"
                       autocomplete="email"
                       aria-label="Email"
                       placeholder="<?php echo htmlspecialchars($enquirySettings['email_placeholder'], ENT_QUOTES, 'UTF-8'); ?>"
                       value="<?php echo htmlspecialchars((string) ($enquiryOldInput['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo !empty($enquirySettings['email_required']) ? 'required' : ''; ?>>
            </div>

            <div class="form-group select-group">
                <select id="product_id" name="product_id" aria-label="Product"
                        <?php echo !empty($enquirySettings['product_required']) ? 'required' : ''; ?>>
                    <option value=""
                        <?php echo !empty($enquirySettings['product_required']) ? 'disabled' : ''; ?>
                        <?php echo empty($enquiryOldInput['product_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($enquirySettings['product_placeholder'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>

                    <?php foreach ($enquiryProducts as $product): ?>
                        <?php
                        $productLabel = trim(
                            (string) ($product['brand_name'] ?? '') . ' ' .
                            (string) ($product['product_code'] ?? '')
                        );
                        if ($productLabel === '') {
                            $productLabel = (string) ($product['product_name'] ?? 'Product');
                        }
                        ?>
                        <option value="<?php echo (int) $product['id']; ?>"
                            <?php echo (int) ($enquiryOldInput['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($productLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group button-group">
                <button type="submit">
                    <?php echo htmlspecialchars($enquirySettings['submit_button_text'], ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </form>
    </div>
</section>

<style>
    .enquiry-honeypot {
        position: absolute !important;
        left: -9999px !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
    }
    .enquiry-message {
        margin: 0 0 14px;
        padding: 10px 14px;
        border: 1px solid transparent;
        border-radius: 5px;
        font-size: 14px;
    }
    .enquiry-message-success {
        color: #1b5e20;
        background: #eafaf1;
        border-color: #9ad4a4;
    }
    .enquiry-message-error {
        color: #842029;
        background: #f8d7da;
        border-color: #f1aeb5;
    }
</style>
<?php endif; ?>

<!-- Products listing section -->
<?php include __DIR__ . '/includes/product-grid.php'; ?>

<!-- Certifications section -->
<section id="certifications">
    <div class="container" data-aos="zoom-out">
        <div class="row justify-content-center">
            <div class="col-md-12 mb-5">
                <div class="section-heading-left">
                    <h2 class="section-title p-3" style="text-align:center;">
                        Certifications
                    </h2>
                </div>

                <div class="grid">
                    <?php if (!empty($certificates)): ?>
                        <?php foreach ($certificates as $certificate): ?>
                            <?php
                            $certificateImage = isset($certificate['cert_img'])
                                ? $certificate['cert_img']
                                : '';

                            $certificateName = isset($certificate['cert_name'])
                                ? $certificate['cert_name']
                                : 'Certificate';

                            $certificateAlt = !empty($certificate['alt_text'])
                                ? $certificate['alt_text']
                                : $certificateName;
                            ?>
                            <div class="entry-content">
                                <figure class="client-logo-media">
                                    <img src="<?php echo htmlspecialchars(getCertificateImageUrl($certificateImage), ENT_QUOTES, 'UTF-8'); ?>"
                                         class="certi"
                                         loading="lazy"
                                         alt="<?php echo htmlspecialchars($certificateAlt, ENT_QUOTES, 'UTF-8'); ?>">
                                </figure>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No certificates available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Manufacturing facility section -->
<section class="first-sec" style="margin-bottom:30px;">
    <div>
        <h4 class="youtube-heading">
            Click here for the manufacturing facility:
        </h4>

        <div class="youtube-link pt-2 justify-content-center">
            <a href="https://youtu.be/buwI_49ZTp0?si=MB2RZVVCKdK_yNlq"
               target="_blank"
               rel="noopener noreferrer">
                <img src="assets/img/youtube-logo.webp"
                     alt="YouTube Logo"
                     loading="lazy">
            </a>

            <div class="link-list">
                <a href="https://youtu.be/buwI_49ZTp0?si=MB2RZVVCKdK_yNlq"
                   target="_blank"
                   rel="noopener noreferrer">
                    www.youtube.com/@mubychemprivatelimited
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
