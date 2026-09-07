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

$message = "";
$messageType = "";

/* ========================================
   HANDLE ACCEPT / REJECT
======================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? null;

    if (
        !verifyCsrfToken(
            is_string($csrfToken) ? $csrfToken : null
        )
    ) {

        $message = "Invalid form request. Please try again.";
        $messageType = "error";

    } else {

        $bookingId = filter_var(
            $_POST["booking_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = $_POST["action"] ?? "";

        if (
            !$bookingId ||
            !in_array(
                $action,
                ["accept", "reject"],
                true
            )
        ) {

            $message = "Invalid booking request.";
            $messageType = "error";

        } else {

            /*
             * Convert admin action into booking status.
             */
            $newStatus =
                $action === "accept"
                    ? "accepted"
                    : "rejected";

            $stmt = $conn->prepare("
                UPDATE bookings
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $newStatus,
                (int) $bookingId
            ]);

            if ($stmt->rowCount() > 0) {

                if ($action === "accept") {
                    $message = "Booking accepted successfully.";
                } else {
                    $message = "Booking rejected successfully.";
                }

                $messageType = "success";

            } else {

                $message =
                    "Booking was not found or its status was already unchanged.";

                $messageType = "error";
            }
        }
    }
}

/* ========================================
   GET ALL BOOKINGS
======================================== */
$stmt = $conn->prepare("
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
    ORDER BY
        CASE
            WHEN b.status = 'pending' THEN 0
            WHEN b.status = 'accepted' THEN 1
            ELSE 2
        END,
        b.booking_date ASC,
        b.start_time ASC,
        b.created_at DESC
");

$stmt->execute();

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        content="Manage gaming cafe bookings."
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
            min-width: 1200px;
        }

        .admin-table th,
        .admin-table td {
            padding: 14px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .admin-table th {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
            white-space: nowrap;
        }

        .admin-table td {
            color: #fff;
        }

        .customer-info strong {
            display: block;
            margin-bottom: 4px;
        }

        .customer-info small {
            display: block;
            opacity: .75;
            margin-top: 2px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
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
            border-color: rgba(57,255,20,.5);
        }

        .status.rejected {
            color: #ff4d4d;
            border-color: rgba(255,77,77,.5);
        }

        .booking-message {
            max-width: 220px;
            line-height: 1.5;
            opacity: .85;
        }

        .booking-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-form {
            margin: 0;
        }

        .action-button {
            border: 0;
            padding: 8px 12px;
            font-family: Orbitron, sans-serif;
            font-size: 9px;
            font-weight: 800;
            cursor: pointer;
            border-radius: 3px;
        }

        .action-button.accept {
            background: #39FF14;
            color: #000;
        }

        .action-button.reject {
            background: #ff4d4d;
            color: #fff;
        }

        .no-bookings {
            text-align: center !important;
            padding: 35px !important;
            opacity: .7;
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


        <!-- ADMIN NAVIGATION -->

        <nav
            class="main-nav"
            id="mainNav"
        >

            <a href="dashboard.php">
                DASHBOARD
            </a>

            <a href="bookings.php">
                BOOKINGS
            </a>

            <a href="../index.php">
                WEBSITE
            </a>

            <a href="logout.php">
                LOGOUT
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     BOOKINGS PAGE
======================================== -->

<main class="inner-page">

    <div class="container form-page">


        <p class="section-kicker">
            ADMIN PANEL
        </p>


        <h1 class="page-title">
            <span>BOOKINGS</span>
        </h1>


        <!-- MESSAGE -->

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


        <!-- ACTION LINKS -->

        <div class="admin-actions">

            <a
                href="dashboard.php"
                class="admin-button dark"
            >
                DASHBOARD
            </a>

            <a
                href="../index.php"
                class="admin-button dark"
            >
                VIEW WEBSITE
            </a>

        </div>


        <!-- ========================================
             BOOKINGS TABLE
        ======================================== -->

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

                        <th>MESSAGE</th>

                        <th>STATUS</th>

                        <th>ACTION</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$bookings): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="no-bookings"
                        >
                            No bookings found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($bookings as $booking): ?>

                        <?php
                        $status = (string) $booking["status"];
                        ?>

                        <tr>


                            <!-- ID -->

                            <td>
                                #<?= (int) $booking["id"] ?>
                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-info">

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $booking["customer_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars(
                                            (string) ($booking["email"] ?? ""),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </small>

                                    <small>
                                        <?= htmlspecialchars(
                                            (string) ($booking["phone"] ?? ""),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </small>

                                </div>

                            </td>


                            <!-- SETUP -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $booking["setup_name"]
                                        ?? "Unknown setup"
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) $booking["booking_date"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <!-- TIME -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) $booking["start_time"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <!-- HOURS -->

                            <td>

                                <?= (int) $booking["hours"] ?>

                            </td>


                            <!-- MESSAGE -->

                            <td>

                                <div class="booking-message">

                                    <?= htmlspecialchars(
                                        (string) (
                                            $booking["message"]
                                            ?? ""
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        strtoupper($status),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <?php if ($status === "pending"): ?>

                                    <div class="booking-actions">


                                        <!-- ACCEPT -->

                                        <form
                                            method="POST"
                                            action="bookings.php"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    csrfToken(),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="booking_id"
                                                value="<?= (int) $booking["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="accept"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button accept"
                                                onclick="return confirm('Accept this booking?');"
                                            >
                                                ACCEPT
                                            </button>

                                        </form>


                                        <!-- REJECT -->

                                        <form
                                            method="POST"
                                            action="bookings.php"
                                            class="action-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    csrfToken(),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="booking_id"
                                                value="<?= (int) $booking["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button reject"
                                                onclick="return confirm('Reject this booking?');"
                                            >
                                                REJECT
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span>
                                        —
                                    </span>

                                <?php endif; ?>

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
     MOBILE MENU SCRIPT
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