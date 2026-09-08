<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

requireLogin();

if (($_SESSION["user_role"] ?? "") === "admin") {
    header("Location: admin/dashboard.php");
    exit;
}

$currentUserId = currentUserId();

if (!$currentUserId) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";


/* ========================================
   HANDLE NOTIFICATION ACTIONS
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postAction = $_POST["notification_action"] ?? "";

    if (
        $postAction === "mark_read" ||
        $postAction === "mark_all_read"
    ) {

        $csrf = $_POST["csrf_token"] ?? "";

        if (
            !is_string($csrf) ||
            !verifyCsrfToken($csrf)
        ) {

            $error =
                "Invalid request. Please refresh the page and try again.";

        } else {

            try {

                if ($postAction === "mark_read") {

                    $notificationId = filter_var(
                        $_POST["notification_id"] ?? null,
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

                    $markStmt = $conn->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = ?
                          AND user_id = ?
                    ");

                    $markStmt->execute([
                        $notificationId,
                        $currentUserId
                    ]);

                } else {

                    $markAllStmt = $conn->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE user_id = ?
                          AND is_read = 0
                    ");

                    $markAllStmt->execute([
                        $currentUserId
                    ]);
                }

            } catch (Throwable $e) {

                $error =
                    "Unable to update notification.";
            }
        }
    }
}


/* ========================================
   NOTIFICATIONS
======================================== */

$notificationStmt = $conn->prepare("
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
    $currentUserId
]);

$notifications =
    $notificationStmt->fetchAll();

$unreadCount = 0;

foreach ($notifications as $notification) {

    if ((int)$notification["is_read"] === 0) {
        $unreadCount++;
    }
}


/* ========================================
   DIGITAL RECEIPT
   SAME INDEX.PHP
======================================== */

$selectedReceipt = null;

