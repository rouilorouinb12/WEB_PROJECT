<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireAdmin();

$message = "";
$error = "";


/* ========================================
   HANDLE BOOKING ACTION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /* ========================================
           VERIFY CSRF
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
           GET BOOKING ID
        ======================================== */

        $bookingId =
            filter_var(
                $_POST["booking_id"] ?? null,
                FILTER_VALIDATE_INT
            );

        if (
            $bookingId === false ||
            $bookingId <= 0
        ) {

            throw new RuntimeException(
                "Invalid booking."
            );
        }


        /* ========================================
           GET ACTION
        ======================================== */

        $action =
            $_POST["action"] ?? "";

        if (!is_string($action)) {

            throw new RuntimeException(
                "Invalid action."
            );
        }


        if (
            !in_array(
                $action,
                [
                    "accept",
                    "reject"
                ],
                true
            )
        ) {

            throw new RuntimeException(
                "Invalid action."
            );
        }


        /* ========================================
           ACCEPT
           PENDING -> ACCEPTED
           THEN OPEN PROCESSING / RECEIPT
        ======================================== */

        if ($action === "accept") {

            $stmt =
                $conn->prepare("
                    UPDATE bookings
                    SET status = 'accepted'
                    WHERE id = ?
                      AND status = 'pending'
                ");

            $stmt->execute([
                $bookingId
            ]);


            /* ========================================
               CHECK UPDATED STATUS
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


            if (
                $currentStatus === "accepted"
            ) {

                header(
                    "Location: process_booking.php?id="
                    . (int)$bookingId
                );

                exit;

            } elseif (
                $currentStatus === "pending"
            ) {

                $error =
                    "The booking is still pending.";

            } elseif (
                $currentStatus === false
            ) {

                $error =
                    "Booking not found.";

            } else {

                $error =
                    "This booking has already been processed.";
            }
        }


        /* ========================================
           REJECT
           PENDING -> REJECTED
        ======================================== */

        if ($action === "reject") {

            $stmt =
                $conn->prepare("
                    UPDATE bookings
                    SET status = 'rejected'
                    WHERE id = ?
                      AND status = 'pending'
                ");

            $stmt->execute([
                $bookingId
            ]);


            /* ========================================
               CHECK UPDATED STATUS
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


            if (
                $currentStatus === "rejected"
            ) {

                header(
                    "Location: bookings.php?updated=rejected"
                );

                exit;

            } elseif (
                $currentStatus === "pending"
            ) {

                $error =
                    "The booking is still pending.";

            } elseif (
                $currentStatus === false
            ) {

                $error =
                    "Booking not found.";

            } else {

                $error =
                    "This booking has already been processed.";
            }
        }

    } catch (Throwable $e) {

        $error =
            $e->getMessage();
    }
}


/* ========================================
   SUCCESS MESSAGE
======================================== */

if (
    isset($_GET["updated"]) &&
    $_GET["updated"] === "rejected"
) {

    $message =
        "Booking rejected successfully.";
}


/* ========================================
   GET ALL BOOKINGS
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
            b.created_at,
            b.payment_method,
            b.payment_reference,
            b.payment_status,
            gs.name AS setup_name,
            gs.price_per_hour

        FROM bookings b

        LEFT JOIN gaming_setups gs
            ON gs.id = b.setup_id

        ORDER BY

            CASE

                WHEN b.status = 'pending'
                    THEN 1

                WHEN b.status = 'accepted'
                    THEN 2

                WHEN b.status = 'completed'
                    THEN 3

                WHEN b.status = 'rejected'
                    THEN 4

                ELSE 5

            END,

            b.created_at DESC
    ");

$stmt->execute();

$bookings =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* ========================================
   CSRF TOKEN
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
        content="Admin booking management - Bais Rouilo Gaming Cafe."
    >

    <title>
        BOOKINGS | ADMIN | Bais Rouilo Gaming Cafe
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
        href="../assets/css/style.css"
    >


    <style>

        /* ========================================
           ADMIN BOOKINGS
        ======================================== */

        .admin-bookings-wrapper {

            width: 100%;

            max-width: 1400px;

            margin: 0 auto;
        }


        /* ========================================
           TOP ACTION
        ======================================== */

        .admin-top-actions {

            display: flex;

            justify-content: flex-start;

            align-items: center;

            gap: 12px;

            margin-bottom: 25px;
        }


        .admin-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 46px;

            padding: 12px 20px;

            background: #39FF14;

            color: #000;

            text-decoration: none;

            font-family: Orbitron, sans-serif;

            font-size: 10px;

            font-weight: 800;

            border: 1px solid #39FF14;

            border-radius: 3px;

            transition: .2s ease;
        }


        .admin-button:hover {

            background: #39FF14;

            color: #000;

            box-shadow:
                0 0 15px
                rgba(57,255,20,.45);

            transform:
                translateY(-2px);
        }


        /* ========================================
           MESSAGES
        ======================================== */

        .admin-message {

            border:
                1px solid #39FF14;

            background:
                rgba(57,255,20,.08);

            color:
                #39FF14;

            padding:
                15px 18px;

            margin-bottom:
                25px;

            font-weight:
                600;
        }


        .admin-error {

            border:
                1px solid #ff3333;

            background:
                rgba(255,0,0,.08);

            color:
                #ff6666;

            padding:
                15px 18px;

            margin-bottom:
                25px;

            font-weight:
                600;
        }


        /* ========================================
           TABLE
        ======================================== */

        .admin-table-wrap {

            width:
                100%;

            overflow:
                hidden;

            border:
                1px solid
                rgba(57,255,20,.35);

            box-sizing:
                border-box;

            background:
                rgba(0,0,0,.35);
        }


        .admin-table {

            width:
                100%;

            min-width:
                0 !important;

            table-layout:
                fixed;

            border-collapse:
                collapse;

            border-spacing:
                0;
        }


        .admin-table th,
        .admin-table td {

            padding:
                16px 11px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

            text-align:
                left;

            vertical-align:
                middle;

            font-size:
                12px;

            line-height:
                1.4;

            box-sizing:
                border-box;

            overflow-wrap:
                anywhere;
        }


        .admin-table th {

            color:
                #39FF14;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                10px;

            font-weight:
                800;

            white-space:
                nowrap;
        }


        .admin-table td {

            color:
                #fff;
        }


        .admin-table tr:hover {

            background:
                rgba(57,255,20,.03);
        }


        /* ========================================
           COLUMN WIDTHS
        ======================================== */

        .admin-table th:nth-child(1),
        .admin-table td:nth-child(1) {
            width: 5%;
        }


        .admin-table th:nth-child(2),
        .admin-table td:nth-child(2) {
            width: 18%;
        }


        .admin-table th:nth-child(3),
        .admin-table td:nth-child(3) {
            width: 13%;
        }


        .admin-table th:nth-child(4),
        .admin-table td:nth-child(4) {
            width: 10%;
        }


        .admin-table th:nth-child(5),
        .admin-table td:nth-child(5) {
            width: 9%;
        }


        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6) {

            width:
                6%;

            text-align:
                center;
        }


        .admin-table th:nth-child(7),
        .admin-table td:nth-child(7) {
            width: 16%;
        }


        .admin-table th:nth-child(8),
        .admin-table td:nth-child(8) {
            width: 10%;
        }


        .admin-table th:nth-child(9),
        .admin-table td:nth-child(9) {
            width: 13%;
        }


        /* ========================================
           CUSTOMER
        ======================================== */

        .admin-table td strong {

            display:
                block;

            margin-bottom:
                3px;

            font-size:
                13px;

            font-weight:
                700;

            line-height:
                1.3;
        }


        .admin-table td small {

            display:
                block;

            margin-top:
                2px;

            font-size:
                10px;

            line-height:
                1.35;

            overflow-wrap:
                anywhere;

            color:
                rgba(255,255,255,.58);
        }


        /* ========================================
           STATUS
        ======================================== */

        .status {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-width:
                82px;

            padding:
                7px 9px;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            border:
                1px solid;

            white-space:
                nowrap;

            box-sizing:
                border-box;
        }


        .status.pending {

            color:
                #39FF14;

            border-color:
                #39FF14;

            background:
                rgba(57,255,20,.08);
        }


        .status.accepted {

            color:
                #39FF14;

            border-color:
                #39FF14;

            background:
                rgba(57,255,20,.08);
        }


        .status.completed {

            color:
                #39FF14;

            border-color:
                #39FF14;

            background:
                rgba(57,255,20,.15);
        }


        .status.rejected {

            color:
                #ff5555;

            border-color:
                #ff5555;

            background:
                rgba(255,0,0,.08);
        }


        /* ========================================
           BOOKING ACTIONS
        ======================================== */

        .booking-actions {

            display:
                flex;

            flex-direction:
                column;

            gap:
                6px;

            align-items:
                stretch;

            width:
                100%;
        }


        .booking-actions form {

            width:
                100%;

            margin:
                0;

            padding:
                0;
        }


        .booking-action {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            width:
                100%;

            min-width:
                0;

            min-height:
                36px;

            padding:
                7px 5px;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                8px;

            font-weight:
                800;

            cursor:
                pointer;

            border-radius:
                2px;

            box-sizing:
                border-box;

            transition:
                .2s ease;
        }


        /* ========================================
           ACCEPT
        ======================================== */

        .accept-button {

            background:
                #39FF14;

            color:
                #000;

            border:
                1px solid
                #39FF14;
        }


        .accept-button:hover {

            background:
                #39FF14;

            color:
                #000;

            box-shadow:
                0 0 10px
                rgba(57,255,20,.45);
        }


        /* ========================================
           REJECT
        ======================================== */

        .reject-button {

            background:
                transparent;

            color:
                #ff5555;

            border:
                1px solid
                #ff5555;
        }


        .reject-button:hover {

            background:
                #ff5555;

            color:
                #000;
        }


        /* ========================================
           PROCESSING / VIEW RECEIPT
        ======================================== */

        .action-link {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            width:
                100%;

            min-height:
                36px;

            padding:
                7px 5px;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                8px;

            font-weight:
                800;

            white-space:
                nowrap;

            border:
                1px solid
                #39FF14;

            border-radius:
                2px;

            background:
                rgba(57,255,20,.10);

            color:
                #39FF14;

            text-decoration:
                none;

            box-sizing:
                border-box;

            transition:
                .2s ease;
        }


        .action-link:hover {

            background:
                #39FF14;

            color:
                #000;

            box-shadow:
                0 0 10px
                rgba(57,255,20,.45);
        }


        /* ========================================
           REJECTED ACTION
        ======================================== */

        .action-rejected {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            width:
                100%;

            min-height:
                36px;

            padding:
                7px 5px;

            font-family:
                Orbitron,
                sans-serif;

            font-size:
                8px;

            font-weight:
                800;

            white-space:
                nowrap;

            border:
                1px solid
                #ff5555;

            box-sizing:
                border-box;

            color:
                #ff5555;

            background:
                rgba(255,0,0,.08);
        }


        /* ========================================
           EMPTY
        ======================================== */

        .no-bookings {

            text-align:
                center;

            padding:
                50px 20px;

            border:
                1px solid
                rgba(57,255,20,.25);

            color:
                rgba(255,255,255,.6);
        }


        /* ========================================
           LARGE DESKTOP
        ======================================== */

        @media (min-width: 1400px) {

            .admin-bookings-wrapper {
                max-width: 1400px;
            }


            .admin-table th,
            .admin-table td {

                padding:
                    17px 13px;

                font-size:
                    12px;
            }


            .admin-table th {
                font-size: 10px;
            }


            .admin-table td strong {
                font-size: 13px;
            }


            .admin-table td small {
                font-size: 10px;
            }


            .status {
                font-size: 9px;
            }


            .booking-action,
            .action-link,
            .action-rejected {

                min-height:
                    36px;

                font-size:
                    8px;
            }

        }


        /* ========================================
           TABLET
        ======================================== */

        @media (max-width: 1000px) {

            .admin-table th,
            .admin-table td {

                padding:
                    11px 7px;

                font-size:
                    10px;
            }


            .admin-table th {
                font-size: 8px;
            }


            .admin-table td strong {
                font-size: 10px;
            }


            .admin-table td small {
                font-size: 7px;
            }


            .status {

                min-width:
                    64px;

                font-size:
                    7px;

                padding:
                    5px;
            }


            .booking-action,
            .action-link,
            .action-rejected {

                min-height:
                    30px;

                font-size:
                    7px;
            }

        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .admin-table th,
            .admin-table td {

                padding:
                    8px 4px;

                font-size:
                    8px;
            }


            .admin-table th {
                font-size: 6px;
            }


            .admin-table td strong {
                font-size: 8px;
            }


            .admin-table td small {
                font-size: 6px;
            }


            .status {

                min-width:
                    48px;

                padding:
                    4px 3px;

                font-size:
                    5px;
            }


            .booking-action,
            .action-link,
            .action-rejected {

                min-height:
                    26px;

                padding:
                    4px 2px;

                font-size:
                    5px;
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
     MAIN
======================================== -->

<main class="inner-page">

    <div class="container form-page">


        <div class="admin-bookings-wrapper">


            <!-- ========================================
                 PAGE HEADER
            ======================================== -->

            <p class="section-kicker">
                ADMIN PANEL
            </p>


            <h1 class="page-title">

                CUSTOMER

                <span>
                    BOOKINGS
                </span>

            </h1>


            <!-- ========================================
                 DASHBOARD BUTTON
            ======================================== -->

            <div class="admin-top-actions">

                <a
                    href="dashboard.php"
                    class="admin-button"
                >

                    ← DASHBOARD

                </a>

            </div>


            <!-- ========================================
                 SUCCESS
            ======================================== -->

            <?php if ($message !== ""): ?>

                <div class="admin-message">

                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================
                 ERROR
            ======================================== -->

            <?php if ($error !== ""): ?>

                <div class="admin-error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================
                 BOOKINGS TABLE
            ======================================== -->

            <?php if (!$bookings): ?>

                <div class="no-bookings">

                    No customer bookings found.

                </div>

            <?php else: ?>

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
                                    MESSAGE
                                </th>

                                <th>
                                    STATUS
                                </th>

                                <th>
                                    ACTION
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $bookings
                            as $booking
                        ): ?>


                            <?php

                            /* ========================================
                               NORMALIZE STATUS
                            ======================================== */

                            $status =
                                strtolower(
                                    trim(
                                        (string)(
                                            $booking["status"]
                                            ?? ""
                                        )
                                    )
                                );


                            if ($status === "") {

                                $status =
                                    "pending";
                            }


                            $knownStatuses = [

                                "pending",
                                "accepted",
                                "completed",
                                "rejected"

                            ];


                            if (
                                !in_array(
                                    $status,
                                    $knownStatuses,
                                    true
                                )
                            ) {

                                $status =
                                    "pending";
                            }


                            $statusLabel =
                                strtoupper(
                                    $status
                                );

                            ?>


                            <tr>


                                <!-- ========================================
                                     ID
                                ======================================== -->

                                <td>

                                    #<?= (int)
                                        $booking["id"]
                                    ?>

                                </td>


                                <!-- ========================================
                                     CUSTOMER
                                ======================================== -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            (string)(
                                                $booking[
                                                    "customer_name"
                                                ] ?? ""
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>


                                    <small>

                                        <?= htmlspecialchars(
                                            (string)(
                                                $booking[
                                                    "email"
                                                ] ?? ""
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </small>


                                    <?php if (
                                        !empty(
                                            $booking["phone"]
                                        )
                                    ): ?>

                                        <small>

                                            <?= htmlspecialchars(
                                                (string)
                                                $booking["phone"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <!-- ========================================
                                     SETUP
                                ======================================== -->

                                <td>

                                    <?= htmlspecialchars(
                                        (string)(
                                            $booking[
                                                "setup_name"
                                            ]
                                            ?? "Unknown"
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- ========================================
                                     DATE
                                ======================================== -->

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


                                <!-- ========================================
                                     TIME
                                ======================================== -->

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


                                <!-- ========================================
                                     HOURS
                                ======================================== -->

                                <td>

                                    <?= (int)
                                        $booking[
                                            "hours"
                                        ]
                                    ?>

                                </td>


                                <!-- ========================================
                                     MESSAGE
                                ======================================== -->

                                <td>

                                    <?php

                                    $bookingMessage =
                                        trim(
                                            (string)(
                                                $booking[
                                                    "message"
                                                ] ?? ""
                                            )
                                        );

                                    ?>


                                    <?php if (
                                        $bookingMessage !== ""
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $bookingMessage,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:
                                                rgba(
                                                    255,
                                                    255,
                                                    255,
                                                    .35
                                                );
                                            "
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ========================================
                                     STATUS
                                ======================================== -->

                                <td>

                                    <span
                                        class="
                                            status
                                            <?= htmlspecialchars(
                                                $status,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )
                                            ?>
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $statusLabel,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ========================================
                                     ACTION
                                ======================================== -->

                                <td>


                                    <?php if (
                                        $status === "pending"
                                    ): ?>


                                        <div
                                            class="booking-actions"
                                        >


                                            <!-- ACCEPT -->

                                            <form
                                                method="POST"
                                                action="bookings.php"
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
                                                    name="booking_id"
                                                    value="<?= (int)
                                                        $booking["id"]
                                                    ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="accept"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        booking-action
                                                        accept-button
                                                    "
                                                >
                                                    ACCEPT
                                                </button>

                                            </form>


                                            <!-- REJECT -->

                                            <form
                                                method="POST"
                                                action="bookings.php"
                                                onsubmit="
                                                    return confirm(
                                                        'Reject this booking?'
                                                    );
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


                                                <input
                                                    type="hidden"
                                                    name="booking_id"
                                                    value="<?= (int)
                                                        $booking["id"]
                                                    ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="reject"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        booking-action
                                                        reject-button
                                                    "
                                                >
                                                    REJECT
                                                </button>

                                            </form>


                                        </div>


                                    <?php elseif (
                                        $status === "accepted"
                                    ): ?>


                                        <!-- PROCESSING -->

                                        <a
                                            href="process_booking.php?id=<?= (int)
                                                $booking["id"]
                                            ?>"
                                            class="action-link"
                                        >
                                            PROCESSING
                                        </a>


                                    <?php elseif (
                                        $status === "completed"
                                    ): ?>


                                        <!-- VIEW RECEIPT -->

                                        <a
                                            href="process_booking.php?id=<?= (int)
                                                $booking["id"]
                                            ?>&view=1"
                                            class="action-link"
                                        >
                                            VIEW RECEIPT
                                        </a>


                                    <?php elseif (
                                        $status === "rejected"
                                    ): ?>


                                        <span
                                            class="action-rejected"
                                        >
                                            REJECTED
                                        </span>


                                    <?php else: ?>


                                        <span
                                            style="
                                                color:
                                                rgba(
                                                    255,
                                                    255,
                                                    255,
                                                    .5
                                                );
                                            "
                                        >
                                            —
                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>

            <?php endif; ?>


        </div>

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

</script>


</body>

</html>