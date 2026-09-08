<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';


/*
|--------------------------------------------------------------------------
| Chemical formula display helpers
|--------------------------------------------------------------------------
| Store product content as plain text/HTML. These helpers add <sub> only
| while rendering, without changing percentages, CAS numbers or standards.
*/
if (!function_exists('formatProductFormulaTokens')) {
    function formatProductFormulaTokens($text)
    {
        $atom = '[A-Z][a-z]?\\d*';
        $group = '(?:' . $atom . '|\\((?:' . $atom . ')+\\)\\d*)';
        $pattern = '/(?<![A-Za-z0-9])'
            . '(?=[A-Za-z0-9().·]*\\d)'
            . '(?:' . $group . ')+'
            . '(?:[·.]\\d*(?:' . $group . ')+)*'
            . '(?![A-Za-z0-9])/u';

        $formatted = preg_replace_callback(
            $pattern,
            static function ($matches) {
                $candidate = $matches[0];

                if (preg_match(
                    '/^(?:USP|NF|IP|BP|EP|JP|ISO|ASTM|ICH)\\d/i',
                    $candidate
                )) {
                    return $candidate;
                }

                preg_match_all(
                    '/[A-Z][a-z]?/',
                    $candidate,
                    $symbolMatches
                );

                static $validElements = null;

                if ($validElements === null) {
                    $validElements = array_fill_keys([
                        'H', 'He', 'Li', 'Be', 'B', 'C', 'N', 'O', 'F', 'Ne',
                        'Na', 'Mg', 'Al', 'Si', 'P', 'S', 'Cl', 'Ar',
                        'K', 'Ca', 'Sc', 'Ti', 'V', 'Cr', 'Mn', 'Fe', 'Co',
                        'Ni', 'Cu', 'Zn', 'Ga', 'Ge', 'As', 'Se', 'Br', 'Kr',
                        'Rb', 'Sr', 'Y', 'Zr', 'Nb', 'Mo', 'Tc', 'Ru', 'Rh',
                        'Pd', 'Ag', 'Cd', 'In', 'Sn', 'Sb', 'Te', 'I', 'Xe',
                        'Cs', 'Ba', 'La', 'Ce', 'Pr', 'Nd', 'Pm', 'Sm', 'Eu',
                        'Gd', 'Tb', 'Dy', 'Ho', 'Er', 'Tm', 'Yb', 'Lu', 'Hf',
                        'Ta', 'W', 'Re', 'Os', 'Ir', 'Pt', 'Au', 'Hg', 'Tl',
                        'Pb', 'Bi', 'Po', 'At', 'Rn', 'Fr', 'Ra', 'Ac', 'Th',
                        'Pa', 'U', 'Np', 'Pu', 'Am', 'Cm', 'Bk', 'Cf', 'Es',
                        'Fm', 'Md', 'No', 'Lr', 'Rf', 'Db', 'Sg', 'Bh', 'Hs',
                        'Mt', 'Ds', 'Rg', 'Cn', 'Nh', 'Fl', 'Mc', 'Lv', 'Ts',
                        'Og'
                    ], true);
                }

                foreach ($symbolMatches[0] as $symbol) {
                    if (!isset($validElements[$symbol])) {
                        return $candidate;
                    }
                }

                $singleElementMolecules = [
                    'H2', 'N2', 'O2', 'O3', 'F2',
                    'Cl2', 'Br2', 'I2', 'P4', 'S8'
                ];

                if (
                    count($symbolMatches[0]) < 2 &&
                    !in_array(
                        $candidate,
                        $singleElementMolecules,
                        true
                    )
                ) {
                    return $candidate;
                }

                return preg_replace(
                    '/(?<=[A-Za-z\\)])(\\d+)/u',
                    '<sub>$1</sub>',
                    $candidate
                );
            },
            (string) $text
        );

        return $formatted ?? (string) $text;
    }

    function renderProductFormulaText($text, $lineBreaks = false)
    {
        $escaped = htmlspecialchars(
            (string) $text,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $formatted = formatProductFormulaTokens($escaped);

        return $lineBreaks
            ? nl2br($formatted, false)
            : $formatted;
    }

    function renderProductFormulaHtml($html)
    {
        $parts = preg_split(
            '/(<[^>]+>)/u',
            (string) $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return (string) $html;
        }

        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }

            $parts[$index] = formatProductFormulaTokens($part);
        }

        return implode('', $parts);
    }
}

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

