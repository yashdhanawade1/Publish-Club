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

if (!isset($_GET['attempt'])) {
    header('Location: quizzes.php');
    exit();
}

$attempt_id = $_GET['attempt'];

// Get attempt details with quiz info
$stmt = $pdo->prepare("
    SELECT 
        qa.*,
        q.title,
        q.passing_score,
        q.thumbnail_path,
        (
            SELECT COUNT(DISTINCT qa2.id)
            FROM quiz_attempts qa2
            WHERE qa2.quiz_id = q.id 
            AND qa2.completed_at IS NOT NULL
            AND (
                (qa2.score / qa2.total_questions) > (qa.score / qa.total_questions)
                OR (
                    (qa2.score / qa2.total_questions) = (qa.score / qa.total_questions)
                    AND qa2.time_taken < qa.time_taken
                )
            )
        ) + 1 as rank
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ? AND qa.user_id = ? AND qa.completed_at IS NOT NULL
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: quizzes.php');
    exit();
}

// Get detailed question results
$stmt = $pdo->prepare("
    SELECT 
        q.question_text,
        q.points,
        ua.is_correct,
        ua.points_earned,
        ao_selected.option_text as selected_answer,
        ao_correct.option_text as correct_answer
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    JOIN answer_options ao_selected ON ua.answer_option_id = ao_selected.id
    JOIN answer_options ao_correct ON q.id = ao_correct.question_id AND ao_correct.is_correct = 1
    WHERE ua.attempt_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attempt_id]);
$question_results = $stmt->fetchAll();

// Calculate statistics
$score_percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
$passed = $score_percentage >= $attempt['passing_score'];
$correct_answers = array_filter($question_results, fn($q) => $q['is_correct']);
$accuracy = count($correct_answers) / count($question_results) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .result-header {
            background-size: cover;
            background-position: center;
            position: relative;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            padding: 60px 0;
            margin-top: 56px; /* Account for fixed navbar */
        }
        .result-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.8));
        }
        .result-content {
            position: relative;
            z-index: 1;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 8px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 2rem;
            font-weight: bold;
        }
        .passed {
            border-color: #28a745;
            color: #28a745;
        }
        .failed {
            border-color: #dc3545;
            color: #dc3545;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .question-result {
            border-left: 4px solid;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        .correct {
            border-left-color: #28a745;
        }
        .incorrect {
            border-left-color: #dc3545;
        }
        .certificate {
            border: 2px solid #gold;
            padding: 30px;
            text-align: center;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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

    <div class="result-header" style="background-image: url('<?php echo $attempt['thumbnail_path'] ?? 'assets/images/default-quiz-bg.jpg'; ?>')">
        <div class="container">
            <div class="result-content text-center">
                <h1 class="display-4 mb-4"><?php echo htmlspecialchars($attempt['title']); ?></h1>
                <div class="score-circle <?php echo $passed ? 'passed' : 'failed'; ?>">
                    <?php echo round($score_percentage, 1); ?>%
                </div>
                <h3 class="mt-3">
                    <?php if ($passed): ?>
                        <i class="bi bi-trophy-fill text-warning"></i> Congratulations! You Passed!
                    <?php else: ?>
                        <i class="bi bi-emoji-frown"></i> Keep practicing! You'll get there!
                    <?php endif; ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title">Performance Summary</h4>
                        <div class="stat-card mb-3">
                            <i class="bi bi-trophy text-warning fs-1"></i>
                            <h3 class="mt-2 mb-0">#<?php echo $attempt['rank']; ?></h3>
                            <p class="text-muted">Your Rank</p>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-bullseye"></i> Accuracy</span>
                                <strong><?php echo round($accuracy, 1); ?>%</strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-clock"></i> Time Taken</span>
                                <strong>
                                    <?php 
                                        echo floor($attempt['time_taken'] / 60) . 'm ' . 
                                             ($attempt['time_taken'] % 60) . 's';
                                    ?>
                                </strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-check-circle"></i> Correct Answers</span>
                                <strong><?php echo count($correct_answers); ?>/<?php echo count($question_results); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($passed): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="certificate">
                                <i class="bi bi-award text-warning display-1"></i>
                                <h4 class="mt-3">Certificate of Achievement</h4>
                                <p class="text-muted">
                                    <?php echo htmlspecialchars($_SESSION['username']); ?><br>
                                    has successfully completed<br>
                                    <?php echo htmlspecialchars($attempt['title']); ?>
                                </p>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($attempt['completed_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Detailed Results</h4>
                        <?php foreach ($question_results as $index => $result): ?>
                            <div class="question-result <?php echo $result['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                <div class="d-flex justify-content-between">
                                    <h5 class="mb-3">Question <?php echo $index + 1; ?></h5>
                                    <span class="badge <?php echo $result['is_correct'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $result['points_earned']; ?>/<?php echo $result['points']; ?> points
                                    </span>
                                </div>
                                <p><?php echo htmlspecialchars($result['question_text']); ?></p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Your Answer:</small>
                                        <p class="mb-0 <?php echo $result['is_correct'] ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo htmlspecialchars($result['selected_answer']); ?>
                                        </p>
                                    </div>
                                    <?php if (!$result['is_correct']): ?>
                                        <div class="col-md-6">
                                            <small class="text-muted">Correct Answer:</small>
                                            <p class="mb-0 text-success">
                                                <?php echo htmlspecialchars($result['correct_answer']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="view_quiz.php?id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-primary me-2">
                View Quiz Details
            </a>
            <a href="quizzes.php" class="btn btn-outline-primary">
                Back to Quizzes
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
