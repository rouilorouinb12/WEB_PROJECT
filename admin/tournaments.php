<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireLogin();

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../index.php");
    exit;
}

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| HANDLE ADD / EDIT / DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $action = $_POST["action"] ?? "";

        /*
        |--------------------------------------------------------------------------
        | ADD TOURNAMENT
        |--------------------------------------------------------------------------
        */

        if ($action === "add") {

            $title = trim((string)($_POST["title"] ?? ""));
            $description = trim((string)($_POST["description"] ?? ""));
            $tournamentDate = trim((string)($_POST["tournament_date"] ?? ""));
            $status = trim((string)($_POST["status"] ?? ""));

            if ($title === "") {

                $error = "Tournament title is required.";

            } elseif (mb_strlen($title) > 150) {

                $error = "Tournament title must not exceed 150 characters.";

            } elseif (mb_strlen($description) > 500) {

                $error = "Description must not exceed 500 characters.";

            } elseif ($tournamentDate === "") {

                $error = "Tournament date is required.";

            } else {

                $dateObject = DateTime::createFromFormat(
                    "Y-m-d",
                    $tournamentDate
                );

                $dateErrors = DateTime::getLastErrors();

                $dateIsValid =
                    $dateObject !== false &&
                    (
                        $dateErrors === false ||
                        (
                            $dateErrors["warning_count"] === 0 &&
                            $dateErrors["error_count"] === 0
                        )
                    );

                if (!$dateIsValid) {

                    $error = "Please enter a valid tournament date.";

                } else {

                    if ($status === "") {
                        $status = "Registration Open";
                    }

                    if (mb_strlen($status) > 50) {

                        $error = "Status must not exceed 50 characters.";

                    } else {

                        try {

                            $stmt = $conn->prepare("
                                INSERT INTO tournaments
                                (
                                    title,
                                    description,
                                    tournament_date,
                                    status
                                )
                                VALUES
                                (
                                    :title,
                                    :description,
                                    :tournament_date,
                                    :status
                                )
                            ");

                            $stmt->execute([
                                ":title" => $title,
                                ":description" =>
                                    $description !== ""
                                        ? $description
                                        : null,
                                ":tournament_date" => $tournamentDate,
                                ":status" => $status
                            ]);

                            $success =
                                "Tournament added successfully.";

                        } catch (Throwable $e) {

                            $error =
                                "Unable to add tournament.";
                        }
                    }
                }
            }


        /*
        |--------------------------------------------------------------------------
        | EDIT TOURNAMENT
        |--------------------------------------------------------------------------
        */

        } elseif ($action === "edit") {

            $tournamentId = filter_input(
                INPUT_POST,
                "tournament_id",
                FILTER_VALIDATE_INT
            );

            $title = trim((string)($_POST["title"] ?? ""));
            $description = trim((string)($_POST["description"] ?? ""));
            $tournamentDate = trim((string)($_POST["tournament_date"] ?? ""));
            $status = trim((string)($_POST["status"] ?? ""));

            if (!$tournamentId) {

                $error = "Invalid tournament.";

            } elseif ($title === "") {

                $error = "Tournament title is required.";

            } elseif (mb_strlen($title) > 150) {

                $error =
                    "Tournament title must not exceed 150 characters.";

            } elseif (mb_strlen($description) > 500) {

                $error =
                    "Description must not exceed 500 characters.";

            } elseif ($tournamentDate === "") {

                $error = "Tournament date is required.";

            } else {

                $dateObject = DateTime::createFromFormat(
                    "Y-m-d",
                    $tournamentDate
                );

                $dateErrors = DateTime::getLastErrors();

                $dateIsValid =
                    $dateObject !== false &&
                    (
                        $dateErrors === false ||
                        (
                            $dateErrors["warning_count"] === 0 &&
                            $dateErrors["error_count"] === 0
                        )
                    );

                if (!$dateIsValid) {

                    $error =
                        "Please enter a valid tournament date.";

                } else {

                    if ($status === "") {
                        $status = "Registration Open";
                    }

                    if (mb_strlen($status) > 50) {

                        $error =
                            "Status must not exceed 50 characters.";

                    } else {

                        try {

                            /*
                            |--------------------------------------------------------------------------
                            | CHECK IF TOURNAMENT EXISTS
                            |--------------------------------------------------------------------------
                            */

                            $checkTournament = $conn->prepare("
                                SELECT
                                    id
                                FROM tournaments
                                WHERE id = :id
                                LIMIT 1
                            ");

                            $checkTournament->execute([
                                ":id" => $tournamentId
                            ]);

                            $existingTournament =
                                $checkTournament->fetch();

                            if (!$existingTournament) {

                                $error =
                                    "Tournament not found.";

                            } else {

                                /*
                                |--------------------------------------------------------------------------
                                | UPDATE TOURNAMENT
                                |--------------------------------------------------------------------------
                                */

                                $update = $conn->prepare("
                                    UPDATE tournaments
                                    SET
                                        title = :title,
                                        description = :description,
                                        tournament_date = :tournament_date,
                                        status = :status
                                    WHERE id = :id
                                    LIMIT 1
                                ");

                                $update->execute([
                                    ":title" => $title,
                                    ":description" =>
                                        $description !== ""
                                            ? $description
                                            : null,
                                    ":tournament_date" =>
                                        $tournamentDate,
                                    ":status" => $status,
                                    ":id" => $tournamentId
                                ]);

                                if ($update->rowCount() >= 0) {

                                    $success =
                                        "Tournament updated successfully.";

                                } else {

                                    $error =
                                        "Unable to update tournament.";
                                }
                            }

                        } catch (Throwable $e) {

                            $error =
                                "Unable to update tournament.";
                        }
                    }
                }
            }


        /*
        |--------------------------------------------------------------------------
        | DELETE TOURNAMENT
        |--------------------------------------------------------------------------
        */

        } elseif ($action === "delete") {

            $tournamentId = filter_input(
                INPUT_POST,
                "tournament_id",
                FILTER_VALIDATE_INT
            );

            if (!$tournamentId) {

                $error = "Invalid tournament.";

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK IF TOURNAMENT EXISTS
                    |--------------------------------------------------------------------------
                    */

                    $checkTournament = $conn->prepare("
                        SELECT
                            id,
                            title
                        FROM tournaments
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $checkTournament->execute([
                        ":id" => $tournamentId
                    ]);

                    $tournament = $checkTournament->fetch();

                    if (!$tournament) {

                        $error = "Tournament not found.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | CHECK EXISTING REGISTRATIONS
                        |--------------------------------------------------------------------------
                        */

                        $checkRegistrations = $conn->prepare("
                            SELECT COUNT(*)
                            FROM tournament_registrations
                            WHERE tournament_id = :tournament_id
                        ");

                        $checkRegistrations->execute([
                            ":tournament_id" => $tournamentId
                        ]);

                        $registrationCount =
                            (int)$checkRegistrations->fetchColumn();

                        if ($registrationCount > 0) {

                            $error =
                                "Cannot delete this tournament because it has existing registrations.";

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | DELETE
                            |--------------------------------------------------------------------------
                            */

                            $delete = $conn->prepare("
                                DELETE FROM tournaments
                                WHERE id = :id
                                LIMIT 1
                            ");

                            $delete->execute([
                                ":id" => $tournamentId
                            ]);

                            if ($delete->rowCount() === 1) {

                                $success =
                                    "Tournament deleted successfully.";

                            } else {

                                $error =
                                    "Unable to delete tournament.";
                            }
                        }
                    }

                } catch (Throwable $e) {

                    $error =
                        "Unable to delete tournament.";
                }
            }

        } else {

            $error = "Invalid action.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET TOURNAMENTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        id,
        title,
        description,
        tournament_date,
        status,
        created_at
    FROM tournaments
    ORDER BY tournament_date ASC, id DESC
");

$tournaments = $stmt->fetchAll();

$csrfToken = csrfToken();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Tournaments | Admin</title>

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
    href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Rajdhani:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<style>

:root {
    --green: #39ff14;
    --black: #000;
    --white: #fff;
    --dark: #080808;
    --border: rgba(57,255,20,.35);
    --muted: #999;
    --red: #ff5757;
}

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {
    background: #000;
    color: #fff;
    font-family: "Rajdhani", sans-serif;
}

body.modal-open {
    overflow: hidden;
}

button,
input,
textarea,
select {
    font-family: inherit;
}

button {
    cursor: pointer;
}

a {
    color: inherit;
    text-decoration: none;
}

/* ========================================
   PAGE
======================================== */

.page {
    width: min(1250px, 94%);
    margin: 0 auto;
    padding: 45px 0 70px;
}

/* ========================================
   TOPBAR
======================================== */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 38px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(57,255,20,.25);
}

.brand {
    color: var(--green);
    font: 800 15px "Orbitron", sans-serif;
    letter-spacing: .5px;
}

.back-link {
    border: 1px solid var(--green);
    padding: 11px 17px;
    border-radius: 4px;
    color: var(--green);
    font: 700 10px "Orbitron", sans-serif;
    transition: .2s ease;
}

.back-link:hover {
    background: var(--green);
    color: #000;
}

/* ========================================
   TITLE
======================================== */

.page-kicker {
    margin: 0 0 10px;
    color: var(--green);
    font: 700 11px "Orbitron", sans-serif;
}

.page-title {
    margin: 0 0 30px;
    font: 800 clamp(30px, 5vw, 52px) "Orbitron", sans-serif;
    letter-spacing: -1px;
}

.page-title span {
    color: var(--green);
}

/* ========================================
   ALERT
======================================== */

.alert {
    padding: 14px 17px;
    margin-bottom: 25px;
    border-radius: 4px;
    font-weight: 600;
}

.alert.success {
    border: 1px solid var(--green);
    color: var(--green);
    background: rgba(57,255,20,.05);
}

.alert.error {
    border: 1px solid var(--red);
    color: #ff7777;
    background: rgba(255,0,0,.05);
}

/* ========================================
   ADD SECTION
======================================== */

.add-section {
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #050505;
    padding: 25px;
    margin-bottom: 35px;
}

.section-title {
    margin: 0 0 20px;
    color: var(--green);
    font: 800 16px "Orbitron", sans-serif;
}

.add-form {
    display: grid;
    grid-template-columns: 1.4fr 2fr 180px 220px;
    gap: 15px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 7px;
    color: #aaa;
    font: 700 10px "Orbitron", sans-serif;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    border: 1px solid #292929;
    border-radius: 4px;
    outline: none;
    background: #0b0b0b;
    color: #fff;
    padding: 12px 13px;
    font-size: 14px;
    transition: .2s ease;
}

.form-group textarea {
    min-height: 44px;
    max-height: 100px;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: var(--green);
    box-shadow: 0 0 12px rgba(57,255,20,.08);
}

.add-button {
    width: 100%;
    min-height: 44px;
    border: 1px solid var(--green);
    border-radius: 4px;
    background: var(--green);
    color: #000;
    font: 800 10px "Orbitron", sans-serif;
    transition: .2s ease;
}

.add-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 18px rgba(57,255,20,.3);
}

/* ========================================
   TOURNAMENT LIST
======================================== */

.list-title {
    margin: 0 0 18px;
    color: #fff;
    font: 800 18px "Orbitron", sans-serif;
}

.tournament-list {
    display: grid;
    gap: 18px;
}

.tournament-card {
    display: grid;
    grid-template-columns: 115px 1fr auto;
    gap: 25px;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #050505;
    padding: 22px;
    transition: .2s ease;
}

.tournament-card:hover {
    box-shadow: 0 0 22px rgba(57,255,20,.08);
}

.date-box {
    text-align: center;
    border-right: 1px solid rgba(57,255,20,.25);
    padding-right: 22px;
}

.date-day {
    display: block;
    color: var(--green);
    font: 800 36px "Orbitron", sans-serif;
    line-height: 1;
}

.date-month {
    display: block;
    margin-top: 7px;
    color: #aaa;
    font: 700 10px "Orbitron", sans-serif;
}

.tournament-info h2 {
    margin: 0 0 8px;
    color: #fff;
    font: 800 17px "Orbitron", sans-serif;
}

.tournament-description {
    margin: 0 0 10px;
    color: #bbb;
    font-size: 15px;
    line-height: 1.4;
}

.tournament-status {
    display: inline-block;
    color: var(--green);
    font: 800 9px "Orbitron", sans-serif;
    text-transform: uppercase;
}

/* ========================================
   ACTION BUTTONS
======================================== */

.tournament-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}

.edit-button {
    min-width: 105px;
    min-height: 40px;
    padding: 0 15px;
    border: 1px solid var(--green);
    border-radius: 4px;
    background: transparent;
    color: var(--green);
    font: 800 9px "Orbitron", sans-serif;
    transition: .2s ease;
}

.edit-button:hover {
    background: var(--green);
    color: #000;
    transform: translateY(-2px);
}

.delete-button {
    min-width: 105px;
    min-height: 40px;
    padding: 0 15px;
    border: 1px solid var(--red);
    border-radius: 4px;
    background: transparent;
    color: var(--red);
    font: 800 9px "Orbitron", sans-serif;
    transition: .2s ease;
}

.delete-button:hover {
    background: var(--red);
    color: #000;
    transform: translateY(-2px);
}

/* ========================================
   EMPTY
======================================== */

.empty {
    border: 1px dashed #333;
    border-radius: 6px;
    padding: 50px 20px;
    text-align: center;
    color: var(--muted);
}

.empty strong {
    display: block;
    margin-bottom: 8px;
    color: var(--green);
    font: 700 16px "Orbitron", sans-serif;
}

/* ========================================
   CONFIRM MODAL
======================================== */

.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,.86);
    backdrop-filter: blur(4px);
}

.confirm-overlay[hidden] {
    display: none;
}

.confirm-modal {
    width: min(430px, 100%);
    border: 1px solid var(--green);
    border-radius: 6px;
    background: #050505;
    padding: 28px;
    text-align: center;
    box-shadow: 0 0 35px rgba(57,255,20,.18);
    animation: confirmModalIn .18s ease;
}

.confirm-modal h3 {
    margin: 0 0 12px;
    color: var(--green);
    font: 800 16px "Orbitron", sans-serif;
}

.confirm-modal p {
    margin: 0 0 24px;
    color: #fff;
    font-size: 14px;
    line-height: 1.5;
}

.confirm-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.confirm-no,
.confirm-yes {
    min-width: 110px;
    height: 42px;
    padding: 0 18px;
    border-radius: 4px;
    font: 800 10px "Orbitron", sans-serif;
    transition: .2s ease;
}

.confirm-no {
    border: 1px solid var(--red);
    background: transparent;
    color: var(--red);
}

.confirm-no:hover {
    background: var(--red);
    color: #000;
}

.confirm-yes {
    border: 1px solid var(--green);
    background: var(--green);
    color: #000;
}

.confirm-yes:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 15px rgba(57,255,20,.35);
}

