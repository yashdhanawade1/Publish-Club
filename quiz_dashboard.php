<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();
$error = '';
$success = '';

// Fetch all quizzes with analytics
$stmt = $pdo->prepare("
    SELECT 
        q.id,
        q.title,
        q.description,
        q.status,
        COUNT(DISTINCT qa.id) as total_attempts,
        AVG(qa.score) as avg_score,
        COUNT(DISTINCT qu.id) as question_count,
        q.created_at
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
    LEFT JOIN questions qu ON q.id = qu.quiz_id
    GROUP BY q.id
    ORDER BY q.created_at DESC
");
$stmt->execute();
$quizzes = $stmt->fetchAll();

// Fetch recent quiz attempts with user details
$stmt = $pdo->prepare("
    SELECT 
        qa.id as attempt_id,
        q.title as quiz_title,
        u.username,
        qa.score,
        qa.total_questions,
        qa.time_taken,
        qa.completed_at
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN users u ON qa.user_id = u.id
    WHERE qa.completed_at IS NOT NULL
    ORDER BY qa.completed_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_attempts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
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
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reviews.php">Reviews</a>
                        </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin Panel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="quiz_dashboard.php">Quiz Management</a>
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

    <div class="hero-banner">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Quiz Management</h1>
                <p class="hero-subtitle">Create and manage quizzes for The Publish Club community.</p>
                <a href="create_quiz.php" class="btn btn-primary btn-lg">Create New Quiz</a>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4 justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">All Quizzes</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            require_once 'config/config.php';
                            $pdo = db_connect();
                            
                            $stmt = $pdo->query("
                                SELECT q.*, 
                                       COUNT(DISTINCT qa.id) as attempt_count,
                                       COUNT(DISTINCT qu.id) as question_count,
                                       AVG(qa.score) as avg_score
                                FROM quizzes q
                                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
                                LEFT JOIN questions qu ON q.id = qu.quiz_id
                                GROUP BY q.id
                                ORDER BY q.created_at DESC
                            ");
                            $quizzes = $stmt->fetchAll();
                            ?>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Questions</th>
                                            <th>Attempts</th>
                                            <th>Avg Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $quiz['thumbnail_path'] ?? 'assets/images/default-quiz.jpg'; ?>" 
                                                             class="rounded" width="48" height="48" alt="">
                                                        <div class="ms-3">
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                            <small class="text-muted">
                                                                <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($quiz['status']) {
                                                            'published' => 'success',
                                                            'draft' => 'secondary',
                                                            'archived' => 'danger'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($quiz['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $quiz['question_count']; ?></td>
                                                <td><?php echo $quiz['attempt_count']; ?></td>
                                                <td>
                                                    <?php 
                                                    if ($quiz['avg_score']) {
                                                        echo round($quiz['avg_score'], 1) . '%';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="quiz_stats.php?id=<?php echo $quiz['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="bi bi-graph-up"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Quiz Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $stats = $pdo->query("
                                SELECT 
                                    COUNT(*) as total_quizzes,
                                    COUNT(CASE WHEN status = 'published' THEN 1 END) as published_quizzes,
                                    (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
                                    (SELECT COUNT(DISTINCT user_id) FROM quiz_attempts) as unique_users
                                FROM quizzes
                            ")->fetch();
                            ?>
                            <div class="row g-4">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="mb-1"><?php echo $stats['total_quizzes']; ?></h3>
                                        <small class="text-muted">Total Quizzes</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="mb-1"><?php echo $stats['published_quizzes']; ?></h3>
                                        <small class="text-muted">Published</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="mb-1"><?php echo $stats['total_attempts']; ?></h3>
                                        <small class="text-muted">Total Attempts</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="mb-1"><?php echo $stats['unique_users']; ?></h3>
                                        <small class="text-muted">Unique Users</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recent_activity = $pdo->query("
                                SELECT 
                                    qa.id,
                                    q.title as quiz_title,
                                    u.username,
                                    qa.score,
                                    qa.completed_at
                                FROM quiz_attempts qa
                                JOIN quizzes q ON qa.quiz_id = q.id
                                JOIN users u ON qa.user_id = u.id
                                WHERE qa.completed_at IS NOT NULL
                                ORDER BY qa.completed_at DESC
                                LIMIT 5
                            ")->fetchAll();
                            ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['username']); ?></h6>
                                                <small class="text-muted">
                                                    completed <?php echo htmlspecialchars($activity['quiz_title']); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-success">
                                                <?php echo $activity['score']; ?>%
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['completed_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h3 class="footer-title">The Publish Club</h3>
                    <p>A platform for knowledge sharing, learning, and community engagement.</p>
                </div>
                <div class="col-md-4">
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="articles.php">Articles</a></li>
                        <li><a href="quizzes.php">Quizzes</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h3 class="footer-title">Connect With Us</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="bi bi-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="bi bi-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="bi bi-linkedin"></i> LinkedIn</a></li>
                        <li><a href="#"><i class="bi bi-github"></i> GitHub</a></li>
                    </ul>
                </div>
            </div>
            <hr class="mt-4 mb-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> The Publish Club. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteQuiz(quizId) {
        if (confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
            window.location.href = `delete_quiz.php?id=${quizId}`;
        }
    }
    </script>
</body>
</html>
