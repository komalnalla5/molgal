 <?php
    include_once('helper.php');
    include_once('config.php');
    $getBroucher = getBroucher($conn); 
    $languages = include __DIR__ . '/languages.php';

    $siteStmt = mysqli_prepare($conn, "SELECT 
            o.id,
            o.name,
            o.site_name,
            o.sub_name,
            sd.logo,
            sd.logo_2,
            sd.logo_2_alt,
            sd.title,
            sd.short_description
        FROM oursites o
        LEFT JOIN site_details sd 
            ON sd.site_id = o.id
        WHERE o.id = ?
          AND o.status = 'active'");
$siteIdParam = SITE_ID; // From config file

mysqli_stmt_bind_param($siteStmt, 'i', $siteIdParam);
mysqli_stmt_execute($siteStmt);
$siteResult = mysqli_stmt_get_result($siteStmt);
$headerSite = mysqli_fetch_assoc($siteResult);
mysqli_stmt_close($siteStmt);

if (!$headerSite) {
    die('Site not found or inactive.');
}
?>

 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
     integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
 <link rel="stylesheet" href="assets/css/style.css">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
<link rel="icon" type="image/x-icon" href="<?php echo ADMIN_BASE_URL; ?>assets/images/fevicon.png">

 </head>
 
 <header class="rv-1-header rv-inner-header to-be-fixed">
     <div class="container">
         <div class="row align-items-center">
             <div class="col-lg-3 col-4 col-xxs-6">
                 <div class="rv-1-logo">
                     <a href="index.php">
                        <img
                            src="<?php
                            echo ($headerSite && !empty($headerSite['logo_2']))
                                ? htmlspecialchars($headerSite['logo_2'])
                                : 'assets/img/molgal-black.png';
                            ?>"
                            style="width: 200px;"
                            alt="<?php echo htmlspecialchars($headerSite['logo_2_alt'] ?? 'Logo'); ?>"
                            class="rv-1-logo"
                            loading="lazy"
                        >
                    </a>
                 </div>
             </div>
             <!-- nav menu -->
             <div class="col-md-6 order-2 order-lg-1">
                 <div class="rv-1-header-nav__sidebar ">
                     <div class="sidebar-heading d-lg-none d-flex align-items-center justify-content-between">
                         <!-- <a href="index.php" class="logo-container"><img src="assets/img/logo.webp" alt="logo" loading="lazy"></a> -->
                         <button
                             class="rv-3-def-btn rv-1-header-mobile-menu-btn rv-inner-mobile-menu-btn sidebar-close-btn"><i
                            class="fa fa-xmark"></i></button>
                     </div>
                    <?php
                        $currentPage = basename($_SERVER['PHP_SELF']);
                    ?>
                     <div class="rv-1-header__nav rv-inner-header__nav">
                         <ul class="justify-content-center">
                             <li><a href="index.php"
                                     class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>">Home</a></li>
                             <li><a href="about.php" class="<?= ($currentPage == 'about.php') ? 'active' : '' ?>">About
                                     us</a></li>
                             <li><a href="products.php"
                                     class="<?= ($currentPage == 'products.php') ? 'active' : '' ?>">Products</a></li>
                             <li><a href="blog.php" class="<?= ($currentPage == 'blog.php') ? 'active' : '' ?>">Blog</a>
                             </li>
                             <li><a href="contact.php"
                                     class="<?= ($currentPage == 'contact.php') ? 'active' : '' ?>">Contact us</a></li>
                         </ul>
                     </div>
                 </div>
             </div>

             <div class="col-lg-3 col-8 col-xxs-6 text-end order-1 order-lg-2">
                 <div class="d-flex justify-content-end">
                  <?php if (!empty($getBroucher) && $getBroucher['status'] == "active"): ?>
                     <div class="rv-inner-header-right-btns">
                        <a href="<?php echo htmlspecialchars($getBroucher['file_path']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="d-sm-inline-block d-none">
                                Brochure 
                        </a>
                     </div>
                    <?php endif; ?>
                        <!-- Custom Language Switcher (moved outside) -->
                        <div class="lang-switcher dropdown notranslate" translate="no">
                            <button class="lang-btn dropdown-toggle hp2-lang-btn" type="button" id="langDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" >
                                <img src="https://flagcdn.com/w20/gb.png" alt="" class="lang-flag" id="currentLangFlag" style="display:none;">
                                <span id="currentLangName">Select Language</span>
                            </button>
                            <ul class="dropdown-menu lang-dropdown-menu" aria-labelledby="langDropdownBtn">
                                <?php foreach ($languages as $code => $lang): ?>
                                    <li>
                                        <a class="dropdown-item lang-option" href="#" data-lang="<?php echo htmlspecialchars($code); ?>" data-name="<?php echo htmlspecialchars($lang['name']); ?>">
                                            <img src="https://flagcdn.com/w20/<?php echo htmlspecialchars($lang['flag']); ?>.png" alt="<?php echo htmlspecialchars($lang['name']); ?>" class="lang-flag">
                                            <?php echo htmlspecialchars($lang['name']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                     <button
                         class="rv-1-header-mobile-menu-btn rv-3-def-btn rv-inner-mobile-menu-btn d-lg-none d-inline-flex"
                         id="rv-1-header-mobile-menu-btn"><i class="fa fa-bars"></i>
                    </button>
                 </div>
             </div>
         </div>
     </div>
 </header>