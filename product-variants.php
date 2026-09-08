<?php
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/helper.php';

    // fetch helper file oursite common data
    $currentSite = getCurrentSite($conn);
    $siteIdParam = SITE_ID;

    // Get parent product by slug
    $slug = trim($_GET['slug'] ?? '');
    if ($slug === '') {
    header('Location: index.php');
    exit;
    }

    $parentStmt = mysqli_prepare($conn, "SELECT id, brand_name, product_code, product_name, usage_tag, image, product_alt_text, slug
    FROM products
    WHERE slug = ? AND site_id = ? AND status = 'active' AND is_deleted = 0");
    mysqli_stmt_bind_param($parentStmt, 'si', $slug, $siteIdParam);
    mysqli_stmt_execute($parentStmt);
    $parentResult = mysqli_stmt_get_result($parentStmt);
    $parent       = mysqli_fetch_assoc($parentResult);
    mysqli_stmt_close($parentStmt);

    if (! $parent) {
    header('Location: index.php');
    exit;
    }

    $childStmt = mysqli_prepare($conn, "SELECT id, brand_name, product_code, product_name, usage_tag, image, product_alt_text, slug
    FROM products
    WHERE parent_product_id = ? AND site_id = ? AND status = 'active' AND is_deleted = 0
    ORDER BY id ASC");
    mysqli_stmt_bind_param($childStmt, 'ii', $parent['id'], $siteIdParam);
    mysqli_stmt_execute($childStmt);
    $childResult = mysqli_stmt_get_result($childStmt);
    $children    = [];
    while ($row = mysqli_fetch_assoc($childResult)) {
    $children[] = $row;
    }
    mysqli_stmt_close($childStmt);

    if (empty($children)) {
    header('Location: index.php');
    exit;
    }

    $allVariants = array_merge([$parent], $children);
     $brandSuper        = getBrandSuperscript($currentSite['sub_name']);
    $superScript       = $brandSuper['symbol'];
    $superScriptClass  = $brandSuper['class'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
     <meta name="description" content="<?php echo htmlspecialchars($currentSite['product_meta_description'] ?? $currentSite['name']); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentSite['product_meta_title'] ?? $currentSite['name']); ?></title>
    <meta name="keywords" content="<?php echo htmlspecialchars($currentSite['product_meta_keywords']); ?>">
     <!-- Canonical Link -->
    <?php if (! empty($currentSite['product_canonical_link'])): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($currentSite['product_canonical_link']); ?>">
    <?php endif; ?>
    <!-- Schema -->
    <?php if (! empty($currentSite['product_meta_schema'])): ?>
        <script type="application/ld+json">
            <?php echo $currentSite['product_meta_schema']; ?>
        </script>
    <?php endif; ?>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/header-2.php'; ?>

    <style>
    <?php include 'assets/css/style.css'; ?>
    </style>
    <section class="product-section">
        <div class="">
            <h2 class="subcate-title"><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($superScript); ?></sup> Range of products</h2>
        </div>
    </section>

    <!-- Products cards -->
    <section class="product-section">
        <div id="card-container" class="card-container">
            <?php foreach ($allVariants as $p): ?>
                <?php
                    $detailUrl = 'product-details.php?slug=' . urlencode($p['slug']);
                    $imageSrc  = ! empty($p['image'])
                        ? $p['image']
                        : 'default.webp';
                ?>
                <div class="product-card">
                    <a href="<?php echo htmlspecialchars($detailUrl); ?>">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>"
                            alt="<?php echo htmlspecialchars($p['product_alt_text'] ?: $p['product_name']); ?>"
                            class="product-image"
                            loading="lazy" />
                        <h2 class="product-title"><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo htmlspecialchars($p['product_code']); ?></h2>
                        <div class="product-subtitle"><?php echo htmlspecialchars($p['product_name']); ?></div>
                        <div class="product-description">(<?php echo htmlspecialchars($p['usage_tag']); ?>)</div>
                    </a>
                    <button class="more-btn" onclick="location.href='<?php echo htmlspecialchars($detailUrl); ?>'">More details</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="subcate-btn">
            <button class="back" onclick="location.href='index.php'">Back</button>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</html>