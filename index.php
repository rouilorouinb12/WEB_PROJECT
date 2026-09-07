<?php
require_once "database.php";
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

include "includes/header.php";


/* ========================================
   GAMING SETUPS
======================================== */

$setups = [

    [
        "title" => "RTX GAMING PC",
        "image" => "setup-1.png",
        "specs" => [
            "RTX 4060",
            "Ryzen 5 5600",
            "16GB RAM",
            "24\" 165Hz Monitor"
        ],
        "price" => "45 PHP / HOUR"
    ],

    [
        "title" => "PREMIUM GAMING PC",
        "image" => "setup-2.png",
        "specs" => [
            "RTX 4070",
            "Ryzen 7 5700X",
            "16GB RAM",
            "24\" 240Hz Monitor"
        ],
        "price" => "55 PHP / HOUR"
    ],

    [
        "title" => "STREAMER SETUP",
        "image" => "setup-3.png",
        "specs" => [
            "RTX 3060",
            "Ryzen 5 5600G",
            "16GB RAM",
            "Dual Monitor",
            "Webcam + Mic"
        ],
        "price" => "60 PHP / HOUR"
    ],

    [
        "title" => "VIP ROOM",
        "image" => "setup-4.png",
        "specs" => [
            "Private Room",
            "High-End PC",
            "55\" 4K TV",
            "Recliner Seat",
            "Perfect for Groups"
        ],
        "price" => "120 PHP / HOUR"
    ]

];


/* ========================================
   RATES
======================================== */

$rates = [

    [
        "time" => "1 HOUR",
        "price" => "35 PHP",
        "label" => "PER HOUR"
    ],

    [
        "time" => "3 HOURS",
        "price" => "90 PHP",
        "label" => "PER SESSION"
    ],

    [
        "time" => "5 HOURS",
        "price" => "140 PHP",
        "label" => "PER SESSION"
    ],

    [
        "time" => "WHOLE DAY",
        "price" => "250 PHP",
        "label" => "ALL DAY PASS"
    ]

];


/* ========================================
   REVIEWS
======================================== */

$testimonials = [

    [
        "photo" => "review-1.jpg",
        "quote" => "Best gaming cafe in town!",
        "text" => "Great PCs, affordable rates, and a chill environment.",
        "name" => "Mark V."
    ],

    [
        "photo" => "review-2.jpg",
        "quote" => "Smooth gaming, zero lag!",
        "text" => "The internet speed here is really next level.",
        "name" => "Kyle C."
    ],

    [
        "photo" => "review-3.jpg",
        "quote" => "My go-to place to play and relax.",
        "text" => "Staff are friendly and the place is super nice!",
        "name" => "John D."
    ]

];

?>


