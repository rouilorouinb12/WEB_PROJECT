<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

/* ========================================
   REQUIRE LOGIN
======================================== */

requireLogin();

/* ========================================
   ADMIN ONLY
   CHECK ROLE DIRECTLY FROM DATABASE
======================================== */

$currentUserId = currentUserId();

$roleStmt = $conn->prepare("
    SELECT role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$roleStmt->execute([$currentUserId]);

$currentUserRole = strtolower(
    trim((string)$roleStmt->fetchColumn())
);

if ($currentUserRole !== "admin") {
    header("Location: ../index.php");
    exit;
}

/* ========================================
   VARIABLES
======================================== */

$message = "";
$error = "";

/* ========================================
   HANDLE REVIEW ACTION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /* VERIFY CSRF */

        $csrf = $_POST["csrf_token"] ?? null;

        if (
            !is_string($csrf) ||
            !verifyCsrfToken($csrf)
        ) {
            throw new RuntimeException(
                "Invalid request. Please refresh the page and try again."
            );
        }

        /* GET REVIEW ID */

        $reviewId = filter_var(
            $_POST["review_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        /* GET ACTION */

        $action = $_POST["action"] ?? "";

        /* VALIDATE REVIEW ID */

        if (
            $reviewId === false ||
            $reviewId <= 0
        ) {
            throw new RuntimeException(
                "Invalid review."
            );
        }

        /* VALIDATE ACTION */

        if (
            !in_array(
                $action,
                ["approve", "reject"],
                true
            )
        ) {
            throw new RuntimeException(
                "Invalid action."
            );
        }

        /* APPROVE REVIEW */

        if ($action === "approve") {

            $stmt = $conn->prepare("
                UPDATE reviews
                SET status = 'approved'
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $reviewId
            ]);

            $message =
                "Review approved successfully.";
        }

        /* REJECT REVIEW */

        if ($action === "reject") {

            $stmt = $conn->prepare("
                UPDATE reviews
                SET status = 'rejected'
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $reviewId
            ]);

            $message =
                "Review rejected successfully.";
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}

/* ========================================
   GET ALL REVIEWS
======================================== */

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.user_id,
        r.rating,
        r.review,
        r.status,
        r.created_at,
        u.name,
        u.email
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.user_id
    ORDER BY
        CASE
            WHEN r.status = 'pending' THEN 1
            WHEN r.status = 'approved' THEN 2
            WHEN r.status = 'rejected' THEN 3
            ELSE 4
        END,
        r.created_at DESC
");

$stmt->execute();

$reviews = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

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
        content="Admin review management - Bais Rouilo Gaming Cafe."
    >

    <title>
        REVIEWS | ADMIN | Bais Rouilo Gaming Cafe
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
           ADMIN REVIEWS
        ======================================== */

        .admin-reviews-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        .admin-top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .admin-back-button {
            display: inline-block;
            text-decoration: none;
            color: #39FF14;
            border: 1px solid #39FF14;
            padding: 12px 20px;
            font-family: Orbitron, sans-serif;
            font-size: 11px;
            font-weight: 800;
        }

        .admin-back-button:hover {
            background: #39FF14;
            color: #000;
        }

        .admin-message {
            border: 1px solid #39FF14;
            background: rgba(57, 255, 20, .08);
            color: #39FF14;
            padding: 15px 18px;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .admin-error {
            border: 1px solid #ff3333;
            background: rgba(255, 0, 0, .08);
            color: #ff6666;
            padding: 15px 18px;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .review-admin-card {
            border: 1px solid rgba(57, 255, 20, .35);
            background: rgba(0, 0, 0, .65);
            padding: 20px 24px;
            margin-bottom: 18px;
            box-sizing: border-box;
        }

        .review-admin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .review-customer-name {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 16px;
            font-weight: 800;
        }

        .review-customer-email {
            color: rgba(255,255,255,.55);
            font-size: 12px;
            margin-top: 5px;
        }

        .review-admin-stars {
            color: #39FF14;
            font-size: 18px;
            letter-spacing: 2px;
        }

        .review-admin-text {
            color: rgba(255,255,255,.9);
            line-height: 1.6;
            white-space: normal;
            word-break: break-word;
            padding: 14px 0;
            border-top: 1px solid rgba(57,255,20,.12);
            border-bottom: 1px solid rgba(57,255,20,.12);
        }

        .review-admin-label {
            color: #39FF14;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            margin-bottom: 7px;
            text-transform: uppercase;
        }

        .review-admin-content {
            color: rgba(255,255,255,.9);
            line-height: 1.6;
            white-space: normal;
            word-break: break-word;
        }

        .review-admin-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .review-admin-date {
            color: rgba(255,255,255,.5);
            font-size: 10px;
        }

        .review-status {
            display: inline-block;
            padding: 7px 12px;
            font-family: Orbitron, sans-serif;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            border: 1px solid #39FF14;
            color: #39FF14;
        }

        .review-status.pending {
            color: #39FF14;
            border-color: #39FF14;
        }

        .review-status.approved {
            color: #39FF14;
            border-color: #39FF14;
            background: rgba(57,255,20,.08);
        }

        .review-status.rejected {
            color: #ff5555;
            border-color: #ff5555;
            background: rgba(255,0,0,.08);
        }

        .review-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .review-action {
            border: none;
            padding: 10px 18px;
            font-family: Orbitron, sans-serif;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
        }

        .approve-button {
            background: #39FF14;
            color: #000;
        }

        .approve-button:hover {
            box-shadow: 0 0 12px rgba(57,255,20,.4);
        }

        .reject-button {
            background: transparent;
            color: #ff5555;
            border: 1px solid #ff5555;
        }

        .reject-button:hover {
            background: #ff5555;
            color: #000;
        }

        .no-reviews {
            text-align: center;
            padding: 45px 20px;
            border: 1px solid rgba(57,255,20,.25);
            color: rgba(255,255,255,.6);
        }

        @media (max-width: 600px) {

            .review-admin-header {
                flex-direction: column;
            }

            .review-admin-footer {
                align-items: flex-start;
                flex-direction: column;
            }

            .review-actions {
                width: 100%;
            }

            .review-action {
                width: 100%;
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

            <a href="bookings.php">
                BOOKINGS
            </a>

            <a
                href="reviews.php"
                class="nav-button"
            >
                REVIEWS
            </a>

            <a href="logout.php">
                LOGOUT
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     ADMIN REVIEWS
======================================== -->

<main class="inner-page">

    <div class="container">

        <div class="admin-reviews-wrapper">

            <!-- PAGE HEADER -->

            <p class="section-kicker">
                ADMIN PANEL
            </p>

            <h1 class="page-title">
                CUSTOMER <span>REVIEWS</span>
            </h1>


            <!-- BACK BUTTON -->

            <div class="admin-top-actions">

                <a
                    href="dashboard.php"
                    class="admin-back-button"
                >
                    ← BACK TO DASHBOARD
                </a>

            </div>


            <!-- SUCCESS MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="admin-message">

                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ERROR MESSAGE -->

            <?php if ($error !== ""): ?>

                <div class="admin-error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- REVIEW LIST -->

            <?php if (!$reviews): ?>

                <div class="no-reviews">
                    No customer reviews yet.
                </div>

            <?php else: ?>

                <?php foreach ($reviews as $item): ?>

                    <div class="review-admin-card">

                        <!-- REVIEW HEADER -->

                        <div class="review-admin-header">

                            <div>

                                <div class="review-customer-name">

                                    <?= htmlspecialchars(
                                        (string)$item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                                <div class="review-customer-email">

                                    <?= htmlspecialchars(
                                        (string)$item["email"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>


                            <!-- STARS -->

                            <div class="review-admin-stars">

                                <?= str_repeat(
                                    "★",
                                    max(
                                        0,
                                        min(
                                            5,
                                            (int)$item["rating"]
                                        )
                                    )
                                ) ?>

                                <?= str_repeat(
                                    "☆",
                                    5 - max(
                                        0,
                                        min(
                                            5,
                                            (int)$item["rating"]
                                        )
                                    )
                                ) ?>

                            </div>

                        </div>


                        <!-- REVIEW CONTENT -->

                        <div class="review-admin-text">

                            <div class="review-admin-label">
                                CUSTOMER REVIEW
                            </div>

                            <div class="review-admin-content">

                                <?= htmlspecialchars(
                                    (string)$item["review"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <!-- REVIEW FOOTER -->

                        <div class="review-admin-footer">

                            <div>

                                <!-- DATE -->

                                <div class="review-admin-date">

                                    Submitted:

                                    <?= htmlspecialchars(
                                        date(
                                            "F d, Y h:i A",
                                            strtotime(
                                                (string)$item["created_at"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>


                                <!-- STATUS -->

                                <div
                                    class="review-status <?= htmlspecialchars(
                                        strtolower(
                                            (string)$item["status"]
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    style="margin-top:8px;"
                                >

                                    <?= htmlspecialchars(
                                        (string)$item["status"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>


                            <!-- ACTIONS -->

                            <?php if (
                                (string)$item["status"] === "pending"
                            ): ?>

                                <div class="review-actions">

                                    <!-- APPROVE -->

                                    <form
                                        method="POST"
                                        action="reviews.php"
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
                                            name="review_id"
                                            value="<?= (int)$item["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="approve"
                                        >

                                        <button
                                            type="submit"
                                            class="review-action approve-button"
                                        >
                                            APPROVE
                                        </button>

                                    </form>


                                    <!-- REJECT -->

                                    <form
                                        method="POST"
                                        action="reviews.php"
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
                                            name="review_id"
                                            value="<?= (int)$item["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reject"
                                        >

                                        <button
                                            type="submit"
                                            class="review-action reject-button"
                                        >
                                            REJECT
                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

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
