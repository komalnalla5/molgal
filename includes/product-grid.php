<?php
/**
 * @var mysqli $conn
 * @var int    $siteIdParam
 * @var array  $site
 */

$productsStmt = mysqli_prepare($conn, "SELECT p.id, p.brand_name, p.product_code, p.product_name, p.usage_tag, p.image, p.product_alt_text, p.slug,
    (SELECT COUNT(*) FROM products c WHERE c.parent_product_id = p.id AND c.status = 'active' AND c.is_deleted = 0) AS child_count
    FROM products p
    WHERE p.site_id = ? AND p.parent_product_id IS NULL AND p.status = 'active' AND p.is_deleted = 0
    ORDER BY p.id ASC");
mysqli_stmt_bind_param($productsStmt, 'i', $siteIdParam);
mysqli_stmt_execute($productsStmt);
$productsResult = mysqli_stmt_get_result($productsStmt);
$products = [];
while ($row = mysqli_fetch_assoc($productsResult)) {
    $products[] = $row;
}
mysqli_stmt_close($productsStmt);
?>

<section class="product-section">
    <div id="card-container" class="card-container">
        <?php foreach ($products as $p): ?>
            <?php
                $detailUrl = ($p['child_count'] > 0)
                    ? 'product-variants.php?slug=' . urlencode($p['slug'])
                    : 'product-details.php?slug=' . urlencode($p['slug']);
                $imageSrc = !empty($p['image'])
                    ? $p['image']
                    : 'default.webp';
            ?>
            <div class="product-card">
                <a href="<?php echo htmlspecialchars($detailUrl); ?>">
                    <img src="<?php echo htmlspecialchars($imageSrc); ?>"
                        alt="<?php echo htmlspecialchars($p['product_alt_text'] ?: $p['product_name']); ?>"
                        class="product-image"
                        loading="lazy" />
                    <h2 class="product-title"><?php echo htmlspecialchars($site['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($site['sub_name']); ?></sup> <?php echo htmlspecialchars($p['product_code']); ?></h2>
                    <div class="product-subtitle"><?php echo htmlspecialchars($p['product_name']); ?></div>
                    <div class="product-description">(<?php echo htmlspecialchars($p['usage_tag']); ?>)</div>
                </a>
                <button class="more-btn" onclick="location.href='<?php echo htmlspecialchars($detailUrl); ?>'">More details</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="pagination" class="pagination">
        <button id="prev-btn" class="pagination-btn">← </button>
        <span id="page-info" class="page-info">Page 1 of X</span>
        <button id="next-btn" class="pagination-btn"> →</button>
    </div>
</section>