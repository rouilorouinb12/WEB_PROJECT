<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";


/* ========================================
   IF ALREADY LOGGED IN
======================================== */

if (isLoggedIn()) {

    $role =
        (string)(
            $_SESSION["user_role"] ??
            "customer"
        );


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

    $csrfToken =
        $_POST["csrf_token"] ?? null;


    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $message =
            "Invalid form request. Please try again.";

        $messageType =
            "error";


    } else {


        /* ========================================
           GET FORM VALUES
        ======================================== */

        $firstName =
            trim(
                (string)(
                    $_POST["first_name"] ?? ""
                )
            );


        $middleName =
            trim(
                (string)(
                    $_POST["middle_name"] ?? ""
                )
            );


        $lastName =
            trim(
                (string)(
                    $_POST["last_name"] ?? ""
                )
            );


        $email =
            strtolower(
                trim(
                    (string)(
                        $_POST["email"] ?? ""
                    )
                )
            );


        $birthdate =
            trim(
                (string)(
                    $_POST["birthdate"] ?? ""
                )
            );


        $password =
            (string)(
                $_POST["password"] ?? ""
            );


        $confirmPassword =
            (string)(
                $_POST["confirm_password"] ?? ""
            );


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

            $messageType =
                "error";


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

            $messageType =
                "error";


        } elseif (
            !preg_match(
                '/^[a-zA-ZÀ-ÿ .\'-]+$/u',
                $firstName
            )
        ) {

            $message =
                "First name contains invalid characters.";

            $messageType =
                "error";


        } elseif (
            !preg_match(
                '/^[a-zA-ZÀ-ÿ .\'-]+$/u',
                $lastName
            )
        ) {

            $message =
                "Last name contains invalid characters.";

            $messageType =
                "error";


        } elseif (
            $middleName !== "" &&
            !preg_match(
                '/^[a-zA-ZÀ-ÿ .\'-]+$/u',
                $middleName
            )
        ) {

            $message =
                "Middle name contains invalid characters.";

            $messageType =
                "error";


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

            $messageType =
                "error";


        } elseif (
            strlen($email) > 160
        ) {

            $message =
                "Email address is too long.";

            $messageType =
                "error";


        /* ========================================
           PASSWORD VALIDATION
        ======================================== */

        } elseif (
            strlen($password) < 8
        ) {

            $message =
                "Password must contain at least 8 characters.";

            $messageType =
                "error";


        } elseif (
            !preg_match(
                '/[a-z]/',
                $password
            )
        ) {

            $message =
                "Password must contain at least one lowercase letter (a-z).";

            $messageType =
                "error";


        } elseif (
            !preg_match(
                '/[A-Z]/',
                $password
            )
        ) {

            $message =
                "Password must contain at least one uppercase letter (A-Z).";

            $messageType =
                "error";


        } elseif (
            !preg_match(
                '/[0-9]/',
                $password
            )
        ) {

            $message =
                "Password must contain at least one number (0-9).";

            $messageType =
                "error";


        /* ========================================
           CONFIRM PASSWORD
        ======================================== */

        } elseif (
            $password !==
            $confirmPassword
        ) {

            $message =
                "Passwords do not match.";

            $messageType =
                "error";


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
                $birthdateObject->format(
                    "Y-m-d"
                ) === $birthdate;


            if (!$validBirthdate) {

                $message =
                    "Please enter a valid birthdate.";

                $messageType =
                    "error";


            } elseif (
                $birthdate > date("Y-m-d")
            ) {

                $message =
                    "Birthdate cannot be in the future.";

                $messageType =
                    "error";


            } else {


                /* ========================================
                   CHECK EMAIL
                ======================================== */

                $check =
                    $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE email = ?
                        LIMIT 1
                    ");


                $check->execute([
                    $email
                ]);


                if ($check->fetch()) {

                    $message =
                        "An account with this email already exists.";

                    $messageType =
                        "error";


                } else {


                    /* ========================================
                       CREATE FULL NAME
                    ======================================== */

                    $fullName =
                        $firstName;


                    if ($middleName !== "") {

                        $fullName .=
                            " " .
                            $middleName;
                    }


                    $fullName .=
                        " " .
                        $lastName;


                    /* ========================================
                       HASH PASSWORD
                    ======================================== */

                    $hashedPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    if ($hashedPassword === false) {

                        $message =
                            "Unable to secure your password. Please try again.";

                        $messageType =
                            "error";


                    } else {


                        /* ========================================
                           PHONE
                        ======================================== */

                        $phone = "";


                        /* ========================================
                           INSERT USER
                        ======================================== */

                        try {

                            $stmt =
                                $conn->prepare("
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


                            $stmt->execute([
                                $fullName,
                                $firstName,
                                $middleName !== ""
                                    ? $middleName
                                    : null,
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

                            $messageType =
                                "success";


                            /* ========================================
                               CLEAR FORM
                            ======================================== */

                            $firstName = "";

                            $middleName = "";

                            $lastName = "";

                            $email = "";

                            $birthdate = "";


                        } catch (PDOException $e) {

                            $message =
                                "Unable to create the account. Please try again.";

                            $messageType =
                                "error";
                        }
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


    <style>

        /* ========================================
           ACCOUNT BACKGROUND
        ======================================== */

        body {

            background:

                linear-gradient(
                    rgba(0, 0, 0, .72),
                    rgba(0, 0, 0, .72)
                ),

                url("assets/images/account-bg.png");

            background-position:
                center center;

            background-size:
                cover;

            background-repeat:
                no-repeat;

            background-attachment:
                fixed;

            background-color:
                #000;

        }


        .site-header {

            background:
                rgba(0, 0, 0, .82);

            backdrop-filter:
                blur(4px);

        }


        /* ========================================
           REGISTER HEADER
           LOGO ONLY
        ======================================== */

        .register-header {

            justify-content:
                flex-start;

        }


        /* ========================================
           BIRTHDATE
        ======================================== */

        .booking-form
        .birthdate-field {

            display:
                grid;

            grid-template-rows:
                auto 40px;

            gap:
                7px;

            margin:
                0;

            padding:
                0;

            align-content:
                start;

        }


        .booking-form
        .birthdate-field
        input[type="date"] {

            width:
                100%;

            height:
                40px;

            min-height:
                40px;

            max-height:
                40px;

            margin:
                0;

            padding:
                0 12px;

            border:
                1px solid #333;

            border-radius:
                4px;

            color:
                #fff;

            background:
                rgba(7, 7, 7, .94);

            outline:
                none;

            font-family:
                "Montserrat",
                Arial,
                sans-serif;

            font-size:
                11px;

            font-weight:
                600;

            box-sizing:
                border-box;

        }


        .booking-form
        .birthdate-field
        input[type="date"]::-webkit-calendar-picker-indicator {

            cursor:
                pointer;

            margin-left:
                auto;

        }


        .booking-form
        .birthdate-field
        input[type="date"]:focus {

            border-color:
                var(--green);

            box-shadow:
                0 0 10px
                rgba(57,255,20,.2);

        }


        /* ========================================
           PASSWORD FIELD
        ======================================== */

        .booking-form
        .password-field {

            position:
                relative;

        }


        /* ========================================
           PASSWORD INPUT WRAPPER
        ======================================== */

        .password-input-wrap {

            position:
                relative;

            width:
                100%;

        }


        .password-input-wrap input {

            width:
                100%;

            height:
                40px;

            min-height:
                40px;

            padding:
                0 82px 0 12px;

            border:
                1px solid #333;

            border-radius:
                4px;

            color:
                #fff;

            background:
                rgba(7, 7, 7, .94);

            outline:
                none;

            box-sizing:
                border-box;

            font-family:
                "Montserrat",
                Arial,
                sans-serif;

            font-size:
                11px;

            font-weight:
                600;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;

        }


        .password-input-wrap input::placeholder {

            color:
                #777;

        }


        .password-input-wrap input:focus {

            border-color:
                var(--green);

            box-shadow:
                0 0 10px
                rgba(57,255,20,.2);

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

            font-family:
                "Orbitron",
                sans-serif;

            font-size:
                8px;

            font-weight:
                800;

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


        .show-password:active {

            color:
                var(--green);

        }


        /* ========================================
           PASSWORD REQUIREMENTS
        ======================================== */

        .booking-form
        .password-field
        .password-requirements {

            position:
                absolute;

            top:
                70px;

            left:
                0;

            width:
                100%;

            margin:
                0;

            padding:
                0;

            z-index:
                5;

        }


        .password-requirements {

            font-family:
                Montserrat,
                sans-serif;

            font-size:
                11px;

            line-height:
                1.5;

        }


        .requirement {

            color:
                #777;

            transition:
                .2s ease;

            margin:
                0 0 4px;

        }


        .requirement span {

            display:
                inline-block;

            width:
                18px;

            font-weight:
                800;

            color:
                #777;

        }


        .requirement.valid {

            color:
                #39FF14;

        }


        .requirement.valid span {

            color:
                #39FF14;

        }


        .requirement.invalid {

            color:
                #777;

        }


        .requirement.invalid span {

            color:
                #777;

        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .password-input-wrap input {

                height:
                    40px;

            }


            .show-password {

                font-size:
                    8px;

                right:
                    8px;

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

    <div
        class="container nav-container register-header"
    >


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

            CREATE

            <span>
                ACCOUNT
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
             REGISTRATION FORM
        ======================================== -->

        <form
            action="register.php"
            method="POST"
            class="booking-form"
            id="registerForm"
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


            <!-- ========================================
                 FIRST NAME / LAST NAME
            ======================================== -->

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


            <!-- ========================================
                 MIDDLE NAME / EMAIL
            ======================================== -->

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
                        maxlength="160"
                        autocomplete="email"
                        required
                    >

                </label>


            </div>


            <!-- ========================================
                 BIRTHDATE / PASSWORD
            ======================================== -->

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


                    <div
                        class="password-input-wrap"
                    >

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="show-password"
                            data-target="password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >

                            SHOW

                        </button>

                    </div>


                    <!-- PASSWORD REQUIREMENTS -->

                    <div
                        class="password-requirements"
                    >


                        <div
                            id="length-check"
                            class="requirement"
                        >

                            <span>
                                ✓
                            </span>

                            contains at least 8 characters

                        </div>


                        <div
                            id="case-check"
                            class="requirement"
                        >

                            <span>
                                ✓
                            </span>

                            contains both lower (a-z) and upper case letters (A-Z)

                        </div>


                        <div
                            id="number-check"
                            class="requirement"
                        >

                            <span>
                                ✓
                            </span>

                            contains at least one number (0-9)

                        </div>


                    </div>

                </label>


            </div>


            <!-- ========================================
                 CONFIRM PASSWORD
            ======================================== -->

            <div class="form-row">


                <label>

                    Confirm Password


                    <div
                        class="password-input-wrap"
                    >

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >


                        <button
                            type="button"
                            class="show-password"
                            data-target="confirm_password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >

                            SHOW

                        </button>

                    </div>

                </label>


                <div></div>


            </div>


            <!-- ========================================
                 SUBMIT
            ======================================== -->

            <button
                type="submit"
                class="green-button"
            >

                SUBMIT

            </button>


        </form>


        <!-- ========================================
             LOGIN LINK
        ======================================== -->

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
     BIRTHDATE + PASSWORD SCRIPTS
======================================== -->

<script>


/* ========================================
   BIRTHDATE CALENDAR
======================================== */

const birthdateInput =
    document.getElementById(
        "birthdate"
    );


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

                    /*
                     * Native date picker
                     * will still work.
                     */

                }

            }

        }
    );

}


/* ========================================
   PASSWORD REQUIREMENTS
======================================== */

const passwordInput =
    document.getElementById(
        "password"
    );


const lengthCheck =
    document.getElementById(
        "length-check"
    );


const caseCheck =
    document.getElementById(
        "case-check"
    );


const numberCheck =
    document.getElementById(
        "number-check"
    );


function updatePasswordRequirements() {

    if (
        !passwordInput ||
        !lengthCheck ||
        !caseCheck ||
        !numberCheck
    ) {

        return;

    }


    const password =
        passwordInput.value;


    const hasLength =
        password.length >= 8;


    const hasLowercase =
        /[a-z]/.test(
            password
        );


    const hasUppercase =
        /[A-Z]/.test(
            password
        );


    const hasBothCase =
        hasLowercase &&
        hasUppercase;


    const hasNumber =
        /[0-9]/.test(
            password
        );


    /* LENGTH */

    lengthCheck.classList.toggle(
        "valid",
        hasLength
    );


    lengthCheck.classList.toggle(
        "invalid",
        !hasLength
    );


    /* CASE */

    caseCheck.classList.toggle(
        "valid",
        hasBothCase
    );


    caseCheck.classList.toggle(
        "invalid",
        !hasBothCase
    );


    /* NUMBER */

    numberCheck.classList.toggle(
        "valid",
        hasNumber
    );


    numberCheck.classList.toggle(
        "invalid",
        !hasNumber
    );

}


if (passwordInput) {

    passwordInput.addEventListener(
        "input",
        updatePasswordRequirements
    );


    updatePasswordRequirements();

}


/* ========================================
   CONFIRM PASSWORD CHECK
======================================== */

const registerForm =
    document.getElementById(
        "registerForm"
    );


const confirmPasswordInput =
    document.getElementById(
        "confirm_password"
    );


if (
    registerForm &&
    passwordInput &&
    confirmPasswordInput
) {


    registerForm.addEventListener(
        "submit",
        function (event) {


            if (
                passwordInput.value !==
                confirmPasswordInput.value
            ) {

                event.preventDefault();


                confirmPasswordInput.setCustomValidity(
                    "Passwords do not match."
                );


                confirmPasswordInput.reportValidity();


            } else {

                confirmPasswordInput.setCustomValidity(
                    ""
                );

            }

        }
    );


    confirmPasswordInput.addEventListener(
        "input",
        function () {


            if (
                passwordInput.value !==
                confirmPasswordInput.value
            ) {

                confirmPasswordInput.setCustomValidity(
                    "Passwords do not match."
                );


            } else {

                confirmPasswordInput.setCustomValidity(
                    ""
                );

            }

        }
    );

}


/* ========================================
   SHOW / HIDE PASSWORD
======================================== */

document.querySelectorAll(
    ".show-password"
).forEach(
    function (button) {


        button.addEventListener(
            "click",
            function () {


                const targetId =
                    button.getAttribute(
                        "data-target"
                    );


                const target =
                    document.getElementById(
                        targetId
                    );


                if (!target) {

                    return;

                }


                const isHidden =
                    target.type ===
                    "password";


                if (isHidden) {

                    target.type =
                        "text";


                    button.textContent =
                        "HIDE";


                    button.setAttribute(
                        "aria-label",
                        "Hide password"
                    );


                    button.setAttribute(
                        "aria-pressed",
                        "true"
                    );


                } else {

                    target.type =
                        "password";


                    button.textContent =
                        "SHOW";


                    button.setAttribute(
                        "aria-label",
                        "Show password"
                    );


                    button.setAttribute(
                        "aria-pressed",
                        "false"
                    );

                }

            }
        );

    }
);


</script>


</body>

</html>