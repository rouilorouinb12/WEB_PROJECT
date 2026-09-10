<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/*
========================================
CURRENT LOGGED-IN USER
========================================
*/

$currentUserId = currentUserId();

$currentUserRole = $_SESSION["user_role"] ?? "";

$isCustomer = (
    $currentUserId &&
    $currentUserRole !== "admin"
);

$isAdmin = (
    $currentUserId &&
    $currentUserRole === "admin"
);

/*
 * INDEX.PHP IS PUBLIC.
 *
 * Guests are allowed to view the homepage.
 * Login is required only by protected pages/actions such as
 * booking and tournament registration.
 */

$error = "";
$success = "";


/*
========================================
HANDLE NOTIFICATION ACTIONS
========================================
*/

if ($isCustomer && $_SERVER["REQUEST_METHOD"] === "POST") {

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


/*
========================================
NOTIFICATIONS
========================================
*/

$notifications = [];
$unreadCount = 0;

if ($isCustomer) {

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

    foreach ($notifications as $notification) {

        if (
            (int)$notification["is_read"] === 0
        ) {

            $unreadCount++;

        }

    }

}

/*
========================================
DIGITAL RECEIPT
SAME INDEX.PHP
========================================
*/

$selectedReceipt = null;

$receiptId = filter_var(
    $_GET["receipt"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $isCustomer &&
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
        ========================================
        Mark matching booking notification
        as read when customer opens receipt.
        ========================================
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


/*
========================================
GAMING SETUPS
LOAD DIRECTLY FROM DATABASE
========================================
*/

$setups = [];

try {

    $setupStmt = $conn->query("

        SELECT

            id,

            name,

            price_per_hour,

            specs,

            image,

            sort_order

        FROM gaming_setups

        ORDER BY

            sort_order ASC,

            id ASC

    ");

    $dbSetups =
        $setupStmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($dbSetups as $setup) {

        /*
        ========================================
        SETUP TITLE
        ========================================
        */

        $setupTitle =
            trim(
                (string)(
                    $setup["name"] ?? ""
                )
            );


        /*
        ========================================
        SETUP SPECS
        DATABASE STORES SPECS AS MULTIPLE LINES
        ========================================
        */

        $setupSpecs = [];

        $rawSpecs =
            trim(
                (string)(
                    $setup["specs"] ?? ""
                )
            );


        if ($rawSpecs !== "") {

            /*
             * Accept both formats from the database:
             *
             * RTX 4060\n             * Ryzen 5 5600\n             * 16GB RAM\n             * 24*165Hz Monitor
             *
             * and the older single-line format:
             * RTX 4060 • Ryzen 5 5600 • 16GB RAM • 24*165Hz Monitor
             */
            $setupSpecs = preg_split(
                '/(?:\r\n|\r|\n|\s*•\s*)/',
                $rawSpecs
            );

            $setupSpecs = array_values(
                array_filter(
                    array_map(
                        static function ($spec) {
                            // Remove an existing dash so the HTML adds exactly one.
                            return ltrim(trim((string)$spec), "- \t");
                        },
                        $setupSpecs
                    ),
                    static function ($spec) {
                        return $spec !== "";
                    }
                )
            );

        }


        /*
        ========================================
        SETUP IMAGE
        GET ONLY THE FILE NAME
        ========================================
        */

        $setupImage =
            basename(
                (string)(
                    $setup["image"] ?? ""
                )
            );


        /*
        ========================================
        PRICE
        ========================================
        */

        $setupPrice =
            number_format(
                (float)(
                    $setup["price_per_hour"] ?? 0
                ),
                0
            )
            .
            " PHP / HOUR";


        /*
        ========================================
        SAVE FINAL SETUP DATA
        ========================================
        */

        $setups[] = [

            "id" =>
                (int)$setup["id"],

            "title" =>
                $setupTitle,

            "image" =>
                $setupImage,

            "specs" =>
                $setupSpecs,

            "price" =>
                $setupPrice

        ];

    }

} catch (Throwable $e) {

    $setups = [];

}


/*
========================================
RATES
LOAD DIRECTLY FROM DATABASE
========================================
*/

$rates = [];

try {

    $rateStmt = $conn->query("
        SELECT
            id,
            duration,
            price,
            label
        FROM rates
        ORDER BY id ASC
    ");

    $dbRates = $rateStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($dbRates as $rate) {

        $rates[] = [

            "time" =>
                trim(
                    (string)$rate["duration"]
                ),

            "price" =>
                number_format(
                    (float)$rate["price"],
                    0
                ) . " PHP",

            "label" =>
                trim(
                    (string)$rate["label"]
                )

        ];

    }

} catch (Throwable $e) {

    $rates = [];

}


/*
========================================
REVIEWS
========================================
*/

$testimonials = [

    [

        "photo" => "review-1.jpg",

        "quote" => "Best gaming cafe in town!",

        "text" =>
            "Great PCs, affordable rates, and a chill environment.",

        "name" => "Mark V."

    ],

    [

        "photo" => "review-2.jpg",

        "quote" => "Smooth gaming, zero lag!",

        "text" =>
            "The internet speed here is really next level.",

        "name" => "Kyle C."

    ],

    [

        "photo" => "review-3.jpg",

        "quote" =>
            "My go-to place to play and relax.",

        "text" =>
            "Staff are friendly and the place is super nice!",

        "name" => "John D."

    ]

];


/*
========================================
DIGITAL RECEIPT DATA
========================================
*/

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

        $receiptPaymentMethod =
            "Cash";

    }

    $receiptIsGcash =
        strcasecmp(
            $receiptPaymentMethod,
            "GCash"
        ) === 0;

}

/*
========================================
TOURNAMENTS
========================================
*/

$tournamentStmt = $conn->query("
    SELECT
        id,
        title,
        description,
        tournament_date,
        status
    FROM tournaments
    ORDER BY tournament_date ASC, id DESC
");

$tournaments = $tournamentStmt->fetchAll();

$csrfToken = $isCustomer || $isAdmin ? csrfToken() : "";

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

            transform:
                translateY(0);

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

                -webkit-print-color-adjust:
                    exact !important;

                print-color-adjust:
                    exact !important;

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

        /* ========================================
           WHY CHOOSE BR
        ======================================== */

        .why-choose-section {
            padding: 70px 0;
        }

        .why-heading {
            text-align: center;
            margin-bottom: 42px;
        }

        .why-heading h2 {
            margin: 8px 0 0;
            font-family: "Orbitron", sans-serif;
            font-weight: 800;
            line-height: 1.15;
        }

        .why-heading h2 span {
            color: #39FF14;
        }

        .why-choose-section .feature-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 22px;
        }

        .why-choose-section .feature-card {
            text-align: center;
            padding: 8px 5px;
        }

        .why-choose-section .feature-icon svg {
            width: 54px;
            height: 54px;
            fill: none;
            stroke: #39FF14;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .why-choose-section .feature-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            display: grid;
            place-items: center;
            color: #39FF14;
            border: 1px solid rgba(57,255,20,.35);
            border-radius: 50%;
            font-size: 28px;
            line-height: 1;
            box-shadow: 0 0 18px rgba(57,255,20,.08);
        }

        .why-choose-section .feature-card h3 {
            margin: 0 0 9px;
            color: #fff;
            font-family: "Orbitron", sans-serif;
            font-size: 11px;
            font-weight: 800;
            line-height: 1.25;
        }

        .why-choose-section .feature-card p {
            margin: 0;
            color: #ddd;
            font-size: 10px;
            line-height: 1.45;
        }

        @media (max-width: 1100px) {
            .why-choose-section .feature-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .why-choose-section {
                padding: 55px 0;
            }

            .why-choose-section .feature-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }
        }


        /* ========================================
           SNACKS & DRINKS - DRINK CUP ICON
        ======================================== */

        .why-choose-section .drink-feature-icon {
            position: relative;
            overflow: visible;
        }

        .why-choose-section .drink-feature-icon svg {
            width: 54px;
            height: 54px;
            overflow: visible;
            filter:
                drop-shadow(0 0 3px rgba(57,255,20,.8))
                drop-shadow(0 0 6px rgba(57,255,20,.35));
        }

        .why-choose-section .drink-feature-icon svg path {
            fill: none;
            stroke: #39FF14;
            stroke-width: 2.3;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .why-choose-section .drink-feature-icon svg circle {
            fill: #39FF14;
            stroke: none;
        }



        /* ========================================
           TOURNAMENT ADMIN CONTROLS
        ======================================== */

        /* ========================================
           TOURNAMENT PAGE ALIGNMENT
           - kicker centered
           - main title centered
           - add button cleanly anchored to the right
        ======================================== */

        .tournaments-section .section-kicker {
            width: 100%;
            margin: 0 0 12px;
            text-align: center;
        }

        .tournament-heading-row {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 72px;
            margin-bottom: 34px;
        }

        .tournament-heading-row .page-title {
            margin: 0;
            text-align: center;
        }

        .tournament-admin-add {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border: 1px solid #39FF14;
            border-radius: 4px;
            background: #39FF14;
            color: #000;
            font: 800 10px "Orbitron", sans-serif;
            text-decoration: none;
            white-space: nowrap;
            transition: .2s ease;
        }

        .tournament-admin-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(57,255,20,.35);
        }

        .tournament-card-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }

        .tournament-delete-form {
            margin: 0;
            padding: 0;
        }

        .tournament-edit-button,
        .tournament-delete-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 78px;
            min-height: 38px;
            padding: 0 13px;
            border-radius: 4px;
            font: 800 9px "Orbitron", sans-serif;
            text-decoration: none;
            transition: .2s ease;
        }

        .tournament-edit-button {
            border: 1px solid #39FF14;
            background: transparent;
            color: #39FF14;
        }

        .tournament-edit-button:hover {
            background: #39FF14;
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(57,255,20,.30);
        }

        .tournament-delete-button {
            border: 1px solid #ff5757;
            background: transparent;
            color: #ff5757;
        }

        .tournament-delete-button:hover {
            background: #ff5757;
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(255,87,87,.30);
        }

        .tournament-empty {
            border: 1px dashed rgba(57,255,20,.35);
            padding: 55px 25px;
            text-align: center;
        }

        .tournament-empty strong {
            display: block;
            margin-bottom: 8px;
            color: #39FF14;
            font: 800 16px "Orbitron", sans-serif;
        }

        .tournament-empty p {
            margin: 0;
            color: #999;
            font-size: 15px;
        }


        /* ========================================
           TOURNAMENT ADMIN MODALS
        ======================================== */

        .tournament-admin-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0,0,0,.86);
            backdrop-filter: blur(5px);
        }

        .tournament-admin-modal-overlay[hidden] {
            display: none;
        }

        .tournament-admin-modal {
            width: min(520px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            border: 1px solid #39FF14;
            border-radius: 6px;
            background: #050505;
            box-shadow: 0 0 35px rgba(57,255,20,.18);
            animation: tournamentModalIn .18s ease;
        }

        .tournament-admin-modal h2 {
            margin: 0 0 22px;
            color: #39FF14;
            text-align: center;
            font: 800 17px "Orbitron", sans-serif;
        }

        .tournament-admin-form {
            display: grid;
            gap: 15px;
        }

        .tournament-admin-form label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            color: #aaa;
            font: 700 10px "Orbitron", sans-serif;
        }

        .tournament-admin-form input,
        .tournament-admin-form textarea,
        .tournament-admin-form select {
            width: 100%;
            border: 1px solid #292929;
            border-radius: 4px;
            outline: none;
            background: #0b0b0b;
            color: #fff;
            padding: 12px 13px;
            font: 500 14px "Rajdhani", sans-serif;
        }

        .tournament-admin-form textarea {
            min-height: 100px;
            resize: vertical;
        }

        .tournament-admin-form input:focus,
        .tournament-admin-form textarea:focus,
        .tournament-admin-form select:focus {
            border-color: #39FF14;
            box-shadow: 0 0 12px rgba(57,255,20,.08);
        }

        .tournament-admin-modal-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 7px;
        }

        .tournament-modal-cancel,
        .tournament-modal-save,
        .tournament-modal-delete {
            min-width: 120px;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 4px;
            font: 800 9px "Orbitron", sans-serif;
            transition: .2s ease;
        }

        .tournament-modal-cancel {
            border: 1px solid #777;
            background: transparent;
            color: #aaa;
        }

        .tournament-modal-cancel:hover {
            border-color: #fff;
            color: #fff;
        }

        .tournament-modal-save {
            border: 1px solid #39FF14;
            background: #39FF14;
            color: #000;
        }

        .tournament-modal-save:hover,
        .tournament-modal-delete:hover {
            transform: translateY(-2px);
        }

        .tournament-modal-save:hover {
            box-shadow: 0 0 15px rgba(57,255,20,.35);
        }

        .tournament-modal-delete {
            border: 1px solid #ff5757;
            background: #ff5757;
            color: #000;
        }

        .tournament-modal-delete:hover {
            box-shadow: 0 0 15px rgba(255,87,87,.30);
        }

        .tournament-delete-modal p {
            margin: 0 0 25px;
            color: #ddd;
            text-align: center;
            font-size: 15px;
            line-height: 1.5;
        }

        @keyframes tournamentModalIn {
            from {
                opacity: 0;
                transform: scale(.96) translateY(8px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        body.tournament-modal-open {
            overflow: hidden;
        }

        @media (max-width: 900px) {
            .tournament-heading-row {
                min-height: 0;
                flex-direction: column;
                gap: 18px;
                margin-bottom: 30px;
            }

            .tournament-admin-add {
                position: static;
                transform: none;
                width: auto;
            }

            .tournament-card {
                grid-template-columns: 90px 1fr;
            }

            .tournament-card-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
            }
        }

        /* ========================================
           PCS / GAMING SETUPS - FINAL LAYOUT
        ======================================== */

        #pcs {
            width: 100%;
            padding: 56px 0 82px;
        }

        #pcs .setups-container {
            width: min(100% - 80px, 1450px);
            max-width: 1450px;
            margin: 0 auto;
            padding: 0;
        }

        #pcs .setups-kicker {
            margin: 0 0 38px;
            text-align: center;
            font-family: "Orbitron", sans-serif;
            font-size: 14px;
            line-height: 1.2;
            color: #39FF14;
            letter-spacing: .02em;
        }

        #pcs .setup-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 32px;
            align-items: stretch;
        }

        #pcs .setup-card {
            width: 100%;
            min-width: 0;
            height: 450px;
            min-height: 450px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
            background: #050505;
            border: 1px solid #39FF14;
            border-radius: 14px;
            box-sizing: border-box;
            box-shadow: none;
        }

        #pcs .setup-image {
            width: 100%;
            height: 220px;
            min-height: 220px;
            flex: 0 0 220px;
            overflow: hidden;
            background: #000;
        }

        #pcs .setup-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            margin: 0;
            border: 0;
            transition: transform .25s ease;
        }

        #pcs .setup-card:hover .setup-image img {
            transform: scale(1.03);
        }

        #pcs .setup-content {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 25px 28px 27px;
            box-sizing: border-box;
        }

        #pcs .setup-content h3 {
            margin: 0 0 16px;
            color: #fff;
            font-family: "Orbitron", sans-serif;
            font-size: 18px;
            line-height: 1.25;
            font-weight: 700;
        }

        #pcs .setup-content p {
            margin: 0 0 4px;
            color: #fff;
            font-family: "Montserrat", sans-serif;
            font-size: 14px;
            line-height: 1.55;
        }

        #pcs .setup-content strong {
            display: block;
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(57,255,20,.8);
            color: #39FF14;
            font-family: "Orbitron", sans-serif;
            font-size: 16px;
            line-height: 1.2;
            font-weight: 700;
        }

        @media (max-width: 1100px) {
            #pcs .setup-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            #pcs {
                padding: 45px 0 65px;
            }

            #pcs .setups-container {
                width: min(100% - 32px, 1450px);
            }

            #pcs .setups-kicker {
                margin-bottom: 28px;
                font-size: 12px;
            }

            #pcs .setup-grid {
                grid-template-columns: 1fr;
                gap: 22px;
            }

            #pcs .setup-card {
                height: auto;
                min-height: 0;
            }

            #pcs .setup-image {
                height: auto;
                min-height: 0;
                aspect-ratio: 16 / 9;
                flex-basis: auto;
            }
        }

        @media (min-width: 701px) and (max-width: 1100px) {
            #pcs .setup-card {
                height: 440px;
                min-height: 440px;
            }
        }

        @media (max-width: 600px) {
            .tournaments-section .section-kicker {
                margin-bottom: 8px;
            }

            .tournament-heading-row .page-title {
                line-height: 1.05;
            }

            .tournament-admin-add {
                width: 100%;
            }

            .tournament-card {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .tournament-date {
                border-right: none;
                border-bottom: 1px solid rgba(57,255,20,.25);
                padding-bottom: 15px;
            }

            .tournament-card-actions {
                width: 100%;
            }

            .tournament-edit-button,
            .tournament-delete-button,
            .tournament-card-actions .outline-button {
                flex: 1;
            }
        }

    </style>

</head>

<body>


<?php include "includes/header.php"; ?>


<?php if ($isCustomer): ?>

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


<?php endif; ?>


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


                    <?php if (
                        !$currentUserId ||
                        ($_SESSION["user_role"] ?? "") !== "admin"
                    ): ?>

                        <a
                            href="book.php"
                            class="outline-button"
                        >

                            BOOK A PC

                        </a>

                    <?php endif; ?>


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
         WHY CHOOSE BR
    ====================================== -->

    <section
        class="section why-choose-section"
        id="why-choose-br"
    >

        <div class="container">

            <div class="why-heading">

                <p class="section-kicker">
                    WHY CHOOSE BR?
                </p>

                <h2>
                    BUILT FOR GAMERS.<br>
                    DESIGNED TO <span>WIN.</span>
                </h2>

            </div>


            <div class="feature-grid">

                <article class="feature-card reveal">

                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <rect x="10" y="14" width="44" height="30" rx="2"></rect>
                            <path d="M22 54h20"></path>
                            <path d="M32 44v10"></path>
                        </svg>
                    </div>

                    <h3>
                        HIGH-END PCS
                    </h3>

                    <p>
                        Powerful specs for<br>
                        smooth gameplay.
                    </p>

                </article>


                <article class="feature-card reveal">

                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <path d="M12 24c11-11 29-11 40 0"></path>
                            <path d="M20 33c7-7 17-7 24 0"></path>
                            <path d="M28 42c2-2 6-2 8 0"></path>
                            <circle cx="32" cy="50" r="2"></circle>
                        </svg>
                    </div>

                    <h3>
                        ULTRA-FAST<br>
                        INTERNET
                    </h3>

                    <p>
                        Low ping. No lag.<br>
                        Just pure speed.
                    </p>

                </article>


                <article class="feature-card reveal">

                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <path d="M32 8v48"></path>
                            <path d="M8 32h48"></path>
                            <path d="M15 15l34 34"></path>
                            <path d="M49 15L15 49"></path>
                            <circle cx="32" cy="32" r="19"></circle>
                        </svg>
                    </div>

                    <h3>
                        AIR<br>
                        CONDITIONED
                    </h3>

                    <p>
                        Stay cool and<br>
                        focused always.
                    </p>

                </article>


                <article class="feature-card reveal">

                    <div class="feature-icon drink-feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">

                            <!-- STRAW -->
                            <path d="M29 25 L33 13 L43 9"></path>

                            <!-- CUP LID -->
                            <path d="M20 27 Q32 22 44 27"></path>
                            <path d="M19 27 H45"></path>

                            <!-- CUP -->
                            <path d="M21 28 L25 50 Q25.5 53 29 53 H35 Q38.5 53 39 50 L43 28"></path>

                            <!-- DRINK -->
                            <path d="M23 37 Q28 33 32 37 Q36 41 41 37"></path>

                            <!-- BUBBLES -->
                            <circle cx="28" cy="43" r="2" fill="#39FF14" stroke="none"></circle>
                            <circle cx="36" cy="43" r="2" fill="#39FF14" stroke="none"></circle>
                            <circle cx="32" cy="48" r="2" fill="#39FF14" stroke="none"></circle>

                        </svg>
                    </div>

                    <h3>
                        SNACKS &amp;<br>
                        DRINKS
                    </h3>

                    <p>
                        Fuel your game<br>
                        with our menu.
                    </p>

                </article>


                <article class="feature-card reveal">

                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <path d="M14 30c0-8 6-14 14-14h8c8 0 14 6 14 14v13c0 4-3 7-7 7h-4V35H25v15h-4c-4 0-7-3-7-7z"></path>
                            <path d="M25 35h-7"></path>
                            <path d="M46 35h-7"></path>
                            <path d="M29 25h6"></path>
                        </svg>
                    </div>

                    <h3>
                        PREMIUM<br>
                        PERIPHERALS
                    </h3>

                    <p>
                        Comfortable &amp;<br>
                        high-quality gears.
                    </p>

                </article>


                <article class="feature-card reveal">

                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <path d="M20 54h24"></path>
                            <path d="M24 54V22h16v32"></path>
                            <path d="M19 22h26"></path>
                            <path d="M24 16h16"></path>
                            <path d="M28 10h8"></path>
                            <path d="M32 6v4"></path>
                            <path d="M18 38h28"></path>
                        </svg>
                    </div>

                    <h3>
                        TOURNAMENT<br>
                        READY
                    </h3>

                    <p>
                        Join weekly events<br>
                        and claim prices.
                    </p>

                </article>

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


                <?php foreach (
                    $setups
                    as $setup
                ): ?>

                    <article
                        class="setup-card reveal"
                    >

                        <div class="setup-image">

                            <?php if (
                                !empty(
                                    $setup["image"]
                                )
                            ): ?>

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

                            <?php endif; ?>

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

                                    -<?= htmlspecialchars(
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


            <p
                class="section-kicker"
                style="
                    position: relative;
                    top: -35px;
                    z-index: 2;
                "
            >

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


            <p
                class="section-kicker"
                style="
                    position: relative;
                    top: -35px;
                    z-index: 2;
                "
            >

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


                <?php if (
                    !$currentUserId ||
                    ($_SESSION["user_role"] ?? "") !== "admin"
                ): ?>

                    <a
                        href="book.php"
                        class="green-button cta-button"
                    >

                        BOOK NOW

                    </a>

                <?php endif; ?>


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

        <div class="tournament-heading-row">

            <h1 class="page-title">
                COMPETE.
                <span>WIN.</span>
            </h1>

            <?php if ($isAdmin): ?>

                <a
                    href="admin/tournaments.php"
                    class="tournament-admin-add"
                >
                    + ADD TOURNAMENT
                </a>

            <?php endif; ?>

        </div>

        <?php if (!$tournaments): ?>

            <div class="tournament-empty">
                <strong>NO TOURNAMENTS YET</strong>
                <p>
                    <?php if ($isAdmin): ?>
                        Add your first tournament using the button above.
                    <?php else: ?>
                        Check back soon for upcoming tournaments.
                    <?php endif; ?>
                </p>
            </div>

        <?php else: ?>

            <div class="tournament-grid">

                <?php foreach ($tournaments as $tournament): ?>

                    <?php
                    $timestamp = strtotime((string)$tournament["tournament_date"]);
                    $day = date("d", $timestamp);
                    $month = strtoupper(date("M", $timestamp));
                    $tournamentId = (int)$tournament["id"];
                    $title = (string)$tournament["title"];
                    $description = trim((string)($tournament["description"] ?? ""));
                    $status = (string)$tournament["status"];
                    ?>

                    <article class="tournament-card reveal">

                        <div class="tournament-date">
                            <span>
                                <?= htmlspecialchars($day, ENT_QUOTES, "UTF-8") ?>
                            </span>
                            <small>
                                <?= htmlspecialchars($month, ENT_QUOTES, "UTF-8") ?>
                            </small>
                        </div>

                        <div class="tournament-info">
                            <h3>
                                <?= htmlspecialchars($title, ENT_QUOTES, "UTF-8") ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $description !== "" ? $description : "No description provided.",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </p>

                            <span>
                                <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </div>

                        <div class="tournament-card-actions">

                            <?php if ($isAdmin): ?>

                                <button
                                    type="button"
                                    class="tournament-edit-button"
                                    onclick="openTournamentEdit(
                                        <?= $tournamentId ?>,
                                        <?= htmlspecialchars(json_encode($title, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>,
                                        <?= htmlspecialchars(json_encode($description, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>,
                                        <?= htmlspecialchars(json_encode((string)$tournament["tournament_date"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>,
                                        <?= htmlspecialchars(json_encode($status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>
                                    )"
                                >
                                    EDIT
                                </button>

                                <form
                                    method="POST"
                                    action="admin/tournaments.php"
                                    class="tournament-delete-form"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >
                                    <input
                                        type="hidden"
                                        name="tournament_id"
                                        value="<?= $tournamentId ?>"
                                    >
                                    <button
                                        type="button"
                                        class="tournament-delete-button"
                                        onclick="openTournamentDelete(this.form, <?= htmlspecialchars(json_encode($title, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)"
                                    >
                                        DELETE
                                    </button>
                                </form>

                            <?php else: ?>

                                <a
                                    href="tournament.php?id=<?= $tournamentId ?>"
                                    class="outline-button"
                                >
                                    JOIN NOW
                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =====================================
     ADMIN TOURNAMENT EDIT MODAL
====================================== -->

<?php if ($isAdmin): ?>

<div
    class="tournament-admin-modal-overlay"
    id="tournamentEditModal"
    hidden
>
    <div class="tournament-admin-modal" role="dialog" aria-modal="true" aria-labelledby="tournamentEditModalTitle">

        <h2 id="tournamentEditModalTitle">
            EDIT TOURNAMENT
        </h2>

        <form
            method="POST"
            action="admin/tournaments.php"
            class="tournament-admin-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
            >

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="tournament_id"
                id="tournamentEditId"
            >

            <label>
                TOURNAMENT TITLE
                <input
                    type="text"
                    name="title"
                    id="tournamentEditTitle"
                    maxlength="150"
                    required
                >
            </label>

            <label>
                DESCRIPTION
                <textarea
                    name="description"
                    id="tournamentEditDescription"
                    maxlength="500"
                ></textarea>
            </label>

            <label>
                TOURNAMENT DATE
                <input
                    type="date"
                    name="tournament_date"
                    id="tournamentEditDate"
                    required
                >
            </label>

            <label>
                STATUS
                <select
                    name="status"
                    id="tournamentEditStatus"
                >
                    <option value="Registration Open">Registration Open</option>
                    <option value="Team Registration Open">Team Registration Open</option>
                    <option value="Cash Prizes Available">Cash Prizes Available</option>
                    <option value="Coming Soon">Coming Soon</option>
                    <option value="Registration Closed">Registration Closed</option>
                </select>
            </label>

            <div class="tournament-admin-modal-actions">
                <button
                    type="button"
                    class="tournament-modal-cancel"
                    id="tournamentEditCancel"
                >
                    CANCEL
                </button>
                <button
                    type="submit"
                    class="tournament-modal-save"
                >
                    SAVE CHANGES
                </button>
            </div>

        </form>
    </div>
</div>


<!-- =====================================
     ADMIN TOURNAMENT DELETE MODAL
====================================== -->

<div
    class="tournament-admin-modal-overlay"
    id="tournamentDeleteModal"
    hidden
>
    <div class="tournament-admin-modal tournament-delete-modal" role="dialog" aria-modal="true" aria-labelledby="tournamentDeleteModalTitle">

        <h2 id="tournamentDeleteModalTitle">
            DELETE TOURNAMENT
        </h2>

        <p id="tournamentDeleteMessage">
            Are you sure you want to delete this tournament?
        </p>

        <div class="tournament-admin-modal-actions">
            <button
                type="button"
                class="tournament-modal-cancel"
                id="tournamentDeleteCancel"
            >
                CANCEL
            </button>
            <button
                type="button"
                class="tournament-modal-delete"
                id="tournamentDeleteConfirm"
            >
                DELETE
            </button>
        </div>

    </div>
</div>

<?php endif; ?>


<!-- =====================================
     DIGITAL RECEIPT
====================================== -->

<?php if ($isCustomer && $selectedReceipt): ?>

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


        /* ========================================
           PAGE NAVIGATION
        ======================================== */

        const homeContent =
            document.getElementById(
                "homePageContent"
            );


        const tournamentContent =
            document.getElementById(
                "tournaments"
            );


        const mainNav =
            document.getElementById(
                "mainNav"
            );


        const menuToggle =
            document.querySelector(
                ".menu-toggle"
            );


        /* ========================================
           GET NAV LINKS
        ======================================== */

        const navLinks = mainNav
            ? mainNav.querySelectorAll("a")
            : [];


        /* ========================================
           CLEAR ACTIVE NAV
        ======================================== */

        function clearActiveNav() {

            navLinks.forEach(
                function (link) {

                    link.classList.remove(
                        "active"
                    );

                }
            );

        }


        /* ========================================
           SET ACTIVE NAV
        ======================================== */

        function setActiveNav(target) {

            clearActiveNav();


            if (!mainNav) {
                return;
            }


            let activeLink = null;


            if (target === "home") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#home"], a[href="#home"]'
                    );

            }


            else if (target === "pcs") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#pcs"], a[href="#pcs"]'
                    );

            }


            else if (target === "rates") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#rates"], a[href="#rates"]'
                    );

            }


            else if (target === "tournaments") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#tournaments"], a[href="#tournaments"]'
                    );

            }


            else if (target === "gallery") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#gallery"], a[href="#gallery"]'
                    );

            }

            else if (target === "why-choose-br") {

                activeLink =
                    mainNav.querySelector(
                        'a[href="index.php#why-choose-br"], a[href="#why-choose-br"]'
                    );

            }


            if (activeLink) {

                activeLink.classList.add(
                    "active"
                );

            }

        }


        /* ========================================
           SHOW HOME
        ======================================== */

        function showHome() {

            if (homeContent) {

                homeContent.style.display =
                    "block";

            }


            if (tournamentContent) {

                tournamentContent.style.display =
                    "none";

            }

        }


        /* ========================================
           SHOW TOURNAMENTS
        ======================================== */

        function showTournaments() {

            if (homeContent) {

                homeContent.style.display =
                    "none";

            }


            if (tournamentContent) {

                tournamentContent.style.display =
                    "block";

            }


            setActiveNav(
                "tournaments"
            );


            window.scrollTo({

                top: 0,

                behavior: "smooth"

            });

        }


        /* ========================================
           SHOW HOME SECTION
        ======================================== */

        function showHomeSection(
            targetId
        ) {

            showHome();


            setActiveNav(
                targetId
            );


            setTimeout(
                function () {

                    const target =
                        document.getElementById(
                            targetId
                        );


                    if (target) {

                        target.scrollIntoView({

                            behavior: "smooth",

                            block: "start"

                        });

                    }

                },
                50
            );

        }


        /* ========================================
           CHECK URL HASH
        ======================================== */

        const currentHash =
            window.location.hash;


        if (
            currentHash ===
            "#tournaments"
        ) {

            showTournaments();

        }


        else if (
            currentHash ===
            "#pcs"
        ) {

            showHomeSection(
                "pcs"
            );

        }


        else if (
            currentHash ===
            "#rates"
        ) {

            showHomeSection(
                "rates"
            );

        }


        else if (
            currentHash ===
            "#gallery"
        ) {

            showHomeSection(
                "gallery"
            );

        }


        else if (
            currentHash ===
            "#why-choose-br"
        ) {

            showHomeSection(
                "why-choose-br"
            );

        }


        else {

            showHome();

            setActiveNav(
                "home"
            );

        }


        /* ========================================
           TOURNAMENTS BUTTON
        ======================================== */

        const tournamentLinks =
            document.querySelectorAll(
                'a[href="index.php#tournaments"], a[href="#tournaments"]'
            );


        tournamentLinks.forEach(
            function (link) {

                link.addEventListener(
                    "click",
                    function (event) {

                        event.preventDefault();


                        showTournaments();


                        history.pushState(
                            null,
                            "",
                            "index.php#tournaments"
                        );


                        if (mainNav) {

                            mainNav.classList.remove(
                                "open"
                            );

                        }


                        if (menuToggle) {

                            menuToggle.setAttribute(
                                "aria-expanded",
                                "false"
                            );

                        }

                    }
                );

            }
        );


        /* ========================================
           HOME BUTTON
        ======================================== */

        const homeLinks =
            document.querySelectorAll(
                'a[href="index.php#home"], a[href="#home"]'
            );


        homeLinks.forEach(
            function (link) {

                link.addEventListener(
                    "click",
                    function (event) {

                        event.preventDefault();


                        showHome();


                        setActiveNav(
                            "home"
                        );


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

                            mainNav.classList.remove(
                                "open"
                            );

                        }


                        if (menuToggle) {

                            menuToggle.setAttribute(
                                "aria-expanded",
                                "false"
                            );

                        }

                    }
                );

            }
        );


        /* ========================================
           PCS / RATES / GALLERY
        ======================================== */

        const homeSectionLinks =
            document.querySelectorAll(
                'a[href="index.php#pcs"],' +
                'a[href="#pcs"],' +
                'a[href="index.php#rates"],' +
                'a[href="#rates"],' +
                'a[href="index.php#gallery"],' +
                'a[href="#gallery"],' +
                'a[href="index.php#why-choose-br"],' +
                'a[href="#why-choose-br"]'
            );


        homeSectionLinks.forEach(
            function (link) {

                link.addEventListener(
                    "click",
                    function (event) {

                        const href =
                            link.getAttribute(
                                "href"
                            );


                        if (!href) {

                            return;

                        }


                        const parts =
                            href.split("#");


                        if (
                            parts.length < 2
                        ) {

                            return;

                        }


                        const targetId =
                            parts[1];


                        event.preventDefault();


                        showHomeSection(
                            targetId
                        );


                        history.pushState(
                            null,
                            "",
                            "index.php#" +
                            targetId
                        );


                        if (mainNav) {

                            mainNav.classList.remove(
                                "open"
                            );

                        }


                        if (menuToggle) {

                            menuToggle.setAttribute(
                                "aria-expanded",
                                "false"
                            );

                        }

                    }
                );

            }
        );


        /* ========================================
           BROWSER BACK / FORWARD
        ======================================== */

        window.addEventListener(
            "popstate",
            function () {

                const hash =
                    window.location.hash;


                if (
                    hash ===
                    "#tournaments"
                ) {

                    showTournaments();

                }


                else if (
                    hash ===
                    "#pcs"
                ) {

                    showHomeSection(
                        "pcs"
                    );

                }


                else if (
                    hash ===
                    "#rates"
                ) {

                    showHomeSection(
                        "rates"
                    );

                }


                else if (
                    hash ===
                    "#gallery"
                ) {

                    showHomeSection(
                        "gallery"
                    );

                }


                else {

                    showHome();

                    setActiveNav(
                        "home"
                    );

                }

            }
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

    }

);