<main>


    <!-- =====================================
         HOME PAGE CONTENT
    ====================================== -->

    <div id="homePageContent">


        <!-- HERO -->

        <section
            class="hero"
            id="home"
        >

            <div class="container hero-grid">


                <div class="hero-copy reveal">

                    <p class="eyebrow">
                        PLAY. COMPETE. WIN
                    </p>


                    <h1>

                        LEVEL UP YOUR<br>

                        <span>
                            GAMING
                        </span><br>

                        EXPERIENCE

                    </h1>


                    <p class="hero-description">

                        High-performance gaming PCs,
                        ultra-fast internet,
                        comfortable gaming stations,
                        and an exciting community,
                        all in one place.

                    </p>


                    <div class="hero-buttons">


                        <a
                            href="book.php"
                            class="outline-button"
                        >
                            BOOK A PC
                        </a>


                        <a
                            href="#rates"
                            class="outline-button nav-home-section"
                        >

                            VIEW RATES

                            <span>
                                →
                            </span>

                        </a>


                    </div>


                </div>



                <div class="hero-photo reveal">

                    <img
                        src="assets/images/gaming-cafe.png"
                        alt="Bais Rouilo Gaming Cafe"
                    >

                </div>


            </div>



            <!-- STATS -->

            <div class="container stats-grid">


                <div class="stat-card">

                    <div class="stat-icon">

                        <svg viewBox="0 0 64 64">

                            <rect
                                x="10"
                                y="8"
                                width="44"
                                height="32"
                                rx="2"
                            ></rect>

                            <path d="M32 40v10"></path>

                            <path d="M20 54h24"></path>

                        </svg>

                    </div>


                    <div>

                        <strong>
                            15+
                        </strong>

                        <small>
                            GAMING PCS
                        </small>

                    </div>

                </div>



                <div class="stat-card">

                    <div class="stat-icon">

                        <svg viewBox="0 0 64 64">

                            <path
                                d="M36 3L15 35h17l-4 26 22-34H33z"
                            ></path>

                        </svg>

                    </div>


                    <div>

                        <strong>
                            1 Gbps
                        </strong>

                        <small>
                            INTERNET SPEED
                        </small>

                    </div>

                </div>



                <div class="stat-card">

                    <div class="stat-icon">

                        <svg viewBox="0 0 64 64">

                            <circle
                                cx="32"
                                cy="20"
                                r="13"
                            ></circle>

                            <path
                                d="M8 57c2-14 11-22 24-22s22 8 24 22"
                            ></path>

                        </svg>

                    </div>


                    <div>

                        <strong>
                            200+
                        </strong>

                        <small>
                            HAPPY GAMERS
                        </small>

                    </div>

                </div>



                <div class="stat-card">

                    <div class="stat-icon">

                        <svg viewBox="0 0 64 64">

                            <path d="M20 6h24"></path>

                            <path d="M22 6v8"></path>

                            <path d="M42 6v8"></path>

                            <path d="M18 14h28"></path>

                            <path d="M22 14c0 15 3 22 10 26"></path>

                            <path d="M42 14c0 15-3 22-10 26"></path>

                            <path d="M24 40h16"></path>

                            <path d="M32 40v10"></path>

                            <path d="M22 55h20"></path>

                        </svg>

                    </div>


                    <div>

                        <strong>
                            30+
                        </strong>

                        <small>
                            GAMING EVENTS
                        </small>

                    </div>

                </div>


            </div>


        </section>



        <!-- =====================================
             PCS
        ====================================== -->

        <section
            class="combined-section"
            id="pcs"
        >

            <div class="container setups-container">


                <p class="section-kicker setups-kicker">
                    OUR GAMING SETUPS
                </p>


                <div class="setup-grid">


                    <?php foreach ($setups as $setup): ?>


                        <article class="setup-card reveal">


                            <div class="setup-image">

                                <img
                                    src="assets/images/<?= htmlspecialchars($setup["image"]) ?>"
                                    alt="<?= htmlspecialchars($setup["title"]) ?>"
                                >

                            </div>


                            <div class="setup-content">


                                <h3>
                                    <?= htmlspecialchars($setup["title"]) ?>
                                </h3>


                                <?php foreach ($setup["specs"] as $spec): ?>


                                    <p>
                                        - <?= htmlspecialchars($spec) ?>
                                    </p>


                                <?php endforeach; ?>


                                <strong>
                                    <?= htmlspecialchars($setup["price"]) ?>
                                </strong>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            </div>

        </section>



        <!-- =====================================
             RATES
        ====================================== -->

        <section
            class="section rates-section"
            id="rates"
        >

            <div class="container">


                <p class="section-kicker">
                    OUR RATES
                </p>


                <div class="rates-grid">


                    <?php foreach ($rates as $rate): ?>


                        <article class="rate-card reveal">


                            <h3>
                                <?= htmlspecialchars($rate["time"]) ?>
                            </h3>


                            <strong>
                                <?= htmlspecialchars($rate["price"]) ?>
                            </strong>


                            <span>
                                <?= htmlspecialchars($rate["label"]) ?>
                            </span>


                        </article>


                    <?php endforeach; ?>


                </div>


            </div>

        </section>



        <!-- =====================================
             GALLERY
        ====================================== -->

        <section
            class="section gallery-section"
            id="gallery"
        >

            <div class="container">


                <p class="section-kicker">
                    GALLERY
                </p>


                <h2 class="section-heading">

                    

                    <span>
                        
                    </span>

                </h2>


                <div class="gallery-grid">


                    <?php for ($i = 1; $i <= 4; $i++): ?>


                        <button
                            type="button"
                            class="gallery-item reveal"
                            data-image="assets/images/gallery-<?= $i ?>.png"
                            aria-label="View gaming cafe gallery <?= $i ?>"
                        >


                            <img
                                src="assets/images/gallery-<?= $i ?>.png"
                                alt="Gaming cafe gallery <?= $i ?>"
                            >


                        </button>


                    <?php endfor; ?>


                </div>


            </div>

        </section>



        <!-- =====================================
             REVIEWS
        ====================================== -->

        <section class="section reviews-section">

            <div class="container">


                <p class="section-kicker">
                    WHAT GAMERS SAY
                </p>


                <div class="reviews-grid">


                    <?php foreach ($testimonials as $review): ?>


                        <article class="review-card reveal">


                            <div class="review-stars">
                                ★★★★★
                            </div>


                            <div class="quote">
                                “
                            </div>


                            <h3>
                                "<?= htmlspecialchars($review["quote"]) ?>"
                            </h3>


                            <p>
                                <?= htmlspecialchars($review["text"]) ?>
                            </p>


                            <div class="reviewer">


                                <img
                                    src="assets/images/<?= htmlspecialchars($review["photo"]) ?>"
                                    alt="<?= htmlspecialchars($review["name"]) ?>"
                                >


                                <span>
                                    — <?= htmlspecialchars($review["name"]) ?>
                                </span>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            </div>

        </section>



        <!-- =====================================
             CTA
        ====================================== -->

        <section class="cta-section">

            <div class="container">


                <div class="cta-box">


                    <div class="cta-content">


                        <div class="cta-logo">

                            <img
                                src="assets/images/icon-logo.png"
                                alt="BR Icon"
                            >

                        </div>


                        <div class="cta-text">


                            <h2>

                                READY TO

                                <span>
                                    PLAY?
                                </span>

                            </h2>


                            <p>

                                Book your gaming station now<br>

                                and dominate the game

                            </p>


                        </div>


                    </div>



                    <a
                        href="book.php"
                        class="green-button cta-button"
                    >
                        BOOK NOW
                    </a>


                </div>


            </div>

        </section>


    </div>
    <!-- END HOME PAGE CONTENT -->



    <!-- =====================================
         TOURNAMENT PAGE
         HIDDEN INITIALLY
    ====================================== -->

    <section
        class="tournaments-section"
        id="tournaments"
        style="display: none;"
    >


        <div class="container">


            <p class="section-kicker">
                TOURNAMENTS
            </p>


            <h1 class="page-title">

                COMPETE.

                <span>
                    WIN.
                </span>

            </h1>



            <div class="tournament-grid">


                <!-- TOURNAMENT 1 -->

                <article class="tournament-card reveal">


                    <div class="tournament-date">

                        <span>
                            15
                        </span>

                        <small>
                            AUG
                        </small>

                    </div>



                    <div class="tournament-info">


                        <h3>
                            WEEKLY GAMING TOURNAMENT
                        </h3>


                        <p>
                            Join our exciting weekly tournament
                            and compete with other gamers.
                        </p>


                        <span>
                            PRIZES AVAILABLE
                        </span>


                    </div>



                    <a
                        href="book.php"
                        class="outline-button"
                    >
                        JOIN NOW
                    </a>


                </article>



                <!-- TOURNAMENT 2 -->

                <article class="tournament-card reveal">


                    <div class="tournament-date">

                        <span>
                            22
                        </span>

                        <small>
                            AUG
                        </small>

                    </div>



                    <div class="tournament-info">


                        <h3>
                            VALORANT TOURNAMENT
                        </h3>


                        <p>
                            Form your team and battle
                            against the best players.
                        </p>


                        <span>
                            TEAM REGISTRATION OPEN
                        </span>


                    </div>



                    <a
                        href="book.php"
                        class="outline-button"
                    >
                        JOIN NOW
                    </a>


                </article>



                <!-- TOURNAMENT 3 -->

                <article class="tournament-card reveal">


                    <div class="tournament-date">

                        <span>
                            29
                        </span>

                        <small>
                            AUG
                        </small>

                    </div>



                    <div class="tournament-info">


                        <h3>
                            MOBILE LEGENDS TOURNAMENT
                        </h3>


                        <p>
                            Gather your squad and compete
                            for exciting prizes.
                        </p>


                        <span>
                            CASH PRIZES AVAILABLE
                        </span>


                    </div>



                    <a
                        href="book.php"
                        class="outline-button"
                    >
                        JOIN NOW
                    </a>


                </article>


            </div>


        </div>


    </section>


