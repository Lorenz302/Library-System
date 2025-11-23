<?php
// frontend/borrow_history.php
session_start();

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

include '../backend/db_connect.php';

// --- 1. HANDLE FILTERS & INPUTS ---
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; // New Status Filter
$sort = $_GET['sort'] ?? 'DESC';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// Validate Sort Order
if (!in_array($sort, ['ASC', 'DESC'])) {
    $sort = 'DESC';
}

// --- 2. BUILD QUERY ---
$where_clauses = [];
$params = [];
$types = "";

// Search Logic
if (!empty($search)) {
    $where_clauses[] = "(u.fullname LIKE ? OR b.book_name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Status Filter Logic
if ($filter_status !== 'all') {
    if ($filter_status === 'history') {
        // Show only closed transactions
        $where_clauses[] = "br.borrow_status IN ('Returned', 'Rejected', 'Cancelled', 'Expired')";
    } else if ($filter_status === 'active') {
        // Show only active transactions
        $where_clauses[] = "br.borrow_status IN ('Pending', 'Approved', 'Borrowed')";
    } else {
        // Show specific status
        $where_clauses[] = "br.borrow_status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
}

// Construct WHERE string
$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// --- 3. PAGINATION COUNT ---
$count_sql = "
    SELECT COUNT(*) 
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    $where_sql
";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);
$stmt_count->close();

// --- 4. FETCH DATA QUERY ---
// We sort by relevant date. If returned, use return_date. If active, use borrow_date.
$data_sql = "
    SELECT br.borrow_id, u.fullname, b.book_name, br.borrow_date, br.return_date, br.borrow_status, br.due_date
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    $where_sql
    ORDER BY 
        CASE WHEN br.return_date IS NOT NULL THEN br.return_date ELSE br.borrow_date END $sort
    LIMIT ? OFFSET ?
";

$stmt_data = $conn->prepare($data_sql);

// Append Limit/Offset params
$params[] = $results_per_page;
$params[] = $offset;
$types .= "ii";

$stmt_data->bind_param($types, ...$params);
$stmt_data->execute();
$result = $stmt_data->get_result();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow History - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* --- REVAMPED DARK STYLES --- */
        
        /* Action Bar & Filters */
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        
        .filter-form { display: flex; gap: 10px; width: 100%; }
        .filter-form input, .filter-form select { padding: 10px 15px; border-radius: 6px; border: 1px solid #4b5563; background: #1f2937; color: #f3f4f6; outline: none; }
        .filter-form button { padding: 10px 20px; border-radius: 6px; background-color: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 600; }
        .filter-form button:hover { background-color: #2563eb; }

        /* Table Container */
        .table-container { background-color: #1f2937; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #374151; }
        
        table { width: 100%; border-collapse: collapse; color: #e5e7eb; font-size: 0.95rem; }
        
        thead tr { background-color: #111827; text-align: left; }
        th { padding: 18px 24px; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; color: #9ca3af; border-bottom: 1px solid #374151; }
        
        tbody tr { border-bottom: 1px solid #374151; transition: background-color 0.15s ease; }
        tbody tr:hover { background-color: #374151; }
        td { padding: 16px 24px; vertical-align: middle; }

        /* Status Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        /* Historical Statuses */
        .status-returned { background-color: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-rejected { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-cancelled { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-expired { background-color: rgba(156, 163, 175, 0.2); color: #d1d5db; border: 1px solid rgba(156, 163, 175, 0.2); } /* Grey */

        /* Active Statuses */
        .status-pending { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); } /* Orange */
        .status-approved { background-color: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); } /* Green */
        .status-borrowed { background-color: rgba(139, 92, 246, 0.2); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); } /* Purple */

        /* Pagination */
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; color: #d1d5db; padding: 8px 12px; text-decoration: none; border: 1px solid #4b5563; margin: 0 2px; border-radius: 4px; background: #1f2937; transition: 0.2s; }
        .pagination a:hover, .pagination a.active { background-color: #3b82f6; border-color: #3b82f6; color: white; }
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
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php" class="active">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Borrow History & Logs</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                
                <!-- Filters / Action Bar -->
                <section class="action-bar">
                    <form action="borrow_history.php" method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search User or Book Title..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow:1;">
                        
                        <select name="status">
                            <option value="all" <?php if($filter_status == 'all') echo 'selected'; ?>>All Statuses</option>
                            <option value="history" <?php if($filter_status == 'history') echo 'selected'; ?>>All History (Closed)</option>
                            <option value="active" <?php if($filter_status == 'active') echo 'selected'; ?>>All Active</option>
                            <option disabled>---</option>
                            <option value="Borrowed" <?php if($filter_status == 'Borrowed') echo 'selected'; ?>>Borrowed</option>
                            <option value="Returned" <?php if($filter_status == 'Returned') echo 'selected'; ?>>Returned</option>
                            <option value="Rejected" <?php if($filter_status == 'Rejected') echo 'selected'; ?>>Rejected</option>
                            <option value="Expired" <?php if($filter_status == 'Expired') echo 'selected'; ?>>Expired</option>
                        </select>

                        <select name="sort">
                            <option value="DESC" <?php if($sort == 'DESC') echo 'selected'; ?>>Newest First</option>
                            <option value="ASC" <?php if($sort == 'ASC') echo 'selected'; ?>>Oldest First</option>
                        </select>
                        
                        <button type="submit">Filter</button>
                    </form>
                </section>

                <!-- Table Content -->
                <section class="content-section">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Book Title</th>
                                    <th>Date Borrowed / Requested</th>
                                    <th>Date Returned / Closed</th>
                                    <th>Current Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars(date('M d, Y', strtotime($row['borrow_date']))); ?>
                                            </td>
                                            <td style="color: #d1d5db;">
                                                <?php 
                                                    // ==================================================
                                                    // LOGIC FIX: Only show date if status is 'Returned'
                                                    // For others (Rejected/Expired), show "N/A"
                                                    // ==================================================
                                                    if ($row['borrow_status'] === 'Returned' && !empty($row['return_date']) && $row['return_date'] != '0000-00-00' && $row['return_date'] != '0000-00-00 00:00:00') {
                                                        echo htmlspecialchars(date('M d, Y', strtotime($row['return_date'])));
                                                    } elseif ($row['borrow_status'] === 'Borrowed') {
                                                        echo "<span style='color:#a78bfa;'>Due: " . htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) . "</span>";
                                                    } else {
                                                        echo '<span style="color: #6b7280;">N/A</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower($row['borrow_status']); ?>">
                                                    <?php echo htmlspecialchars($row['borrow_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:30px; color:#9ca3af;">
                                            No records found matching your criteria.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php 
                            // Prepare query string for links to keep current filters active
                            $query_params = ['search' => $search, 'sort' => $sort, 'status' => $filter_status]; 
                        ?>
                        
                        <!-- Prev Link -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($query_params); ?>">&laquo; Prev</a>
                        <?php endif; ?>

                        <!-- Numbered Links -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" class="<?php if ($i == $page) echo 'active'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next Link -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($query_params); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </section>
            </main>
        </div>
    </div>
</body>
</html>