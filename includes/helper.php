<?php

require_once __DIR__ . '/../config.php';

function getCurrentSite($conn)
{

    static $siteInfo = null;
    if ($siteInfo !== null) {
        return $siteInfo;
    }
    $stmt = mysqli_prepare($conn, "SELECT
            oursites.id,
            oursites.name,
            oursites.domain,
            oursites.site_name,
            oursites.sub_name,
            oursites.status,
            site_details.logo,
            site_details.title,
            site_details.short_description,
            site_details.banner_img,
            site_details.product_hero_banner_img,
            site_details.meta_title,
            site_details.meta_description,
            site_details.meta_keywords,
            site_details.meta_schema,
            site_details.canonical_link,
            site_details.product_meta_title,
            site_details.product_meta_keywords,
            site_details.product_meta_description,
            site_details.product_meta_schema,
            site_details.product_canonical_link
        FROM oursites
        LEFT JOIN site_details ON site_details.site_id = oursites.id
        WHERE oursites.id = ?
          AND oursites.deleted_at IS NULL");
        $siteId = SITE_ID;  
        mysqli_stmt_bind_param($stmt, 'i', $siteId);  
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $siteInfo = mysqli_fetch_assoc($result) ?: [];
        mysqli_stmt_close($stmt);
        return $siteInfo;
}

function getCertificate($conn, $type = null)
{
    $baseSql = "
        SELECT
            id,
            cert_name,
            cert_img,
            alt_text,
            status,
            display_order
        FROM certificate
        WHERE status = 'active'
        AND deleted_at IS NULL
    ";

    $displayFlag = null;
    if ($type === 'product') {
        $displayFlag = 'show_on_product';
    }

    if ($type === 'blog') {
        $displayFlag = 'show_on_blog';
    }

    $sql = $baseSql;
    if ($displayFlag !== null) {
        $sql .= " AND {$displayFlag} = 1";
    }

    // First sort by display_order.
    // If two certificates have the same order, sort by ID.
    $sql .= " ORDER BY display_order ASC, id ASC";

    $result = false;
    try {
        $result = mysqli_query($conn, $sql);
    } catch (Throwable $e) {
        error_log('Certificate filtered query error: ' . $e->getMessage());
    }

    // Some live databases were created before show_on_blog/show_on_product
    // was added. Do not crash the whole page: fall back to active records.
    if (!$result && $displayFlag !== null) {
        $fallbackSql = $baseSql . " ORDER BY display_order ASC, id ASC";
        try {
            $result = mysqli_query($conn, $fallbackSql);
        } catch (Throwable $e) {
            error_log('Certificate fallback query error: ' . $e->getMessage());
            $result = false;
        }
    }

    $certificates = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $certificates[] = $row;
        }
    } elseif ($displayFlag === null) {
        error_log('Certificate query error: ' . mysqli_error($conn));
    }
    return $certificates;
}

function getCertificateImageUrl($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');

    // Older records may already include the uploads/ prefix.
    if (strpos($path, 'uploads/') === 0) {
        return rtrim(ADMIN_BASE_URL, '/') . '/' . $path;
    }

    return rtrim(CERTIFICATE_BASE_URL, '/') . '/' . $path;
}


function getProduct($conn){
$productStmt = mysqli_prepare($conn, "SELECT 
            p.id,
            p.site_id,
            p.brand_name,
            p.product_code,
            p.product_name
        FROM products p
        WHERE p.site_id = ?
          AND p.status = 'active'");
$siteIdParam = SITE_ID; // From config file

mysqli_stmt_bind_param($productStmt, 'i', $siteIdParam);
mysqli_stmt_execute($productStmt);
$siteResult = mysqli_stmt_get_result($productStmt);
$products = mysqli_fetch_all($siteResult, MYSQLI_ASSOC);
mysqli_stmt_close($productStmt);

return $products;
}


function getBroucher($conn)
{
    $brochureStmt = mysqli_prepare(
        $conn,
        "SELECT name, file_path 
         FROM brochures 
         WHERE site_id = ? 
         AND status = 'active' 
         AND deleted_at IS NULL 
         LIMIT 1"
    );

    $brochureSiteIdParam = SITE_ID;

    mysqli_stmt_bind_param($brochureStmt, 'i', $brochureSiteIdParam);
    mysqli_stmt_execute($brochureStmt);

    $brochureResult = mysqli_stmt_get_result($brochureStmt);
    $siteBrochure = mysqli_fetch_assoc($brochureResult);

    mysqli_stmt_close($brochureStmt);

    if (!$siteBrochure) {
        return null;
    }

    return $siteBrochure;
}

function getBrandSuperscript($subName) {
    $raw   = trim((string) $subName);
    $upper = strtoupper($raw);

    // Matches either the plain-text code ("TM"/"SM"/"R") stored in the DB,
    // or the actual symbol itself, in case it was saved pre-rendered.
    if ($upper === 'TM' || $raw === '™') {
        return ['symbol' => 'ᵀᴹ', 'class' => 'brand-sup-tm'];
    }

    if ($upper === 'SM' || $raw === 'ˢᵐ') {
        return ['symbol' => 'ˢᵐ', 'class' => 'brand-sup-sm'];
    }

    if ($upper === 'R' || $raw === '®') {
        return ['symbol' => '®', 'class' => 'brand-sup-r'];
    }

    // Fallback for anything else (custom text, unexpected value, etc.)
    return ['symbol' => $raw, 'class' => 'brand-sup'];
}
