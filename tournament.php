<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

requireLogin();

$userId = currentUserId();

$tournamentId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$tournamentId) {
    header("Location: index.php#tournaments");
    exit;
}


/* ========================================
   GET TOURNAMENT
======================================== */

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


/* ========================================
   GET CURRENT CUSTOMER
======================================== */

$userStmt = $conn->prepare("
    SELECT
        name,
        email,
        phone
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


/* ========================================
   INITIAL VALUES
======================================== */

$error = "";
$success = "";

$paymentMethod = "";
$paymentReference = "";


/* ========================================
   HANDLE REGISTRATION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF
    ======================================== */

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $error =
            "Invalid request. Please try again.";

    } else {

        /* ========================================
           GET FORM VALUES
        ======================================== */

        $teamName = trim(
            (string)(
                $_POST["team_name"] ?? ""
            )
        );

        $teamMembers = trim(
            (string)(
                $_POST["team_members"] ?? ""
            )
        );

        $contactNumber = trim(
            (string)(
                $_POST["contact_number"] ?? ""
            )
        );

        $message = trim(
            (string)(
                $_POST["message"] ?? ""
            )
        );

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

        if ($teamName === "") {

            $error =
                "Please enter your team name.";

        } elseif (
            mb_strlen($teamName) > 120
        ) {

            $error =
                "Team name is too long.";

        } elseif ($teamMembers === "") {

            $error =
                "Please enter your team members.";

        } elseif (
            mb_strlen($teamMembers) > 500
        ) {

            $error =
                "Team members information is too long.";

        } elseif ($contactNumber === "") {

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

            /* ========================================
               CHECK DUPLICATE REGISTRATION
            ======================================== */

            $check = $conn->prepare("
                SELECT
                    id
                FROM tournament_registrations
                WHERE tournament_id = :tournament_id
                  AND user_id = :user_id
                LIMIT 1
            ");

            $check->execute([
                ":tournament_id" => $tournamentId,
                ":user_id" => $userId
            ]);

            if ($check->fetch()) {

                $error =
                    "You have already registered for this tournament.";

            } else {

                /* ========================================
                   STORE TEAM MEMBERS
                ======================================== */

                $registrationMessage =
                    "Team Members:\n"
                    . $teamMembers;

                if ($message !== "") {

                    $registrationMessage .=
                        "\n\nAdditional Message:\n"
                        . $message;
                }


                /* ========================================
                   PAYMENT STATUS
                ======================================== */

                $paymentStatus =
                    $paymentMethod === "GCash"
                        ? "pending"
                        : "unpaid";


                /* ========================================
                   INSERT REGISTRATION
                ======================================== */

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


                /* ========================================
                   SUCCESS
                ======================================== */

                $success =
                    "Tournament registration submitted successfully.";

                $_POST = [];
            }
        }
    }
}

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
        repeat(2, minmax(0, 1fr));

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

    width: 100%;

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

    box-sizing:
        border-box;
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
}

</style>


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
                class="tournament-card
                       tournament-details-card"
            >

                <div class="tournament-date">

                    <span>

                        <?= date(
                            "d",
                            strtotime(
                                $tournament["tournament_date"]
                            )
                        ) ?>

                    </span>

                    <small>

                        <?= strtoupper(
                            date(
                                "M",
                                strtotime(
                                    $tournament["tournament_date"]
                                )
                            )
                        ) ?>

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

            <form
                method="POST"
                class="tournament-register-card"
                autocomplete="off"
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


                <div
                    class="tournament-register-grid"
                >


                    <!-- TOURNAMENT -->

                    <div
                        class="tournament-field full-width"
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
                                $tournament["title"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            readonly
                        >

                    </div>


                    <!-- FULL NAME -->

                    <div
                        class="tournament-field"
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
                        class="tournament-field"
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
                        class="tournament-field"
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
                                    ($user["phone"] ?? "")
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- TEAM NAME -->

                    <div
                        class="tournament-field"
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
                                    ] ?? ""
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- TEAM MEMBERS -->

                    <div
                        class="tournament-field full-width"
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
                        class="tournament-field full-width"
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


                    <!-- ========================================
                         PAYMENT
                    ========================================= -->

                    <div class="tournament-payment">

                        <h2>
                            PAYMENT
                        </h2>


                        <p
                            class="tournament-payment-note"
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
                            class="tournament-register-grid"
                        >


                            <!-- PAYMENT METHOD -->

                            <div
                                class="tournament-field"
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
                                class="tournament-field"
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


                <!-- ========================================
                     BUTTONS
                ========================================= -->

                <div class="tournament-buttons">

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

        </div>

    </section>

</main>


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

            paymentReference.value = "";
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


<?php include "includes/footer.php"; ?>