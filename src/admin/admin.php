<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access denied. Admins only.";
    exit();
}

?>
