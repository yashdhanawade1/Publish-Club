<?php
require_once './config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

// Handle article management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_reviewer'])) {
        $article_id = $_POST['article_id'];
        $reviewer_id = $_POST['reviewer_id'];
        
        $stmt = $pdo->prepare("UPDATE articles SET reviewer_id = ?, status = 'under_review' WHERE id = ?");
        $stmt->execute([$reviewer_id, $article_id]);
        header('Location: admin.php?success=Reviewer assigned successfully');
        exit();
    }
    
    if (isset($_POST['update_status'])) {
        $article_id = $_POST['article_id'];
        $new_status = $_POST['new_status'];
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("UPDATE articles SET status = ?, published_at = ? WHERE id = ?");
        $stmt->execute([$new_status, $new_status === 'published' ? $current_time : null, $article_id]);
        header('Location: admin.php?success=Article status updated successfully');
        exit();
    }
}

// Get all reviewers
$reviewers = $pdo->query("SELECT id, username FROM users WHERE role = 'reviewer'")->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM articles) as total_articles,
        (SELECT COUNT(*) FROM reviews) as total_reviews
")->fetch(PDO::FETCH_ASSOC);

// Get recent activities
$recent_activities = $pdo->query("
    (SELECT 
        'article' as type,
        a.title as title,
        u.username as user,
        a.created_at as date,
        a.status as status
    FROM articles a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5)
    UNION ALL
    (SELECT 
        'review' as type,
        art.title as title,
        u.username as user,
        r.reviewed_at as date,
        r.recommendation as status
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    JOIN articles art ON r.article_id = art.id
    ORDER BY r.reviewed_at DESC
    LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics by role
$user_stats = $pdo->query("
    SELECT role, COUNT(*) as count
    FROM users
    GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);

// Get article statistics by status
$article_stats = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM articles
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Get all articles with their details
$articles = $pdo->query("
    SELECT 
        a.id,
        a.title,
        a.status,
        a.created_at,
        u.username as author,
        r.username as reviewer,
        COUNT(rv.id) as review_count,
        GROUP_CONCAT(
            CASE 
                WHEN rv.recommendation = 'approve' THEN 'approve'
                WHEN rv.recommendation = 'reject' THEN 'reject'
            END
        ) as recommendations
    FROM articles a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN users r ON a.reviewer_id = r.id
    LEFT JOIN reviews rv ON a.id = rv.article_id
    GROUP BY a.id, a.title, a.status, a.created_at, u.username, r.username
    ORDER BY a.created_at DESC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Publish Club</title>
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
                    <?php if (isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'reviewer'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="./reviews.php">Reviews</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link active" href="./admin.php">Admin Panel</a>
                            </li>
                        <?php endif; ?>
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
                <h1 class="hero-title">Admin Dashboard</h1>
                <p class="hero-subtitle">Manage and monitor The Publish Club platform</p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-people fs-1 text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-1"><?php echo $stats['total_users']; ?></h3>
                                    <div class="text-muted">Total Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-file-text fs-1 text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-1"><?php echo $stats['total_articles']; ?></h3>
                                    <div class="text-muted">Articles</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-star fs-1 text-warning"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-1"><?php echo $stats['total_reviews']; ?></h3>
                                    <div class="text-muted">Reviews</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Title</th>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $activity['type'] === 'article' ? 'primary' : 'success';
                                                    ?>">
                                                        <?php echo ucfirst($activity['type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person-circle me-2"></i>
                                                        <?php echo htmlspecialchars($activity['user']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($activity['status']) {
                                                            'published' => 'success',
                                                            'under_review' => 'warning',
                                                            'rejected' => 'danger',
                                                            'pending' => 'secondary',
                                                            'approve' => 'success',
                                                            default => 'primary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                                    </small>
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
                            <h5 class="card-title mb-0">User Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($user_stats as $stat): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-person-badge me-2 text-<?php 
                                                    echo match($stat['role']) {
                                                        'admin' => 'danger',
                                                        'reviewer' => 'warning',
                                                        default => 'primary'
                                                    };
                                                ?>"></i>
                                                <span><?php echo ucfirst($stat['role']); ?>s</span>
                                            </div>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $stat['count']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Article Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($article_stats as $stat): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-text me-2 text-<?php 
                                                    echo match($stat['status']) {
                                                        'published' => 'success',
                                                        'under_review' => 'warning',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>"></i>
                                                <span><?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?></span>
                                            </div>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $stat['count']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <div class="card-header-actions">
                                <h5 class="card-title mb-0">Article Management</h5>
                                <div class="ms-auto">
                                    <input type="text" class="form-control" id="articleSearch" placeholder="Search articles...">
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($_GET['success']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (empty($articles)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <h4>No Articles Found</h4>
                                    <p>There are no articles in the system yet. Articles will appear here once users start submitting them.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Author</th>
                                                <th>Status</th>
                                                <th>Reviewer</th>
                                                <th>Reviews</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($articles as $article): ?>
                                                <tr>
                                                    <td>
                                                        <a href="./view_article.php?id=<?php echo $article['id']; ?>" class="article-title-link">
                                                            <?php echo htmlspecialchars($article['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-person-circle me-2"></i>
                                                            <?php echo htmlspecialchars($article['author']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="article-status">
                                                            <span class="badge status-<?php echo $article['status']; ?>">
                                                                <?php echo ucfirst($article['status']); ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($article['reviewer']): ?>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-person-badge me-2"></i>
                                                                <?php echo htmlspecialchars($article['reviewer']); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-flex align-items-center">
                                                                <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                                                <select name="reviewer_id" class="form-select form-select-sm reviewer-select me-2" required>
                                                                    <option value="">Select Reviewer</option>
                                                                    <?php foreach ($reviewers as $reviewer): ?>
                                                                        <option value="<?php echo $reviewer['id']; ?>">
                                                                            <?php echo htmlspecialchars($reviewer['username']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <button type="submit" name="assign_reviewer" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-person-plus"></i>
                                                                    Assign
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="review-count">
                                                            <?php if ($article['review_count'] > 0): ?>
                                                                <span class="badge bg-secondary">
                                                                    <?php echo $article['review_count']; ?> reviews
                                                                </span>
                                                                <?php
                                                                $recommendations = explode(',', $article['recommendations']);
                                                                $approves = array_count_values($recommendations)['approve'] ?? 0;
                                                                $rejects = array_count_values($recommendations)['reject'] ?? 0;
                                                                ?>
                                                                <div class="review-stats">
                                                                    <span>
                                                                        <i class="bi bi-check-circle text-success"></i>
                                                                        <?php echo $approves; ?>
                                                                    </span>
                                                                    <span>
                                                                        <i class="bi bi-x-circle text-danger"></i>
                                                                        <?php echo $rejects; ?>
                                                                    </span>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">
                                                                    <i class="bi bi-clock me-1"></i>
                                                                    Awaiting Reviews
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-calendar3 me-2"></i>
                                                            <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <?php if ($article['status'] === 'approved'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="published">
                                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                                        <i class="bi bi-globe"></i>
                                                                        Publish
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($article['status'] === 'rejected'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="pending">
                                                                    <button type="submit" name="update_status" class="btn btn-sm btn-warning">
                                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                                        Reset
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($article['status'] === 'under_review' && $article['review_count'] > 0): ?>
                                                                <div class="btn-group">
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="approved">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-outline-success">
                                                                            <i class="bi bi-check-lg"></i>
                                                                            Approve
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="rejected">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-outline-danger">
                                                                            <i class="bi bi-x-lg"></i>
                                                                            Reject
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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
                        <li><a href="./articles.php">Articles</a></li>
                        <li><a href="./about.php">About Us</a></li>
                        <li><a href="./contact.php">Contact</a></li>
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
        document.addEventListener('DOMContentLoaded', function() {
            const articleSearch = document.getElementById('articleSearch');
            const articleTable = document.querySelector('.table');
            
            if (articleSearch && articleTable) {
                articleSearch.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const rows = articleTable.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const title = row.querySelector('td:first-child').textContent.toLowerCase();
                        const author = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const status = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                        const reviewer = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                        
                        const matches = title.includes(searchTerm) || 
                                      author.includes(searchTerm) || 
                                      status.includes(searchTerm) ||
                                      reviewer.includes(searchTerm);
                        
                        row.style.display = matches ? '' : 'none';
                    });
                    
                    // Show/hide empty state message
                    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                    const emptyState = document.querySelector('.empty-state');
                    if (emptyState) {
                        if (visibleRows.length === 0) {
                            if (!document.querySelector('.search-empty-state')) {
                                const searchEmptyState = document.createElement('div');
                                searchEmptyState.className = 'empty-state search-empty-state';
                                searchEmptyState.innerHTML = `
                                    <i class="bi bi-search"></i>
                                    <h4>No Results Found</h4>
                                    <p>No articles match your search criteria. Try adjusting your search terms.</p>
                                `;
                                emptyState.parentNode.insertBefore(searchEmptyState, emptyState.nextSibling);
                            }
                            document.querySelector('.search-empty-state').style.display = 'block';
                            document.querySelector('.table-responsive').style.display = 'none';
                        } else {
                            const searchEmptyState = document.querySelector('.search-empty-state');
                            if (searchEmptyState) {
                                searchEmptyState.style.display = 'none';
                            }
                            document.querySelector('.table-responsive').style.display = 'block';
                        }
                    }
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>