<?php

$currentPage = basename($_SERVER["PHP_SELF"]);

$hideFooterOnContact = (
    $currentPage === "contact.php"
);

?>

<?php if (!$hideFooterOnContact): ?>

<footer
    class="site-footer"
    id="siteFooter"
>

    <div class="container footer-grid">


        <div class="footer-brand">

            <img
                src="assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
            >

            <p>
                Your ultimate gaming destination.<br>
                Play hard. Win big. Repeat.
            </p>


            <div class="social-links">

                <a
                    href="#"
                    aria-label="Facebook"
                >
                    f
                </a>

                <a
                    href="#"
                    aria-label="Instagram"
                >
                    ◎
                </a>

                <a
                    href="#"
                    aria-label="Discord"
                >
                    ◉
                </a>

                <a
                    href="#"
                    aria-label="TikTok"
                >
                    ♪
                </a>

            </div>

        </div>


        <div class="footer-column">

            <h3>
                QUICK LINKS
            </h3>

            <a href="index.php#home">
                Home
            </a>

            <a href="index.php#pcs">
                PCs
            </a>

            <a href="index.php#rates">
                Rates
            </a>

            <a href="index.php#tournaments">
                Tournaments
            </a>

            <a href="index.php#gallery">
                Gallery
            </a>

            <a href="contact.php">
                Contact
            </a>

        </div>


        <div class="footer-column">

            <h3>
                OPENING HOURS
            </h3>

            <p>
                Monday - Friday<br>
                10:00 AM - 2:00 AM
            </p>

            <p>
                Saturday - Sunday<br>
                9:00 AM - 1:00 AM
            </p>

            <p>
                Open Everyday
            </p>

        </div>


        <div class="footer-column">

            <h3>
                CONTACT US
            </h3>

            <p>
                ☎ 09926749467
            </p>

            <p>
                ✉ info@brgaming.com
            </p>

            <p>
                ⌖ Southbags,<br>
                Bagacay, Dumaguete<br>
                City, Negros Oriental
            </p>

        </div>


    </div>


    <div class="copyright">

        <span>
            ©
        </span>

        2026 BR Bais Rouilo Gaming Cafe.
        All Rights Reserved.

    </div>

</footer>

<?php endif; ?>


<style>
/* Hide footer on the customer Tournaments section. */
#siteFooter.footer-hidden {
    display: none !important;
}
</style>

<script>
(function () {

    function updateFooterVisibility() {

        const footer = document.getElementById("siteFooter");

        if (!footer) {
            return;
        }

        const isTournamentSection =
            window.location.hash.toLowerCase() === "#tournaments";

        footer.classList.toggle(
            "footer-hidden",
            isTournamentSection
        );
    }

    // Run immediately.
    updateFooterVisibility();

    // Run after the page loads.
    document.addEventListener(
        "DOMContentLoaded",
        updateFooterVisibility
    );

    // Handle section navigation.
    window.addEventListener(
        "hashchange",
        updateFooterVisibility
    );

    // Handle browser Back / Forward.
    window.addEventListener(
        "popstate",
        updateFooterVisibility
    );

})();
</script>


<script src="assets/js/main.js"></script>

</body>

</html>