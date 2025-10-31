<?php
// Start the session to access user data
session_start();

// Security check: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

// Include the database connection
include '../backend/db_connect.php';

// --- START: Filtering, Sorting, and Pagination Logic ---

$search_term = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// --- Query to fetch users ---
$base_query = "FROM users";
$conditions = [];
$params = [];
$types = '';

// We must not show the current logged-in librarian in the list to prevent self-lockout
// <<< FIXED: Changed 'id' to 'user_id'
$conditions[] = "user_id != ?"; 
$params[] = $_SESSION['user_id'];
$types .= 'i';

if (!empty($search_term)) {
    $conditions[] = "(fullname LIKE ? OR id_number LIKE ? OR email LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params = array_merge($params, [$search_like, $search_like, $search_like]);
    $types .= 'sss';
}
if ($filter_role !== 'all') {
    $conditions[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}
if ($filter_status !== 'all') {
    // Assuming you add a 'status' column to your users table (e.g., 'Active', 'Inactive')
    // If you don't have this column, you can remove this filter block.
    // For now, I'll assume you will add it.
    $conditions[] = "status = ?"; 
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// --- Count total results for pagination ---
$count_sql = "SELECT COUNT(*) $base_query $where_clause";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);

// --- Fetch the data for the current page ---
// <<< FIXED: Changed 'id' to 'user_id' in the SELECT list
$data_sql = "SELECT user_id, id_number, fullname, email, role, status $base_query $where_clause ORDER BY fullname ASC LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($data_sql);

$params_with_pagination = $params;
$params_with_pagination[] = $results_per_page;
$params_with_pagination[] = $offset;
$types_with_pagination = $types . 'ii';

$stmt_data->bind_param($types_with_pagination, ...$params_with_pagination);
$stmt_data->execute();
$all_users = $stmt_data->get_result();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* Styles are copied from manage_requests.php for consistency */
        .filter-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
        .filter-form input[type="text"], .filter-form select, .filter-form button { padding: 8px 12px; border-radius: 5px; border: 1px solid #444; background-color: #2c3e50; color: #ecf0f1; font-size: 14px; }
        .filter-form input[type="text"] { flex-grow: 1; }
        .filter-form button { background-color: #e74c3c; border-color: #e74c3c; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .filter-form button:hover { background-color: #c0392b; }
        .pagination { margin-top: 25px; text-align: center; }
        .pagination a { display: inline-block; color: #ecf0f1; padding: 8px 16px; text-decoration: none; border: 1px solid #444; margin: 0 4px; border-radius: 5px; transition: background-color 0.2s; }
        .pagination a:hover, .pagination a.active { background-color: #e74c3c; border-color: #e74c3c; }
        .status-tag { padding: 4px 8px; border-radius: 5px; color: #fff; font-weight: bold; font-size: 0.8em; text-align: center; }
        .status-active { background-color: #2ecc71; }
        .status-inactive { background-color: #95a5a6; }
        .role-tag { text-transform: capitalize; }
        .action-buttons form { display: flex; flex-direction: column; gap: 5px; }
        .action-buttons button { width: 100%; font-size: 12px; padding: 5px 8px; }
    </style>
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
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php" class="active">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <header class="main-header">
                <h1>Manage Users</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <section class="filter-section">
                    <form action="manage_users.php" method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search by Name, ID, or Email..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <select name="role">
                            <option value="all" <?php if ($filter_role == 'all') echo 'selected'; ?>>All Roles</option>
                            <option value="student" <?php if ($filter_role == 'student') echo 'selected'; ?>>Student</option>
                            <option value="librarian" <?php if ($filter_role == 'librarian') echo 'selected'; ?>>Librarian</option>
                        </select>
                        <select name="status">
                            <option value="all" <?php if ($filter_status == 'all') echo 'selected'; ?>>All Statuses</option>
                            <option value="Active" <?php if ($filter_status == 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if ($filter_status == 'Inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                        <button type="submit">Filter</button>
                    </form>
                </section>

                <section class="request-section">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_users->num_rows > 0): ?>
                                    <?php while($user = $all_users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id_number']); ?></td>
                                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="role-tag"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                            <td><span class="status-tag status-<?php echo strtolower($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                            <td class="action-buttons">
                                                <form action="../backend/update_user_status.php" method="POST">
                                                    <!-- <<< FIXED: Changed 'id' to 'user_id' -->
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    
                                                    <!-- Hidden inputs to preserve filter state on redirect -->
                                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($filter_role); ?>">
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                                    <input type="hidden" name="page" value="<?php echo $page; ?>">

                                                    <?php if ($user['status'] == 'Active'): ?>
                                                        <button type="submit" name="action" value="deactivate" class="reject-btn">Deactivate</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="activate" class="approve-btn">Activate</button>
                                                    <?php endif; ?>

                                                    <?php if ($user['role'] == 'student'): ?>
                                                        <button type="submit" name="action" value="promote" class="pickup-btn">Promote to Librarian</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="demote" class="return-btn">Demote to Student</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">No users found matching your criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <?php $query_params = ['search' => $search_term, 'role' => $filter_role, 'status' => $filter_status]; ?>
                        <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($query_params); ?>">&laquo; Previous</a><?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" class="<?php if ($i == $page) echo 'active'; ?>"><?php echo $i; ?></a><?php endfor; ?>
                        <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($query_params); ?>">Next &raquo;</a><?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>