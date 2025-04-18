<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .quiz-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .quiz-card:hover {
            transform: translateY(-5px);
        }
        .quiz-thumbnail {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: calc(0.375rem - 1px);
            border-top-right-radius: calc(0.375rem - 1px);
        }
        .quiz-stats {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .quiz-progress {
            height: 8px;
        }
        .leaderboard-card {
            background: linear-gradient(45deg, #4158D0, #C850C0);
            color: white;
        }
        .performer-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .badge-outline {
            background: transparent;
            border: 1px solid currentColor;
        }
        .navbar {
            background-color: #fff;
        }
        .quiz-section {
            display: flex;
            justify-content: center;
        }
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .quiz-card {
            grid-column: span 1;
        }
    </style>
</head>
<body>
    <?php
    require_once 'config/config.php';

    // Initialize session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get quizzes from database
    $pdo = db_connect();
    $stmt = $pdo->query("
        SELECT q.*, 
               COUNT(DISTINCT qa.id) as attempt_count,
               COUNT(DISTINCT qu.id) as question_count,
               u.username as creator_name
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
        LEFT JOIN questions qu ON q.id = qu.quiz_id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.status = 'published'
        GROUP BY q.id
        ORDER BY q.created_at DESC
    ");
    $quizzes = $stmt->fetchAll();
    ?>
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
                        <a class="nav-link active" href="quizzes.php">Quizzes</a>
                    </li>
                    <?php if (isset($_SESSION['role'])): ?>
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
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-banner" style="background: linear-gradient(135deg, #0d6efd, #0099ff);">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Test Your Knowledge</h1>
                <p class="hero-subtitle">Explore our collection of quizzes and challenge yourself to learn something new.</p>
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

            <div class="quiz-section">
                <?php if (empty($quizzes)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No quizzes available at the moment.
                    </div>
                <?php else: ?>
                    <div class="quiz-grid">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-4">
                                <div class="card quiz-card h-100">
                                    <img src="<?php echo $quiz['thumbnail_path'] ?? 'assets/images/default-quiz.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($quiz['title']); ?>">
                                    <div class="card-body d-flex flex-column">
                                        <div class="quiz-meta mb-2">
                                            <i class="bi bi-people-fill"></i> <?php echo $quiz['attempt_count']; ?> attempts
                                            <i class="bi bi-list-check ms-3"></i> <?php echo $quiz['question_count']; ?> questions
                                            <?php if ($quiz['time_limit']): ?>
                                                <i class="bi bi-clock-fill ms-3"></i> <?php echo $quiz['time_limit']; ?> minutes
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                        <div class="mt-3">
                                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] !== $quiz['created_by'])): ?>
                                                <a href="start_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                                                    Take Quiz
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary" disabled>Cannot take your own quiz</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($quiz['user_attempts'] > 0): ?>
                                                <a href="quiz_history.php?quiz_id=<?php echo $quiz['id']; ?>" 
                                                   class="btn btn-outline-primary ms-2">
                                                    View History
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
            <hr class="mt-4 mb-4 border-light">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> The Publish Club. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