$receiptId = filter_var(
    $_GET["receipt"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $receiptId !== false &&
    $receiptId !== null &&
    $receiptId > 0
) {

    $receiptStmt = $conn->prepare("
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

        INNER JOIN users u
            ON LOWER(TRIM(u.email)) =
               LOWER(TRIM(b.email))

        WHERE b.id = ?
          AND u.id = ?
        LIMIT 1
    ");

    $receiptStmt->execute([
        $receiptId,
        $currentUserId
    ]);

    $selectedReceipt =
        $receiptStmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedReceipt) {

        $error =
            "Digital receipt not found.";

    } else {

        /*
         * Mark the matching booking notification
         * as read when customer opens the receipt.
         */
        $readReceiptNotification = $conn->prepare("
            SELECT id
            FROM notifications
            WHERE user_id = ?
              AND type = 'booking'
              AND message LIKE ?
            LIMIT 1
        ");

        $readReceiptNotification->execute([
            $currentUserId,
            "%#" . $receiptId . "%"
        ]);

        $matchingNotification =
            $readReceiptNotification->fetchColumn();

        if ($matchingNotification) {

            $updateNotification =
                $conn->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE id = ?
                      AND user_id = ?
                ");

            $updateNotification->execute([
                $matchingNotification,
                $currentUserId
            ]);
        }

    }
}


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


/* ========================================
   DIGITAL RECEIPT DATA
======================================== */

$receiptHours = 0;
$receiptRate = 0;
$receiptTotal = 0;
$receiptDate = "";
$receiptTime = "";
$receiptPaymentMethod = "Cash";
$receiptPaymentReference = "";
$receiptIsGcash = false;

if ($selectedReceipt) {

    $receiptHours =
        (int)$selectedReceipt["hours"];

    $receiptRate =
        (float)$selectedReceipt["price_per_hour"];

    $receiptTotal =
        $receiptHours * $receiptRate;

    $receiptDateTimestamp =
        strtotime(
            (string)$selectedReceipt["booking_date"]
        );

    $receiptTimeTimestamp =
        strtotime(
            (string)$selectedReceipt["start_time"]
        );

    $receiptDate =
        $receiptDateTimestamp !== false
            ? date(
                "F d, Y",
                $receiptDateTimestamp
            )
            : (string)$selectedReceipt["booking_date"];

    $receiptTime =
        $receiptTimeTimestamp !== false
            ? date(
                "h:i A",
                $receiptTimeTimestamp
            )
            : (string)$selectedReceipt["start_time"];

    $receiptPaymentMethod =
        trim(
            (string)(
                $selectedReceipt["payment_method"] ?? ""
            )
        );

    $receiptPaymentReference =
        trim(
            (string)(
                $selectedReceipt["payment_reference"] ?? ""
            )
        );

    if ($receiptPaymentMethod === "") {
        $receiptPaymentMethod = "Cash";
    }

    $receiptIsGcash =
        strcasecmp(
            $receiptPaymentMethod,
            "GCash"
        ) === 0;
}

$csrfToken = csrfToken();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Bais Rouilo Gaming Cafe
    </title>

    <style>

        /* ========================================
           NOTIFICATION BUTTON
        ======================================== */

        .customer-notification-button {
            position: fixed;
            right: 28px;
            bottom: 28px;
            z-index: 900;

            width: 52px;
            height: 52px;

            border: 1px solid #39FF14;
            border-radius: 50%;

            background: #000;
            color: #fff;

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;

            font-size: 21px;

            box-shadow:
                0 0 18px rgba(57,255,20,.18);

            transition: .2s ease;
        }

        .customer-notification-button:hover {
            background: #39FF14;
            color: #000;
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;

            min-width: 19px;
            height: 19px;

            padding: 0 5px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 20px;

            background: #39FF14;
            color: #000;

            font:
                800 8px
                "Orbitron",
                sans-serif;
        }


        /* ========================================
           NOTIFICATION PANEL
        ======================================== */

        .notification-panel {
            position: fixed;
            right: 28px;
            bottom: 91px;

            z-index: 899;

            width: min(410px, calc(100vw - 30px));

            background: #050505;

            border:
                1px solid
                rgba(57,255,20,.55);

            border-radius: 8px;

            box-shadow:
                0 0 30px rgba(0,0,0,.65),
                0 0 18px rgba(57,255,20,.10);

            overflow: hidden;

            opacity: 0;
            visibility: hidden;

            transform:
                translateY(12px);

            transition:
                opacity .2s ease,
                visibility .2s ease,
                transform .2s ease;
        }

        .notification-panel.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;

            padding: 16px 18px;

            border-bottom:
                1px solid
                rgba(57,255,20,.20);
        }

        .notification-panel-title {
            margin: 0;

            color: #fff;

            font:
                800 11px
                "Orbitron",
                sans-serif;
        }

        .notification-mark-all {
            border: 0;
            background: transparent;
            color: #39FF14;

            font:
                700 7px
                "Orbitron",
                sans-serif;

            cursor: pointer;
        }

        .notification-mark-all:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 420px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 16px 18px;

            border-bottom:
                1px solid
                rgba(255,255,255,.07);

            background: #050505;
        }

        .notification-item:last-child {
            border-bottom: 0;
        }

        .notification-item.unread {
            background:
                rgba(57,255,20,.035);
        }

        .notification-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;

            margin-bottom: 7px;
        }

        .notification-item-title {
            color: #39FF14;

            font:
                800 9px
                "Orbitron",
                sans-serif;
        }

        .notification-item-dot {
            width: 6px;
            height: 6px;

            flex: 0 0 auto;

            border-radius: 50%;

            background: #39FF14;
        }

        .notification-item-message {
            margin: 0 0 10px;

            color: #fff;

            font-size: 12px;
            line-height: 1.45;
        }

        .notification-item-date {
            display: block;

            color: #777;

            font-size: 9px;
        }

        .notification-item-actions {
            display: flex;

            gap: 8px;

            align-items: center;

            margin-top: 11px;
        }

        .notification-receipt-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 31px;

            padding: 0 12px;

            border:
                1px solid
                #39FF14;

            background:
                #39FF14;

            color:
                #000;

            border-radius:
                3px;

            font:
                800 7px
                "Orbitron",
                sans-serif;

            text-decoration:
                none;

            transition:
                .2s ease;
        }

        .notification-receipt-button:hover {
            background: transparent;
            color: #39FF14;
        }

        .notification-read-button {
            min-height: 31px;

            padding: 0 12px;

            border:
                1px solid
                #444;

            background:
                transparent;

            color:
                #aaa;

            border-radius:
                3px;

            font:
                700 7px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;
        }

        .notification-read-button:hover {
            border-color: #39FF14;
            color: #39FF14;
        }

        .notification-empty {
            padding: 35px 20px;

            text-align: center;

            color: #777;

            font-size: 12px;
        }


        /* ========================================
           DIGITAL RECEIPT OVERLAY
        ======================================== */

        .digital-receipt-overlay {
            position: fixed;
            inset: 0;

            z-index: 1000;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background:
                rgba(0,0,0,.85);

            opacity: 0;
            visibility: hidden;

            transition:
                opacity .2s ease,
                visibility .2s ease;
        }

        .digital-receipt-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .digital-receipt-card {
            position: relative;

            width: min(650px, 100%);

            max-height: 90vh;

            overflow-y: auto;

            background: #000;

            border:
                1px solid
                #39FF14;

            border-radius: 8px;

            box-shadow:
                0 0 35px
                rgba(57,255,20,.12);
        }

        .digital-receipt-close {
            position: absolute;

            top: 14px;
            right: 14px;

            width: 30px;
            height: 30px;

            border:
                1px solid
                #555;

            border-radius: 50%;

            background:
                transparent;

            color:
                #fff;

            cursor:
                pointer;

            font-size:
                15px;

            z-index: 2;
        }

        .digital-receipt-close:hover {
            border-color: #39FF14;
            color: #39FF14;
        }

        .digital-receipt-header {
            padding: 30px;

            text-align: center;

            border-bottom:
                1px solid
                rgba(57,255,20,.22);
        }

        .digital-receipt-logo {
            width: 95px;

            display:
                block;

            margin:
                0 auto 12px;
        }

        .digital-receipt-business {
            margin: 0;

            color: #fff;

            font:
                700 20px
                "Orbitron",
                sans-serif;
        }

        .digital-receipt-subtitle {
            margin: 7px 0 0;

            color: #39FF14;

            font:
                600 9px
                "Orbitron",
                sans-serif;
        }

        .digital-receipt-body {
            padding:
                24px 30px 30px;
        }

        .digital-receipt-row {
            display: grid;

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

        .digital-receipt-label {
            color:
                rgba(255,255,255,.52);

            font-size:
                10px;

            font-weight:
                700;

            text-transform:
                uppercase;
        }

        .digital-receipt-value {
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

        .digital-receipt-total {
            display: flex;

            align-items: center;
            justify-content: space-between;

            margin-top: 22px;

            padding: 18px 20px;

            border:
                1px solid
                #39FF14;

            background:
                rgba(57,255,20,.04);
        }

        .digital-receipt-total span {
            color: #fff;

            font:
                700 12px
                "Orbitron",
                sans-serif;
        }

        .digital-receipt-total strong {
            color: #39FF14;

            font:
                700 25px
                "Orbitron",
                sans-serif;
        }

        .digital-receipt-payment {
            color: #fff;
        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .customer-notification-button {
                right: 17px;
                bottom: 17px;
            }

            .notification-panel {
                right: 15px;
                bottom: 82px;
            }

            .digital-receipt-overlay {
                padding: 12px;
            }

            .digital-receipt-header {
                padding: 25px 18px;
            }

            .digital-receipt-body {
                padding: 20px;
            }

            .digital-receipt-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .digital-receipt-value {
                text-align: left;
            }

            .digital-receipt-total {
                padding: 15px;
            }

            .digital-receipt-total strong {
                font-size: 20px;
            }

        }


        /* ========================================
           PRINT
        ======================================== */

        @media print {

            @page {
                margin: 0;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;

                background: #000 !important;
                color: #fff !important;
            }

            body > * {
                display: none !important;
            }

            .digital-receipt-overlay {
                display: flex !important;

                position: static !important;

                width: 100% !important;
                height: auto !important;

                padding: 0 !important;

                background: #000 !important;

                opacity: 1 !important;
                visibility: visible !important;
            }

            .digital-receipt-card {
                display: block !important;

                width: 100% !important;
                max-width: 850px !important;

                max-height: none !important;

                margin: 0 auto !important;

                background: #000 !important;
                color: #fff !important;

                border:
                    1px solid
                    #39FF14 !important;

                border-radius: 0 !important;

                box-shadow:
                    none !important;

                overflow: visible !important;
            }

            .digital-receipt-close {
                display: none !important;
            }

            .digital-receipt-header,
            .digital-receipt-body {
                background: #000 !important;
                color: #fff !important;
            }

            .digital-receipt-business,
            .digital-receipt-value,
            .digital-receipt-label,
            .digital-receipt-payment,
            .digital-receipt-total span {
                color: #fff !important;
            }

            .digital-receipt-total {
                background: #000 !important;
                border-color: #39FF14 !important;
            }

            .digital-receipt-total strong {
                color: #39FF14 !important;
            }

        }

    </style>

    <?php
    /*
     * YOUR EXISTING HEADER
     */
    ?>

</head>

<body>


<?php include "includes/header.php"; ?>


<!-- =========================================
     CUSTOMER NOTIFICATION BUTTON
========================================= -->

<button
    type="button"
    class="customer-notification-button"
    id="notificationButton"
    aria-label="Open notifications"
    aria-expanded="false"
>
    🔔

    <?php if ($unreadCount > 0): ?>

        <span class="notification-badge">
            <?= $unreadCount > 99
                ? "99+"
                : $unreadCount
            ?>
        </span>

    <?php endif; ?>

</button>


<!-- =========================================
     CUSTOMER NOTIFICATION PANEL
========================================= -->

<div
    class="notification-panel"
    id="notificationPanel"
>

    <div class="notification-panel-header">

        <h3 class="notification-panel-title">
            NOTIFICATIONS
        </h3>


        <?php if ($unreadCount > 0): ?>

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
                    class="notification-mark-all"
                >
                    MARK ALL READ
                </button>

            </form>

        <?php endif; ?>

    </div>


    <div class="notification-list">

        <?php if (!$notifications): ?>

            <div class="notification-empty">

                No notifications yet.

            </div>

        <?php else: ?>

            <?php foreach ($notifications as $notification): ?>

                <?php

                $notificationId =
                    (int)$notification["id"];

                $notificationType =
                    (string)$notification["type"];

                $notificationTitle =
                    (string)$notification["title"];

                $notificationMessage =
                    (string)$notification["message"];

                $notificationIsUnread =
                    (int)$notification["is_read"] === 0;

                $notificationDate =
                    strtotime(
                        (string)$notification["created_at"]
                    );

                /*
                 * Detect booking ID from message:
                 * "Your booking #15 ..."
                 */
                $notificationBookingId = null;

                if (
                    $notificationType === "booking"
                ) {

                    if (
                        preg_match(
                            '/#(\d+)/',
                            $notificationMessage,
                            $matches
                        )
                    ) {

                        $notificationBookingId =
                            (int)$matches[1];

                    }
                }

                ?>

                <div
                    class="notification-item <?= $notificationIsUnread
                        ? "unread"
                        : ""
                    ?>"
                >

                    <div class="notification-item-top">

                        <div class="notification-item-title">

                            <?= htmlspecialchars(
                                $notificationTitle,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <?php if ($notificationIsUnread): ?>

                            <span
                                class="notification-item-dot"
                            ></span>

                        <?php endif; ?>

                    </div>


                    <p class="notification-item-message">

                        <?= htmlspecialchars(
                            $notificationMessage,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>


                    <?php if ($notificationDate !== false): ?>

                        <span class="notification-item-date">

                            <?= date(
                                "M d, Y h:i A",
                                $notificationDate
                            ) ?>

                        </span>

                    <?php endif; ?>


                    <div class="notification-item-actions">


                        <?php if (
                            $notificationType === "booking" &&
                            $notificationBookingId
                        ): ?>

                            <a
                                href="index.php?receipt=<?= $notificationBookingId ?>"
                                class="notification-receipt-button"
                            >
                                VIEW DIGITAL RECEIPT
                            </a>

                        <?php endif; ?>


                        <?php if ($notificationIsUnread): ?>

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
                                    class="notification-read-button"
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

</div>


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

                        <path
                            d="M32 40v10"
                        ></path>

                        <path
                            d="M20 54h24"
                        ></path>

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

                    <article
                        class="setup-card reveal"
                    >

                        <div class="setup-image">

                            <img
                                src="assets/images/<?= htmlspecialchars(
                                    $setup["image"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $setup["title"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                        </div>


                        <div class="setup-content">

                            <h3>

                                <?= htmlspecialchars(
                                    $setup["title"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </h3>


                            <?php foreach (
                                $setup["specs"]
                                as $spec
                            ): ?>

                                <p>

                                    -

                                    <?= htmlspecialchars(
                                        $spec,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </p>

                            <?php endforeach; ?>


                            <strong>

                                <?= htmlspecialchars(
                                    $setup["price"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

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

                    <article
                        class="rate-card reveal"
                    >


                        <h3>

                            <?= htmlspecialchars(
                                $rate["time"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </h3>


                        <strong>

                            <?= htmlspecialchars(
                                $rate["price"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </strong>


                        <span>

                            <?= htmlspecialchars(
                                $rate["label"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

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


                <?php for (
                    $i = 1;
                    $i <= 4;
                    $i++
                ): ?>

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

    <section
        class="section reviews-section"
    >

        <div class="container">


            <p class="section-kicker">
                WHAT GAMERS SAY
            </p>


            <div class="reviews-grid">


                <?php foreach (
                    $testimonials
                    as $review
                ): ?>

                    <article
                        class="review-card reveal"
                    >


                        <div class="review-stars">
                            ★★★★★
                        </div>


                        <div class="quote">
                            “
                        </div>


                        <h3>

                            "<?= htmlspecialchars(
                                $review["quote"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"

                        </h3>


                        <p>

                            <?= htmlspecialchars(
                                $review["text"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </p>


                        <div class="reviewer">


                            <img
                                src="assets/images/<?= htmlspecialchars(
                                    $review["photo"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $review["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >


                            <span>

                                —

                                <?= htmlspecialchars(
                                    $review["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

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

    <section
        class="cta-section"
    >

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



<!-- =====================================
     TOURNAMENT PAGE
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

            <article
                class="tournament-card reveal"
            >


                <div class="tournament-date">

                    <span>
                        15
                    </span>

                    <small>
                        SEP
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
                    href="tournament.php?id=1"
                    class="outline-button"
                >
                    JOIN NOW
                </a>


            </article>



            <!-- TOURNAMENT 2 -->

            <article
                class="tournament-card reveal"
            >


                <div class="tournament-date">

                    <span>
                        22
                    </span>

                    <small>
                        NOV
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
                    href="tournament.php?id=2"
                    class="outline-button"
                >
                    JOIN NOW
                </a>


            </article>



            <!-- TOURNAMENT 3 -->

            <article
                class="tournament-card reveal"
            >


                <div class="tournament-date">

                    <span>
                        29
                    </span>

                    <small>
                        DEC
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
                    href="tournament.php?id=3"
                    class="outline-button"
                >
                    JOIN NOW
                </a>


            </article>


        </div>

    </div>

</section>



<!-- =====================================
     DIGITAL RECEIPT
====================================== -->

<?php if ($selectedReceipt): ?>

    <div
        class="digital-receipt-overlay open"
        id="digitalReceiptOverlay"
    >

        <div
            class="digital-receipt-card"
        >

            <button
                type="button"
                class="digital-receipt-close"
                id="closeDigitalReceipt"
                aria-label="Close digital receipt"
            >
                ×
            </button>


            <!-- RECEIPT HEADER -->

            <div class="digital-receipt-header">

                <img
                    src="assets/images/logo.png"
                    alt="Bais Rouilo Gaming Cafe"
                    class="digital-receipt-logo"
                >


                <h2 class="digital-receipt-business">

                    BAIS ROUILO
                    GAMING CAFE

                </h2>


                <p class="digital-receipt-subtitle">

                    DIGITAL BOOKING RECEIPT

                </p>

            </div>


            <!-- RECEIPT BODY -->

            <div class="digital-receipt-body">


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Booking ID
                    </span>

                    <span class="digital-receipt-value">

                        #<?= (int)$selectedReceipt["id"] ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Customer
                    </span>

                    <span class="digital-receipt-value">

                        <?= htmlspecialchars(
                            (string)$selectedReceipt[
                                "customer_name"
                            ],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Email
                    </span>

                    <span class="digital-receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $selectedReceipt[
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
                        $selectedReceipt["phone"]
                    )
                ): ?>

                    <div class="digital-receipt-row">

                        <span class="digital-receipt-label">
                            Phone
                        </span>

                        <span class="digital-receipt-value">

                            <?= htmlspecialchars(
                                (string)$selectedReceipt[
                                    "phone"
                                ],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Gaming Setup
                    </span>

                    <span class="digital-receipt-value">

                        <?= htmlspecialchars(
                            (string)(
                                $selectedReceipt[
                                    "setup_name"
                                ] ?? "Unknown"
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Booking Date
                    </span>

                    <span class="digital-receipt-value">

                        <?= htmlspecialchars(
                            $receiptDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Start Time
                    </span>

                    <span class="digital-receipt-value">

                        <?= htmlspecialchars(
                            $receiptTime,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Duration
                    </span>

                    <span class="digital-receipt-value">

                        <?= $receiptHours ?>

                        hour<?= $receiptHours !== 1
                            ? "s"
                            : "" ?>

                    </span>

                </div>


                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Rate / Hour
                    </span>

                    <span class="digital-receipt-value">

                        ₱<?= number_format(
                            $receiptRate,
                            2
                        ) ?>

                    </span>

                </div>


                <!-- MODE OF PAYMENT -->

                <div class="digital-receipt-row">

                    <span class="digital-receipt-label">
                        Mode of Payment
                    </span>

                    <span
                        class="digital-receipt-value digital-receipt-payment"
                    >

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

                    <div class="digital-receipt-row">

                        <span class="digital-receipt-label">
                            GCash Reference Number
                        </span>

                        <span
                            class="digital-receipt-value digital-receipt-payment"
                        >

                            <?= htmlspecialchars(
                                $receiptPaymentReference,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php

                $receiptMessage =
                    trim(
                        (string)(
                            $selectedReceipt[
                                "message"
                            ] ?? ""
                        )
                    );

                ?>


                <?php if (
                    $receiptMessage !== ""
                ): ?>

                    <div class="digital-receipt-row">

                        <span class="digital-receipt-label">
                            Message
                        </span>

                        <span class="digital-receipt-value">

                            <?= htmlspecialchars(
                                $receiptMessage,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- TOTAL -->

                <div class="digital-receipt-total">

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


                <!-- PRINT -->

                <div
                    style="
                        display:flex;
                        justify-content:center;
                        margin-top:18px;
                    "
                >

                    <button
                        type="button"
                        class="outline-button"
                        onclick="window.print()"
                    >
                        PRINT RECEIPT
                    </button>

                </div>


            </div>

        </div>

    </div>

<?php endif; ?>



<!-- =====================================
     NOTIFICATION SCRIPT
====================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* ========================================
           NOTIFICATION PANEL
        ======================================== */

        const notificationButton =
            document.getElementById(
                "notificationButton"
            );

        const notificationPanel =
            document.getElementById(
                "notificationPanel"
            );


        if (
            notificationButton &&
            notificationPanel
        ) {

            notificationButton.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();

                    const isOpen =
                        notificationPanel.classList.toggle(
                            "open"
                        );

                    notificationButton.setAttribute(
                        "aria-expanded",
                        isOpen
                            ? "true"
                            : "false"
                    );

                }
            );


            notificationPanel.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();

                }
            );


            document.addEventListener(
                "click",
                function () {

                    notificationPanel.classList.remove(
                        "open"
                    );

                    notificationButton.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                }
            );

        }


        /* ========================================
           DIGITAL RECEIPT CLOSE
        ======================================== */

        const receiptOverlay =
            document.getElementById(
                "digitalReceiptOverlay"
            );

        const closeReceipt =
            document.getElementById(
                "closeDigitalReceipt"
            );


        if (
            receiptOverlay &&
            closeReceipt
        ) {


            function closeDigitalReceipt() {

                window.location.href =
                    "index.php#home";

            }


            closeReceipt.addEventListener(
                "click",
                closeDigitalReceipt
            );


            receiptOverlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target ===
                        receiptOverlay
                    ) {

                        closeDigitalReceipt();

                    }

                }
            );


            document.addEventListener(
                "keydown",
                function (event) {

                    if (
                        event.key === "Escape"
                    ) {

                        closeDigitalReceipt();

                    }

                }
            );

        }

    }

);

</script>



<?php

include "includes/footer.php";

?>