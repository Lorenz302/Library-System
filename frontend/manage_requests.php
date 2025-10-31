<?php
// Start the session to access user data
session_start();

// Security check: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: ../frontend/index.html");
    exit;
}

// Include the database connection
include '../backend/db_connect.php';

// --- START: Filtering, Sorting, and Pagination Logic ---

$search_term = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'DESC';

if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

// --- CORRECTED AND EXPANDED BASE QUERY ---
$base_query = "
    (SELECT 
        br.borrow_id AS request_id, 
        u.id_number, 
        u.fullname AS user_name, 
        b.book_id,
        b.book_name AS book_title,
        br.borrow_date AS request_date, 
        br.due_date AS relevant_date,
        'Borrow' AS request_type,
        br.borrow_status AS status
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    WHERE br.borrow_status IN ('Pending', 'Approved', 'Borrowed'))
    UNION ALL
    (SELECT 
        rr.reservation_id AS request_id, 
        u.id_number, 
        u.fullname AS user_name, 
        b.book_id,
        b.book_name AS book_title,
        rr.reservation_date AS request_date, 
        rr.pickup_expiry_date AS relevant_date,
        'Reservation' AS request_type,
        rr.reservation_status AS status
    FROM reservation_requests rr
    JOIN users u ON rr.id_number = u.id_number
    JOIN books b ON rr.book_id = b.book_id
    WHERE rr.reservation_status IN ('Pending', 'Available'))
";

$final_base_query = "SELECT * FROM ($base_query) AS all_requests";

$conditions = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $conditions[] = "(user_name LIKE ? OR book_title LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params = array_merge($params, [$search_like, $search_like]);
    $types .= 'ss';
}
if ($filter_type !== 'all') {
    $conditions[] = "request_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_status !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

$count_sql = "SELECT COUNT(*) FROM ($final_base_query) AS final_count $where_clause";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_results = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $results_per_page);

$data_sql = "$final_base_query $where_clause ORDER BY request_date $sort_order LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($data_sql);

$params_with_pagination = $params;
$params_with_pagination[] = $results_per_page;
$params_with_pagination[] = $offset;
$types_with_pagination = $types . 'ii';

