<?php
    include_once('helper.php');
    include_once('config.php');
    $getBroucher = getBroucher($conn);
    $languages = include __DIR__ . '/languages.php';

?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">

<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css"
    integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="assets/img/fevicon.png" alt="title image" type="image/x-icon" loading="lazy">

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Rb2X8dcJpsqEwltj33Rm1NUdElo0akZ7eKq7nM5r3JgqN0KQ6z7F4tzV+9XQnydS"
    crossorigin="anonymous"></script>
</head>
<?php

$siteStmt = mysqli_prepare($conn, "SELECT 
            o.id,
            o.name,
            o.site_name,
            o.sub_name,
            sd.logo,
            sd.logo_alt,
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
$brandSuper        = getBrandSuperscript($subName);
$superScript       = $brandSuper['symbol'];
$superScriptClass  = $brandSuper['class'];
?>
<!-- TOP NAVBAR -->
<nav class="navbar navbar-expand-lg bg-brown sticky-top">
    <div class="container">
        <!-- LEFT LINKS -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
            aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
       <div class="collapse navbar-collapse me-lg-3" id="navbarContent">
    <marquee>
        <p class="top-navbar">
            <span class="site-name">
                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($subName !== ''): ?>
                    <sup class="<?php echo htmlspecialchars($superScriptClass, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?>
                    </sup>
                    <?php echo htmlspecialchars($headerSite['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
        </p>
    </marquee>
</div>
        <!-- RIGHT LOGO -->
        <a class="navbar-brand ms-5" style="justify-content: center;" href="index.php">
            <img src="assets/img/muby-chem-white.png" alt="Mubychem Logo" style="width:200px;" loading="lazy">
        </a>
    </div>
</nav>
<header class="rv-28-header">
    <div class="rv-28-border-for-menu to-be-fixed">
        <div class="container">
            <div class="row">
                <div class="rv-28-menu">
                    <div class="rv-28-logo">
                        <a href="index.php">
                           <img src="<?php
                            echo ($headerSite && !empty($headerSite['logo']))
                                ? htmlspecialchars($headerSite['logo'])
                                : 'assets/img/molprop-white.png';
                            ?>" style="width:200px;" alt="<?php echo htmlspecialchars($headerSite['logo_alt'])?>" loading="lazy">
                        </a>
                    </div>
                    <div class="rv-1-header-nav__sidebar">
                        <div class="sidebar-heading d-lg-none d-flex align-items-center justify-content-between">
                            <button class=" rv-1-header-mobile-menu-btn sidebar-close-btn rv-3-def-btn">
                                <i class="fa fa-xmark"></i>
                            </button>
                        </div>
                        <div class="rv-28-menubar rv-1-header__nav">
                            <ul class="rv-28-menubar__list">
                                <li><a href="index.php">Home</a>
                                </li>
                                <li><a href="about.php">About us</a></li>
                                <li><a href="products.php">Products</a>

                                </li>
                                <li><a href="blog.php">Blog</a>

                                </li>
                                <li><a href="contact.php">Contact us</a></li>
                            </ul>
                        </div>
                        <?php if (!empty($getBroucher) && $getBroucher['status'] == "active"): ?>
                            <a href="<?php echo htmlspecialchars($getBroucher['file_path']); ?>" class="a-book-btn res-menu-btn" target="_blank" rel="noopener noreferrer">Brochure
                                <i class="fa fa-angle-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="rv-28-menu-BookNow">
                        <?php if (!empty($getBroucher) && $getBroucher['status'] == "active"): ?>
                            <a href="<?php echo htmlspecialchars($getBroucher['file_path']); ?>" class="a-book-btn" target="_blank" rel="noopener noreferrer"> Brochure
                                <i class="fa fa-angle-right"></i>
                            </a>
                        <?php endif; ?>
                        <button class="rv-3-def-btn rv-1-header-mobile-menu-btn d-lg-none d-inline-block"
                            id="rv-1-header-mobile-menu-btn" aria-label="Open menu">
                            <i class="fa fa-bars" aria-hidden="true"></i>
                        </button>
                    </div>
                    <!-- Custom Language Switcher (moved outside) -->
                        <div class="lang-switcher dropdown header1-language notranslate" translate="no">
                            <button class="home-lang-btn dropdown-toggle" type="button" id="langDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="https://flagcdn.com/w20/gb.png" alt="" class="lang-flag" id="currentLangFlag" style="display:none;">
                                <span id="currentLangName" class="currentLangName" style="font-weight: 100 !important;">Select Language</span>
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
                </div>
            </div>
        </div>
    </div>
</header>