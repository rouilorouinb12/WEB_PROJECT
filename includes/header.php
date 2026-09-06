<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}

$currentPage = basename($_SERVER["PHP_SELF"]);

$isLoggedIn = isset($_SESSION["user_id"]);

$userName = $_SESSION["user"]["name"] ?? "";

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
        content="Bais Rouilo Gaming Cafe - Play. Compete. Win."
    >

    <title>
        BAIS ROUILO | Gaming Cafe
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

<header class="site-header">

    <div class="container nav-container">

        <!-- LOGO -->

        <a
            href="index.php"
            class="brand"
        >

            <img
                src="assets/images/logo.png"
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


        <!-- MAIN NAVIGATION -->

        <nav
            class="main-nav"
            id="mainNav"
        >

            <a
                href="index.php#home"
                class="<?= $currentPage === "index.php" ? "active" : "" ?>"
            >
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


            <a
                href="contact.php"
                class="<?= $currentPage === "contact.php" ? "active" : "" ?>"
            >
                CONTACT
            </a>


            <!-- PROFILE / BOOK NOW -->

            <?php if ($isLoggedIn): ?>

                <a
                    href="profile.php"
                    class="nav-button"
                >
                    PROFILE
                </a>

            <?php else: ?>

                <a
                    href="login.php"
                    class="nav-button"
                >
                    BOOK NOW
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>