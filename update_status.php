<?php
require 'db.php';
session_start();

// Security check: Only admins should be able to update status
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $note = isset($_POST['action_taken']) ? trim((string)$_POST['action_taken']) : '';

    $pdo->beginTransaction();
    try {
        // Update the main report status
        $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Save action note into timeline (admin updates)
        if ($note !== '') {
            $historyStmt = $pdo->prepare("INSERT INTO report_actions (report_id, user_id, note) VALUES (?, ?, ?)");
            $historyStmt->execute([$id, $_SESSION['user_id'], $note]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // On failure, still redirect with error flag
        header("Location: admin_dashboard.php?error=failed");
        exit;
    }
}

header("Location: admin_dashboard.php");
exit;
