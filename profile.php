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

$userId =
    currentUserId();


$stmt =
    $conn->prepare("
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


$stmt->execute([
    $userId
]);


$user =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/* ========================================
   SAFETY CHECK
======================================== */

if (!$user) {

    header(
        "Location: logout.php"
    );

    exit;

}


/* ========================================
   DETERMINE USER ROLE
======================================== */

$userRole =
    (string)(
        $user["role"]
        ?? "customer"
    );


$isAdmin =
    ($userRole === "admin");


$isCustomer =
    ($userRole === "customer");


/* ========================================
   GET CUSTOMER BOOKINGS
   ONLY FOR CUSTOMER
======================================== */

$bookings = [];


if ($isCustomer) {

    $bookingStmt =
        $conn->prepare("
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
                b.payment_method,
                b.payment_reference,
                b.payment_status,
                b.created_at,
                gs.name AS setup_name,
                gs.price_per_hour
            FROM bookings b
            LEFT JOIN gaming_setups gs
                ON gs.id = b.setup_id
            WHERE LOWER(TRIM(b.email)) =
                  LOWER(TRIM(?))
            ORDER BY
                b.booking_date DESC,
                b.start_time DESC
        ");


    $bookingStmt->execute([
        $user["email"]
    ]);


    $bookings =
        $bookingStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}


/* ========================================
   GET CUSTOMER NOTIFICATIONS
   ONLY FOR CUSTOMER
======================================== */

$notifications = [];

$unreadCount = 0;


if ($isCustomer) {

    $notificationStmt =
        $conn->prepare("
            SELECT
                id,
                type,
                title,
                message,
                is_read,
                created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY
                is_read ASC,
                created_at DESC
        ");


    $notificationStmt->execute([
        $userId
    ]);


    $notifications =
        $notificationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $notifications
        as $notification
    ) {

        if (
            (int)$notification["is_read"] === 0
        ) {

            $unreadCount++;

        }

    }

}


/* ========================================
   GET ACCEPTED TOURNAMENT REGISTRATIONS
   FOR TOURNAMENT RECEIPT LINKS
======================================== */

$acceptedTournamentRegistrations = [];


if ($isCustomer) {

    $tournamentRegistrationStmt =
        $conn->prepare("
            SELECT
                tr.id,
                tr.tournament_id,
                tr.status,
                tr.created_at,
                t.title AS tournament_title
            FROM tournament_registrations tr
            INNER JOIN tournaments t
                ON t.id = tr.tournament_id
            WHERE tr.user_id = ?
              AND tr.status = 'accepted'
            ORDER BY tr.created_at DESC
        ");


    $tournamentRegistrationStmt->execute([
        $userId
    ]);


    $acceptedTournamentRegistrations =
        $tournamentRegistrationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}


/* ========================================
   HANDLE NOTIFICATION ACTION
======================================== */

if (
    $isCustomer &&
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $notificationAction =
        $_POST["notification_action"]
        ?? "";


    $csrf =
        $_POST["csrf_token"]
        ?? "";


    if (
        !is_string($csrf) ||
        !verifyCsrfToken($csrf)
    ) {

        $_SESSION["profile_error"] =
            "Invalid request. Please try again.";


        header(
            "Location: profile.php#history"
        );


        exit;

    }


    try {

        /* ========================================
           MARK ONE NOTIFICATION AS READ
        ======================================== */

        if (
            $notificationAction ===
            "mark_read"
        ) {

            $notificationId =
                filter_var(
                    $_POST["notification_id"]
                    ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$notificationId ||
                $notificationId <= 0
            ) {

                throw new RuntimeException(
                    "Invalid notification."
                );

            }


            $readStmt =
                $conn->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE id = ?
                      AND user_id = ?
                ");


            $readStmt->execute([

                $notificationId,

                $userId

            ]);

        }


        /* ========================================
           MARK ALL AS READ
        ======================================== */

        elseif (
            $notificationAction ===
            "mark_all_read"
        ) {

            $readAllStmt =
                $conn->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE user_id = ?
                      AND is_read = 0
                ");


            $readAllStmt->execute([
                $userId
            ]);

        }


        $_SESSION["profile_success"] =
            "Notification updated successfully.";


    } catch (Throwable $e) {

        $_SESSION["profile_error"] =
            "Unable to update notification.";

    }


    header(
        "Location: profile.php#history"
    );


    exit;

}


/* ========================================
   SESSION ALERTS
======================================== */

$success =
    $_SESSION["profile_success"]
    ?? "";


$error =
    $_SESSION["profile_error"]
    ?? "";


unset(
    $_SESSION["profile_success"],
    $_SESSION["profile_error"]
);


/* ========================================
   DIGITAL BOOKING RECEIPT
   SAME PROFILE.PHP
======================================== */

$receipt = null;


$receiptId =
    filter_var(
        $_GET["receipt"]
        ?? null,
        FILTER_VALIDATE_INT
    );


if (
    $isCustomer &&
    $receiptId !== false &&
    $receiptId !== null &&
    $receiptId > 0
) {

    $receiptStmt =
        $conn->prepare("
            SELECT
                b.id,
                b.customer_name,
                b.email,
                b.phone,
                b.booking_date,
                b.start_time,
                b.hours,
                b.message,
                b.status,
                b.payment_method,
                b.payment_reference,
                b.payment_status,
                b.created_at,
                gs.name AS setup_name,
                gs.price_per_hour
            FROM bookings b
            LEFT JOIN gaming_setups gs
                ON gs.id = b.setup_id
            WHERE b.id = ?
              AND LOWER(TRIM(b.email)) =
                  LOWER(TRIM(?))
            LIMIT 1
        ");


    $receiptStmt->execute([
        $receiptId,
        $user["email"]
    ]);


    $receipt =
        $receiptStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$receipt) {

        $error =
            "Digital receipt not found.";

    } else {

        /* ========================================
           MARK BOOKING NOTIFICATION AS READ
        ======================================== */

        $readReceiptStmt =
            $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ?
                  AND type = 'booking'
                  AND message LIKE ?
            ");


        $readReceiptStmt->execute([
            $userId,
            "%#" . $receiptId . "%"
        ]);

    }

}


/* ========================================
   RECEIPT DATA
======================================== */

$receiptTotal = 0;

$receiptHours = 0;

$receiptRate = 0;

$receiptDate = "";

$receiptTime = "";

$receiptPaymentMethod = "Cash";

$receiptPaymentReference = "";

$receiptIsGcash = false;


if ($receipt) {

    $receiptHours =
        (int)$receipt["hours"];


    $receiptRate =
        (float)(
            $receipt["price_per_hour"]
            ?? 0
        );


    $receiptTotal =
        $receiptHours *
        $receiptRate;


    $receiptDateTimestamp =
        strtotime(
            (string)
            $receipt["booking_date"]
        );


    $receiptTimeTimestamp =
        strtotime(
            (string)
            $receipt["start_time"]
        );


    $receiptDate =
        $receiptDateTimestamp !== false
            ? date(
                "F d, Y",
                $receiptDateTimestamp
            )
            : (string)
                $receipt["booking_date"];


    $receiptTime =
        $receiptTimeTimestamp !== false
            ? date(
                "h:i A",
                $receiptTimeTimestamp
            )
            : (string)
                $receipt["start_time"];


    $receiptPaymentMethod =
        trim(
            (string)(
                $receipt[
                    "payment_method"
                ] ?? ""
            )
        );


    $receiptPaymentReference =
        trim(
            (string)(
                $receipt[
                    "payment_reference"
                ] ?? ""
            )
        );


    if (
        $receiptPaymentMethod === ""
    ) {

        $receiptPaymentMethod =
            "Cash";

    }


    $receiptIsGcash =
        strcasecmp(
            $receiptPaymentMethod,
            "GCash"
        ) === 0;

}


$csrfToken =
    csrfToken();

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
           KEEP LOGOUT TEXT VISIBLE
        ======================================== */

        .main-nav a.profile-logout-button:hover,
        .profile-logout-button:hover,
        .profile-logout-button:focus,
        .profile-logout-button:active {

            color:
                #000 !important;

            -webkit-text-fill-color:
                #000 !important;

        }


        /* ========================================
           NOTIFICATIONS
        ======================================== */

        .profile-notifications {

            margin-bottom:
                35px;

        }


        .notification-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                18px;

        }


        .notification-heading {

            margin:
                0;

            color:
                #39FF14;

            font:
                700 18px
                "Orbitron",
                sans-serif;

        }


        .notification-count {

            min-width:
                24px;

            height:
                24px;

            padding:
                0 7px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50px;

            background:
                #39FF14;

            color:
                #000;

            font:
                800 9px
                "Orbitron",
                sans-serif;

        }


        .notification-item {

            margin-bottom:
                12px;

            padding:
                17px;

            border:
                1px solid
                #222;

            border-radius:
                5px;

            background:
                #050505;

            transition:
                .2s ease;

        }


        .notification-item.unread {

            border-color:
                rgba(57,255,20,.55);

            background:
                rgba(57,255,20,.025);

        }


        .notification-item:last-child {

            margin-bottom:
                0;

        }


        .notification-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                7px;

        }


        .notification-title {

            color:
                #39FF14;

            font:
                800 10px
                "Orbitron",
                sans-serif;

        }


        .notification-unread-dot {

            width:
                7px;

            height:
                7px;

            flex:
                0 0 auto;

            border-radius:
                50%;

            background:
                #39FF14;

            box-shadow:
                0 0 8px
                rgba(57,255,20,.55);

        }


        .notification-message {

            margin:
                0 0 8px;

            color:
                #fff;

            font-size:
                13px;

            line-height:
                1.45;

        }


        .notification-date {

            display:
                block;

            color:
                #666;

            font-size:
                9px;

            margin-bottom:
                12px;

        }


        .notification-actions {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            flex-wrap:
                wrap;

        }


        .notification-button {

            min-height:
                34px;

            padding:
                0 13px;

            border-radius:
                4px;

            font:
                800 8px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .receipt-button {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                1px solid
                #39FF14;

            background:
                #39FF14;

            color:
                #000;

            text-decoration:
                none;

        }


        .receipt-button:hover {

            background:
                transparent;

            color:
                #39FF14;

        }


        .read-button {

            border:
                1px solid
                #444;

            background:
                transparent;

            color:
                #aaa;

        }


        .read-button:hover {

            border-color:
                #39FF14;

            color:
                #39FF14;

        }


        .mark-all-button {

            border:
                0;

            background:
                transparent;

            color:
                #39FF14;

            font:
                700 8px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

        }


        .mark-all-button:hover {

            text-decoration:
                underline;

        }


        .notification-empty {

            padding:
                20px;

            border:
                1px dashed
                #333;

            border-radius:
                5px;

            color:
                #777;

            text-align:
                center;

            font-size:
                12px;

        }


        /* ========================================
           DIGITAL RECEIPT MODAL
        ======================================== */

        .receipt-overlay {

            position:
                fixed;

            inset:
                0;

            z-index:
                9999;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                20px;

            background:
                rgba(0,0,0,.86);

            opacity:
                1;

            visibility:
                visible;

        }


        .receipt-card {

            position:
                relative;

            width:
                min(720px, 100%);

            max-height:
                90vh;

            overflow-y:
                auto;

            border:
                1px solid
                #39FF14;

            border-radius:
                8px;

            background:
                #000;

            box-shadow:
                0 0 35px
                rgba(57,255,20,.12);

        }


        .receipt-close {

            position:
                absolute;

            top:
                12px;

            right:
                12px;

            width:
                32px;

            height:
                32px;

            border:
                1px solid
                #555;

            border-radius:
                50%;

            background:
                transparent;

            color:
                #fff;

            font-size:
                18px;

            cursor:
                pointer;

            z-index:
                2;

        }


        .receipt-close:hover {

            border-color:
                #39FF14;

            color:
                #39FF14;

        }


        .receipt-header {

            padding:
                30px;

            text-align:
                center;

            border-bottom:
                1px solid
                rgba(57,255,20,.25);

        }


        .receipt-logo {

            width:
                105px;

            display:
                block;

            margin:
                0 auto 12px;

        }


        .receipt-business {

            margin:
                0;

            color:
                #fff;

            font:
                700 20px
                "Orbitron",
                sans-serif;

        }


        .receipt-subtitle {

            margin:
                7px 0 0;

            color:
                #39FF14;

            font:
                600 9px
                "Orbitron",
                sans-serif;

        }


        .receipt-body {

            padding:
                25px 30px 30px;

        }


        .receipt-row {

            display:
                grid;

            grid-template-columns:
                1fr 1.2fr;

            gap:
                25px;

            padding:
                12px 0;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

        }


        .receipt-label {

            color:
                rgba(255,255,255,.52);

            font-size:
                10px;

            font-weight:
                700;

            text-transform:
                uppercase;

        }


        .receipt-value {

            color:
                #fff;

            font-size:
                12px;

            font-weight:
                600;

            text-align:
                right;

            overflow-wrap:
                anywhere;

        }


        .receipt-total {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin-top:
                22px;

            padding:
                18px 20px;

            border:
                1px solid
                #39FF14;

            background:
                rgba(57,255,20,.05);

        }


        .receipt-total span {

            color:
                #fff;

            font:
                700 12px
                "Orbitron",
                sans-serif;

        }


        .receipt-total strong {

            color:
                #39FF14;

            font:
                700 25px
                "Orbitron",
                sans-serif;

        }


        .receipt-print-button {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            width:
                100%;

            min-height:
                44px;

            margin-top:
                18px;

            border:
                1px solid
                #39FF14;

            background:
                #39FF14;

            color:
                #000;

            border-radius:
                4px;

            font:
                800 9px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

        }


        .receipt-print-button:hover {

            background:
                transparent;

            color:
                #39FF14;

        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .notification-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .receipt-overlay {

                padding:
                    12px;

            }


            .receipt-header {

                padding:
                    25px 18px;

            }


            .receipt-body {

                padding:
                    20px;

            }


            .receipt-row {

                grid-template-columns:
                    1fr;

                gap:
                    5px;

            }


            .receipt-value {

                text-align:
                    left;

            }


            .receipt-total {

                padding:
                    15px;

            }


            .receipt-total strong {

                font-size:
                    20px;

            }

        }


        /* ========================================
           PRINT DIGITAL RECEIPT
        ======================================== */

        @media print {

            @page {

                margin:
                    0;

            }


            * {

                -webkit-print-color-adjust:
                    exact !important;

                print-color-adjust:
                    exact !important;

            }


            html,
            body {

                margin:
                    0 !important;

                padding:
                    0 !important;

                background:
                    #000 !important;

                color:
                    #fff !important;

            }


            body > * {

                display:
                    none !important;

            }


            .receipt-overlay {

                display:
                    flex !important;

                position:
                    static !important;

                width:
                    100% !important;

                height:
                    auto !important;

                padding:
                    0 !important;

                background:
                    #000 !important;

            }


            .receipt-card {

                display:
                    block !important;

                width:
                    100% !important;

                max-width:
                    850px !important;

                max-height:
                    none !important;

                margin:
                    0 auto !important;

                overflow:
                    visible !important;

                border:
                    1px solid
                    #39FF14 !important;

                border-radius:
                    0 !important;

                background:
                    #000 !important;

                box-shadow:
                    none !important;

            }


            .receipt-close,
            .receipt-print-button {

                display:
                    none !important;

            }


            .receipt-header,
            .receipt-body {

                background:
                    #000 !important;

                color:
                    #fff !important;

            }


            .receipt-business,
            .receipt-value,
            .receipt-total span {

                color:
                    #fff !important;

            }


            .receipt-label {

                color:
                    rgba(255,255,255,.65) !important;

            }


            .receipt-total {

                background:
                    #000 !important;

            }


            .receipt-total strong {

                color:
                    #39FF14 !important;

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
            type="button"
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

            MY

            <span>

                PROFILE

            </span>

        </h1>


        <!-- ========================================
             CUSTOMER TABS
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


            <!-- NAME + EMAIL -->

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


            <!-- PHONE + MEMBER SINCE -->

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
                                $user["created_at"]
                                ?? "now"
                            )
                        ) ?>"
                        readonly
                    >

                </label>


            </div>


            <!-- ROLE -->

            <div class="form-row">


                <label>

                    Account Type

                    <input
                        type="text"
                        value="<?= $isAdmin
                            ? "Administrator"
                            : "Customer"
                        ?>"
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
                        class="
                            outline-button
                            profile-logout-button
                        "
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
                        class="
                            outline-button
                            profile-logout-button
                        "
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
             CUSTOMER HISTORY
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


                <!-- ========================================
                     NOTIFICATION HEADER
                ======================================== -->

                <div
                    class="profile-notifications"
                >


                    <div
                        class="notification-header"
                    >


                        <h2
                            class="
                                notification-heading
                            "
                        >

                            NOTIFICATIONS

                        </h2>


                        <?php if (
                            $unreadCount > 0
                        ): ?>

                            <div
                                style="
                                    display:flex;
                                    align-items:center;
                                    gap:10px;
                                "
                            >


                                <span
                                    class="
                                        notification-count
                                    "
                                >

                                    <?= $unreadCount ?>

                                </span>


                                <form
                                    method="POST"
                                    style="margin:0;"
                                >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $csrfToken,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="notification_action"
                                        value="mark_all_read"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            mark-all-button
                                        "
                                    >

                                        MARK ALL READ

                                    </button>


                                </form>


                            </div>

                        <?php endif; ?>


                    </div>


                    <?php if (
                        !$notifications
                    ): ?>


                        <div
                            class="
                                notification-empty
                            "
                        >

                            No notifications yet.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>


                            <?php

                            $notificationId =
                                (int)
                                $notification["id"];


                            $notificationType =
                                (string)
                                $notification["type"];


                            $notificationTitle =
                                (string)
                                $notification["title"];


                            $notificationMessage =
                                (string)
                                $notification["message"];


                            $isUnread =
                                (
                                    (int)
                                    $notification[
                                        "is_read"
                                    ] === 0
                                );


                            $notificationDate =
                                strtotime(
                                    (string)
                                    $notification[
                                        "created_at"
                                    ]
                                );


                            /*
                            ========================================
                            BOOKING ID
                            ========================================
                            */

                            $notificationBookingId =
                                null;


                            if (
                                $notificationType ===
                                "booking"
                            ) {

                                if (
                                    preg_match(
                                        '/#(\d+)/',
                                        $notificationMessage,
                                        $matches
                                    )
                                ) {

                                    $notificationBookingId =
                                        (int)
                                        $matches[1];

                                }

                            }


                            /*
                            ========================================
                            TOURNAMENT ID
                            ========================================
                            */

                            $notificationTournamentId =
                                null;


                            if (
                                $notificationType ===
                                "tournament"
                            ) {

                                if (
                                    preg_match(
                                        '/^Your registration for (.+) has been approved successfully\.$/i',
                                        $notificationMessage,
                                        $tournamentMatches
                                    )
                                ) {

                                    $notificationTournamentTitle =
                                        trim(
                                            $tournamentMatches[1]
                                        );


                                    foreach (
                                        $acceptedTournamentRegistrations
                                        as $acceptedTournament
                                    ) {


                                        if (
                                            strcasecmp(
                                                trim(
                                                    (string)
                                                    $acceptedTournament[
                                                        "tournament_title"
                                                    ]
                                                ),
                                                $notificationTournamentTitle
                                            ) === 0
                                        ) {

                                            $notificationTournamentId =
                                                (int)
                                                $acceptedTournament[
                                                    "tournament_id"
                                                ];

                                            break;

                                        }

                                    }

                                }

                            }

                            ?>


                            <div
                                class="
                                    notification-item
                                    <?= $isUnread
                                        ? "unread"
                                        : ""
                                    ?>
                                "
                            >


                                <div
                                    class="
                                        notification-top
                                    "
                                >


                                    <span
                                        class="
                                            notification-title
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $notificationTitle,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>


                                    <?php if (
                                        $isUnread
                                    ): ?>


                                        <span
                                            class="
                                                notification-unread-dot
                                            "
                                        ></span>


                                    <?php endif; ?>


                                </div>


                                <p
                                    class="
                                        notification-message
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $notificationMessage,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </p>


                                <?php if (
                                    $notificationDate !== false
                                ): ?>


                                    <span
                                        class="
                                            notification-date
                                        "
                                    >

                                        <?= date(
                                            "M d, Y h:i A",
                                            $notificationDate
                                        ) ?>

                                    </span>


                                <?php endif; ?>


                                <div
                                    class="
                                        notification-actions
                                    "
                                >


                                    <!-- ====================================
                                         BOOKING RECEIPT
                                    ===================================== -->

                                    <?php if (
                                        $notificationType ===
                                            "booking" &&
                                        $notificationBookingId
                                    ): ?>


                                        <a
                                            href="profile.php?receipt=<?= $notificationBookingId ?>#history"
                                            class="
                                                notification-button
                                                receipt-button
                                            "
                                        >

                                            VIEW DIGITAL RECEIPT

                                        </a>


                                    <?php endif; ?>


                                    <!-- ====================================
                                         TOURNAMENT RECEIPT
                                    ===================================== -->

                                    <?php if (
                                        $notificationType ===
                                            "tournament" &&
                                        $notificationTournamentId
                                    ): ?>


                                        <a
                                            href="tournament.php?id=<?= $notificationTournamentId ?>&receipt=1"
                                            class="
                                                notification-button
                                                receipt-button
                                            "
                                        >

                                            VIEW TOURNAMENT RECEIPT

                                        </a>


                                    <?php endif; ?>


                                    <!-- ====================================
                                         MARK READ
                                    ===================================== -->

                                    <?php if (
                                        $isUnread
                                    ): ?>


                                        <form
                                            method="POST"
                                            style="margin:0;"
                                        >


                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $csrfToken,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="notification_action"
                                                value="mark_read"
                                            >


                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= $notificationId ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="
                                                    notification-button
                                                    read-button
                                                "
                                            >

                                                MARK READ

                                            </button>


                                        </form>


                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


                <!-- ========================================
                     BOOKING HISTORY
                ======================================== -->

                <p
                    class="section-kicker"
                >

                    BOOKING HISTORY

                </p>


                <h2
                    class="section-heading"
                    style="margin-bottom:25px;"
                >

                    MY

                    <span>

                        BOOKINGS

                    </span>

                </h2>


                <?php if (
                    count($bookings) === 0
                ): ?>


                    <div
                        class="form-message"
                    >

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


                        <?php foreach (
                            $bookings
                            as $booking
                        ): ?>


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
                                            font-family:'Orbitron',sans-serif;
                                            font-size:15px;
                                        "
                                    >

                                        BOOKING

                                        #<?= (int)
                                            $booking["id"]
                                        ?>


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
                                            (string)
                                            $booking["status"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>


                                    </span>


                                </div>


                                <!-- STATION + DATE -->

                                <div
                                    class="form-row"
                                >


                                    <label>

                                        Gaming Station

                                        <input
                                            type="text"
                                            value="<?= htmlspecialchars(
                                                (string)(
                                                    $booking[
                                                        "setup_name"
                                                    ]
                                                    ?? ""
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
                                            value="<?= date(
                                                "F d, Y",
                                                strtotime(
                                                    (string)
                                                    $booking[
                                                        "booking_date"
                                                    ]
                                                )
                                            ) ?>"
                                            readonly
                                        >

                                    </label>


                                </div>


                                <!-- TIME + DURATION -->

                                <div
                                    class="form-row"
                                >


                                    <label>

                                        Time

                                        <input
                                            type="text"
                                            value="<?= date(
                                                "h:i A",
                                                strtotime(
                                                    (string)
                                                    $booking[
                                                        "start_time"
                                                    ]
                                                )
                                            ) ?>"
                                            readonly
                                        >

                                    </label>


                                    <label>

                                        Duration

                                        <input
                                            type="text"
                                            value="<?= (int)
                                                $booking[
                                                    "hours"
                                                ]
                                                ?> hour<?= (
                                                    (int)
                                                    $booking[
                                                        "hours"
                                                    ] !== 1
                                                )
                                                    ? "s"
                                                    : ""
                                                ?>"
                                            readonly
                                        >

                                    </label>


                                </div>


                                <!-- PAYMENT -->

                                <div
                                    class="form-row"
                                >


                                    <label>

                                        Mode of Payment

                                        <input
                                            type="text"
                                            value="<?= htmlspecialchars(
                                                strtoupper(
                                                    trim(
                                                        (string)(
                                                            $booking[
                                                                "payment_method"
                                                            ]
                                                            ?? "Cash"
                                                        )
                                                    )
                                                    ?: "CASH"
                                                ),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            readonly
                                        >

                                    </label>


                                    <?php

                                    $bookingPaymentMethod =
                                        trim(
                                            (string)(
                                                $booking[
                                                    "payment_method"
                                                ]
                                                ?? ""
                                            )
                                        );


                                    $bookingPaymentReference =
                                        trim(
                                            (string)(
                                                $booking[
                                                    "payment_reference"
                                                ]
                                                ?? ""
                                            )
                                        );


                                    $bookingIsGcash =
                                        strcasecmp(
                                            $bookingPaymentMethod,
                                            "GCash"
                                        ) === 0;

                                    ?>


                                    <?php if (
                                        $bookingIsGcash &&
                                        $bookingPaymentReference !== ""
                                    ): ?>


                                        <label>

                                            GCash Reference Number

                                            <input
                                                type="text"
                                                value="<?= htmlspecialchars(
                                                    $bookingPaymentReference,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                                readonly
                                            >

                                        </label>


                                    <?php endif; ?>


                                </div>


                                <!-- NOTES -->

                                <?php if (
                                    !empty(
                                        $booking["message"]
                                    )
                                ): ?>


                                    <label>

                                        Notes

                                        <textarea
                                            readonly
                                            rows="3"
                                            style="resize:none;"
                                        ><?= htmlspecialchars(
                                            (string)
                                            $booking["message"],
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


        <?php if (
            $success !== ""
        ): ?>


            <div
                style="
                    margin-top:20px;
                    padding:12px 15px;
                    border:1px solid #39FF14;
                    background:rgba(57,255,20,.05);
                    color:#39FF14;
                    border-radius:4px;
                    font-size:12px;
                "
            >

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


        <?php endif; ?>


        <?php if (
            $error !== ""
        ): ?>


            <div
                style="
                    margin-top:20px;
                    padding:12px 15px;
                    border:1px solid #ff4747;
                    background:rgba(255,0,0,.05);
                    color:#ff6b6b;
                    border-radius:4px;
                    font-size:12px;
                "
            >

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


        <?php endif; ?>


    </div>

</main>


<!-- ========================================
     DIGITAL BOOKING RECEIPT
======================================== -->

<?php if ($receipt): ?>


    <div
        class="receipt-overlay"
        id="receiptOverlay"
    >


        <div
            class="receipt-card"
        >


            <button
                type="button"
                class="receipt-close"
                id="receiptClose"
                aria-label="Close receipt"
            >

                ×

            </button>


            <!-- RECEIPT HEADER -->

            <div
                class="receipt-header"
            >


                <img
                    src="assets/images/logo.png"
                    alt="Bais Rouilo Gaming Cafe"
                    class="receipt-logo"
                >


                <h2
                    class="receipt-business"
                >

                    BAIS ROUILO
                    GAMING CAFE

                </h2>


                <p
                    class="receipt-subtitle"
                >

                    DIGITAL BOOKING RECEIPT

                </p>


            </div>


            <!-- RECEIPT BODY -->

            <div
                class="receipt-body"
            >


                <div class="receipt-row">


                    <span class="receipt-label">

                        Booking ID

                    </span>


                    <span class="receipt-value">

                        #<?= (int)
                            $receipt["id"]
                        ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Customer

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)
                            $receipt[
                                "customer_name"
                            ],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Email

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $receipt[
                                    "email"
                                ] ?? ""
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <?php if (
                    !empty(
                        $receipt["phone"]
                    )
                ): ?>


                    <div class="receipt-row">


                        <span class="receipt-label">

                            Phone

                        </span>


                        <span class="receipt-value">

                            <?= htmlspecialchars(
                                (string)
                                $receipt[
                                    "phone"
                                ],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>


                    </div>


                <?php endif; ?>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Gaming Setup

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $receipt[
                                    "setup_name"
                                ] ?? "Unknown"
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Booking Date

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            $receiptDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Start Time

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            $receiptTime,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Duration

                    </span>


                    <span class="receipt-value">

                        <?= $receiptHours ?>

                        hour<?= $receiptHours !== 1
                            ? "s"
                            : "" ?>

                    </span>


                </div>


                <div class="receipt-row">


                    <span class="receipt-label">

                        Rate / Hour

                    </span>


                    <span class="receipt-value">

                        ₱<?= number_format(
                            $receiptRate,
                            2
                        ) ?>

                    </span>


                </div>


                <!-- MODE OF PAYMENT -->

                <div class="receipt-row">


                    <span class="receipt-label">

                        Mode of Payment

                    </span>


                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            strtoupper(
                                $receiptPaymentMethod
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>


                </div>


                <!-- GCASH REFERENCE -->

                <?php if (
                    $receiptIsGcash &&
                    $receiptPaymentReference !== ""
                ): ?>


                    <div class="receipt-row">


                        <span class="receipt-label">

                            GCash Reference Number

                        </span>


                        <span class="receipt-value">

                            <?= htmlspecialchars(
                                $receiptPaymentReference,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>


                    </div>


                <?php endif; ?>


                <!-- NOTES -->

                <?php

                $receiptMessage =
                    trim(
                        (string)(
                            $receipt[
                                "message"
                            ] ?? ""
                        )
                    );

                ?>


                <?php if (
                    $receiptMessage !== ""
                ): ?>


                    <div class="receipt-row">


                        <span class="receipt-label">

                            Notes

                        </span>


                        <span class="receipt-value">

                            <?= htmlspecialchars(
                                $receiptMessage,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>


                    </div>


                <?php endif; ?>


                <!-- TOTAL -->

                <div
                    class="receipt-total"
                >


                    <span>

                        TOTAL

                    </span>


                    <strong>

                        ₱<?= number_format(
                            $receiptTotal,
                            2
                        ) ?>

                    </strong>


                </div>


                <button
                    type="button"
                    class="receipt-print-button"
                    onclick="window.print()"
                >

                    PRINT RECEIPT

                </button>


            </div>


        </div>


    </div>


<?php endif; ?>


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


function showProfileSection(
    sectionName
) {


    profileSections.forEach(
        function(section) {


            section.style.display =
                section.dataset.profileSection ===
                sectionName
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


                tab.style.color =
                    "";


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


/*
========================================
INITIAL PROFILE TAB
========================================
*/

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
     BOOKING RECEIPT SCRIPT
======================================== -->

<?php if ($receipt): ?>


<script>

const receiptOverlay =
    document.getElementById(
        "receiptOverlay"
    );


const receiptClose =
    document.getElementById(
        "receiptClose"
    );


function closeReceipt() {

    window.location.href =
        "profile.php#history";

}


if (
    receiptOverlay &&
    receiptClose
) {


    receiptClose.addEventListener(
        "click",
        closeReceipt
    );


    receiptOverlay.addEventListener(
        "click",
        function(event) {


            if (
                event.target ===
                receiptOverlay
            ) {

                closeReceipt();

            }


        }
    );


    document.addEventListener(
        "keydown",
        function(event) {


            if (
                event.key === "Escape"
            ) {

                closeReceipt();

            }


        }
    );


}

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


if (
    menuToggle &&
    mainNav
) {


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