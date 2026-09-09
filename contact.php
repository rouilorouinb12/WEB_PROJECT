<?php

declare(strict_types=1);

require_once "database.php";
require_once "auth.php";
require_once "validation.php";

$errors = [];
$message = "";

$name = "";
$email = "";
$phone = "";
$msg = "";


/* ========================================
   HANDLE CONTACT FORM
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ========================================
       CSRF CHECK
    ======================================== */

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !verifyCsrfToken($csrfToken)
    ) {

        $errors[] =
            "Invalid security token. Please refresh the page and try again.";

    } else {

        /* ========================================
           GET INPUT
        ======================================== */

        $name =
            trim($_POST["name"] ?? "");

        $email =
            strtolower(
                trim($_POST["email"] ?? "")
            );

        $phone =
            trim($_POST["phone"] ?? "");

        $msg =
            trim($_POST["message"] ?? "");


        /* ========================================
           VALIDATE INPUT
        ======================================== */

        $errors = validateContactInput(
            $name,
            $email,
            $phone,
            $msg
        );


        /* ========================================
           SAVE MESSAGE
        ======================================== */

        if (!$errors) {

            try {

                /*
                ========================================
                START TRANSACTION
                ========================================
                */

                $conn->beginTransaction();


                /*
                ========================================
                INSERT CUSTOMER MESSAGE
                ========================================
                */

                $stmt = $conn->prepare("
                    INSERT INTO contacts
                    (
                        name,
                        email,
                        phone,
                        message
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $email !== ""
                        ? $email
                        : null,

                    $phone !== ""
                        ? $phone
                        : null,

                    $msg
                ]);


                /*
                ========================================
                GET ALL ADMIN ACCOUNTS
                ========================================
                */

                $adminStmt = $conn->query("
                    SELECT id
                    FROM users
                    WHERE role = 'admin'
                ");

                $admins =
                    $adminStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                /*
                ========================================
                CREATE ADMIN NOTIFICATION
                ========================================
                */

                if ($admins) {

                    $adminNotificationStmt =
                        $conn->prepare("
                            INSERT INTO notifications
                            (
                                user_id,
                                type,
                                title,
                                message,
                                is_read
                            )
                            VALUES (
                                ?,
                                'contact',
                                ?,
                                ?,
                                0
                            )
                        ");


                    $notificationTitle =
                        "NEW CUSTOMER MESSAGE";


                    $notificationMessage =
                        "A customer has sent a new message through the Contact page.";


                    foreach (
                        $admins
                        as $admin
                    ) {

                        $adminNotificationStmt->execute([
                            (int)$admin["id"],
                            $notificationTitle,
                            $notificationMessage
                        ]);

                    }

                }


                /*
                ========================================
                COMMIT
                ========================================
                */

                $conn->commit();


                /*
                ========================================
                SUCCESS MESSAGE
                ========================================
                */

                $message =
                    "Message sent successfully!";


                /*
                ========================================
                CLEAR FORM
                ========================================
                */

                $name = "";

                $email = "";

                $phone = "";

                $msg = "";


            } catch (Throwable $e) {

                /*
                ========================================
                ROLLBACK WHEN SOMETHING FAILS
                ========================================
                */

                if ($conn->inTransaction()) {

                    $conn->rollBack();

                }


                $errors[] =
                    "Message could not be sent. Please try again.";

            }

        }

    }

}


/* ========================================
   CSRF TOKEN
======================================== */

$csrfToken = csrfToken();


include "includes/header.php";

?>


<main class="inner-page">

    <div class="container contact-page">


        <p class="section-kicker">

            CONTACT US

        </p>


        <h1 class="page-title">

            LET'S <span>CONNECT.</span>

        </h1>


        <?php if ($message): ?>

            <div class="form-message">

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($errors): ?>

            <div class="form-message error">

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


        <div class="contact-layout">


            <div class="contact-info">

                <h2>

                    BAIS ROUILO<br>

                    <span>

                        GAMING CAFE

                    </span>

                </h2>


                <p>

                    ☎ 09926749467

                </p>


                <p>

                    ✉ info@brgaming.com

                </p>


                <p>

                    ⌖ Southbags, Bagacay, Dumaguete<br>

                    City, Negros Oriental

                </p>

            </div>


            <form
                method="POST"
                class="booking-form"
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


                <label>

                    Name *

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </label>


                <label>

                    Email

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                </label>


                <label>

                    Phone

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                </label>


                <label>

                    Message *

                    <textarea
                        name="message"
                        rows="6"
                        required
                    ><?= htmlspecialchars(
                        $msg,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?></textarea>

                </label>


                <button
                    type="submit"
                    class="green-button"
                >

                    SEND MESSAGE

                </button>


            </form>

        </div>

    </div>

</main>
