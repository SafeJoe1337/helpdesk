<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;

    $stmt = $pdo->prepare("UPDATE reports SET status = ?, assigned_to = ? WHERE id = ?");
    if ($stmt->execute([$status, $assigned_to, $id])) {
        header("Location: admin_dashboard_original.php?msg=updated");
    } else {
        header("Location: admin_dashboard_original.php?error=failed");
    }
    exit;
}