/* ========================================
   EDIT MODAL
======================================== */

.edit-modal {
    width: min(520px, 100%);
    text-align: left;
}

.edit-modal h3 {
    text-align: center;
}

.edit-form {
    display: grid;
    gap: 14px;
}

.edit-field {
    display: flex;
    flex-direction: column;
}

.edit-field label {
    margin-bottom: 7px;
    color: #aaa;
    font: 700 10px "Orbitron", sans-serif;
}

.edit-field input,
.edit-field textarea,
.edit-field select {
    width: 100%;
    border: 1px solid #292929;
    border-radius: 4px;
    outline: none;
    background: #0b0b0b;
    color: #fff;
    padding: 12px 13px;
    font-size: 14px;
    transition: .2s ease;
}

.edit-field textarea {
    min-height: 90px;
    resize: vertical;
}

.edit-field input:focus,
.edit-field textarea:focus,
.edit-field select:focus {
    border-color: var(--green);
    box-shadow: 0 0 12px rgba(57,255,20,.08);
}

@keyframes confirmModalIn {

    from {
        opacity: 0;
        transform: scale(.96) translateY(8px);
    }

    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }

}

/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 1000px) {

    .add-form {
        grid-template-columns: 1fr 1fr;
    }

    .tournament-card {
        grid-template-columns: 100px 1fr auto;
    }

}

