<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $title = $_POST['title'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

    $stmt = $pdo->prepare("INSERT INTO reports (user_id, title, category, description, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$user_id, $title, $category, $description, $latitude, $longitude]);
        $_SESSION['success'] = "Your report has been submitted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Submission failed. Please try again.";
    }
    
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        header("Location: user_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}
header("Location: index.php");