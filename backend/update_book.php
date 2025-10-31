<?php
session_start();
include 'db_connect.php';

// Security: Ensure user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: ../frontend/index.html");
    exit;
}

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../frontend/manage_books.php");
    exit;
}

// 1. Get all data from the form
$book_id = (int)$_POST['book_id'];
$book_name = $_POST['book_name'];
$author = $_POST['author'];
$category = $_POST['category'];
$publication_year = (int)$_POST['publication_year'];
$new_total_copies = (int)$_POST['total_copies'];
$book_description = $_POST['book_description'];
$existing_image_path = $_POST['existing_image_path'] ?? null;

$new_image_path = $existing_image_path; // Default to old path

// 2. Handle File Upload (if a new image is provided)
if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
    // CORRECTED: Point to the 'Book_Images' directory, which is one level up from /backend
    $upload_dir = '../Book_Images/';
    
    // Create a unique filename to prevent overwriting existing files
    $file_extension = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('book_', true) . '.' . $file_extension;
    $target_file = $upload_dir . $unique_filename;
    
    // Ensure the Book_Images directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move the uploaded file to the target directory
    if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_file)) {
        // CORRECTED: Store the path with the correct folder name for the database
        $new_image_path = 'Book_Images/' . $unique_filename;
        
        // This logic correctly deletes the old image file if it exists
        if ($existing_image_path && file_exists('../' . $existing_image_path) && $existing_image_path !== $new_image_path) {
            unlink('../' . $existing_image_path);
        }
    } else {
        // Handle upload error if necessary
        header("Location: ../frontend/edit_book.php?id=$book_id&error=upload_failed");
        exit;
    }
}

// 3. Logic to update available_copies correctly
$conn->begin_transaction();
try {
    // Get the current state of the book from the database
    $stmt_current = $conn->prepare("SELECT total_copies, available_copies FROM books WHERE book_id = ? FOR UPDATE");
    $stmt_current->bind_param("i", $book_id);
    $stmt_current->execute();
    $current_book = $stmt_current->get_result()->fetch_assoc();
    $current_total_copies = $current_book['total_copies'];
    
    // Calculate the number of books currently on loan
    $books_on_loan = $current_total_copies - $current_book['available_copies'];

    // Validation: The new total cannot be less than the number of books currently checked out.
    if ($new_total_copies < $books_on_loan) {
        throw new Exception("Total copies cannot be less than the number of books currently on loan ($books_on_loan).");
    }

    // Calculate the new available_copies
    $new_available_copies = $new_total_copies - $books_on_loan;
    
    // 4. Prepare and execute the final UPDATE statement
    $sql_update = "UPDATE books SET 
                    book_name = ?, 
                    author = ?, 
                    category = ?, 
                    publication_year = ?, 
                    total_copies = ?, 
                    available_copies = ?, 
                    book_description = ?, 
                    book_image_path = ? 
                   WHERE book_id = ?";
                   
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param(
        "sssiisssi",
        $book_name,
        $author,
        $category,
        $publication_year,
        $new_total_copies,
        $new_available_copies,
        $book_description,
        $new_image_path,
        $book_id
    );

    if (!$stmt_update->execute()) {
        throw new Exception("Database update failed.");
    }
    
    $conn->commit();
    header("Location: ../frontend/manage_books.php?update=success");

} catch (Exception $e) {
    $conn->rollback();
    // Redirect with the error message for the user to see
    header("Location: ../frontend/edit_book.php?id=$book_id&error=" . urlencode($e->getMessage()));
}

$conn->close();
exit();
?>