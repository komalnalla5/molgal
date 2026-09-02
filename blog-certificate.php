<?php
include('config.php');
include('includes/helper.php');
// Fetch all active certificates for this site
$certificates = getCertificate($conn,'blog');
$certificates = array_slice($certificates, 0, 10);
?>
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
                    <p>No certificates available.</p>
                <?php endif; ?>
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