@media (max-width: 700px) {

    .page {
        width: 92%;
        padding: 30px 0 50px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .add-form {
        grid-template-columns: 1fr;
    }

    .tournament-card {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .date-box {
        border-right: none;
        border-bottom: 1px solid rgba(57,255,20,.25);
        padding-right: 0;
        padding-bottom: 15px;
        text-align: left;
    }

    .date-day,
    .date-month {
        display: inline-block;
    }

    .date-day {
        margin-right: 8px;
    }

    .tournament-actions {
        justify-content: flex-start;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
    }

    .edit-button,
    .delete-button {
        width: 100%;
    }

    .tournament-actions form {
        width: 100%;
    }

}

@media (max-width: 500px) {

    .add-section {
        padding: 20px;
    }

    .confirm-actions {
        flex-direction: column;
    }

    .confirm-no,
    .confirm-yes {
        width: 100%;
    }

}

</style>

</head>

<body>

<div class="page">

    <!-- ========================================
         TOP BAR
    ======================================== -->

    <div class="topbar">

        <div class="brand">
            BR GAMING CAFE ADMIN
        </div>

        <a
            href="dashboard.php"
            class="back-link"
        >
            BACK TO DASHBOARD
        </a>

    </div>


    <!-- ========================================
         TITLE
    ======================================== -->

    <p class="page-kicker">
        ADMIN MANAGEMENT
    </p>

    <h1 class="page-title">
        TOURNAMENT
        <span>MANAGEMENT</span>
    </h1>


    <!-- ========================================
         ALERTS
    ======================================== -->

    <?php if ($success !== ""): ?>

        <div class="alert success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         ADD TOURNAMENT
    ======================================== -->

    <section class="add-section">

        <h2 class="section-title">
            ADD TOURNAMENT
        </h2>

        <form
            method="POST"
            class="add-form"
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

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <!-- TITLE -->

            <div class="form-group">

                <label for="title">
                    TOURNAMENT TITLE
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    placeholder="e.g. VALORANT TOURNAMENT"
                    required
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label for="description">
                    DESCRIPTION
                </label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="500"
                    placeholder="Tournament description..."
                ></textarea>

            </div>


            <!-- DATE -->

            <div class="form-group">

                <label for="tournament_date">
                    TOURNAMENT DATE
                </label>

                <input
                    type="date"
                    id="tournament_date"
                    name="tournament_date"
                    required
                >

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label for="status">
                    STATUS
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="Registration Open">
                        Registration Open
                    </option>

                    <option value="Team Registration Open">
                        Team Registration Open
                    </option>

                    <option value="Cash Prizes Available">
                        Cash Prizes Available
                    </option>

                    <option value="Coming Soon">
                        Coming Soon
                    </option>

                    <option value="Registration Closed">
                        Registration Closed
                    </option>

                </select>

            </div>


            <!-- ADD -->

            <button
                type="submit"
                class="add-button"
            >
                + ADD TOURNAMENT
            </button>

        </form>

    </section>


    <!-- ========================================
         TOURNAMENT LIST
    ======================================== -->

    <h2 class="list-title">
        EXISTING TOURNAMENTS
    </h2>


    <?php if (!$tournaments): ?>

        <div class="empty">

            <strong>
                NO TOURNAMENTS YET
            </strong>

            Add your first tournament using
            the form above.

        </div>

    <?php else: ?>

        <div class="tournament-list">

            <?php foreach ($tournaments as $tournament): ?>

                <?php

                $timestamp = strtotime(
                    (string)$tournament["tournament_date"]
                );

                $day = date(
                    "d",
                    $timestamp
                );

                $month = strtoupper(
                    date(
                        "M",
                        $timestamp
                    )
                );

                ?>

                <article class="tournament-card">


                    <!-- DATE -->

                    <div class="date-box">

                        <span class="date-day">

                            <?= htmlspecialchars(
                                $day,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                        <span class="date-month">

                            <?= htmlspecialchars(
                                $month,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>


                    <!-- INFO -->

                    <div class="tournament-info">

                        <h2>

                            <?= htmlspecialchars(
                                (string)$tournament["title"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </h2>


                        <p class="tournament-description">

                            <?php

                            $description =
                                trim(
                                    (string)(
                                        $tournament["description"]
                                        ?? ""
                                    )
                                );

                            if ($description !== ""):

                            ?>

                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            <?php else: ?>

                                No description provided.

                            <?php endif; ?>

                        </p>


                        <span class="tournament-status">

                            <?= htmlspecialchars(
                                (string)$tournament["status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>


                    <!-- EDIT / DELETE -->

                    <div class="tournament-actions">


                        <!-- EDIT BUTTON -->

                        <button
                            type="button"
                            class="edit-button"
                            onclick="openEditModal(
                                <?= (int)$tournament["id"] ?>,
                                <?= htmlspecialchars(
                                    json_encode(
                                        (string)$tournament["title"],
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>,
                                <?= htmlspecialchars(
                                    json_encode(
                                        (string)(
                                            $tournament["description"]
                                            ?? ""
                                        ),
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>,
                                <?= htmlspecialchars(
                                    json_encode(
                                        (string)$tournament["tournament_date"],
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>,
                                <?= htmlspecialchars(
                                    json_encode(
                                        (string)$tournament["status"],
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            )"
                        >
                            EDIT
                        </button>


                        <!-- DELETE -->

                        <form
                            method="POST"
                            id="deleteForm<?= (int)$tournament["id"] ?>"
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

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="tournament_id"
                                value="<?= (int)$tournament["id"] ?>"
                            >

                            <button
                                type="button"
                                class="delete-button"
                                onclick="openDeleteConfirm(
                                    'deleteForm<?= (int)$tournament["id"] ?>',
                                    <?= htmlspecialchars(
                                        json_encode(
                                            (string)$tournament["title"],
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                )"
                            >
                                DELETE
                            </button>

                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<!-- ========================================
     EDIT TOURNAMENT MODAL
======================================== -->

<div
    class="confirm-overlay"
    id="editOverlay"
    hidden
>

    <div
        class="confirm-modal edit-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="editTitle"
    >

        <h3 id="editTitle">
            EDIT TOURNAMENT
        </h3>


        <form
            method="POST"
            class="edit-form"
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

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="tournament_id"
                id="editTournamentId"
            >


            <!-- TITLE -->

            <div class="edit-field">

                <label for="editTournamentTitle">
                    TOURNAMENT TITLE
                </label>

                <input
                    type="text"
                    id="editTournamentTitle"
                    name="title"
                    maxlength="150"
                    required
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="edit-field">

                <label for="editTournamentDescription">
                    DESCRIPTION
                </label>

                <textarea
                    id="editTournamentDescription"
                    name="description"
                    maxlength="500"
                ></textarea>

            </div>


            <!-- DATE -->

            <div class="edit-field">

                <label for="editTournamentDate">
                    TOURNAMENT DATE
                </label>

                <input
                    type="date"
                    id="editTournamentDate"
                    name="tournament_date"
                    required
                >

            </div>


            <!-- STATUS -->

            <div class="edit-field">

                <label for="editTournamentStatus">
                    STATUS
                </label>

                <select
                    id="editTournamentStatus"
                    name="status"
                >

                    <option value="Registration Open">
                        Registration Open
                    </option>

                    <option value="Team Registration Open">
                        Team Registration Open
                    </option>

                    <option value="Cash Prizes Available">
                        Cash Prizes Available
                    </option>

                    <option value="Coming Soon">
                        Coming Soon
                    </option>

                    <option value="Registration Closed">
                        Registration Closed
                    </option>

                </select>

            </div>


            <!-- EDIT ACTIONS -->

            <div class="confirm-actions">

                <button
                    type="button"
                    class="confirm-no"
                    id="editCancel"
                >
                    CANCEL
                </button>

                <button
                    type="submit"
                    class="confirm-yes"
                >
                    SAVE CHANGES
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ========================================
     DELETE CONFIRMATION MODAL
======================================== -->

<div
    class="confirm-overlay"
    id="deleteConfirmOverlay"
    hidden
>

    <div
        class="confirm-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteConfirmTitle"
    >

        <h3 id="deleteConfirmTitle">
            DELETE TOURNAMENT
        </h3>

        <p id="deleteConfirmMessage">
            Are you sure you want to delete this tournament?
        </p>

        <div class="confirm-actions">

            <button
                type="button"
                class="confirm-no"
                id="deleteConfirmCancel"
            >
                CANCEL
            </button>

            <button
                type="button"
                class="confirm-yes"
                id="deleteConfirmOkay"
            >
                DELETE
            </button>

        </div>

    </div>

</div>


<script>

/* ========================================
   EDIT TOURNAMENT
======================================== */

function openEditModal(
    id,
    title,
    description,
    tournamentDate,
    status
) {

    const overlay =
        document.getElementById(
            "editOverlay"
        );

    const idInput =
        document.getElementById(
            "editTournamentId"
        );

    const titleInput =
        document.getElementById(
            "editTournamentTitle"
        );

    const descriptionInput =
        document.getElementById(
            "editTournamentDescription"
        );

    const dateInput =
        document.getElementById(
            "editTournamentDate"
        );

    const statusInput =
        document.getElementById(
            "editTournamentStatus"
        );


    if (
        !overlay ||
        !idInput ||
        !titleInput ||
        !descriptionInput ||
        !dateInput ||
        !statusInput
    ) {

        return;

    }


    idInput.value = id;

    titleInput.value = title;

    descriptionInput.value =
        description;

    dateInput.value =
        tournamentDate;

    statusInput.value =
        status || "Registration Open";


    overlay.hidden = false;

    document.body.classList.add(
        "modal-open"
    );


    setTimeout(
        function () {

            titleInput.focus();

        },
        50
    );
}


/* ========================================
   CLOSE EDIT MODAL
======================================== */

function closeEditModal() {

    const overlay =
        document.getElementById(
            "editOverlay"
        );

    if (overlay) {

        overlay.hidden = true;

    }


    const deleteOverlay =
        document.getElementById(
            "deleteConfirmOverlay"
        );


    if (
        !deleteOverlay ||
        deleteOverlay.hidden
    ) {

        document.body.classList.remove(
            "modal-open"
        );

    }
}


/* ========================================
   DELETE CONFIRMATION
======================================== */

let tournamentFormToDelete = null;


/* ========================================
   OPEN DELETE CONFIRMATION
======================================== */

function openDeleteConfirm(
    formId,
    tournamentTitle
) {

    const form =
        document.getElementById(
            formId
        );

    const overlay =
        document.getElementById(
            "deleteConfirmOverlay"
        );

    const message =
        document.getElementById(
            "deleteConfirmMessage"
        );


    if (!form || !overlay) {

        return;

    }


    tournamentFormToDelete =
        form;


    if (message) {

        message.textContent =
            'Are you sure you want to delete "' +
            tournamentTitle +
            '"?';

    }


    overlay.hidden = false;

    document.body.classList.add(
        "modal-open"
    );
}


/* ========================================
   CLOSE DELETE MODAL
======================================== */

function closeDeleteConfirm() {

    const overlay =
        document.getElementById(
            "deleteConfirmOverlay"
        );


    if (overlay) {

        overlay.hidden = true;

    }


    tournamentFormToDelete =
        null;


    const editOverlay =
        document.getElementById(
            "editOverlay"
        );


    if (
        !editOverlay ||
        editOverlay.hidden
    ) {

        document.body.classList.remove(
            "modal-open"
        );

    }
}


/* ========================================
   DOM READY
======================================== */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const deleteOverlay =
            document.getElementById(
                "deleteConfirmOverlay"
            );

        const deleteCancelButton =
            document.getElementById(
                "deleteConfirmCancel"
            );

        const deleteOkayButton =
            document.getElementById(
                "deleteConfirmOkay"
            );

        const editOverlay =
            document.getElementById(
                "editOverlay"
            );

        const editCancelButton =
            document.getElementById(
                "editCancel"
            );


        /* ====================================
           EDIT CANCEL
        ==================================== */

        if (editCancelButton) {

            editCancelButton.addEventListener(
                "click",
                function () {

                    closeEditModal();

                }
            );

        }


        /* ====================================
           EDIT CLICK OUTSIDE
        ==================================== */

        if (editOverlay) {

            editOverlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target ===
                        editOverlay
                    ) {

                        closeEditModal();

                    }

                }
            );

        }


        /* ====================================
           DELETE CANCEL
        ==================================== */

        if (deleteCancelButton) {

            deleteCancelButton.addEventListener(
                "click",
                function () {

                    closeDeleteConfirm();

                }
            );

        }


        /* ====================================
           DELETE
        ==================================== */

        if (deleteOkayButton) {

            deleteOkayButton.addEventListener(
                "click",
                function () {

                    if (
                        tournamentFormToDelete &&
                        tournamentFormToDelete instanceof
                        HTMLFormElement
                    ) {

                        tournamentFormToDelete.requestSubmit();

                    }

                }
            );

        }


        /* ====================================
           DELETE CLICK OUTSIDE
        ==================================== */

        if (deleteOverlay) {

            deleteOverlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target ===
                        deleteOverlay
                    ) {

                        closeDeleteConfirm();

                    }

                }
            );

        }


        /* ====================================
           ESCAPE
        ==================================== */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape"
                ) {

                    closeEditModal();

                    closeDeleteConfirm();

                }

            }
        );

    }
);

</script>

</body>

</html>