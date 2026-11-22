<?php
/**
 * views/front/includes/footer.php
 * Pied de page du frontoffice (template Novena) + bouton "Retour en haut".
 */
?>
<footer class="footer section gray-bg">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mr-auto col-sm-6">
                <div class="widget mb-5 mb-lg-0">
                    <div class="logo mb-4">
                        <img src="assets/front/images/logo-labtrack.png" alt="LabTrack" class="img-fluid lt-logo-footer">
                    </div>
                    <p><?= $t['footer_about'] ?></p>
                    <p class="lt-disclaimer"><i class="icofont-doctor mr-2"></i><?= $t['footer'] ?></p>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-sm-6">
                <div class="widget mb-5 mb-lg-0">
                    <h4 class="text-capitalize mb-3"><?= $t['footer_links'] ?></h4>
                    <div class="divider mb-4"></div>
                    <ul class="list-unstyled footer-menu lh-35">
                        <li><a href="index.php?page=home"><?= $t['nav_home'] ?></a></li>
                        <li><a href="index.php?page=login"><?= $t['nav_login'] ?></a></li>
                        <li><a href="index.php?page=register"><?= $t['nav_register'] ?></a></li>
                        <li><a href="index.php?page=dashboard"><?= $t['nav_dashboard'] ?></a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-sm-6">
                <div class="widget mb-5 mb-lg-0">
                    <h4 class="text-capitalize mb-3"><?= $t['footer_languages'] ?></h4>
                    <div class="divider mb-4"></div>
                    <ul class="list-unstyled footer-menu lh-35">
                        <li><?= $t['lang_ar'] ?></li>
                        <li><?= $t['lang_fr'] ?></li>
                        <li><?= $t['lang_en'] ?></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="widget widget-contact mb-5 mb-lg-0">
                    <h4 class="text-capitalize mb-3"><?= $t['footer_academic'] ?></h4>
                    <div class="divider mb-4"></div>
                    <div class="footer-contact-block mb-4">
                        <div class="icon d-flex align-items-center">
                            <i class="icofont-graduate-alt mr-3"></i>
                            <span class="h6 mb-0">Esprit School of Engineering</span>
                        </div>
                        <h4 class="mt-2">Projet Web 2A · 2026–2027</h4>
                    </div>
                    <div class="footer-contact-block">
                        <div class="icon d-flex align-items-center">
                            <i class="icofont-email mr-3"></i>
                            <span class="h6 mb-0">Contact</span>
                        </div>
                        <h4 class="mt-2"><a href="mailto:contact@labtrack.tn">contact@labtrack.tn</a></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-btm py-4 mt-5">
            <div class="row align-items-center justify-content-between">
                <div class="col-lg-8">
                    <div class="copyright">
                        &copy; <?= date('Y') ?> <?= $t['footer_rights'] ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="copyright text-lg-right mt-3 mt-lg-0">
                        Template : <a href="https://themefisher.com/">Novena by Themefisher</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4">
                    <a class="backtop scroll-top-to" href="#top" title="<?= $t['back_top'] ?>">
                        <i class="icofont-long-arrow-up"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>
