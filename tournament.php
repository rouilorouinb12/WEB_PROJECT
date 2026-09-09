<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

requireLogin();

$userId = currentUserId();

if (!$userId) {
    header("Location: login.php");
    exit;
}


/*
========================================
GET CURRENT USER
========================================
*/

$userStmt = $conn->prepare("

    SELECT

        id,
        name,
        email,
        phone,
        role

    FROM users

    WHERE id = :id

    LIMIT 1

");

$userStmt->execute([
    ":id" => $userId
]);

$user = $userStmt->fetch();


if (!$user) {

    header("Location: logout.php");
    exit;

}


/*
========================================
CHECK ADMIN
========================================
*/

$userRole = strtolower(
    trim(
        (string)($user["role"] ?? "")
    )
);


/*
========================================
ADMIN IS NOT ALLOWED TO JOIN
========================================
*/

if ($userRole === "admin") {

    header("Location: index.php#tournaments");
    exit;

}


/*
========================================
GET TOURNAMENT ID
========================================
*/

$tournamentId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$tournamentId) {

    header("Location: index.php#tournaments");
    exit;

}


/*
========================================
GET TOURNAMENT
========================================
*/

$stmt = $conn->prepare("

    SELECT

        id,
        title,
        description,
        tournament_date,
        status_message

    FROM tournaments

    WHERE id = :id

    LIMIT 1

");

$stmt->execute([
    ":id" => $tournamentId
]);

$tournament = $stmt->fetch();


if (!$tournament) {

    header("Location: index.php#tournaments");
    exit;

}


/*
========================================
INITIAL VALUES
========================================
*/

$error = "";

$success = "";

$paymentMethod = "";

$paymentReference = "";

$registrationMessageValue = "";


/*
========================================
RECEIPT MODE
========================================
*/

$receiptMode =
    isset($_GET["receipt"]) &&
    $_GET["receipt"] === "1";


/*
========================================
GET CURRENT USER REGISTRATION
========================================
*/

$currentRegistrationStmt = $conn->prepare("

    SELECT

        tr.id,
        tr.tournament_id,
        tr.user_id,
        tr.team_name,
        tr.contact_number,
        tr.message,
        tr.status,
        tr.payment_method,
        tr.payment_reference,
        tr.payment_status,
        tr.created_at

    FROM tournament_registrations tr

    WHERE tr.tournament_id = :tournament_id

      AND tr.user_id = :user_id

    LIMIT 1

");

$currentRegistrationStmt->execute([

    ":tournament_id" =>
        $tournamentId,

    ":user_id" =>
        $userId

]);

$currentRegistration =
    $currentRegistrationStmt->fetch();


/*
========================================
HANDLE REGISTRATION
========================================
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    ========================================
    EXTRA ADMIN PROTECTION
    ========================================
    */

    if ($userRole === "admin") {

        $error =
            "Admin accounts are not allowed to join tournaments.";

    } else {

        /*
        ========================================
        CSRF
        ========================================
        */

        $csrfToken =
            $_POST["csrf_token"] ?? "";


        if (
            !is_string($csrfToken) ||
            !verifyCsrfToken($csrfToken)
        ) {

            $error =
                "Invalid request. Please try again.";

        } else {

            /*
            ========================================
            GET FORM VALUES
            ========================================
            */

            $teamName =
                trim(
                    (string)(
                        $_POST["team_name"] ?? ""
                    )
                );


            $teamMembers =
                trim(
                    (string)(
                        $_POST["team_members"] ?? ""
                    )
                );


            $contactNumber =
                trim(
                    (string)(
                        $_POST["contact_number"] ?? ""
                    )
                );


            $message =
                trim(
                    (string)(
                        $_POST["message"] ?? ""
                    )
                );


            $paymentMethod =
                trim(
                    (string)(
                        $_POST["payment_method"] ?? ""
                    )
                );


            $paymentReference =
                trim(
                    (string)(
                        $_POST["payment_reference"] ?? ""
                    )
                );


            /*
            ========================================
            VALIDATION
            ========================================
            */

            if ($teamName === "") {

                $error =
                    "Please enter your team name.";

            } elseif (
                mb_strlen($teamName) > 120
            ) {

                $error =
                    "Team name is too long.";

            } elseif (
                $teamMembers === ""
            ) {

                $error =
                    "Please enter your team members.";

            } elseif (
                mb_strlen($teamMembers) > 500
            ) {

                $error =
                    "Team members information is too long.";

            } elseif (
                $contactNumber === ""
            ) {

                $error =
                    "Please enter your contact number.";

            } elseif (
                !preg_match(
                    '/^[0-9+\-\s()]{7,40}$/',
                    $contactNumber
                )
            ) {

                $error =
                    "Please enter a valid contact number.";

            } elseif (
                $message !== "" &&
                mb_strlen($message) > 500
            ) {

                $error =
                    "Message is too long.";

            } elseif (
                $paymentMethod !== "GCash" &&
                $paymentMethod !== "Cash"
            ) {

                $error =
                    "Please select a valid payment method.";

            } elseif (
                $paymentMethod === "GCash" &&
                $paymentReference === ""
            ) {

                $error =
                    "Please enter your GCash reference number.";

            } elseif (
                mb_strlen($paymentReference) > 100
            ) {

                $error =
                    "Payment reference number is too long.";

            } else {

                /*
                ========================================
                CHECK DUPLICATE REGISTRATION
                ========================================
                */

                $check = $conn->prepare("

                    SELECT

                        id,
                        status

                    FROM tournament_registrations

                    WHERE tournament_id = :tournament_id

                      AND user_id = :user_id

                    LIMIT 1

                ");

                $check->execute([

                    ":tournament_id" =>
                        $tournamentId,

                    ":user_id" =>
                        $userId

                ]);


                $existingRegistration =
                    $check->fetch();


                if ($existingRegistration) {

                    $existingStatus =
                        (string)$existingRegistration["status"];


                    if (
                        $existingStatus === "pending"
                    ) {

                        $error =
                            "You have already registered for this tournament.";

                    } elseif (
                        $existingStatus === "accepted"
                    ) {

                        $error =
                            "You are already registered for this tournament.";

                    } else {

                        $error =
                            "Your registration for this tournament has already been processed.";

                    }

                } else {

                    /*
                    ========================================
                    STORE TEAM MEMBERS
                    ========================================
                    */

                    $registrationMessage =
                        "Team Members:\n"
                        . $teamMembers;


                    if ($message !== "") {

                        $registrationMessage .=
                            "\n\nAdditional Message:\n"
                            . $message;

                    }


                    $registrationMessageValue =
                        $registrationMessage;


                    /*
                    ========================================
                    PAYMENT STATUS
                    ========================================
                    */

                    $paymentStatus =
                        $paymentMethod === "GCash"
                            ? "pending"
                            : "unpaid";


                    /*
                    ========================================
                    INSERT REGISTRATION
                    ========================================
                    */

                    $insert = $conn->prepare("

                        INSERT INTO tournament_registrations

                        (

                            tournament_id,
                            user_id,
                            team_name,
                            contact_number,
                            message,
                            status,
                            payment_method,
                            payment_reference,
                            payment_status

                        )

                        VALUES

                        (

                            :tournament_id,
                            :user_id,
                            :team_name,
                            :contact_number,
                            :message,
                            'pending',
                            :payment_method,
                            :payment_reference,
                            :payment_status

                        )

                    ");


                    $insert->execute([

                        ":tournament_id" =>
                            $tournamentId,

                        ":user_id" =>
                            $userId,

                        ":team_name" =>
                            $teamName,

                        ":contact_number" =>
                            $contactNumber,

                        ":message" =>
                            $registrationMessage,

                        ":payment_method" =>
                            $paymentMethod,

                        ":payment_reference" =>
                            $paymentReference !== ""
                                ? $paymentReference
                                : null,

                        ":payment_status" =>
                            $paymentStatus

                    ]);


                    /*
                    ========================================
                    SUCCESS
                    ========================================
                    */

                    $success =
                        "Tournament registration submitted successfully.";

                    $_POST = [];


                    /*
                    ========================================
                    REFRESH CURRENT REGISTRATION
                    ========================================
                    */

                    $currentRegistrationStmt->execute([

                        ":tournament_id" =>
                            $tournamentId,

                        ":user_id" =>
                            $userId

                    ]);


                    $currentRegistration =
                        $currentRegistrationStmt->fetch();

                }

            }

        }

    }

}


/*
========================================
GET CURRENT REGISTRATION AGAIN
========================================
*/

$currentRegistrationStmt->execute([

    ":tournament_id" =>
        $tournamentId,

    ":user_id" =>
        $userId

]);

$currentRegistration =
    $currentRegistrationStmt->fetch();


/*
========================================
DIGITAL TOURNAMENT RECEIPT
========================================
*/

$selectedReceipt = null;


if (
    $receiptMode &&
    $currentRegistration
) {

    if (
        (string)$currentRegistration["status"]
        === "accepted"
    ) {

        $selectedReceipt =
            $currentRegistration;

    } else {

        $error =
            "Your tournament registration must be accepted before the digital receipt can be viewed.";

        $receiptMode =
            false;

    }

}


/*
========================================
DIGITAL RECEIPT DATA
========================================
*/

$receiptId = 0;

$receiptTeamName = "";

$receiptContactNumber = "";

$receiptTeamMembers = "";

$receiptPaymentMethod = "";

$receiptPaymentReference = "";

$receiptPaymentStatus = "";

$receiptStatus = "";

$receiptDate = "";

$receiptTournamentDate = "";

$receiptTournamentTitle = "";


if ($selectedReceipt) {

    $receiptId =
        (int)$selectedReceipt["id"];


    $receiptTeamName =
        (string)(
            $selectedReceipt["team_name"]
            ?? ""
        );


    $receiptContactNumber =
        (string)(
            $selectedReceipt["contact_number"]
            ?? ""
        );


    $receiptPaymentMethod =
        trim(
            (string)(
                $selectedReceipt[
                    "payment_method"
                ] ?? ""
            )
        );


    $receiptPaymentReference =
        trim(
            (string)(
                $selectedReceipt[
                    "payment_reference"
                ] ?? ""
            )
        );


    $receiptPaymentStatus =
        trim(
            (string)(
                $selectedReceipt[
                    "payment_status"
                ] ?? ""
            )
        );


    $receiptStatus =
        strtoupper(
            (string)(
                $selectedReceipt["status"]
            )
        );


    $receiptTournamentTitle =
        (string)$tournament["title"];


    /*
    ========================================
    TOURNAMENT DATE
    ========================================
    */

    $receiptTournamentTimestamp =
        strtotime(
            (string)
            $tournament["tournament_date"]
        );


    $receiptTournamentDate =
        $receiptTournamentTimestamp !== false
            ? date(
                "F d, Y",
                $receiptTournamentTimestamp
            )
            : (string)
            $tournament["tournament_date"];


    /*
    ========================================
    DATE REGISTERED
    ========================================
    */

    $receiptCreatedTimestamp =
        strtotime(
            (string)
            $selectedReceipt["created_at"]
        );


    $receiptDate =
        $receiptCreatedTimestamp !== false
            ? date(
                "F d, Y h:i A",
                $receiptCreatedTimestamp
            )
            : (string)
            $selectedReceipt["created_at"];


    /*
    ========================================
    GET TEAM MEMBERS
    ========================================
    */

    $savedMessage =
        (string)(
            $selectedReceipt["message"]
            ?? ""
        );


    $receiptTeamMembers =
        $savedMessage;


    if (
        preg_match(
            '/Team Members:\s*(.*?)(?:\n\nAdditional Message:|\z)/s',
            $savedMessage,
            $matches
        )
    ) {

        $receiptTeamMembers =
            trim(
                $matches[1]
            );

    }


    if (
        $receiptPaymentMethod === ""
    ) {

        $receiptPaymentMethod =
            "Cash";

    }

}


/*
========================================
CSRF TOKEN
========================================
*/

$csrfToken = csrfToken();


include "includes/header.php";

?>


<style>

/* ========================================
   TOURNAMENT REGISTRATION
======================================== */

.tournament-register-wrapper {

    width: 100%;

    max-width: 1120px;

    margin: 0 auto;

}


.tournament-register-card {

    border:
        1px solid
        var(--green);

    border-radius:
        5px;

    background:
        #050505;

    padding:
        28px 32px 32px;

    box-sizing:
        border-box;

}


.tournament-register-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap:
        20px 24px;

}


.tournament-field {

    width: 100%;

}


.tournament-field.full-width {

    grid-column:
        1 / -1;

}


.tournament-field label {

    display:
        block;

    margin-bottom:
        8px;

    color:
        #fff;

    font:
        700 11px
        "Rajdhani",
        sans-serif;

    letter-spacing:
        .3px;

}


.tournament-field input,
.tournament-field textarea,
.tournament-field select {

    width:
        100%;

    min-height:
        46px;

    padding:
        12px 14px;

    box-sizing:
        border-box;

    background:
        #080808;

    color:
        #fff;

    border:
        1px solid #333;

    border-radius:
        4px;

    outline:
        none;

    font:
        600 14px
        "Rajdhani",
        sans-serif;

}


.tournament-field input:focus,
.tournament-field textarea:focus,
.tournament-field select:focus {

    border-color:
        var(--green);

    box-shadow:
        0 0 0 1px
        rgba(57,255,20,.15),

        0 0 12px
        rgba(57,255,20,.08);

}


.tournament-field input[readonly] {

    color:
        #d8d8d8;

    background:
        #0b0b0b;

}


.tournament-field textarea {

    min-height:
        120px;

    resize:
        vertical;

}


.tournament-field textarea.team-members {

    min-height:
        135px;

}


/* ========================================
   BUTTONS
======================================== */

.tournament-buttons {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

    flex-wrap:
        wrap;

    margin-top:
        26px;

}


.tournament-buttons .green-button,
.tournament-buttons .outline-button {

    min-height:
        44px;

}


/* ========================================
   ALERTS
======================================== */

.tournament-alert {

    max-width:
        1120px;

    margin:
        18px auto 0;

    padding:
        14px 16px;

    border-radius:
        4px;

    box-sizing:
        border-box;

    font:
        600 14px
        "Rajdhani",
        sans-serif;

}


.tournament-alert.error {

    border:
        1px solid
        #ff4444;

    color:
        #ff6666;

    background:
        rgba(255,0,0,.04);

}


.tournament-alert.success {

    border:
        1px solid
        var(--green);

    color:
        var(--green);

    background:
        rgba(57,255,20,.04);

}


/* ========================================
   PAYMENT
======================================== */

.tournament-payment {

    grid-column:
        1 / -1;

    margin-top:
        4px;

    padding-top:
        24px;

    border-top:
        1px solid
        rgba(57,255,20,.25);

}


.tournament-payment h2 {

    margin:
        0 0 15px;

    color:
        var(--green);

    font:
        700 18px
        "Orbitron",
        sans-serif;

}


.tournament-payment-note {

    margin:
        0 0 20px;

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


.tournament-payment-note strong {

    color:
        var(--green);

}


.tournament-details-card {

    margin-bottom:
        24px;

}


/* ========================================
   REGISTRATION STATUS
======================================== */

.tournament-status-box {

    margin:
        0 auto 24px;

    max-width:
        1120px;

    padding:
        16px 18px;

    border:
        1px solid
        rgba(57,255,20,.35);

    background:
        rgba(57,255,20,.03);

}


.tournament-status-title {

    margin:
        0 0 6px;

    color:
        var(--green);

    font:
        800 10px
        "Orbitron",
        sans-serif;

}


.tournament-status-text {

    margin:
        0;

    color:
        #fff;

    font-size:
        14px;

    line-height:
        1.5;

}


.tournament-receipt-link {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-height:
        42px;

    padding:
        0 18px;

    margin-top:
        14px;

    background:
        var(--green);

    color:
        #000;

    border:
        1px solid
        var(--green);

    border-radius:
        4px;

    font:
        800 9px
        "Orbitron",
        sans-serif;

    text-decoration:
        none;

    transition:
        .2s ease;

}


.tournament-receipt-link:hover {

    background:
        transparent;

    color:
        var(--green);

    transform:
        translateY(-2px);

}


/* ========================================
   DIGITAL TOURNAMENT RECEIPT
======================================== */

.tournament-receipt-overlay {

    position:
        fixed;

    inset:
        0;

    z-index:
        9999;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        20px;

    background:
        rgba(0,0,0,.88);

}


.tournament-receipt-card {

    width:
        min(650px, 100%);

    max-height:
        90vh;

    overflow-y:
        auto;

    background:
        #000;

    border:
        1px solid
        #39FF14;

    border-radius:
        8px;

    box-shadow:
        0 0 35px
        rgba(57,255,20,.12);

}


.tournament-receipt-close {

    display:
        flex;

    justify-content:
        flex-end;

    padding:
        14px 14px 0;

}


.tournament-receipt-close a {

    width:
        30px;

    height:
        30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border:
        1px solid
        #555;

    border-radius:
        50%;

    background:
        transparent;

    color:
        #fff;

    text-decoration:
        none;

    font-size:
        15px;

}


.tournament-receipt-close a:hover {

    border-color:
        #39FF14;

    color:
        #39FF14;

}


.tournament-receipt-header {

    padding:
        12px 30px 30px;

    text-align:
        center;

    border-bottom:
        1px solid
        rgba(57,255,20,.22);

}


.tournament-receipt-logo {

    width:
        95px;

    display:
        block;

    margin:
        0 auto 12px;

}


.tournament-receipt-business {

    margin:
        0;

    color:
        #fff;

    font:
        700 20px
        "Orbitron",
        sans-serif;

}


.tournament-receipt-subtitle {

    margin:
        7px 0 0;

    color:
        #39FF14;

    font:
        600 9px
        "Orbitron",
        sans-serif;

}


.tournament-receipt-body {

    padding:
        24px 30px 30px;

}


.tournament-receipt-row {

    display:
        grid;

    grid-template-columns:
        1fr 1.2fr;

    gap:
        25px;

    padding:
        12px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.tournament-receipt-label {

    color:
        rgba(255,255,255,.52);

    font-size:
        10px;

    font-weight:
        700;

    text-transform:
        uppercase;

}


.tournament-receipt-value {

    color:
        #fff;

    font-size:
        12px;

    font-weight:
        600;

    text-align:
        right;

    overflow-wrap:
        anywhere;

    white-space:
        pre-line;

}


.tournament-receipt-status {

    color:
        #39FF14;

}


.tournament-receipt-actions {

    display:
        flex;

    justify-content:
        center;

    margin-top:
        20px;

}


.tournament-receipt-print {

    min-height:
        42px;

    padding:
        0 18px;

    border:
        1px solid
        #39FF14;

    background:
        #39FF14;

    color:
        #000;

    border-radius:
        4px;

    font:
        800 9px
        "Orbitron",
        sans-serif;

    cursor:
        pointer;

    transition:
        .2s ease;

}


.tournament-receipt-print:hover {

    background:
        transparent;

    color:
        #39FF14;

}


/* ========================================
   MOBILE
======================================== */

@media (max-width: 768px) {

    .tournament-register-card {

        padding:
            22px 18px 24px;

    }


    .tournament-register-grid {

        grid-template-columns:
            1fr;

    }


    .tournament-field.full-width {

        grid-column:
            auto;

    }


    .tournament-payment {

        grid-column:
            auto;

    }


    .tournament-buttons {

        align-items:
            stretch;

        flex-direction:
            column;

    }


    .tournament-buttons a,
    .tournament-buttons button {

        width:
            100%;

    }


    .tournament-receipt-card {

        max-height:
            94vh;

    }


    .tournament-receipt-header {

        padding:
            10px 18px 25px;

    }


    .tournament-receipt-body {

        padding:
            20px;

    }


    .tournament-receipt-row {

        grid-template-columns:
            1fr;

        gap:
            5px;

    }


    .tournament-receipt-value {

        text-align:
            left;

    }

}


/* ========================================
   PRINT RECEIPT
======================================== */

@media print {

    @page {

        margin:
            0;

    }


    * {

        -webkit-print-color-adjust:
            exact !important;

        print-color-adjust:
            exact !important;

    }


    html,
    body {

        margin:
            0 !important;

        padding:
            0 !important;

        background:
            #000 !important;

        color:
            #fff !important;

    }


    body > * {

        display:
            none !important;

    }


    .tournament-receipt-overlay {

        display:
            flex !important;

        position:
            static !important;

        width:
            100% !important;

        height:
            auto !important;

        padding:
            0 !important;

        background:
            #000 !important;

    }


    .tournament-receipt-card {

        display:
            block !important;

        width:
            100% !important;

        max-width:
            850px !important;

        max-height:
            none !important;

        margin:
            0 auto !important;

        border:
            1px solid
            #39FF14 !important;

        border-radius:
            0 !important;

        box-shadow:
            none !important;

        overflow:
            visible !important;

    }


    .tournament-receipt-close,
    .tournament-receipt-actions {

        display:
            none !important;

    }


    .tournament-receipt-business,
    .tournament-receipt-label,
    .tournament-receipt-value {

        color:
            #fff !important;

    }


    .tournament-receipt-status {

        color:
            #39FF14 !important;

    }

}

</style>


<?php if (!$receiptMode): ?>

<main>

    <section class="section">

        <div class="tournament-register-wrapper">


            <!-- ========================================
                 TITLE
            ========================================= -->

            <p class="section-kicker">

                TOURNAMENT REGISTRATION

            </p>


            <h1 class="page-title">

                JOIN

                <span>

                    THE BATTLE.

                </span>

            </h1>


            <!-- ========================================
                 TOURNAMENT DETAILS
            ========================================= -->

            <div
                class="
                    tournament-card
                    tournament-details-card
                "
            >

                <div class="tournament-date">

                    <?php

                    $tournamentTimestamp =
                        strtotime(
                            (string)
                            $tournament[
                                "tournament_date"
                            ]
                        );

                    ?>


                    <span>

                        <?= $tournamentTimestamp !== false
                            ? date(
                                "d",
                                $tournamentTimestamp
                            )
                            : "--"
                        ?>

                    </span>


                    <!-- DYNAMIC MONTH -->

                    <small>

                        <?= $tournamentTimestamp !== false
                            ? strtoupper(
                                date(
                                    "M",
                                    $tournamentTimestamp
                                )
                            )
                            : "---"
                        ?>

                    </small>

                </div>


                <div class="tournament-info">

                    <h3>

                        <?= htmlspecialchars(
                            (string)
                            $tournament["title"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </h3>


                    <p>

                        <?= htmlspecialchars(
                            (string)
                            $tournament["description"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>


                    <span>

                        <?= htmlspecialchars(
                            (string)
                            $tournament["status_message"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>

            </div>


            <!-- ========================================
                 REGISTRATION STATUS
            ========================================= -->

            <?php if ($currentRegistration): ?>

                <div
                    class="
                        tournament-status-box
                    "
                >

                    <p
                        class="
                            tournament-status-title
                        "
                    >

                        YOUR REGISTRATION STATUS

                    </p>


                    <p
                        class="
                            tournament-status-text
                        "
                    >

                        <?php if (
                            $currentRegistration[
                                "status"
                            ] === "pending"
                        ): ?>

                            Your tournament registration is
                            currently pending admin approval.

                        <?php elseif (
                            $currentRegistration[
                                "status"
                            ] === "accepted"
                        ): ?>

                            Your tournament registration has
                            been approved successfully.

                        <?php else: ?>

                            Your tournament registration has
                            been rejected.

                        <?php endif; ?>

                    </p>


                    <?php if (
                        $currentRegistration[
                            "status"
                        ] === "accepted"
                    ): ?>

                        <a
                            href="tournament.php?id=<?= (int)$tournamentId ?>&receipt=1"
                            class="
                                tournament-receipt-link
                            "
                        >

                            VIEW DIGITAL RECEIPT

                        </a>

                    <?php endif; ?>


                </div>

            <?php endif; ?>


            <!-- ========================================
                 MESSAGES
            ========================================= -->

            <?php if ($error !== ""): ?>

                <div class="tournament-alert error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if ($success !== ""): ?>

                <div class="tournament-alert success">

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================
                 REGISTRATION FORM
            ========================================= -->

            <?php if (
                !$currentRegistration &&
                $userRole !== "admin"
            ): ?>

                <form
                    method="POST"
                    class="tournament-register-card"
                    autocomplete="off"
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


                    <div
                        class="
                            tournament-register-grid
                        "
                    >


                        <!-- TOURNAMENT -->

                        <div
                            class="
                                tournament-field
                                full-width
                            "
                        >

                            <label
                                for="tournament"
                            >

                                TOURNAMENT

                            </label>


                            <input
                                type="text"
                                id="tournament"
                                value="<?= htmlspecialchars(
                                    (string)
                                    $tournament[
                                        "title"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                readonly
                            >

                        </div>


                        <!-- FULL NAME -->

                        <div
                            class="
                                tournament-field
                            "
                        >

                            <label
                                for="full_name"
                            >

                                FULL NAME

                            </label>


                            <input
                                type="text"
                                id="full_name"
                                value="<?= htmlspecialchars(
                                    (string)
                                    $user["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                readonly
                            >

                        </div>


                        <!-- EMAIL -->

                        <div
                            class="
                                tournament-field
                            "
                        >

                            <label
                                for="email"
                            >

                                EMAIL ADDRESS

                            </label>


                            <input
                                type="email"
                                id="email"
                                value="<?= htmlspecialchars(
                                    (string)
                                    $user["email"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                readonly
                            >

                        </div>


                        <!-- CONTACT -->

                        <div
                            class="
                                tournament-field
                            "
                        >

                            <label
                                for="contact_number"
                            >

                                CONTACT NUMBER

                            </label>


                            <input
                                type="text"
                                id="contact_number"
                                name="contact_number"
                                maxlength="40"
                                placeholder="ENTER CONTACT NUMBER"
                                value="<?= htmlspecialchars(
                                    (string)(
                                        $_POST[
                                            "contact_number"
                                        ]
                                        ??
                                        (
                                            $user["phone"]
                                            ?? ""
                                        )
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- TEAM NAME -->

                        <div
                            class="
                                tournament-field
                            "
                        >

                            <label
                                for="team_name"
                            >

                                TEAM NAME

                            </label>


                            <input
                                type="text"
                                id="team_name"
                                name="team_name"
                                maxlength="120"
                                placeholder="ENTER TEAM NAME"
                                value="<?= htmlspecialchars(
                                    (string)(
                                        $_POST[
                                            "team_name"
                                        ]
                                        ?? ""
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- TEAM MEMBERS -->

                        <div
                            class="
                                tournament-field
                                full-width
                            "
                        >

                            <label
                                for="team_members"
                            >

                                TEAM MEMBERS

                            </label>


                            <textarea
                                id="team_members"
                                name="team_members"
                                class="team-members"
                                maxlength="500"
                                placeholder="ENTER TEAM MEMBERS&#10;Example:&#10;Player 1 - Juan Dela Cruz&#10;Player 2 - Pedro Santos&#10;Player 3 - Mark Reyes"
                                required
                            ><?= htmlspecialchars(
                                (string)(
                                    $_POST[
                                        "team_members"
                                    ] ?? ""
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>


                        <!-- MESSAGE -->

                        <div
                            class="
                                tournament-field
                                full-width
                            "
                        >

                            <label
                                for="message"
                            >

                                MESSAGE

                            </label>


                            <textarea
                                id="message"
                                name="message"
                                maxlength="500"
                                placeholder="OPTIONAL MESSAGE OR SPECIAL REQUEST"
                            ><?= htmlspecialchars(
                                (string)(
                                    $_POST[
                                        "message"
                                    ] ?? ""
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>


                        <!-- PAYMENT -->

                        <div
                            class="tournament-payment"
                        >

                            <h2>

                                PAYMENT

                            </h2>


                            <p
                                class="
                                    tournament-payment-note
                                "
                            >

                                <strong>
                                    GCash:
                                </strong>

                                Send your tournament payment
                                to the gaming cafe GCash account,
                                then enter your
                                <strong>
                                    reference number
                                </strong>.

                                <br><br>

                                <strong>
                                    Cash:
                                </strong>

                                Select Cash if you will pay
                                directly at the cafe.

                            </p>


                            <div
                                class="
                                    tournament-register-grid
                                "
                            >


                                <!-- PAYMENT METHOD -->

                                <div
                                    class="
                                        tournament-field
                                    "
                                >

                                    <label
                                        for="payment_method"
                                    >

                                        PAYMENT METHOD

                                    </label>


                                    <select
                                        id="payment_method"
                                        name="payment_method"
                                        required
                                    >

                                        <option value="">

                                            SELECT PAYMENT METHOD

                                        </option>


                                        <option
                                            value="GCash"
                                            <?= $paymentMethod === "GCash"
                                                ? "selected"
                                                : "" ?>
                                        >

                                            GCash

                                        </option>


                                        <option
                                            value="Cash"
                                            <?= $paymentMethod === "Cash"
                                                ? "selected"
                                                : "" ?>
                                        >

                                            Cash

                                        </option>

                                    </select>

                                </div>


                                <!-- GCASH REFERENCE -->

                                <div
                                    class="
                                        tournament-field
                                    "
                                    id="referenceField"
                                >

                                    <label
                                        for="payment_reference"
                                    >

                                        GCASH REFERENCE NUMBER

                                    </label>


                                    <input
                                        type="text"
                                        id="payment_reference"
                                        name="payment_reference"
                                        maxlength="100"
                                        placeholder="ENTER REFERENCE NUMBER"
                                        value="<?= htmlspecialchars(
                                            $paymentReference,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                </div>


                            </div>

                        </div>


                    </div>


                    <!-- BUTTONS -->

                    <div
                        class="
                            tournament-buttons
                        "
                    >

                        <button
                            type="submit"
                            class="green-button"
                        >

                            REGISTER NOW

                        </button>


                        <a
                            href="index.php#tournaments"
                            class="outline-button"
                        >

                            BACK TO TOURNAMENTS

                        </a>

                    </div>


                </form>

            <?php endif; ?>


        </div>

    </section>

</main>

<?php endif; ?>


<!-- ========================================
     DIGITAL TOURNAMENT RECEIPT
======================================== -->

<?php if ($selectedReceipt): ?>

    <div
        class="
            tournament-receipt-overlay
        "
        id="tournamentReceiptOverlay"
    >

        <div
            class="
                tournament-receipt-card
            "
        >

            <div
                class="
                    tournament-receipt-close
                "
            >

                <a
                    href="profile.php#history"
                    aria-label="Close tournament receipt"
                >

                    ×

                </a>

            </div>


            <div
                class="
                    tournament-receipt-header
                "
            >

                <img
                    src="assets/images/logo.png"
                    alt="Bais Rouilo Gaming Cafe"
                    class="
                        tournament-receipt-logo
                    "
                >


                <h2
                    class="
                        tournament-receipt-business
                    "
                >

                    BAIS ROUILO
                    GAMING CAFE

                </h2>


                <p
                    class="
                        tournament-receipt-subtitle
                    "
                >

                    TOURNAMENT REGISTRATION RECEIPT

                </p>

            </div>


            <div
                class="
                    tournament-receipt-body
                "
            >

                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Registration ID

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        #<?= $receiptId ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Tournament

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptTournamentTitle,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Tournament Date

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptTournamentDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Customer

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            (string)$user["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Email

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            (string)$user["email"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Team Name

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptTeamName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Team Members

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptTeamMembers,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Contact Number

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptContactNumber,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Mode of Payment

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            strtoupper(
                                $receiptPaymentMethod
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <?php if (
                    strcasecmp(
                        $receiptPaymentMethod,
                        "GCash"
                    ) === 0 &&
                    $receiptPaymentReference !== ""
                ): ?>

                    <div
                        class="
                            tournament-receipt-row
                        "
                    >

                        <span
                            class="
                                tournament-receipt-label
                            "
                        >

                            GCash Reference Number

                        </span>


                        <span
                            class="
                                tournament-receipt-value
                            "
                        >

                            <?= htmlspecialchars(
                                $receiptPaymentReference,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php if (
                    $receiptPaymentStatus !== ""
                ): ?>

                    <div
                        class="
                            tournament-receipt-row
                        "
                    >

                        <span
                            class="
                                tournament-receipt-label
                            "
                        >

                            Payment Status

                        </span>


                        <span
                            class="
                                tournament-receipt-value
                            "
                        >

                            <?= htmlspecialchars(
                                strtoupper(
                                    $receiptPaymentStatus
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Registration Status

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                            tournament-receipt-status
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptStatus,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-row
                    "
                >

                    <span
                        class="
                            tournament-receipt-label
                        "
                    >

                        Date Registered

                    </span>


                    <span
                        class="
                            tournament-receipt-value
                        "
                    >

                        <?= htmlspecialchars(
                            $receiptDate,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>


                <div
                    class="
                        tournament-receipt-actions
                    "
                >

                    <button
                        type="button"
                        class="
                            tournament-receipt-print
                        "
                        onclick="window.print()"
                    >

                        PRINT RECEIPT

                    </button>

                </div>


            </div>

        </div>

    </div>

<?php endif; ?>


<script>

/* ========================================
   PAYMENT FIELDS
======================================== */

const paymentMethod =
    document.getElementById(
        "payment_method"
    );


const referenceField =
    document.getElementById(
        "referenceField"
    );


const paymentReference =
    document.getElementById(
        "payment_reference"
    );


function updatePaymentFields() {

    if (!paymentMethod) {

        return;

    }


    const isGCash =
        paymentMethod.value === "GCash";


    if (referenceField) {

        referenceField.style.display =
            isGCash
                ? ""
                : "none";

    }


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


/* ========================================
   ESCAPE KEY
======================================== */

const tournamentReceiptOverlay =
    document.getElementById(
        "tournamentReceiptOverlay"
    );


document.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key === "Escape" &&
            tournamentReceiptOverlay
        ) {

            window.location.href =
                "profile.php#history";

        }

    }
);

</script>