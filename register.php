<?php
require 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = $_POST['role'] ?? 'resident'; // Default resident

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    
    try {
        $stmt->execute([$username, $email, $password, $role]);
        $_SESSION['success'] = "Admin registered successfully! You can now login.";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Registration failed. Email or Username might already exist.";
        header("Location: index.php");
        exit;
    }
}
?>