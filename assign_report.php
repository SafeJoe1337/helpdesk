<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $reportId = (int) $_POST['id'];
    $assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== ''
        ? (int) $_POST['assigned_to']
        : null;

    if ($assignedTo !== null) {
        $residentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'resident'");
        $residentCheck->execute([$assignedTo]);
        if (!$residentCheck->fetch()) {
            $_SESSION['error'] = 'Invalid resident selected.';
            header("Location: admin_dashboard.php");
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE reports SET assigned_to = ? WHERE id = ?");
    $stmt->execute([$assignedTo, $reportId]);

    if ($assignedTo !== null) {
        $statusStmt = $pdo->prepare("UPDATE reports SET status = 'Ongoing' WHERE id = ? AND status = 'Pending'");
        $statusStmt->execute([$reportId]);
        $_SESSION['success'] = 'Task assigned to resident successfully.';
    } else {
        $_SESSION['success'] = 'Assignment removed.';
    }
}

header("Location: admin_dashboard.php");
exit;
