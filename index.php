<?php
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

<!-- Enquiry form section -->
<section class="enquiry-form-section">
    <div class="form-container">
        <form class="enquiry-form"
              method="POST"
              action="enquiry-form-handler.php">

            <div class="form-group">
                <input type="text"
                       id="name"
                       name="name"
                       placeholder="Your Name"
                       required>
            </div>

            <div class="form-group">
                <input type="email"
                       id="email"
                       name="email"
                       placeholder="Email"
                       required>
            </div>

            <div class="form-group select-group">
                <select id="product" name="product" required>
                    <option value="" disabled selected>
                         <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?><?php if ($subName !== ''): ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subName, ENT_QUOTES,'UTF-8'); ?></sup><?php endif; ?> Products
                    </option>
                    

                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                           <option value="<?php echo htmlspecialchars($product['product_code']); ?>">
                                <?php
                                echo htmlspecialchars($product['brand_name']) . '&nbsp;&nbsp;&nbsp;' . htmlspecialchars($product['product_code']);
                                ?>
                            </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                </select>
            </div>

            <div class="form-group button-group">
                <button type="submit">Enquire Now</button>
            </div>
        </form>
    </div>
</section>

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
