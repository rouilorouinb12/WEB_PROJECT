<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";


/* 
   IF ALREADY LOGGED IN
*/

if (isLoggedIn()) {

    $role = $_SESSION["user_role"] ?? "customer";

    if ($role === "admin") {

        header("Location: admin/dashboard.php");
        exit;

    }

    header("Location: index.php");
    exit;
}


$message = "";
$messageType = "";
$email = "";


 
   //LOGIN RATE LIMIT


if (!isset($_SESSION["login_attempts"])) {
    $_SESSION["login_attempts"] = 0;
}

if (!isset($_SESSION["login_lock_until"])) {
    $_SESSION["login_lock_until"] = 0;
}



   //HANDLE LOGIN


if ($_SERVER["REQUEST_METHOD"] === "POST") {


    
       //CHECK LOGIN LOCK
    

    if (
        isset($_SESSION["login_lock_until"]) &&
        time() < (int) $_SESSION["login_lock_until"]
    ) {

        $message =
            "Too many login attempts. Please try again later.";

        $messageType = "error";


    } else {


        
           //CSRF CHECK
        
        $csrfToken = $_POST["csrf_token"] ?? null;

        if (
            !verifyCsrfToken(
                is_string($csrfToken)
                    ? $csrfToken
                    : null
            )
        ) {

            $message =
                "Invalid form request. Please try again.";

            $messageType = "error";


        } else {


        
               //GET LOGIN VALUES
           

            $email = strtolower(
                trim(
                    (string) ($_POST["email"] ?? "")
                )
            );

            $password = (string) (
                $_POST["password"] ?? ""
            );


            
               //VALIDATION
            

            if (
                $email === "" ||
                $password === ""
            ) {

                $message =
                    "Please enter your email and password.";

                $messageType = "error";


            } elseif (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $message =
                    "Please enter a valid email address.";

                $messageType = "error";


            } else {


               
                   //FIND USER
              

                $stmt = $conn->prepare("
                    SELECT
                        id,
                        name,
                        email,
                        phone,
                        password,
                        role
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $email
                ]);

                $user = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                /* ========================================
                   CHECK PASSWORD
                ======================================== */

                if (
                    $user &&
                    isset($user["password"]) &&
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {


                    /* ========================================
                       REHASH PASSWORD IF NEEDED
                    ======================================== */

                    if (
                        password_needs_rehash(
                            $user["password"],
                            PASSWORD_DEFAULT
                        )
                    ) {

                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $update = $conn->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                        ");

                        $update->execute([
                            $newHash,
                            (int) $user["id"]
                        ]);

                    }


                    /* ========================================
                       RESET LOGIN ATTEMPTS
                    ======================================== */

                    $_SESSION["login_attempts"] = 0;
                    $_SESSION["login_lock_until"] = 0;


                    /* ========================================
                       REGENERATE SESSION ID
                    ======================================== */

                    session_regenerate_id(true);


                    /* ========================================
                       GET USER ROLE
                    ======================================== */

                    $role = (string) (
                        $user["role"] ?? "customer"
                    );


                    /* ========================================
                       ONLY ALLOW VALID ROLES
                    ======================================== */

                    if (
                        $role !== "admin" &&
                        $role !== "customer"
                    ) {

                        $role = "customer";

                    }


                    /* ========================================
                       SAVE USER SESSION
                    ======================================== */

                    $_SESSION["user_id"] =
                        (int) $user["id"];

                    $_SESSION["user_name"] =
                        (string) $user["name"];

                    $_SESSION["user_email"] =
                        (string) $user["email"];

                    $_SESSION["user_phone"] =
                        (string) (
                            $user["phone"] ?? ""
                        );

                    $_SESSION["user_role"] =
                        $role;


                    /* ========================================
                       GENERATE NEW CSRF TOKEN
                    ======================================== */

                    $_SESSION["csrf_token"] =
                        bin2hex(
                            random_bytes(32)
                        );


                    /* ========================================
                       ROLE-BASED REDIRECT
                    ======================================== */

                    if ($role === "admin") {

                        header(
                            "Location: admin/dashboard.php"
                        );

                        exit;

                    }


                    header(
                        "Location: index.php"
                    );

                    exit;

                } else {


                    /* ========================================
                       FAILED LOGIN
                    ======================================== */

                    $_SESSION["login_attempts"]++;


                    /* ========================================
                       LOCK AFTER 5 FAILED ATTEMPTS
                    ======================================== */

                    if (
                        $_SESSION["login_attempts"] >= 5
                    ) {

                        $_SESSION["login_lock_until"] =
                            time() + 60;

                        $_SESSION["login_attempts"] = 0;

                        $message =
                            "Too many login attempts. Please try again later.";

                    } else {

                        $message =
                            "Incorrect email or password.";

                    }

                    $messageType = "error";

                }

            }

        }

    }

}

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
        content="Login to your Bais Rouilo Gaming Cafe account."
    >

    <title>
        LOGIN | Bais Rouilo Gaming Cafe
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

            </nav>

        </div>

    </header>


    <!-- ========================================
         LOGIN PAGE
    ======================================== -->

    <main class="inner-page">

        <div class="container form-page">


            <p class="section-kicker">
                CUSTOMER ACCOUNT
            </p>


            <h1 class="page-title">

                <span>
                    LOGIN
                </span>

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


            <!-- ========================================
                 ONE LOGIN FORM
            ======================================== -->

            <form
                action="login.php"
                method="POST"
                class="booking-form"
            >


                <!-- CSRF TOKEN -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrfToken(),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >


                <div class="form-row">


                    <!-- EMAIL -->

                    <label>

                        Email Address

                        <input
                            type="email"
                            name="email"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
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


                </div>


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="green-button"
                >
                    LOGIN
                </button>


            </form>


            <!-- CREATE ACCOUNT -->

            <p
                style="
                    text-align:center;
                    margin-top:20px;
                    font-size:12px;
                "
            >

                Don't have an account?

                <a
                    href="register.php"
                    style="color:#39FF14;"
                >
                    CREATE ACCOUNT
                </a>

            </p>


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