$stmt_data->bind_param($types_with_pagination, ...$params_with_pagination);
$stmt_data->execute();
$all_requests = $stmt_data->get_result();

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']) . "!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .filter-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-form input[type="text"], .filter-form select, .filter-form button { padding: 8px 12px; border-radius: 5px; border: 1px solid #444; background-color: #2c3e50; color: #ecf0f1; font-size: 14px; }
        .filter-form input[type="text"] { flex-grow: 1; }
        .filter-form button { background-color: #e74c3c; border-color: #e74c3c; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .filter-form button:hover { background-color: #c0392b; }
        .pagination { margin-top: 25px; text-align: center; }
        .pagination a { display: inline-block; color: #ecf0f1; padding: 8px 16px; text-decoration: none; border: 1px solid #444; margin: 0 4px; border-radius: 5px; transition: background-color 0.2s; }
        .pagination a:hover, .pagination a.active { background-color: #e74c3c; border-color: #e74c3c; }
        .status-tag { padding: 4px 8px; border-radius: 5px; color: #fff; font-weight: bold; font-size: 0.8em; text-align: center; }
        .status-pending { background-color: #f39c12; }
        .status-approved { background-color: #2ecc71; }
        .status-available { background-color: #27ae60; }
        .status-borrowed { background-color: #3498db; }
        .action-buttons form { display: flex; gap: 5px; }
        .pickup-btn { background-color: #3498db !important; border-color: #3498db !important; }
        .return-btn { background-color: #27ae60 !important; border-color: #27ae60 !important; }
        .fulfill-btn { background-color: #9b59b6 !important; border-color: #9b59b6 !important; color: white !important; }
        
        .header-info-icon {
            font-size: 1.5rem;
            color: #bdc3c7;
            cursor: pointer;
            margin-left: 20px;
            font-weight: bold;
            border: 2px solid #bdc3c7;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .header-info-icon:hover {
            color: white;
            border-color: white;
            transform: scale(1.1);
        }
        .info-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }
        .info-modal-content {
            background: #34495e;
            color: #ecf0f1;
            padding: 30px 40px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            border: 1px solid #4a627a;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .info-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 2rem;
            font-weight: bold;
            color: #bdc3c7;
            cursor: pointer;
            transition: color 0.2s;
        }
        .info-modal-close:hover {
            color: white;
        }
        .info-modal h2 {
            text-align: center;
            color: white;
            margin-bottom: 25px;
            border-bottom: 1px solid #4a627a;
            padding-bottom: 15px;
        }
        .info-modal ul {
            list-style: none;
            padding: 0;
        }
        .info-modal li {
            margin-bottom: 15px;
        }
        .info-modal strong {
            display: inline-block;
            background-color: #f39c12;
            padding: 4px 8px;
            border-radius: 5px;
            margin-right: 10px;
            color: white;
        }
        .info-modal .status-approved, .info-modal .status-available { background-color: #27ae60; }
        .info-modal .status-borrowed { background-color: #3498db; }
        .info-modal .status-pending { background-color: #f39c12; }
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
                    <li><a href="manage_requests.php" class="active">Manage Requests</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Manage Active Requests</h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                    <span id="infoBtn" class="header-info-icon" title="Page Information">i</span>
                </div>
            </header>

            <main class="main-content">
                <section class="filter-section">
                    <form action="manage_requests.php" method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search by Name or Book Title..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <select name="type">
                            <option value="all" <?php if ($filter_type == 'all') echo 'selected'; ?>>All Types</option>
                            <option value="Borrow" <?php if ($filter_type == 'Borrow') echo 'selected'; ?>>Borrow</option>
                            <option value="Reservation" <?php if ($filter_type == 'Reservation') echo 'selected'; ?>>Reservation</option>
                        </select>
                        <select name="status">
                            <option value="all" <?php if ($filter_status == 'all') echo 'selected'; ?>>All Statuses</option>
                            <option value="Pending" <?php if ($filter_status == 'Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Approved" <?php if ($filter_status == 'Approved') echo 'selected'; ?>>Approved (Borrows)</option>
                            <option value="Available" <?php if ($filter_status == 'Available') echo 'selected'; ?>>Available (Reservations)</option>
                            <option value="Borrowed" <?php if ($filter_status == 'Borrowed') echo 'selected'; ?>>Borrowed</option>
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
                                    <th>User Name</th>
                                    <th>Book Title</th>
                                    <th>Request Date</th>
                                    <th>Due / Expiry Date</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_requests->num_rows > 0): ?>
                                    <?php while($row = $all_requests->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['request_date']))); ?></td>
                                            <td>
                                                <?php 
                                                    echo !empty($row['relevant_date']) ? htmlspecialchars(date('Y-m-d', strtotime($row['relevant_date']))) : 'N/A';
                                                ?>
                                            </td>
                                            <td><span class="status-tag status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['request_type']); ?></td>
                                            <td class="action-buttons">
                                                <form action="../backend/update_request_status.php" method="POST">
                                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_order); ?>">
                                                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                    
                                                    <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                                    <input type="hidden" name="request_type" value="<?php echo strtolower($row['request_type']); ?>">
                                                    <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($row['book_id']); ?>">
                                                    <input type="hidden" name="user_id_number" value="<?php echo htmlspecialchars($row['id_number']); ?>">
                                                    
                                                    <?php if ($row['request_type'] == 'Borrow'): ?>
                                                        <?php if ($row['status'] == 'Pending'): ?>
                                                            <button type="submit" name="action" value="Approve" class="approve-btn">Approve</button>
                                                            <button type="submit" name="action" value="Reject" class="reject-btn">Reject</button>
                                                        <?php elseif ($row['status'] == 'Approved'): ?>
                                                            <button type="submit" name="action" value="MarkPickedUp" class="pickup-btn">Mark as Picked Up</button>
                                                        <?php elseif ($row['status'] == 'Borrowed'): ?>
                                                            <button type="submit" name="action" value="MarkReturned" class="return-btn">Mark as Returned</button>
                                                        <?php endif; ?>
                                                    <?php elseif ($row['request_type'] == 'Reservation'): ?>
                                                        <?php if ($row['status'] == 'Pending'): ?>
                                                            <button type="submit" name="action" value="CancelReservation" class="reject-btn">Cancel</button>
                                                        <?php elseif ($row['status'] == 'Available'): ?>
                                                            <button type="submit" name="action" value="Fulfill" class="fulfill-btn">Fulfill</button>
                                                            <button type="submit" name="action" value="MarkAsExpired" class="reject-btn">Mark as Expired</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7">No active requests found matching your criteria.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <?php $query_params = ['search' => $search_term, 'type' => $filter_type, 'status' => $filter_status, 'sort' => $sort_order]; ?>
                        <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($query_params); ?>">&laquo; Previous</a><?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&<?php echo http_build_query($query_params); ?>" class="<?php if ($i == $page) echo 'active'; ?>"><?php echo $i; ?></a><?php endfor; ?>
                        <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($query_params); ?>">Next &raquo;</a><?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="infoModal" class="info-modal">
        <div class="info-modal-content">
            <span id="infoModalClose" class="info-modal-close">&times;</span>
            <h2>Status and Action Guide</h2>
            <ul>
                <li>
                    <strong class="status-pending">Pending</strong>
                    A student has requested to borrow an available book OR has reserved a currently unavailable book. Your action is required.
                </li>
                <li>
                    <strong class="status-approved">Approved</strong>
                    You have approved a borrow request. The book is now on hold, waiting for the student to pick it up.
                </li>
                <li>
                    <strong class="status-available">Available</strong>
                    A reserved book has been returned. It is now on hold for the first person in the reservation queue. They have been notified to pick it up.
                </li>
                <li>
                    <strong class="status-borrowed">Borrowed</strong>
                    The student has picked up the approved book. The book is now officially on loan until its due date.
                </li>
            </ul>
            <h2>Action Explanations</h2>
            <ul>
                <li>
                    <strong>Fulfill:</strong> The student has arrived to pick up their 'Available' reservation. This action will convert the reservation into an active loan.
                </li>
                <li>
                    <strong>Mark as Expired:</strong> Use this if a student with an 'Available' reservation fails to pick up the book before the expiry date. This gives the book to the next person in the queue or makes it publicly available.
                </li>
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const infoModal = document.getElementById('infoModal');
            const infoBtn = document.getElementById('infoBtn');
            const infoModalClose = document.getElementById('infoModalClose');

            if (infoBtn) {
                infoBtn.onclick = function() {
                    infoModal.style.display = 'flex';
                }
            }

            if (infoModalClose) {
                infoModalClose.onclick = function() {
                    infoModal.style.display = 'none';
                }
            }

            window.onclick = function(event) {
                if (event.target == infoModal) {
                    infoModal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>