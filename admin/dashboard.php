<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireLogin();


/* ========================================
   ADMIN ONLY
======================================== */

if (
    ($_SESSION["user_role"] ?? "customer")
    !== "admin"
) {

    http_response_code(403);

    die("Access denied.");

}


/* ========================================
   CSRF TOKEN
======================================== */

$csrfToken = csrfToken();


/* ========================================
   HANDLE ADMIN CONTACT NOTIFICATIONS
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $notificationAction =
        $_POST["notification_action"] ?? "";

    if (
        $notificationAction
        === "mark_contact_read"
    ) {

        $postedCsrf =
            $_POST["csrf_token"] ?? "";

        if (
            is_string($postedCsrf) &&
            verifyCsrfToken($postedCsrf)
        ) {

            $markContactStmt =
                $conn->prepare("
                    UPDATE notifications n

                    INNER JOIN users u
                        ON u.id = n.user_id

                    SET n.is_read = 1

                    WHERE n.type = 'contact'

                      AND n.is_read = 0

                      AND u.role = 'admin'
                ");

            $markContactStmt->execute();

        }

    }

}


/* ========================================
   GET RECENT BOOKINGS
======================================== */

$stmt = $conn->prepare("

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

        b.created_at,

        gs.name AS setup_name

    FROM bookings b

    LEFT JOIN gaming_setups gs

        ON gs.id = b.setup_id

    ORDER BY b.created_at DESC

");

$stmt->execute();

$bookings =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* ========================================
   COUNT BOOKINGS
======================================== */

$countStmt = $conn->query("

    SELECT

        COUNT(*) AS total,

        SUM(status = 'pending') AS pending,

        SUM(status = 'accepted') AS accepted,

        SUM(status = 'rejected') AS rejected

    FROM bookings

");

$counts =
    $countStmt->fetch(
        PDO::FETCH_ASSOC
    );


$totalBookings =
    (int)(
        $counts["total"] ?? 0
    );


$pendingBookings =
    (int)(
        $counts["pending"] ?? 0
    );


$acceptedBookings =
    (int)(
        $counts["accepted"] ?? 0
    );


$rejectedBookings =
    (int)(
        $counts["rejected"] ?? 0
    );


/* ========================================
   CALCULATE TOTAL INCOME
   Only completed bookings are counted.
   Income = booked hours × setup price/hour.
======================================== */
$totalIncomeStmt = $conn->query("
    SELECT
        COALESCE(
            SUM(b.hours * COALESCE(gs.price_per_hour, 0)),
            0
        ) AS total_income
    FROM bookings b
    LEFT JOIN gaming_setups gs
        ON gs.id = b.setup_id
    WHERE b.status = 'completed'
");

$totalIncome = (float)(
    $totalIncomeStmt->fetchColumn()
    ?? 0
);


/* ========================================
   GET CONTACT MESSAGES
   CUSTOMER -> CONTACT -> ADMIN
======================================== */

$contactStmt = $conn->query("

    SELECT

        id,

        name,

        email,

        phone,

        message,

        created_at

    FROM contacts

    ORDER BY created_at DESC

");

$contactMessages =
    $contactStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* ========================================
   COUNT CONTACT MESSAGES
======================================== */

$contactCountStmt =
    $conn->query("

        SELECT COUNT(*) AS total

        FROM contacts

    ");

$contactCount =
    (int)(
        $contactCountStmt->fetchColumn()
        ?? 0
    );


/* ========================================
   ADMIN CONTACT NOTIFICATIONS
======================================== */

$adminNotificationStmt =
    $conn->query("

        SELECT

            n.id,

            n.type,

            n.title,

            n.message,

            n.is_read,

            n.created_at

        FROM notifications n

        INNER JOIN users u

            ON u.id = n.user_id

        WHERE u.role = 'admin'

          AND n.type = 'contact'

        ORDER BY

            n.is_read ASC,

            n.created_at DESC

        LIMIT 20

    ");

$adminNotifications =
    $adminNotificationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* ========================================
   UNREAD ADMIN CONTACT NOTIFICATIONS
======================================== */

$unreadContactCountStmt =
    $conn->query("

        SELECT COUNT(*)

        FROM notifications n

        INNER JOIN users u

            ON u.id = n.user_id

        WHERE u.role = 'admin'

          AND n.type = 'contact'

          AND n.is_read = 0

    ");

$unreadContactCount =
    (int)(
        $unreadContactCountStmt->fetchColumn()
        ?? 0
    );

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
        content="Bais Rouilo Gaming Cafe Admin Dashboard."
    >


    <title>

        ADMIN DASHBOARD | Bais Rouilo Gaming Cafe

    </title>


    <!-- ========================================
         GOOGLE FONTS
    ======================================== -->

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


    <!-- ========================================
         MAIN CSS
    ======================================== -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <style>


        /* ========================================
           ADMIN STATS
        ======================================== */

        .admin-stats {

            display:
                grid;

            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap:
                20px;

            margin:
                30px 0;
        }


        .admin-stat {

            border:
                1px solid
                rgba(57,255,20,.35);

            background:
                rgba(0,0,0,.65);

            padding:
                25px;

            text-align:
                center;
        }


        .admin-stat h3 {

            margin:
                0;

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                30px;
        }


        /* Keep the income amount inside the card */
        .admin-stat.income-stat h3 {

            font-size: 22px;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: -0.5px;
        }


        .admin-stat p {

            margin:
                8px 0 0;

            font-size:
                12px;

            font-weight:
                700;
        }


        /* ========================================
           ADMIN ACTION BUTTONS
        ======================================== */

        .admin-actions {

            display:
                grid !important;

            grid-template-columns:
                repeat(4, minmax(0, 1fr)) !important;

            gap:
                12px !important;

            width:
                100% !important;

            margin:
                0 0 25px 0 !important;

            padding:
                0 !important;

            align-items:
                stretch !important;
        }


        .admin-actions
        .admin-button {

            display:
                flex !important;

            align-items:
                center !important;

            justify-content:
                center !important;

            width:
                100% !important;

            height:
                60px !important;

            min-height:
                60px !important;

            min-width:
                0 !important;

            padding:
                6px 10px !important;

            margin:
                0 !important;

            box-sizing:
                border-box !important;

            background:
                #39FF14 !important;

            color:
                #000 !important;

            border:
                1px solid
                #39FF14 !important;

            border-radius:
                3px !important;

            text-decoration:
                none !important;

            text-align:
                center !important;

            font-family:
                Orbitron,
                sans-serif !important;

            font-size:
                10px !important;

            font-weight:
                800 !important;

            line-height:
                1.25 !important;

            white-space:
                normal !important;

            overflow:
                hidden !important;

            overflow-wrap:
                break-word !important;

            transition:
                .2s ease !important;
        }


        .admin-actions
        .admin-button:hover {

            background:
                #39FF14 !important;

            color:
                #000 !important;

            box-shadow:
                0 0 18px
                rgba(57,255,20,.50)
                !important;

            transform:
                translateY(-2px);

        }


        .admin-actions
        .tournament-button {

            padding-left:
                8px !important;

            padding-right:
                8px !important;
        }


        /* ========================================
           ADMIN TABLE
        ======================================== */

        .admin-table-wrap {

            overflow-x:
                auto;

            border:
                1px solid
                rgba(57,255,20,.3);
        }


        .admin-table {

            width:
                100%;

            border-collapse:
                collapse;

            min-width:
                900px;
        }


        .admin-table th,
        .admin-table td {

            padding:
                14px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

            text-align:
                left;

            font-size:
                12px;
        }


        .admin-table th {

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                10px;
        }


        .admin-table td {

            color:
                #fff;
        }


        /* ========================================
           BOOKING STATUS
        ======================================== */

        .status {

            display:
                inline-block;

            padding:
                5px 9px;

            font-size:
                10px;

            font-weight:
                800;

            text-transform:
                uppercase;

            border:
                1px solid
                rgba(255,255,255,.2);
        }


        .status.pending {

            color:
                #fff;
        }


        .status.accepted {

            color:
                #39FF14;
        }


        .status.rejected {

            color:
                #ff4d4d;
        }


        .status.completed {

            color:
                #39FF14;
        }


        /* ========================================
           CONTACT MESSAGES
        ======================================== */

        .contact-messages-section {

            margin-top:
                45px;
        }


        .contact-message-header {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;

            flex-wrap:
                wrap;

            margin-bottom:
                20px;
        }


        .contact-message-count {

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                11px;

            font-weight:
                800;

            border:
                1px solid
                rgba(57,255,20,.35);

            padding:
                9px 13px;
        }


        .contact-message-card {

            border:
                1px solid
                rgba(57,255,20,.35);

            background:
                rgba(0,0,0,.75);

            padding:
                22px;

            margin-bottom:
                15px;

            border-radius:
                6px;
        }


        .contact-message-card:hover {

            border-color:
                rgba(57,255,20,.65);

            box-shadow:
                0 0 18px
                rgba(57,255,20,.06);
        }


        .contact-message-top {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                20px;

            flex-wrap:
                wrap;

            margin-bottom:
                18px;
        }


        .contact-customer-name {

            color:
                #fff;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                15px;

            font-weight:
                800;
        }


        .contact-customer-email {

            color:
                #39FF14;

            margin-top:
                5px;

            font-size:
                11px;

            word-break:
                break-word;
        }


        .contact-message-date {

            color:
                rgba(255,255,255,.55);

            font-size:
                10px;

            text-align:
                right;

            white-space:
                nowrap;
        }


        .contact-field {

            margin-top:
                14px;
        }


        .contact-label {

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                9px;

            font-weight:
                800;

            margin-bottom:
                6px;
        }


        .contact-value {

            color:
                #fff;

            font-size:
                12px;

            line-height:
                1.6;

            word-break:
                break-word;
        }


        .contact-message-text {

            border:
                1px solid
                rgba(255,255,255,.10);

            background:
                rgba(255,255,255,.02);

            padding:
                13px;

            border-radius:
                4px;

            white-space:
                pre-wrap;
        }


        .no-contact-messages {

            border:
                1px solid
                rgba(57,255,20,.25);

            background:
                rgba(0,0,0,.65);

            padding:
                25px;

            text-align:
                center;

            color:
                rgba(255,255,255,.55);

            font-size:
                12px;
        }


        /* ========================================
           ADMIN NOTIFICATION BUTTON
        ======================================== */

        .admin-notification-button {

            position:
                fixed;

            right:
                28px;

            bottom:
                28px;

            z-index:
                1000;

            width:
                52px;

            height:
                52px;

            border:
                1px solid
                #39FF14;

            border-radius:
                50%;

            background:
                #000;

            color:
                #fff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            cursor:
                pointer;

            font-size:
                21px;

            box-shadow:
                0 0 18px
                rgba(57,255,20,.18);

            transition:
                .2s ease;
        }


        .admin-notification-button:hover {

            background:
                #39FF14;

            color:
                #000;

            transform:
                translateY(-2px);
        }


        .admin-notification-badge {

            position:
                absolute;

            top:
                -4px;

            right:
                -4px;

            min-width:
                19px;

            height:
                19px;

            padding:
                0 5px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                20px;

            background:
                #39FF14;

            color:
                #000;

            font:
                800 8px
                "Orbitron",
                sans-serif;
        }


        /* ========================================
           ADMIN NOTIFICATION PANEL
        ======================================== */

        .admin-notification-panel {

            position:
                fixed;

            right:
                28px;

            bottom:
                91px;

            z-index:
                999;

            width:
                min(410px, calc(100vw - 30px));

            background:
                #050505;

            border:
                1px solid
                rgba(57,255,20,.55);

            border-radius:
                8px;

            box-shadow:
                0 0 30px
                rgba(0,0,0,.65),
                0 0 18px
                rgba(57,255,20,.10);

            overflow:
                hidden;

            opacity:
                0;

            visibility:
                hidden;

            transform:
                translateY(12px);

            transition:
                opacity .2s ease,
                visibility .2s ease,
                transform .2s ease;
        }


        .admin-notification-panel.open {

            opacity:
                1;

            visibility:
                visible;

            transform:
                translateY(0);
        }


        .admin-notification-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;

            padding:
                16px 18px;

            border-bottom:
                1px solid
                rgba(57,255,20,.20);
        }


        .admin-notification-title {

            margin:
                0;

            color:
                #fff;

            font:
                800 11px
                "Orbitron",
                sans-serif;
        }


        .admin-mark-read {

            border:
                0;

            background:
                transparent;

            color:
                #39FF14;

            font:
                700 7px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;
        }


        .admin-mark-read:hover {

            text-decoration:
                underline;
        }


        .admin-notification-list {

            max-height:
                420px;

            overflow-y:
                auto;
        }


        .admin-notification-item {

            padding:
                16px 18px;

            border-bottom:
                1px solid
                rgba(255,255,255,.07);

            background:
                #050505;
        }


        .admin-notification-item:last-child {

            border-bottom:
                0;
        }


        .admin-notification-item.unread {

            background:
                rgba(57,255,20,.035);
        }


        .admin-notification-item-title {

            color:
                #39FF14;

            font:
                800 9px
                "Orbitron",
                sans-serif;

            margin-bottom:
                7px;
        }


        .admin-notification-item-message {

            color:
                #fff;

            font-size:
                12px;

            line-height:
                1.45;

            margin:
                0 0 8px;
        }


        .admin-notification-item-date {

            color:
                #777;

            font-size:
                9px;
        }


        .admin-view-contact {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                31px;

            padding:
                0 12px;

            margin-top:
                11px;

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
        }


        .admin-view-contact:hover {

            background:
                transparent;

            color:
                #39FF14;
        }


        .admin-notification-empty {

            padding:
                35px 20px;

            text-align:
                center;

            color:
                #777;

            font-size:
                12px;
        }


        /* ========================================
           CONTACTS LINK
        ======================================== */

        .contacts-anchor {

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                9px;

            font-weight:
                800;

            text-decoration:
                none;

            border:
                1px solid
                rgba(57,255,20,.35);

            padding:
                8px 12px;

            transition:
                .2s ease;
        }


        .contacts-anchor:hover {

            background:
                #39FF14;

            color:
                #000;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 900px) {

            .admin-actions {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr))
                    !important;
            }

        }


        @media (max-width: 1100px) {

            .admin-stats {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }

        }


        @media (max-width: 800px) {

            .admin-stats {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 500px) {

            .admin-stat.income-stat h3 {
                font-size: 24px;
            }


            .admin-stats {

                grid-template-columns:
                    1fr;
            }


            .admin-actions {

                grid-template-columns:
                    1fr !important;
            }


            .admin-actions
            .admin-button {

                width:
                    100% !important;
            }


            .contact-message-date {

                text-align:
                    left;
            }


            .admin-notification-button {

                right:
                    17px;

                bottom:
                    17px;
            }


            .admin-notification-panel {

                right:
                    15px;

                bottom:
                    82px;
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
            href="../index.php"
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


        <!-- ====================================
             ADMIN HEADER NAVIGATION
        ==================================== -->
<nav 

    class="main-nav" id="mainNav">

    <a href="bookings.php">
        BOOKINGS
    </a>

    <a href="dashboard.php" class="active">
        DASHBOARD
    </a>

    <a href="../index.php">
        WEBSITE
    </a>

    <a href="../profile.php" class="nav-button">
        PROFILE
    </a>

</nav>

    </div>

</header>


<!-- ========================================
     ADMIN NOTIFICATION BUTTON
======================================== -->

<button
    type="button"
    class="admin-notification-button"
    id="adminNotificationButton"
    aria-label="Open admin notifications"
    aria-expanded="false"
>

    🔔


    <?php if ($unreadContactCount > 0): ?>

        <span class="admin-notification-badge">

            <?= $unreadContactCount > 99
                ? "99+"
                : $unreadContactCount
            ?>

        </span>

    <?php endif; ?>

</button>


<!-- ========================================
     ADMIN NOTIFICATION PANEL
======================================== -->

<div
    class="admin-notification-panel"
    id="adminNotificationPanel"
>


    <div class="admin-notification-header">


        <h3 class="admin-notification-title">

            ADMIN NOTIFICATIONS

        </h3>


        <?php if ($unreadContactCount > 0): ?>

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
                    value="mark_contact_read"
                >


                <button
                    type="submit"
                    class="admin-mark-read"
                >

                    MARK ALL READ

                </button>

            </form>

        <?php endif; ?>


    </div>


    <div class="admin-notification-list">


        <?php if (!$adminNotifications): ?>


            <div
                class="admin-notification-empty"
            >

                No notifications yet.

            </div>


        <?php else: ?>


            <?php foreach (
                $adminNotifications
                as $adminNotification
            ): ?>


                <?php

                $notificationUnread =
                    (int)$adminNotification[
                        "is_read"
                    ] === 0;

                $notificationDate =
                    strtotime(
                        (string)
                        $adminNotification[
                            "created_at"
                        ]
                    );

                ?>


                <div
                    class="
                        admin-notification-item
                        <?= $notificationUnread
                            ? "unread"
                            : ""
                        ?>
                    "
                >


                    <div
                        class="admin-notification-item-title"
                    >

                        <?= htmlspecialchars(
                            (string)
                            $adminNotification[
                                "title"
                            ],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>


                    <p
                        class="admin-notification-item-message"
                    >

                        <?= htmlspecialchars(
                            (string)
                            $adminNotification[
                                "message"
                            ],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>


                    <?php if (
                        $notificationDate !== false
                    ): ?>

                        <div
                            class="admin-notification-item-date"
                        >

                            <?= date(
                                "M d, Y h:i A",
                                $notificationDate
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <a
                        href="#contact-messages"
                        class="admin-view-contact"
                        data-contact-link="true"
                    >

                        VIEW CONTACTS

                    </a>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>

</div>


<!-- ========================================
     ADMIN DASHBOARD
======================================== -->

<main class="inner-page">

    <div class="container form-page">


        <!-- ====================================
             PAGE TITLE
        ==================================== -->

        <p class="section-kicker">

            ADMIN PANEL

        </p>


        <h1 class="page-title">

            <span>

                DASHBOARD

            </span>

        </h1>


        <!-- ====================================
             BOOKING STATISTICS
        ==================================== -->

        <div class="admin-stats">


            <div class="admin-stat">

                <h3>

                    <?= $totalBookings ?>

                </h3>


                <p>

                    TOTAL BOOKINGS

                </p>

            </div>


            <div class="admin-stat">

                <h3>

                    <?= $pendingBookings ?>

                </h3>


                <p>

                    PENDING

                </p>

            </div>


            <div class="admin-stat">

                <h3>

                    <?= $acceptedBookings ?>

                </h3>


                <p>

                    ACCEPTED

                </p>

            </div>


            <div class="admin-stat">

                <h3>

                    <?= $rejectedBookings ?>

                </h3>


                <p>

                    REJECTED

                </p>

            </div>


            <!-- TOTAL INCOME -->
            <div class="admin-stat income-stat">

                <h3>

                    ₱<?= number_format($totalIncome, 2) ?>

                </h3>

                <p>

                    TOTAL INCOME

                </p>

            </div>


        </div>


        <!-- ====================================
             ADMIN ACTION BUTTONS
        ==================================== -->

        <div class="admin-actions">


            <!-- CUSTOMER REVIEWS -->

            <a
                href="reviews.php"
                class="admin-button"
            >

                CUSTOMER REVIEWS

            </a>


            <!-- TOURNAMENT REGISTRATIONS -->

            <a
                href="tournament_registrations.php"
                class="admin-button tournament-button"
            >

                TOURNAMENT REGISTRATIONS

            </a>


            <!-- MANAGE SETUPS -->

            <a
                href="setups.php"
                class="admin-button"
            >

                MANAGE SETUPS

            </a>


            <!-- MANAGE RATES -->

            <a
                href="rates.php"
                class="admin-button"
            >

                MANAGE RATES

            </a>


        </div>


        <!-- ====================================
             RECENT BOOKINGS TABLE
        ==================================== -->

        <div class="admin-table-wrap">

            <table class="admin-table">


                <thead>

                    <tr>

                        <th>

                            ID

                        </th>


                        <th>

                            CUSTOMER

                        </th>


                        <th>

                            SETUP

                        </th>


                        <th>

                            DATE

                        </th>


                        <th>

                            TIME

                        </th>


                        <th>

                            HOURS

                        </th>


                        <th>

                            STATUS

                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (!$bookings): ?>


                        <tr>

                            <td colspan="7">

                                No bookings found.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $bookings
                            as $booking
                        ): ?>


                            <tr>


                                <td>

                                    #<?= (int)
                                        $booking["id"]
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            (string)
                                            $booking[
                                                "customer_name"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>


                                    <br>


                                    <?= htmlspecialchars(
                                        (string)
                                        (
                                            $booking["email"]
                                            ?? ""
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        (
                                            $booking[
                                                "setup_name"
                                            ]
                                            ?? "Unknown"
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        $booking[
                                            "booking_date"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        $booking[
                                            "start_time"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <td>

                                    <?= (int)
                                        $booking["hours"]
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="status <?= htmlspecialchars(
                                            (string)
                                            $booking[
                                                "status"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            (string)
                                            $booking[
                                                "status"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>


        <!-- ========================================
             CONTACT MESSAGES
        ======================================== -->

        <section
            class="contact-messages-section"
            id="contact-messages"
        >


            <div class="contact-message-header">


                <div>

                    <p class="section-kicker">

                        CUSTOMER INBOX

                    </p>


                    <h2
                        class="section-heading"
                        style="margin-bottom:0;"
                    >

                        CONTACT

                        <span>

                            MESSAGES

                        </span>

                    </h2>

                </div>


                <a
                    href="#contact-messages"
                    class="contacts-anchor"
                >

                    CONTACTS
                    (<?= $contactCount ?>)

                </a>


            </div>


            <?php if (
                !$contactMessages
            ): ?>


                <div class="no-contact-messages">

                    No customer messages yet.

                </div>


            <?php else: ?>


                <?php foreach (
                    $contactMessages
                    as $contact
                ): ?>


                    <div
                        class="contact-message-card"
                    >


                        <div
                            class="contact-message-top"
                        >


                            <div>


                                <div
                                    class="
                                        contact-customer-name
                                    "
                                >

                                    <?= htmlspecialchars(
                                        (string)
                                        $contact["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $contact["email"]
                                    )
                                ): ?>


                                    <div
                                        class="
                                            contact-customer-email
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            (string)
                                            $contact[
                                                "email"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                            </div>


                            <div
                                class="
                                    contact-message-date
                                "
                            >

                                <?= htmlspecialchars(
                                    date(
                                        "F d, Y h:i A",
                                        strtotime(
                                            (string)
                                            $contact[
                                                "created_at"
                                            ]
                                        )
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>


                        </div>


                        <?php if (
                            !empty(
                                $contact["phone"]
                            )
                        ): ?>


                            <div
                                class="contact-field"
                            >

                                <div
                                    class="
                                        contact-label
                                    "
                                >

                                    PHONE

                                </div>


                                <div
                                    class="
                                        contact-value
                                    "
                                >

                                    <?= htmlspecialchars(
                                        (string)
                                        $contact["phone"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>


                        <?php endif; ?>


                        <div
                            class="contact-field"
                        >

                            <div
                                class="
                                    contact-label
                                "
                            >

                                CUSTOMER MESSAGE

                            </div>


                            <div
                                class="
                                    contact-value
                                    contact-message-text
                                "
                            >

                                <?= htmlspecialchars(
                                    (string)
                                    $contact["message"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </section>


    </div>

</main>


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


/* ========================================
   ADMIN NOTIFICATION
======================================== */

const adminNotificationButton =
    document.getElementById(
        "adminNotificationButton"
    );


const adminNotificationPanel =
    document.getElementById(
        "adminNotificationPanel"
    );


if (
    adminNotificationButton &&
    adminNotificationPanel
) {


    adminNotificationButton.addEventListener(
        "click",
        function (event) {

            event.stopPropagation();


            const isOpen =
                adminNotificationPanel.classList.toggle(
                    "open"
                );


            adminNotificationButton.setAttribute(
                "aria-expanded",
                isOpen
                    ? "true"
                    : "false"
            );

        }
    );


    adminNotificationPanel.addEventListener(
        "click",
        function (event) {

            event.stopPropagation();

        }
    );


    document.addEventListener(
        "click",
        function () {

            adminNotificationPanel.classList.remove(
                "open"
            );


            adminNotificationButton.setAttribute(
                "aria-expanded",
                "false"
            );

        }
    );

}


/* ========================================
   VIEW CONTACTS
======================================== */

const contactLinks =
    document.querySelectorAll(
        '[data-contact-link="true"], .contacts-anchor'
    );


const contactMessages =
    document.getElementById(
        "contact-messages"
    );


contactLinks.forEach(
    function (link) {

        link.addEventListener(
            "click",
            function (event) {

                event.preventDefault();


                if (adminNotificationPanel) {

                    adminNotificationPanel.classList.remove(
                        "open"
                    );

                }


                if (adminNotificationButton) {

                    adminNotificationButton.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                }


                if (contactMessages) {

                    contactMessages.scrollIntoView({

                        behavior: "smooth",

                        block: "start"

                    });

                }

            }
        );

    }
);

</script>


</body>

</html>