<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireAdmin();

$error = "";

/* ========================================
   GET BOOKING ID
======================================== */
$bookingId = filter_var(
    $_GET["id"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $bookingId === false ||
    $bookingId <= 0
) {
    http_response_code(404);
    exit("Booking not found.");
}

/* ========================================
   VIEW RECEIPT MODE
   ?view=1
======================================== */
$viewMode =
    isset($_GET["view"]) &&
    $_GET["view"] === "1";


/* ========================================
   HANDLE DONE
   ACCEPTED -> COMPLETED
======================================== */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !$viewMode
) {

    try {

        /* ========================================
           CSRF
        ======================================== */
        $csrf =
            $_POST["csrf_token"] ?? "";

        if (
            !is_string($csrf) ||
            !verifyCsrfToken($csrf)
        ) {

            throw new RuntimeException(
                "Invalid request. Please refresh the page and try again."
            );
        }


        /* ========================================
           GET CUSTOMER INFO
        ======================================== */
        $customerStmt =
            $conn->prepare("
                SELECT
                    b.id,
                    b.customer_name,
                    b.email,
                    b.booking_date,
                    b.start_time,
                    u.id AS user_id
                FROM bookings b
                LEFT JOIN users u
                    ON LOWER(TRIM(u.email)) =
                       LOWER(TRIM(b.email))
                WHERE b.id = ?
                LIMIT 1
            ");

        $customerStmt->execute([
            $bookingId
        ]);

        $customerInfo =
            $customerStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$customerInfo) {

            throw new RuntimeException(
                "Booking not found."
            );
        }


        /* ========================================
           COMPLETE BOOKING
        ======================================== */
        $stmt =
            $conn->prepare("
                UPDATE bookings
                SET status = 'completed'
                WHERE id = ?
                  AND status = 'accepted'
            ");

        $stmt->execute([
            $bookingId
        ]);


        /* ========================================
           CHECK ACTUAL STATUS
        ======================================== */
        $checkStmt =
            $conn->prepare("
                SELECT status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

        $checkStmt->execute([
            $bookingId
        ]);

        $currentStatus =
            $checkStmt->fetchColumn();


        /* ========================================
           CREATE CUSTOMER NOTIFICATION
        ======================================== */
        if (
            $currentStatus === "completed" &&
            !empty($customerInfo["user_id"])
        ) {

            $userId =
                (int)$customerInfo["user_id"];

            $notificationTitle =
                "BOOKING APPROVED";

            $notificationMessage =
                "Your booking #"
                . $bookingId
                . " at Bais Rouilo Gaming Cafe "
                . "has been approved and completed.";


            /* ========================================
               PREVENT DUPLICATE NOTIFICATION
            ======================================== */
            $duplicateCheck =
                $conn->prepare("
                    SELECT id
                    FROM notifications
                    WHERE user_id = ?
                      AND type = 'booking'
                      AND title = ?
                      AND message = ?
                    LIMIT 1
                ");

            $duplicateCheck->execute([
                $userId,
                $notificationTitle,
                $notificationMessage
            ]);

            $existingNotification =
                $duplicateCheck->fetchColumn();


            if (!$existingNotification) {

                $notifyStmt =
                    $conn->prepare("
                        INSERT INTO notifications
                        (
                            user_id,
                            type,
                            title,
                            message
                        )
                        VALUES
                        (
                            ?,
                            'booking',
                            ?,
                            ?
                        )
                    ");

                $notifyStmt->execute([
                    $userId,
                    $notificationTitle,
                    $notificationMessage
                ]);
            }


            /* ========================================
               GO BACK TO DASHBOARD
            ======================================== */
            header(
                "Location: dashboard.php"
            );

            exit;
        }


        if ($currentStatus === false) {

            throw new RuntimeException(
                "Booking not found."
            );
        }


        throw new RuntimeException(
            "This booking cannot be completed."
        );

    } catch (Throwable $e) {

        $error =
            $e->getMessage();
    }
}


/* ========================================
   GET BOOKING DETAILS
======================================== */
$stmt =
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

        LIMIT 1
    ");

$stmt->execute([
    $bookingId
]);

$booking =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$booking) {

    http_response_code(404);

    exit(
        "Booking not found."
    );
}


/* ========================================
   STATUS HANDLING
======================================== */

/*
 * Completed booking:
 * allow it only in VIEW MODE.
 */
if (
    $booking["status"] === "completed" &&
    !$viewMode
) {

    header(
        "Location: dashboard.php"
    );

    exit;
}


/*
 * Rejected booking cannot be processed/viewed.
 */
if (
    $booking["status"] === "rejected"
) {

    header(
        "Location: bookings.php"
    );

    exit;
}


/*
 * New accepted booking is allowed
 * for normal processing.
 */
if (
    $booking["status"] !== "accepted" &&
    $booking["status"] !== "completed"
) {

    header(
        "Location: bookings.php"
    );

    exit;
}


/* ========================================
   CALCULATE TOTAL
======================================== */
$hours =
    (int)$booking["hours"];

$pricePerHour =
    (float)$booking["price_per_hour"];

$total =
    $hours * $pricePerHour;


/* ========================================
   PAYMENT DETAILS
======================================== */
$paymentMethod =
    trim(
        (string)(
            $booking["payment_method"] ?? ""
        )
    );

$paymentReference =
    trim(
        (string)(
            $booking["payment_reference"] ?? ""
        )
    );


if ($paymentMethod === "") {
    $paymentMethod = "Cash";
}


$isGcash =
    strcasecmp(
        $paymentMethod,
        "GCash"
    ) === 0;


/* ========================================
   FORMAT DATE / TIME
======================================== */
$dateTimestamp =
    strtotime(
        (string)$booking["booking_date"]
    );

$timeTimestamp =
    strtotime(
        (string)$booking["start_time"]
    );


$formattedDate =
    $dateTimestamp !== false
        ? date(
            "F d, Y",
            $dateTimestamp
        )
        : (string)$booking["booking_date"];


$formattedTime =
    $timeTimestamp !== false
        ? date(
            "h:i A",
            $timeTimestamp
        )
        : (string)$booking["start_time"];


/* ========================================
   CSRF
======================================== */
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
        content="Booking receipt - Bais Rouilo Gaming Cafe."
    >

    <title>
        <?= $viewMode
            ? "VIEW RECEIPT"
            : "PROCESS BOOKING"
        ?>
        | Bais Rouilo Gaming Cafe
    </title>


    <!-- GOOGLE FONTS -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
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
        href="../assets/css/style.css"
    >


    <style>

        /* ========================================
           PROCESS PAGE
        ======================================== */

        .process-page {

            min-height:
                calc(100vh - 101px);

            padding:
                55px 20px 80px;

        }


        .process-wrapper {

            width:
                100%;

            max-width:
                850px;

            margin:
                0 auto;

        }


        /* ========================================
           TITLE
        ======================================== */

        .process-kicker {

            margin:
                0 0 8px;

            text-align:
                center;

            color:
                #39FF14;

            font:
                500 11px
                "Orbitron",
                sans-serif;

        }


        .process-title {

            margin:
                0 0 32px;

            text-align:
                center;

            color:
                #fff;

            font:
                600 42px/1
                "Orbitron",
                sans-serif;

        }


        .process-title span {

            color:
                #39FF14;

        }


        /* ========================================
           ERROR
        ======================================== */

        .process-error {

            margin:
                0 0 20px;

            padding:
                14px 18px;

            border:
                1px solid
                #ff4444;

            background:
                rgba(255,0,0,.08);

            color:
                #ff7777;

            font-size:
                12px;

        }


        /* ========================================
           RECEIPT CARD
        ======================================== */

        .receipt-card {

            width:
                100%;

            border:
                1px solid
                #39FF14;

            border-radius:
                10px;

            background:
                rgba(0,0,0,.93);

            box-shadow:
                0 0 25px
                rgba(57,255,20,.08);

            overflow:
                hidden;

        }


        /* ========================================
           RECEIPT HEADER
        ======================================== */

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
                600 10px
                "Orbitron",
                sans-serif;

        }


        /* ========================================
           PROCESSING NOTICE
        ======================================== */

        .processing-notice {

            margin:
                24px 30px 0;

            padding:
                13px 15px;

            border:
                1px solid
                #39FF14;

            background:
                rgba(57,255,20,.06);

            color:
                #39FF14;

            text-align:
                center;

            font:
                700 10px
                "Orbitron",
                sans-serif;

        }


        /* ========================================
           RECEIPT BODY
        ======================================== */

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
                13px 0;

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


        /* ========================================
           PAYMENT
           WHITE ONLY
        ======================================== */

        .payment-value {

            color:
                #fff !important;

        }


        .gcash-reference {

            color:
                #fff !important;

        }


        /* ========================================
           TOTAL
        ======================================== */

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
                rgba(57,255,20,.06);

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


        /* ========================================
           ACTIONS
        ======================================== */

        .process-actions {

            display:
                flex;

            align-items:
                stretch;

            gap:
                12px;

            padding:
                0 30px 30px;

        }


        .print-button,
        .done-button {

            flex:
                1;

            min-height:
                48px;

            border:
                1px solid
                #39FF14;

            border-radius:
                4px;

            font:
                800 10px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .print-button {

            background:
                transparent;

            color:
                #39FF14;

        }


        .print-button:hover {

            background:
                #39FF14;

            color:
                #000;

            box-shadow:
                0 0 15px
                rgba(57,255,20,.4);

        }


        .done-button {

            background:
                #39FF14;

            color:
                #000;

        }


        .done-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 0 15px
                rgba(57,255,20,.5);

        }


        /* ========================================
           CENTER CONFIRM MODAL
        ======================================== */

        .confirm-overlay {

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
                rgba(0,0,0,.82);

        }


        .confirm-overlay[hidden] {

            display:
                none !important;

        }


        .confirm-modal {

            width:
                min(440px, 100%);

            padding:
                28px;

            border:
                1px solid
                #39FF14;

            border-radius:
                8px;

            background:
                #080808;

            box-shadow:
                0 0 30px
                rgba(57,255,20,.18);

            text-align:
                center;

        }


        .confirm-modal h3 {

            margin:
                0 0 12px;

            color:
                #39FF14;

            font:
                700 18px
                "Orbitron",
                sans-serif;

        }


        .confirm-modal p {

            margin:
                0 0 24px;

            color:
                #fff;

            font-size:
                14px;

            line-height:
                1.5;

        }


        .confirm-actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                10px;

        }


        .confirm-yes,
        .confirm-no {

            min-width:
                105px;

            min-height:
                40px;

            padding:
                0 18px;

            border-radius:
                4px;

            font:
                800 10px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .confirm-yes {

            background:
                #39FF14;

            color:
                #000;

            border:
                1px solid
                #39FF14;

        }


        .confirm-yes:hover {

            box-shadow:
                0 0 15px
                rgba(57,255,20,.4);

            transform:
                translateY(-2px);

        }


        .confirm-no {

            background:
                transparent;

            color:
                #fff;

            border:
                1px solid
                #555;

        }


        .confirm-no:hover {

            border-color:
                #39FF14;

            color:
                #39FF14;

        }


        /* ========================================
           BACK TO BOOKINGS
           VIEW RECEIPT ONLY
        ======================================== */
        .receipt-back-wrapper {
            width: 100%;
            max-width: 850px;
            margin: 0 auto 18px;
        }

        .receipt-back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid #39FF14;
            border-radius: 4px;
            background: #39FF14;
            color: #000;
            text-decoration: none;
            font: 800 9px "Orbitron", sans-serif;
            transition: .2s ease;
        }

        .receipt-back-button:hover {
            background: transparent;
            color: #39FF14;
            box-shadow: 0 0 12px rgba(57,255,20,.35);
        }

        /* ========================================
           VIEW MODE
           COMPLETED RECEIPT
        ======================================== */

        <?php if ($viewMode): ?>

        .process-page {

            padding:
                35px 20px 60px;

        }


        .receipt-card {

            max-width:
                850px;

            margin:
                0 auto;

        }

        <?php endif; ?>


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .process-page {

                padding:
                    40px 15px 60px;

            }


            .process-title {

                font-size:
                    30px;

            }


            .receipt-header {

                padding:
                    25px 18px;

            }


            .receipt-body {

                padding:
                    20px;

            }


            .processing-notice {

                margin:
                    20px 20px 0;

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


            .process-actions {

                flex-direction:
                    column;

                padding:
                    0 20px 20px;

            }


            .confirm-modal {

                padding:
                    24px 20px;

            }

        }


        /* ========================================
           PRINT RECEIPT
        ======================================== */

        @media print {

            @page {

                margin:
                    0;

                size:
                    auto;

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


            .site-header,
            .process-kicker,
            .process-title,
            .processing-notice,
            .process-actions,
            .confirm-overlay,
            .receipt-back-wrapper {

                display:
                    none !important;

            }


            .process-page {

                min-height:
                    100vh !important;

                padding:
                    0 !important;

                background:
                    #000 !important;

            }


            .process-wrapper {

                width:
                    100% !important;

                max-width:
                    none !important;

                margin:
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

                min-height:
                    100vh !important;

                margin:
                    0 auto !important;

                border:
                    1px solid
                    #39FF14 !important;

                border-radius:
                    0 !important;

                background:
                    #000 !important;

                color:
                    #fff !important;

                box-shadow:
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
            .payment-value,
            .gcash-reference,
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

                border:
                    1px solid
                    #39FF14 !important;

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
     ADMIN HEADER
======================================== -->

<header class="site-header">

    <div class="container nav-container">


        <!-- LOGO -->

        <a
            href="dashboard.php"
            class="brand"
        >

            <img
                src="../assets/images/logo.png"
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


        <!-- ADMIN NAVIGATION -->

        <nav
            class="main-nav"
            id="mainNav"
        >

            <a href="dashboard.php">
                DASHBOARD
            </a>


            <a
                href="bookings.php"
                class="active"
            >
                BOOKINGS
            </a>


            <a href="../index.php">
                WEBSITE
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     PROCESS PAGE
======================================== -->

<main class="process-page">

    <div class="process-wrapper">


        <?php if ($viewMode): ?>

            <!-- ========================================
                 BACK TO BOOKINGS
            ======================================== -->

            <div class="receipt-back-wrapper">

                <a
                    href="bookings.php"
                    class="receipt-back-button"
                >
                    ← BACK TO BOOKINGS
                </a>

            </div>

        <?php endif; ?>


        <?php if (!$viewMode): ?>

            <!-- TITLE -->

            <p class="process-kicker">
                ADMIN PANEL
            </p>


            <h1 class="process-title">

                BOOKING

                <span>
                    PROCESSING
                </span>

            </h1>

        <?php endif; ?>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="process-error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             RECEIPT CARD
        ======================================== -->

        <div class="receipt-card">


            <!-- RECEIPT HEADER -->

            <div class="receipt-header">

                <img
                    src="../assets/images/logo.png"
                    alt="Bais Rouilo Gaming Cafe"
                    class="receipt-logo"
                >


                <h2 class="receipt-business">

                    BAIS ROUILO
                    GAMING CAFE

                </h2>


                <p class="receipt-subtitle">

                    BOOKING RECEIPT

                </p>

            </div>


            <!-- ========================================
                 PROCESSING NOTICE
                 HIDDEN IN VIEW MODE
            ======================================== -->

            <?php if (!$viewMode): ?>

                <div class="processing-notice">

                    BOOKING ACCEPTED —
                    READY TO COMPLETE

                </div>

            <?php endif; ?>


            <!-- ========================================
                 RECEIPT BODY
            ======================================== -->

            <div class="receipt-body">


                <!-- BOOKING ID -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Booking ID
                    </span>

                    <span class="receipt-value">

                        #<?= (int)$booking["id"] ?>

                    </span>

                </div>


                <!-- CUSTOMER -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Customer
                    </span>

                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)
                            $booking["customer_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- EMAIL -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Email
                    </span>

                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $booking["email"] ?? ""
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- PHONE -->

                <?php if (
                    !empty($booking["phone"])
                ): ?>

                    <div class="receipt-row">

                        <span class="receipt-label">
                            Phone
                        </span>

                        <span class="receipt-value">

                            <?= htmlspecialchars(
                                (string)
                                $booking["phone"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- GAMING SETUP -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Gaming Setup
                    </span>

                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $booking["setup_name"]
                                ?? "Unknown"
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- BOOKING DATE -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Booking Date
                    </span>

                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            $formattedDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- START TIME -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Start Time
                    </span>

                    <span class="receipt-value">

                        <?= htmlspecialchars(
                            $formattedTime,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- DURATION -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Duration
                    </span>

                    <span class="receipt-value">

                        <?= $hours ?>

                        hour<?= $hours !== 1
                            ? "s"
                            : "" ?>

                    </span>

                </div>


                <!-- RATE -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Rate / Hour
                    </span>

                    <span class="receipt-value">

                        ₱<?= number_format(
                            $pricePerHour,
                            2
                        ) ?>

                    </span>

                </div>


                <!-- MODE OF PAYMENT -->

                <div class="receipt-row">

                    <span class="receipt-label">
                        Mode of Payment
                    </span>

                    <span class="receipt-value payment-value">

                        <?= htmlspecialchars(
                            strtoupper(
                                $paymentMethod
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <!-- GCASH REFERENCE -->

                <?php if (
                    $isGcash &&
                    $paymentReference !== ""
                ): ?>

                    <div class="receipt-row">

                        <span class="receipt-label">
                            GCash Reference Number
                        </span>

                        <span class="receipt-value gcash-reference">

                            <?= htmlspecialchars(
                                $paymentReference,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- MESSAGE -->

                <?php

                $bookingMessage =
                    trim(
                        (string)(
                            $booking["message"] ?? ""
                        )
                    );

                ?>


                <?php if (
                    $bookingMessage !== ""
                ): ?>

                    <div class="receipt-row">

                        <span class="receipt-label">
                            Message
                        </span>

                        <span class="receipt-value">

                            <?= htmlspecialchars(
                                $bookingMessage,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- TOTAL -->

                <div class="receipt-total">

                    <span>
                        TOTAL
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $total,
                            2
                        ) ?>

                    </strong>

                </div>


            </div>


            <!-- ========================================
                 ACTION BUTTONS
                 ONLY FOR NORMAL PROCESSING
            ======================================== -->

            <?php if (!$viewMode): ?>

                <div class="process-actions">


                    <!-- PRINT -->

                    <button
                        type="button"
                        class="print-button"
                        onclick="window.print()"
                    >
                        PRINT RECEIPT
                    </button>


                    <!-- DONE -->

                    <form
                        method="POST"
                        action="process_booking.php?id=<?= (int)$bookingId ?>"
                        id="doneForm"
                        style="
                            flex:1;
                            margin:0;
                        "
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


                        <button
                            type="button"
                            class="done-button"
                            id="doneButton"
                        >
                            DONE
                        </button>

                    </form>

                </div>

            <?php endif; ?>


        </div>

    </div>

</main>


<!-- ========================================
     CENTER CONFIRMATION MODAL
     ONLY FOR NORMAL PROCESSING
======================================== -->

<?php if (!$viewMode): ?>

    <div
        class="confirm-overlay"
        id="confirmOverlay"
        hidden
    >

        <div
            class="confirm-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="confirmTitle"
        >

            <h3 id="confirmTitle">
                CONFIRM BOOKING
            </h3>


            <p>
                Mark this booking as completed?
            </p>


            <div class="confirm-actions">

                <button
                    type="button"
                    class="confirm-no"
                    id="cancelDone"
                >
                    CANCEL
                </button>


                <button
                    type="button"
                    class="confirm-yes"
                    id="confirmDone"
                >
                    OK
                </button>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- ========================================
     JAVASCRIPT
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


/* ========================================
   MOBILE MENU
======================================== */

if (
    menuToggle &&
    mainNav
) {

    menuToggle.addEventListener(
        "click",
        function () {

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


<?php if (!$viewMode): ?>

/* ========================================
   CENTER CONFIRMATION
======================================== */

const doneButton =
    document.getElementById(
        "doneButton"
    );


const confirmOverlay =
    document.getElementById(
        "confirmOverlay"
    );


const cancelDone =
    document.getElementById(
        "cancelDone"
    );


const confirmDone =
    document.getElementById(
        "confirmDone"
    );


const doneForm =
    document.getElementById(
        "doneForm"
    );


if (
    doneButton &&
    confirmOverlay &&
    cancelDone &&
    confirmDone &&
    doneForm
) {


    /* OPEN */

    doneButton.addEventListener(
        "click",
        function () {

            confirmOverlay.hidden =
                false;

            document.body.style.overflow =
                "hidden";

        }
    );


    /* CANCEL */

    cancelDone.addEventListener(
        "click",
        function () {

            confirmOverlay.hidden =
                true;

            document.body.style.overflow =
                "";

        }
    );


    /* OK */

    confirmDone.addEventListener(
        "click",
        function () {

            confirmOverlay.hidden =
                true;

            document.body.style.overflow =
                "";

            doneForm.submit();

        }
    );


    /* CLICK OUTSIDE */

    confirmOverlay.addEventListener(
        "click",
        function (event) {

            if (
                event.target ===
                confirmOverlay
            ) {

                confirmOverlay.hidden =
                    true;

                document.body.style.overflow =
                    "";

            }

        }
    );


    /* ESC */

    document.addEventListener(
        "keydown",
        function (event) {

            if (
                event.key === "Escape" &&
                !confirmOverlay.hidden
            ) {

                confirmOverlay.hidden =
                    true;

                document.body.style.overflow =
                    "";

            }

        }
    );

}

<?php endif; ?>

</script>


</body>

</html>