// Fetch typical properties for this product (if any)
$propStmt = mysqli_prepare($conn, "SELECT properties, typical_value, sr_no FROM product_properties WHERE product_id = ? AND site_id = ? ORDER BY sr_no ASC");
mysqli_stmt_bind_param($propStmt, 'ii', $product['id'], $siteIdParam);
mysqli_stmt_execute($propStmt);
$propResult = mysqli_stmt_get_result($propStmt);
$typicalProperties = [];
while ($row = mysqli_fetch_assoc($propResult)) {
     // Skip rows that have no value at all
    if (trim((string) $row['typical_value']) === '') {
        continue;
    }
       $propertyName = ucwords(
        strtolower(trim($row['properties']))
        );
         $propertyName = str_ireplace(
            'cas',
            'CAS',
            $propertyName
        );
$row['properties'] = $propertyName;
    $typicalProperties[] = $row;
}
mysqli_stmt_close($propStmt);

// Fetch site-wide disclaimer
$discStmt = mysqli_prepare($conn, "SELECT description FROM disclaimers WHERE site_id = ? AND status = 'active'");
mysqli_stmt_bind_param($discStmt, 'i', $siteIdParam);
mysqli_stmt_execute($discStmt);
$discResult = mysqli_stmt_get_result($discStmt);
$disclaimer = mysqli_fetch_assoc($discResult);
mysqli_stmt_close($discStmt);

// helper file code fetch oursite data
$currentSite = getCurrentSite($conn);

$hasTypicalProps = !empty($typicalProperties);

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
                <h1 translate="no" class="notranslate"><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo renderProductFormulaText($product['product_code']); ?></h1>
                <h2><?php echo renderProductFormulaText($product['product_name']); ?></h2>
                <p>(<?php echo renderProductFormulaText($product['usage_tag']); ?>)</p>
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
                <strong translate="no" class="notranslate"><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']);?></sup> <?php echo renderProductFormulaText($product['product_code']); ?></strong> <?php echo renderProductFormulaHtml($imgDesc); ?> 
            </div>
        <?php endif; ?>
    </div>
<!-- end hero section -->

<!-- Intro section left intro product image right intro description -->
    <div class="det-wrapper">
        <div class="card">
            <div class="det-content">
                <div class="left">
                    <h2 class="product-left-title" translate="no" class="notranslate">
                        <?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo renderProductFormulaText($product['product_code']); ?>
                    </h2>
                    <?php if (!empty($product['intro_by'])): ?>
                    <p style="margin-right: 50px;">
                      by <strong><?php echo renderProductFormulaText($product['intro_by']); ?></strong>
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
                    <h2><?php echo renderProductFormulaText($product['product_name']); ?></h2>
                    <?php if (!empty($product['intro_text'])): ?>
                        <?php echo renderProductFormulaHtml($product['intro_text']); ?>
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
            <h2><?php echo htmlspecialchars($currentSite['name']); ?><sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currentSite['sub_name']); ?></sup> <?php echo renderProductFormulaText($product['product_code']); ?> TYPICAL PROPERTIES</h2>
            <table>
                <thead>
                    <tr><th>#</th><th>PROPERTY</th><th>TYPICAL VALUE</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($typicalProperties as $index => $prop): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo renderProductFormulaText($prop['properties']); ?></td>
                            <td><?php echo renderProductFormulaHtml(strip_tags((string) $prop['typical_value'], '<sub>')); ?></td>
                        </tr>
                    <?php endforeach; ?>
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
                    <tr><th>#</th><th>TEST</th><th>SPECIFICATION<?php echo !empty($product['grade']) ? ' - ' . renderProductFormulaText($product['grade']) : ''; ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($specifications as $spec): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($spec['sr_no'] ?? ''); ?></td>
                            <td><?php echo renderProductFormulaText($spec['test_name'], true); ?></td>
                            <td><?php echo renderProductFormulaText($spec['specification_value'], true); ?></td>
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
            <?php echo renderProductFormulaHtml($product['package_description']); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accreditations -->
    <?php include __DIR__ . '/Product-certificates.php'; ?>

    <?php if (!empty($disclaimer['description'])): ?>
    <div class="det-wrapper pb-4">
        <div class="disclaimer-box">
            <h2>Disclaimer</h2>
            <?php echo renderProductFormulaHtml($disclaimer['description']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php include('includes/footer.php'); ?>
</html>
