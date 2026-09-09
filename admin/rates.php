<?php

declare(strict_types=1);

require_once "../database.php";
require_once "../auth.php";

requireLogin();

/* ========================================
   ADMIN ONLY
======================================== */

if (($_SESSION["user_role"] ?? "customer") !== "admin") {
    http_response_code(403);
    exit("Access denied.");
}


/* ========================================
   VARIABLES
======================================== */

$errors = [];
$success = "";

$editId = 0;
$duration = "";
$price = "";
$label = "";


/* ========================================
   DELETE
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete"
) {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $errors[] = "Invalid security token.";

    } else {

        $deleteId = filter_var(
            $_POST["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$deleteId) {

            $errors[] = "Invalid rate ID.";

        } else {

            $deleteStmt = $conn->prepare("
                DELETE FROM rates
                WHERE id = ?
            ");

            try {

                $deleteStmt->execute([
                    $deleteId
                ]);

                if ($deleteStmt->rowCount() === 1) {

                    $success =
                        "Rate deleted successfully.";

                } else {

                    $errors[] =
                        "Rate not found.";

                }

            } catch (PDOException $e) {

                $errors[] =
                    "Unable to delete the rate.";

            }

        }

    }
}


/* ========================================
   LOAD EDIT DATA
======================================== */

$requestedEditId = filter_var(
    $_GET["edit"] ?? null,
    FILTER_VALIDATE_INT
);

if ($requestedEditId) {

    $editStmt = $conn->prepare("
        SELECT
            id,
            duration,
            price,
            label
        FROM rates
        WHERE id = ?
        LIMIT 1
    ");

    $editStmt->execute([
        $requestedEditId
    ]);

    $editRate = $editStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($editRate) {

        $editId =
            (int)$editRate["id"];

        $duration =
            (string)$editRate["duration"];

        $price =
            (string)$editRate["price"];

        $label =
            (string)$editRate["label"];

    } else {

        $errors[] =
            "Rate not found.";

    }
}


/* ========================================
   ADD / UPDATE
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") !== "delete"
) {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $errors[] =
            "Invalid security token.";

    } else {

        $action =
            (string)(
                $_POST["action"] ?? "add"
            );

        $postedId =
            filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

        $duration =
            trim(
                (string)(
                    $_POST["duration"] ?? ""
                )
            );

        $price =
            trim(
                (string)(
                    $_POST["price"] ?? ""
                )
            );

        $label =
            trim(
                (string)(
                    $_POST["label"] ?? ""
                )
            );


        /* ========================================
           VALIDATION
        ======================================== */

        if ($duration === "") {

            $errors[] =
                "Please enter the duration.";

        } elseif (
            mb_strlen($duration) > 50
        ) {

            $errors[] =
                "Duration is too long.";

        }


        $validPrice =
            filter_var(
                $price,
                FILTER_VALIDATE_FLOAT
            );

        if (
            $price === "" ||
            $validPrice === false ||
            $validPrice < 0
        ) {

            $errors[] =
                "Please enter a valid price.";

        }


        if ($label === "") {

            $errors[] =
                "Please enter the rate label.";

        } elseif (
            mb_strlen($label) > 50
        ) {

            $errors[] =
                "Label is too long.";

        }


        /* ========================================
           ADD RATE
        ======================================== */

        if (
            !$errors &&
            $action === "add"
        ) {

            $insertStmt = $conn->prepare("
                INSERT INTO rates
                (
                    duration,
                    price,
                    label
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
            ");

            $insertStmt->execute([
                $duration,
                number_format(
                    (float)$validPrice,
                    2,
                    ".",
                    ""
                ),
                $label
            ]);

            $success =
                "Rate added successfully.";

            $duration = "";
            $price = "";
            $label = "";

            $editId = 0;
        }


        /* ========================================
           UPDATE RATE
        ======================================== */

        elseif (
            !$errors &&
            $action === "update"
        ) {

            if (!$postedId) {

                $errors[] =
                    "Invalid rate ID.";

            } else {

                $updateStmt = $conn->prepare("
                    UPDATE rates
                    SET
                        duration = ?,
                        price = ?,
                        label = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $duration,
                    number_format(
                        (float)$validPrice,
                        2,
                        ".",
                        ""
                    ),
                    $label,
                    $postedId
                ]);

                if (
                    $updateStmt->rowCount() >= 0
                ) {

                    $success =
                        "Rate updated successfully.";

                    $duration = "";
                    $price = "";
                    $label = "";

                    $editId = 0;

                }

            }

        }


        /* ========================================
           INVALID ACTION
        ======================================== */

        elseif (
            !$errors &&
            $action !== "add" &&
            $action !== "update"
        ) {

            $errors[] =
                "Invalid rate action.";

        }

    }
}


/* ========================================
   GET ALL RATES
======================================== */

