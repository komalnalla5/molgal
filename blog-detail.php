<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';
$site_id = SITE_ID;

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { header("Location: blog.php"); exit; }

$stmt = mysqli_prepare($conn, "SELECT * FROM blogs WHERE slug = ? AND site_id = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, "si", $slug, $site_id);
mysqli_stmt_execute($stmt);
$blog = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$blog) { header("Location: blog.php"); exit; }

function blog_image($row) {
    if (empty($row['banner_image'])) return 'assets/img/blog-banner.webp';
    if (strpos($row['banner_image'], 'http') === 0) return $row['banner_image'];
    if (strpos($row['banner_image'], 'uploads/') === 0) return ADMIN_BASE_URL . $row['banner_image'];
    return $row['banner_image'];
}
function blog_feature_image($row) {
    // Falls back to the Banner Background Image, then the default placeholder, if no
    // separate Feature Image was uploaded for this post.
    if (!empty($row['feature_image'])) {
        if (strpos($row['feature_image'], 'http') === 0) return $row['feature_image'];
        if (strpos($row['feature_image'], 'uploads/') === 0) return ADMIN_BASE_URL . $row['feature_image'];
        return $row['feature_image'];
    }
    return blog_image($row);
}
function blog_excerpt($content, $len = 130) {
    return htmlspecialchars(mb_strimwidth(strip_tags($content), 0, $len, '...'));
}
function read_time($content) {
    return max(1, ceil(str_word_count(strip_tags($content)) / 200));
}
// Admin-authored rich text (Introduction / Key Features / Applications / QA) is stored as HTML.
// Only allow a small safe formatting subset when printing it back out.
function rt_clean($html) {
    if ($html === null || $html === '') return '';
    return strip_tags($html, '<b><strong><i><em><u><br><span><p>');
}

$word_count = str_word_count(strip_tags($blog['content']));
$rt = max(1, ceil($word_count / 200));

$stmt = mysqli_prepare($conn, "SELECT DISTINCT category FROM blogs WHERE site_id=? AND status='active' AND category IS NOT NULL AND category != '' ORDER BY category ASC");
mysqli_stmt_bind_param($stmt, "i", $site_id);
mysqli_stmt_execute($stmt);
$category_names = array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'category');

$stmt2 = mysqli_prepare($conn, "SELECT title, slug, banner_image, category, created_at, content FROM blogs
                                 WHERE site_id=? AND status='active' AND id != ?
                                 ORDER BY created_at DESC LIMIT 4");
mysqli_stmt_bind_param($stmt2, "ii", $site_id, $blog['id']);
mysqli_stmt_execute($stmt2);
$related = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);