</script>


<?php if ($isAdmin): ?>
<script>

let tournamentDeleteForm = null;

function openTournamentEdit(id, title, description, tournamentDate, status) {
    const modal = document.getElementById("tournamentEditModal");
    const idInput = document.getElementById("tournamentEditId");
    const titleInput = document.getElementById("tournamentEditTitle");
    const descriptionInput = document.getElementById("tournamentEditDescription");
    const dateInput = document.getElementById("tournamentEditDate");
    const statusInput = document.getElementById("tournamentEditStatus");

    if (!modal) return;

    idInput.value = id;
    titleInput.value = title;
    descriptionInput.value = description;
    dateInput.value = tournamentDate;
    statusInput.value = status || "Registration Open";

    modal.hidden = false;
    document.body.classList.add("tournament-modal-open");

    setTimeout(function () {
        titleInput.focus();
    }, 50);
}

function closeTournamentEdit() {
    const modal = document.getElementById("tournamentEditModal");
    if (modal) modal.hidden = true;

    if (document.getElementById("tournamentDeleteModal")?.hidden !== false) {
        document.body.classList.remove("tournament-modal-open");
    }
}

function openTournamentDelete(form, title) {
    const modal = document.getElementById("tournamentDeleteModal");
    const message = document.getElementById("tournamentDeleteMessage");

    if (!modal || !form) return;

    tournamentDeleteForm = form;

    if (message) {
        message.textContent = 'Are you sure you want to delete "' + title + '"?';
    }

    modal.hidden = false;
    document.body.classList.add("tournament-modal-open");
}

