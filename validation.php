<?php
declare(strict_types=1);


/* ========================================
   REQUIRED FIELD
======================================== */

function validateRequired(string $value, string $fieldName): ?string
{
    if (trim($value) === "") {
        return $fieldName . " is required.";
    }

    return null;
}


/* ========================================
   EMAIL
======================================== */

function validateEmailFormat(string $email): ?string
{
    if ($email === "") {
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address.";
    }

    return null;
}


/* ========================================
   PHONE
======================================== */

function validatePhone(string $phone): ?string
{
    if ($phone === "") {
        return "Phone number is required.";
    }

    if (!preg_match('/^[0-9+\-\s()]{7,40}$/', $phone)) {
        return "Please enter a valid phone number.";
    }

    return null;
}


/* ========================================
   PASSWORD
======================================== */

function validatePassword(string $password): ?string
{
    if ($password === "") {
        return "Password is required.";
    }

    if (strlen($password) < 8) {
        return "Password must be at least 8 characters.";
    }

    return null;
}


/* ========================================
   CONFIRM PASSWORD
======================================== */

function validatePasswordConfirmation(
    string $password,
    string $confirmPassword
): ?string {
    if ($password !== $confirmPassword) {
        return "Passwords do not match.";
    }

    return null;
}


/* ========================================
   BOOKING HOURS
======================================== */

function validateHours(int $hours): ?string
{
    if ($hours < 1 || $hours > 12) {
        return "Number of hours must be between 1 and 12.";
    }

    return null;
}


/* ========================================
   BOOKING DATE
======================================== */

function validateBookingDate(string $date): ?string
{
    if ($date === "") {
        return "Booking date is required.";
    }

    $dateObject = DateTime::createFromFormat("Y-m-d", $date);

    if (!$dateObject || $dateObject->format("Y-m-d") !== $date) {
        return "Please enter a valid booking date.";
    }

    $today = new DateTime("today");

    if ($dateObject < $today) {
        return "Booking date cannot be in the past.";
    }

    return null;
}


/* ========================================
   REGISTRATION
======================================== */

function validateRegistrationInput(
    string $name,
    string $phone,
    string $email,
    string $password,
    string $confirmPassword
): array {

    $errors = [];

    if ($error = validateRequired($name, "Name")) {
        $errors[] = $error;
    }

    if ($error = validatePhone($phone)) {
        $errors[] = $error;
    }

    if ($error = validateRequired($email, "Email")) {
        $errors[] = $error;
    } elseif ($error = validateEmailFormat($email)) {
        $errors[] = $error;
    }

    if ($error = validatePassword($password)) {
        $errors[] = $error;
    }

    if ($error = validatePasswordConfirmation(
        $password,
        $confirmPassword
    )) {
        $errors[] = $error;
    }

    return $errors;
}


/* ========================================
   LOGIN
======================================== */

function validateLoginInput(
    string $email,
    string $password
): array {

    $errors = [];

    if ($error = validateRequired($email, "Email")) {
        $errors[] = $error;
    }

    if ($error = validateRequired($password, "Password")) {
        $errors[] = $error;
    }

    return $errors;
}


/* ========================================
   BOOKING
======================================== */

function validateBookingInput(
    string $name,
    string $phone,
    string $email,
    string $station,
    string $bookingDate,
    string $bookingTime,
    int $hours
): array {

    $errors = [];

    if ($error = validateRequired($name, "Name")) {
        $errors[] = $error;
    }

    if ($error = validatePhone($phone)) {
        $errors[] = $error;
    }

    if ($email !== "") {
        if ($error = validateEmailFormat($email)) {
            $errors[] = $error;
        }
    }

    if ($error = validateRequired($station, "Gaming Setup")) {
        $errors[] = $error;
    }

    if ($error = validateBookingDate($bookingDate)) {
        $errors[] = $error;
    }

    if ($error = validateRequired($bookingTime, "Booking time")) {
        $errors[] = $error;
    }

    if ($error = validateHours($hours)) {
        $errors[] = $error;
    }

    return $errors;
}


/* ========================================
   CONTACT
======================================== */

function validateContactInput(
    string $name,
    string $email,
    string $phone,
    string $message
): array {

    $errors = [];

    if ($error = validateRequired($name, "Name")) {
        $errors[] = $error;
    }

    if ($email !== "") {
        if ($error = validateEmailFormat($email)) {
            $errors[] = $error;
        }
    }

    if ($phone !== "") {
        if (!preg_match('/^[0-9+\-\s()]{7,40}$/', $phone)) {
            $errors[] = "Please enter a valid phone number.";
        }
    }

    if ($error = validateRequired($message, "Message")) {
        $errors[] = $error;
    }

    return $errors;
}
?>