<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';
$site_id = SITE_ID;

$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort     = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM blogs WHERE site_id=? AND status='active'");
mysqli_stmt_bind_param($stmt, "i", $site_id);
mysqli_stmt_execute($stmt);
$total_articles = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

$stmt = mysqli_prepare($conn, "SELECT DISTINCT category FROM blogs WHERE site_id=? AND status='active' AND category IS NOT NULL AND category != ''");
mysqli_stmt_bind_param($stmt, "i", $site_id);
mysqli_stmt_execute($stmt);
$categories = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$category_names = array_column($categories, 'category');

$where = "WHERE site_id=? AND status='active'";
$types = "i";
$params = [$site_id];

if ($search !== '') { $where .= " AND title LIKE ?"; $types .= "s"; $params[] = "%$search%"; }
if ($category !== '' && $category !== 'All') { $where .= " AND category = ?"; $types .= "s"; $params[] = $category; }

$order = ($sort === 'oldest') ? "created_at ASC" : "created_at DESC";
$sql = "SELECT * FROM blogs $where ORDER BY $order";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$blogs = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$featured = $blogs[0] ?? null;
$rest = array_slice($blogs, 1);

function blog_image($row) {
    if (empty($row['banner_image'])) return 'assets/img/blog-banner.webp';
    if (strpos($row['banner_image'], 'http') === 0) return $row['banner_image'];
    if (strpos($row['banner_image'], 'uploads/') === 0) return ADMIN_BASE_URL . $row['banner_image'];
    return $row['banner_image'];
}
function blog_excerpt($content, $len = 130) {
    return htmlspecialchars(mb_strimwidth(strip_tags($content), 0, $len, '...'));
}
function read_time($content) {
    return max(1, ceil(str_word_count(strip_tags($content)) / 200));
}

$stmt = mysqli_prepare($conn, "SELECT hero_title, hero_subtitle, hero_banner_image, audit_title, audit_description FROM blog_page_settings WHERE site_id = ?");
mysqli_stmt_bind_param($stmt, "i", $site_id);
mysqli_stmt_execute($stmt);
$hero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$hero_title = !empty($hero['hero_title']) ? $hero['hero_title'] : 'Our Blog';
$hero_subtitle = !empty($hero['hero_subtitle']) ? $hero['hero_subtitle'] : 'Pharmaceutical Online';
$audit_title = !empty($hero['audit_title']) ? $hero['audit_title'] : 'Audit Ready';
$audit_description = !empty($hero['audit_description']) ? $hero['audit_description'] : 'Our certifications and quality systems ensure we are always audit ready and compliant.';
$hero_banner = 'assets/img/blog-hero.webp';
if (!empty($hero['hero_banner_image'])) {
    if (strpos($hero['hero_banner_image'], 'http') === 0) {
        $hero_banner = $hero['hero_banner_image'];
    } elseif (strpos($hero['hero_banner_image'], 'uploads/') === 0) {
        $hero_banner = ADMIN_BASE_URL . $hero['hero_banner_image'];
    } else {
        $hero_banner = $hero['hero_banner_image'];
    }
}

