<?php
declare(strict_types=1);

require_once "database.php";
require_once "auth.php";
require_once "booking_function.php";

requireLogin();

$userId = currentUserId();

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

$booking = null;

if ($id) {
    $booking = getBooking(
        $conn,
        $id,
        $userId
    );
}

include "includes/header.php";
?>

<main class="inner-page">

    <div class="container form-page">

        <?php if (!$booking): ?>

            <p class="section-kicker">
                BOOKING
            </p>

            <h1 class="page-title">
                BOOKING <span>NOT FOUND</span>
            </h1>

            <div class="form-message error">
                The booking could not be found.
            </div>

            <div style="text-align:center; margin-top:25px;">

                <a
                    href="book.php"
                    class="green-button"
                    style="padding:15px 25px;"
                >
                    BACK TO BOOKING
                </a>

            </div>


        <?php else: ?>

            <p class="section-kicker">
                BOOKING CONFIRMED
            </p>

            <h1 class="page-title">
                THANK YOU, <span><?= htmlspecialchars($booking["customer_name"]) ?></span>
            </h1>


            <div class="form-message">

                Your booking request has been submitted successfully.

            </div>


            <div class="booking-form">

                <h2
                    style="
                        margin-top:0;
                        color:#39FF14;
                        font-size:20px;
                    "
                >
                    BOOKING #<?= htmlspecialchars((string)$booking["id"]) ?>
                </h2>


                <p>
                    <strong>Customer:</strong>
                    <?= htmlspecialchars($booking["customer_name"]) ?>
                </p>


                <p>
                    <strong>Phone:</strong>
                    <?= htmlspecialchars($booking["phone"] ?? "") ?>
                </p>


                <?php if (!empty($booking["email"])): ?>

                    <p>
                        <strong>Email:</strong>
                        <?= htmlspecialchars($booking["email"]) ?>
                    </p>

                <?php endif; ?>


                <p>
                    <strong>Gaming Setup:</strong>
                    <?= htmlspecialchars($booking["setup_name"] ?? "Unknown Setup") ?>
                </p>


                <p>
                    <strong>Date:</strong>
                    <?= htmlspecialchars($booking["booking_date"]) ?>
                </p>


                <p>
                    <strong>Time:</strong>
                    <?= htmlspecialchars(
                        date(
                            "h:i A",
                            strtotime($booking["start_time"])
                        )
                    ) ?>
                </p>


                <p>
                    <strong>Hours:</strong>
                    <?= htmlspecialchars((string)$booking["hours"]) ?>
                    hour<?= $booking["hours"] > 1 ? "s" : "" ?>
                </p>


                <?php if (!empty($booking["message"])): ?>

                    <p>
                        <strong>Notes:</strong>
                        <?= htmlspecialchars($booking["message"]) ?>
                    </p>

                <?php endif; ?>


                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars(
                        strtoupper($booking["status"])
                    ) ?>
                </p>

            </div>


            <div
                style="
                    display:flex;
                    justify-content:center;
                    gap:15px;
                    margin-top:30px;
                    flex-wrap:wrap;
                "
            >

                <a
                    href="index.php"
                    class="green-button"
                    style="padding:15px 25px;"
                >
                    BACK TO HOME
                </a>


                <a
                    href="book.php"
                    class="outline-button"
                >
                    NEW BOOKING
                </a>

            </div>


            <div style="text-align:center; margin-top:25px;">

                <a
                    href="logout.php"
                    style="
                        color:#39FF14;
                        font-size:11px;
                        font-family:'Orbitron', sans-serif;
                        font-weight:700;
                    "
                >
                    LOG OUT
                </a>

            </div>

        <?php endif; ?>

    </div>

</main>

<?php include "includes/footer.php"; ?>