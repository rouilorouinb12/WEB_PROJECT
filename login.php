<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";


/* ========================================
   IF ALREADY LOGGED IN
======================================== */

if (isLoggedIn()) {

    $role = currentUserRole();

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


/* ========================================
   HANDLE LOGIN
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF CHECK
    ======================================== */

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

        $messageType =
            "error";

    } else {

        /* ========================================
           GET INPUT
        ======================================== */

        $email =
            strtolower(
                trim(
                    (string)(
                        $_POST["email"] ?? ""
                    )
                )
            );

        $password =
            (string)(
                $_POST["password"] ?? ""
            );


        /* ========================================
           BASIC VALIDATION
        ======================================== */

        if (
            $email === "" ||
            $password === ""
        ) {

            $message =
                "Please enter your email and password.";

            $messageType =
                "error";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                "Please enter a valid email address.";

            $messageType =
                "error";

        } else {

            try {

                /* ========================================
                   RATE LIMIT CHECK
                ======================================== */

                if (
                    loginRateLimitExceeded(
                        $email
                    )
                ) {

                    $message =
                        "Too many login attempts. Please try again later.";

                    $messageType =
                        "error";

                } else {

                    /* ========================================
                       FIND USER
                    ======================================== */

                    $stmt =
                        $conn->prepare("
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

                    $user =
                        $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    /* ========================================
                       PASSWORD CHECK
                    ======================================== */

                    $passwordHash =
                        $user
                            ? (string)$user["password"]
                            : "";


                    /*
                     * Perform password verification even
                     * when account does not exist.
                     */

                    if ($passwordHash === "") {

                        $passwordHash =
                            password_hash(
                                "invalid-login-password",
                                PASSWORD_DEFAULT
                            );
                    }


                    $passwordValid =
                        password_verify(
                            $password,
                            $passwordHash
                        );


                    /* ========================================
                       SUCCESSFUL LOGIN
                    ======================================== */

                    if (
                        $user &&
                        $passwordValid
                    ) {

                        $role =
                            (string)(
                                $user["role"] ?? ""
                            );


                        /* ========================================
                           VALIDATE ROLE
                        ======================================== */

                        if (
                            $role !== "admin" &&
                            $role !== "customer"
                        ) {

                            throw new RuntimeException(
                                "Invalid account role."
                            );
                        }


                        /* ========================================
                           REHASH PASSWORD IF NEEDED
                        ======================================== */

                        if (
                            password_needs_rehash(
                                (string)$user["password"],
                                PASSWORD_DEFAULT
                            )
                        ) {

                            $newHash =
                                password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );

                            $update =
                                $conn->prepare("
                                    UPDATE users
                                    SET password = ?
                                    WHERE id = ?
                                ");

                            $update->execute([
                                $newHash,
                                (int)$user["id"]
                            ]);
                        }


                        /* ========================================
                           CLEAR FAILED LOGIN RECORD
                        ======================================== */

                        clearFailedLogin(
                            $email
                        );


                        /* ========================================
                           CREATE AUTHENTICATED SESSION
                        ======================================== */

                        loginUser(
                            $user
                        );


                        /* ========================================
                           ROLE-BASED REDIRECT
                        ======================================== */

                        if (
                            $role === "admin"
                        ) {

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

                        registerFailedLogin(
                            $email
                        );

                        $message =
                            "Incorrect email or password.";

                        $messageType =
                            "error";
                    }
                }

            } catch (PDOException $e) {

                /*
                 * Do not expose database details.
                 */

                $message =
                    "Unable to process your login right now. Please try again.";

                $messageType =
                    "error";


            } catch (RuntimeException $e) {

                $message =
                    "Unable to process your login right now. Please try again.";

                $messageType =
                    "error";
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


    <style>

        /* ========================================
           PAGE BACKGROUND
           IMAGE ONLY IN LOGIN CONTENT
        ======================================== */

        body {

            background:
                #000;
        }


        .site-header {

            background:
                #000;

            backdrop-filter:
                none;
        }


        /* ========================================
           LOGIN PAGE
        ======================================== */

        .login-page {

            position:
                relative;

            isolation:
                isolate;

            min-height:
                calc(100vh - 101px);

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                center;

            padding:
                55px 20px 80px;
        }


        .login-page::before {

            content:
                "";

            position:
                absolute;

            inset:
                0;

            z-index:
                -1;

            background:

                linear-gradient(
                    rgba(0, 0, 0, .72),
                    rgba(0, 0, 0, .72)
                ),

                url("assets/images/account-bg.png")

                center center / cover

                no-repeat;
        }


        /* ========================================
           LOGIN CONTAINER
        ======================================== */

        .login-container {

            width:
                100%;

            max-width:
                560px;

            margin:
                0 auto;
        }


        /* ========================================
           LOGIN HEADING
        ======================================== */

        .login-kicker {

            margin:
                0 0 8px;

            text-align:
                center;

            color:
                var(--green);

            font:
                500 11px "Orbitron",
                sans-serif;
        }


        .login-title {

            margin:
                0 0 32px;

            text-align:
                center;

            color:
                var(--green);

            font:
                600 48px/1
                "Orbitron",
                sans-serif;
        }


        /* ========================================
           LOGIN CARD
        ======================================== */

        .login-card {

            width:
                100%;

            border:
                1px solid var(--green);

            border-radius:
                10px;

            background:
                rgba(0, 0, 0, .88);

            padding:
                38px 42px;

            box-shadow:

                0 0 18px
                rgba(57, 255, 20, .08),

                0 0 40px
                rgba(57, 255, 20, .03);

            box-sizing:
                border-box;
        }


        /* ========================================
           FORM
        ======================================== */

        .login-fields {

            display:
                grid;

            grid-template-columns:
                1fr;

            gap:
                22px;
        }


        .login-fields label {

            display:
                grid;

            gap:
                8px;

            color:
                #eeeeee;

            font:
                700 11px
                "Montserrat",
                sans-serif;
        }


        .login-fields input {

            width:
                100%;

            height:
                48px;

            padding:
                0 15px;

            border:
                1px solid #333;

            border-radius:
                4px;

            background:
                #070707;

            color:
                #fff;

            outline:
                none;

            box-sizing:
                border-box;

            font:
                500 13px
                "Montserrat",
                sans-serif;

            transition:

                border-color .2s ease,

                box-shadow .2s ease;
        }


        .login-fields input::placeholder {

            color:
                #777;
        }


        .login-fields input:focus {

            border-color:
                var(--green);

            box-shadow:

                0 0 10px
                rgba(57, 255, 20, .18);
        }


        /* ========================================
           PASSWORD
        ======================================== */

        .password-input-wrap {

            position:
                relative;

            width:
                100%;
        }


        .password-input-wrap input {

            padding-right:
                82px;
        }


        /* ========================================
           SHOW / HIDE PASSWORD
        ======================================== */

        .show-password {

            position:
                absolute;

            right:
                10px;

            top:
                50%;

            transform:
                translateY(-50%);

            border:
                none;

            background:
                transparent;

            color:
                var(--green);

            padding:
                5px;

            font:
                800 8px
                "Orbitron",
                sans-serif;

            line-height:
                1;

            cursor:
                pointer;

            z-index:
                3;
        }


        .show-password:hover {

            color:
                #fff;
        }


        .show-password:focus {

            outline:
                none;
        }


        /* ========================================
           LOGIN BUTTON
        ======================================== */

        .login-submit {

            width:
                100%;

            height:
                48px;

            margin-top:
                26px;

            border:
                1px solid var(--green);

            border-radius:
                4px;

            background:
                var(--green);

            color:
                #000;

            font:
                800 12px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:

                transform .2s ease,

                box-shadow .2s ease;
        }


        .login-submit:hover {

            background:
                var(--green);

            color:
                #000;

            transform:
                translateY(-2px);

            box-shadow:

                0 0 12px
                rgba(57, 255, 20, .55),

                0 0 25px
                rgba(57, 255, 20, .18);
        }


        .login-submit:focus,
        .login-submit:active {

            background:
                var(--green);

            color:
                #000;
        }


        /* ========================================
           REGISTER LINK
        ======================================== */

        .login-register {

            margin:
                22px 0 0;

            text-align:
                center;

            color:
                #ddd;

            font:
                12px
                "Montserrat",
                sans-serif;
        }


        .login-register a {

            color:
                var(--green);

            font-weight:
                700;

            text-decoration:
                none;
        }


        .login-register a:hover {

            color:
                #fff;
        }


        /* ========================================
           BACK TO HOMEPAGE
        ======================================== */

        .back-home {

            margin:
                18px 0 0;

            text-align:
                center;
        }


        .back-home a {

            display:
                inline-block;

            color:
                #fff;

            text-decoration:
                none;

            font:
                700 11px
                "Orbitron",
                sans-serif;

            letter-spacing:
                .5px;

            transition:
                color .2s ease,
                transform .2s ease;
        }


        .back-home a:hover {

            color:
                var(--green);

            transform:
                translateY(-1px);
        }


        /* ========================================
           MESSAGE
        ======================================== */

        .login-message {

            width:
                100%;

            margin:
                0 0 18px;

            box-sizing:
                border-box;
        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .login-page {

                min-height:
                    calc(100vh - 82px);

                padding:
                    40px 15px 60px;
            }


            .login-container {

                max-width:
                    100%;
            }


            .login-title {

                font-size:
                    36px;

                margin-bottom:
                    25px;
            }


            .login-card {

                padding:
                    28px 22px;
            }


            .login-fields {

                gap:
                    18px;
            }


            .back-home a {

                font-size:
                    10px;
            }

        }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
     LOGO ONLY
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

    </div>

</header>


<!-- ========================================
     LOGIN PAGE
======================================== -->

<main class="login-page">

    <div class="login-container">


        <!-- LOGIN HEADING -->

        <p class="login-kicker">
            ACCOUNT
        </p>


        <h1 class="login-title">
            LOGIN
        </h1>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div
                class="form-message <?= htmlspecialchars(
                    $messageType,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?> login-message"
            >

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <!-- LOGIN CARD -->

        <div class="login-card">

            <form
                action="login.php"
                method="POST"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrfToken(),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >


                <!-- FIELDS -->

                <div class="login-fields">


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
                            autocomplete="username"
                            maxlength="160"
                            required
                        >

                    </label>


                    <!-- PASSWORD -->

                    <label>

                        Password

                        <div
                            class="password-input-wrap"
                        >

                            <input
                                type="password"
                                name="password"
                                id="loginPassword"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >


                            <button
                                type="button"
                                class="show-password"
                                id="showPasswordButton"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                SHOW
                            </button>

                        </div>

                    </label>

                </div>


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="login-submit"
                >
                    LOGIN
                </button>

            </form>

        </div>


        <!-- CREATE ACCOUNT -->

        <p class="login-register">

            Don't have an account?

            <a href="register.php">
                CREATE ACCOUNT
            </a>

        </p>


        <!-- BACK TO HOMEPAGE -->

        <div class="back-home">

            <a href="index.php">
                ← BACK TO HOMEPAGE
            </a>

        </div>


    </div>

</main>


<!-- ========================================
     SHOW / HIDE PASSWORD
======================================== -->

<script>

const loginPassword =
    document.getElementById(
        "loginPassword"
    );


const showPasswordButton =
    document.getElementById(
        "showPasswordButton"
    );


if (
    loginPassword &&
    showPasswordButton
) {

    showPasswordButton.addEventListener(
        "click",
        function () {

            const isHidden =
                loginPassword.type ===
                "password";


            if (isHidden) {

                loginPassword.type =
                    "text";

                showPasswordButton.textContent =
                    "HIDE";

                showPasswordButton.setAttribute(
                    "aria-label",
                    "Hide password"
                );

                showPasswordButton.setAttribute(
                    "aria-pressed",
                    "true"
                );

            } else {

                loginPassword.type =
                    "password";

                showPasswordButton.textContent =
                    "SHOW";

                showPasswordButton.setAttribute(
                    "aria-label",
                    "Show password"
                );

                showPasswordButton.setAttribute(
                    "aria-pressed",
                    "false"
                );

            }

        }
    );

}

</script>


</body>

</html>