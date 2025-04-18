<?php
require_once 'config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();
$error = '';
$success = '';

// Fetch categories for the dropdown
$stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['article']) && $_FILES['article']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['article'];
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
        
        if (!$category_id) {
            $error = 'Please select a category';
        } else {
            // Handle thumbnail upload
            $thumbnail_path = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbnail = $_FILES['thumbnail'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($thumbnail['type'], $allowed_types)) {
                    $error = 'Thumbnail must be a JPG, PNG, or GIF image';
                } else {
                    // Create uploads and thumbnails directories if they don't exist
                    if (!file_exists(UPLOAD_DIR)) {
                        if (!mkdir(UPLOAD_DIR, 0777, true)) {
                            $error = 'Failed to create uploads directory';
                        }
                    }
                    
                    $thumbnail_dir = UPLOAD_DIR . 'thumbnails/';
                    if (!file_exists($thumbnail_dir)) {
                        if (!mkdir($thumbnail_dir, 0777, true)) {
                            $error = 'Failed to create thumbnails directory';
                        }
                    }
                    
                    if (!$error) {
                        $thumbnail_filename = uniqid() . '_' . basename($thumbnail['name']);
                        $thumbnail_path = 'thumbnails/' . $thumbnail_filename;
                        
                        if (!move_uploaded_file($thumbnail['tmp_name'], UPLOAD_DIR . $thumbnail_path)) {
                            $error = 'Failed to upload thumbnail: ' . error_get_last()['message'];
                        }
                    }
                }
            }
            
            // Only proceed with article upload if no errors so far
            if (!$error) {
                // Validate file type
                if (!in_array($file['type'], ALLOWED_TYPES)) {
                    $error = 'Only PDF files are allowed';
                } else if ($file['size'] > MAX_FILE_SIZE) {
                    $error = 'File size must be less than ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                } else {
                    // Create uploads directory if it doesn't exist (double-check)
                    if (!file_exists(UPLOAD_DIR)) {
                        if (!mkdir(UPLOAD_DIR, 0777, true)) {
                            $error = 'Failed to create uploads directory';
                        }
                    }
                    
                    if (!$error) {
                        $filename = uniqid() . '_' . basename($file['name']);
                        
                        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO articles (
                                        title, description, user_id, category_id, 
                                        file_path, thumbnail_path
                                    ) VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $title, $description, $_SESSION['user_id'], 
                                    $category_id, $filename, $thumbnail_path
                                ]);
                                $success = 'Article uploaded successfully';
                            } catch (PDOException $e) {
                                $error = 'Failed to save article details: ' . $e->getMessage();
                                // Clean up uploaded files if database insert fails
                                unlink(UPLOAD_DIR . $filename);
                                if ($thumbnail_path) {
                                    unlink(UPLOAD_DIR . $thumbnail_path);
                                }
                            }
                        } else {
                            $error = 'Failed to upload file: ' . error_get_last()['message'];
                            if ($thumbnail_path) {
                                unlink(UPLOAD_DIR . $thumbnail_path);
                            }
                        }
                    }
                }
            }
        }
    } else {
        $error = 'No file uploaded or file upload failed';
    }
}

// Fetch user's articles with category info
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.name as category_name,
        COUNT(DISTINCT r.id) as review_count,
        AVG(r.rating) as avg_rating
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    LEFT JOIN reviews r ON a.id = r.article_id
    WHERE a.user_id = ?
    GROUP BY a.id, c.name
    ORDER BY a.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$articles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Articles - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .article-card {
            height: 100%;
            transition: transform 0.2s;
        }
        .article-card:hover {
            transform: translateY(-5px);
        }
        .thumbnail-preview {
            max-width: 200px;
            max-height: 200px;
            width: auto;
            height: auto;
            display: none;
            margin-top: 10px;
        }
        .status-badge {
            text-transform: capitalize;
        }
    </style>
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
                        <a class="nav-link" href="published.php">Published Articles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="articles.php">My Articles</a>
                    </li>
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
                <h1 class="hero-title">My Articles</h1>
                <p class="hero-subtitle">Submit and manage your articles</p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Submit New Article</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category_id" required>
                                        <option value="">Select a category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="article" class="form-label">PDF File</label>
                                    <input type="file" class="form-control" id="article" name="article" accept=".pdf" required>
                                    <div class="form-text">Only PDF files are accepted.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="thumbnail" class="form-label">Thumbnail Image</label>
                                    <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                    <img id="thumbnailPreview" class="thumbnail-preview">
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Submit Article</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <?php if (empty($articles)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            You haven't submitted any articles yet.
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($articles as $article): ?>
                                <div class="col-md-6">
                                    <div class="card article-card h-100">
                                        <?php if ($article['thumbnail_path']): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($article['title']); ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?php echo htmlspecialchars($article['category_name']); ?>
                                            </h6>
                                            <?php if ($article['description']): ?>
                                                <p class="card-text">
                                                    <?php echo nl2br(htmlspecialchars(substr($article['description'], 0, 150))); ?>...
                                                </p>
                                            <?php endif; ?>
                                            <div class="mb-2">
                                                <span class="badge status-badge bg-<?php 
                                                    echo match($article['status']) {
                                                        'pending' => 'secondary',
                                                        'under_review' => 'info',
                                                        'approved' => 'success',
                                                        'published' => 'primary',
                                                        'rejected' => 'danger'
                                                    };
                                                ?>">
                                                    <?php echo htmlspecialchars($article['status']); ?>
                                                </span>
                                            </div>
                                            <?php if ($article['review_count'] > 0): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <?php echo $article['review_count']; ?> reviews
                                                        (<?php echo number_format($article['avg_rating'], 1); ?>/5)
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-3">
                                                <a href="view_article.php?id=<?php echo $article['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                        <div class="card-footer text-muted">
                                            Submitted <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                        <li><a href="published.php">Published Articles</a></li>
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

    <script>
        // Preview thumbnail image
        document.getElementById('thumbnail').addEventListener('change', function(e) {
            const preview = document.getElementById('thumbnailPreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
