<?php
declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

requireLogin();

$userId = currentUserId();

/* ========================================
   GET CURRENT USER
======================================== */

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

$user = $stmt->fetch();


/* ========================================
   GET CUSTOMER BOOKINGS
======================================== */

$bookings = [];

if ($user) {

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
        ORDER BY b.booking_date DESC, b.start_time DESC
    ");

    $bookingStmt->execute([
        $user["email"]
    ]);

    $bookings = $bookingStmt->fetchAll();
}


/* ========================================
   SAFETY CHECK
======================================== */

if (!$user) {

    header("Location: logout.php");
    exit;

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
        content="Customer profile - Bais Rouilo Gaming Cafe."
    >

    <title>MY PROFILE | Bais Rouilo Gaming Cafe</title>


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


            <!-- PROFILE -->

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
             ACCOUNT INFORMATION
        ======================================== -->

        <div class="booking-form">


            <h2
                style="
                    color:#39FF14;
                    font-family:'Orbitron',sans-serif;
                    font-size:20px;
                    margin:0 0 25px;
                "
            >
                ACCOUNT INFORMATION
            </h2>


            <div class="form-row">


                <label>

                    Full Name

                    <input
                        type="text"
                        value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                        readonly
                    >

                </label>


                <label>

                    Email Address

                    <input
                        type="email"
                        value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                        readonly
                    >

                </label>


            </div>


            <div class="form-row">


                <label>

                    Phone Number

                    <input
                        type="text"
                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                        readonly
                    >

                </label>


                <label>

                    Member Since

                    <input
                        type="text"
                        value="<?= !empty($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : '' ?>"
                        readonly
                    >

                </label>


            </div>


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
                    style="color:#39FF14;"
                >
                    LOGOUT
                </a>

            </div>


        </div>


        <!-- ========================================
             BOOKING HISTORY
        ======================================== -->

        <div style="margin-top:45px;">


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
                                    BOOKING #<?= (int) $booking['id'] ?>
                                </h3>


                                <span
                                    style="
                                        color:#39FF14;
                                        font-size:10px;
                                        font-weight:700;
                                        text-transform:uppercase;
                                    "
                                >
                                    <?= htmlspecialchars($booking['status'] ?? '') ?>
                                </span>


                            </div>


                            <div class="form-row">


                                <label>

                                    Gaming Station

                                    <input
                                        type="text"
                                        value="<?= htmlspecialchars($booking['setup_name'] ?? 'Unknown Setup') ?>"
                                        readonly
                                    >

                                </label>


                                <label>

                                    Date

                                    <input
                                        type="text"
                                        value="<?= !empty($booking['booking_date']) ? date('F d, Y', strtotime($booking['booking_date'])) : '' ?>"
                                        readonly
                                    >

                                </label>


                            </div>


                            <div class="form-row">


                                <label>

                                    Time

                                    <input
                                        type="text"
                                        value="<?= !empty($booking['start_time']) ? date('h:i A', strtotime($booking['start_time'])) : '' ?>"
                                        readonly
                                    >

                                </label>


                                <label>

                                    Duration

                                    <input
                                        type="text"
                                        value="<?= (int) $booking['hours'] ?> hour<?= ((int) $booking['hours'] !== 1 ? 's' : '') ?>"
                                        readonly
                                    >

                                </label>


                            </div>


                            <?php if (!empty($booking['message'])): ?>

                                <label>

                                    Notes

                                    <textarea
                                        readonly
                                        rows="3"
                                    ><?= htmlspecialchars($booking['message']) ?></textarea>

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
     MOBILE MENU
======================================== -->

<script>

const menuToggle = document.querySelector(".menu-toggle");
const mainNav = document.querySelector(".main-nav");

if (menuToggle && mainNav) {

    menuToggle.addEventListener("click", function () {

        mainNav.classList.toggle("open");

        const isOpen =
            mainNav.classList.contains("open");

        menuToggle.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );

    });

}

</script>


</body>

</html>