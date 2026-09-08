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

/* ========================================
   HANDLE STATUS ACTION
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $error = "Invalid request. Please try again.";

    } else {

        $registrationId = filter_input(
            INPUT_POST,
            "registration_id",
            FILTER_VALIDATE_INT
        );

        $status = $_POST["status"] ?? "";

        $allowedStatuses = [
            "accepted",
            "rejected"
        ];

        if (!$registrationId) {

            $error = "Invalid registration.";

        } elseif (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            $error = "Invalid registration status.";

        } else {

            /* ========================================
               GET REGISTRATION INFO
            ======================================== */
            $check = $conn->prepare("
                SELECT
                    tr.id,
                    tr.user_id,
                    tr.status,
                    tr.team_name,
                    t.title AS tournament_title

                FROM tournament_registrations tr

                INNER JOIN tournaments t
                    ON t.id = tr.tournament_id

                WHERE tr.id = :id

                LIMIT 1
            ");

            $check->execute([
                ":id" => $registrationId
            ]);

            $registration = $check->fetch();

            if (!$registration) {

                $error =
                    "Registration not found.";

            } elseif (
                $registration["status"] !== "pending"
            ) {

                $error =
                    "This registration has already been processed.";

            } else {

                try {

                    /*
                     * Transaction keeps the status change
                     * and notification together.
                     */
                    $conn->beginTransaction();

                    /* ========================================
                       UPDATE REGISTRATION STATUS
                    ======================================== */
                    $update = $conn->prepare("
                        UPDATE tournament_registrations

                        SET status = :status

                        WHERE id = :id
                          AND status = 'pending'
                    ");

                    $update->execute([
                        ":status" => $status,
                        ":id" => $registrationId
                    ]);

                    if ($update->rowCount() !== 1) {

                        throw new RuntimeException(
                            "Unable to update the tournament registration."
                        );
                    }

                    /* ========================================
                       ACCEPTED
                       CREATE CUSTOMER NOTIFICATION
                    ======================================== */
                    if ($status === "accepted") {

                        $userId =
                            (int)$registration["user_id"];

                        $tournamentTitle =
                            (string)$registration[
                                "tournament_title"
                            ];

                        $notificationTitle =
                            "TOURNAMENT APPROVED";

                        $notificationMessage =
                            "Your registration for "
                            . $tournamentTitle
                            . " has been approved successfully.";

                        /*
                         * Prevent duplicate notifications.
                         */
                        $duplicateCheck = $conn->prepare("
                            SELECT id

                            FROM notifications

                            WHERE user_id = ?
                              AND type = 'tournament'
                              AND title = ?
                              AND message = ?

                            LIMIT 1
                        ");

                        $duplicateCheck->execute([
                            $userId,
                            $notificationTitle,
                            $notificationMessage
                        ]);

                        $existingNotification =
                            $duplicateCheck->fetchColumn();

                        if (!$existingNotification) {

                            $notifyStmt = $conn->prepare("
                                INSERT INTO notifications
                                (
                                    user_id,
                                    type,
                                    title,
                                    message
                                )

                                VALUES
                                (
                                    ?,
                                    'tournament',
                                    ?,
                                    ?
                                )
                            ");

                            $notifyStmt->execute([
                                $userId,
                                $notificationTitle,
                                $notificationMessage
                            ]);
                        }

                        $success =
                            "Tournament registration accepted successfully.";

                    } else {

                        $success =
                            "Tournament registration rejected successfully.";
                    }

                    $conn->commit();

                } catch (Throwable $e) {

                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    $error =
                        "Unable to process the tournament registration.";
                }
            }
        }
    }
}

/* ========================================
   GET REGISTRATIONS
======================================== */

$stmt = $conn->query("
    SELECT
        tr.id,
        tr.team_name,
        tr.contact_number,
        tr.message,
        tr.status,
        tr.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        t.title AS tournament_title,
        t.tournament_date

    FROM tournament_registrations tr

    INNER JOIN users u
        ON u.id = tr.user_id

    INNER JOIN tournaments t
        ON t.id = tr.tournament_id

    ORDER BY
        CASE
            WHEN tr.status = 'pending' THEN 0
            WHEN tr.status = 'accepted' THEN 1
            ELSE 2
        END,

        tr.created_at DESC
");

$registrations = $stmt->fetchAll();

/* ========================================
   COUNTS
======================================== */

$countStmt = $conn->query("
    SELECT
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'accepted') AS accepted_count,
        SUM(status = 'rejected') AS rejected_count

    FROM tournament_registrations
");

$counts = $countStmt->fetch();

$pendingCount =
    (int)($counts["pending_count"] ?? 0);

$acceptedCount =
    (int)($counts["accepted_count"] ?? 0);

$rejectedCount =
    (int)($counts["rejected_count"] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Tournament Registrations | Admin
    </title>

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
            --border: rgba(57, 255, 20, .35);
            --muted: #999;
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
            background: var(--black);
            color: var(--white);
            font-family: "Rajdhani", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font-family: inherit;
        }

        .page {
            width: min(1200px, 94%);
            margin: 0 auto;
            padding: 45px 0 60px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 35px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(57, 255, 20, .25);
        }

        .brand {
            color: var(--green);
            font: 800 15px "Orbitron", sans-serif;
            letter-spacing: .5px;
        }

        .back-link {
            border: 1px solid var(--green);
            padding: 10px 16px;
            border-radius: 4px;
            color: var(--green);
            font: 700 11px "Orbitron", sans-serif;
            transition: .2s ease;
        }

        .back-link:hover {
            background: var(--green);
            color: #000;
        }

        .page-kicker {
            margin: 0 0 10px;
            color: var(--green);
            font: 700 12px "Orbitron", sans-serif;
        }

        .page-title {
            margin: 0 0 30px;
            font: 800 clamp(30px, 5vw, 54px) "Orbitron", sans-serif;
            letter-spacing: -1px;
        }

        .page-title span {
            color: var(--green);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat {
            border: 1px solid var(--border);
            border-radius: 5px;
            background: var(--dark);
            padding: 20px;
        }

        .stat-label {
            display: block;
            color: #bbb;
            font: 700 11px "Orbitron", sans-serif;
            margin-bottom: 10px;
        }

        .stat-number {
            color: var(--green);
            font: 800 30px "Orbitron", sans-serif;
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 22px;
            border-radius: 4px;
            font-weight: 600;
        }

        .alert.success {
            border: 1px solid var(--green);
            color: var(--green);
            background: rgba(57, 255, 20, .05);
        }

        .alert.error {
            border: 1px solid #ff4747;
            color: #ff6b6b;
            background: rgba(255, 0, 0, .05);
        }

        .registrations {
            display: grid;
            gap: 20px;
        }

        .registration-card {
            border: 1px solid var(--border);
            border-radius: 5px;
            background: #050505;
            padding: 24px;
        }

        .registration-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .registration-title h2 {
            margin: 0 0 7px;
            color: var(--green);
            font: 800 18px "Orbitron", sans-serif;
        }

        .registration-title p {
            margin: 0;
            color: #bbb;
            font-size: 15px;
        }

        .status {
            flex-shrink: 0;
            padding: 8px 12px;
            border-radius: 4px;
            font: 800 10px "Orbitron", sans-serif;
            border: 1px solid;
        }

        .status.pending {
            color: #ffd84a;
            border-color: #ffd84a;
        }

        .status.accepted {
            color: var(--green);
            border-color: var(--green);
        }

        .status.rejected {
            color: #ff5757;
            border-color: #ff5757;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 22px;
        }

        .detail {
            border: 1px solid #222;
            border-radius: 4px;
            background: #080808;
            padding: 14px;
        }

        .detail.full {
            grid-column: 1 / -1;
        }

        .detail-label {
            display: block;
            margin-bottom: 6px;
            color: #888;
            font: 700 10px "Orbitron", sans-serif;
        }

        .detail-value {
            color: #fff;
            font-size: 15px;
            line-height: 1.4;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #1a1a1a;
        }

        .action-form {
            margin: 0;
        }

        .action-button {
            min-width: 110px;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 4px;
            font: 800 10px "Orbitron", sans-serif;
            cursor: pointer;
            transition:
                transform .2s ease,
                box-shadow .2s ease,
                background .2s ease,
                color .2s ease;
        }

        .accept-button {
            background: var(--green);
            color: #000;
            border: 1px solid var(--green);
        }

        .accept-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(57, 255, 20, .35);
        }

        .reject-button {
            background: transparent;
            color: #ff5757;
            border: 1px solid #ff5757;
        }

        .reject-button:hover {
            background: #ff5757;
            color: #000;
            transform: translateY(-2px);
        }

        .date-added {
            margin-top: 16px;
            color: #666;
            font-size: 12px;
        }

        .empty {
            border: 1px dashed #333;
            border-radius: 5px;
            padding: 45px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty strong {
            display: block;
            color: var(--green);
            font: 700 16px "Orbitron", sans-serif;
            margin-bottom: 8px;
        }

        @media (max-width: 800px) {

            .stats {
                grid-template-columns: 1fr;
            }

            .registration-header {
                flex-direction: column;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .detail.full {
                grid-column: auto;
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
         PAGE TITLE
    ======================================== -->

    <p class="page-kicker">
        ADMIN MANAGEMENT
    </p>

    <h1 class="page-title">

        TOURNAMENT

        <span>
            REGISTRATIONS
        </span>

    </h1>


    <!-- ========================================
         STATS
    ======================================== -->

    <div class="stats">

        <div class="stat">

            <span class="stat-label">
                PENDING
            </span>

            <div class="stat-number">
                <?= $pendingCount ?>
            </div>

        </div>


        <div class="stat">

            <span class="stat-label">
                ACCEPTED
            </span>

            <div class="stat-number">
                <?= $acceptedCount ?>
            </div>

        </div>


        <div class="stat">

            <span class="stat-label">
                REJECTED
            </span>

            <div class="stat-number">
                <?= $rejectedCount ?>
            </div>

        </div>

    </div>


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
         REGISTRATIONS
    ======================================== -->

    <?php if (!$registrations): ?>

        <div class="empty">

            <strong>
                NO REGISTRATIONS YET
            </strong>

            There are currently no tournament registrations.

        </div>

    <?php else: ?>

        <div class="registrations">

            <?php foreach ($registrations as $registration): ?>

                <article class="registration-card">

                    <!-- HEADER -->

                    <div class="registration-header">

                        <div class="registration-title">

                            <h2>
                                <?= htmlspecialchars(
                                    (string)$registration["tournament_title"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </h2>

                            <p>

                                <?= htmlspecialchars(
                                    (string)$registration["customer_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    (string)$registration["customer_email"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </p>

                        </div>


                        <span
                            class="status <?= htmlspecialchars(
                                (string)$registration["status"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                            <?= strtoupper(
                                htmlspecialchars(
                                    (string)$registration["status"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ) ?>

                        </span>

                    </div>


                    <!-- DETAILS -->

                    <div class="details">

                        <div class="detail">

                            <span class="detail-label">
                                TOURNAMENT DATE
                            </span>

                            <div class="detail-value">

                                <?= date(
                                    "F d, Y",
                                    strtotime(
                                        (string)$registration["tournament_date"]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span class="detail-label">
                                TEAM NAME
                            </span>

                            <div class="detail-value">

                                <?= htmlspecialchars(
                                    (string)$registration["team_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span class="detail-label">
                                CONTACT NUMBER
                            </span>

                            <div class="detail-value">

                                <?= htmlspecialchars(
                                    (string)$registration["contact_number"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span class="detail-label">
                                CUSTOMER EMAIL
                            </span>

                            <div class="detail-value">

                                <?= htmlspecialchars(
                                    (string)$registration["customer_email"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail full">

                            <span class="detail-label">
                                TEAM MEMBERS / MESSAGE
                            </span>

                            <div class="detail-value">

                                <?= htmlspecialchars(
                                    (string)$registration["message"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- DATE -->

                    <div class="date-added">

                        REGISTERED:

                        <?= date(
                            "M d, Y h:i A",
                            strtotime(
                                (string)$registration["created_at"]
                            )
                        ) ?>

                    </div>


                    <!-- ACTIONS -->

                    <?php if (
                        $registration["status"] === "pending"
                    ): ?>

                        <div class="actions">

                            <!-- ACCEPT -->

                            <form
                                method="POST"
                                class="action-form"
                                onsubmit="
                                    return confirm(
                                        'Accept this tournament registration?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        csrfToken(),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="registration_id"
                                    value="<?= (int)$registration["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="status"
                                    value="accepted"
                                >

                                <button
                                    type="submit"
                                    class="action-button accept-button"
                                >
                                    ACCEPT
                                </button>

                            </form>


                            <!-- REJECT -->

                            <form
                                method="POST"
                                class="action-form"
                                onsubmit="
                                    return confirm(
                                        'Reject this tournament registration?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        csrfToken(),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="registration_id"
                                    value="<?= (int)$registration["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="status"
                                    value="rejected"
                                >

                                <button
                                    type="submit"
                                    class="action-button reject-button"
                                >
                                    REJECT
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>