function closeTournamentDelete() {
    const modal = document.getElementById("tournamentDeleteModal");
    if (modal) modal.hidden = true;

    tournamentDeleteForm = null;

    if (document.getElementById("tournamentEditModal")?.hidden !== false) {
        document.body.classList.remove("tournament-modal-open");
    }
}

document.addEventListener("DOMContentLoaded", function () {

    const editModal = document.getElementById("tournamentEditModal");
    const editCancel = document.getElementById("tournamentEditCancel");
    const deleteModal = document.getElementById("tournamentDeleteModal");
    const deleteCancel = document.getElementById("tournamentDeleteCancel");
    const deleteConfirm = document.getElementById("tournamentDeleteConfirm");

    if (editCancel) {
        editCancel.addEventListener("click", closeTournamentEdit);
    }

    if (editModal) {
        editModal.addEventListener("click", function (event) {
            if (event.target === editModal) closeTournamentEdit();
        });
    }

    if (deleteCancel) {
        deleteCancel.addEventListener("click", closeTournamentDelete);
    }

    if (deleteConfirm) {
        deleteConfirm.addEventListener("click", function () {
            if (tournamentDeleteForm instanceof HTMLFormElement) {
                tournamentDeleteForm.submit();
            }
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener("click", function (event) {
            if (event.target === deleteModal) closeTournamentDelete();
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeTournamentEdit();
            closeTournamentDelete();
        }
    });

});

</script>
<?php endif; ?>


<?php

include "includes/footer.php";

?>