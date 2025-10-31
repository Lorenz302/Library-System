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
$filter_status = $_GET['status'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'DESC';

if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// This query is updated to include due_date
$base_query = "
    SELECT 
        br.borrow_id, 
        u.fullname AS user_name, 
        b.book_name,
        br.borrow_date,
        br.due_date,
        br.return_date,
        br.borrow_status AS status
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    WHERE br.borrow_status IN ('Returned', 'Rejected', 'Cancelled')
";

$conditions = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $conditions[] = "(u.fullname LIKE ? OR b.book_name LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params = array_merge($params, [$search_like, $search_like]);
    $types .= 'ss';
}
if ($filter_status !== 'all') {
    $conditions[] = "br.borrow_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = !empty($conditions) ? " AND " . implode(" AND ", $conditions) : "";

$final_query_string = $base_query . $where_clause;

// The count query needs to be constructed carefully with subqueries when there's a join and where clause.
// A simpler way for counting without subquery complexity for this case:
$count_base_query = "
    SELECT COUNT(*) 
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    WHERE br.borrow_status IN ('Returned', 'Rejected', 'Cancelled')
";
$count_sql = $count_base_query . $where_clause;
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);

$data_sql = "$final_query_string ORDER BY br.borrow_date $sort_order LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($data_sql);

$params_with_pagination = $params;
$params_with_pagination[] = $results_per_page;
$params_with_pagination[] = $offset;
$types_with_pagination = $types . 'ii';

$stmt_data->bind_param($types_with_pagination, ...$params_with_pagination);
$stmt_data->execute();
$history_requests = $stmt_data->get_result();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow History - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .filter-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
        .filter-form input[type="text"], .filter-form select, .filter-form button { padding: 8px 12px; border-radius: 5px; border: 1px solid #444; background-color: #2c3e50; color: #ecf0f1; font-size: 14px; }
        .filter-form input[type="text"] { flex-grow: 1; }
        .filter-form input[type="text"]::placeholder { color: #bdc3c7; }
        .filter-form button { background-color: #e74c3c; border-color: #e74c3c; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .filter-form button:hover { background-color: #c0392b; }
        .pagination { margin-top: 25px; text-align: center; }
        .pagination a { display: inline-block; color: #ecf0f1; padding: 8px 16px; text-decoration: none; border: 1px solid #444; margin: 0 4px; border-radius: 5px; transition: background-color 0.2s; }
        .pagination a:hover, .pagination a.active { background-color: #e74c3c; border-color: #e74c3c; }
        .status-tag { padding: 4px 8px; border-radius: 5px; color: #fff; font-weight: bold; font-size: 0.8em; text-align: center; }
        .status-returned { background-color: #27ae60; }
        .status-rejected { background-color: #c0392b; }
        .status-cancelled { background-color: #7f8c8d; }
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
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php" class="active">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <header class="main-header">
                <h1>Borrow History</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <section class="filter-section">
                    <form action="borrow_history.php" method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search by User Name or Book Title..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <select name="status">
                            <option value="all" <?php if ($filter_status == 'all') echo 'selected'; ?>>All Statuses</option>
                            <option value="Returned" <?php if ($filter_status == 'Returned') echo 'selected'; ?>>Returned</option>
                            <option value="Rejected" <?php if ($filter_status == 'Rejected') echo 'selected'; ?>>Rejected</option>
                            <option value="Cancelled" <?php if ($filter_status == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                        <select name="sort">
                            <option value="DESC" <?php if ($sort_order == 'DESC') echo 'selected'; ?>>Newest First</option>
                            <option value="ASC" <?php if ($sort_order == 'ASC') echo 'selected'; ?>>Oldest First</option>
                        </select>
                        <button type="submit">Filter</button>
                    </form>
                </section>

                <section class="request-section">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>User Name</th>
                                    <th>Book Title</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history_requests->num_rows > 0): ?>
                                    <?php while($row = $history_requests->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['borrow_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['borrow_date']))); ?></td>
                                            <td>
                                                <?php 
                                                    // Display due date only if it's not NULL
                                                    echo $row['due_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['due_date']))) : 'N/A';
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    // Display return date only if it's not NULL
                                                    echo $row['return_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['return_date']))) : 'N/A';
                                                ?>
                                            </td>
                                            <td><span class="status-tag status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7">No historical records found matching your criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <?php $query_params = ['search' => $search_term, 'status' => $filter_status, 'sort' => $sort_order]; ?>
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