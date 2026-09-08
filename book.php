<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

requireLogin();


/* ========================================
   REQUIRE CUSTOMER LOGIN
======================================== */

$userId = currentUserId();


/* ========================================
   GET CURRENT CUSTOMER
======================================== */

$userStmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone
    FROM users
    WHERE id = ?
    LIMIT 1
");

$userStmt->execute([
    $userId
]);

$user = $userStmt->fetch();


/* ========================================
   SAFETY CHECK
======================================== */

if (!$user) {

    header("Location: logout.php");
    exit;
}


/* ========================================
   INITIAL VALUES
======================================== */

$message = "";
$messageType = "";

$setupId = "";
$bookingDate = "";
$bookingTime = "";
$hours = 1;
$notes = "";

$phone = trim(
    (string)($user["phone"] ?? "")
);


/* ========================================
   PAYMENT VALUES
======================================== */

$paymentMethod = "";
$paymentReference = "";


/* ========================================
   HANDLE BOOKING
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* ========================================
       CSRF CHECK
    ======================================== */

    $csrfToken =
        $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $message =
            "Invalid security token. Please refresh the page and try again.";

        $messageType =
            "error";

    } else {


        /* ========================================
           GET FORM DATA
        ======================================== */

        $setupId = trim(
            (string)(
                $_POST["setup_id"] ?? ""
            )
        );

        $bookingDate = trim(
            (string)(
                $_POST["booking_date"] ?? ""
            )
        );

        $bookingTime = trim(
            (string)(
                $_POST["booking_time"] ?? ""
            )
        );

        $hours =
            (int)(
                $_POST["hours"] ?? 1
            );

        $notes = trim(
            (string)(
                $_POST["notes"] ?? ""
            )
        );

        $phone = trim(
            (string)(
                $_POST["phone"] ?? ""
            )
        );


        /* ========================================
           PAYMENT DATA
        ======================================== */

        $paymentMethod = trim(
            (string)(
                $_POST["payment_method"] ?? ""
            )
        );

        $paymentReference = trim(
            (string)(
                $_POST["payment_reference"] ?? ""
            )
        );


        /* ========================================
           VALIDATION
        ======================================== */

        if (
            $setupId === "" ||
            $bookingDate === "" ||
            $bookingTime === ""
        ) {

            $message =
                "Please complete all required booking fields.";

            $messageType =
                "error";

        } elseif (
            !in_array(
                (int)$setupId,
                [1, 2, 3, 4],
                true
            )
        ) {

            $message =
                "Please select a valid gaming station.";

            $messageType =
                "error";

        } elseif (
            $hours < 1 ||
            $hours > 12
        ) {

            $message =
                "Booking duration must be between 1 and 12 hours.";

            $messageType =
                "error";

        } elseif (
            strlen($phone) > 30
        ) {

            $message =
                "Phone number is too long.";

            $messageType =
                "error";

        } elseif (
            $phone !== "" &&
            !preg_match(
                '/^[0-9+\-\s().]+$/',
                $phone
            )
        ) {

            $message =
                "Please enter a valid phone number.";

            $messageType =
                "error";

        } elseif (
            strlen($notes) > 1000
        ) {

            $message =
                "Notes are too long.";

            $messageType =
                "error";

        } elseif (
            $paymentMethod !== "GCash" &&
            $paymentMethod !== "Cash"
        ) {

            $message =
                "Please select a valid payment method.";

            $messageType =
                "error";

        } elseif (
            $paymentMethod === "GCash" &&
            $paymentReference === ""
        ) {

            $message =
                "Please enter your GCash reference number.";

            $messageType =
                "error";

        } elseif (
            strlen($paymentReference) > 100
        ) {

            $message =
                "Payment reference number is too long.";

            $messageType =
                "error";

        } else {


            /* ========================================
               VALIDATE DATE
            ======================================== */

            $dateObject =
                DateTime::createFromFormat(
                    "Y-m-d",
                    $bookingDate
                );

            $validDate =
                $dateObject !== false &&
                $dateObject->format("Y-m-d") ===
                    $bookingDate;


            if (!$validDate) {

                $message =
                    "Please select a valid booking date.";

                $messageType =
                    "error";

            } elseif (
                $bookingDate < date("Y-m-d")
            ) {

                $message =
                    "You cannot book a date in the past.";

                $messageType =
                    "error";

            } else {


                /* ========================================
                   VALIDATE TIME
                ======================================== */

                $timeObject =
                    DateTime::createFromFormat(
                        "H:i",
                        $bookingTime
                    );

                $validTime =
                    $timeObject !== false &&
                    $timeObject->format("H:i") ===
                        $bookingTime;


                if (!$validTime) {

                    $message =
                        "Please select a valid booking time.";

                    $messageType =
                        "error";

                } else {


                    /* ========================================
                       CHECK IF SETUP EXISTS
                    ======================================== */

                    $setupStmt = $conn->prepare("
                        SELECT
                            id,
                            name
                        FROM gaming_setups
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $setupStmt->execute([
                        (int)$setupId
                    ]);

                    $setup =
                        $setupStmt->fetch();


                    if (!$setup) {

                        $message =
                            "The selected gaming station is not available.";

                        $messageType =
                            "error";

                    } else {


                        /* ========================================
                           CHECK BOOKING CONFLICT
                        ======================================== */

                        $checkStmt = $conn->prepare("
                            SELECT
                                id
                            FROM bookings
                            WHERE setup_id = ?
                              AND booking_date = ?
                              AND status IN ('pending', 'accepted')
                              AND (
                                    start_time < ADDTIME(
                                        ?,
                                        SEC_TO_TIME(? * 3600)
                                    )
                                    AND
                                    ADDTIME(
                                        start_time,
                                        SEC_TO_TIME(hours * 3600)
                                    ) > ?
                              )
                            LIMIT 1
                        ");

                        $checkStmt->execute([
                            (int)$setupId,
                            $bookingDate,
                            $bookingTime,
                            $hours,
                            $bookingTime
                        ]);


                        if ($checkStmt->fetch()) {

                            $message =
                                "That gaming station is already booked for the selected date and time.";

                            $messageType =
                                "error";

                        } else {


                            /* ========================================
                               UPDATE USER PHONE
                            ======================================== */

                            $updatePhoneStmt =
                                $conn->prepare("
                                    UPDATE users
                                    SET phone = ?
                                    WHERE id = ?
                                ");

                            $updatePhoneStmt->execute([
                                $phone,
                                (int)$user["id"]
                            ]);


                            /* ========================================
                               CUSTOMER NAME
                            ======================================== */

                            $customerName =
                                trim(
                                    (string)(
                                        $user["name"] ?? ""
                                    )
                                );


                            if (
                                $customerName === ""
                            ) {

                                $message =
                                    "Your account name is invalid. Please contact the administrator.";

                                $messageType =
                                    "error";

                            } else {


                                /* ========================================
                                   PAYMENT STATUS
                                ======================================== */

                                $paymentStatus =
                                    $paymentMethod === "GCash"
                                        ? "pending"
                                        : "unpaid";


                                /* ========================================
                                   SAVE BOOKING
                                ======================================== */

                                $insertStmt =
                                    $conn->prepare("
                                        INSERT INTO bookings (
                                            customer_name,
                                            email,
                                            phone,
                                            setup_id,
                                            booking_date,
                                            start_time,
                                            hours,
                                            message,
                                            status,
                                            payment_method,
                                            payment_reference,
                                            payment_status
                                        )
                                        VALUES (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            'pending',
                                            ?,
                                            ?,
                                            ?
                                        )
                                    ");


                                $insertStmt->execute([

                                    $customerName,

                                    (string)
                                    $user["email"],

                                    $phone !== ""
                                        ? $phone
                                        : null,

                                    (int)$setupId,

                                    $bookingDate,

                                    $bookingTime,

                                    $hours,

                                    $notes !== ""
                                        ? $notes
                                        : null,

                                    $paymentMethod,

                                    $paymentReference !== ""
                                        ? $paymentReference
                                        : null,

                                    $paymentStatus

                                ]);


                                /* ========================================
                                   GET NEW BOOKING ID
                                ======================================== */

                                $bookingId =
                                    (int)
                                    $conn->lastInsertId();


                                /* ========================================
                                   SUCCESS PAGE
                                ======================================== */

                                header(
                                    "Location: success.php?id="
                                    . $bookingId
                                );

                                exit;

                            }
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
        content="Book your gaming station at Bais Rouilo Gaming Cafe."
    >

    <title>
        BOOK NOW | Bais Rouilo Gaming Cafe
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
           PAYMENT
        ======================================== */

        .payment-section {

            margin-top:
                25px;

            padding-top:
                25px;

            border-top:
                1px solid
                rgba(57,255,20,.25);

        }


        .payment-section h2 {

            margin:
                0 0 20px;

            color:
                #39FF14;

            font-family:
                "Orbitron",
                sans-serif;

            font-size:
                18px;

        }


        .payment-note {

            margin:
                0 0 18px;

            padding:
                12px 14px;

            border:
                1px solid
                rgba(57,255,20,.3);

            background:
                rgba(57,255,20,.03);

            color:
                #ccc;

            font-size:
                13px;

            line-height:
                1.5;

        }


        .payment-note strong {

            color:
                #39FF14;

        }


        /* ========================================
           MOBILE
        ======================================== */

        @media (max-width: 700px) {

            .payment-section {

                padding-top:
                    20px;

            }

        }

    </style>

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



<main class="inner-page">

    <div class="container form-page">


        <!-- ========================================
             TITLE
        ========================================= -->

        <p class="section-kicker">

            CUSTOMER BOOKING

        </p>


        <h1 class="page-title">

            BOOK
            <span>
                NOW
            </span>

        </h1>



        <!-- ========================================
             CUSTOMER INFORMATION
        ========================================= -->

        <div
            class="booking-form"
            style="margin-bottom:25px;"
        >

            <h2
                style="
                    margin:0 0 20px;
                    color:#39FF14;
                    font-family:'Orbitron',sans-serif;
                    font-size:18px;
                "
            >

                CUSTOMER INFORMATION

            </h2>


            <div class="form-row">


                <!-- FULL NAME -->

                <label>

                    Full Name

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            (string)
                            $user["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


                <!-- EMAIL -->

                <label>

                    Email Address

                    <input
                        type="email"
                        value="<?= htmlspecialchars(
                            (string)
                            $user["email"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        readonly
                    >

                </label>


            </div>


            <div class="form-row">


                <!-- PHONE -->

                <label>

                    Phone Number

                    <input
                        type="text"
                        name="phone"
                        form="bookingForm"
                        value="<?= htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter your phone number"
                        maxlength="30"
                    >

                </label>


                <!-- ACCOUNT -->

                <label>

                    Account

                    <input
                        type="text"
                        value="Logged in"
                        readonly
                    >

                </label>


            </div>

        </div>



        <!-- ========================================
             MESSAGE
        ========================================= -->

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
             BOOKING FORM
        ========================================= -->

        <form
            action="book.php"
            method="POST"
            class="booking-form"
            id="bookingForm"
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



            <!-- ========================================
                 GAMING STATION / DATE
            ========================================= -->

            <div class="form-row">


                <!-- GAMING STATION -->

                <label>

                    Gaming Station

                    <select
                        name="setup_id"
                        required
                    >

                        <option value="">
                            Select a station
                        </option>


                        <option
                            value="1"
                            <?= (
                                (string)$setupId === "1"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            RTX Gaming PC

                        </option>


                        <option
                            value="2"
                            <?= (
                                (string)$setupId === "2"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            Premium Gaming PC

                        </option>


                        <option
                            value="3"
                            <?= (
                                (string)$setupId === "3"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            Streamer Setup

                        </option>


                        <option
                            value="4"
                            <?= (
                                (string)$setupId === "4"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            VIP Room

                        </option>

                    </select>

                </label>


                <!-- BOOKING DATE -->

                <label>

                    Booking Date

                    <input
                        type="date"
                        name="booking_date"
                        value="<?= htmlspecialchars(
                            $bookingDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        min="<?= date("Y-m-d") ?>"
                        required
                    >

                </label>


            </div>



            <!-- ========================================
                 TIME / HOURS
            ========================================= -->

            <div class="form-row">


                <!-- TIME -->

                <label>

                    Booking Time

                    <input
                        type="time"
                        name="booking_time"
                        value="<?= htmlspecialchars(
                            $bookingTime,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </label>


                <!-- HOURS -->

                <label>

                    Duration

                    <select
                        name="hours"
                        required
                    >

                        <?php for (
                            $i = 1;
                            $i <= 12;
                            $i++
                        ): ?>

                            <option
                                value="<?= $i ?>"
                                <?= (
                                    (int)$hours === $i
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= $i ?>
                                Hour<?= $i > 1 ? "s" : "" ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                </label>


            </div>



            <!-- ========================================
                 NOTES
            ========================================= -->

            <label>

                Notes

                <textarea
                    name="notes"
                    rows="5"
                    maxlength="1000"
                    placeholder="Optional notes or special requests..."
                ><?= htmlspecialchars(
                    $notes,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

            </label>



            <!-- ========================================
                 PAYMENT
            ========================================= -->

            <div class="payment-section">


                <h2>

                    PAYMENT

                </h2>


                <p class="payment-note">

                    <strong>
                        GCash:
                    </strong>

                    Send your payment to the gaming cafe
                    GCash account, then enter your
                    <strong>
                        reference number
                    </strong>.

                    <br><br>

                    <strong>
                        Cash:
                    </strong>

                    Select Cash if you will pay directly
                    at the cafe.

                </p>


                <div class="form-row">


                    <!-- PAYMENT METHOD -->

                    <label>

                        Payment Method

                        <select
                            name="payment_method"
                            id="paymentMethod"
                            required
                        >

                            <option value="">

                                Select payment method

                            </option>


                            <option
                                value="GCash"
                                <?= $paymentMethod === "GCash"
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                GCash

                            </option>


                            <option
                                value="Cash"
                                <?= $paymentMethod === "Cash"
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                Cash

                            </option>

                        </select>

                    </label>


                    <!-- GCASH REFERENCE -->

                    <label
                        id="referenceLabel"
                    >

                        GCash Reference Number

                        <input
                            type="text"
                            name="payment_reference"
                            id="paymentReference"
                            value="<?= htmlspecialchars(
                                $paymentReference,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="100"
                            placeholder="Enter GCash reference number"
                        >

                    </label>


                </div>

            </div>



            <!-- ========================================
                 CONFIRM
            ========================================= -->

            <button
                type="submit"
                class="green-button"
                style="
                    margin-top:20px;
                    padding:13px 25px;
                "
            >

                CONFIRM BOOKING

            </button>


        </form>


    </div>

</main>



<script>

/* ========================================
   PAYMENT FIELD CONTROL
======================================== */

const paymentMethod =
    document.getElementById(
        "paymentMethod"
    );


const referenceLabel =
    document.getElementById(
        "referenceLabel"
    );


const paymentReference =
    document.getElementById(
        "paymentReference"
    );


function updatePaymentFields() {

    if (!paymentMethod) {
        return;
    }


    const isGCash =
        paymentMethod.value === "GCash";


    /* SHOW / HIDE REFERENCE */

    if (referenceLabel) {

        referenceLabel.style.display =
            isGCash
                ? ""
                : "none";

    }


    /* REQUIRED ONLY FOR GCASH */

    if (paymentReference) {

        paymentReference.required =
            isGCash;


        if (!isGCash) {

            paymentReference.value =
                "";

        }

    }

}


if (paymentMethod) {

    paymentMethod.addEventListener(
        "change",
        updatePaymentFields
    );

    updatePaymentFields();

}


/* ========================================
   MOBILE MENU
======================================== */

const menuToggle =
    document.querySelector(
        ".menu-toggle"
    );


const mainNav =
    document.querySelector(
        ".main-nav"
    );


if (
    menuToggle &&
    mainNav
) {

    menuToggle.addEventListener(
        "click",
        function () {

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