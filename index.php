<?php
require_once 'config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db_connect();

// Get categories
$stmt = $pdo->query("SELECT id, name, slug, description FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Fetch recent articles
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.title,
        a.abstract,
        a.thumbnail_path,
        a.created_at,
        u.username,
        COUNT(r.id) as review_count,
        AVG(r.rating) as avg_rating
    FROM articles a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id
    WHERE a.status = 'published'
    GROUP BY a.id
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmt->execute();
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Publish Club - Share Knowledge, Learn Together</title>
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="articles.php">Articles</a>
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

    <div class="hero-banner">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Welcome to The Publish Club</h1>
                <p class="hero-subtitle">Share your knowledge, learn from others, and grow together.</p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-lg me-3">Join Now</a>
                        <a href="login.php" class="btn btn-outline-primary btn-lg">Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 text-center">
                    <h2 class="section-title">Explore Categories</h2>
                    <p class="section-subtitle">Discover articles in your favorite topics</p>
                </div>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($categories as $category): ?>
                <div class="col-md-4">
                    <a href="published.php?category=<?php echo urlencode($category['slug']); ?>" 
                       class="category-card text-decoration-none">
                        <div class="card h-100 border-0 shadow-hover">
                            <div class="card-body p-4 text-center">
                                <div class="category-icon mb-3">
                                    <i class="bi bi-folder2-open display-4"></i>
                                </div>
                                <h3 class="category-title h4 mb-2">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h3>
                                <?php if ($category['description']): ?>
                                    <p class="category-description mb-0 text-muted">
                                        <?php echo htmlspecialchars($category['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 text-center">
                    <h2 class="section-title">Recent Articles</h2>
                    <p class="section-subtitle">Latest knowledge shared by our community</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($articles as $article): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-img-top article-thumbnail">
                            <?php if ($article['thumbnail_path'] && file_exists('uploads/' . $article['thumbnail_path'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($article['title']); ?>"
                                     class="img-fluid">
                            <?php else: ?>
                                <div class="default-thumbnail">
                                    <i class="bi bi-file-text display-4"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                            <div class="article-meta">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-person-circle me-2"></i>
                                    <span><?php echo htmlspecialchars($article['username']); ?></span>
                                </div>
                                <?php if ($article['review_count'] > 0): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="text-warning me-2">
                                            <?php
                                            $rating = round($article['avg_rating']);
                                            for ($i = 0; $i < 5; $i++) {
                                                echo '<i class="bi bi-star' . ($i < $rating ? '-fill' : '') . '"></i>';
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted">(<?php echo $article['review_count']; ?> reviews)</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($article['abstract']): ?>
                                <p class="card-text mt-3"><?php echo htmlspecialchars(substr($article['abstract'], 0, 150)) . '...'; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                                </small>
                                <a href="view_article.php?id=<?php echo $article['id']; ?>" 
                                   class="btn btn-outline-primary">Read More</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="articles.php" class="btn btn-outline-primary">View All Articles</a>
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
