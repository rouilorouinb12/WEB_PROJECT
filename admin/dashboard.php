<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireLogin();


/* ========================================
   ADMIN ONLY
======================================== */

if (($_SESSION["user_role"] ?? "customer") !== "admin") {
    http_response_code(403);
    die("Access denied.");
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

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalBookings =
    (int) ($counts["total"] ?? 0);

$pendingBookings =
    (int) ($counts["pending"] ?? 0);

$acceptedBookings =
    (int) ($counts["accepted"] ?? 0);

$rejectedBookings =
    (int) ($counts["rejected"] ?? 0);

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
           ADMIN STATS
        ======================================== */

        .admin-stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin: 30px 0;

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
           SIX BUTTONS - ONE LINE
        ======================================== */

        .admin-actions {

            display:
                grid !important;

            grid-template-columns:
                repeat(6, minmax(0, 1fr)) !important;

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


        /* ========================================
           BUTTONS
           SAME SIZE
        ======================================== */

        .admin-actions .admin-button {

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
                6px 8px !important;

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
                9px !important;

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

            word-break:
                normal !important;

            transition:
                .2s ease !important;

        }


        .admin-actions .admin-button:hover {

            background:
                #39FF14 !important;

            color:
                #000 !important;

            box-shadow:
                0 0 18px
                rgba(57,255,20,.50) !important;

            transform:
                translateY(-2px);

        }


        /* ========================================
           TOURNAMENT BUTTON
           LONG TEXT
        ======================================== */

        .admin-actions .tournament-button {

            padding-left:
                5px !important;

            padding-right:
                5px !important;

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


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 1100px) {

            .admin-actions {

                grid-template-columns:
                    repeat(6, 125px)
                    !important;

                overflow-x:
                    auto !important;

                padding-bottom:
                    5px !important;

            }


            .admin-actions .admin-button {

                width:
                    125px !important;

            }

        }


        @media (max-width: 800px) {

            .admin-stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .admin-actions {

                grid-template-columns:
                    repeat(6, 120px)
                    !important;

            }


            .admin-actions .admin-button {

                width:
                    120px !important;

            }

        }


        @media (max-width: 500px) {

            .admin-stats {

                grid-template-columns:
                    1fr;

            }


            .admin-actions {

                grid-template-columns:
                    repeat(6, 115px)
                    !important;

                gap:
                    10px !important;

                overflow-x:
                    auto !important;

            }


            .admin-actions .admin-button {

                width:
                    115px !important;

                height:
                    56px !important;

                min-height:
                    56px !important;

                font-size:
                    8px !important;

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
                href="../index.php"
                class="brand"
            >

                <img
                    src="../assets/images/logo.png"
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

                <a href="../index.php">
                    WEBSITE
                </a>

                <a href="dashboard.php">
                    DASHBOARD
                </a>

                <a href="bookings.php">
                    BOOKINGS
                </a>

                <a href="logout.php">
                    LOGOUT
                </a>

            </nav>

        </div>

    </header>


    <!-- ========================================
         ADMIN DASHBOARD
    ======================================== -->

    <main class="inner-page">

        <div class="container form-page">


            <p class="section-kicker">
                ADMIN PANEL
            </p>


            <h1 class="page-title">

                <span>
                    DASHBOARD
                </span>

            </h1>


            <!-- ========================================
                 BOOKING STATISTICS
            ======================================== -->

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


            </div>


            <!-- ========================================
                 ADMIN ACTION BUTTONS
            ======================================== -->

            <div class="admin-actions">


                <!-- MANAGE BOOKINGS -->

                <a
                    href="bookings.php"
                    class="admin-button"
                >
                    MANAGE BOOKINGS
                </a>


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


                <!-- VIEW WEBSITE -->

                <a
                    href="../index.php"
                    class="admin-button"
                >
                    VIEW WEBSITE
                </a>


            </div>


            <!-- ========================================
                 RECENT BOOKINGS TABLE
            ======================================== -->

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


                        <?php foreach ($bookings as $booking): ?>

                            <tr>


                                <!-- ID -->

                                <td>

                                    #<?= (int) $booking["id"] ?>

                                </td>


                                <!-- CUSTOMER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            (string)
                                            $booking["customer_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>

                                    <br>

                                    <?= htmlspecialchars(
                                        (string)
                                        ($booking["email"] ?? ""),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- SETUP -->

                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        ($booking["setup_name"]
                                            ?? "Unknown"),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        $booking["booking_date"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- TIME -->

                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        $booking["start_time"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- HOURS -->

                                <td>

                                    <?= (int)
                                        $booking["hours"] ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= htmlspecialchars(
                                            (string)
                                            $booking["status"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            (string)
                                            $booking["status"],
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

        </div>

    </main>


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