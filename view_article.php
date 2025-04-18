<?php
require_once 'config/config.php';

// Get article ID from URL
$article_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$article_id) {
    header('Location: index.php');
    exit();
}

$pdo = db_connect();

// Fetch article details with category and author info
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.name as category_name,
        c.slug as category_slug,
        u.username as author,
        COUNT(DISTINCT r.id) as review_count,
        AVG(r.rating) as avg_rating
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id
    WHERE a.id = ? AND (a.status = 'published' OR a.user_id = ?)
    GROUP BY a.id, c.name, c.slug, u.username
");

$stmt->execute([$article_id, is_authenticated() ? $_SESSION['user_id'] : 0]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit();
}

// Fetch reviews if the article is published
$reviews = [];
if ($article['status'] === 'published') {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            u.username as reviewer
        FROM reviews r
        JOIN users u ON r.reviewer_id = u.id
        WHERE r.article_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$article_id]);
    $reviews = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    <style>
        .article-header {
            background: #f8f9fa;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        .article-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .article-category {
            text-decoration: none;
            color: #0d6efd;
        }
        .article-category:hover {
            text-decoration: underline;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        #pdfViewer {
            width: 100%;
            height: 800px;
            border: 1px solid #dee2e6;
            margin: 2rem 0;
        }
        .review-card {
            margin-bottom: 1rem;
        }
        .thumbnail-container {
            max-width: 300px;
            margin-bottom: 2rem;
        }
        .thumbnail-container img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                        <a class="nav-link" href="published.php">Published Articles</a>
                    </li>
                    <?php if (is_authenticated()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="articles.php">My Articles</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (is_authenticated()): ?>
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

    <div class="article-header">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1 class="display-4"><?php echo htmlspecialchars($article['title']); ?></h1>
                    <div class="article-meta mt-3">
                        <a href="published.php?category=<?php echo urlencode($article['category_slug']); ?>" class="article-category">
                            <?php echo htmlspecialchars($article['category_name']); ?>
                        </a>
                        <span class="mx-2">•</span>
                        <span>By <?php echo htmlspecialchars($article['author']); ?></span>
                        <?php if ($article['published_at']): ?>
                            <span class="mx-2">•</span>
                            <span>Published <?php echo date('M j, Y', strtotime($article['published_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($article['review_count'] > 0): ?>
                        <div class="rating-stars">
                            <?php
                            $rating = round($article['avg_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating ? '★' : '☆';
                            }
                            ?>
                            <span class="ms-2">(<?php echo $article['review_count']; ?> reviews)</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <?php if ($article['description']): ?>
                    <div class="article-description mb-4">
                        <h5>Abstract</h5>
                        <p class="lead"><?php echo nl2br(htmlspecialchars($article['description'])); ?></p>
                    </div>
                <?php endif; ?>

                <div id="pdfViewer"></div>

                <?php if (!empty($reviews)): ?>
                    <div class="reviews-section mt-5">
                        <h3>Reviews</h3>
                        <?php foreach ($reviews as $review): ?>
                            <div class="card review-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            <?php echo htmlspecialchars($review['reviewer']); ?>
                                        </h6>
                                        <div class="rating-stars">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $review['rating'] ? '★' : '☆';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($review['feedback'])); ?></p>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <?php if ($article['thumbnail_path']): ?>
                    <div class="thumbnail-container">
                        <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>" 
                             class="img-fluid">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';

        // Load and display the PDF
        const loadPDF = async () => {
            const pdfPath = 'uploads/<?php echo htmlspecialchars($article['file_path']); ?>';
            try {
                const loadingTask = pdfjsLib.getDocument(pdfPath);
                const pdf = await loadingTask.promise;
                
                // Get the first page
                const page = await pdf.getPage(1);
                const scale = 1.5;
                const viewport = page.getViewport({ scale });

                // Prepare canvas for rendering
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                // Render PDF page
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                await page.render(renderContext);
                
                // Display the canvas
                document.getElementById('pdfViewer').appendChild(canvas);
            } catch (error) {
                console.error('Error loading PDF:', error);
                document.getElementById('pdfViewer').innerHTML = '<div class="alert alert-danger">Error loading PDF. Please try again later.</div>';
            }
        };

        loadPDF();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
