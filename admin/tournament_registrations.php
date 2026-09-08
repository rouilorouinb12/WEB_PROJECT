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
                    ========================================
                    TRANSACTION
                    ========================================
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


                    if (
                        $update->rowCount() !== 1
                    ) {

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
                        ========================================
                        PREVENT DUPLICATE NOTIFICATION
                        ========================================
                        */

                        $duplicateCheck =
                            $conn->prepare("

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


                        if (
                            !$existingNotification
                        ) {

                            $notifyStmt =
                                $conn->prepare("

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

        tr.user_id,

        tr.tournament_id,

        tr.team_name,

        tr.contact_number,

        tr.message,

        tr.status,

        tr.payment_method,

        tr.payment_reference,

        tr.payment_status,

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

$registrations =
    $stmt->fetchAll();


/* ========================================
   COUNTS
======================================== */

$countStmt = $conn->query("

    SELECT

        SUM(status = 'pending')
            AS pending_count,

        SUM(status = 'accepted')
            AS accepted_count,

        SUM(status = 'rejected')
            AS rejected_count

    FROM tournament_registrations

");

$counts =
    $countStmt->fetch();


$pendingCount =
    (int)(
        $counts["pending_count"]
        ?? 0
    );


$acceptedCount =
    (int)(
        $counts["accepted_count"]
        ?? 0
    );


$rejectedCount =
    (int)(
        $counts["rejected_count"]
        ?? 0
    );


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

            --green:
                #39ff14;

            --black:
                #000;

            --white:
                #fff;

            --dark:
                #080808;

            --border:
                rgba(57, 255, 20, .35);

            --muted:
                #999;

        }


        * {

            box-sizing:
                border-box;

        }


        html,
        body {

            margin:
                0;

            padding:
                0;

        }


        body {

            background:
                var(--black);

            color:
                var(--white);

            font-family:
                "Rajdhani",
                sans-serif;

        }


        a {

            color:
                inherit;

            text-decoration:
                none;

        }


        button {

            font-family:
                inherit;

        }


        .page {

            width:
                min(1200px, 94%);

            margin:
                0 auto;

            padding:
                45px 0 60px;

        }


        .topbar {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;

            margin-bottom:
                35px;

            padding-bottom:
                18px;

            border-bottom:
                1px solid
                rgba(57, 255, 20, .25);

        }


        .brand {

            color:
                var(--green);

            font:
                800 15px
                "Orbitron",
                sans-serif;

            letter-spacing:
                .5px;

        }


        .back-link {

            border:
                1px solid
                var(--green);

            padding:
                10px 16px;

            border-radius:
                4px;

            color:
                var(--green);

            font:
                700 11px
                "Orbitron",
                sans-serif;

            transition:
                .2s ease;

        }


        .back-link:hover {

            background:
                var(--green);

            color:
                #000;

        }


        .page-kicker {

            margin:
                0 0 10px;

            color:
                var(--green);

            font:
                700 12px
                "Orbitron",
                sans-serif;

        }


        .page-title {

            margin:
                0 0 30px;

            font:
                800 clamp(30px, 5vw, 54px)
                "Orbitron",
                sans-serif;

            letter-spacing:
                -1px;

        }


        .page-title span {

            color:
                var(--green);

        }


        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                18px;

            margin-bottom:
                28px;

        }


        .stat {

            border:
                1px solid
                var(--border);

            border-radius:
                5px;

            background:
                var(--dark);

            padding:
                20px;

        }


        .stat-label {

            display:
                block;

            color:
                #bbb;

            font:
                700 11px
                "Orbitron",
                sans-serif;

            margin-bottom:
                10px;

        }


        .stat-number {

            color:
                var(--green);

            font:
                800 30px
                "Orbitron",
                sans-serif;

        }


        .alert {

            padding:
                14px 16px;

            margin-bottom:
                22px;

            border-radius:
                4px;

            font-weight:
                600;

        }


        .alert.success {

            border:
                1px solid
                var(--green);

            color:
                var(--green);

            background:
                rgba(57, 255, 20, .05);

        }


        .alert.error {

            border:
                1px solid
                #ff4747;

            color:
                #ff6b6b;

            background:
                rgba(255, 0, 0, .05);

        }


        .registrations {

            display:
                grid;

            gap:
                20px;

        }


        .registration-card {

            border:
                1px solid
                var(--border);

            border-radius:
                5px;

            background:
                #050505;

            padding:
                24px;

        }


        .registration-header {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                20px;

            margin-bottom:
                20px;

        }


        .registration-title h2 {

            margin:
                0 0 7px;

            color:
                var(--green);

            font:
                800 18px
                "Orbitron",
                sans-serif;

        }


        .registration-title p {

            margin:
                0;

            color:
                #bbb;

            font-size:
                15px;

        }


        .status {

            flex-shrink:
                0;

            padding:
                8px 12px;

            border-radius:
                4px;

            font:
                800 10px
                "Orbitron",
                sans-serif;

            border:
                1px solid;

        }


        .status.pending {

            color:
                #ffd84a;

            border-color:
                #ffd84a;

        }


        .status.accepted {

            color:
                var(--green);

            border-color:
                var(--green);

        }


        .status.rejected {

            color:
                #ff5757;

            border-color:
                #ff5757;

        }


        .details {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap:
                16px 22px;

        }


        .detail {

            border:
                1px solid
                #222;

            border-radius:
                4px;

            background:
                #080808;

            padding:
                14px;

        }


        .detail.full {

            grid-column:
                1 / -1;

        }


        .detail-label {

            display:
                block;

            margin-bottom:
                6px;

            color:
                #888;

            font:
                700 10px
                "Orbitron",
                sans-serif;

        }


        .detail-value {

            color:
                #fff;

            font-size:
                15px;

            line-height:
                1.4;

            white-space:
                pre-line;

            overflow-wrap:
                anywhere;

        }


        .actions {

            display:
                flex;

            gap:
                10px;

            flex-wrap:
                wrap;

            margin-top:
                20px;

            padding-top:
                18px;

            border-top:
                1px solid
                #1a1a1a;

        }


        .action-form {

            margin:
                0;

        }


        .action-button {

            min-width:
                110px;

            min-height:
                40px;

            padding:
                0 16px;

            border-radius:
                4px;

            font:
                800 10px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                background .2s ease,
                color .2s ease;

        }


        .accept-button {

            background:
                var(--green);

            color:
                #000;

            border:
                1px solid
                var(--green);

        }


        .accept-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 0 15px
                rgba(
                    57,
                    255,
                    20,
                    .35
                );

        }


        .reject-button {

            background:
                transparent;

            color:
                #ff5757;

            border:
                1px solid
                #ff5757;

        }


        .reject-button:hover {

            background:
                #ff5757;

            color:
                #000;

            transform:
                translateY(-2px);

        }


        .view-receipt-button {

            background:
                transparent;

            color:
                var(--green);

            border:
                1px solid
                var(--green);

        }


        .view-receipt-button:hover {

            background:
                var(--green);

            color:
                #000;

            transform:
                translateY(-2px);

            box-shadow:
                0 0 15px
                rgba(
                    57,
                    255,
                    20,
                    .35
                );

        }


        .date-added {

            margin-top:
                16px;

            color:
                #666;

            font-size:
                12px;

        }


        .empty {

            border:
                1px dashed
                #333;

            border-radius:
                5px;

            padding:
                45px 20px;

            text-align:
                center;

            color:
                var(--muted);

        }


        .empty strong {

            display:
                block;

            color:
                var(--green);

            font:
                700 16px
                "Orbitron",
                sans-serif;

            margin-bottom:
                8px;

        }


        /* ========================================
           CENTER CONFIRMATION MODAL
        ======================================== */

        .confirm-overlay {

            position:
                fixed;

            inset:
                0;

            z-index:
                5000;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                20px;

            background:
                rgba(0, 0, 0, .82);

            backdrop-filter:
                blur(3px);

        }


        .confirm-overlay[hidden] {

            display:
                none;

        }


        .confirm-modal {

            width:
                min(430px, 100%);

            background:
                #050505;

            border:
                1px solid
                var(--green);

            border-radius:
                6px;

            padding:
                28px;

            text-align:
                center;

            box-shadow:
                0 0 35px
                rgba(
                    57,
                    255,
                    20,
                    .18
                );

            animation:
                confirmModalIn
                .18s
                ease;

        }


        .confirm-modal h3 {

            margin:
                0 0 12px;

            color:
                var(--green);

            font:
                800 16px
                "Orbitron",
                sans-serif;

        }


        .confirm-modal p {

            margin:
                0 0 24px;

            color:
                #fff;

            font-size:
                13px;

            line-height:
                1.5;

        }


        .confirm-actions {

            display:
                flex;

            justify-content:
                center;

            gap:
                10px;

        }


        .confirm-no,
        .confirm-yes {

            min-width:
                110px;

            height:
                42px;

            padding:
                0 18px;

            border-radius:
                4px;

            font:
                800 10px
                "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .confirm-no {

            background:
                transparent;

            color:
                #ff5757;

            border:
                1px solid
                #ff5757;

        }


        .confirm-no:hover {

            background:
                #ff5757;

            color:
                #000;

        }


        .confirm-yes {

            background:
                var(--green);

            color:
                #000;

            border:
                1px solid
                var(--green);

        }


        .confirm-yes:hover {

            box-shadow:
                0 0 15px
                rgba(
                    57,
                    255,
                    20,
                    .35
                );

            transform:
                translateY(-2px);

        }


        @keyframes confirmModalIn {

            from {

                opacity:
                    0;

                transform:
                    scale(.96)
                    translateY(8px);

            }


            to {

                opacity:
                    1;

                transform:
                    scale(1)
                    translateY(0);

            }

        }


        /* ========================================
           TOURNAMENT RECEIPT MODAL
        ======================================== */

        .admin-receipt-overlay {

            position:
                fixed;

            inset:
                0;

            z-index:
                6000;

            display:
                none;

            align-items:
                center;

            justify-content:
                center;

            padding:
                20px;

            background:
                rgba(
                    0,
                    0,
                    0,
                    .88
                );

        }


        .admin-receipt-overlay.open {

            display:
                flex;

        }


        .admin-receipt-card {

            position:
                relative;

            width:
                min(
                    680px,
                    100%
                );

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
                rgba(
                    57,
                    255,
                    20,
                    .15
                );

        }


        .admin-receipt-close {

            position:
                absolute;

            top:
                12px;

            right:
                12px;

            width:
                32px;

            height:
                32px;

            border:
                1px solid
                #555;

            border-radius:
                50%;

            background:
                transparent;

            color:
                #fff;

            font-size:
                18px;

            cursor:
                pointer;

            z-index:
                2;

        }


        .admin-receipt-close:hover {

            border-color:
                #39FF14;

            color:
                #39FF14;

        }


        .admin-receipt-header {

            padding:
                30px;

            text-align:
                center;

            border-bottom:
                1px solid
                rgba(
                    57,
                    255,
                    20,
                    .22
                );

        }


        .admin-receipt-logo {

            width:
                100px;

            display:
                block;

            margin:
                0 auto 12px;

        }


        .admin-receipt-title {

            margin:
                0;

            color:
                #fff;

            font:
                700 20px
                "Orbitron",
                sans-serif;

        }


        .admin-receipt-subtitle {

            margin:
                7px 0 0;

            color:
                #39FF14;

            font:
                600 9px
                "Orbitron",
                sans-serif;

        }


        .admin-receipt-body {

            padding:
                25px 30px 30px;

        }


        .admin-receipt-row {

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
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

        }


        .admin-receipt-label {

            color:
                rgba(
                    255,
                    255,
                    255,
                    .52
                );

            font-size:
                10px;

            font-weight:
                700;

            text-transform:
                uppercase;

        }


        .admin-receipt-value {

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


        .admin-receipt-status {

            color:
                #39FF14;

        }


        .admin-receipt-actions {

            display:
                flex;

            justify-content:
                center;

            margin-top:
                20px;

        }


        .admin-receipt-print {

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


        .admin-receipt-print:hover {

            background:
                transparent;

            color:
                #39FF14;

        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 800px) {

            .stats {

                grid-template-columns:
                    1fr;

            }


            .registration-header {

                flex-direction:
                    column;

            }


            .details {

                grid-template-columns:
                    1fr;

            }


            .detail.full {

                grid-column:
                    auto;

            }

        }


        @media (max-width: 600px) {

            .confirm-modal {

                padding:
                    22px 18px;

            }


            .confirm-actions {

                flex-direction:
                    column;

            }


            .confirm-no,
            .confirm-yes {

                width:
                    100%;

            }


            .admin-receipt-card {

                max-height:
                    94vh;

            }


            .admin-receipt-header {

                padding:
                    25px 18px;

            }


            .admin-receipt-body {

                padding:
                    20px;

            }


            .admin-receipt-row {

                grid-template-columns:
                    1fr;

                gap:
                    5px;

            }


            .admin-receipt-value {

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


            .admin-receipt-overlay {

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


            .admin-receipt-card {

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

                overflow:
                    visible !important;

                border:
                    1px solid
                    #39FF14 !important;

                border-radius:
                    0 !important;

                box-shadow:
                    none !important;

            }


            .admin-receipt-close,
            .admin-receipt-print {

                display:
                    none !important;

            }


            .admin-receipt-title,
            .admin-receipt-value {

                color:
                    #fff !important;

            }


            .admin-receipt-label {

                color:
                    rgba(
                        255,
                        255,
                        255,
                        .65
                    ) !important;

            }


            .admin-receipt-status {

                color:
                    #39FF14 !important;

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


            <?php foreach (
                $registrations
                as $registration
            ): ?>


                <article
                    class="registration-card"
                >


                    <!-- HEADER -->

                    <div
                        class="registration-header"
                    >


                        <div
                            class="registration-title"
                        >


                            <h2>

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "tournament_title"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </h2>


                            <p>

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "customer_name"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "customer_email"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </p>


                        </div>


                        <span
                            class="
                                status
                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "status"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            "
                        >

                            <?= strtoupper(
                                htmlspecialchars(
                                    (string)
                                    $registration[
                                        "status"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ) ?>

                        </span>


                    </div>


                    <!-- DETAILS -->

                    <div class="details">


                        <div class="detail">

                            <span
                                class="detail-label"
                            >

                                TOURNAMENT DATE

                            </span>


                            <div
                                class="detail-value"
                            >

                                <?= date(
                                    "F d, Y",
                                    strtotime(
                                        (string)
                                        $registration[
                                            "tournament_date"
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span
                                class="detail-label"
                            >

                                TEAM NAME

                            </span>


                            <div
                                class="detail-value"
                            >

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "team_name"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span
                                class="detail-label"
                            >

                                CONTACT NUMBER

                            </span>


                            <div
                                class="detail-value"
                            >

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "contact_number"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail">

                            <span
                                class="detail-label"
                            >

                                CUSTOMER EMAIL

                            </span>


                            <div
                                class="detail-value"
                            >

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "customer_email"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                        <div class="detail full">

                            <span
                                class="detail-label"
                            >

                                TEAM MEMBERS / MESSAGE

                            </span>


                            <div
                                class="detail-value"
                            >

                                <?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "message"
                                    ],
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
                                (string)
                                $registration[
                                    "created_at"
                                ]
                            )
                        ) ?>

                    </div>


                    <!-- ========================================
                         PENDING ACTIONS
                    ======================================== -->

                    <?php if (
                        $registration["status"]
                        === "pending"
                    ): ?>


                        <div class="actions">


                            <!-- ACCEPT -->

                            <form
                                method="POST"
                                class="action-form"
                                id="
                                    acceptForm
                                    <?= (int)$registration["id"] ?>
                                "
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
                                    name="registration_id"
                                    value="<?= (int)$registration["id"] ?>"
                                >


                                <input
                                    type="hidden"
                                    name="status"
                                    value="accepted"
                                >


                                <button
                                    type="button"
                                    class="
                                        action-button
                                        accept-button
                                    "
                                    onclick="
                                        openTournamentConfirm(
                                            document.getElementById(
                                                'acceptForm<?= (int)$registration["id"] ?>'
                                            ),
                                            'Accept this tournament registration?'
                                        )
                                    "
                                >

                                    ACCEPT

                                </button>


                            </form>


                            <!-- REJECT -->

                            <form
                                method="POST"
                                class="action-form"
                                id="
                                    rejectForm
                                    <?= (int)$registration["id"] ?>
                                "
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
                                    name="registration_id"
                                    value="<?= (int)$registration["id"] ?>"
                                >


                                <input
                                    type="hidden"
                                    name="status"
                                    value="rejected"
                                >


                                <button
                                    type="button"
                                    class="
                                        action-button
                                        reject-button
                                    "
                                    onclick="
                                        openTournamentConfirm(
                                            document.getElementById(
                                                'rejectForm<?= (int)$registration["id"] ?>'
                                            ),
                                            'Reject this tournament registration?'
                                        )
                                    "
                                >

                                    REJECT

                                </button>


                            </form>


                        </div>


                    <?php endif; ?>


                    <!-- ========================================
                         ACCEPTED ACTIONS
                    ======================================== -->

                    <?php if (
                        $registration["status"]
                        === "accepted"
                    ): ?>


                        <div class="actions">


                            <button
                                type="button"
                                class="
                                    action-button
                                    view-receipt-button
                                "
                                onclick="openTournamentReceipt(this)"
                                data-registration-id="<?= (int)$registration["id"] ?>"
                                data-tournament-id="<?= (int)$registration["tournament_id"] ?>"
                                data-tournament="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "tournament_title"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-tournament-date="<?= htmlspecialchars(
                                    date(
                                        "F d, Y",
                                        strtotime(
                                            (string)
                                            $registration[
                                                "tournament_date"
                                            ]
                                        )
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-customer="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "customer_name"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-email="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "customer_email"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-team="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "team_name"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-contact="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "contact_number"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-message="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "message"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-payment-method="<?= htmlspecialchars(
                                    (string)(
                                        $registration[
                                            "payment_method"
                                        ]
                                        ?? "Cash"
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-payment-reference="<?= htmlspecialchars(
                                    (string)(
                                        $registration[
                                            "payment_reference"
                                        ]
                                        ?? ""
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-payment-status="<?= htmlspecialchars(
                                    (string)(
                                        $registration[
                                            "payment_status"
                                        ]
                                        ?? ""
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-status="<?= htmlspecialchars(
                                    (string)
                                    $registration[
                                        "status"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                data-created="<?= htmlspecialchars(
                                    date(
                                        "F d, Y h:i A",
                                        strtotime(
                                            (string)
                                            $registration[
                                                "created_at"
                                            ]
                                        )
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                                VIEW RECEIPT

                            </button>


                        </div>


                    <?php endif; ?>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


<!-- ========================================
     CENTER CONFIRMATION MODAL
======================================== -->

<div
    class="confirm-overlay"
    id="tournamentConfirmOverlay"
    hidden
>


    <div
        class="confirm-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tournamentConfirmTitle"
    >


        <h3
            id="tournamentConfirmTitle"
        >

            CONFIRM TOURNAMENT

        </h3>


        <p
            id="tournamentConfirmMessage"
        >

            Accept this tournament registration?

        </p>


        <div class="confirm-actions">


            <button
                type="button"
                class="confirm-no"
                id="tournamentConfirmCancel"
            >

                CANCEL

            </button>


            <button
                type="button"
                class="confirm-yes"
                id="tournamentConfirmOkay"
            >

                OK

            </button>


        </div>


    </div>

</div>


<!-- ========================================
     ADMIN TOURNAMENT RECEIPT
======================================== -->

<div
    class="admin-receipt-overlay"
    id="adminReceiptOverlay"
>


    <div
        class="admin-receipt-card"
    >


        <!-- CLOSE -->

        <button
            type="button"
            class="admin-receipt-close"
            onclick="closeTournamentReceipt()"
            aria-label="Close tournament receipt"
        >

            ×

        </button>


        <!-- RECEIPT HEADER -->

        <div
            class="admin-receipt-header"
        >


            <img
                src="../assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
                class="admin-receipt-logo"
            >


            <h2
                class="admin-receipt-title"
            >

                BAIS ROUILO
                GAMING CAFE

            </h2>


            <p
                class="admin-receipt-subtitle"
            >

                TOURNAMENT REGISTRATION RECEIPT

            </p>


        </div>


        <!-- RECEIPT BODY -->

        <div
            class="admin-receipt-body"
        >


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Registration ID

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptRegistrationId"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Tournament

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptTournament"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Tournament Date

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptTournamentDate"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Customer

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptCustomer"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Email

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptEmail"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Team Name

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptTeam"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Team Members

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptMembers"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Contact Number

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptContact"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Mode of Payment

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptPaymentMethod"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
                id="receiptReferenceRow"
            >

                <span
                    class="admin-receipt-label"
                >

                    GCash Reference Number

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptPaymentReference"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Payment Status

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptPaymentStatus"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Registration Status

                </span>


                <span
                    class="
                        admin-receipt-value
                        admin-receipt-status
                    "
                    id="receiptRegistrationStatus"
                ></span>


            </div>


            <div
                class="admin-receipt-row"
            >

                <span
                    class="admin-receipt-label"
                >

                    Date Registered

                </span>


                <span
                    class="admin-receipt-value"
                    id="receiptCreated"
                ></span>


            </div>


            <!-- PRINT -->

            <div
                class="
                    admin-receipt-actions
                "
            >

                <button
                    type="button"
                    class="admin-receipt-print"
                    onclick="window.print()"
                >

                    PRINT RECEIPT

                </button>

            </div>


        </div>


    </div>


</div>


<!-- ========================================
     JAVASCRIPT
======================================== -->

<script>

let tournamentFormToSubmit = null;


/* ========================================
   OPEN CONFIRMATION
======================================== */

function openTournamentConfirm(
    form,
    message
) {

    tournamentFormToSubmit =
        form;


    const overlay =
        document.getElementById(
            "tournamentConfirmOverlay"
        );


    const messageElement =
        document.getElementById(
            "tournamentConfirmMessage"
        );


    if (!overlay) {

        return;

    }


    if (messageElement) {

        messageElement.textContent =
            message;

    }


    overlay.hidden =
        false;


    document.body.style.overflow =
        "hidden";

}


/* ========================================
   CLOSE CONFIRMATION
======================================== */

function closeTournamentConfirm() {

    const overlay =
        document.getElementById(
            "tournamentConfirmOverlay"
        );


    if (overlay) {

        overlay.hidden =
            true;

    }


    document.body.style.overflow =
        "";


    tournamentFormToSubmit =
        null;

}


/* ========================================
   OPEN TOURNAMENT RECEIPT
======================================== */

function openTournamentReceipt(button) {

    const overlay =
        document.getElementById(
            "adminReceiptOverlay"
        );


    if (!overlay) {

        return;

    }


    /*
    ========================================
    BASIC INFORMATION
    ========================================
    */

    document.getElementById(
        "receiptRegistrationId"
    ).textContent =
        "#" +
        (button.dataset.registrationId || "");


    document.getElementById(
        "receiptTournament"
    ).textContent =
        button.dataset.tournament || "";


    document.getElementById(
        "receiptTournamentDate"
    ).textContent =
        button.dataset.tournamentDate || "";


    document.getElementById(
        "receiptCustomer"
    ).textContent =
        button.dataset.customer || "";


    document.getElementById(
        "receiptEmail"
    ).textContent =
        button.dataset.email || "";


    document.getElementById(
        "receiptTeam"
    ).textContent =
        button.dataset.team || "";


    document.getElementById(
        "receiptContact"
    ).textContent =
        button.dataset.contact || "";


    /*
    ========================================
    EXTRACT TEAM MEMBERS
    ========================================
    */

    const savedMessage =
        button.dataset.message || "";


    let teamMembers =
        savedMessage;


    const memberMatch =
        savedMessage.match(
            /Team Members:\s*(.*?)(?:\n\nAdditional Message:|$)/s
        );


    if (memberMatch) {

        teamMembers =
            memberMatch[1].trim();

    }


    document.getElementById(
        "receiptMembers"
    ).textContent =
        teamMembers;


    /*
    ========================================
    PAYMENT METHOD
    ========================================
    */

    const paymentMethod =
        button.dataset.paymentMethod ||
        "Cash";


    const normalizedPaymentMethod =
        paymentMethod.trim();


    document.getElementById(
        "receiptPaymentMethod"
    ).textContent =
        normalizedPaymentMethod.toUpperCase();


    /*
    ========================================
    GCASH REFERENCE
    ========================================
    */

    const paymentReference =
        button.dataset.paymentReference ||
        "";


    const referenceRow =
        document.getElementById(
            "receiptReferenceRow"
        );


    const referenceValue =
        document.getElementById(
            "receiptPaymentReference"
        );


    if (
        normalizedPaymentMethod.toLowerCase()
            === "gcash" &&
        paymentReference.trim() !== ""
    ) {

        referenceRow.style.display =
            "grid";


        referenceValue.textContent =
            paymentReference;

    } else {

        referenceRow.style.display =
            "none";


        referenceValue.textContent =
            "";

    }


    /*
    ========================================
    PAYMENT STATUS
    ========================================
    */

    const paymentStatus =
        button.dataset.paymentStatus ||
        "unpaid";


    document.getElementById(
        "receiptPaymentStatus"
    ).textContent =
        paymentStatus.toUpperCase();


    /*
    ========================================
    REGISTRATION STATUS
    ========================================
    */

    const registrationStatus =
        button.dataset.status ||
        "accepted";


    document.getElementById(
        "receiptRegistrationStatus"
    ).textContent =
        registrationStatus.toUpperCase();


    /*
    ========================================
    CREATED DATE
    ========================================
    */

    document.getElementById(
        "receiptCreated"
    ).textContent =
        button.dataset.created || "";


    /*
    ========================================
    SHOW MODAL
    ========================================
    */

    overlay.classList.add(
        "open"
    );


    document.body.style.overflow =
        "hidden";

}


/* ========================================
   CLOSE TOURNAMENT RECEIPT
======================================== */

function closeTournamentReceipt() {

    const overlay =
        document.getElementById(
            "adminReceiptOverlay"
        );


    if (overlay) {

        overlay.classList.remove(
            "open"
        );

    }


    document.body.style.overflow =
        "";

}


/* ========================================
   DOM READY
======================================== */

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const confirmOverlay =
            document.getElementById(
                "tournamentConfirmOverlay"
            );


        const cancelButton =
            document.getElementById(
                "tournamentConfirmCancel"
            );


        const okayButton =
            document.getElementById(
                "tournamentConfirmOkay"
            );


        /*
        ========================================
        CANCEL
        ========================================
        */

        if (cancelButton) {

            cancelButton.addEventListener(
                "click",
                function () {

                    closeTournamentConfirm();

                }
            );

        }


        /*
        ========================================
        OK
        ========================================
        */

        if (okayButton) {

            okayButton.addEventListener(
                "click",
                function () {

                    if (
                        tournamentFormToSubmit
                    ) {

                        /*
                        Submit the actual form.
                        */

                        tournamentFormToSubmit.submit();

                    }

                }
            );

        }


        /*
        ========================================
        CLICK OUTSIDE CONFIRM MODAL
        ========================================
        */

        if (confirmOverlay) {

            confirmOverlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target ===
                        confirmOverlay
                    ) {

                        closeTournamentConfirm();

                    }

                }
            );

        }


        /*
        ========================================
        ESCAPE
        ========================================
        */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key ===
                    "Escape"
                ) {

                    closeTournamentConfirm();

                    closeTournamentReceipt();

                }

            }
        );


        /*
        ========================================
        RECEIPT CLICK OUTSIDE
        ========================================
        */

        const receiptOverlay =
            document.getElementById(
                "adminReceiptOverlay"
            );


        if (receiptOverlay) {

            receiptOverlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target ===
                        receiptOverlay
                    ) {

                        closeTournamentReceipt();

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>