$stmt3 = mysqli_prepare($conn, "SELECT title, slug, banner_image, category, created_at, content FROM blogs
                                 WHERE site_id=? AND status='active' AND id != ?
                                 ORDER BY created_at DESC LIMIT 3");
mysqli_stmt_bind_param($stmt3, "ii", $site_id, $blog['id']);
mysqli_stmt_execute($stmt3);
$recent = mysqli_fetch_all(mysqli_stmt_get_result($stmt3), MYSQLI_ASSOC);

$current_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$tag_list = !empty($blog['tags']) ? array_filter(array_map('trim', explode(',', $blog['tags']))) : [];

$feature_list = !empty($blog['key_features']) ? array_filter(array_map('trim', explode("\n", $blog['key_features']))) : [];

$application_list = [];
if (!empty($blog['applications'])) {
    foreach (explode("\n", trim($blog['applications'])) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode('|', $line);
        if (count($parts) >= 3) {
            // New format: Icon|Title|Description
            $icon = trim($parts[0]) ?: 'fa-solid fa-flask';
            $title = trim($parts[1]);
            $desc = trim(implode('|', array_slice($parts, 2)));
        } else {
            // Legacy format: Title|Description (no icon stored) — fall back to a default icon
            $icon = 'fa-solid fa-flask';
            $title = trim($parts[0] ?? '');
            $desc = trim($parts[1] ?? '');
        }
        $application_list[] = ['icon' => $icon, 'title' => $title, 'desc' => $desc];
    }
}

$intro_heading = !empty($blog['intro_heading']) ? $blog['intro_heading'] : 'Introduction';
$key_features_heading = !empty($blog['key_features_heading']) ? $blog['key_features_heading'] : 'Key Features';
$applications_heading = !empty($blog['applications_heading']) ? $blog['applications_heading'] : 'Applications';
$qa_heading = !empty($blog['qa_heading']) ? $blog['qa_heading'] : 'Quality Assurance';
$hero_subtitle = !empty($blog['banner_subtitle']) ? $blog['banner_subtitle'] : mb_strimwidth(strip_tags($blog['intro_content']), 0, 200, '...');

$certifications = [];
foreach (getCertificate($conn, 'blog') as $certificate) {
    $certifications[] = [
        'label' => $certificate['cert_name'],
        'img' => getCertificateImageUrl($certificate['cert_img'])
    ];
}

$auditRes = mysqli_prepare($conn, "SELECT audit_title, audit_description FROM blog_page_settings WHERE site_id = ?");
mysqli_stmt_bind_param($auditRes, "i", $site_id);
mysqli_stmt_execute($auditRes);
$auditRow = mysqli_fetch_assoc(mysqli_stmt_get_result($auditRes));
$audit_title = !empty($auditRow['audit_title']) ? $auditRow['audit_title'] : 'Audit Ready';
$audit_description = !empty($auditRow['audit_description']) ? $auditRow['audit_description'] : 'Our certifications and quality systems ensure we are always audit ready and compliant.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?php echo htmlspecialchars($blog['meta_title'] ?: $blog['title']); ?></title>
   <meta name="description" content="<?php echo htmlspecialchars($blog['meta_description']); ?>">
   <?php if (!empty($blog['meta_keywords'])): ?><meta name="keywords" content="<?php echo htmlspecialchars($blog['meta_keywords']); ?>"><?php endif; ?>
   <link rel="canonical" href="<?php echo htmlspecialchars(!empty($blog['canonical_url']) ? $blog['canonical_url'] : $current_url); ?>">
   <?php if (!empty($blog['schema_markup']) && json_decode($blog['schema_markup']) !== null): ?><script type="application/ld+json"><?php echo $blog['schema_markup']; ?></script><?php endif; ?>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
   <?php include('includes/header-2.php'); ?>

   <style>
      :root{ --brand:#6d1220; --brand-dark:#4a0c16; --brand-light:#f6e9e5; }
      body{ font-family:'Poppins', sans-serif; }

      .blog-hero{ position: relative;
    min-height: 280px;
    overflow: hidden;
    background: linear-gradient(90deg, #7e3f3f 45%, rgba(74, 12, 22, .35));
    background-size: cover;
    background-position: center;
    color: #fff;
    padding: 26px 50px 30px; }
      .crumbs{ font-size:.82rem; opacity:.85; margin-bottom:14px; }
      .crumbs a{ color:#fff !important; text-decoration:none; }
      .blog-tag-badge{ border:1px solid #fff; padding:3px 12px; border-radius:5px; font-size:.7rem; font-weight:600; display:inline-block; margin-bottom:12px; }
      .hero-meta{ display:flex; gap:16px; font-size:.82rem; opacity:.85; margin-bottom:10px; }
      .hero-meta span{ display:flex; align-items:center; gap:5px; }
      .blog-hero h1{ font-family:'Playfair Display', serif; font-size:2.1rem; margin-bottom:10px; }
      .blog-hero p{ max-width:600px; opacity:.85; font-size:.92rem; line-height:1.6; }

      .filter-bar{ max-width:1200px; margin:-24px auto 0; background:#fff; border-radius:10px; box-shadow:0 6px 24px rgba(0,0,0,.12); padding:14px 18px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; position:relative; z-index:3; }
      .filter-bar form.search-form{ display:flex; align-items:center; gap:8px; border:1px solid #ddd; border-radius:8px; padding:9px 14px; flex:1; min-width:180px; color:var(--brand); }
      .filter-bar form.search-form svg{ width:17px; height:17px; flex-shrink:0; }
      .filter-bar form.search-form input{ border:none; outline:none; flex:1; font-family:inherit; }
      .category-select select,
      .sort-select select{ border:1px solid #ddd; border-radius:8px; padding:9px 36px 9px 14px; font-size:.85rem; font-family:inherit; background-color:#fff; color:#333; cursor:pointer; }
      .category-select select{ min-width:190px; }
      .sort-select{ margin-left:auto; }

      .blog-layout{ display:flex; gap:28px; max-width:1200px; margin:40px auto; padding:0 20px; align-items:flex-start; }
      .blog-main{ flex:2.3; } .blog-sidebar{ flex:1; min-width:280px; display:flex; flex-direction:column; gap:20px; }

      .feature-img{ position:relative; height:260px; border-radius:10px; overflow:hidden; margin-bottom:26px; }
      .feature-img img{ width:100%; height:100%; object-fit:cover; }
      .feature-img::after{ content:''; position:absolute; inset:0;  }

      .post-content{ color:#444; line-height:1.75; }
      .post-content h2, .post-content h3{ color:var(--brand); font-weight:600; margin:26px 0 10px; }
      .post-content p{ margin-bottom:14px; }
      .post-content ul{ list-style:none; padding:0; margin:0 0 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 20px; }
      .post-content ul li{ position:relative; padding-left:26px; font-size:.92rem; }
      .post-content ul li::before{ content:'\2713'; position:absolute; left:0; top:1px; width:17px; height:17px; border-radius:50%; background:var(--brand); color:#fff; font-size:.62rem; display:flex; align-items:center; justify-content:center; }

      .app-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin:16px 0 24px; }
      .app-card{ border:1px solid #eee; border-radius:10px; padding:18px 14px; text-align:center; }
      .app-card svg{ width:30px; height:30px; color:var(--brand); margin-bottom:10px; }
      .app-card i{ font-size:1.7rem; color:var(--brand); margin-bottom:10px; display:block; }
      .app-card h5{ font-size:.88rem; color:var(--brand-dark); margin-bottom:6px; font-weight:600; }
      .app-card p{ font-size:.76rem; color:#777; margin:0; line-height:1.5; }
      .section-divider{ display:flex; align-items:center; gap:12px; margin:24px 0; }
      .section-divider::before, .section-divider::after{ content:''; flex:1; border-top:1px dashed #ddd; }
      .section-divider .dot{ width:22px; height:22px; border:1.5px solid var(--brand); transform:rotate(45deg); }

      .qa-box{ background:var(--brand-light); border-radius:10px; padding:18px 20px; display:flex; gap:14px; margin:22px 0; }
      .qa-box .ico{ width:38px; height:38px; border-radius:50%; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
      .qa-box h4{ color:var(--brand); font-size:1rem; margin-bottom:6px; font-weight:600; }
      .qa-box p{ font-size:.88rem; color:#555; margin:0; line-height:1.6; }

      .tags-share{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin:24px 0; padding-top:18px; border-top:1px solid #eee; }
      .tag-pill{ background:#f1f1f1; color:#444; padding:5px 12px; border-radius:20px; font-size:.78rem; margin-right:6px; display:inline-block; }
      .share-row a{ width:30px; height:30px; border-radius:50%; border:1px solid var(--brand); color:var(--brand) !important; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; font-size:.8rem; margin-left:6px; }

      .related-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-top:16px; }
      .related-card{ border:1px solid #eee; border-radius:10px; overflow:hidden; text-decoration:none; color:inherit; }
      .related-card img{ width:100%; height:90px; object-fit:cover; }
      .related-card .body{ padding:12px; }
      .related-card .cat-tag{ background:var(--brand); color:#fff; font-size:.62rem; font-weight:600; padding:3px 9px; border-radius:4px; }
      .related-card h4{ font-size:.85rem; color:var(--brand-dark); margin:8px 0 4px; font-weight:600; line-height:1.35; }
      .related-card .meta{ font-size:.7rem; color:#888; margin-bottom:6px; }
      .related-card .read-more{ font-size:.78rem; color:var(--brand); font-weight:600; }

      .widget{ background:#fff; border:1px solid #eee; border-radius:10px; padding:18px; }
      .cert-widget{ border:1px solid #eee; border-radius:10px; overflow:hidden; }
      .cert-header{ background:#7e3f3f; color:#fff; padding:14px 16px; }
      .cert-header h3{ font-size:.95rem; margin:0; font-weight:600; }
      .cert-header small{ opacity:.75; font-size:.7rem; }
      .cert-grid{ display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:14px; }
      .cert-cell{ border:1px solid #e8d9d9; border-radius:8px; padding:12px 6px; text-align:center; }
      .cert-cell img{ height:95px; object-fit:contain; margin-bottom:6px; }
      .cert-cell span{ font-size:.68rem; color:var(--brand-dark); font-weight:600; display:block; }

      .audit-box{ background:#7e3f3f; color:#fff; border-radius:10px; padding:18px; display:flex; gap:12px; position:relative; overflow:hidden; }
      .audit-box .ico{ width:34px; height:34px; border-radius:50%; border:2px solid rgba(255,255,255,.5); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
      .audit-box h3{ font-size:.92rem; margin-bottom:5px; font-weight:600; }
      .audit-box p{ font-size:.76rem; opacity:.85; margin:0; line-height:1.5; }

      .recent-item{ display:flex; gap:10px; margin-bottom:14px; text-decoration:none; color:inherit; }
      .recent-item img{ width:56px; height:56px; object-fit:cover; border-radius:6px; flex-shrink:0; }
      .recent-item .cat-tag{ background:var(--brand); color:#fff; font-size:.6rem; font-weight:600; padding:2px 7px; border-radius:4px; }
      .recent-item h4{ font-size:.82rem; margin:5px 0 4px; color:var(--brand-dark); font-weight:600; line-height:1.3; }
      .recent-item span{ font-size:.7rem; color:#888; }
      .view-all{ text-align:center; margin-top:6px; }
      .view-all a{ color:var(--brand); font-weight:600; font-size:.85rem; text-decoration:none; }

      @media (max-width: 900px){ .blog-layout{ flex-direction:column; } .related-grid{ grid-template-columns:1fr 1fr; } .post-content ul{ grid-template-columns:1fr; } }
      @media (max-width: 560px){ .related-grid{ grid-template-columns:1fr; } }
   </style>
</head>

   <div class="blog-hero" style="background-image: url('<?php echo htmlspecialchars(blog_image($blog)); ?>');">
      <div class="crumbs">
         <a href="index.php">Home</a> &gt; <a href="blog.php">Blog</a> &gt; <?php echo htmlspecialchars($blog['title']); ?>
      </div>
      <span class="blog-tag-badge"><?php echo htmlspecialchars($blog['category'] ?: 'Blog'); ?></span>
      <div class="hero-meta">
         <span>&#128197; <?php echo date('M d, Y', strtotime($blog['created_at'])); ?></span>
         <span>&#128337; <?php echo $rt; ?> min read</span>
      </div>
      <h1><?php echo htmlspecialchars($blog['title']); ?></h1>
      <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
   </div>

   <div class="filter-bar">
      <form class="search-form" method="GET" action="blog.php">
         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
         <input type="text" name="search" placeholder="Search blogs...">
      </form>
      <form class="category-select" method="GET" action="blog.php">
         <select name="category" aria-label="Filter blogs by category" onchange="this.form.submit()">
            <option value="" <?php echo empty($blog['category']) ? 'selected' : ''; ?>>All Categories</option>
            <?php foreach ($category_names as $cat): ?>
               <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (string) $blog['category'] === (string) $cat ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat); ?>
               </option>
            <?php endforeach; ?>
         </select>
      </form>
      <div class="sort-select">
         <select onchange="window.location.href='blog.php?sort='+this.value">
            <option value="latest">Latest</option>
            <option value="oldest">Oldest</option>
         </select>
      </div>
   </div>

   <div class="blog-layout">
      <div class="blog-main">

         <div class="feature-img">
            <img src="<?php echo htmlspecialchars(blog_feature_image($blog)); ?>" alt="<?php echo htmlspecialchars($blog['feature_alt'] ?: $blog['banner_alt'] ?: $blog['title']); ?>">
         </div>

         <h2 style="color:var(--brand-dark); font-size:1.25rem; font-weight:600; margin-bottom:10px;"><?php echo htmlspecialchars($intro_heading); ?></h2>
         <div class="post-content">
            <p><?php echo rt_clean($blog['intro_content']); ?></p>
         </div>

         <?php if (!empty($feature_list)): ?>
         <div class="section-divider"><div class="dot"></div></div>
         <h2 style="color:var(--brand-dark); font-size:1.25rem; font-weight:600; margin-bottom:10px;"><?php echo htmlspecialchars($key_features_heading); ?></h2>
         <ul style="list-style:none; padding:0; margin:0 0 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 20px;">
            <?php foreach ($feature_list as $f): ?>
               <li style="position:relative; padding-left:26px; font-size:.92rem; color:#444;">
                  <span style="position:absolute; left:0; top:1px; width:17px; height:17px; border-radius:50%; background:var(--brand); color:#fff; font-size:.62rem; display:flex; align-items:center; justify-content:center;">&#10003;</span>
                  <?php echo rt_clean($f); ?>
               </li>
            <?php endforeach; ?>
         </ul>
         <?php endif; ?>

         <?php if (!empty($application_list)): ?>
         <h2 style="color:var(--brand-dark); font-size:1.25rem; font-weight:600; margin:22px 0 4px;"><?php echo htmlspecialchars($applications_heading); ?></h2>
         <div class="app-grid">
            <?php foreach ($application_list as $app): ?>
               <div class="app-card">
                  <i class="<?php echo htmlspecialchars($app['icon']); ?>"></i>
                  <h5><?php echo htmlspecialchars($app['title']); ?></h5>
                  <p><?php echo rt_clean($app['desc']); ?></p>
               </div>
            <?php endforeach; ?>
         </div>
         <?php endif; ?>

         <?php if (!empty($blog['qa_content'])): ?>
         <div class="qa-box">
            <div class="ico">&#128737;</div>
            <div>
               <h4><?php echo htmlspecialchars($qa_heading); ?></h4>
               <p><?php echo rt_clean($blog['qa_content']); ?></p>
            </div>
         </div>
         <?php endif; ?>

         <div class="tags-share">
            <div>
               <?php foreach ($tag_list as $tag): ?><span class="tag-pill"><?php echo htmlspecialchars($tag); ?></span><?php endforeach; ?>
            </div>
            <div class="share-row">
               Share:
               <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener">f</a>
               <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($blog['title']); ?>" target="_blank" rel="noopener">X</a>
               <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener">in</a>
               <a href="mailto:?subject=<?php echo urlencode($blog['title']); ?>&body=<?php echo urlencode($current_url); ?>">@</a>
            </div>
         </div>

         <?php if (!empty($related)): ?>
         <h2 style="color:var(--brand-dark); font-size:1.2rem; font-weight:600; margin-bottom:4px;">Related Blogs</h2>
         <div class="related-grid">
            <?php foreach ($related as $r): ?>
               <a href="blog-detail.php?slug=<?php echo urlencode($r['slug']); ?>" class="related-card">
                  <img src="<?php echo htmlspecialchars(blog_image($r)); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>">
                  <div class="body">
                     <?php if (!empty($r['category'])): ?><span class="cat-tag"><?php echo htmlspecialchars($r['category']); ?></span><?php endif; ?>
                     <h4><?php echo htmlspecialchars($r['title']); ?></h4>
                     <div class="meta"><?php echo date('M d, Y', strtotime($r['created_at'])); ?> &middot; <?php echo read_time($r['content']); ?> min read</div>
                     <span class="read-more">Read More &#8594;</span>
                  </div>
               </a>
            <?php endforeach; ?>
         </div>
         <?php endif; ?>
      </div>

      <div class="blog-sidebar">
         <div class="cert-widget">
            <div class="cert-header"><h3>Certificates</h3><small>Added from Admin</small></div>
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
         </div>

         <?php if (!empty($recent)): ?>
         <div class="widget">
            <h3 style="color:var(--brand-dark); font-size:.95rem; margin-bottom:14px; font-weight:600;">Recent Blogs</h3>
            <?php foreach ($recent as $r): ?>
               <a href="blog-detail.php?slug=<?php echo urlencode($r['slug']); ?>" class="recent-item">
                  <img src="<?php echo htmlspecialchars(blog_image($r)); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>">
                  <div>
                     <?php if (!empty($r['category'])): ?><span class="cat-tag"><?php echo htmlspecialchars($r['category']); ?></span><?php endif; ?>
                     <h4><?php echo htmlspecialchars($r['title']); ?></h4>
                     <span><?php echo date('M d, Y', strtotime($r['created_at'])); ?> &middot; <?php echo read_time($r['content']); ?> min read</span>
                  </div>
               </a>
            <?php endforeach; ?>
            <div class="view-all"><a href="blog.php">View all blogs &#8594;</a></div>
         </div>
         <?php endif; ?>
      </div>
   </div>

   <?php include('includes/footer.php'); ?>
</html>
