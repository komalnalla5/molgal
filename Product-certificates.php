<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';


// Fetch all active certificates for this site
$certificates = getCertificate($conn,'product');
?>
<div class="det-wrapper mb-3">
    <div class="spec-table-det-container">
        <h3>ACCREDITATIONS</h3>
        <div class="swiper productSlider">
            <div class="swiper-wrapper">
                <?php if (!empty($certificates)): ?>
                    <?php foreach ($certificates as $certificate):?>                   
                        <div class="swiper-slide">
                            <img src="<?php echo htmlspecialchars(getCertificateImageUrl($certificate['cert_img']), ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($certificate['alt_text'] ?: $certificate['cert_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                loading="lazy">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No certificates available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
