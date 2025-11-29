<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (empty($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}


if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {

    http_response_code(403);
    echo "Access denied. Customers only.";
    exit();
}
