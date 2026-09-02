<?php
include('config.php');
include('includes/helper.php');

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



         


