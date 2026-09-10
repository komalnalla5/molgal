<?php
include('config.php');

// Fetch all active certificates for this site
$certificates = getCertificate($conn, 'blog');
$certificates = array_slice($certificates, 0, 12);
?>

<div class="blog-sidebar">
         <div class="cert-widget">
            <div class="cert-header"><h3>Certificates</h3><small>Added from Admin</small></div>
            <div class="cert-grid">
        <?php if (!empty($certificates)): ?>
            <?php foreach ($certificates as $certificate): ?>
                <div class="cert-cell">
                    <img src="<?php echo htmlspecialchars(CERTIFICATE_BASE_URL . $certificate['cert_img']); ?>"
                        alt="<?php echo htmlspecialchars($certificate['alt_text'] ?: $certificate['cert_name']); ?>"
                        loading="lazy">
                        <span><?php echo htmlspecialchars($certificate['cert_name']); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small mb-0">No certificates available.</p>
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

         


