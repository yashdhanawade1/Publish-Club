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

// Get quiz details
if (!isset($_GET['id'])) {
    header('Location: quiz_dashboard.php');
    exit();
}

$quiz_id = $_GET['id'];

// Handle quiz update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Handle thumbnail upload
        $thumbnail_path = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/quiz_thumbnails/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $thumbnail_path = $upload_dir . uniqid('quiz_') . '.' . $file_extension;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbnail_path);
            }
        }

        // Update quiz
        $stmt = $pdo->prepare("
            UPDATE quizzes 
            SET title = ?, description = ?, time_limit = ?, passing_score = ?, 
                status = ?, thumbnail_path = COALESCE(?, thumbnail_path)
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['time_limit'],
            $_POST['passing_score'],
            $_POST['status'],
            $thumbnail_path,
            $quiz_id
        ]);

        // Delete existing questions
        $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);

        // Insert updated questions and answers
        foreach ($_POST['questions'] as $order_num => $question) {
            if (empty($question['text'])) continue;

            $stmt = $pdo->prepare("
                INSERT INTO questions (quiz_id, question_text, question_type, points, order_num)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $quiz_id,
                $question['text'],
                $question['type'],
                $question['points'],
                $order_num + 1
            ]);
            $question_id = $pdo->lastInsertId();

            foreach ($question['options'] as $option_num => $option) {
                if (empty($option['text'])) continue;

                $stmt = $pdo->prepare("
                    INSERT INTO answer_options (question_id, option_text, is_correct, order_num)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $question_id,
                    $option['text'],
                    isset($option['is_correct']) ? 1 : 0,
                    $option_num + 1
                ]);
            }
        }

        $pdo->commit();
        $success = "Quiz updated successfully!";
        header("Location: quiz_dashboard.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating quiz: " . $e->getMessage();
    }
}

// Fetch quiz data
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quiz_dashboard.php');
    exit();
}

// Fetch questions and options
$stmt = $pdo->prepare("
    SELECT q.*, GROUP_CONCAT(
        JSON_OBJECT(
            'id', ao.id,
            'text', ao.option_text,
            'is_correct', ao.is_correct,
            'order_num', ao.order_num
        )
    ) as options
    FROM questions q
    LEFT JOIN answer_options ao ON q.id = ao.question_id
    WHERE q.quiz_id = ?
    GROUP BY q.id
    ORDER BY q.order_num
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .thumbnail-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
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
                        <a class="nav-link" href="admin.php">Article Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quiz_dashboard.php">Quiz Management</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Edit Quiz</h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" id="quizForm" enctype="multipart/form-data">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Quiz Title</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thumbnail" class="form-label">Quiz Thumbnail</label>
                                <?php if ($quiz['thumbnail_path']): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($quiz['thumbnail_path']); ?>" 
                                             class="thumbnail-preview" alt="Quiz thumbnail">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="thumbnail" name="thumbnail" 
                                       accept="image/*">
                                <small class="text-muted">Leave empty to keep current thumbnail</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft" <?php echo $quiz['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $quiz['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo $quiz['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" 
                                       value="<?php echo $quiz['time_limit']; ?>" min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passing_score" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                       value="<?php echo $quiz['passing_score']; ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="questionsContainer">
                <!-- Questions will be loaded here -->
            </div>

            <div class="mb-4">
                <button type="button" class="btn btn-outline-primary" onclick="addQuestion()">
                    <i class="bi bi-plus-circle"></i> Add Question
                </button>
            </div>

            <div class="d-flex justify-content-between">
                <a href="quiz_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let questionCount = <?php echo count($questions); ?>;
    const existingQuestions = <?php echo json_encode($questions); ?>;

    function addQuestion(questionData = null) {
        const container = document.getElementById('questionsContainer');
        const questionDiv = document.createElement('div');
        questionDiv.className = 'card mb-4';
        questionDiv.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Question ${questionCount + 1}</h5>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQuestion(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Question Text</label>
                    <textarea class="form-control" name="questions[${questionCount}][text]" required>${questionData ? questionData.question_text : ''}</textarea>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Question Type</label>
                        <select class="form-select" name="questions[${questionCount}][type]" 
                                onchange="toggleOptions(this, ${questionCount})">
                            <option value="multiple_choice" ${questionData && questionData.question_type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                            <option value="true_false" ${questionData && questionData.question_type === 'true_false' ? 'selected' : ''}>True/False</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Points</label>
                        <input type="number" class="form-control" name="questions[${questionCount}][points]" 
                               value="${questionData ? questionData.points : '1'}" min="1">
                    </div>
                </div>
                <div class="options-container" id="options-${questionCount}">
                    <!-- Options will be added here -->
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" 
                        onclick="addOption(${questionCount})">
                    <i class="bi bi-plus"></i> Add Option
                </button>
            </div>
        `;
        container.appendChild(questionDiv);
        
        const select = questionDiv.querySelector('select');
        if (questionData) {
            const options = JSON.parse(questionData.options);
            toggleOptions(select, questionCount, options);
        } else {
            toggleOptions(select, questionCount);
        }
        
        questionCount++;
    }

    function toggleOptions(select, questionNum, existingOptions = null) {
        const container = document.getElementById(`options-${questionNum}`);
        container.innerHTML = '';
        
        if (select.value === 'true_false') {
            addTrueFalseOptions(questionNum, existingOptions);
        } else {
            if (existingOptions) {
                existingOptions.forEach(option => addOption(questionNum, option));
            } else {
                addOption(questionNum);
                addOption(questionNum);
            }
        }
    }

    function addTrueFalseOptions(questionNum, existingOptions = null) {
        const container = document.getElementById(`options-${questionNum}`);
        container.innerHTML = `
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questions[${questionNum}][options][0][is_correct]" 
                           ${existingOptions && existingOptions[0].is_correct ? 'checked' : ''} required>
                    <input type="text" class="form-control" name="questions[${questionNum}][options][0][text]" 
                           value="True" readonly>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questions[${questionNum}][options][1][is_correct]"
                           ${existingOptions && existingOptions[1].is_correct ? 'checked' : ''} required>
                    <input type="text" class="form-control" name="questions[${questionNum}][options][1][text]" 
                           value="False" readonly>
                </div>
            </div>
        `;
    }

    function addOption(questionNum, existingOption = null) {
        const container = document.getElementById(`options-${questionNum}`);
        const optionCount = container.children.length;
        const optionDiv = document.createElement('div');
        optionDiv.className = 'mb-3';
        optionDiv.innerHTML = `
            <div class="input-group">
                <div class="input-group-text">
                    <input class="form-check-input" type="radio" 
                           name="questions[${questionNum}][options][${optionCount}][is_correct]"
                           ${existingOption && existingOption.is_correct ? 'checked' : ''}>
                </div>
                <input type="text" class="form-control" 
                       name="questions[${questionNum}][options][${optionCount}][text]" 
                       value="${existingOption ? existingOption.text : ''}"
                       placeholder="Option text" required>
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
        container.appendChild(optionDiv);
    }

    function removeQuestion(button) {
        button.closest('.card').remove();
    }

    function removeOption(button) {
        button.closest('.mb-3').remove();
    }

    // Load existing questions
    existingQuestions.forEach(question => addQuestion(question));
    if (questionCount === 0) {
        addQuestion();
    }
    </script>
</body>
</html>
