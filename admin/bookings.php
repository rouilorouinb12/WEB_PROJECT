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
$error = "";

/* ========================================
   HANDLE BOOKING ACTION
======================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /* ----------------------------------------
           VERIFY CSRF
        ---------------------------------------- */
        $csrf = $_POST["csrf_token"] ?? "";

        if (!is_string($csrf) || !verifyCsrfToken($csrf)) {
            throw new RuntimeException(
                "Invalid request. Please refresh the page and try again."
            );
        }

        /* ----------------------------------------
           GET BOOKING ID
        ---------------------------------------- */
        $bookingId = filter_var(
            $_POST["booking_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($bookingId === false || $bookingId <= 0) {
            throw new RuntimeException("Invalid booking.");
        }

        /* ----------------------------------------
           GET ACTION
        ---------------------------------------- */
        $action = $_POST["action"] ?? "";

        if (!is_string($action)) {
            throw new RuntimeException("Invalid action.");
        }

        if (!in_array($action, ["accept", "reject"], true)) {
            throw new RuntimeException("Invalid action.");
        }

        /* ========================================
           ACCEPT
        ======================================== */
        if ($action === "accept") {

            $stmt = $conn->prepare("
                UPDATE bookings
                SET status = 'accepted'
                WHERE id = ?
                AND status = 'pending'
            ");

            $stmt->execute([$bookingId]);

            /*
             * IMPORTANT:
             * Do not rely only on rowCount().
             * Check the actual database value after UPDATE.
             */
            $checkStmt = $conn->prepare("
                SELECT status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$bookingId]);

            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus === "accepted") {

                /*
                 * Redirect after successful update.
                 * This removes POST refresh problems.
                 */
                header(
                    "Location: bookings.php?updated=accepted"
                );
                exit;

            } elseif ($currentStatus === "pending") {

                $error =
                    "The booking is still pending. The status was not updated.";

            } elseif ($currentStatus === false) {

                $error =
                    "Booking not found.";

            } else {

                $error =
                    "This booking has already been processed.";
            }
        }

        /* ========================================
           REJECT
        ======================================== */
        if ($action === "reject") {

            $stmt = $conn->prepare("
                UPDATE bookings
                SET status = 'rejected'
                WHERE id = ?
                AND status = 'pending'
            ");

            $stmt->execute([$bookingId]);

            /*
             * Check the actual database value.
             */
            $checkStmt = $conn->prepare("
                SELECT status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$bookingId]);

            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus === "rejected") {

                header(
                    "Location: bookings.php?updated=rejected"
                );
                exit;

            } elseif ($currentStatus === "pending") {

                $error =
                    "The booking is still pending. The status was not updated.";

            } elseif ($currentStatus === false) {

                $error =
                    "Booking not found.";

            } else {

                $error =
                    "This booking has already been processed.";
            }
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/* ========================================
   SUCCESS MESSAGE AFTER REDIRECT
======================================== */
if (isset($_GET["updated"])) {

    $updated = $_GET["updated"];

    if ($updated === "accepted") {

        $message =
            "Booking accepted successfully.";

    } elseif ($updated === "rejected") {

        $message =
            "Booking rejected successfully.";
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
        b.booking_date,
        b.start_time,
        b.hours,
        b.message,
        b.status,
        b.created_at,
        gs.name AS setup_name,
        gs.price_per_hour

    FROM bookings b

    LEFT JOIN gaming_setups gs
        ON gs.id = b.setup_id

    ORDER BY
        CASE
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'accepted' THEN 2
            WHEN b.status = 'rejected' THEN 3
            ELSE 4
        END,

        b.created_at DESC
");

$stmt->execute();

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ========================================
   CSRF TOKEN
======================================== */
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
            max-width: 1200px;
            margin: 0 auto;
        }


        /* ========================================
           TOP ACTIONS
        ======================================== */

        .admin-top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
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
            border: 1px solid #39FF14;
            transition: .2s ease;
        }


        .admin-button:hover {
            background: #39FF14;
            color: #000;
            box-shadow:
                0 0 15px
                rgba(57,255,20,.45);
        }


        .admin-button.dark {
            background: #39FF14;
            color: #000;
            border: 1px solid #39FF14;
        }


        /* ========================================
           MESSAGE
        ======================================== */

        .admin-message {
            border: 1px solid #39FF14;
            background: rgba(57,255,20,.08);
            color: #39FF14;
            padding: 15px 18px;
            margin-bottom: 25px;
            font-weight: 600;
        }


        .admin-error {
            border: 1px solid #ff3333;
            background: rgba(255,0,0,.08);
            color: #ff6666;
            padding: 15px 18px;
            margin-bottom: 25px;
            font-weight: 600;
        }


        /* ========================================
           TABLE
        ======================================== */

        .admin-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(57,255,20,.3);
        }


        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }


        .admin-table th,
        .admin-table td {
            padding: 14px;
            border-bottom:
                1px solid
                rgba(255,255,255,.08);
            text-align: left;
            font-size: 12px;
            vertical-align: middle;
        }


        .admin-table th {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }


        .admin-table td {
            color: #fff;
        }


        .admin-table tr:hover {
            background:
                rgba(57,255,20,.025);
        }


        /* ========================================
           STATUS
        ======================================== */

        .status {
            display: inline-block;
            padding: 6px 11px;
            font-family: Orbitron, sans-serif;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            border: 1px solid;
            white-space: nowrap;
        }


        .status.pending {
            color: #39FF14;
            border-color: #39FF14;
            background:
                rgba(57,255,20,.08);
        }


        .status.accepted {
            color: #39FF14;
            border-color: #39FF14;
            background:
                rgba(57,255,20,.08);
        }


        .status.rejected {
            color: #ff5555;
            border-color: #ff5555;
            background:
                rgba(255,0,0,.08);
        }


        /* ========================================
           ACTION BUTTONS
        ======================================== */

        .booking-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }


        .booking-actions form {
            margin: 0;
        }


        .booking-action {
            display: inline-block;
            padding: 8px 13px;
            font-family: Orbitron, sans-serif;
            font-size: 9px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            border-radius: 2px;
            transition: .2s ease;
        }


        /* ACCEPT */

        .accept-button {
            background: #39FF14;
            color: #000;
            border: 1px solid #39FF14;
        }


        .accept-button:hover {
            background: #39FF14;
            color: #000;
            box-shadow:
                0 0 10px
                rgba(57,255,20,.45);
        }


        /* REJECT */

        .reject-button {
            background: transparent;
            color: #ff5555;
            border: 1px solid #ff5555;
        }


        .reject-button:hover {
            background: #ff5555;
            color: #000;
        }


        /* ========================================
           COMPLETED ACTION
        ======================================== */

        .action-complete {
            display: inline-block;
            padding: 6px 10px;
            font-family: Orbitron, sans-serif;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
            border: 1px solid;
        }


        .action-complete.accepted {
            color: #39FF14;
            border-color: #39FF14;
            background: rgba(57,255,20,.08);
        }


        .action-complete.rejected {
            color: #ff5555;
            border-color: #ff5555;
            background: rgba(255,0,0,.08);
        }


        /* ========================================
           EMPTY TABLE
        ======================================== */

        .no-bookings {
            text-align: center;
            padding: 45px 20px;
            border:
                1px solid
                rgba(57,255,20,.25);
            color:
                rgba(255,255,255,.6);
        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .admin-top-actions {
                align-items: flex-start;
                flex-direction: column;
            }

            .admin-button {
                width: 100%;
                text-align: center;
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


        <!-- NAVIGATION -->

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


            <a href="reviews.php">
                REVIEWS
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
                <span>BOOKINGS</span>

            </h1>



            <!-- ========================================
                 TOP ACTIONS
            ======================================== -->

            <div class="admin-top-actions">


                <div>

                    <a
                        href="dashboard.php"
                        class="admin-button"
                    >
                        ← DASHBOARD
                    </a>


                    <a
                        href="reviews.php"
                        class="admin-button"
                        style="margin-left:8px;"
                    >
                        CUSTOMER REVIEWS
                    </a>

                </div>


                <a
                    href="../index.php"
                    class="admin-button dark"
                >
                    VIEW WEBSITE
                </a>

            </div>



            <!-- ========================================
                 SUCCESS MESSAGE
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
                 ERROR MESSAGE
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


                        <?php foreach ($bookings as $booking): ?>

                            <?php

                            /*
                             * Normalize status.
                             */
                            $status = strtolower(
                                trim(
                                    (string)
                                    ($booking["status"] ?? "")
                                )
                            );

                            /*
                             * Safety fallback.
                             */
                            if ($status === "") {
                                $status = "pending";
                            }

                            /*
                             * Only allow known CSS/action statuses.
                             */
                            if (
                                !in_array(
                                    $status,
                                    [
                                        "pending",
                                        "accepted",
                                        "rejected"
                                    ],
                                    true
                                )
                            ) {
                                $statusClass = "pending";
                                $statusLabel = strtoupper($status);
                            } else {
                                $statusClass = $status;
                                $statusLabel = strtoupper($status);
                            }

                            ?>

                            <tr>


                                <!-- ID -->

                                <td>

                                    #<?= (int)
                                        $booking["id"] ?>

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

                                    <small
                                        style="
                                            color:
                                            rgba(255,255,255,.5);
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            (string)
                                            ($booking["email"] ?? ""),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </small>


                                    <?php if (
                                        !empty($booking["phone"])
                                    ): ?>

                                        <br>

                                        <small
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

                                            <?= htmlspecialchars(
                                                (string)
                                                $booking["phone"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>



                                <!-- SETUP -->

                                <td>

                                    <?= htmlspecialchars(
                                        (string)
                                        (
                                            $booking["setup_name"]
                                            ?? "Unknown"
                                        ),
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



                                <!-- MESSAGE -->

                                <td>

                                    <?php

                                    $bookingMessage =
                                        trim(
                                            (string)
                                            (
                                                $booking["message"]
                                                ?? ""
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



                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="
                                            status
                                            <?= htmlspecialchars(
                                                $statusClass,
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



                                <!-- ACTION -->

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
                                                        $booking["id"] ?>"
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
                                                        $booking["id"] ?>"
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


                                        <span
                                            class="
                                                action-complete
                                                accepted
                                            "
                                        >
                                            ACCEPTED
                                        </span>


                                    <?php elseif (
                                        $status === "rejected"
                                    ): ?>


                                        <span
                                            class="
                                                action-complete
                                                rejected
                                            "
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