$certifications = [];
foreach (getCertificate($conn, 'blog') as $certificate) {
    $certifications[] = [
        'label' => $certificate['cert_name'],
        'img' => getCertificateImageUrl($certificate['cert_img'])
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?php echo htmlspecialchars($hero_title); ?></title>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
   <?php include('includes/header-2.php'); ?>

   <style>
      :root{ --brand:#6d1220; --brand-dark:#4a0c16; --brand-light:#f3e2e2; }
      body{ font-family:'Poppins', sans-serif; }

      .blog-hero{ position:relative; min-height:360px; overflow:hidden; background:var(--brand-dark); }
      .blog-hero-bg{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
      .blog-hero-shape{ position:absolute; inset:0; width:100%; height:100%; z-index:1; }
      .blog-hero-inner{ position:relative; z-index:2; display:flex; min-height:340px; }
      .cat-pill:hover {color: #7e3f3f !important;}
      .blog-hero-left{ width:56%; padding:60px 50px; display:flex; flex-direction:column; justify-content:center; color:#fff; }
      .blog-hero-left h1{ font-family:'Playfair Display', serif; font-size:2.6rem; line-height:1.2; margin-bottom:10px; }
      .blog-hero-left p{ opacity:.85; margin-bottom:8px; font-size:1rem; }
      .subtitle-line{ width:36px; height:2px; background:rgba(255,255,255,.5); margin-bottom:22px; }
      .stat-pill svg{ width:15px; height:15px; flex-shrink:0; }
      .blog-hero-right{ flex:1; }

      .stat-pills{ display:flex; gap:12px; flex-wrap:wrap; }
      .stat-pill{ border:1px solid rgba(255,255,255,.4); border-radius:20px; padding:7px 16px; font-size:.82rem; display:flex; align-items:center; gap:7px; }

      .filter-bar{ max-width:1200px; margin:-28px auto 0; background:#fff; border-radius:10px; box-shadow:0 6px 24px rgba(0,0,0,.12); padding:14px 18px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; position:relative; z-index:3; }
      .filter-bar form.search-form{ display:flex; align-items:center; gap:8px; border:1px solid #ddd; border-radius:8px; padding:9px 14px; flex:1; min-width:180px; color:var(--brand); }
      .filter-bar form.search-form svg{ width:17px; height:17px; color:var(--brand); flex-shrink:0; }
      .filter-bar form.search-form input{ border:none; outline:none; flex:1; font-family:inherit; }
      .cat-pill{ border:1px solid #ddd; border-radius:8px; padding:8px 18px; font-size:.85rem; text-decoration:none; color:#333; white-space:nowrap; font-weight:500; }
      .cat-pill.active{ background:#7e3f3f; border-color:var(--brand); color:#fff !important; }
      .sort-select select{ border:1px solid #ddd; border-radius:8px; padding:9px 14px; font-size:.85rem; font-family:inherit; }

      .blog-layout{ display:flex; gap:28px; max-width:1200px; margin:40px auto; padding:0 20px; align-items:flex-start; }
      .blog-main{ flex:2.5; } .blog-sidebar{ flex:1; min-width:280px; }

      .section-title{ font-size:1.5rem; color:var(--brand-dark); font-weight:600; position:relative; padding-bottom:12px; margin-bottom:24px; }
      .section-title::after{ content:''; position:absolute; left:0; bottom:0; width:50px; height:3px; background:var(--brand); }
      .section-title .dots{ position:absolute; left:60px; bottom:0px; display:flex; gap:5px; align-items:center; }
      .section-title .dots span{ width:4px; height:4px; border-radius:50%; background:var(--brand); opacity:.5; }
      .section-title .dots span:nth-child(2){ width:6px; height:6px; opacity:.8; }

      .featured-card{ display:flex; border:1px solid #eee; border-radius:10px; overflow:hidden; margin-bottom:24px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
      .featured-card img{ width:44%; object-fit:cover; }
      .featured-card .body{ flex:1; padding:22px 24px; display:flex; flex-direction:column; justify-content:center; }
      .card-meta{ display:flex; align-items:center; gap:14px; font-size:.78rem; color:#888; margin-bottom:10px; }
      .cat-tag{ background:var(--brand); color:#fff; padding:4px 12px; border-radius:5px; font-size:.68rem; font-weight:600; }
      .featured-card h3{ font-size:1.4rem; color:var(--brand-dark); margin-bottom:10px; font-weight:600; }
      .featured-card p{ color:#666; font-size:.9rem; margin-bottom:14px; line-height:1.6; }
      .read-more{ color:var(--brand); font-weight:600; text-decoration:none; font-size:.85rem; }

      .post-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:18px; }
      .post-card{ border:1px solid #eee; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.03); }
      .post-card img{ width:100%; height:110px; object-fit:cover; }
      .post-card .body{ padding:14px; }
      .post-card h4{ font-size:.9rem; color:var(--brand-dark); margin:8px 0 6px; font-weight:600; line-height:1.35; }
      .post-card p{ font-size:.78rem; color:#666; margin-bottom:10px; line-height:1.5; }

      .cert-widget{ border:1px solid #eee; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.04); }
      .cert-header{ background:#7e3f3f; color:#fff; padding:16px 18px; display:flex; align-items:center; gap:10px; }
      .cert-header div h3{ font-size:1rem; margin:0; font-weight:600; }
      .cert-header div small{ opacity:.75; font-size:.72rem; }
      .cert-grid{ display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:16px; }
      .cert-cell{ border:1px solid #e8d9d9; border-radius:8px; padding:14px 6px; text-align:center; }
      .cert-cell img{ height:95px; object-fit:contain; margin-bottom:8px; }
      .cert-cell span{ font-size:.72rem; color:var(--brand-dark); font-weight:600; display:block; }

      .audit-box{ background:#7e3f3f; color:#fff; border-radius:10px; padding:20px; display:flex; align-items:flex-start; gap:14px; margin-top:16px; position:relative; overflow:hidden; }
      .audit-box .hex-corner{ position:absolute; right:-10px; bottom:-10px; width:70px; height:70px; opacity:.5; }
      .audit-box .ico{ width:36px; height:36px; border-radius:50%; border:2px solid rgba(255,255,255,.5); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
      .audit-box h3{ font-size:.98rem; margin-bottom:6px; font-weight:600; }
      .audit-box p{ font-size:.8rem; opacity:.85; margin:0; line-height:1.5; }

      @media (max-width: 900px){
         .blog-hero-shape{ display:none; }
         .blog-hero::before{ content:''; position:absolute; inset:0; background:linear-gradient(180deg, rgba(74,12,22,.75), rgba(74,12,22,.92)); z-index:1; }
         .blog-hero-inner{ flex-direction:column; }
         .blog-hero-left{ width:100%; padding:40px 24px; }
         .blog-hero-right{ display:none; }
         .blog-layout{ flex-direction:column; }
         .post-grid{ grid-template-columns:1fr 1fr; }
         .featured-card{ flex-direction:column; }
         .featured-card img{ width:100%; height:220px; }
      }
      @media (max-width: 560px){ .post-grid{ grid-template-columns:1fr; } }
   </style>
</head>

   <div class="blog-hero">
      <img class="blog-hero-bg" src="<?php echo htmlspecialchars($hero_banner); ?>" alt="<?php echo htmlspecialchars($hero_title); ?>">
      <svg class="blog-hero-shape" viewBox="0 0 1000 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
         <defs>
            <linearGradient id="heroGrad" x1="0" y1="0" x2="1" y2="1">
               <stop offset="0" stop-color="#4a0c16"/>
               <stop offset="1" stop-color="#7a1a26"/>
            </linearGradient>
         </defs>
         <polygon points="0,0 500,0 420,200 500,400 0,400" fill="url(#heroGrad)"/>
         <g opacity="0.18" stroke="#ffffff" stroke-width="2" fill="none">
            <path d="M330 20 L370 20 L390 55 L370 90 L330 90 L310 55 Z"/>
            <path d="M395 70 L430 70 L448 100 L430 130 L395 130 L377 100 Z"/>
            <path d="M300 100 L325 100 L338 122 L325 144 L300 144 L287 122 Z"/>
         </g>
         <g opacity="0.15" fill="#ffffff">
            <circle cx="250" cy="40" r="2"/><circle cx="270" cy="40" r="2"/><circle cx="290" cy="40" r="2"/>
            <circle cx="250" cy="58" r="2"/><circle cx="270" cy="58" r="2"/><circle cx="290" cy="58" r="2"/>
            <circle cx="250" cy="76" r="2"/><circle cx="270" cy="76" r="2"/><circle cx="290" cy="76" r="2"/>
         </g>
         <polyline points="500,0 420,200 500,400" fill="none" stroke="#ffffff" stroke-opacity="0.45" stroke-width="3"/>
      </svg>
      <div class="blog-hero-inner">
         <div class="blog-hero-left">
            <h1><?php echo htmlspecialchars($hero_title); ?></h1>
            <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
            <div class="subtitle-line"></div>
            <div class="stat-pills">
               <span class="stat-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v5h5M6 3h8l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/></svg> <?php echo $total_articles; ?> Articles</span>
               <span class="stat-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> <?php echo count($category_names); ?> Categories</span>
               <span class="stat-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5z"/></svg> Audit Ready</span>
            </div>
         </div>
         <div class="blog-hero-right"></div>
      </div>
   </div>

   <div class="filter-bar">
      <form class="search-form" method="GET" action="blog.php">
         <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
         <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
         <input type="text" name="search" placeholder="Search blogs..." value="<?php echo htmlspecialchars($search); ?>">
      </form>

      <a href="blog.php?sort=<?php echo urlencode($sort); ?>" class="cat-pill <?php echo $category==='' ? 'active' : ''; ?>">All</a>
      <?php foreach ($category_names as $cat): ?>
         <a href="blog.php?category=<?php echo urlencode($cat); ?>&sort=<?php echo urlencode($sort); ?>" class="cat-pill <?php echo $category===$cat ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat); ?></a>
      <?php endforeach; ?>

      <form class="sort-select" style="margin-left:auto;" method="GET" action="blog.php">
         <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
         <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
         <select name="sort" onchange="this.form.submit()">
            <option value="latest" <?php echo $sort==='latest'?'selected':''; ?>>Latest</option>
            <option value="oldest" <?php echo $sort==='oldest'?'selected':''; ?>>Oldest</option>
         </select>
      </form>
   </div>

   <div class="blog-layout">
      <div class="blog-main">
         <h2 class="section-title">All Blogs<span class="dots"><span></span><span></span><span></span></span></h2>

         <?php if (!$featured): ?>
            <p>No blogs found.</p>
         <?php else: ?>
            <a href="blog-detail.php?slug=<?php echo urlencode($featured['slug']); ?>" style="text-decoration:none; color:inherit;">
               <div class="featured-card">
                  <img src="<?php echo htmlspecialchars(blog_image($featured)); ?>" alt="<?php echo htmlspecialchars($featured['title']); ?>">
                  <div class="body">
                     <?php if (!empty($featured['category'])): ?>
                        <div style="margin-bottom:10px;"><span class="cat-tag"><?php echo htmlspecialchars($featured['category']); ?></span></div>
                     <?php endif; ?>
                     <div class="card-meta">
                        <span>&#128197; <?php echo date('M d, Y', strtotime($featured['created_at'])); ?></span>
                        <span>&#128337; <?php echo read_time($featured['content']); ?> min read</span>
                     </div>
                     <h3><?php echo htmlspecialchars($featured['title']); ?></h3>
                     <p><?php echo blog_excerpt($featured['intro_content'] ?: $featured['content'], 160); ?></p>
                     <span class="read-more">Read More &#8594;</span>
                  </div>
               </div>
            </a>

            <div class="post-grid">
               <?php foreach ($rest as $blog): ?>
                  <a href="blog-detail.php?slug=<?php echo urlencode($blog['slug']); ?>" style="text-decoration:none; color:inherit;">
                     <div class="post-card">
                        <img src="<?php echo htmlspecialchars(blog_image($blog)); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>">
                        <div class="body">
                           <?php if (!empty($blog['category'])): ?>
                              <span class="cat-tag"><?php echo htmlspecialchars($blog['category']); ?></span>
                           <?php endif; ?>
                           <div class="card-meta">
                              <span>&#128197; <?php echo date('M d, Y', strtotime($blog['created_at'])); ?></span>
                              <span>&#128337; <?php echo read_time($blog['content']); ?> min</span>
                           </div>
                           <h4><?php echo htmlspecialchars($blog['title']); ?></h4>
                           <p><?php echo blog_excerpt($blog['intro_content'] ?: $blog['content'], 70); ?></p>
                           <span class="read-more">Read More &#8594;</span>
                        </div>
                     </div>
                  </a>
               <?php endforeach; ?>
            </div>
         <?php endif; ?>
      </div>

      <div class="blog-sidebar">
         <div class="cert-widget">
            <div class="cert-header">
               <div style="font-size:1.3rem;">&#127942;</div>
               <div>
                  <h3>Certificates</h3>
                  <small>Added from Admin</small>
               </div>
            </div>
            <div class="cert-grid">
               <?php if (!empty($certifications)): foreach ($certifications as $c): ?>
                  <div class="cert-cell">
                     <img src="<?php echo htmlspecialchars($c['img']); ?>" alt="<?php echo htmlspecialchars($c['label']); ?>" loading="lazy" onerror="this.style.display='none'">
                     <span><?php echo htmlspecialchars($c['label']); ?></span>
                  </div>
               <?php endforeach; else: ?>
                  <p class="text-muted small mb-0">No certificates added yet.</p>
               <?php endif; ?>
            </div>
         </div>

         <div class="audit-box">
            <div class="ico">&#128737;</div>
            <div>
               <h3><?php echo htmlspecialchars($audit_title); ?></h3>
               <p><?php echo htmlspecialchars($audit_description); ?></p>
            </div>
            <svg class="hex-corner" viewBox="0 0 70 70" xmlns="http://www.w3.org/2000/svg">
               <path d="M17 0 L53 0 L70 30 L53 60 L17 60 L0 30 Z" fill="none" stroke="#ffffff" stroke-opacity="0.5" stroke-width="1.5"/>
               <path d="M27 15 L43 15 L51 30 L43 45 L27 45 L19 30 Z" fill="none" stroke="#ffffff" stroke-opacity="0.5" stroke-width="1.5"/>
            </svg>
         </div>
      </div>
   </div>

   <?php include('includes/footer.php'); ?>
</html>
