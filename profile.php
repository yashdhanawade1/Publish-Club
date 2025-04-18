<?php
require_once 'config/config.php';

// Initialize session if not already started
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

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stored_password = $stmt->fetchColumn();
    
    if (!password_verify($current_password, $stored_password)) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            $success = 'Password updated successfully';
        } catch (PDOException $e) {
            $error = 'Failed to update password';
        }
    }
}

// Fetch user statistics
$stats = [];
if ($user['role'] === 'user') {
    // Get article statistics for regular users
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_articles,
            COUNT(CASE WHEN status = 'published' THEN 1 END) as published_articles,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_articles,
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_articles
        FROM articles 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} elseif ($user['role'] === 'reviewer') {
    // Get review statistics for reviewers
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_reviews
        FROM reviews 
        WHERE reviewer_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - The Publish Club</title>
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
                    <?php if ($user['role'] === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reviews.php">Reviews</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
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
                <div class="profile-avatar mb-3">
                    <i class="bi bi-person-circle display-1"></i>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="hero-subtitle"><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Profile Information</h5>
                            <div class="profile-info">
                                <div class="mb-3">
                                    <div class="text-muted mb-1">Username</div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person me-2"></i>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="text-muted mb-1">Email</div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-envelope me-2"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted mb-1">Role</div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-shield-check me-2"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($stats)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Statistics</h5>
                            <?php if ($user['role'] === 'user'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-file-text"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['total_articles']; ?></div>
                                        <div class="stat-label text-muted">Total Articles</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['published_articles']; ?></div>
                                        <div class="stat-label text-muted">Published Articles</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['pending_articles']; ?></div>
                                        <div class="stat-label text-muted">Pending Articles</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['under_review_articles']; ?></div>
                                        <div class="stat-label text-muted">Under Review</div>
                                    </div>
                                </div>
                            <?php elseif ($user['role'] === 'reviewer'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-star"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['total_reviews']; ?></div>
                                        <div class="stat-label text-muted">Total Reviews</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value"><?php echo $stats['recent_reviews']; ?></div>
                                        <div class="stat-label text-muted">Reviews (Last 30 Days)</div>
                                    </div>
                                </div>
                                <?php if ($stats['avg_rating']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon me-3">
                                            <i class="bi bi-bar-chart"></i>
                                        </div>
                                        <div>
                                            <div class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?>/5</div>
                                            <div class="stat-label text-muted">Average Rating Given</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Change Password</h5>
                            <form method="POST">
                                <div class="mb-4">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-key"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-key-fill"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    Update Password
                                </button>
                            </form>
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
</body>
</html>
