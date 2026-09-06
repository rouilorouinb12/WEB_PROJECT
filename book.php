<?php
declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/* ========================================
   REQUIRE CUSTOMER LOGIN
======================================== */

requireLogin();

/* ========================================
   GET CURRENT CUSTOMER
======================================== */

$userId = currentUserId();

$userStmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone
    FROM users
    WHERE id = ?
    LIMIT 1
");

$userStmt->execute([$userId]);

$user = $userStmt->fetch();

/* ========================================
   SAFETY CHECK
======================================== */

if (!$user) {
    header("Location: logout.php");
    exit;
}

/* ========================================
   INITIAL VALUES
======================================== */

$message = "";
$messageType = "";

$setupId = "";
$bookingDate = "";
$bookingTime = "";
$hours = 1;
$notes = "";

$phone = trim((string)($user["phone"] ?? ""));

/* ========================================
   HANDLE BOOKING
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF CHECK
    ======================================== */

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {

        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        /* ========================================
           GET FORM DATA
        ======================================== */

        $setupId = trim($_POST["setup_id"] ?? "");
        $bookingDate = trim($_POST["booking_date"] ?? "");
        $bookingTime = trim($_POST["booking_time"] ?? "");
        $hours = (int)($_POST["hours"] ?? 1);
        $notes = trim($_POST["notes"] ?? "");
        $phone = trim($_POST["phone"] ?? "");

        /* ========================================
           VALIDATION
        ======================================== */

        if (
            $setupId === "" ||
            $bookingDate === "" ||
            $bookingTime === ""
        ) {

            $message = "Please complete all required booking fields.";
            $messageType = "error";

        } elseif (!in_array((int)$setupId, [1, 2, 3, 4], true)) {

            $message = "Please select a valid gaming station.";
            $messageType = "error";

        } elseif ($hours < 1 || $hours > 12) {

            $message = "Booking duration must be between 1 and 12 hours.";
            $messageType = "error";

        } elseif (strlen($phone) > 30) {

            $message = "Phone number is too long.";
            $messageType = "error";

        } elseif (
            $phone !== "" &&
            !preg_match('/^[0-9+\-\s().]+$/', $phone)
        ) {

            $message = "Please enter a valid phone number.";
            $messageType = "error";

        } elseif (strlen($notes) > 1000) {

            $message = "Notes are too long.";
            $messageType = "error";

        } else {

            /* ========================================
               VALIDATE DATE
            ======================================== */

            $dateObject = DateTime::createFromFormat(
                "Y-m-d",
                $bookingDate
            );

            $validDate =
                $dateObject &&
                $dateObject->format("Y-m-d") === $bookingDate;

            if (!$validDate) {

                $message = "Please select a valid booking date.";
                $messageType = "error";

            } elseif ($bookingDate < date("Y-m-d")) {

                $message = "You cannot book a date in the past.";
                $messageType = "error";

            } else {

                /* ========================================
                   VALIDATE TIME
                ======================================== */

                $timeObject = DateTime::createFromFormat(
                    "H:i",
                    $bookingTime
                );

                $validTime =
                    $timeObject &&
                    $timeObject->format("H:i") === $bookingTime;

                if (!$validTime) {

                    $message = "Please select a valid booking time.";
                    $messageType = "error";

                } else {

                    /* ========================================
                       CHECK IF SETUP EXISTS
                    ======================================== */

                    $setupStmt = $conn->prepare("
                        SELECT
                            id,
                            name
                        FROM gaming_setups
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $setupStmt->execute([
                        (int)$setupId
                    ]);

                    $setup = $setupStmt->fetch();

                    if (!$setup) {

                        $message = "The selected gaming station is not available.";
                        $messageType = "error";

                    } else {

                        /* ========================================
                           CHECK BOOKING CONFLICT
                        ======================================== */

                        /*
                         * Existing bookings table uses:
                         * setup_id
                         * booking_date
                         * start_time
                         * hours
                         *
                         * This checks if the selected setup has
                         * another active booking that overlaps
                         * with the requested time.
                         */

                        $checkStmt = $conn->prepare("
                            SELECT id
                            FROM bookings
                            WHERE setup_id = ?
                            AND booking_date = ?
                            AND status IN ('pending', 'confirmed')
                            AND (
                                start_time < ADDTIME(?, SEC_TO_TIME(? * 3600))
                                AND ADDTIME(start_time, SEC_TO_TIME(hours * 3600)) > ?
                            )
                            LIMIT 1
                        ");

                        $checkStmt->execute([
                            (int)$setupId,
                            $bookingDate,
                            $bookingTime,
                            $hours,
                            $bookingTime
                        ]);

                        if ($checkStmt->fetch()) {

                            $message = "That gaming station is already booked for the selected date and time.";
                            $messageType = "error";

                        } else {

                            /* ========================================
                               UPDATE USER PHONE NUMBER
                            ======================================== */

                            $updatePhoneStmt = $conn->prepare("
                                UPDATE users
                                SET phone = ?
                                WHERE id = ?
                            ");

                            $updatePhoneStmt->execute([
                                $phone,
                                $user["id"]
                            ]);

                            /* ========================================
                               GET CUSTOMER NAME
                            ======================================== */

                            $customerName = trim(
                                (string)($user["name"] ?? "")
                            );

                            /* ========================================
                               SAVE BOOKING
                            ======================================== */

                            $insertStmt = $conn->prepare("
                                INSERT INTO bookings (
                                    customer_name,
                                    email,
                                    phone,
                                    setup_id,
                                    booking_date,
                                    start_time,
                                    hours,
                                    message,
                                    status
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");

                            $insertStmt->execute([
                                $customerName,
                                $user["email"],
                                $phone,
                                (int)$setupId,
                                $bookingDate,
                                $bookingTime,
                                $hours,
                                $notes
                            ]);

                            /* ========================================
                               GET NEW BOOKING ID
                            ======================================== */

                            $bookingId = (int)$conn->lastInsertId();

                            /* ========================================
                               GO TO SUCCESS PAGE
                            ======================================== */

                            header(
                                "Location: success.php?id=" . $bookingId
                            );

                            exit;
                        }
                    }
                }
            }
        }
    }
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
        content="Book your gaming station at Bais Rouilo Gaming Cafe."
    >

    <title>BOOK NOW | Bais Rouilo Gaming Cafe</title>

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
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

        <!-- NAVIGATION -->

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
     BOOKING PAGE
======================================== -->

<main class="inner-page">

    <div class="container form-page">

        <p class="section-kicker">
            CUSTOMER BOOKING
        </p>

        <h1 class="page-title">
            BOOK <span>NOW</span>
        </h1>

        <!-- ========================================
             CUSTOMER INFORMATION
        ======================================== -->

        <div
            class="booking-form"
            style="margin-bottom:25px;"
        >

            <h2
                style="
                    margin:0 0 20px;
                    color:#39FF14;
                    font-family:'Orbitron',sans-serif;
                    font-size:18px;
                "
            >
                CUSTOMER INFORMATION
            </h2>

            <div class="form-row">

                <label>

                    Full Name

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $user["name"],
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
                            $user["email"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>

            </div>

            <div class="form-row">

                <label>

                    Phone Number

                    <input
                        type="text"
                        name="phone"
                        form="bookingForm"
                        value="<?= htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter your phone number"
                        maxlength="30"
                    >

                </label>

                <label>

                    Account

                    <input
                        type="text"
                        value="Logged in"
                        readonly
                    >

                </label>

            </div>

        </div>

        <!-- ========================================
             ERROR / SUCCESS MESSAGE
        ======================================== -->

        <?php if ($message !== ""): ?>

            <div
                class="form-message <?= htmlspecialchars(
                    $messageType,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <!-- ========================================
             BOOKING FORM
        ======================================== -->

        <form
            action="book.php"
            method="POST"
            class="booking-form"
            id="bookingForm"
        >

            <!-- CSRF TOKEN -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    csrfToken(),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <div class="form-row">

                <!-- GAMING STATION -->

                <label>

                    Gaming Station

                    <select
                        name="setup_id"
                        required
                    >

                        <option value="">
                            Select a station
                        </option>

                        <option
                            value="1"
                            <?= ((string)($setupId ?? "") === "1")
                                ? "selected"
                                : "" ?>
                        >
                            PC 01
                        </option>

                        <option
                            value="2"
                            <?= ((string)($setupId ?? "") === "2")
                                ? "selected"
                                : "" ?>
                        >
                            PC 02
                        </option>

                        <option
                            value="3"
                            <?= ((string)($setupId ?? "") === "3")
                                ? "selected"
                                : "" ?>
                        >
                            PC 03
                        </option>

                        <option
                            value="4"
                            <?= ((string)($setupId ?? "") === "4")
                                ? "selected"
                                : "" ?>
                        >
                            PC 04
                        </option>

                    </select>

                </label>

                <!-- DATE -->

                <label>

                    Booking Date

                    <input
                        type="date"
                        name="booking_date"
                        value="<?= htmlspecialchars(
                            $bookingDate ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        min="<?= date("Y-m-d") ?>"
                        required
                    >

                </label>

            </div>

            <div class="form-row">

                <!-- TIME -->

                <label>

                    Booking Time

                    <input
                        type="time"
                        name="booking_time"
                        value="<?= htmlspecialchars(
                            $bookingTime ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </label>

                <!-- HOURS -->

                <label>

                    Duration

                    <select
                        name="hours"
                        required
                    >

                        <?php for ($i = 1; $i <= 12; $i++): ?>

                            <option
                                value="<?= $i ?>"
                                <?= ((int)($hours ?? 1) === $i)
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $i ?>
                                Hour<?= $i > 1 ? "s" : "" ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </label>

            </div>

            <!-- NOTES -->

            <label>

                Notes

                <textarea
                    name="notes"
                    rows="5"
                    maxlength="1000"
                    placeholder="Optional notes or special requests..."
                ><?= htmlspecialchars(
                    $notes ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

            </label>

            <button
                type="submit"
                class="green-button"
                style="
                    margin-top:20px;
                    padding:13px 25px;
                "
            >
                CONFIRM BOOKING
            </button>

        </form>

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