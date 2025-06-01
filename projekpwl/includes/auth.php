<?php
// includes/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function logout() {
    session_destroy();
    header('Location: index.php');
    exit();
}
?>