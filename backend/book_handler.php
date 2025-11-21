<?php
// backend/book_handler.php

session_start();
include 'db_connect.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    die("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // --- HANDLE ADDING A NEW BOOK ---
    if ($_POST['action'] == 'add') {
        $book_name = mysqli_real_escape_string($conn, $_POST['book_name']);
        $author = mysqli_real_escape_string($conn, $_POST['author']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $publication_year = (int)$_POST['publication_year'];
        $total_copies = (int)$_POST['total_copies'];
        $description = mysqli_real_escape_string($conn, $_POST['book_description']);
        $available_copies = $total_copies; 

        $image_path = null;

        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
            
            // =================================================================
            // FIX: Pointing to the Root 'Book_Images' folder
            // "../Book_Images/" means: Go out of 'backend', into 'Book_Images'
            // =================================================================
            $target_dir = "../Book_Images/";
            
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $image_name = time() . '_' . basename($_FILES["book_image"]["name"]);
            $target_file = $target_dir . $image_name;
            
            if (move_uploaded_file($_FILES["book_image"]["tmp_name"], $target_file)) {
                // We store the path as "Book_Images/filename.jpg" in the database
                $image_path = "Book_Images/" . $image_name; 
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO books (book_name, author, category, publication_year, total_copies, available_copies, book_description, book_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiisss", $book_name, $author, $category, $publication_year, $total_copies, $available_copies, $description, $image_path);
        
        if ($stmt->execute()) {
            header("Location: ../frontend/manage_books.php?status=add_success");
        } else {
            header("Location: ../frontend/manage_books.php?status=add_error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
    }

    // --- HANDLE DELETING A BOOK ---
    if ($_POST['action'] == 'delete') {
        $book_id = (int)$_POST['book_id'];

        // Delete image file from Root folder
        $stmt_select = $conn->prepare("SELECT book_image_path FROM books WHERE book_id = ?");
        $stmt_select->bind_param("i", $book_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['book_image_path'])) {
                // FIX: Path to delete is "../" + "Book_Images/filename"
                $full_path = '../' . $row['book_image_path'];
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }
        $stmt_select->close();

        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);

        if ($stmt->execute()) {
            header("Location: ../frontend/manage_books.php?status=delete_success");
        } else {
            header("Location: ../frontend/manage_books.php?status=delete_error");
        }
        $stmt->close();
    }

} else {
    header("Location: ../frontend/manage_books.php");
}
$conn->close();
?>