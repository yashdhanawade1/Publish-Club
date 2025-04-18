<?php
require_once './config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is reviewer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'reviewer') {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

// Get article ID from URL
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if article exists and is assigned to this reviewer
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.username as author,
        r.rating as my_rating,
        r.comments as my_comments,
        r.recommendation as my_recommendation,
        r.reviewed_at as my_review_date
    FROM articles a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id AND r.reviewer_id = ?
    WHERE a.id = ? AND a.reviewer_id = ? AND a.status = 'under_review'
");
$stmt->execute([$_SESSION['user_id'], $article_id, $_SESSION['user_id']]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: reviews.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $recommendation = isset($_POST['recommendation']) ? $_POST['recommendation'] : '';
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    
    $errors = [];
    
    // Validate input
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating between 1 and 5 stars.';
    }
    if (!in_array($recommendation, ['approve', 'reject'])) {
        $errors[] = 'Please select a recommendation (Approve or Reject).';
    }
    if (empty($comments)) {
        $errors[] = 'Please provide review comments.';
    }
    
    if (empty($errors)) {
        // Check if review already exists
        if ($article['my_review_date']) {
            // Update existing review
            $stmt = $pdo->prepare("
                UPDATE reviews 
                SET rating = ?, recommendation = ?, comments = ?, reviewed_at = CURRENT_TIMESTAMP 
                WHERE article_id = ? AND reviewer_id = ?
            ");
            $stmt->execute([$rating, $recommendation, $comments, $article_id, $_SESSION['user_id']]);
        } else {
            // Create new review
            $stmt = $pdo->prepare("
                INSERT INTO reviews (article_id, reviewer_id, rating, recommendation, comments) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$article_id, $_SESSION['user_id'], $rating, $recommendation, $comments]);
        }
        
        header('Location: reviews.php?success=Review submitted successfully');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Review - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="./index.php">The Publish Club</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="./index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./articles.php">Articles</a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="./reviews.php">Reviews</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="./profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Submit Review</h1>
                <p class="hero-subtitle">Provide your feedback and recommendation</p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Review Form</h5>
                                <a href="./view_article.php?id=<?php echo $article_id; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                    View Article
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Article Preview -->
                            <div class="article-preview mb-4">
                                <img src="<?php echo $article['thumbnail_path'] ?? './assets/images/default-article.jpg'; ?>" 
                                     class="article-preview-image" alt="">
                                <div class="article-preview-content">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h5>
                                    <div class="article-preview-meta">
                                        <span>
                                            <i class="bi bi-person-circle"></i>
                                            <?php echo htmlspecialchars($article['author']); ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-calendar3"></i>
                                            <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="review-form">
                                <!-- Rating -->
                                <div class="mb-4">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-group">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" class="rating-btn <?php echo ($article['my_rating'] === $i) ? 'active' : ''; ?>" data-rating="<?php echo $i; ?>">
                                                <i class="bi bi-star-fill"></i>
                                                <?php echo $i; ?>
                                            </button>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" id="rating" value="<?php echo $article['my_rating'] ?? ''; ?>">
                                    </div>
                                </div>

                                <!-- Recommendation -->
                                <div class="mb-4">
                                    <label class="form-label">Recommendation</label>
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn <?php echo ($article['my_recommendation'] === 'approve') ? 'btn-success' : 'btn-outline-success'; ?>" data-recommendation="approve">
                                            <i class="bi bi-check-lg"></i>
                                            Approve
                                        </button>
                                        <button type="button" class="btn <?php echo ($article['my_recommendation'] === 'reject') ? 'btn-danger' : 'btn-outline-danger'; ?>" data-recommendation="reject">
                                            <i class="bi bi-x-lg"></i>
                                            Reject
                                        </button>
                                        <input type="hidden" name="recommendation" id="recommendation" value="<?php echo $article['my_recommendation'] ?? ''; ?>">
                                    </div>
                                </div>

                                <!-- Comments -->
                                <div class="mb-4">
                                    <label for="comments" class="form-label">Review Comments</label>
                                    <textarea class="form-control" id="comments" name="comments" rows="6" 
                                              placeholder="Provide detailed feedback about the article..."><?php echo htmlspecialchars($article['my_comments'] ?? ''); ?></textarea>
                                    <div class="form-text">
                                        Please provide constructive feedback to help the author improve their work.
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="./reviews.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i>
                                        Back to Reviews
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check2-circle"></i>
                                        Submit Review
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Rating buttons
            const ratingBtns = document.querySelectorAll('.rating-btn');
            const ratingInput = document.getElementById('rating');
            
            ratingBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const rating = this.dataset.rating;
                    ratingInput.value = rating;
                    
                    // Update active state
                    ratingBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Recommendation buttons
            const recommendationBtns = document.querySelectorAll('[data-recommendation]');
            const recommendationInput = document.getElementById('recommendation');
            
            recommendationBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const recommendation = this.dataset.recommendation;
                    recommendationInput.value = recommendation;
                    
                    // Update button states
                    if (recommendation === 'approve') {
                        this.classList.remove('btn-outline-success');
                        this.classList.add('btn-success');
                        recommendationBtns[1].classList.remove('btn-danger');
                        recommendationBtns[1].classList.add('btn-outline-danger');
                    } else {
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-danger');
                        recommendationBtns[0].classList.remove('btn-success');
                        recommendationBtns[0].classList.add('btn-outline-success');
                    }
                });
            });
            
            // Form validation
            const form = document.querySelector('.review-form');
            form.addEventListener('submit', function(e) {
                if (!ratingInput.value || !recommendationInput.value || !comments.value.trim()) {
                    e.preventDefault();
                    alert('Please fill in all required fields: rating, recommendation, and comments.');
                }
            });
        });
    </script>
</body>
</html>