</main>



<!-- =====================================
     NAVIGATION JAVASCRIPT
===================================== -->

<script>

document.addEventListener("DOMContentLoaded", function () {


    const homeContent = document.getElementById("homePageContent");

    const tournamentContent = document.getElementById("tournaments");

    const mainNav = document.getElementById("mainNav");

    const menuToggle = document.querySelector(".menu-toggle");


    /*
    ========================================
    SHOW HOME
    ========================================
    */

    function showHome() {

        homeContent.style.display = "block";

        tournamentContent.style.display = "none";

    }


    /*
    ========================================
    SHOW TOURNAMENTS
    ========================================
    */

    function showTournaments() {

        homeContent.style.display = "none";

        tournamentContent.style.display = "block";

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    }


    /*
    ========================================
    CHECK URL HASH ON PAGE LOAD
    ========================================
    */

    if (window.location.hash === "#tournaments") {

        showTournaments();

    }


    /*
    ========================================
    TOURNAMENT BUTTON
    ========================================
    */

    const tournamentLinks = document.querySelectorAll(
        'a[href="index.php#tournaments"], a[href="#tournaments"]'
    );


    tournamentLinks.forEach(function (link) {

        link.addEventListener("click", function (event) {

            event.preventDefault();

            showTournaments();

            history.pushState(
                null,
                "",
                "index.php#tournaments"
            );


            if (mainNav) {

                mainNav.classList.remove("open");

            }


            if (menuToggle) {

                menuToggle.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        });

    });



    /*
    ========================================
    HOME BUTTON
    ========================================
    */

    const homeLinks = document.querySelectorAll(
        'a[href="index.php#home"], a[href="#home"]'
    );


    homeLinks.forEach(function (link) {

        link.addEventListener("click", function (event) {

            event.preventDefault();

            showHome();

            history.pushState(
                null,
                "",
                "index.php#home"
            );


            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });


            if (mainNav) {

                mainNav.classList.remove("open");

            }


            if (menuToggle) {

                menuToggle.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        });

    });



    /*
    ========================================
    PCS / RATES / GALLERY
    ========================================
    */

    const homeSectionLinks = document.querySelectorAll(
        ".nav-home-section"
    );


    homeSectionLinks.forEach(function (link) {

        link.addEventListener("click", function (event) {

            const href = link.getAttribute("href");

            if (!href) {
                return;
            }


            let targetId = "";


            if (href.includes("#")) {

                targetId = href.split("#")[1];

            }


            if (!targetId) {
                return;
            }


            event.preventDefault();


            showHome();


            history.pushState(
                null,
                "",
                "index.php#" + targetId
            );


            setTimeout(function () {

                const target = document.getElementById(targetId);


                if (target) {

                    target.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });

                }

            }, 50);


            if (mainNav) {

                mainNav.classList.remove("open");

            }


            if (menuToggle) {

                menuToggle.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        });

    });



    /*
    ========================================
    BROWSER BACK / FORWARD
    ========================================
    */

    window.addEventListener("popstate", function () {

        if (window.location.hash === "#tournaments") {

            showTournaments();

        } else {

            showHome();

        }

    });



    /*
    ========================================
    MOBILE MENU
    ========================================
    */

    if (menuToggle && mainNav) {

        menuToggle.addEventListener(
            "click",
            function () {

                mainNav.classList.toggle("open");


                const isOpen =
                    mainNav.classList.contains("open");


                menuToggle.setAttribute(
                    "aria-expanded",
                    isOpen
                );

            }
        );

    }


});

</script>



<?php include "includes/footer.php"; ?>