<?php
declare(strict_types=1);

require_once "database.php";
require_once "validation.php";

$errors = [];
$message = "";

$name = "";
$email = "";
$phone = "";
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $phone = trim($_POST["phone"] ?? "");
    $msg = trim($_POST["message"] ?? "");

    $errors = validateContactInput(
        $name,
        $email,
        $phone,
        $msg
    );

    if (!$errors) {

        $stmt = $conn->prepare(
            "INSERT INTO contacts
            (name, email, phone, message)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([
            $name,
            $email !== "" ? $email : null,
            $phone !== "" ? $phone : null,
            $msg
        ]);

        $message = "Message sent successfully!";

        $name = "";
        $email = "";
        $phone = "";
        $msg = "";
    }
}

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
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors): ?>

            <div class="form-message error">

                <?php foreach ($errors as $error): ?>

                    <div>
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="contact-layout">

            <div class="contact-info">

                <h2>
                    BAIS ROUILO<br>
                    <span>GAMING CAFE</span>
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

                <label>
                    Name *

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>"
                        required
                    >
                </label>

                <label>
                    Email

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                    >
                </label>

                <label>
                    Phone

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars($phone) ?>"
                    >
                </label>

                <label>
                    Message *

                    <textarea
                        name="message"
                        rows="6"
                        required
                    ><?= htmlspecialchars($msg) ?></textarea>

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

<?php include "includes/footer.php"; ?>