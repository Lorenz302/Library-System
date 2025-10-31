<?php
// Start the session to access user data
session_start();

// Security check:
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

// *** NEW: Include the script to get our dashboard data ***
include '../backend/get_dashboard_data.php';

// A welcome message using the user's fullname from the session
$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BHM College Library</title>
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
                    <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="manage_requests.php">Manage Requests</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <header class="main-header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <section class="summary-cards">
                    
                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/task.png" alt="Requests Icon"/>
                        </div>
                        <div class="card-info">
                            <!-- *** UPDATED: Using the PHP variable now *** -->
                            <h2><?php echo $pending_requests_count; ?></h2>
                            <p>Pending Requests</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/books.png" alt="Books Icon"/>
                        </div>
                        <div class="card-info">
                             <!-- *** UPDATED: Using the PHP variable now *** -->
                            <h2><?php echo $total_books_count; ?></h2>
                            <p>Total Books</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/reading.png" alt="On Loan Icon"/>
                        </div>
                        <div class="card-info">
                             <!-- *** UPDATED: Using the PHP variable now *** -->
                            <h2><?php echo $books_on_loan_count; ?></h2>
                            <p>Books on Loan</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                             <img src="https://img.icons8.com/ios-filled/50/C81D14/user-group-man-man.png" alt="Users Icon"/>
                        </div>
                        <div class="card-info">
                             <!-- *** UPDATED: Using the PHP variable now *** -->
                            <h2><?php echo $total_users_count; ?></h2>
                            <p>Total Users</p>
                        </div>
                    </div>

                </section>
            </main>
        </div>
    </div>
</body>
</html>