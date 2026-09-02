<?php
include('config.php'); // adjust path if needed

// ---- Fetch main About Us record for this site ----
$aboutUs = null;

$sql = "SELECT * FROM about_us WHERE oursite_id = ? AND deleted_at IS NULL AND status = 'active' LIMIT 1";

//   echo"<pre>";
//     var_dump($sql);
//     die;


$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $siteId);
$siteId = SITE_ID; // bind_param needs a variable, not a constant directly
mysqli_stmt_bind_param($stmt, 'i', $siteId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$aboutUs = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// ---- Fetch related About Us Section rows ----
$aboutSections = [];

if ($aboutUs) {
    $sql2 = "SELECT * FROM about_us_section WHERE about_us_id = ? AND oursite_id = ? ORDER BY id ASC";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, 'ii', $aboutUs['id'], $siteId);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    while ($row = mysqli_fetch_assoc($result2)) {
        $aboutSections[] = $row;
    }
    mysqli_stmt_close($stmt2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="<?php echo htmlspecialchars($aboutUs['meta_desc'] ?? 'MOLPROP'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($aboutUs['meta_title'] ?? 'About MOLPROP'); ?></title>
     <meta name="keywords" content="<?php echo  $aboutUs['meta_keywords'] ?>">
    <link rel="canonical" href="<?php echo  $aboutUs['canonical_link'] ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
     <script type="application/ld+json">
    <?php echo $aboutUs['meta_schema']; ?>
    </script>
    <?php include('includes/header-2.php'); ?>

    <section class="hero-section"
        style="background:linear-gradient(rgba(130, 58, 55, 0.38)), url('<?php echo $aboutUs ? htmlspecialchars($aboutUs['banner_path']) : 'assets/img/about-us-banner.webp'; ?>') center/cover no-repeat;"
        alt="<?php echo htmlspecialchars($aboutUs['banner_alt'] ?? 'about Banner'); ?>"  loading="lazy">
        <div class="hero-content">
            <h1>About Us</h1>
                

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php"><i class="fa-solid fa fa-home"></i> Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">About Us</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="about-section">
        <div class="about-image">
            <img src="<?php echo $aboutUs ? htmlspecialchars($aboutUs['introimage_path']) : 'assets/img/about-home.webp'; ?>"
                alt="<?php echo htmlspecialchars($aboutUs['introimage_alt'] ?? 'Muby Chem Factory'); ?>" loading="lazy">
        </div>

        <div class="about-content">
            <?php if ($aboutUs): ?>
                 <h2><?php echo $aboutUs['title']; ?></h2>
               <p><?php echo $aboutUs['about_us_description']; // stored as HTML/rich text ?></p> 
            <?php else: ?>
                <h2>MUBY CHEM PRIVATE LIMITED</h2>
                <p>Muby Chem Pvt. Ltd. was established in 1976...</p>
            <?php endif; ?>
        </div>
    </section>

   <?php if (!empty($aboutSections)): ?>
    <section class="contact-layout1">
        <div class="about-sec-container">
            <?php foreach ($aboutSections as $index => $section): ?>
                <div class="about_us_box card-color-<?php echo ($index % 3) + 1; ?>">
                    <div class="about_us_icon">
                        <img src="<?php echo htmlspecialchars($section['image_path']); ?>"
                            alt="<?php echo htmlspecialchars($section['image_alt'] ?? $section['title']); ?>"
                            loading="lazy">
                    </div>
                    <?php if (!empty($section['title'])): ?>
                        <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($section['description'])): ?>
                        <?php echo $section['description']; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <?php include('includes/footer.php'); ?>

</body>
</html>