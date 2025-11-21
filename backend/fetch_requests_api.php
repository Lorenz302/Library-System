<?php
// backend/fetch_requests_api.php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode([]);
    exit;
}

// Get filters from AJAX request
$search_term = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'DESC';

if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

// Base Query (Same as manage_requests.php logic)
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

// NOTE: Pagination is handled by Frontend logic or simple scrolling for the API
// For simplicity in the poller, we fetch the top 50 recent items to keep it fast
$limit = 50; 

$data_sql = "$final_base_query $where_clause ORDER BY request_date $sort_order LIMIT ?";
$stmt = $conn->prepare($data_sql);

$params[] = $limit;
$types .= 'i';

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    // Format dates for JSON
    $row['request_date_formatted'] = date('Y-m-d', strtotime($row['request_date']));
    $row['relevant_date_formatted'] = !empty($row['relevant_date']) ? date('Y-m-d', strtotime($row['relevant_date'])) : 'N/A';
    $data[] = $row;
}

echo json_encode($data);
exit;
?>