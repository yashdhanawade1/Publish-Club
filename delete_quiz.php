<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: quiz_dashboard.php');
    exit();
}

$pdo = db_connect();
$quiz_id = $_GET['id'];

try {
    $pdo->beginTransaction();

    // Delete the quiz and all related data (questions, options, attempts, and user answers will be deleted via CASCADE)
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);

    $pdo->commit();
    $_SESSION['success'] = "Quiz deleted successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting quiz: " . $e->getMessage();
}

header('Location: quiz_dashboard.php');
exit();
?>
