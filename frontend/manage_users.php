<?php
// frontend/manage_users.php
session_start();

// Security: Allow Admin OR Librarian
$allowed_roles = ['admin', 'librarian'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.html");
    exit;
}

include '../backend/db_connect.php';

// Helper variables
$is_admin = ($_SESSION['role'] === 'admin');
$is_librarian = ($_SESSION['role'] === 'librarian');

// --- Filtering & Pagination ---
$search_term = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// Base Query
$conditions = ["user_id != ?"]; 
$params = [$_SESSION['user_id']];
$types = 'i';

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
    $conditions[] = "status = ?"; 
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Count
$count_sql = "SELECT COUNT(*) FROM users $where_clause";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);

// Fetch Data
$data_sql = "SELECT user_id, id_number, fullname, email, role, status FROM users $where_clause ORDER BY fullname ASC LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($data_sql);
$params[] = $results_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt_data->bind_param($types, ...$params);
$stmt_data->execute();
$all_users = $stmt_data->get_result();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* --- REVAMPED DARK TABLE STYLES --- */
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        
        /* Filter Inputs */
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

        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        /* Status Colors */
        .status-active { background-color: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-banned { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-inactive { background-color: rgba(156, 163, 175, 0.2); color: #d1d5db; border: 1px solid rgba(156, 163, 175, 0.2); }

        /* Role Colors */
        .role-admin { color: #fcd34d; font-weight: bold; } /* Gold */
        .role-librarian { color: #60a5fa; font-weight: bold; } /* Blue */
        .role-student { color: #e5e7eb; } /* Grey */

        /* Buttons */
        .action-buttons form { display: flex; gap: 8px; }
        .btn-mini { padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; color: white; }
        
        .btn-promote { background-color: #10b981; } .btn-promote:hover { background-color: #059669; }
        .btn-demote { background-color: #f59e0b; } .btn-demote:hover { background-color: #d97706; }
        .btn-ban { background-color: #ef4444; }     .btn-ban:hover { background-color: #dc2626; }
        .btn-activate { background-color: #3b82f6; } .btn-activate:hover { background-color: #2563eb; }

        /* Pagination */
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; color: #d1d5db; padding: 8px 12px; text-decoration: none; border: 1px solid #4b5563; margin: 0 2px; border-radius: 4px; background: #1f2937; }
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
                    <li><a href="manage_users.php" class="active">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Manage Users</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <!-- Filters -->
                <section class="action-bar">
                    <form action="manage_users.php" method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search Name, ID..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow:1;">
                        <select name="role">
                            <option value="all">All Roles</option>
                            <option value="student" <?php if ($filter_role == 'student') echo 'selected'; ?>>Student</option>
                            <option value="librarian" <?php if ($filter_role == 'librarian') echo 'selected'; ?>>Librarian</option>
                            <option value="admin" <?php if ($filter_role == 'admin') echo 'selected'; ?>>Admin</option>
                        </select>
                        <select name="status">
                            <option value="all">All Statuses</option>
                            <option value="Active" <?php if ($filter_status == 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Banned" <?php if ($filter_status == 'Banned') echo 'selected'; ?>>Banned</option>
                        </select>
                        <button type="submit">Filter</button>
                    </form>
                </section>

                <section class="content-section">
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
                                            <td><span style="color:#9ca3af; font-family:monospace;"><?php echo htmlspecialchars($user['id_number']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="role-<?php echo strtolower($user['role']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge status-<?php echo strtolower($user['status']); ?>">
                                                    <?php echo htmlspecialchars($user['status']); ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <?php 
                                                // Permission Logic
                                                $can_edit = true;
                                                $target_role = $user['role'];
                                                if ($is_librarian && ($target_role === 'admin' || $target_role === 'librarian')) { $can_edit = false; }
                                                if ($is_admin && $target_role === 'admin') { $can_edit = false; }
                                                ?>

                                                <?php if ($can_edit): ?>
                                                    <form id="form-<?php echo $user['user_id']; ?>" action="../backend/update_user_status.php" method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="action" id="action-input-<?php echo $user['user_id']; ?>" value="">
                                                        
                                                        <!-- Preserve Filters -->
                                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($filter_role); ?>">
                                                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                                        <input type="hidden" name="page" value="<?php echo $page; ?>">

                                                        <!-- ROLE BUTTONS (ADMIN ONLY) -->
                                                        <?php if ($is_admin): ?>
                                                            <?php if ($user['role'] == 'student'): ?>
                                                                <button type="button" class="btn-mini btn-promote" onclick="confirmAction(<?php echo $user['user_id']; ?>, 'promote', '<?php echo htmlspecialchars($user['fullname']); ?>')">Promote</button>
                                                            <?php elseif ($user['role'] == 'librarian'): ?>
                                                                <button type="button" class="btn-mini btn-demote" onclick="confirmAction(<?php echo $user['user_id']; ?>, 'demote', '<?php echo htmlspecialchars($user['fullname']); ?>')">Demote</button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <!-- STATUS BUTTONS -->
                                                        <?php if ($user['status'] == 'Active'): ?>
                                                            <button type="button" class="btn-mini btn-ban" onclick="confirmAction(<?php echo $user['user_id']; ?>, 'ban', '<?php echo htmlspecialchars($user['fullname']); ?>')">Ban</button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn-mini btn-activate" onclick="confirmAction(<?php echo $user['user_id']; ?>, 'activate', '<?php echo htmlspecialchars($user['fullname']); ?>')">Unban</button>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding: 30px; color: #9ca3af;">No users found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (Simple implementation for style) -->
                    <div class="pagination">
                        <?php $query_params = ['search' => $search_term, 'role' => $filter_role, 'status' => $filter_status]; ?>
                        <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($query_params); ?>">&laquo; Prev</a><?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" class="<?php if ($i == $page) echo 'active'; ?>"><?php echo $i; ?></a><?php endfor; ?>
                        <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($query_params); ?>">Next &raquo;</a><?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        function confirmAction(userId, action, userName) {
            let message = "";
            if (action === 'promote') message = "Promote " + userName + " to Librarian?";
            else if (action === 'demote') message = "Demote " + userName + " to Student?";
            else if (action === 'ban') message = "WARNING: Ban " + userName + " from the system?";
            else if (action === 'activate') message = "Reactivate " + userName + "'s account?";

            if (confirm(message)) {
                document.getElementById('action-input-' + userId).value = action;
                document.getElementById('form-' + userId).submit();
            }
        }
    </script>
</body>
</html>