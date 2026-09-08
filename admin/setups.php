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
    die("Access denied.");
}

$errors = [];
$success = "";


/* ========================================
   FORM VALUES
======================================== */

$editId = 0;

$name = "";
$pricePerHour = "";
$specs = "";
$image = "";
$sortOrder = "0";


/* ========================================
   UPLOAD DIRECTORY
======================================== */

$uploadDirectory =
    __DIR__
    . DIRECTORY_SEPARATOR
    . ".."
    . DIRECTORY_SEPARATOR
    . "assets"
    . DIRECTORY_SEPARATOR
    . "images";


/* ========================================
   CREATE UPLOAD DIRECTORY IF NEEDED
======================================== */

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0755, true);
}


/* ========================================
   HANDLE DELETE
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete"
) {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!verifyCsrfToken(
        is_string($csrfToken)
            ? $csrfToken
            : null
    )) {

        $errors[] = "Invalid security token.";

    } else {

        $deleteId = filter_var(
            $_POST["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$deleteId) {

            $errors[] = "Invalid setup ID.";

        } else {

            /* ========================================
               GET IMAGE BEFORE DELETE
            ======================================== */

            $imageStmt = $conn->prepare("
                SELECT image
                FROM gaming_setups
                WHERE id = ?
                LIMIT 1
            ");

            $imageStmt->execute([
                $deleteId
            ]);

            $setupToDelete = $imageStmt->fetch(
                PDO::FETCH_ASSOC
            );


            /* ========================================
               DELETE SETUP
            ======================================== */

            $deleteStmt = $conn->prepare("
                DELETE FROM gaming_setups
                WHERE id = ?
            ");

            try {

                $deleteStmt->execute([
                    $deleteId
                ]);

                if ($deleteStmt->rowCount() === 1) {

                    /* ========================================
                       DELETE IMAGE FILE IF LOCAL
                    ======================================== */

                    if (
                        $setupToDelete &&
                        !empty($setupToDelete["image"])
                    ) {

                        $imageName =
                            basename(
                                (string)
                                $setupToDelete["image"]
                            );

                        $imagePath =
                            $uploadDirectory
                            . DIRECTORY_SEPARATOR
                            . $imageName;

                        if (
                            is_file($imagePath)
                        ) {

                            @unlink($imagePath);

                        }

                    }

                    $success =
                        "Gaming setup deleted successfully.";

                } else {

                    $errors[] =
                        "Gaming setup not found.";

                }

            } catch (PDOException $e) {

                $errors[] =
                    "Unable to delete the gaming setup. It may still be used by existing bookings.";

            }

        }

    }
}


/* ========================================
   LOAD SETUP FOR EDIT
======================================== */

$requestedEditId = filter_var(
    $_GET["edit"] ?? null,
    FILTER_VALIDATE_INT
);

