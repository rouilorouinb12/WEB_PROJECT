<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireLogin();

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/
if (($_SESSION["user_role"] ?? "customer") !== "admin") {
    http_response_code(403);
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| GET RECENT BOOKINGS
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| COUNT BOOKINGS
|--------------------------------------------------------------------------
*/
$countStmt = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'accepted') AS accepted,
        SUM(status = 'rejected') AS rejected
    FROM bookings
");

$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalBookings = (int) ($counts["total"] ?? 0);
$pendingBookings = (int) ($counts["pending"] ?? 0);
$acceptedBookings = (int) ($counts["accepted"] ?? 0);
$rejectedBookings = (int) ($counts["rejected"] ?? 0);

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

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .admin-stat {
            border: 1px solid rgba(57,255,20,.35);
            background: rgba(0,0,0,.65);
            padding: 25px;
            text-align: center;
        }

        .admin-stat h3 {
            margin: 0;
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 30px;
        }

        .admin-stat p {
            margin: 8px 0 0;
            font-size: 12px;
            font-weight: 700;
        }

        .admin-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .admin-button {
            display: inline-block;
            padding: 12px 18px;
            background: #39FF14;
            color: #000;
            text-decoration: none;
            font-family: Orbitron, sans-serif;
            font-size: 11px;
            font-weight: 800;
            border-radius: 3px;
        }

        .admin-button.dark {
            background: #111;
            color: #fff;
            border: 1px solid rgba(57,255,20,.4);
        }

        .admin-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(57,255,20,.3);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .admin-table th,
        .admin-table td {
            padding: 14px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-align: left;
            font-size: 12px;
        }

        .admin-table th {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
        }

        .admin-table td {
            color: #fff;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            border: 1px solid rgba(255,255,255,.2);
        }

        .status.pending {
            color: #fff;
        }

        .status.accepted {
            color: #39FF14;
        }

        .status.rejected {
            color: #ff4d4d;
        }

        @media (max-width: 800px) {

            .admin-stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 500px) {

            .admin-stats {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

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


<main class="inner-page">

    <div class="container form-page">

        <p class="section-kicker">
            ADMIN PANEL
        </p>

        <h1 class="page-title">
            <span>DASHBOARD</span>
        </h1>


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


        <div class="admin-actions">

            <a
                href="bookings.php"
                class="admin-button"
            >
                MANAGE BOOKINGS
            </a>

            <a
                href="../index.php"
                class="admin-button dark"
            >
                VIEW WEBSITE
            </a>

        </div>


        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>CUSTOMER</th>

                        <th>SETUP</th>

                        <th>DATE</th>

                        <th>TIME</th>

                        <th>HOURS</th>

                        <th>STATUS</th>

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

                            <td>
                                #<?= (int) $booking["id"] ?>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $booking["customer_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>

                                <br>

                                <?= htmlspecialchars(
                                    (string) ($booking["email"] ?? ""),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) ($booking["setup_name"] ?? "Unknown"),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $booking["booking_date"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $booking["start_time"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $booking["hours"] ?>
                            </td>

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        (string) $booking["status"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        (string) $booking["status"],
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