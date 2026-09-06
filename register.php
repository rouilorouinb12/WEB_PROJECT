<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";


/* ========================================
   IF ALREADY LOGGED IN
======================================== */

if (isLoggedIn()) {

    header("Location: book.php");
    exit;
}


$message = "";
$messageType = "";


/* ========================================
   FORM VALUES
======================================== */

$firstName = "";
$middleName = "";
$lastName = "";
$email = "";
$birthdate = "";


/* ========================================
   HANDLE REGISTRATION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF PROTECTION
    ======================================== */

    $csrfToken = $_POST["csrf_token"] ?? null;

    if (!verifyCsrfToken(
        is_string($csrfToken) ? $csrfToken : null
    )) {

        $message = "Invalid form request. Please try again.";
        $messageType = "error";

    } else {

        /* ========================================
           GET FORM VALUES
        ======================================== */

        $firstName =
            trim((string) ($_POST["first_name"] ?? ""));

        $middleName =
            trim((string) ($_POST["middle_name"] ?? ""));

        $lastName =
            trim((string) ($_POST["last_name"] ?? ""));

        $email =
            trim((string) ($_POST["email"] ?? ""));

        $birthdate =
            trim((string) ($_POST["birthdate"] ?? ""));

        $password =
            (string) ($_POST["password"] ?? "");

        $confirmPassword =
            (string) ($_POST["confirm_password"] ?? "");


        /* ========================================
           BASIC VALIDATION
        ======================================== */

        if (
            $firstName === "" ||
            $lastName === "" ||
            $email === "" ||
            $birthdate === "" ||
            $password === "" ||
            $confirmPassword === ""
        ) {

            $message =
                "Please fill in all required fields.";

            $messageType = "error";


        /* ========================================
           NAME VALIDATION
        ======================================== */

        } elseif (
            strlen($firstName) > 80 ||
            strlen($middleName) > 80 ||
            strlen($lastName) > 80
        ) {

            $message =
                "Name fields are too long.";

            $messageType = "error";


        /* ========================================
           EMAIL VALIDATION
        ======================================== */

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                "Please enter a valid email address.";

            $messageType = "error";


        } elseif (strlen($email) > 255) {

            $message =
                "Email address is too long.";

            $messageType = "error";


        /* ========================================
           PASSWORD VALIDATION
        ======================================== */

        } elseif (strlen($password) < 8) {

            $message =
                "Password must contain at least 8 characters.";

            $messageType = "error";


        } elseif (!preg_match('/[a-z]/', $password)) {

            $message =
                "Password must contain at least one lowercase letter (a-z).";

            $messageType = "error";


        } elseif (!preg_match('/[A-Z]/', $password)) {

            $message =
                "Password must contain at least one uppercase letter (A-Z).";

            $messageType = "error";


        } elseif (!preg_match('/[0-9]/', $password)) {

            $message =
                "Password must contain at least one number (0-9).";

            $messageType = "error";


        /* ========================================
           CONFIRM PASSWORD
        ======================================== */

        } elseif ($password !== $confirmPassword) {

            $message =
                "Passwords do not match.";

            $messageType = "error";


        } else {

            /* ========================================
               VALIDATE BIRTHDATE
            ======================================== */

            $birthdateObject =
                DateTime::createFromFormat(
                    "Y-m-d",
                    $birthdate
                );

            $validBirthdate =
                $birthdateObject !== false &&
                $birthdateObject->format("Y-m-d")
                    === $birthdate;


            if (!$validBirthdate) {

                $message =
                    "Please enter a valid birthdate.";

                $messageType = "error";


            } elseif ($birthdate > date("Y-m-d")) {

                $message =
                    "Birthdate cannot be in the future.";

                $messageType = "error";


            } else {

                /* ========================================
                   CHECK EMAIL
                ======================================== */

                $check = $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $check->execute([$email]);


                if ($check->fetch()) {

                    $message =
                        "An account with this email already exists.";

                    $messageType = "error";


                } else {

                    /* ========================================
                       CREATE FULL NAME
                    ======================================== */

                    $fullName = $firstName;

                    if ($middleName !== "") {

                        $fullName .=
                            " " . $middleName;
                    }

                    $fullName .=
                        " " . $lastName;


                    /* ========================================
                       HASH PASSWORD
                    ======================================== */

                    $hashedPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    /* ========================================
                       PHONE
                    ======================================== */

                    $phone = "";


                    /* ========================================
                       INSERT USER
                    ======================================== */

                    $stmt = $conn->prepare("
                        INSERT INTO users (
                            name,
                            first_name,
                            middle_name,
                            last_name,
                            email,
                            birthdate,
                            phone,
                            password
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");


                    try {

                        $stmt->execute([
                            $fullName,
                            $firstName,
                            $middleName,
                            $lastName,
                            $email,
                            $birthdate,
                            $phone,
                            $hashedPassword
                        ]);


                        /* ========================================
                           SUCCESS
                        ======================================== */

                        $message =
                            "Account created successfully! You can now log in.";

                        $messageType = "success";


                        /* Clear form */

                        $firstName = "";
                        $middleName = "";
                        $lastName = "";
                        $email = "";
                        $birthdate = "";


                    } catch (PDOException $e) {

                        /*
                         * Avoid exposing database errors.
                         */

                        $message =
                            "Unable to create the account. Please try again.";

                        $messageType = "error";
                    }
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
        content="Create your Bais Rouilo Gaming Cafe account."
    >

    <title>
        CREATE ACCOUNT | Bais Rouilo Gaming Cafe
    </title>


    <!-- ========================================
         GOOGLE FONTS
    ======================================== -->

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


    <!-- ========================================
         REGISTER PAGE FIXES
    ======================================== -->

    <style>

        .booking-form .birthdate-field {
            display: grid;
            grid-template-rows: auto 40px;
            gap: 7px;
            margin: 0;
            padding: 0;
            align-content: start;
        }

        .booking-form .birthdate-field input[type="date"] {
            width: 100%;
            height: 40px;
            min-height: 40px;
            max-height: 40px;
            margin: 0;
            padding: 0 12px;
            border: 1px solid #333;
            border-radius: 4px;
            color: #fff;
            background: #070707;
            outline: none;
            font-family: "Montserrat", Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            box-sizing: border-box;
        }

        .booking-form .birthdate-field input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            margin-left: auto;
        }

        .booking-form .birthdate-field input[type="date"]:focus {
            border-color: var(--green);
            box-shadow: 0 0 10px rgba(57, 255, 20, .2);
        }


        /* ========================================
           PASSWORD REQUIREMENTS
           DOES NOT CHANGE FORM LAYOUT
        ======================================== */

        .booking-form .password-field {
            position: relative;
        }

        .booking-form .password-field .password-requirements {
            position: absolute;
            top: 70px;
            left: 0;
            width: 100%;
            margin: 0;
            padding: 0;
            z-index: 5;
        }

        .password-requirements {
            font-family: Montserrat, sans-serif;
            font-size: 11px;
            line-height: 1.5;
        }

        .requirement {
            color: #777;
            transition: 0.2s ease;
            margin: 0 0 4px;
        }

        .requirement span {
            display: inline-block;
            width: 18px;
            font-weight: 800;
            color: #777;
        }

        .requirement.valid {
            color: #39FF14;
        }

        .requirement.valid span {
            color: #39FF14;
        }

        .requirement.invalid {
            color: #777;
        }

        .requirement.invalid span {
            color: #777;
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
     CREATE ACCOUNT PAGE
======================================== -->

<main class="inner-page">

    <div class="container form-page">


        <p class="section-kicker">
            CUSTOMER ACCOUNT
        </p>


        <h1 class="page-title">
            CREATE <span>ACCOUNT</span>
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
             REGISTRATION FORM
        ======================================== -->

        <form
            action="register.php"
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


            <!-- FIRST NAME / LAST NAME -->

            <div class="form-row">

                <label>

                    First Name

                    <input
                        type="text"
                        name="first_name"
                        value="<?= htmlspecialchars(
                            $firstName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter your first name"
                        maxlength="80"
                        autocomplete="given-name"
                        required
                    >

                </label>


                <label>

                    Last Name

                    <input
                        type="text"
                        name="last_name"
                        value="<?= htmlspecialchars(
                            $lastName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter your last name"
                        maxlength="80"
                        autocomplete="family-name"
                        required
                    >

                </label>

            </div>


            <!-- MIDDLE NAME / EMAIL -->

            <div class="form-row">

                <label>

                    Middle Name

                    <input
                        type="text"
                        name="middle_name"
                        value="<?= htmlspecialchars(
                            $middleName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter your middle name"
                        maxlength="80"
                        autocomplete="additional-name"
                    >

                </label>


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
                        maxlength="255"
                        autocomplete="email"
                        required
                    >

                </label>

            </div>


            <!-- BIRTHDATE / PASSWORD -->

            <div class="form-row">

                <!-- BIRTHDATE -->

                <label class="birthdate-field">

                    Birthdate

                    <input
                        type="date"
                        id="birthdate"
                        name="birthdate"
                        value="<?= htmlspecialchars(
                            $birthdate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        max="<?= date("Y-m-d") ?>"
                        autocomplete="bday"
                        required
                    >

                </label>


                <!-- PASSWORD -->

                <label class="password-field">

                    Password

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="new-password"
                        required
                    >


                    <!-- PASSWORD REQUIREMENTS -->

                    <div class="password-requirements">

                        <div
                            id="length-check"
                            class="requirement"
                        >

                            <span>✓</span>
                            contains at least 8 characters

                        </div>


                        <div
                            id="case-check"
                            class="requirement"
                        >

                            <span>✓</span>
                            contains both lower (a-z) and upper case letters (A-Z)

                        </div>


                        <div
                            id="number-check"
                            class="requirement"
                        >

                            <span>✓</span>
                            contains at least one number (0-9)

                        </div>

                    </div>

                </label>

            </div>


            <!-- CONFIRM PASSWORD -->

            <div class="form-row">

                <label>

                    Confirm Password

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                        required
                    >

                </label>

                <div></div>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="green-button"
            >
                SUBMIT
            </button>

        </form>


        <!-- LOGIN LINK -->

        <p
            style="
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
            "
        >

            Already have an account?

            <a
                href="login.php"
                style="color:#39FF14;"
            >
                LOGIN HERE
            </a>

        </p>

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


/* ========================================
   BIRTHDATE CALENDAR
======================================== */

const birthdateInput =
    document.getElementById("birthdate");


if (birthdateInput) {

    birthdateInput.addEventListener(
        "click",
        function () {

            if (
                typeof this.showPicker ===
                "function"
            ) {

                try {

                    this.showPicker();

                } catch (error) {

                    // Native date picker
                    // will still work.

                }

            }

        }
    );

}


/* ========================================
   PASSWORD REQUIREMENTS
======================================== */

const passwordInput =
    document.getElementById("password");

const lengthCheck =
    document.getElementById("length-check");

const caseCheck =
    document.getElementById("case-check");

const numberCheck =
    document.getElementById("number-check");


if (passwordInput) {

    passwordInput.addEventListener(
        "input",
        function () {

            const password =
                this.value;


            const hasLength =
                password.length >= 8;


            const hasLowercase =
                /[a-z]/.test(password);


            const hasUppercase =
                /[A-Z]/.test(password);


            const hasBothCase =
                hasLowercase &&
                hasUppercase;


            const hasNumber =
                /[0-9]/.test(password);


            /* LENGTH */

            if (hasLength) {

                lengthCheck.classList.add("valid");
                lengthCheck.classList.remove("invalid");

            } else {

                lengthCheck.classList.remove("valid");
                lengthCheck.classList.add("invalid");

            }


            /* CASE */

            if (hasBothCase) {

                caseCheck.classList.add("valid");
                caseCheck.classList.remove("invalid");

            } else {

                caseCheck.classList.remove("valid");
                caseCheck.classList.add("invalid");

            }


            /* NUMBER */

            if (hasNumber) {

                numberCheck.classList.add("valid");
                numberCheck.classList.remove("invalid");

            } else {

                numberCheck.classList.remove("valid");
                numberCheck.classList.add("invalid");

            }

        }
    );

}

</script>


</body>
</html>