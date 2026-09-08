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


$error = "";
$success = "";


/* ========================================
   HANDLE REGISTRATION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {

        $error = "Invalid request. Please try again.";

    } else {

        $teamName = trim(
            $_POST["team_name"] ?? ""
        );

        $contactNumber = trim(
            $_POST["contact_number"] ?? ""
        );

        $message = trim(
            $_POST["message"] ?? ""
        );


        /* ========================================
           VALIDATION
        ======================================== */

        if (mb_strlen($teamName) > 120) {

            $error = "Team name is too long.";

        } elseif (mb_strlen($contactNumber) > 40) {

            $error = "Contact number is too long.";

        } elseif (mb_strlen($message) > 500) {

            $error = "Message is too long.";

        } else {


            /* ========================================
               CHECK DUPLICATE REGISTRATION
            ======================================== */

            $check = $conn->prepare("
                SELECT id
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
                   INSERT REGISTRATION
                ======================================== */

                $insert = $conn->prepare("
                    INSERT INTO tournament_registrations
                    (
                        tournament_id,
                        user_id,
                        team_name,
                        contact_number,
                        message
                    )
                    VALUES
                    (
                        :tournament_id,
                        :user_id,
                        :team_name,
                        :contact_number,
                        :message
                    )
                ");

                $insert->execute([
                    ":tournament_id" => $tournamentId,
                    ":user_id" => $userId,
                    ":team_name" =>
                        $teamName !== ""
                            ? $teamName
                            : null,
                    ":contact_number" =>
                        $contactNumber !== ""
                            ? $contactNumber
                            : null,
                    ":message" =>
                        $message !== ""
                            ? $message
                            : null
                ]);

                $success =
                    "Tournament registration submitted successfully.";
            }
        }
    }
}


include "includes/header.php";

?>

<main>

    <section class="section">

        <div class="container">


            <!-- ========================================
                 TITLE
            ======================================== -->

            <p class="section-kicker">
                TOURNAMENT REGISTRATION
            </p>


            <h1 class="page-title">

                <?= htmlspecialchars(
                    $tournament["title"]
                ) ?>

            </h1>


            <!-- ========================================
                 TOURNAMENT INFORMATION
            ======================================== -->

            <div class="tournament-card">

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
                            $tournament["title"]
                        ) ?>

                    </h3>

                    <p>

                        <?= htmlspecialchars(
                            $tournament["description"]
                        ) ?>

                    </p>

                    <span>

                        <?= htmlspecialchars(
                            $tournament["status_message"]
                        ) ?>

                    </span>

                </div>

            </div>


            <!-- ========================================
                 ERROR MESSAGE
            ======================================== -->

            <?php if ($error !== ""): ?>

                <div class="form-message error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================
                 SUCCESS MESSAGE
            ======================================== -->

            <?php if ($success !== ""): ?>

                <div class="form-message success">

                    <?= htmlspecialchars($success) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================
                 REGISTRATION FORM
            ======================================== -->

            <form
                method="POST"
                class="contact-form"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrfToken()
                    ) ?>"
                >


                <!-- TEAM NAME -->

                <div class="form-group">

                    <label for="team_name">
                        TEAM NAME
                    </label>

                    <input
                        type="text"
                        id="team_name"
                        name="team_name"
                        maxlength="120"
                        placeholder="ENTER TEAM NAME"
                        value="<?= htmlspecialchars(
                            $_POST["team_name"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- CONTACT NUMBER -->

                <div class="form-group">

                    <label for="contact_number">
                        CONTACT NUMBER
                    </label>

                    <input
                        type="text"
                        id="contact_number"
                        name="contact_number"
                        maxlength="40"
                        placeholder="ENTER CONTACT NUMBER"
                        value="<?= htmlspecialchars(
                            $_POST["contact_number"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- MESSAGE -->

                <div class="form-group">

                    <label for="message">
                        MESSAGE
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        maxlength="500"
                        rows="5"
                        placeholder="ADDITIONAL INFORMATION"
                    ><?= htmlspecialchars(
                        $_POST["message"] ?? ""
                    ) ?></textarea>

                </div>


                <!-- BUTTONS -->

                <div
                    style="
                        display:flex;
                        gap:12px;
                        flex-wrap:wrap;
                        margin-top:20px;
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

        </div>

    </section>

</main>


<?php include "includes/footer.php"; ?>