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

$currentUserId = currentUserId();
$currentUserRole = (string)($_SESSION["user_role"] ?? "customer");
$isAdmin = ($currentUserRole === "admin");

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

        $name = trim($_POST["name"] ?? "");

        $email = strtolower(
            trim($_POST["email"] ?? "")
        );

        $phone = trim($_POST["phone"] ?? "");

        $msg = trim($_POST["message"] ?? "");


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

                /* ========================================
                   START TRANSACTION
                ======================================== */

                $conn->beginTransaction();


                /* ========================================
                   INSERT CUSTOMER MESSAGE

                   Link message to logged-in customer account.
                   Guests remain NULL.
                ======================================== */

                $stmt = $conn->prepare("
                    INSERT INTO contacts
                    (
                        user_id,
                        name,
                        email,
                        phone,
                        message
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $currentUserId,
                    $name,
                    $email !== ""
                        ? $email
                        : null,
                    $phone !== ""
                        ? $phone
                        : null,
                    $msg
                ]);


                /* ========================================
                   GET ALL ADMIN ACCOUNTS
                ======================================== */

                $adminStmt = $conn->query("
                    SELECT id
                    FROM users
                    WHERE role = 'admin'
                ");

                $admins = $adminStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


                /* ========================================
                   CREATE ADMIN NOTIFICATION
                ======================================== */

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

                    foreach ($admins as $admin) {

                        $adminNotificationStmt->execute([
                            (int)$admin["id"],
                            $notificationTitle,
                            $notificationMessage
                        ]);

                    }
                }


                /* ========================================
                   COMMIT
                ======================================== */

                $conn->commit();

                $message =
                    "Message sent successfully!";


                /* ========================================
                   CLEAR FORM
                ======================================== */

                $name = "";
                $email = "";
                $phone = "";
                $msg = "";


            } catch (Throwable $e) {

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
   CUSTOMER REPLIES

   ONLY CUSTOMER ACCOUNTS CAN SEE THIS.
   ADMIN ACCOUNTS MUST NOT LOAD THIS.
======================================== */

$customerConversations = [];

if ($currentUserId !== null && !$isAdmin) {

    $replyStmt = $conn->prepare("
        SELECT
            c.id AS contact_id,
            c.message AS customer_message,
            c.created_at AS customer_created_at,
            r.id AS reply_id,
            r.message AS admin_reply,
            r.created_at AS reply_created_at
        FROM contacts c
        LEFT JOIN contact_replies r
            ON r.contact_id = c.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC, r.created_at ASC
    ");

    $replyStmt->execute([
        $currentUserId
    ]);

    $customerConversations =
        $replyStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


/* ========================================
   CUSTOMER REPLY NOTIFICATIONS

   ONLY CUSTOMER ACCOUNTS CAN SEE THIS.
======================================== */

$customerNotifications = [];

if ($currentUserId !== null && !$isAdmin) {

    $notificationStmt = $conn->prepare("
        SELECT
            id,
            title,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = ?
          AND type = 'contact_reply'
        ORDER BY is_read ASC, created_at DESC
        LIMIT 20
    ");

    $notificationStmt->execute([
        $currentUserId
    ]);

    $customerNotifications =
        $notificationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
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


        <!-- ========================================
             CUSTOMER INBOX / ADMIN REPLIES

             HIDDEN FOR ADMIN ACCOUNTS
        ======================================== -->

        <?php if ($currentUserId !== null && !$isAdmin): ?>

            <section
                class="contact-replies-section"
                id="contact-replies"
                style="margin-top:45px;"
            >

                <p class="section-kicker">
                    CUSTOMER INBOX
                </p>


                <h2 class="section-heading">
                    ADMIN <span>REPLIES</span>
                </h2>


                <?php if ($customerNotifications): ?>

                    <div class="form-message">

                        <?php foreach ($customerNotifications as $notification): ?>

                            <div style="margin-bottom:8px;">

                                <strong>

                                    <?= htmlspecialchars(
                                        (string)$notification["title"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </strong>

                                —

                                <?= htmlspecialchars(
                                    (string)$notification["message"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <?php if (!$customerConversations): ?>

                    <div class="contact-message-text">

                        No contact messages yet.

                    </div>

                <?php else: ?>

                    <?php
                    $currentContactId = null;
                    ?>


                    <?php foreach ($customerConversations as $conversation): ?>


                        <?php if (
                            $currentContactId !==
                            (int)$conversation["contact_id"]
                        ): ?>

                            <?php

                            $currentContactId =
                                (int)$conversation["contact_id"];

                            ?>


                            <div
                                class="contact-message-card"
                                style="
                                    margin-bottom:20px;
                                    border:1px solid var(--green);
                                    background:#000;
                                    padding:22px 24px;
                                    box-shadow:
                                    0 0 10px rgba(57,255,20,.15),
                                    inset 0 0 10px rgba(57,255,20,.04);"
                            >

                                <div class="contact-label">
                                    YOUR MESSAGE
                                </div>


                                <div class="contact-message-text">

                                    <?= htmlspecialchars(
                                        (string)$conversation["customer_message"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>


                                <?php if (!empty($conversation["customer_created_at"])): ?>

                                    <div
                                        class="contact-message-date"
                                        style="margin-top:8px;"
                                    >

                                        <?= htmlspecialchars(
                                            date(
                                                "F d, Y h:i A",
                                                strtotime(
                                                    (string)$conversation["customer_created_at"]
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                        <?php endif; ?>


                        <?php if (!empty($conversation["reply_id"])): ?>

                                <div
    class="contact-field admin-reply-card"
    style="
        margin-top:18px;
        border:1px solid var(--green);
        background:#000;
        padding:22px 24px;
        box-shadow:
            0 0 12px rgba(57,255,20,.20),
            0 0 25px rgba(57,255,20,.08);
    "
>

                                    <div class="contact-label">
                                        ADMIN REPLY
                                    </div>


                                    <div class="contact-message-text">

                                        <?= htmlspecialchars(
                                            (string)$conversation["admin_reply"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>


                                    <div
                                        class="contact-message-date"
                                        style="margin-top:8px;"
                                    >

                                        <?= htmlspecialchars(
                                            date(
                                                "F d, Y h:i A",
                                                strtotime(
                                                    (string)$conversation["reply_created_at"]
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                </div>

                        <?php endif; ?>


                        <?php

                        $nextContactId = null;

                        $conversationIndex =
                            array_search(
                                $conversation,
                                $customerConversations,
                                true
                            );

                        $nextConversation =
                            (
                                $conversationIndex !== false &&
                                isset(
                                    $customerConversations[
                                        $conversationIndex + 1
                                    ]
                                )
                            )
                                ? $customerConversations[
                                    $conversationIndex + 1
                                ]
                                : null;

                        $nextContactId =
                            $nextConversation
                                ? (int)$nextConversation["contact_id"]
                                : null;

                        ?>


                        <?php if (
                            $nextContactId !==
                            (int)$conversation["contact_id"]
                        ): ?>

                            </div>

                        <?php endif; ?>


                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </div>

</main>


<?php

include "includes/footer.php";

?>