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
$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header('Location: quizzes.php');
    exit();
}

$quiz_id = $_GET['id'];

// Fetch quiz details
$stmt = $pdo->prepare("
    SELECT q.*, 
           COUNT(DISTINCT qa.id) as total_attempts,
           AVG(qa.score) as avg_score
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
    WHERE q.id = ? AND (q.status = 'published' OR ? = true)
    GROUP BY q.id
");
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$stmt->execute([$quiz_id, $is_admin]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quizzes.php');
    exit();
}

// Fetch top performers
$stmt = $pdo->prepare("
    SELECT 
        u.username,
        qa.score,
        qa.total_questions,
        qa.time_taken,
        qa.completed_at,
        RANK() OVER (ORDER BY (qa.score / qa.total_questions * 100) DESC, qa.time_taken ASC) as rank
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = ? AND qa.completed_at IS NOT NULL
    ORDER BY (qa.score / qa.total_questions * 100) DESC, qa.time_taken ASC
    LIMIT 10
");
$stmt->execute([$quiz_id]);
$top_performers = $stmt->fetchAll();

// Get user's best attempt
$stmt = $pdo->prepare("
    SELECT 
        qa.score,
        qa.total_questions,
        qa.time_taken,
        qa.completed_at,
        RANK() OVER (ORDER BY (qa.score / qa.total_questions * 100) DESC, qa.time_taken ASC) as rank
    FROM quiz_attempts qa
    WHERE qa.quiz_id = ? AND qa.user_id = ? AND qa.completed_at IS NOT NULL
    ORDER BY (qa.score / qa.total_questions * 100) DESC, qa.time_taken ASC
    LIMIT 1
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$user_best_attempt = $stmt->fetch();

// Check if user has any ongoing attempt
$stmt = $pdo->prepare("
    SELECT id FROM quiz_attempts 
    WHERE quiz_id = ? AND user_id = ? AND completed_at IS NULL
    LIMIT 1
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$ongoing_attempt = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - The Publish Club</title>
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
        .leaderboard-item {
            transition: transform 0.2s;
        }
        .leaderboard-item:hover {
            transform: translateX(5px);
        }
        .rank-1 {
            background-color: #ffd700 !important;
            color: #000;
        }
        .rank-2 {
            background-color: #c0c0c0 !important;
            color: #000;
        }
        .rank-3 {
            background-color: #cd7f32 !important;
            color: #000;
        }
        .quiz-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
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
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
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
                        <h3>Quiz Information</h3>
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <i class="bi bi-clock"></i>
                                    <h4><?php echo $quiz['time_limit']; ?></h4>
                                    <p class="text-muted mb-0">Minutes</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <i class="bi bi-trophy"></i>
                                    <h4><?php echo $quiz['passing_score']; ?>%</h4>
                                    <p class="text-muted mb-0">To Pass</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card">
                                    <i class="bi bi-people"></i>
                                    <h4><?php echo $quiz['total_attempts']; ?></h4>
                                    <p class="text-muted mb-0">Attempts</p>
                                </div>
                            </div>
                        </div>

                        <?php if ($user_best_attempt): ?>
                            <div class="alert alert-info">
                                <h5>Your Best Performance</h5>
                                <p class="mb-1">Score: <?php echo round(($user_best_attempt['score'] / $user_best_attempt['total_questions']) * 100, 1); ?>%</p>
                                <p class="mb-1">Time: <?php echo floor($user_best_attempt['time_taken'] / 60); ?> minutes <?php echo $user_best_attempt['time_taken'] % 60; ?> seconds</p>
                                <p class="mb-0">Rank: #<?php echo $user_best_attempt['rank']; ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($quiz['status'] === 'published'): ?>
                            <?php if ($ongoing_attempt): ?>
                                <a href="take_quiz.php?attempt=<?php echo $ongoing_attempt['id']; ?>" class="btn btn-warning btn-lg w-100">
                                    Continue Attempt
                                </a>
                            <?php else: ?>
                                <a href="start_quiz.php?id=<?php echo $quiz_id; ?>" class="btn btn-primary btn-lg w-100">
                                    Start Quiz
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                This quiz is currently not available.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">🏆 Top Performers</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_performers as $performer): ?>
                                <div class="list-group-item leaderboard-item <?php echo 'rank-' . $performer['rank']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo $performer['rank'] <= 3 ? '👑 ' : ''; ?>
                                                <?php echo htmlspecialchars($performer['username']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($performer['completed_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo round(($performer['score'] / $performer['total_questions']) * 100, 1); ?>%</strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo floor($performer['time_taken'] / 60); ?>m 
                                                <?php echo $performer['time_taken'] % 60; ?>s
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
