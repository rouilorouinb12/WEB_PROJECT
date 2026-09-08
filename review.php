<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/* ========================================
   REQUIRE LOGIN
======================================== */

requireLogin();

/* ========================================
   ONLY CUSTOMERS CAN SUBMIT REVIEWS
======================================== */

if (($_SESSION["user_role"] ?? "customer") !== "customer") {
    header("Location: profile.php");
    exit;
}

$userId = currentUserId();

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$successMessage = "";
$errorMessage = "";

/* ========================================
   SUBMIT REVIEW
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {

        $errorMessage = "Invalid security token. Please refresh the page and try again.";

    } else {

        $rating = (int)($_POST["rating"] ?? 0);
        $review = trim((string)($_POST["review"] ?? ""));

        if ($rating < 1 || $rating > 5) {

            $errorMessage = "Please select a rating from 1 to 5 stars.";

        } elseif ($review === "") {

            $errorMessage = "Please write your review.";

        } elseif (mb_strlen($review) < 5) {

            $errorMessage = "Your review must be at least 5 characters.";

        } elseif (mb_strlen($review) > 1000) {

            $errorMessage = "Your review must not exceed 1000 characters.";

        } else {

            $insertStmt = $conn->prepare("
                INSERT INTO reviews
                    (user_id, rating, review, status)
                VALUES
                    (?, ?, ?, 'pending')
            ");

            $insertStmt->execute([
                $userId,
                $rating,
                $review
            ]);

            $successMessage =
                "Thank you! Your review has been submitted and is waiting for approval.";

            $_POST["rating"] = "";
            $_POST["review"] = "";
        }
    }
}

/* ========================================
   CSRF TOKEN
======================================== */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

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
        content="Submit a review - Bais Rouilo Gaming Cafe."
    >

    <title>
        SEND REVIEW | Bais Rouilo Gaming Cafe
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
        href="assets/css/style.css"
    >

</head>

<body>

<!-- ========================================
     HEADER
======================================== -->

<header class="site-header">

    <div class="container nav-container">

        <a
            href="index.php"
            class="brand"
        >

            <img
                src="assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
            >

        </a>

        <button
            class="menu-toggle"
            aria-label="Open menu"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

        <nav
            class="main-nav"
            id="mainNav"
        >

            <a href="index.php#home">
                HOME
            </a>

            <a href="index.php#pcs">
                PCS
            </a>

            <a href="index.php#rates">
                RATES
            </a>

            <a href="index.php#tournaments">
                TOURNAMENTS
            </a>

            <a href="index.php#gallery">
                GALLERY
            </a>

            <a href="contact.php">
                CONTACT
            </a>

            <a
                href="profile.php"
                class="nav-button"
            >
                PROFILE
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     REVIEW PAGE
======================================== -->

<main class="inner-page">

    <div class="container form-page">

        <p class="section-kicker">
            CUSTOMER FEEDBACK
        </p>

        <h1 class="page-title">
            SEND <span>REVIEW</span>
        </h1>


        <div class="booking-form">

            <h2
                style="
                    color:#39FF14;
                    font-family:'Orbitron',sans-serif;
                    font-size:20px;
                    margin:0 0 25px;
                "
            >
                SHARE YOUR EXPERIENCE
            </h2>


            <?php if ($successMessage !== ""): ?>

                <div
                    class="form-message success"
                    style="
                        margin-bottom:25px;
                        border:1px solid #39FF14;
                        color:#39FF14;
                        padding:15px;
                    "
                >

                    <?= htmlspecialchars(
                        $successMessage,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if ($errorMessage !== ""): ?>

                <div
                    class="form-message error"
                    style="
                        margin-bottom:25px;
                        border:1px solid #ff3333;
                        color:#ff3333;
                        padding:15px;
                    "
                >

                    <?= htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="review.php"
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


                <!-- CUSTOMER -->

                <div class="form-row">

                    <label>

                        Full Name

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string)($user["name"] ?? ""),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            readonly
                        >

                    </label>


                    <label>

                        Email Address

                        <input
                            type="email"
                            value="<?= htmlspecialchars(
                                (string)($user["email"] ?? ""),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            readonly
                        >

                    </label>

                </div>


                <!-- RATING -->

                <label>

                    Rating

                    <select
                        name="rating"
                        required
                    >

                        <option value="">
                            SELECT RATING
                        </option>

                        <option
                            value="5"
                            <?= ((int)($_POST["rating"] ?? 0) === 5) ? "selected" : "" ?>
                        >
                            ★★★★★ — 5 STARS
                        </option>

                        <option
                            value="4"
                            <?= ((int)($_POST["rating"] ?? 0) === 4) ? "selected" : "" ?>
                        >
                            ★★★★☆ — 4 STARS
                        </option>

                        <option
                            value="3"
                            <?= ((int)($_POST["rating"] ?? 0) === 3) ? "selected" : "" ?>
                        >
                            ★★★☆☆ — 3 STARS
                        </option>

                        <option
                            value="2"
                            <?= ((int)($_POST["rating"] ?? 0) === 2) ? "selected" : "" ?>
                        >
                            ★★☆☆☆ — 2 STARS
                        </option>

                        <option
                            value="1"
                            <?= ((int)($_POST["rating"] ?? 0) === 1) ? "selected" : "" ?>
                        >
                            ★☆☆☆☆ — 1 STAR
                        </option>

                    </select>

                </label>


                <!-- REVIEW -->

                <label>

                    Your Review

                    <textarea
                        name="review"
                        rows="7"
                        maxlength="1000"
                        required
                        placeholder="Tell us about your experience at Bais Rouilo Gaming Cafe..."
                    ><?= htmlspecialchars(
                        (string)($_POST["review"] ?? ""),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?></textarea>

                </label>


                <div
                    style="
                        display:flex;
                        gap:15px;
                        flex-wrap:wrap;
                        margin-top:5px;
                    "
                >

                    <button
                        type="submit"
                        class="green-button"
                        style="
                            padding:13px 22px;
                            border:none;
                            cursor:pointer;
                            font-family:'Orbitron',sans-serif;
                        "
                    >
                        SUBMIT REVIEW
                    </button>


                    <a
                        href="profile.php"
                        class="outline-button"
                        style="
                            color:#39FF14;
                            padding:13px 22px;
                        "
                    >
                        BACK TO PROFILE
                    </a>

                </div>

            </form>

        </div>


        <div
            style="
                text-align:center;
                margin-top:25px;
            "
        >

            <a
                href="reviews.php"
                class="outline-button"
                style="
                    color:#39FF14;
                    padding:13px 22px;
                "
            >
                VIEW CUSTOMER REVIEWS
            </a>

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

if (menuToggle && mainNav) {

    menuToggle.addEventListener(
        "click",
        function() {

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
