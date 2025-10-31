<?php
session_start();

// Security check: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}
$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Book - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
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

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <header class="main-header">
                <h1>Add a New Book</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <div class="form-container">
                    <form action="../backend/book_handler.php" method="POST" enctype="multipart/form-data">
                        <!-- Hidden input to specify the action -->
                        <input type="hidden" name="action" value="add">

                        <div class="form-group">
                            <label for="book_name">Book Title</label>
                            <input type="text" id="book_name" name="book_name" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" required>
                        </div>
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" min="1000" max="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="total_copies">Total Copies</label>
                            <input type="number" id="total_copies" name="total_copies" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="book_description">Description</label>
                            <textarea id="book_description" name="book_description" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="book_image">Book Cover Image</label>
                            <input type="file" id="book_image" name="book_image" accept="image/jpeg, image/png, image/gif">
                        </div>

                        <button type="submit" class="submit-btn">Add Book</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>