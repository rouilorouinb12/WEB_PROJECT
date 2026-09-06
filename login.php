<?php
declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/* ========================================
   START SESSION
======================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========================================
   REDIRECT IF ALREADY LOGGED IN
======================================== */
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

/* ========================================
   LOGIN VARIABLES
======================================== */
$email = "";
$error = "";
$success = "";

/* ========================================
   HANDLE LOGIN
======================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF CHECK
    ======================================== */
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"] ?? "",
            $_POST["csrf_token"]
        )
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        /* ========================================
           VALIDATION
        ======================================== */
        if ($email === "" || $password === "") {
            $error = "Please enter your email and password.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {

            /* ========================================
               FIND USER
            ======================================== */
            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    first_name,
                    middle_name,
                    last_name,
                    email,
                    birthdate,
                    phone,
                    password,
                    created_at
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch();

            /* ========================================
               VERIFY PASSWORD
            ======================================== */
            if ($user && password_verify($password, $user["password"])) {

                /* ========================================
                   REHASH PASSWORD IF NEEDED
                ======================================== */
                if (password_needs_rehash(
                    $user["password"],
                    PASSWORD_DEFAULT
                )) {

                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $updatePassword = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                    $updatePassword->execute([
                        $newHash,
                        $user["id"]
                    ]);
                }

                /* ========================================
                   REGENERATE SESSION ID
                ======================================== */
                session_regenerate_id(true);

                /* ========================================
                   SAVE LOGIN SESSION
                ======================================== */
                $_SESSION["user_id"] = (int) $user["id"];

                $_SESSION["user"] = [
                    "id" => (int) $user["id"],
                    "name" => $user["name"],
                    "first_name" => $user["first_name"] ?? "",
                    "middle_name" => $user["middle_name"] ?? "",
                    "last_name" => $user["last_name"] ?? "",
                    "email" => $user["email"],
                    "birthdate" => $user["birthdate"] ?? "",
                    "phone" => $user["phone"] ?? "",
                    "created_at" => $user["created_at"]
                ];

                /* ========================================
                   LOGIN SUCCESS
                   REDIRECT DIRECTLY TO HOME/DASHBOARD
                ======================================== */
                header("Location: index.php");
                exit;

            } else {

                /* ========================================
                   GENERIC LOGIN ERROR
                ======================================== */
                $error = "Invalid email or password.";
            }
        }
    }
}

/* ========================================
   CREATE CSRF TOKEN
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
        content="Login to Bais Rouilo Gaming Cafe."
    >

    <title>LOGIN | Bais Rouilo Gaming Cafe</title>

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

            <!-- BOOK NOW -->
            <a
                href="register.php"
                class="nav-button"
            >
                BOOK NOW
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     LOGIN PAGE
======================================== -->

<main class="inner-page">

    <div class="container form-page">

        <!-- PAGE LABEL -->
        <p class="section-kicker">
            CUSTOMER LOGIN
        </p>

        <!-- PAGE TITLE -->
        <h1 class="page-title">
            LOGIN <span>ACCOUNT</span>
        </h1>


        <!-- ========================================
             LOGIN FORM
        ======================================== -->

        <div class="booking-form">

            <h2
                style="
                    color:#39FF14;
                    font-family:'Orbitron',sans-serif;
                    font-size:20px;
                    margin:0 0 25px;
                "
            >
                WELCOME BACK
            </h2>


            <!-- ERROR MESSAGE -->
            <?php if ($error !== ""): ?>

                <div
                    class="form-message"
                    style="
                        margin-bottom:20px;
                        color:#ff4444;
                        border-color:#ff4444;
                    "
                >
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <!-- SUCCESS MESSAGE -->
            <?php if ($success !== ""): ?>

                <div
                    class="form-message"
                    style="
                        margin-bottom:20px;
                    "
                >
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
            >

                <!-- CSRF TOKEN -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >


                <!-- EMAIL -->
                <label>

                    Email Address

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="Enter your email"
                        autocomplete="email"
                        required
                    >

                </label>


                <!-- PASSWORD -->
                <label>

                    Password

                    <input
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </label>


                <!-- SUBMIT -->
                <div
                    style="
                        margin-top:25px;
                        display:flex;
                        justify-content:center;
                    "
                >

                    <button
                        type="submit"
                        class="green-button"
                        style="
                            border:none;
                            cursor:pointer;
                            padding:13px 28px;
                        "
                    >
                        LOGIN
                    </button>

                </div>

            </form>


            <!-- REGISTER -->
            <div
                style="
                    text-align:center;
                    margin-top:30px;
                    font-size:14px;
                "
            >

                Don't have an account?

                <a
                    href="register.php"
                    style="
                        color:#39FF14;
                        font-weight:700;
                    "
                >
                    REGISTER HERE
                </a>

            </div>

        </div>

    </div>

</main>


<!-- ========================================
     MOBILE MENU SCRIPT
======================================== -->

<script>

const menuToggle = document.querySelector(".menu-toggle");
const mainNav = document.querySelector(".main-nav");

if (menuToggle && mainNav) {

    menuToggle.addEventListener("click", function () {

        mainNav.classList.toggle("open");

        const isOpen =
            mainNav.classList.contains("open");

        menuToggle.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );

    });

}

</script>

</body>
</html>