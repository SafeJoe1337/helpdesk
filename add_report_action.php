<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['note'])) {
    $reportId = (int) $_POST['id'];
    $note = trim($_POST['note']);

    if ($note === '') {
        $_SESSION['error'] = 'Please describe the actions you took.';
        header("Location: user_dashboard.php");
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND assigned_to = ?");
    $check->execute([$reportId, $_SESSION['user_id']]);

    if (!$check->fetch()) {
        $_SESSION['error'] = 'You can only add actions for tasks assigned to you.';
        header("Location: user_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO report_actions (report_id, user_id, note) VALUES (?, ?, ?)");
    $stmt->execute([$reportId, $_SESSION['user_id'], $note]);
    $_SESSION['success'] = 'Action note saved successfully.';
}

header("Location: user_dashboard.php");
exit;
