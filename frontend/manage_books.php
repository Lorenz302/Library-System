<?php
session_start();

// Security check: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

include '../backend/db_connect.php';

// Fetch all books from the database, ordered by name
$sql_books = "SELECT * FROM books ORDER BY book_name ASC";
$all_books = $conn->query($sql_books);

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Admin Panel</title>
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
                <h1>Manage Books</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <div class="action-bar">
                    <a href="add_book.php" class="add-new-btn">+ Add New Book</a>
                </div>

                <section class="content-section">
                    <h2>Book Collection</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Total Copies</th>
                                    <th>Available</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_books->num_rows > 0): ?>
                                    <?php while($book = $all_books->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['book_id']); ?></td>
                                            <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                                            <td><?php echo htmlspecialchars($book['total_copies']); ?></td>
                                            <td><?php echo htmlspecialchars($book['available_copies']); ?></td>
                                            <td class="action-links">
                                                <a href="edit_book.php?id=<?php echo $book['book_id']; ?>" class="edit-link">Edit</a>
                                                <form action="../backend/book_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="delete-btn">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">No books found in the library.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>