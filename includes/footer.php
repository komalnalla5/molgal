<?php
 include_once('helper.php');
include_once('config.php');

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
$certificates = $certificates ?? [];
?>
<footer>
    <div class="footer-sec">
        <div class="container">
            <div class="row">
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class=" footer-logo">
                        <a href="index.php">
                           <img src="<?php
                            echo ($headerSite && !empty($headerSite['logo']))
                                ? htmlspecialchars($headerSite['logo'])
                                : 'assets/img/molprop-white.png';
                            ?>" style="width:200px;" alt="<?php echo htmlspecialchars($headerSite['logo_alt'])?>" loading="lazy">
                        </a><br>
                        <div class="custom-footer">
                            <a href="tel: +91-22-2377 0100">
                                <i class="fa fa-phone text-white "></i> +91-22-2377 0100
                            </a><br>
                            <a href="mailto:ask@mubychem.com">
                                <i class="fa-sharp fa-regular fa-envelope text-white"></i> ask@mubychem.com
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-sm-6 col-6">
                    <div class=" footer-links">
                        <h4 class="links-courses ">Manufactured at:</h4>
                        <ul class=" footer-ul">

                            <li class=""><a href="https://share.google/rFGjL1u8jPVMuCXa2" target="_blank"><i
                                        class="fa-sharp fa fa-location-dot me-2"></i>
                                    Ankleshwar, India</a>
                            </li>
                            <li class=""><a href="https://share.google/wdoyWi9FrhPnt1TU4" target="_blank"><i
                                        class="fa-sharp fa fa-location-dot me-2"></i>
                                    Mumbai, India</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-4 col-sm-6 col-6">
                    <div class=" footer-courses ">
                        <h4 class="links-courses">Useful links</h4>
                        <div class="row " style="margin-left:0px">
                            <div class="col-6 ">
                                <ul class="footer-ul2">
                                    <li class=""><i class="fa fa-angle-right"></i>
                                        <a href="index.php">Home</a>
                                    </li>
                                    <li><i class="fa fa-angle-right"></i>
                                        <a href="about.php">About us</a>
                                    </li>
                                    <li><i class="fa fa-angle-right"></i>
                                        <a href="products.php">Products</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-6 minus-mt-9">
                                <ul class=" footer-ul2 ">
                                    <li><i class="fa fa-angle-right"></i>
                                        <a href="#">Blog</a>
                                    </li>
                                    <li><i class="fa fa-angle-right"></i>
                                        <a href="contact.php">Contact us</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class=" small-footer">
                <div class="copyright">
                    Copyright © 2025 <a href="index.php">Muby Chem Private Limited</a> || All Rights Reserved
                </div>
                <div class="follow-us">
                    <h6 class="text-white ">Follow Us</h6>
                    <div class="follow-us-ul d-flex align-items-center">

                        <a href="https://www.facebook.com/people/Muby-Chem-Private-Limited/61583203333051/" target="_blank"
                            aria-label="Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="https://x.com/mubychemicals" target="_blank" aria-label="Twitter/X">
                            <i class="fa-brands fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/company/muby-chem-pvt-ltd/?viewAsMember=true" target="_blank"
                            aria-label="LinkedIn">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                        <a href="https://www.instagram.com/muby_chem_private_limited/" target="_blank" aria-label="Instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous">
