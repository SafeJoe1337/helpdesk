<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: index.php");
    exit;
}

$allowedStatuses = ['Pending', 'Ongoing', 'Resolved'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $reportId = (int) $_POST['id'];
    $status = $_POST['status'];

    if (!in_array($status, $allowedStatuses, true)) {
        $_SESSION['error'] = 'Invalid status selected.';
        header("Location: user_dashboard.php");
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND assigned_to = ?");
    $check->execute([$reportId, $_SESSION['user_id']]);

    if (!$check->fetch()) {
        $_SESSION['error'] = 'You can only update status for tasks assigned to you.';
        header("Location: user_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$status, $reportId, $_SESSION['user_id']]);
    $_SESSION['success'] = 'Status updated successfully.';
}

header("Location: user_dashboard.php");
exit;
