<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

if (!isset($_GET['id'])) {
    header('Location: quizzes.php');
    exit();
}

$quiz_id = $_GET['id'];

// Get quiz details
$stmt = $pdo->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count
    FROM quizzes q
    WHERE q.id = ? AND q.status = 'published'
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quizzes.php');
    exit();
}

// Start the quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_quiz'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (quiz_id, user_id, score, total_questions)
            VALUES (?, ?, 0, ?)
        ");
        $stmt->execute([$quiz_id, $_SESSION['user_id'], $quiz['question_count']]);
        $attempt_id = $pdo->lastInsertId();
        
        header("Location: take_quiz.php?attempt=" . $attempt_id);
        exit();
    } catch (Exception $e) {
        $error = "Error starting quiz: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Quiz - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .quiz-header {
            background-size: cover;
            background-position: center;
            min-height: 200px;
            position: relative;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            margin-top: 56px; /* Account for fixed navbar */
        }
        .quiz-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.7));
        }
        .quiz-header-content {
            position: relative;
            z-index: 1;
        }
        .instruction-card {
            border-left: 4px solid #0d6efd;
            background: #f8f9fa;
            margin-bottom: 15px;
            padding: 15px;
        }
        .info-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-card i {
            font-size: 2rem;
            color: #0d6efd;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">The Publish Club</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="articles.php">Articles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="quizzes.php">Quizzes</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reviews.php">Reviews</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin Panel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quiz_dashboard.php">Quiz Management</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="quiz-header" style="background-image: url('<?php echo $quiz['thumbnail_path'] ?? 'assets/images/default-quiz-bg.jpg'; ?>')">
        <div class="container py-5">
            <div class="quiz-header-content">
                <h1 class="display-4"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                <p class="lead"><?php echo htmlspecialchars($quiz['description']); ?></p>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title">Quiz Instructions</h3>
                        
                        <div class="instruction-card">
                            <h5><i class="bi bi-info-circle"></i> Before You Begin</h5>
                            <ul>
                                <li>Make sure you have a stable internet connection</li>
                                <li>Find a quiet place without distractions</li>
                                <li>Keep track of the time limit</li>
                            </ul>
                        </div>

                        <div class="instruction-card">
                            <h5><i class="bi bi-list-check"></i> Quiz Rules</h5>
                            <ul>
                                <li>You have <?php echo $quiz['time_limit']; ?> minutes to complete the quiz</li>
                                <li>There are <?php echo $quiz['question_count']; ?> questions in total</li>
                                <li>Each question has only one correct answer</li>
                                <li>You must score <?php echo $quiz['passing_score']; ?>% or higher to pass</li>
                                <li>The quiz will auto-submit when the time runs out</li>
                            </ul>
                        </div>

                        <div class="instruction-card">
                            <h5><i class="bi bi-trophy"></i> Scoring</h5>
                            <ul>
                                <li>Each question is worth different points based on difficulty</li>
                                <li>No negative marking for wrong answers</li>
                                <li>Your final score and rank will be shown after completion</li>
                                <li>A certificate will be awarded if you pass the quiz</li>
                            </ul>
                        </div>

                        <form method="post" class="mt-4">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="agree" required>
                                <label class="form-check-label" for="agree">
                                    I have read and understood the instructions
                                </label>
                            </div>
                            <button type="submit" name="start_quiz" class="btn btn-primary btn-lg">
                                Start Quiz
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="info-card">
                    <i class="bi bi-clock"></i>
                    <h4><?php echo $quiz['time_limit']; ?> Minutes</h4>
                    <p class="text-muted mb-0">Time Limit</p>
                </div>

                <div class="info-card">
                    <i class="bi bi-question-circle"></i>
                    <h4><?php echo $quiz['question_count']; ?> Questions</h4>
                    <p class="text-muted mb-0">Total Questions</p>
                </div>

                <div class="info-card">
                    <i class="bi bi-award"></i>
                    <h4><?php echo $quiz['passing_score']; ?>%</h4>
                    <p class="text-muted mb-0">Passing Score</p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tips for Success</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="bi bi-clock-history"></i> Manage your time wisely
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-eye"></i> Read questions carefully
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-skip-forward"></i> Skip difficult questions and return later
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-check2-circle"></i> Review your answers before submitting
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