</script>
<script src="assets/js/script.js"></script>
<?php $certCount = count($certificates); ?>
<script>
    var swiper = new Swiper(".productSlider", {
        slidesPerView: 5,
        spaceBetween: 30,
        loop: <?php echo $certCount > 6 ? 'true' : 'false'; ?>,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev"
        },
        autoplay: {
            delay: 2500,
            disableOnInteraction: false
        },
        breakpoints: {
            0: {
                slidesPerView: 1
            },
            768: {
                slidesPerView: 2
            },
            1024: {
                slidesPerView: 6
            }
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const header = document.querySelector('.to-be-fixed');
        const mobileMenuButton = document.getElementById('rv-1-header-mobile-menu-btn');
        const sidebar = document.querySelector('.rv-1-header-nav__sidebar');
        const sidebarCloseButton = document.querySelector('.sidebar-close-btn');
        const mobileMenuOverlay = document.createElement('div');
        mobileMenuOverlay.classList.add('mobile-menu-overlay');
        document.body.appendChild(mobileMenuOverlay); 

        const handleScroll = () => {
            if (window.scrollY >
                0) {
                header.classList.add('fixed');
            } else {
                header.classList.remove('fixed');
            }
        };

        window.addEventListener('scroll', handleScroll);
        handleScroll();
        // Mobile Menu Toggle Logic
        const toggleMobileMenu = () => {
            sidebar.classList.toggle('open');
            mobileMenuOverlay.classList.toggle('show');
            document.body.classList.toggle('no-scroll'); 
        };

        mobileMenuButton.addEventListener('click', toggleMobileMenu);
        sidebarCloseButton.addEventListener('click', toggleMobileMenu);
        mobileMenuOverlay.addEventListener('click', toggleMobileMenu); 

    });

    document.addEventListener("DOMContentLoaded", function() {
        const cardsPerPage = 8;
        const cardContainer = document.getElementById("card-container");
        const cards = Array.from(cardContainer.getElementsByClassName("product-card"));
        const prevBtn = document.getElementById("prev-btn");
        const nextBtn = document.getElementById("next-btn");
        const pageInfo = document.getElementById("page-info");

        let currentPage = 1;
        const totalPages = Math.ceil(cards.length / cardsPerPage);

        function updatePage() {
            const start = (currentPage - 1) * cardsPerPage;
            const end = start + cardsPerPage;

            cards.forEach((card, index) => {
                card.style.display = (index >= start && index < end) ? "block" : "none";
            });

            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        prevBtn.addEventListener("click", () => {
            if (currentPage > 1) {
                currentPage--;
                updatePage();
            }
        });

        nextBtn.addEventListener("click", () => {
            if (currentPage < totalPages) {
                currentPage++;
                updatePage();
            }
        });

        updatePage();
    });

    $(document).ready(function() {
        $('.fancybox-icon-title').on('click', function() {
            $(this).siblings('.fancybox-body').find('.fancybox-desc-wrapper').slideToggle();
        });
    });

    $(document).ready(function() {
        setTimeout(function() {
            const $target = $('#page-content');

            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top
                }, 1000);
            }
        }, 2000); 
    });

</script>

<!-- language  -->
<div id="google_translate_element" style="display:none;"></div>
<script>
    // Initialize Google Translate
    function googleTranslateElementInit() {
        new google.translate.TranslateElement(
            { pageLanguage: 'en' },
            'google_translate_element'
        );
    }

    setInterval(function () {
        if (document.body.style.top && document.body.style.top !== '0px') {
            document.body.style.top = '0px';
        }
        var banner = document.querySelector('iframe.skiptranslate');
        if (banner) {
            banner.style.display = 'none';
        }
    }, 300);

    // Language switcher
    document.addEventListener('DOMContentLoaded', function () {
        const langOptions = document.querySelectorAll('.lang-option');
        const currentLangFlag = document.getElementById('currentLangFlag');
        const currentLangName = document.getElementById('currentLangName');

        function setGoogleTranslateLanguage(langCode) {
            const cookieValue = '/en/' + langCode;
            document.cookie = 'googtrans=' + cookieValue + '; path=/';
            document.cookie = 'googtrans=' + cookieValue + '; path=/; domain=' + window.location.hostname;
            window.location.reload();
        }

        langOptions.forEach(function (option) {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                const langCode = this.getAttribute('data-lang');
                const langName = this.getAttribute('data-name');
                const flagSrc = this.querySelector('.lang-flag').getAttribute('src');
                if (currentLangFlag) currentLangFlag.src = flagSrc;
                if (currentLangName) currentLangName.textContent = langName;
                localStorage.setItem('preferredLang', langCode);
                setGoogleTranslateLanguage(langCode);
            });
        });

       const savedLang = localStorage.getItem('preferredLang');
        if (savedLang && savedLang !== 'en') {
            const matchedOption = document.querySelector('.lang-option[data-lang="' + savedLang + '"]');
            if (matchedOption) {
                const flagSrc = matchedOption.querySelector('.lang-flag').getAttribute('src');
                const langName = matchedOption.getAttribute('data-name');
                if (currentLangFlag) {
                    currentLangFlag.src = flagSrc;
                    currentLangFlag.style.display = 'inline-block';
                }
                if (currentLangName) currentLangName.textContent = langName;
            }
        }
    });
</script>
<script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
