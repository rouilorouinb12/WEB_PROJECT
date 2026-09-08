<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/* ========================================
   GET APPROVED CUSTOMER REVIEWS
======================================== */

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.rating,
        r.review,
        r.created_at,
        u.name,
        u.email
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.user_id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
");

$stmt->execute();

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Customer reviews - Bais Rouilo Gaming Cafe."
    >

    <title>
        CUSTOMER REVIEWS | Bais Rouilo Gaming Cafe
    </title>

    <!-- GOOGLE FONTS -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Orbitron:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <!-- MAIN CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ========================================
           CUSTOMER REVIEWS PAGE
        ======================================== */

        .customer-reviews-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }

        .reviews-list {
            display: grid;
            gap: 20px;
        }

        .customer-review-card {
            border: 1px solid rgba(57,255,20,.35);
            background: rgba(0,0,0,.65);
            padding: 22px 24px;
            border-radius: 8px;
            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .customer-review-card:hover {
            transform: translateY(-3px);
            box-shadow:
                0 0 15px rgba(57,255,20,.18);
        }

        .customer-review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .customer-review-name {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 15px;
            font-weight: 800;
        }

        .customer-review-email {
            margin-top: 5px;
            color: rgba(255,255,255,.5);
            font-size: 10px;
        }

        .customer-review-stars {
            color: #39FF14;
            font-size: 18px;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .customer-review-content {
            padding: 14px 0;
            border-top: 1px solid rgba(57,255,20,.12);
            border-bottom: 1px solid rgba(57,255,20,.12);
        }

        .customer-review-label {
            margin-bottom: 7px;
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .customer-review-text {
            color: rgba(255,255,255,.9);
            font-size: 12px;
            line-height: 1.6;
            word-break: break-word;
        }

        .customer-review-date {
            margin-top: 11px;
            color: rgba(255,255,255,.45);
            font-size: 10px;
        }

        .no-customer-reviews {
            border: 1px solid rgba(57,255,20,.25);
            padding: 40px 20px;
            color: rgba(255,255,255,.6);
            text-align: center;
        }

        .customer-review-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        @media (max-width: 600px) {

            .customer-review-card {
                padding: 18px;
            }

            .customer-review-header {
                flex-direction: column;
            }

            .customer-review-actions {
                flex-direction: column;
            }

            .customer-review-actions a {
                width: 100%;
                text-align: center;
            }

        }

    </style>

</head>

<body>

<!-- ========================================
     HEADER
======================================== -->

<header class="site-header">

    <div class="container nav-container">

        <!-- LOGO -->

        <a
            href="index.php"
            class="brand"
        >

            <img
                src="assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
            >

        </a>

        <!-- MOBILE MENU -->

        <button
            class="menu-toggle"
            aria-label="Open menu"
            aria-expanded="false"
            type="button"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

        <!-- MAIN NAVIGATION -->

        <nav
            class="main-nav"
            id="mainNav"
        >

            <a href="index.php#home">
                HOME
            </a>

            <a href="index.php#pcs">
                PCS
            </a>

            <a href="index.php#rates">
                RATES
            </a>

            <a href="index.php#tournaments">
                TOURNAMENTS
            </a>

            <a href="index.php#gallery">
                GALLERY
            </a>

            <a href="contact.php">
                CONTACT
            </a>

            <?php if (isLoggedIn()): ?>

                <a
                    href="profile.php"
                    class="nav-button"
                >
                    PROFILE
                </a>

            <?php else: ?>

                <a
                    href="login.php"
                    class="nav-button"
                >
                    BOOK NOW
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>

<!-- ========================================
     CUSTOMER REVIEWS
======================================== -->

<main class="inner-page">

    <div class="container form-page">

        <div class="customer-reviews-wrapper">

            <!-- PAGE HEADER -->

            <p class="section-kicker">
                CUSTOMER FEEDBACK
            </p>

            <h1 class="page-title">
                CUSTOMER <span>REVIEWS</span>
            </h1>

            <!-- ========================================
                 REVIEW LIST
            ======================================== -->

            <?php if (!$reviews): ?>

                <div class="no-customer-reviews">

                    No approved customer reviews yet.

                </div>

            <?php else: ?>

                <div class="reviews-list">

                    <?php foreach ($reviews as $item): ?>

                        <?php

                        $rating = max(
                            1,
                            min(
                                5,
                                (int)$item["rating"]
                            )
                        );

                        ?>

                        <article
                            class="customer-review-card"
                        >

                            <!-- REVIEW HEADER -->

                            <div
                                class="customer-review-header"
                            >

                                <div>

                                    <div
                                        class="customer-review-name"
                                    >

                                        <?= htmlspecialchars(
                                            (string)$item["name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                    <div
                                        class="customer-review-email"
                                    >

                                        <?= htmlspecialchars(
                                            (string)$item["email"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                </div>

                                <!-- STARS -->

                                <div
                                    class="customer-review-stars"
                                    aria-label="<?= $rating ?> out of 5 stars"
                                >

                                    <?= str_repeat(
                                        "★",
                                        $rating
                                    ) ?>

                                    <?= str_repeat(
                                        "☆",
                                        5 - $rating
                                    ) ?>

                                </div>

                            </div>

                            <!-- ========================================
                                 REVIEW CONTENT
                            ======================================== -->

                            <div
                                class="customer-review-content"
                            >

                                <div
                                    class="customer-review-label"
                                >
                                    CUSTOMER REVIEW
                                </div>

                                <div
                                    class="customer-review-text"
                                >

                                    <?= htmlspecialchars(
                                        (string)$item["review"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>

                            <!-- DATE -->

                            <div
                                class="customer-review-date"
                            >

                                Submitted:

                                <?= htmlspecialchars(
                                    date(
                                        "F d, Y h:i A",
                                        strtotime(
                                            (string)$item["created_at"]
                                        )
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <!-- ========================================
                 ACTION BUTTONS
            ======================================== -->

            <div
                class="customer-review-actions"
            >

                <?php if (isLoggedIn()): ?>

                    <?php if (
                        ($_SESSION["user_role"] ?? "customer")
                        === "customer"
                    ): ?>

                        <a
                            href="review.php"
                            class="green-button"
                            style="padding:13px 22px;"
                        >
                            SEND REVIEW
                        </a>

                    <?php endif; ?>

                    <a
                        href="profile.php"
                        class="outline-button"
                        style="
                            color:#39FF14;
                            padding:13px 22px;
                        "
                    >
                        BACK TO PROFILE
                    </a>

                <?php else: ?>

                    <a
                        href="login.php"
                        class="green-button"
                        style="padding:13px 22px;"
                    >
                        LOGIN TO REVIEW
                    </a>

                    <a
                        href="index.php"
                        class="outline-button"
                        style="
                            color:#39FF14;
                            padding:13px 22px;
                        "
                    >
                        BACK TO HOME
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</main>

<!-- ========================================
     MOBILE MENU
======================================== -->

<script>

const menuToggle =
    document.querySelector(".menu-toggle");

const mainNav =
    document.querySelector(".main-nav");

if (menuToggle && mainNav) {

    menuToggle.addEventListener(
        "click",
        function () {

            mainNav.classList.toggle("open");

            const isOpen =
                mainNav.classList.contains("open");

            menuToggle.setAttribute(
                "aria-expanded",
                isOpen ? "true" : "false"
            );

        }
    );

}

</script>

</body>

</html>