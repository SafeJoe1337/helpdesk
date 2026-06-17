<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    // Avoid bouncing the resident to landing page for an internal POST failure.
    // When role/session is missing, send back to the resident dashboard.
    header("Location: user_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $action_taken = trim((string)($_POST['action_taken'] ?? ''));

    $pdo->beginTransaction();
    try {
        // Verify task exists and is assigned to this resident
        $checkStmt = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND assigned_to = ?");
        $checkStmt->execute([$id, $_SESSION['user_id']]);
        if (!$checkStmt->fetch()) {
            throw new Exception("Task not found or not assigned to you.");
        }

        // Update the main report status
        $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$status, $id, $_SESSION['user_id']]);

        // Add to history timeline
        $historyStmt = $pdo->prepare("INSERT INTO report_actions (report_id, user_id, note) VALUES (?, ?, ?)");
        $historyStmt->execute([$id, $_SESSION['user_id'], $action_taken]);

        $pdo->commit();
        // Redirect back to the resident dashboard so the UI can reflect the updated status.
        $_SESSION['success'] = 'Task updated successfully.';
        header("Location: user_dashboard.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
        header("Location: user_dashboard.php");
        exit;
    }
}