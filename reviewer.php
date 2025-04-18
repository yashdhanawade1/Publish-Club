<?php
require_once 'config/config.php';

// Check if user is reviewer
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reviewer') {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $article_id = $_POST['article_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    
    try {
        $pdo->beginTransaction();
        
        // Add review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (article_id, reviewer_id, rating, comments, reviewed_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            comments = VALUES(comments),
            reviewed_at = NOW()
        ");
        $stmt->execute([$article_id, $_SESSION['user_id'], $rating, $comments]);
        
        $pdo->commit();
        $_SESSION['success'] = "Review submitted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error submitting review: " . $e->getMessage();
    }
    
    header('Location: reviewer.php');
    exit();
}

// Get assigned articles
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.username as author,
        r.rating,
        r.comments,
        r.reviewed_at
    FROM articles a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id AND r.reviewer_id = ?
    WHERE a.reviewer_id = ? AND a.status = 'under_review'
    ORDER BY a.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$articles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviewer Dashboard - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .pdf-viewer {
            width: 100%;
            height: 800px;
            border: none;
        }
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            gap: 0.5rem;
        }
        .rating-input input {
            display: none;
        }
        .rating-input label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ddd;
        }
        .rating-input label:hover,
        .rating-input label:hover ~ label,
        .rating-input input:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">The Publish Club</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reviewer.php">Reviewer Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Reviewer Dashboard</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($articles)): ?>
            <div class="alert alert-info">
                No articles are currently assigned to you for review.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($article['title']); ?></h5>
                                <button class="btn btn-primary btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#articleModal<?php echo $article['id']; ?>">
                                    View Article
                                </button>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    By <?php echo htmlspecialchars($article['author']); ?>
                                </p>
                                
                                <?php if ($article['description']): ?>
                                    <p><?php echo nl2br(htmlspecialchars($article['description'])); ?></p>
                                <?php endif; ?>

                                <?php if ($article['reviewed_at']): ?>
                                    <div class="alert alert-success">
                                        <h6>Your Review</h6>
                                        <div class="mb-2">
                                            Rating: <?php echo $article['rating']; ?>/5
                                        </div>
                                        <div>
                                            Comments: <?php echo nl2br(htmlspecialchars($article['comments'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Article Modal -->
                        <div class="modal fade" id="articleModal<?php echo $article['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Review Article</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <iframe src="uploads/<?php echo urlencode($article['file_path']); ?>" 
                                                class="pdf-viewer mb-4"></iframe>
                                        
                                        <form method="post" class="mt-4">
                                            <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Rating</label>
                                                <div class="rating-input">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <input type="radio" 
                                                               name="rating" 
                                                               value="<?php echo $i; ?>" 
                                                               id="star<?php echo $article['id'] . $i; ?>"
                                                               <?php echo ($article['rating'] == $i) ? 'checked' : ''; ?>
                                                               required>
                                                        <label for="star<?php echo $article['id'] . $i; ?>">★</label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Comments</label>
                                                <textarea name="comments" 
                                                          class="form-control" 
                                                          rows="5" 
                                                          required><?php echo htmlspecialchars($article['comments'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <button type="submit" 
                                                    name="submit_review" 
                                                    class="btn btn-primary">
                                                Submit Review
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
