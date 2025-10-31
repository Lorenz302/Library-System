<?php
session_start();
include '../backend/db_connect.php';

// Security: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

// 1. Get the book ID from the URL and validate it
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_books.php?error=invalid_id");
    exit;
}
$book_id = (int)$_GET['id'];

// 2. Fetch the book's current data from the database
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If no book is found with that ID, redirect
    header("Location: manage_books.php?error=not_found");
    exit;
}
$book = $result->fetch_assoc();
$stmt->close();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .form-section {
            background-color: #34495e;
            padding: 30px;
            border-radius: 8px;
            max-width: 800px;
            margin: 0 auto;
        }
        .form-section h2 {
            margin-bottom: 25px;
            text-align: center;
            color: #ecf0f1;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #bdc3c7;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #2c3e50;
            color: #ecf0f1;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        .form-actions .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: #2ecc71;
            color: white;
        }
        .btn-secondary {
            background-color: #7f8c8d;
            color: white;
        }
        .current-image {
            display: block;
            max-width: 150px;
            height: auto;
            border-radius: 5px;
            margin-top: 10px;
            border: 2px solid #444;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="LIBRARY_LOGO.png" alt="Logo" class="sidebar-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_requests.php">Manage Requests</a></li>
                    <li><a href="manage_books.php" class="active">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Edit Book Details</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <section class="form-section">
                    <h2>Editing: <?php echo htmlspecialchars($book['book_name']); ?></h2>
                    <form action="../backend/update_book.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                        
                        <div class="form-group">
                            <label for="book_name">Book Title</label>
                            <input type="text" id="book_name" name="book_name" value="<?php echo htmlspecialchars($book['book_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" min="1000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($book['publication_year']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="total_copies">Total Copies</label>
                            <input type="number" id="total_copies" name="total_copies" min="0" value="<?php echo htmlspecialchars($book['total_copies']); ?>" required>
                            <small style="color: #bdc3c7;">Adjusting this will automatically update available copies. Note: You cannot set total copies lower than the number of books currently on loan.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="book_description">Description</label>
                            <textarea id="book_description" name="book_description"><?php echo htmlspecialchars($book['book_description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="book_image">Book Cover Image (Optional)</label>
                            <input type="file" id="book_image" name="book_image" accept="image/jpeg, image/png, image/gif">
                            <?php if (!empty($book['book_image_path'])): ?>
                                <p style="color: #bdc3c7; margin-top: 10px;">Current Image:</p>
                                <img src="<?php echo htmlspecialchars($book['book_image_path']); ?>" alt="Current Cover" class="current-image">
                                <input type="hidden" name="existing_image_path" value="<?php echo htmlspecialchars($book['book_image_path']); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <a href="manage_books.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>
</body>
</html>