<?php
// backend/fetch_my_borrow_history_api.php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

// 1. SET TIMEZONE TO REAL TIME (Philippines)
// This ensures "Today" matches your actual wall clock, not the server's UTC time.
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['id_number'];

// Fetch records
$sql = "SELECT br.borrow_status, br.borrow_date, br.due_date, br.return_date, b.book_name, b.book_description, b.book_image_path
        FROM borrow_requests br
        JOIN books b ON br.book_id = b.book_id
        WHERE br.id_number = ? 
        AND br.borrow_status IN ('Approved', 'Borrowed', 'Returned', 'Expired', 'Rejected')
        ORDER BY br.borrow_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [
    'server_time' => date('F j, Y, g:i:s a'), // Send real-time server clock to frontend
    'overdue' => [],
    'current' => [],
    'returned' => []
];

// 2. DEFINE "TODAY" STRICTLY
$todayStr = date('Y-m-d'); // e.g., "2025-11-23"

while ($book = $result->fetch_assoc()) {
    // Format display dates
    $book['formatted_borrow_date'] = date('M d, Y', strtotime($book['borrow_date']));
    $book['formatted_due_date'] = !empty($book['due_date']) ? date('M d, Y', strtotime($book['due_date'])) : '';
    $book['formatted_return_date'] = !empty($book['return_date']) ? date('M d, Y', strtotime($book['return_date'])) : '';
    
    // Image path fix
    $book['book_image_path'] = !empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png';

    // --- CATEGORIZATION LOGIC ---

    if ($book['borrow_status'] === 'Returned' || $book['borrow_status'] === 'Expired' || $book['borrow_status'] === 'Rejected') {
        // HISTORY
        $data['returned'][] = $book;

    } else if ($book['borrow_status'] === 'Borrowed') {
        // ACTIVE LOAN
        if (!empty($book['due_date'])) {
            
            // 3. REAL-TIME COMPARISON
            // If Due Date (e.g. 2025-11-22) is LESS THAN Today (2025-11-23) -> OVERDUE
            // If Due Date (e.g. 2025-11-23) is EQUAL TO Today -> CURRENT (Due today)
            
            if ($book['due_date'] < $todayStr) {
                $data['overdue'][] = $book;
            } else {
                $data['current'][] = $book;
            }
        } else {
            $data['current'][] = $book;
        }

    } else if ($book['borrow_status'] === 'Approved') {
        // APPROVED (READY FOR PICKUP)
        $book['is_approved'] = true;
        $data['current'][] = $book;
    }
}

echo json_encode($data);
$conn->close();
?>