$listStmt = $conn->query("
    SELECT
        id,
        duration,
        price,
        label,
        created_at
    FROM rates
    ORDER BY id ASC
");

$rates =
    $listStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

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
        MANAGE RATES | Bais Rouilo Gaming Cafe
    </title>


    <!-- GOOGLE FONTS -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Orbitron:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >


    <!-- MAIN CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <style>

        /* ========================================
           PAGE
        ======================================== */

        .rates-content {

            padding:
                50px 0 70px;

        }


        /* ========================================
           HEADER
        ======================================== */

        .rates-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap: 20px;

            flex-wrap: wrap;

            margin-bottom:
                30px;

        }


        .rates-title {

            margin: 0;

            font:
                800 36px "Orbitron",
                sans-serif;

        }


        .rates-title span {

            color:
                #39FF14;

        }


        .rates-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        /* ========================================
           BUTTONS
        ======================================== */

        .admin-button {

            display: inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                40px;

            padding:
                0 16px;

            border:
                1px solid #39FF14;

            border-radius:
                4px;

            background:
                #39FF14;

            color:
                #000;

            text-decoration:
                none;

            font:
                800 10px "Orbitron",
                sans-serif;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .admin-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 0 15px
                rgba(57,255,20,.35);

        }


        .admin-button.outline {

            background:
                transparent;

            color:
                #39FF14;

        }


        .admin-button.danger {

            background:
                transparent;

            color:
                #ff4d4d;

            border-color:
                #ff4d4d;

        }


        .admin-button.danger:hover {

            background:
                #ff4d4d;

            color:
                #000;

        }


        /* ========================================
           FORM CARD
        ======================================== */

        .rate-form-card {

            border:
                1px solid
                rgba(57,255,20,.35);

            background:
                rgba(0,0,0,.65);

            padding:
                28px;

            margin-bottom:
                35px;

        }


        .rate-form-title {

            margin:
                0 0 22px;

            color:
                #39FF14;

            font:
                800 18px "Orbitron",
                sans-serif;

        }


        .rate-form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap:
                18px 22px;

        }


        .rate-field {

            display:
                flex;

            flex-direction:
                column;

            gap:
                7px;

        }


        .rate-field label {

            color:
                #fff;

            font:
                700 11px "Orbitron",
                sans-serif;

        }


        .rate-field input {

            width:
                100%;

            padding:
                12px 14px;

            border:
                1px solid #333;

            border-radius:
                4px;

            background:
                #080808;

            color:
                #fff;

            outline:
                none;

            box-sizing:
                border-box;

            font:
                600 14px "Rajdhani",
                sans-serif;

        }


        .rate-field input:focus {

            border-color:
                #39FF14;

            box-shadow:
                0 0 10px
                rgba(57,255,20,.12);

        }


        .rate-form-actions {

            display:
                flex;

            gap:
                10px;

            flex-wrap:
                wrap;

            margin-top:
                24px;

        }


        /* ========================================
           ALERTS
        ======================================== */

        .alert {

            padding:
                13px 15px;

            border-radius:
                4px;

            margin-bottom:
                20px;

            font-weight:
                600;

        }


        .alert.success {

            border:
                1px solid #39FF14;

            color:
                #39FF14;

            background:
                rgba(57,255,20,.04);

        }


        .alert.error {

            border:
                1px solid #ff4d4d;

            color:
                #ff6b6b;

            background:
                rgba(255,0,0,.04);

        }


        /* ========================================
           RATE LIST
        ======================================== */

        .rate-list {

            display:
                grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap:
                22px;

        }


        .rate-card {

            border:
                1px solid
                rgba(57,255,20,.30);

            background:
                #050505;

            padding:
                24px;

        }


        .rate-card-top {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                20px;

            margin-bottom:
                18px;

        }


        .rate-card h2 {

            margin:
                0 0 8px;

            color:
                #39FF14;

            font:
                800 18px "Orbitron",
                sans-serif;

        }


        .rate-duration {

            color:
                #aaa;

            font:
                600 14px "Rajdhani",
                sans-serif;

        }


        .rate-price {

            text-align:
                right;

            color:
                #fff;

            font:
                800 25px "Orbitron",
                sans-serif;

        }


        .rate-price small {

            display:
                block;

            margin-top:
                4px;

            color:
                #777;

            font:
                600 10px "Rajdhani",
                sans-serif;

        }


        .rate-meta {

            padding-top:
                14px;

            border-top:
                1px solid #1c1c1c;

            color:
                #666;

            font-size:
                11px;

            margin-bottom:
                15px;

        }


        .rate-card-actions {

            display:
                flex;

            gap:
                10px;

            flex-wrap:
                wrap;

        }


        .delete-form {

            margin:
                0;

        }


        /* ========================================
           EMPTY
        ======================================== */

        .empty-rates {

            border:
                1px solid
                rgba(57,255,20,.25);

            padding:
                30px;

            text-align:
                center;

            color:
                #777;

            grid-column:
                1 / -1;

        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 850px) {

            .rate-form-grid {

                grid-template-columns:
                    1fr;

            }

            .rate-list {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 600px) {

            .rates-title {

                font-size:
                    28px;

            }

            .rate-card-top {

                flex-direction:
                    column;

            }

            .rate-price {

                text-align:
                    left;

            }

        }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
======================================== -->

<header class="site-header">

    <div class="container nav-container">


        <a
            href="../index.php"
            class="brand"
        >

            <img
                src="../assets/images/logo.png"
                alt="Bais Rouilo Gaming Cafe"
            >

        </a>


        <button
            class="menu-toggle"
            type="button"
            aria-label="Open menu"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>


        <nav
            class="main-nav"
            id="mainNav"
        >

            <a href="../index.php">
                WEBSITE
            </a>

            <a href="dashboard.php">
                DASHBOARD
            </a>

            <a href="bookings.php">
                BOOKINGS
            </a>

            <a href="reviews.php">
                REVIEWS
            </a>

            <a href="tournament_registrations.php">
                TOURNAMENTS
            </a>

            <a href="../profile.php"
                class="nav-button">
                
                PROFILE
                
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     MAIN
======================================== -->

<main class="inner-page">

    <div class="container rates-content">


        <!-- HEADER -->

        <div class="rates-header">

            <div>

                <p class="section-kicker">
                    ADMIN PANEL
                </p>

                <h1 class="rates-title">

                    MANAGE
                    <span>
                        RATES
                    </span>

                </h1>

            </div>


            <div class="rates-actions">

                <a
                    href="dashboard.php"
                    class="admin-button outline"
                >
                    BACK TO DASHBOARD
                </a>

                <a
                    href="../index.php#rates"
                    class="admin-button"
                >
                    VIEW ON WEBSITE
                </a>

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


        <?php if ($errors): ?>

            <div class="alert error">

                <?php foreach ($errors as $error): ?>

                    <div>

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             ADD / EDIT FORM
        ======================================== -->

        <div class="rate-form-card">

            <h2 class="rate-form-title">

                <?= $editId > 0
                    ? "EDIT RATE"
                    : "ADD RATE"
                ?>

            </h2>


            <form method="POST">

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
                    name="action"
                    value="<?= $editId > 0
                        ? "update"
                        : "add"
                    ?>"
                >


                <?php if ($editId > 0): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $editId ?>"
                    >

                <?php endif; ?>


                <div class="rate-form-grid">


                    <!-- DURATION -->

                    <div class="rate-field">

                        <label for="duration">
                            DURATION
                        </label>

                        <input
                            type="text"
                            id="duration"
                            name="duration"
                            maxlength="50"
                            placeholder="1 Hour"
                            value="<?= htmlspecialchars(
                                $duration,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- PRICE -->

                    <div class="rate-field">

                        <label for="price">
                            PRICE
                        </label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            step="0.01"
                            placeholder="35.00"
                            value="<?= htmlspecialchars(
                                $price,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- LABEL -->

                    <div class="rate-field">

                        <label for="label">
                            LABEL
                        </label>

                        <input
                            type="text"
                            id="label"
                            name="label"
                            maxlength="50"
                            placeholder="PER HOUR"
                            value="<?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                        >

                    </div>


                </div>


                <div class="rate-form-actions">

                    <button
                        type="submit"
                        class="admin-button"
                    >

                        <?= $editId > 0
                            ? "UPDATE RATE"
                            : "ADD RATE"
                        ?>

                    </button>


                    <?php if ($editId > 0): ?>

                        <a
                            href="rates.php"
                            class="admin-button outline"
                        >
                            CANCEL
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


        <!-- ========================================
             RATE LIST
        ======================================== -->

        <div class="rate-list">


            <?php if (!$rates): ?>

                <div class="empty-rates">

                    NO RATES AVAILABLE.

                </div>

            <?php else: ?>


                <?php foreach ($rates as $rate): ?>

                    <article class="rate-card">


                        <div class="rate-card-top">


                            <div>

                                <h2>

                                    <?= htmlspecialchars(
                                        (string)$rate["duration"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </h2>


                                <div class="rate-duration">

                                    <?= htmlspecialchars(
                                        (string)$rate["label"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>


                            <div class="rate-price">

                                ₱<?= number_format(
                                    (float)$rate["price"],
                                    2
                                ) ?>

                                <small>
                                    PHP
                                </small>

                            </div>


                        </div>


                        <div class="rate-meta">

                            RATE ID:
                            <?= (int)$rate["id"] ?>

                            &nbsp; | &nbsp;

                            CREATED:
                            <?= htmlspecialchars(
                                (string)$rate["created_at"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <div class="rate-card-actions">


                            <!-- EDIT -->

                            <a
                                href="rates.php?edit=<?= (int)$rate["id"] ?>"
                                class="admin-button"
                            >
                                EDIT
                            </a>


                            <!-- DELETE -->

                            <form
                                method="POST"
                                class="delete-form"
                                onsubmit="return confirm('Are you sure you want to delete this rate?');"
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
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$rate["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="admin-button danger"
                                >
                                    DELETE
                                </button>

                            </form>


                        </div>


                    </article>

                <?php endforeach; ?>


            <?php endif; ?>


        </div>

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