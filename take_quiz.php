<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to take quizzes.";
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

if (!isset($_GET['attempt'])) {
    $_SESSION['error'] = "Invalid quiz attempt.";
    header('Location: quizzes.php');
    exit();
}

$attempt_id = $_GET['attempt'];

// Verify this attempt belongs to the current user
$stmt = $pdo->prepare("
    SELECT qa.*, q.title, q.time_limit
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ? AND qa.user_id = ? AND qa.completed_at IS NULL
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = "Invalid or expired quiz attempt.";
    header('Location: quizzes.php');
    exit();
}

// Check if the quiz time limit has been exceeded
$timeElapsed = time() - strtotime($attempt['started_at']);
if ($timeElapsed >= $attempt['time_limit'] * 60) {
    try {
        // Auto-submit the quiz
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET score = 0,
                time_taken = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$timeElapsed, $attempt_id]);
        
        $pdo->commit();
        
        $_SESSION['warning'] = "Quiz time limit exceeded. Your attempt has been submitted.";
        header("Location: quiz_result.php?attempt=" . $attempt_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error auto-submitting quiz: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again.";
        header('Location: quizzes.php');
        exit();
    }
}

// Get questions for this quiz
try {
$stmt = $pdo->prepare("
        SELECT q.*
    FROM questions q
    WHERE q.quiz_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attempt['quiz_id']]);
$questions = $stmt->fetchAll();

    if (empty($questions)) {
        $_SESSION['error'] = "This quiz has no questions.";
        header('Location: quizzes.php');
        exit();
    }

    // Get options for all questions
    $questionIds = array_column($questions, 'id');
    $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT question_id, id, option_text, order_num
        FROM answer_options
        WHERE question_id IN ($placeholders)
        ORDER BY question_id, order_num
    ");
    $stmt->execute($questionIds);
    $allOptions = $stmt->fetchAll();
    
    // Group options by question
    $optionsByQuestion = [];
    foreach ($allOptions as $option) {
        if (!isset($optionsByQuestion[$option['question_id']])) {
            $optionsByQuestion[$option['question_id']] = [];
        }
        $optionsByQuestion[$option['question_id']][] = $option;
    }
    
    // Add options to each question
