<?php
declare(strict_types=1);

require_once "auth.php";

/* ========================================
   LOGOUT USER
======================================== */

$_SESSION = [];

/*
 * Delete the session cookie if one exists.
 */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
 * Destroy the current session.
 */
session_destroy();

/*
 * Return to the homepage.
 */
header("Location: index.php");
exit;
?>