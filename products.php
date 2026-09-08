<?php
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/helper.php';

    $site        = getCurrentSite($conn);
    $siteIdParam = SITE_ID;

    if (! $site) {
    die('Site not found or inactive.');
    }

    $brandSuper        = getBrandSuperscript($site['sub_name']);
    $superScript       = $brandSuper['symbol'];
    $superScriptClass  = $brandSuper['class'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="<?php echo htmlspecialchars($site['product_meta_description'] ?? $site['name']); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site['product_meta_title'] ?? $site['name']); ?></title>
    <meta name="keywords" content="<?php echo htmlspecialchars($site['product_meta_keywords']); ?>">
     <!-- Canonical Link -->
    <?php if (! empty($site['product_canonical_link'])): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($site['product_canonical_link']); ?>">
    <?php endif; ?>
    <!-- Schema -->
    <?php if (! empty($site['product_meta_schema'])): ?>
        <script type="application/ld+json">
            <?php echo $site['product_meta_schema']; ?>
        </script>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/header-2.php'; ?>
    <section class="hero-section" style="background-image: url('<?php echo !empty($site['product_hero_banner_img']) ? htmlspecialchars($site['product_hero_banner_img']) : 'assets/img/product-slider.webp'; ?>');"
        alt="title image"
        loading="lazy">
        <div class="hero-content">
           <h1 translate="no" class="notranslate"><?php echo htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8'); ?><?php if ($site['sub_name'] !== ''): ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($site['sub_name'], ENT_QUOTES,'UTF-8'); ?></sup><?php endif; ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php"><i class="fa-solid fa fa-home"> </i> Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Products</li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- products listing section -->
      <?php include 'includes/product-grid.php'; ?>
    <!-- End -->
    <?php include 'includes/footer.php'; ?>

</html>