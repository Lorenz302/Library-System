<?php
// Start the session to check for login status
session_start();

// Include the database connection
include '../backend/db_connect.php';

// If not logged in, redirect them to the login page
if (!isset($_SESSION["id_number"])) {
    header("location: index.html");
    exit;
}

// Check if a book ID is provided in the URL. If not, or if it's not a number, go back home.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: home.php");
    exit;
}

$book_id = $_GET['id'];

// Prepare and execute a secure query to get the specific book's details
$sql = "SELECT * FROM books WHERE book_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch the book data into an associative array
$book = $result->fetch_assoc();

// If no book was found with that ID, redirect back to the home page
if (!$book) {
    header("location: home.php");
    exit;
}

// We are done with the database for this page, so we can close the connection
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The page title will be the name of the book -->
    <title><?php echo htmlspecialchars($book['book_name']); ?> - Details</title>
    <!-- We reuse the main CSS file for the header and general styles -->
    <link rel="stylesheet" href="home.css">
    
    <!-- STYLES SPECIFIC TO THIS DETAILS PAGE -->
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
        }
        .details-page-container {
            max-width: 1100px;
            margin: 40px auto; /* Add space from the header and footer */
            padding: 20px;
            min-height: calc(100vh - 400px); /* Ensures content pushes footer down */
        }
        .details-card {
            display: flex;
            flex-wrap: wrap; /* Allows items to wrap on smaller screens */
            gap: 40px;
            padding: 40px;
            background-color: #1e1e1e;
            border-radius: 12px;
            border: 1px solid #333;
        }
        .details-image-section {
            flex: 1 1 300px; /* Flex properties for responsive resizing */
            max-width: 350px;
        }
        .details-image-section img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #444;
        }
        .details-info-section {
            flex: 2 1 500px; /* Allows this section to take more space */
        }
        .details-info-section h1 {
            font-size: 2.8rem;
            font-weight: bold;
            color: #c81d14;
            margin: 0 0 20px 0;
        }
        .details-info-section .info-item {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #ccc;
        }
        .details-info-section .info-item strong {
            color: #ffffff;
            min-width: 150px;
            display: inline-block;
        }
        .description-section {
            margin-top: 30px;
            font-size: 1rem;
            line-height: 1.7;
            text-align: justify;
            color: #b0b0b0;
        }
        .back-button {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background-color: #c81d14;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .back-button:hover {
            background-color: #a5120f;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .details-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 25px;
            }
            .details-info-section h1 {
                font-size: 2.2rem;
            }
            .details-info-section .info-item strong {
                display: block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Consistent Header from home.php -->
    <header class="header">
         <div class="logo-container">
            <img src="LIBRARY_LOGO.png" alt="Logo">
        </div>  
        <nav>
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="reservation-section.html">Reservations</a></li>
                <li><a href="borrow-books.html">Borrowed Books</a></li>
                <li><a href="bookmark.html">Bookmarks</a></li>
            </ul>
        </nav>
        <div class="prof-notif-icon">
            <img class="notification-icons" src="NOTIF-ICON.png" alt="Notification">
            <img class="profile-icons " src="profile-icon.png" alt="Profile">
            <a href="../backend/logout.php" style="margin-left: 15px; color: white; text-decoration: none; font-weight: bold;">Logout</a>
        </div> 
    </header>

    <!-- Main Content Area for the Book Details -->
    <div class="details-page-container">
        <div class="details-card">
            
            <!-- Book Cover Image -->
            <div class="details-image-section">
                <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? $book['book_image_path'] : 'placeholder.png'); ?>" alt="Cover of <?php echo htmlspecialchars($book['book_name']); ?>">
            </div>

            <!-- Book Information -->
            <div class="details-info-section">
                <h1><?php echo htmlspecialchars($book['book_name']); ?></h1>
                
                <p class="info-item">
                    <strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?>
                </p>
                <p class="info-item">
                    <strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?>
                </p>
                <p class="info-item">
                    <strong>Publication Year:</strong> <?php echo htmlspecialchars($book['publication_year']); ?>
                </p>
                <p class="info-item">
                    <strong>Available Copies:</strong> <?php echo htmlspecialchars($book['available_copies']); ?>
                </p>

                <div class="description-section">
                    <p><?php echo nl2br(htmlspecialchars($book['book_description'])); ?></p>
                </div>
                
                <a href="home.php" class="back-button">Back to Library</a>
            </div>
        </div>
    </div>

    <!-- ===============================FOOTER============================================ -->
    <footer>
        <div class="footer">
            <div class="footer-content">
                <div class="footer-column company-info">
                    <div class="company-logo"><img src="LIBRARY_LOGO.png" alt="Company Logo" /></div>
                    <div class="footer-details">
                        <div class="footer-item"><span class="footer-title">Address:</span><span class="footer-text">Roman Highway Balanga City Bataan</span></div>
                        <div class="footer-item"><span class="footer-title">Contact:</span><span class="footer-text">1800 123 4567</span><span class="footer-text">info@heroes1979.edu.ph</span></div>
                    </div>
                    <div class="social-links"><img src="FB_LOGO.png" alt="Facebook" /></div>
                </div>
                <div class="footer-column footer-links">
                    <ul>
                        <li><a href="home.php">Home</a></li>
                        <li><a href="reservation-section.html">Reservations</a></li>
                        <li><a href="bookmark.html">Borrowed Books</a></li>
                        <li><a href="bookmark.html">Bookmarks</a></li>
                    </ul>
                    <ul>
                        <li><a href="#">Recommended Books</a></li>
                        <li><a href="#">How to Borrow Books</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="divider"></div>
                <div class="footer-row">
                    <span>© 2024 HEROES LIBRARY All rights reserved.</span>
                    <div class="footer-bottom-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Cookies Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>