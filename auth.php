<?php

declare(strict_types=1);


/* ========================================
   SECURE SESSION SETTINGS
======================================== */

if (session_status() === PHP_SESSION_NONE) {

    /*
     * Detect HTTPS.
     *
     * On localhost using normal http,
     * secure cookies must remain false.
     *
     * On HTTPS hosting, they become true.
     */
    $isHttps =
        (!empty($_SERVER["HTTPS"]) &&
         $_SERVER["HTTPS"] !== "off")
        ||
        (
            isset($_SERVER["SERVER_PORT"]) &&
            (int) $_SERVER["SERVER_PORT"] === 443
        );

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}


/* ========================================
   CSRF TOKEN
======================================== */

function csrfToken(): string
{
    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        $_SESSION["csrf_token"] === ""
    ) {

        $_SESSION["csrf_token"] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}


/* ========================================
   VERIFY CSRF TOKEN
======================================== */

function verifyCsrfToken(?string $token): bool
{
    if (
        $token === null ||
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION["csrf_token"],
        $token
    );
}


/* ========================================
   CHECK IF USER IS LOGGED IN
======================================== */

function isLoggedIn(): bool
{
    return isset($_SESSION["user_id"])
        && is_numeric($_SESSION["user_id"])
        && (int) $_SESSION["user_id"] > 0;
}


/* ========================================
   REQUIRE LOGIN
======================================== */

function requireLogin(): void
{
    if (!isLoggedIn()) {

        header("Location: login.php");
        exit;
    }
}


/* ========================================
   GET CURRENT USER ID
======================================== */

function currentUserId(): ?int
{
    if (!isset($_SESSION["user_id"])) {
        return null;
    }

    $id = filter_var(
        $_SESSION["user_id"],
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id <= 0) {
        return null;
    }

    return $id;
}

?>