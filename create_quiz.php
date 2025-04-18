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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert quiz
        $stmt = $pdo->prepare("
            INSERT INTO quizzes (title, description, time_limit, passing_score, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['time_limit'],
            $_POST['passing_score'],
            'draft'
        ]);
        $quiz_id = $pdo->lastInsertId();

        // Insert questions and answers
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

            // Insert answer options
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
        $success = "Quiz created successfully!";
        header("Location: quiz_dashboard.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating quiz: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                <h2>Create New Quiz</h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" id="quizForm">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Quiz Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passing_score" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                       min="0" max="100" value="60">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="questionsContainer">
                <!-- Questions will be added here dynamically -->
            </div>

            <div class="mb-4">
                <button type="button" class="btn btn-outline-primary" onclick="addQuestion()">
                    <i class="bi bi-plus-circle"></i> Add Question
                </button>
            </div>

            <div class="d-flex justify-content-between">
                <a href="quiz_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Quiz</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let questionCount = 0;

    function addQuestion() {
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
                    <textarea class="form-control" name="questions[${questionCount}][text]" required></textarea>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Question Type</label>
                        <select class="form-select" name="questions[${questionCount}][type]" 
                                onchange="toggleOptions(this, ${questionCount})">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Points</label>
                        <input type="number" class="form-control" name="questions[${questionCount}][points]" 
                               value="1" min="1">
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
        
        // Add initial options
        const select = questionDiv.querySelector('select');
        toggleOptions(select, questionCount);
        
        questionCount++;
    }

    function toggleOptions(select, questionNum) {
        const container = document.getElementById(`options-${questionNum}`);
        container.innerHTML = '';
        
        if (select.value === 'true_false') {
            addTrueFalseOptions(questionNum);
        } else {
            addOption(questionNum);
            addOption(questionNum);
        }
    }

    function addTrueFalseOptions(questionNum) {
        const container = document.getElementById(`options-${questionNum}`);
        container.innerHTML = `
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questions[${questionNum}][options][0][is_correct]" required>
                    <input type="text" class="form-control" name="questions[${questionNum}][options][0][text]" 
                           value="True" readonly>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questions[${questionNum}][options][1][is_correct]" required>
                    <input type="text" class="form-control" name="questions[${questionNum}][options][1][text]" 
                           value="False" readonly>
                </div>
            </div>
        `;
    }

    function addOption(questionNum) {
        const container = document.getElementById(`options-${questionNum}`);
        const optionCount = container.children.length;
        const optionDiv = document.createElement('div');
        optionDiv.className = 'mb-3';
        optionDiv.innerHTML = `
            <div class="input-group">
                <div class="input-group-text">
                    <input class="form-check-input" type="radio" 
                           name="questions[${questionNum}][options][${optionCount}][is_correct]">
                </div>
                <input type="text" class="form-control" 
                       name="questions[${questionNum}][options][${optionCount}][text]" 
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

    // Add first question automatically
    addQuestion();
    </script>
</body>
</html>
