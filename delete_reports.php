<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_ids']) && is_array($_POST['report_ids'])) {
    $ids = array_values(array_filter(array_map('intval', $_POST['report_ids']), function ($id) {
        return $id > 0;
    }));

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
}

header("Location: admin_dashboard.php");
exit;
?>