if ($requestedEditId) {

    $editStmt = $conn->prepare("
        SELECT
            id,
            name,
            price_per_hour,
            specs,
            image,
            sort_order
        FROM gaming_setups
        WHERE id = ?
        LIMIT 1
    ");

    $editStmt->execute([
        $requestedEditId
    ]);

    $editSetup = $editStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($editSetup) {

        $editId =
            (int)$editSetup["id"];

        $name =
            (string)$editSetup["name"];

        $pricePerHour =
            (string)$editSetup["price_per_hour"];

        $specs =
            (string)($editSetup["specs"] ?? "");

        $image =
            (string)($editSetup["image"] ?? "");

        $sortOrder =
            (string)(
                $editSetup["sort_order"] ?? 0
            );

    } else {

        $errors[] =
            "Gaming setup not found.";

    }
}


/* ========================================
   HANDLE ADD / UPDATE
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") !== "delete"
) {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!verifyCsrfToken(
        is_string($csrfToken)
            ? $csrfToken
            : null
    )) {

        $errors[] =
            "Invalid security token.";

    } else {

        $action =
            $_POST["action"] ?? "add";

        $postedId =
            filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

        $name =
            trim(
                (string)(
                    $_POST["name"] ?? ""
                )
            );

        $pricePerHour =
            trim(
                (string)(
                    $_POST["price_per_hour"] ?? ""
                )
            );

        $specs =
            trim(
                (string)(
                    $_POST["specs"] ?? ""
                )
            );

        $sortOrder =
            trim(
                (string)(
                    $_POST["sort_order"] ?? "0"
                )
            );


        /* ========================================
           VALIDATION
        ======================================== */

        if ($name === "") {

            $errors[] =
                "Please enter the setup name.";

        } elseif (mb_strlen($name) > 120) {

            $errors[] =
                "Setup name is too long.";

        }


        $validPrice =
            filter_var(
                $pricePerHour,
                FILTER_VALIDATE_FLOAT
            );

        if (
            $pricePerHour === "" ||
            $validPrice === false ||
            $validPrice < 0
        ) {

            $errors[] =
                "Please enter a valid price.";

        }


        if (mb_strlen($specs) > 2000) {

            $errors[] =
                "Specifications are too long.";

        }


        $validSortOrder =
            filter_var(
                $sortOrder,
                FILTER_VALIDATE_INT
            );

        if (
            $sortOrder === "" ||
            $validSortOrder === false ||
            $validSortOrder < 0
        ) {

            $errors[] =
                "Sort order must be a non-negative number.";

        }


        /* ========================================
           HANDLE IMAGE
        ======================================== */

        $newImageName = null;

        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES["image"]["error"] !== UPLOAD_ERR_OK
            ) {

                $errors[] =
                    "There was a problem uploading the image.";

            } elseif (
                $_FILES["image"]["size"]
                > 5 * 1024 * 1024
            ) {

                $errors[] =
                    "Image must not exceed 5MB.";

            } else {

                $finfo =
                    finfo_open(
                        FILEINFO_MIME_TYPE
                    );

                $mimeType =
                    finfo_file(
                        $finfo,
                        $_FILES["image"]["tmp_name"]
                    );

                finfo_close($finfo);


                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];


                if (
                    !isset(
                        $allowedTypes[$mimeType]
                    )
                ) {

                    $errors[] =
                        "Only JPG, PNG, and WEBP images are allowed.";

                } else {

                    $extension =
                        $allowedTypes[$mimeType];

                    $newImageName =
                        "setup_"
                        . bin2hex(
                            random_bytes(12)
                        )
                        . "."
                        . $extension;

                    $destination =
                        $uploadDirectory
                        . DIRECTORY_SEPARATOR
                        . $newImageName;


                    if (
                        !move_uploaded_file(
                            $_FILES["image"]["tmp_name"],
                            $destination
                        )
                    ) {

                        $errors[] =
                            "Unable to save the uploaded image.";

                        $newImageName = null;

                    }

                }

            }

        }


        /* ========================================
           ADD SETUP
        ======================================== */

        if (
            !$errors &&
            $action === "add"
        ) {

            $insertStmt = $conn->prepare("
                INSERT INTO gaming_setups
                (
                    name,
                    price_per_hour,
                    specs,
                    image,
                    sort_order
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $insertStmt->execute([
                $name,
                number_format(
                    (float)$validPrice,
                    2,
                    ".",
                    ""
                ),
                $specs !== ""
                    ? $specs
                    : null,
                $newImageName,
                (int)$validSortOrder
            ]);

            $success =
                "Gaming setup added successfully.";

            $name = "";
            $pricePerHour = "";
            $specs = "";
            $image = "";
            $sortOrder = "0";

            $editId = 0;

        }


        /* ========================================
           UPDATE SETUP
        ======================================== */

        elseif (
            !$errors &&
            $action === "update"
        ) {

            if (!$postedId) {

                $errors[] =
                    "Invalid setup ID.";

            } else {

                /* ========================================
                   GET CURRENT SETUP
                ======================================== */

                $currentStmt = $conn->prepare("
                    SELECT image
                    FROM gaming_setups
                    WHERE id = ?
                    LIMIT 1
                ");

                $currentStmt->execute([
                    $postedId
                ]);

                $currentSetup =
                    $currentStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$currentSetup) {

                    $errors[] =
                        "Gaming setup not found.";

                } else {

                    $imageToSave =
                        $currentSetup["image"];


                    if ($newImageName !== null) {

                        $imageToSave =
                            $newImageName;

                    }


                    $updateStmt = $conn->prepare("
                        UPDATE gaming_setups
                        SET
                            name = ?,
                            price_per_hour = ?,
                            specs = ?,
                            image = ?,
                            sort_order = ?
                        WHERE id = ?
                    ");

                    $updateStmt->execute([

                        $name,

                        number_format(
                            (float)$validPrice,
                            2,
                            ".",
                            ""
                        ),

                        $specs !== ""
                            ? $specs
                            : null,

                        $imageToSave,

                        (int)$validSortOrder,

                        $postedId

                    ]);


                    /* ========================================
                       DELETE OLD IMAGE IF REPLACED
                    ======================================== */

                    if (
                        $newImageName !== null &&
                        !empty($currentSetup["image"])
                    ) {

                        $oldImage =
                            basename(
                                (string)
                                $currentSetup["image"]
                            );

                        $oldImagePath =
                            $uploadDirectory
                            . DIRECTORY_SEPARATOR
                            . $oldImage;


                        if (
                            is_file($oldImagePath) &&
                            $oldImage !== $newImageName
                        ) {

                            @unlink(
                                $oldImagePath
                            );

                        }

                    }


                    $success =
                        "Gaming setup updated successfully.";

                    $editId = 0;

                    $name = "";
                    $pricePerHour = "";
                    $specs = "";
                    $image = "";
                    $sortOrder = "0";

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
                "Invalid setup action.";

        }

    }
}


/* ========================================
   GET ALL SETUPS
======================================== */

$listStmt = $conn->query("
    SELECT
        id,
        name,
        price_per_hour,
        specs,
        image,
        sort_order,
        created_at
    FROM gaming_setups
    ORDER BY
        sort_order ASC,
        id ASC
");

$setups =
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

    <meta
        name="description"
        content="Manage gaming setups for Bais Rouilo Gaming Cafe."
    >

    <title>
        MANAGE SETUPS | Bais Rouilo Gaming Cafe
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
        href="../assets/css/style.css"
    >


    <style>

        /* ========================================
           PAGE
        ======================================== */

        .admin-content {

            padding:
                50px 0 70px;

        }


        .admin-header {

            display: flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap: 20px;

            margin-bottom: 30px;

            flex-wrap: wrap;

        }


        .admin-title {

            margin: 0;

            font:
                800 36px "Orbitron",
                sans-serif;

        }


        .admin-title span {

            color: #39FF14;

        }


        .admin-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        /* ========================================
           BUTTONS
        ======================================== */

        .admin-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 40px;

            padding:
                0 16px;

            border:
                1px solid #39FF14;

            border-radius: 4px;

            background: #39FF14;

            color: #000;

            text-decoration: none;

            font:
                800 10px "Orbitron",
                sans-serif;

            cursor: pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                background .2s ease;

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

            box-shadow:
                0 0 15px
                rgba(255,77,77,.3);

        }


        /* ========================================
           FORM CARD
        ======================================== */

        .setup-form-card {

            border:
                1px solid
                rgba(57,255,20,.35);

            background:
                rgba(0,0,0,.65);

            padding: 28px;

            margin-bottom: 35px;

        }


        .setup-form-title {

            margin:
                0 0 22px;

            color:
                #39FF14;

            font:
                800 18px "Orbitron",
                sans-serif;

        }


        .setup-form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px 22px;

        }


        .setup-field {

            display: flex;

            flex-direction:
                column;

            gap: 7px;

        }


        .setup-field.full {

            grid-column:
                1 / -1;

        }


        .setup-field label {

            color:
                #fff;

            font:
                700 11px "Orbitron",
                sans-serif;

        }


        .setup-field input,
        .setup-field textarea {

            width: 100%;

            padding:
                12px 14px;

            border:
                1px solid #333;

            border-radius: 4px;

            background:
                #080808;

            color:
                #fff;

            outline:
                none;

            font:
                600 14px "Rajdhani",
                sans-serif;

            box-sizing:
                border-box;

        }


        .setup-field textarea {

            min-height:
                130px;

            resize:
                vertical;

        }


        .setup-field input:focus,
        .setup-field textarea:focus {

            border-color:
                #39FF14;

            box-shadow:
                0 0 10px
                rgba(57,255,20,.12);

        }


        .setup-field input[type="file"] {

            padding:
                10px;

        }


        .current-image {

            display: flex;

            align-items:
                center;

            gap: 12px;

            margin-top: 5px;

            color: #999;

            font-size: 12px;

        }


        .current-image img {

            width:
                80px;

            height:
                60px;

            object-fit:
                cover;

            border:
                1px solid
                rgba(57,255,20,.3);

            border-radius:
                4px;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

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
           SETUP LIST
        ======================================== */

        .setup-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;

        }


        .setup-item {

            border:
                1px solid
                rgba(57,255,20,.30);

            background:
                #050505;

            padding:
                20px;

        }


        .setup-item-top {

            display: flex;

            gap: 18px;

            align-items:
                flex-start;

            margin-bottom:
                18px;

        }


        .setup-preview {

            width:
                140px;

            height:
                100px;

            flex-shrink:
                0;

            border:
                1px solid
                #222;

            border-radius:
                4px;

            overflow:
                hidden;

            background:
                #080808;

        }


        .setup-preview img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

        }


        .setup-preview.no-image {

            display: flex;

            align-items:
                center;

            justify-content:
                center;

            color:
                #555;

            font:
                700 10px "Orbitron",
                sans-serif;

        }


        .setup-info {

            min-width:
                0;

        }


        .setup-info h2 {

            margin:
                0 0 8px;

            color:
                #39FF14;

            font:
                800 17px "Orbitron",
                sans-serif;

            overflow-wrap:
                anywhere;

        }


        .setup-price {

            color:
                #fff;

            font:
                700 15px "Rajdhani",
                sans-serif;

        }


        .setup-specs {

            white-space:
                pre-line;

            color:
                #bbb;

            line-height:
                1.5;

            font-size:
                14px;

            margin-bottom:
                18px;

        }


        .setup-meta {

            color:
                #666;

            font-size:
                11px;

            margin-bottom:
                14px;

        }


        .setup-actions {

            display: flex;

            gap:
                10px;

            flex-wrap:
                wrap;

            padding-top:
                15px;

            border-top:
                1px solid #1c1c1c;

        }


        .setup-action-form {

            margin: 0;

        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 900px) {

            .setup-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 700px) {

            .setup-form-grid {

                grid-template-columns:
                    1fr;

            }

            .setup-field.full {

                grid-column:
                    auto;

            }

            .setup-item-top {

                flex-direction:
                    column;

            }

            .setup-preview {

                width:
                    100%;

                height:
                    180px;

            }

            .admin-title {

                font-size:
                    28px;

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
            aria-label="Open menu"
            aria-expanded="false"
            type="button"
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

            <a href="logout.php">
                LOGOUT
            </a>

        </nav>

    </div>

</header>


<!-- ========================================
     MAIN
======================================== -->

<main class="inner-page">

    <div class="container admin-content">


        <!-- HEADER -->

        <div class="admin-header">

            <div>

                <p class="section-kicker">
                    ADMIN PANEL
                </p>

                <h1 class="admin-title">

                    GAMING
                    <span>
                        SETUPS
                    </span>

                </h1>

            </div>


            <div class="admin-actions">

                <a
                    href="dashboard.php"
                    class="admin-button outline"
                >
                    BACK TO DASHBOARD
                </a>

                <a
                    href="../index.php#pcs"
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

        <div class="setup-form-card">


            <h2 class="setup-form-title">

                <?= $editId > 0
                    ? "EDIT GAMING SETUP"
                    : "ADD GAMING SETUP"
                ?>

            </h2>


            <form
                method="POST"
                enctype="multipart/form-data"
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


                <div class="setup-form-grid">


                    <!-- NAME -->

                    <div class="setup-field">

                        <label for="name">
                            SETUP NAME
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="120"
                            value="<?= htmlspecialchars(
                                $name,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            placeholder="e.g. RTX GAMING PC"
                            required
                        >

                    </div>


                    <!-- PRICE -->

                    <div class="setup-field">

                        <label for="price_per_hour">
                            PRICE PER HOUR
                        </label>

                        <input
                            type="number"
                            id="price_per_hour"
                            name="price_per_hour"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars(
                                $pricePerHour,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            placeholder="45.00"
                            required
                        >

                    </div>


                    <!-- SPECS -->

                    <div class="setup-field full">

                        <label for="specs">
                            SPECIFICATIONS
                        </label>

                        <textarea
                            id="specs"
                            name="specs"
                            maxlength="2000"
                            placeholder="RTX 4060&#10;Ryzen 5 5600&#10;16GB RAM&#10;24&quot; 165Hz Monitor"
                        ><?= htmlspecialchars(
                            $specs,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>


                    <!-- IMAGE -->

                    <div class="setup-field">

                        <label for="image">
                            SETUP IMAGE
                        </label>

                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                        >


                        <?php if (
                            $editId > 0 &&
                            $image !== ""
                        ): ?>

                            <div class="current-image">

                                <img
                                    src="../assets/images/<?= htmlspecialchars(
                                        basename($image),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    alt="Current setup image"
                                >

                                <span>
                                    Current image
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- SORT ORDER -->

                    <div class="setup-field">

                        <label for="sort_order">
                            SORT ORDER
                        </label>

                        <input
                            type="number"
                            id="sort_order"
                            name="sort_order"
                            min="0"
                            value="<?= htmlspecialchars(
                                $sortOrder,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                    </div>


                </div>


                <!-- FORM ACTIONS -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="admin-button"
                    >

                        <?= $editId > 0
                            ? "UPDATE SETUP"
                            : "ADD SETUP"
                        ?>

                    </button>


                    <?php if ($editId > 0): ?>

                        <a
                            href="setups.php"
                            class="admin-button outline"
                        >
                            CANCEL
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


        <!-- ========================================
             SETUP LIST
        ======================================== -->

        <div class="setup-grid">


            <?php if (!$setups): ?>

                <div class="setup-form-card">

                    <h2 class="setup-form-title">
                        NO GAMING SETUPS
                    </h2>

                    <p style="color:#999;">
                        There are currently no gaming setups.
                    </p>

                </div>

            <?php else: ?>


                <?php foreach ($setups as $setup): ?>

                    <article class="setup-item">


                        <div class="setup-item-top">


                            <!-- IMAGE -->

                            <?php
                            $setupImage =
                                trim(
                                    (string)(
                                        $setup["image"] ?? ""
                                    )
                                );

                            $imagePath =
                                "../assets/images/"
                                . basename($setupImage);

                            if (
                                $setupImage !== "" &&
                                is_file(
                                    __DIR__
                                    . DIRECTORY_SEPARATOR
                                    . ".."
                                    . DIRECTORY_SEPARATOR
                                    . "assets"
                                    . DIRECTORY_SEPARATOR
                                    . "images"
                                    . DIRECTORY_SEPARATOR
                                    . basename(
                                        $setupImage
                                    )
                                )
                            ):
                            ?>

                                <div class="setup-preview">

                                    <img
                                        src="<?= htmlspecialchars(
                                            $imagePath,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $setup["name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                </div>

                            <?php else: ?>

                                <div class="setup-preview no-image">
                                    NO IMAGE
                                </div>

                            <?php endif; ?>


                            <!-- INFO -->

                            <div class="setup-info">

                                <h2>

                                    <?= htmlspecialchars(
                                        $setup["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </h2>


                                <div class="setup-price">

                                    ₱<?= number_format(
                                        (float)$setup["price_per_hour"],
                                        2
                                    ) ?>

                                    / HOUR

                                </div>

                            </div>


                        </div>


                        <!-- SPECS -->

                        <div class="setup-specs">

                            <?= htmlspecialchars(
                                (string)(
                                    $setup["specs"] ?? ""
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <!-- META -->

                        <div class="setup-meta">

                            SORT ORDER:
                            <?= (int)(
                                $setup["sort_order"]
                                ?? 0
                            ) ?>

                            &nbsp; | &nbsp;

                            CREATED:
                            <?= htmlspecialchars(
                                (string)(
                                    $setup["created_at"]
                                    ?? ""
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <!-- ACTIONS -->

                        <div class="setup-actions">


                            <!-- EDIT -->

                            <a
                                href="setups.php?edit=<?= (int)$setup["id"] ?>"
                                class="admin-button"
                            >
                                EDIT
                            </a>


                            <!-- DELETE -->

                            <form
                                method="POST"
                                class="setup-action-form"
                                onsubmit="return confirm('Are you sure you want to delete this gaming setup?');"
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
                                    value="<?= (int)$setup["id"] ?>"
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