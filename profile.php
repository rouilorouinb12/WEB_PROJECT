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
        role,
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
   DETERMINE USER ROLE
======================================== */
$userRole = (string)($user["role"] ?? "customer");

$isAdmin = ($userRole === "admin");
$isCustomer = ($userRole === "customer");

/* ========================================
   GET CUSTOMER BOOKINGS
   ONLY FOR CUSTOMER
======================================== */
$bookings = [];

if ($isCustomer) {

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

    $bookingStmt->execute([
        $user["email"]
    ]);

    $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
}

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
        content="User profile - Bais Rouilo Gaming Cafe."
    >

    <title>
        MY PROFILE | Bais Rouilo Gaming Cafe
    </title>

    <!-- GOOGLE FONTS -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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

        <a
            href="index.php"
            class="brand"
        >

            <img
                src="assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
            >

        </a>


        <button
            class="menu-toggle"
            aria-label="Open menu"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>


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


        <!-- ========================================
             PAGE HEADER
        ======================================== -->

        <p class="section-kicker">

            <?php if ($isAdmin): ?>

                ADMIN ACCOUNT

            <?php else: ?>

                CUSTOMER ACCOUNT

            <?php endif; ?>

        </p>


        <h1 class="page-title">

            MY <span>PROFILE</span>

        </h1>



        <!-- ========================================
             CUSTOMER TABS
             ADMIN DOES NOT NEED THESE
        ======================================== -->

        <?php if ($isCustomer): ?>

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
                        color:#39FF14;
                        padding:13px 22px;
                    "
                    data-profile-tab="history"
                >
                    HISTORY
                </a>

            </div>

        <?php endif; ?>



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
                    font-family:'Orbitron',sans-serif;
                    font-size:20px;
                    margin:0 0 25px;
                "
            >

                <?php if ($isAdmin): ?>

                    ADMIN ACCOUNT INFORMATION

                <?php else: ?>

                    ACCOUNT INFORMATION

                <?php endif; ?>

            </h2>



            <!-- ========================================
                 NAME + EMAIL
            ======================================== -->

            <div class="form-row">

                <label>

                    Full Name

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $user["name"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


                <label>

                    Email Address

                    <input
                        type="email"
                        value="<?= htmlspecialchars(
                            $user["email"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>

            </div>



            <!-- ========================================
                 PHONE + MEMBER SINCE
            ======================================== -->

            <div class="form-row">

                <label>

                    Phone Number

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $user["phone"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


                <label>

                    Member Since

                    <input
                        type="text"
                        value="<?= date(
                            "F d, Y",
                            strtotime(
                                $user["created_at"] ?? "now"
                            )
                        ) ?>"
                        readonly
                    >

                </label>

            </div>



            <!-- ========================================
                 ROLE
                 SHOW ADMIN/CUSTOMER ACCOUNT TYPE
            ======================================== -->

            <div class="form-row">

                <label>

                    Account Type

                    <input
                        type="text"
                        value="<?= $isAdmin ? "Administrator" : "Customer" ?>"
                        readonly
                    >

                </label>

            </div>



            <!-- ========================================
                 ADMIN ACTIONS
            ======================================== -->

            <?php if ($isAdmin): ?>

                <div
                    style="
                        display:flex;
                        gap:15px;
                        flex-wrap:wrap;
                        margin-top:5px;
                    "
                >

                    <a
                        href="admin/dashboard.php"
                        class="green-button"
                        style="padding:13px 22px;"
                    >
                        ADMIN DASHBOARD
                    </a>


                    <a
                        href="logout.php"
                        class="outline-button"
                        style="
                            color:#39FF14;
                            padding:13px 22px;
                        "
                    >
                        LOGOUT
                    </a>

                </div>

            <?php endif; ?>



            <!-- ========================================
                 CUSTOMER ACTIONS
            ======================================== -->

            <?php if ($isCustomer): ?>

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
                        href="review.php"
                        class="green-button"
                        style="padding:13px 22px;"
                    >
                        SEND FEEDBACK / REVIEW
                    </a>


                    <a
                        href="logout.php"
                        class="outline-button"
                        style="
                            color:#39FF14;
                            padding:13px 22px;
                        "
                    >
                        LOGOUT
                    </a>

                </div>

            <?php endif; ?>

        </div>



        <!-- ========================================
             CUSTOMER BOOKING HISTORY
             ADMIN WILL NEVER SEE THIS
        ======================================== -->

        <?php if ($isCustomer): ?>

            <div
                id="history"
                data-profile-section="history"
                style="
                    margin-top:45px;
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



                <!-- ========================================
                     NO BOOKINGS
                ======================================== -->

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


                    <!-- ========================================
                         BOOKINGS
                    ======================================== -->

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


                                <!-- ========================================
                                     BOOKING HEADER
                                ======================================== -->

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
                                            font-family:'Orbitron',sans-serif;
                                            font-size:15px;
                                        "
                                    >

                                        BOOKING
                                        #<?= (int)$booking["id"] ?>

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
                                            (string)$booking["status"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </div>



                                <!-- ========================================
                                     STATION + DATE
                                ======================================== -->

                                <div class="form-row">

                                    <label>

                                        Gaming Station

                                        <input
                                            type="text"
                                            value="<?= htmlspecialchars(
                                                (string)($booking["setup_name"] ?? ""),
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
                                            value="<?= date(
                                                "F d, Y",
                                                strtotime(
                                                    (string)$booking["booking_date"]
                                                )
                                            ) ?>"
                                            readonly
                                        >

                                    </label>

                                </div>



                                <!-- ========================================
                                     TIME + DURATION
                                ======================================== -->

                                <div class="form-row">

                                    <label>

                                        Time

                                        <input
                                            type="text"
                                            value="<?= date(
                                                "h:i A",
                                                strtotime(
                                                    (string)$booking["start_time"]
                                                )
                                            ) ?>"
                                            readonly
                                        >

                                    </label>


                                    <label>

                                        Duration

                                        <input
                                            type="text"
                                            value="<?= (int)$booking["hours"] ?>
                                            hour<?= (
                                                (int)$booking["hours"] !== 1
                                                    ? "s"
                                                    : ""
                                            ) ?>"
                                            readonly
                                        >

                                    </label>

                                </div>



                                <!-- ========================================
                                     NOTES
                                ======================================== -->

                                <?php if (!empty($booking["message"])): ?>

                                    <label>

                                        Notes

                                        <textarea
                                            readonly
                                            rows="3"
                                            style="resize:none;"
                                        ><?= htmlspecialchars(
                                            (string)$booking["message"],
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

        <?php endif; ?>


    </div>

</main>



<!-- ========================================
     CUSTOMER PROFILE TAB SCRIPT
======================================== -->

<?php if ($isCustomer): ?>

<script>

const profileTabs =
    document.querySelectorAll(
        "[data-profile-tab]"
    );

const profileSections =
    document.querySelectorAll(
        "[data-profile-section]"
    );


function showProfileSection(sectionName) {

    profileSections.forEach(
        function(section) {

            section.style.display =
                section.dataset.profileSection === sectionName
                    ? "block"
                    : "none";

        }
    );


    profileTabs.forEach(
        function(tab) {

            if (
                tab.dataset.profileTab ===
                sectionName
            ) {

                tab.classList.add(
                    "green-button"
                );

                tab.classList.remove(
                    "outline-button"
                );

                tab.style.color = "";

                tab.style.padding =
                    "13px 22px";

            } else {

                tab.classList.add(
                    "outline-button"
                );

                tab.classList.remove(
                    "green-button"
                );

                tab.style.color =
                    "#39FF14";

                tab.style.padding =
                    "13px 22px";

            }

        }
    );

}


profileTabs.forEach(
    function(tab) {

        tab.addEventListener(
            "click",
            function(event) {

                event.preventDefault();

                const sectionName =
                    tab.dataset.profileTab;

                showProfileSection(
                    sectionName
                );

                history.replaceState(
                    null,
                    "",
                    "#" + sectionName
                );

            }
        );

    }
);


const initialSection =
    window.location.hash === "#history"
        ? "history"
        : "account";


showProfileSection(
    initialSection
);

</script>

<?php endif; ?>



<!-- ========================================
     MOBILE MENU
======================================== -->

<script>

const menuToggle =
    document.querySelector(
        ".menu-toggle"
    );

const mainNav =
    document.querySelector(
        ".main-nav"
    );


if (menuToggle && mainNav) {

    menuToggle.addEventListener(
        "click",
        function() {

            mainNav.classList.toggle(
                "open"
            );

            const isOpen =
                mainNav.classList.contains(
                    "open"
                );

            menuToggle.setAttribute(
                "aria-expanded",
                isOpen
                    ? "true"
                    : "false"
            );

        }
    );

}

</script>


</body>

</html>