foreach ($questions as &$question) {
        $question['options'] = $optionsByQuestion[$question['id']] ?? [];
        if (empty($question['options'])) {
            throw new Exception("Question {$question['id']} has no options.");
        }
    }
    unset($question);
} catch (Exception $e) {
    error_log("Error loading quiz: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the quiz. Please try again.";
    header('Location: quizzes.php');
    exit();
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    try {
        $pdo->beginTransaction();
        
        $score = 0;
        $total_questions = count($questions);
        
        // Validate that all questions are answered
        if (count($_POST['answers'] ?? []) !== $total_questions) {
            throw new Exception("Please answer all questions before submitting.");
        }
        
        foreach ($_POST['answers'] as $question_id => $answer_id) {
            // Validate question belongs to this quiz
            if (!in_array($question_id, $questionIds)) {
                throw new Exception("Invalid question detected.");
            }
            
            // Get correct answer
            $stmt = $pdo->prepare("
                SELECT ao.is_correct, ao.points 
                FROM answer_options ao
                WHERE ao.id = ? AND ao.question_id = ?
            ");
            $stmt->execute([$answer_id, $question_id]);
            $result = $stmt->fetch();
            
            if (!$result) {
                throw new Exception("Invalid answer option detected.");
            }
            
                $points_earned = $result['is_correct'] ? $result['points'] : 0;
                $score += $points_earned;
                
                // Save user's answer
                $stmt = $pdo->prepare("
                    INSERT INTO user_answers (
                        attempt_id, 
                        question_id, 
                        answer_option_id, 
                        is_correct, 
                        points_earned
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $attempt_id,
                    $question_id,
                    $answer_id,
                    $result['is_correct'] ? 1 : 0,
                    $points_earned
                ]);
        }
        
        // Update attempt
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET score = ?, 
                total_questions = ?,
                time_taken = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$score, $total_questions, $attempt_id]);
        
        $pdo->commit();
        header("Location: quiz_result.php?attempt=" . $attempt_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error submitting quiz: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($attempt['title']); ?> - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .quiz-timer {
            position: fixed;
            top: 70px; /* Adjusted to account for navbar */
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .question-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: .25rem;
            margin-bottom: 1rem;
            contain: content;
        }
        .options-container {
            display: grid;
            gap: 10px;
            padding: 10px 0;
            contain: layout style paint;
        }
        .option {
            position: relative;
            transition: transform 0.2s ease;
            will-change: transform;
            contain: layout style paint;
        }
        .option-label {
            display: block;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            background: #fff;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            user-select: none;
        }
        .option-label:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .option-label.active {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .option-input:checked + .option-label {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        @media (prefers-reduced-motion: reduce) {
            .option {
                transition: none;
            }
            .option-label {
                transition: none;
            }
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

    <div style="margin-top: 56px;"><!-- Spacer for fixed navbar --></div>

    <div class="quiz-timer" id="timer">
        <i class="bi bi-clock"></i> <span id="time-remaining"></span>
    </div>

    <div class="container my-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2><?php echo htmlspecialchars($attempt['title']); ?></h2>
                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar" id="progress-bar"></div>
                        </div>
                        <p class="text-muted">
                            Time Limit: <?php echo $attempt['time_limit']; ?> minutes
                        </p>
                    </div>
                </div>

                <form method="post" id="quiz-form">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card card">
                            <div class="card-body">
                                <h5 class="card-title">Question <?php echo $index + 1; ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                
                                <div class="options-container">
                                    <?php 
                                    if (!empty($question['options'])) {
                                        foreach ($question['options'] as $option): 
                                    ?>
                                        <div class="option">
                                            <input type="radio" 
                                                   id="option_<?php echo $option['id']; ?>" 
                                                   name="answers[<?php echo $question['id']; ?>]" 
                                                   value="<?php echo $option['id']; ?>"
                                                   class="option-input d-none" 
                                                   required>
                                            <label for="option_<?php echo $option['id']; ?>" class="option-label">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php 
                                        endforeach;
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-grid">
                        <button type="submit" name="submit_quiz" class="btn btn-primary btn-lg">
                            Submit Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store timer variables globally to avoid recalculating
        const startTime = new Date('<?php echo $attempt['started_at']; ?>').getTime();
        const timeLimit = <?php echo $attempt['time_limit']; ?> * 60 * 1000; // Convert minutes to milliseconds
        const endTime = startTime + timeLimit;
        let timerInterval;

        function updateTimer() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance <= 0) {
                clearInterval(timerInterval);
                document.getElementById('quiz-form').submit();
                return;
            }

            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('time-remaining').innerHTML = 
                (hours > 0 ? hours + "h " : "") + 
                (minutes < 10 ? "0" : "") + minutes + "m " + 
                (seconds < 10 ? "0" : "") + seconds + "s";
        }

        // Initialize timer
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);

        // Handle form submission
        document.getElementById('quiz-form').addEventListener('submit', function(e) {
            clearInterval(timerInterval);
        });

        // Optimize option selection
        const optionLabels = document.querySelectorAll('.option-label');
        optionLabels.forEach(label => {
            label.addEventListener('click', function() {
                // Remove active class from all options in the same question
                const questionDiv = this.closest('.question');
                questionDiv.querySelectorAll('.option-label').forEach(l => l.classList.remove('active'));
                // Add active class to clicked option
                this.classList.add('active');
            });
        });

        // Disable context menu and selection to prevent cheating
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('selectstart', e => e.preventDefault());

        // Warn before leaving page
        window.addEventListener('beforeunload', function(e) {
            const confirmationMessage = 'Are you sure you want to leave? Your quiz progress will be lost.';
            e.returnValue = confirmationMessage;
            return confirmationMessage;
        });
    </script>
</body>
</html>
