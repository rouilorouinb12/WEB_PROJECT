<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/* ========================================
   REQUIRE LOGIN
======================================== */

requireLogin();


/* ========================================
   GET CURRENT USER
======================================== */

$userId = currentUserId();

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


/* ========================================
   SAFETY CHECK
======================================== */

if (!$user) {

    header("Location: logout.php");
    exit;
}


/* ========================================
   GET CUSTOMER BOOKINGS
======================================== */

$bookingStmt = $conn->prepare("
    SELECT
        b.id,
        b.customer_name,
        b.email,
        b.phone,
        b.setup_id,
        b.booking_date,
        b.start_time,
        b.hours,
        b.message,
        b.status,
        b.created_at,
        gs.name AS setup_name

    FROM bookings b

    LEFT JOIN gaming_setups gs
        ON gs.id = b.setup_id

    WHERE b.email = ?

    ORDER BY
        b.booking_date DESC,
        b.start_time DESC
");

$bookingStmt->execute([$user["email"]]);

$bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

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
        content="Customer profile - Bais Rouilo Gaming Cafe."
    >

    <title>MY PROFILE | Bais Rouilo Gaming Cafe</title>


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

            <a
                href="profile.php"
                class="nav-button"
            >
                PROFILE
            </a>

        </nav>

    </div>

</header>



<!-- ========================================
     PROFILE
======================================== -->

<main class="inner-page">

    <div class="container form-page">


        <p class="section-kicker">
            CUSTOMER ACCOUNT
        </p>


        <h1 class="page-title">
            MY <span>PROFILE</span>
        </h1>



        <!-- ========================================
             PROFILE TABS
        ======================================== -->

        <div
            style="
                display:flex;
                justify-content:center;
                gap:15px;
                flex-wrap:wrap;
                margin-bottom:35px;
            "
        >

            <a
                href="#account"
                class="green-button"
                style="padding:13px 22px;"
                data-profile-tab="account"
            >
                ACCOUNT
            </a>


            <a
                href="#history"
                class="outline-button"
                style="
                    padding:13px 22px;
                    color:#39FF14;
                "
                data-profile-tab="history"
            >
                HISTORY
            </a>

        </div>



        <!-- ========================================
             ACCOUNT INFORMATION
        ======================================== -->

        <div
            class="booking-form"
            id="account"
            data-profile-section="account"
        >


            <h2
                style="
                    color:#39FF14;
                    font-family:'Orbitron', sans-serif;
                    font-size:20px;
                    margin:0 0 25px;
                "
            >
                ACCOUNT INFORMATION
            </h2>



            <div class="form-row">


                <!-- FULL NAME -->

                <label>

                    Full Name

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($user["name"] ?? ""),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>



                <!-- EMAIL -->

                <label>

                    Email Address

                    <input
                        type="email"
                        value="<?= htmlspecialchars(
                            (string) ($user["email"] ?? ""),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


            </div>



            <div class="form-row">


                <!-- PHONE -->

                <label>

                    Phone Number

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($user["phone"] ?? ""),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>



                <!-- MEMBER SINCE -->

                <label>

                    Member Since

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            date(
                                "F d, Y",
                                strtotime(
                                    (string) (
                                        $user["created_at"] ?? "now"
                                    )
                                )
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


            </div>



            <!-- ACCOUNT BUTTONS -->

            <div
                style="
                    display:flex;
                    gap:15px;
                    flex-wrap:wrap;
                    margin-top:5px;
                "
            >


                <a
                    href="book.php"
                    class="green-button"
                    style="padding:13px 22px;"
                >
                    BOOK NOW
                </a>



                <a
                    href="logout.php"
                    class="outline-button"
                    style="
                        padding:13px 22px;
                        color:#39FF14;
                    "
                >
                    LOGOUT
                </a>


            </div>


        </div>



        <!-- ========================================
             BOOKING HISTORY
        ======================================== -->

        <div
            id="history"
            data-profile-section="history"
            style="
                display:none;
            "
        >


            <p class="section-kicker">
                BOOKING HISTORY
            </p>


            <h2
                class="section-heading"
                style="margin-bottom:25px;"
            >
                MY <span>BOOKINGS</span>
            </h2>



            <?php if (count($bookings) === 0): ?>


                <div class="form-message">

                    You don't have any bookings yet.

                </div>


                <div
                    style="
                        text-align:center;
                        margin-top:20px;
                    "
                >

                    <a
                        href="book.php"
                        class="green-button"
                        style="padding:13px 22px;"
                    >
                        MAKE A BOOKING
                    </a>

                </div>



            <?php else: ?>


                <div
                    style="
                        display:grid;
                        gap:18px;
                    "
                >


                    <?php foreach ($bookings as $booking): ?>


                        <div
                            class="booking-form"
                            style="padding:22px;"
                        >


                            <!-- BOOKING HEADER -->

                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    gap:15px;
                                    flex-wrap:wrap;
                                    margin-bottom:15px;
                                "
                            >


                                <h3
                                    style="
                                        margin:0;
                                        color:#39FF14;
                                        font-family:'Orbitron', sans-serif;
                                        font-size:15px;
                                    "
                                >

                                    BOOKING #<?= (int) $booking["id"] ?>

                                </h3>



                                <span
                                    style="
                                        color:#39FF14;
                                        font-size:10px;
                                        font-weight:700;
                                        text-transform:uppercase;
                                    "
                                >

                                    <?= htmlspecialchars(
                                        (string) (
                                            $booking["status"] ?? ""
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>


                            </div>



                            <!-- ROW 1 -->

                            <div class="form-row">


                                <label>

                                    Gaming Station

                                    <input
                                        type="text"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $booking["setup_name"]
                                                ?? "Not Available"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        readonly
                                    >

                                </label>



                                <label>

                                    Date

                                    <input
                                        type="text"
                                        value="<?= htmlspecialchars(
                                            date(
                                                "F d, Y",
                                                strtotime(
                                                    (string) (
                                                        $booking["booking_date"]
                                                        ?? "now"
                                                    )
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        readonly
                                    >

                                </label>


                            </div>



                            <!-- ROW 2 -->

                            <div class="form-row">


                                <label>

                                    Time

                                    <input
                                        type="text"
                                        value="<?= htmlspecialchars(
                                            date(
                                                "h:i A",
                                                strtotime(
                                                    (string) (
                                                        $booking["start_time"]
                                                        ?? "00:00:00"
                                                    )
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        readonly
                                    >

                                </label>



                                <label>

                                    Duration

                                    <input
                                        type="text"
                                        value="<?= (int) (
                                            $booking["hours"] ?? 0
                                        ) ?> hour<?= (
                                            (int) (
                                                $booking["hours"] ?? 0
                                            ) !== 1
                                            ? "s"
                                            : ""
                                        ) ?>"
                                        readonly
                                    >

                                </label>


                            </div>



                            <!-- NOTES -->

                            <?php if (
                                !empty($booking["message"])
                            ): ?>


                                <label>

                                    Notes

                                    <textarea
                                        readonly
                                        rows="3"
                                    ><?= htmlspecialchars(
                                        (string) $booking["message"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?></textarea>

                                </label>


                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


    </div>

</main>



<!-- ========================================
     PROFILE TAB SCRIPT
======================================== -->

<script>

const profileTabs =
    document.querySelectorAll("[data-profile-tab]");

const profileSections =
    document.querySelectorAll("[data-profile-section]");


function showProfileSection(sectionName) {

    profileSections.forEach(function (section) {

        section.style.display =
            section.dataset.profileSection === sectionName
                ? "block"
                : "none";

    });


    profileTabs.forEach(function (tab) {

        if (
            tab.dataset.profileTab === sectionName
        ) {

            /* ACTIVE BUTTON */

            tab.classList.add("green-button");
            tab.classList.remove("outline-button");

            tab.style.color = "";
            tab.style.padding = "13px 22px";

        } else {

            /* INACTIVE BUTTON */

            tab.classList.add("outline-button");
            tab.classList.remove("green-button");

            tab.style.color = "#39FF14";
            tab.style.padding = "13px 22px";

        }

    });

}


profileTabs.forEach(function (tab) {

    tab.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            const sectionName =
                tab.dataset.profileTab;

            showProfileSection(sectionName);

            history.replaceState(
                null,
                "",
                "#" + sectionName
            );

        }
    );

});


/* ========================================
   CHECK INITIAL HASH
======================================== */

const initialSection =
    window.location.hash === "#history"
        ? "history"
        : "account";


showProfileSection(initialSection);

</script>



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