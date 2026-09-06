<?php
declare(strict_types=1);


/* ========================================
   CREATE BOOKING
======================================== */

function createBooking(
    PDO $conn,
    int $userId,
    string $name,
    string $phone,
    string $email,
    string $station,
    string $bookingDate,
    string $bookingTime,
    int $hours,
    string $notes
): int {

    /*
     * The bookings table does not have user_id.
     * We use the selected gaming setup's ID instead.
     */

    $setupStmt = $conn->prepare("
        SELECT id
        FROM gaming_setups
        WHERE name = ?
        LIMIT 1
    ");

    $setupStmt->execute([
        $station
    ]);

    $setup = $setupStmt->fetch();

    if (!$setup) {
        throw new RuntimeException(
            "Selected gaming setup was not found."
        );
    }

    $setupId = (int)$setup["id"];


    /* ========================================
       INSERT BOOKING
    ======================================== */

    $stmt = $conn->prepare("
        INSERT INTO bookings (
            customer_name,
            email,
            phone,
            setup_id,
            booking_date,
            start_time,
            hours,
            message,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $name,
        $email !== "" ? $email : null,
        $phone !== "" ? $phone : null,
        $setupId,
        $bookingDate,
        $bookingTime,
        $hours,
        $notes !== "" ? $notes : null
    ]);

    return (int)$conn->lastInsertId();
}


/* ========================================
   GET BOOKING
======================================== */

function getBooking(
    PDO $conn,
    int $id,
    int $userId
): ?array {

    /*
     * The bookings table does not have user_id.
     *
     * To keep the booking protected, we verify
     * ownership using the logged-in user's email.
     */

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.customer_name,
            b.email,
            b.phone,
            b.setup_id,
            b.booking_date,
            b.start_time,
            b.hours,
            b.message,
            b.status,
            b.created_at,
            gs.name AS setup_name,
            gs.price_per_hour
        FROM bookings b
        LEFT JOIN gaming_setups gs
            ON gs.id = b.setup_id
        INNER JOIN users u
            ON u.email = b.email
        WHERE b.id = ?
        AND u.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $userId
    ]);

    $booking = $stmt->fetch();

    return $booking ?: null;
}
?>