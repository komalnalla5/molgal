<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: index.php');
    exit;
}


// Fetch product by slug + site
$siteIdParam = SITE_ID;
$prodStmt = mysqli_prepare($conn, "SELECT * FROM products WHERE slug = ? AND site_id = ? AND status = 'active' AND is_deleted = 0");
mysqli_stmt_bind_param($prodStmt, 'si', $slug, $siteIdParam);
mysqli_stmt_execute($prodStmt);
$prodResult = mysqli_stmt_get_result($prodStmt);
$product = mysqli_fetch_assoc($prodResult);
mysqli_stmt_close($prodStmt);


// Fetch specifications for this product (if any)
$specStmt = mysqli_prepare($conn, "SELECT test_name, specification_value,sr_no FROM product_specifications WHERE product_id = ? AND site_id = ? ORDER BY sr_no ASC");
mysqli_stmt_bind_param($specStmt, 'ii', $product['id'], $siteIdParam);
mysqli_stmt_execute($specStmt);
$specResult = mysqli_stmt_get_result($specStmt);
$specifications = [];
while ($row = mysqli_fetch_assoc($specResult)) {
    $specifications[] = $row;
}
mysqli_stmt_close($specStmt);


// Fetch site-wide disclaimer
$discStmt = mysqli_prepare($conn, "SELECT description FROM disclaimers WHERE site_id = ? AND status = 'active'");
mysqli_stmt_bind_param($discStmt, 'i', $siteIdParam);
mysqli_stmt_execute($discStmt);
$discResult = mysqli_stmt_get_result($discStmt);
$disclaimer = mysqli_fetch_assoc($discResult);
mysqli_stmt_close($discStmt);

// helper file code fetch oursite data
$currentSite = getCurrentSite($conn);

$hasTypicalProps = !empty($product['cas_number']) || !empty($product['molecular_formula']) || !empty($product['molecular_weight']);

 $brandSuper        = getBrandSuperscript($currentSite['sub_name']);
$superScript       = $brandSuper['symbol'];
$superScriptClass  = $brandSuper['class'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- dynamic meta details added here -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['meta_title'] ?? $currentSite['name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($product['meta_description'] ?? $currentSite['name']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['meta_keywords']); ?>">
     <!-- Canonical Link -->
    <?php if (!empty($product['meta_canonical'])): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($product['meta_canonical']); ?>">
    <?php endif; ?>
    <!-- Schema -->
    <?php if (!empty($product['meta_schema'])): ?>
        <script type="application/ld+json">
            <?php echo $product['meta_schema']; ?>
        </script>
    <?php endif; ?>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <?php include('includes/header-2.php'); ?>
    <style>
    <?php include('assets/css/style.css'); ?>
    </style>

 <!-- hero section -->
    <div class="det-wrapper">
        <div class="det-container">
            <!-- product hero section left content -->
            <div class="left-box product-left-content">
                <h1><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo htmlspecialchars($product['product_code']); ?></h1>
                <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
                <p>(<?php echo htmlspecialchars($product['usage_tag']); ?>)</p>
            </div>
            <!-- product hero section right side image -->
            <div class="right-box">
                <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'default.webp'; ?>"
                     alt="<?php echo htmlspecialchars($product['product_alt_text'] ?: $product['product_name']); ?>" loading="lazy" />
            </div>
        </div>
        <!-- product herosection righ bellow displaying short description -->
        <?php if (!empty($product['img_description'])): ?>
            <div class="description">
                <?php
                    $imgDesc = preg_replace('/^\s*<p[^>]*>|<\/p>\s*$/i', '', trim($product['img_description']));
                ?>
                <strong><?php echo $currentSite['name']; ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']);?></sup> <?php echo htmlspecialchars($product['product_code']); ?></strong> <?php echo $imgDesc; ?> 
            </div>
        <?php endif; ?>
    </div>
<!-- end hero section -->

<!-- Intro section left intro product image right intro description -->
    <div class="det-wrapper">
        <div class="card">
            <div class="det-content">
                <div class="left">
                    <h2 class="product-left-title">
                        <?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo htmlspecialchars($product['product_code']); ?>
                    </h2>
                    <?php if (!empty($product['intro_by'])): ?>
                    <p style="margin-right: 119px;">
                      by <strong><?php echo htmlspecialchars($product['intro_by']); ?></strong>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($product['intro_image'])): ?>
                    <div class="circle-img">
                        <img src="<?php echo htmlspecialchars($product['intro_image']); ?>"
                            alt="<?php echo htmlspecialchars($product['intro_alt_text'] ?: $product['product_name']); ?>" loading="lazy">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="right">
                    <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
                    <?php if (!empty($product['intro_text'])): ?>
                        <?php echo $product['intro_text']; ?>
                    <?php endif; ?>
                    <div class="buttons">
                        <a href="contact.php"><button class="enquiry">Enquiry</button></a>
                        <button class="back" onclick="location.href='index.php'">Back</button>
                    </div>
                </div>
                <div class="det-des">
                    We follow Good Manufacturing Practices (GMP) and adhere to international regulatory standards to ensure quality consistency with continuous supply.
                </div>
            </div>
        </div>
      </div>

      <!-- property formula section -->
    <?php if ($hasTypicalProps): ?>
    <div class="det-wrapper">
        <div class="spec-table-det-container">
            <h2><?php echo $currentSite['name']; ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $currentSite['sub_name']; ?></sup> <?php echo htmlspecialchars($product['product_code']); ?> TYPICAL PROPERTIES</h2>
            <table>
                <thead>
                    <tr><th>#</th><th>PROPERTY</th><th>TYPICAL VALUE</th></tr>
                </thead>
                <tbody>
                    <?php $i = 1; ?>
                    <?php if (!empty($product['cas_number'])): ?>
                        <tr><td><?php echo $i++; ?></td><td>CAS Number</td><td><?php echo htmlspecialchars($product['cas_number']); ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($product['molecular_formula'])): ?>
                        <tr><td><?php echo $i++; ?></td><td>Molecular Formula</td><td><?php echo $product['molecular_formula']; ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($product['molecular_weight'])): ?>
                        <tr><td><?php echo $i++; ?></td><td>Molecular Weight</td><td><?php echo htmlspecialchars($product['molecular_weight']); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- specification section -->
    <?php if (!empty($specifications)): ?>
    <div class="det-wrapper">
        <div class="spec-table-det-container">
            <h2>SPECIFICATION</h2>
            <table>
                <thead>
                    <tr><th>#</th><th>TEST</th><th>SPECIFICATION<?php echo !empty($product['grade']) ? ' - ' . htmlspecialchars($product['grade']) : ''; ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($specifications as $spec): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($spec['sr_no'] ?? ''); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($spec['test_name'])); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($spec['specification_value'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($product['package_description'])): ?>
    <div class="det-wrapper">
        <div class="spec-table-det-container">
            <?php echo $product['package_description']; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accreditations -->
    <?php include __DIR__ . '/Product-certificates.php'; ?>

    <?php if (!empty($disclaimer['description'])): ?>
    <div class="det-wrapper pb-4">
        <div class="disclaimer-box">
            <h2>Disclaimer</h2>
            <?php echo $disclaimer['description']; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php include('includes/footer.php'); ?>
</html>
