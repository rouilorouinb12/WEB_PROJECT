<?php

declare(strict_types=1);

/* ========================================
   DATABASE SETTINGS
======================================== */

$host = "localhost";
$dbname = "bais_rouilo_gaming_cafe";
$username = "root";
$password = "";


/* ========================================
   DATABASE CONNECTION
======================================== */

try {

    $conn = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            /*
             * Use native prepared statements.
             * This provides stronger protection
             * against SQL injection.
             */
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    /*
     * Do not expose database details
     * to the visitor.
     */

    http_response_code(500);

    die(
        "Database connection failed. " .
        "Please make sure MySQL is running."
    );
}

?>