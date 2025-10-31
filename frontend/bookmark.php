<?php
// Start the session to check for login status
session_start();

// Include the database connection
include '../backend/db_connect.php';

// If not logged in as a student, redirect them to the login page
if (!isset($_SESSION["id_number"]) || $_SESSION['role'] !== 'student') {
    header("location: index.html");
    exit;
}

// --- FETCH USER DETAILS FOR PROFILE MODAL ---
$current_user_id = $_SESSION['id_number'];
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_details = $user_result->fetch_assoc();
$stmt_user->close();


// --- FETCH ALL FAVORITED BOOKS FOR THE CURRENT USER ---
$sql_favorites = "SELECT b.*
                  FROM user_favorites uf
                  JOIN books b ON uf.book_id = b.book_id
                  WHERE uf.id_number = ?";
$stmt_favorites = $conn->prepare($sql_favorites);
$stmt_favorites->bind_param("s", $current_user_id);
$stmt_favorites->execute();
$favorites_result = $stmt_favorites->get_result();

// --- FETCH USER'S CURRENT BORROWS AND RESERVATIONS TO DETERMINE BUTTON STATES ---
$borrowed_book_ids = [];
$sql_borrowed = "SELECT book_id FROM borrow_requests WHERE id_number = ? AND borrow_status IN ('Pending', 'Approved', 'Borrowed')";
$stmt_borrowed = $conn->prepare($sql_borrowed);
$stmt_borrowed->bind_param("s", $current_user_id);
$stmt_borrowed->execute();
$result_borrowed = $stmt_borrowed->get_result();
while($borrow_row = $result_borrowed->fetch_assoc()) {
    $borrowed_book_ids[] = $borrow_row['book_id'];
}
$stmt_borrowed->close();

$reserved_book_ids = [];
$sql_reserved = "SELECT book_id FROM reservation_requests WHERE id_number = ? AND reservation_status IN ('Pending', 'Available')";
$stmt_reserved = $conn->prepare($sql_reserved);
$stmt_reserved->bind_param("s", $current_user_id);
$stmt_reserved->execute();
$result_reserved = $stmt_reserved->get_result();
while($reserve_row = $result_reserved->fetch_assoc()) {
    $reserved_book_ids[] = $reserve_row['book_id'];
}
$stmt_reserved->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookmarks</title>
    <link rel="stylesheet" href="home.css"> 
    <style>
        .empty-message {
            text-align: center;
            grid-column: 1 / -1;
            color: #ccc;
            padding: 40px;
            background-color: #1e1e1e;
            border-radius: 8px;
            border: 1px dashed #444;
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="logo-container">
            <img src="LIBRARY_LOGO.png" alt="Logo">
        </div>  
        <nav>
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="reservation-section.php">Reservations</a></li>
                <li><a href="borrow-books.php">Borrowed Books</a></li>
                <li><a href="bookmark.php">Bookmarks</a></li>
            </ul>
        </nav>
        <div class="prof-notif-icon" >
            <img class="notification-icons" src="NOTIF-ICON.png" alt="Notification">
            <button id="profileBtn" class="profile-btn" title="View Profile">
                <img class="profile-icons" src="profile-icon.png" alt="Profile">
            </button>
            <a href="../backend/logout.php" style="margin-left: 15px; color: white; text-decoration: none; font-weight: bold;">Logout</a>
        </div>  
    </header>

    <div class="recommend-reads" style="margin-top: 40px;">
        <div class="recommended"><h1>My Saved Books</h1>
            <p>All the books you've bookmarked for later.</p>
        </div> 
    </div>

    <div class="product-container">
        <?php if ($favorites_result->num_rows > 0): ?>
            <?php while($book = $favorites_result->fetch_assoc()): ?>
                <?php
                    $image_path = !empty($book['book_image_path']) ? '../' . htmlspecialchars($book['book_image_path']) : 'placeholder.png';
                    $has_borrowed = in_array($book['book_id'], $borrowed_book_ids);
                    $has_reserved = in_array($book['book_id'], $reserved_book_ids);
                ?>
                <div class="product-card">
                    <a href="book-details.php?id=<?php echo $book['book_id']; ?>" class="book-card-link">
                        <div class="card-clickable-area">
                            <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                            <div class="card-info">
                                <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                                <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                                <p class="book-quantity"><strong>Available Copies:</strong> <?php echo htmlspecialchars($book['available_copies']); ?></p>
                                <?php if (!empty($book['category'])): ?>
                                <p class="book-category">Category: <?php echo htmlspecialchars($book['category']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <div class="button-group">
                        <?php if ($has_borrowed): ?>
                            <button class="borrowed-btn" disabled>Borrowed</button>
                        <?php elseif ($has_reserved): ?>
                            <button class="reserved-btn" disabled>Reserved</button>
                        <?php elseif ($book['available_copies'] > 0): ?>
                            <button class="open-borrow-modal-btn" data-book-id="<?php echo $book['book_id']; ?>" data-book-name="<?php echo htmlspecialchars($book['book_name']); ?>">Borrow Now</button>
                        <?php else: ?>
                            <form action="../backend/create_reservation.php" method="POST" style="flex-grow: 1;">
                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                <button type="submit" class="reserve-btn">Reserve</button>
                            </form>
                        <?php endif; ?>
                        
                        <form class="favorite-form" action="../backend/toggle_favorite.php" method="POST" onclick="event.stopPropagation();">
                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                            <button type="submit" class="favorite-btn is-favorite">&#9733;</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-message">You have not saved any books yet. Click the star icon on a book to add it here!</p>
        <?php endif; ?>
    </div>

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
                        <li><a href="my_reservations.php">Reservations</a></li>
                        <li><a href="borrow-books.php">Borrowed Books</a></li>
                        <li><a href="bookmark.php">Bookmarks</a></li>
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

    <div id="borrowModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn">&times;</span>
            <div class="modal-body">
                <div class="modal-title">Borrow Book</div>
                <div class="modal-info">
                    <p>You are requesting to borrow:</p>
                    <p><strong id="modalBookName"></strong></p>
                </div>
                <form id="borrowForm" class="modal-form" action="../backend/borrow_book.php" method="POST">
                    <input type="hidden" id="modalBookId" name="book_id">
                    <label for="returnDate">Select a return date:</label>
                    <input type="date" id="returnDate" name="return_date" required>
                    <button type="submit">Confirm Borrow Request</button>
                </form>
            </div>
        </div>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="profileModalCloseBtn">&times;</span>
            <div class="profile-modal-header">
                <h2>Your Profile</h2>
            </div>
            <div class="profile-modal-body">
                <div class="profile-info-row">
                    <strong>Full Name:</strong>
                    <span><?php echo htmlspecialchars($user_details['fullname'] ?? 'N/A'); ?></span>
                </div>
                <div class="profile-info-row">
                    <strong>Student ID:</strong>
                    <span><?php echo htmlspecialchars($_SESSION['id_number']); ?></span>
                </div>
                <div class="profile-info-row">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($user_details['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="profile-info-row">
                    <strong>Program & Year:</strong>
                    <span><?php echo htmlspecialchars($user_details['program_and_year'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div class="profile-modal-footer">
                <button id="openEditProfileBtn" class="edit-profile-btn">Edit Profile</button>
            </div>
        </div>
    </div>

    <div id="editProfileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="editProfileModalCloseBtn">&times;</span>
            <div class="profile-modal-header">
                <h2>Edit Your Profile</h2>
            </div>
            <form id="editProfileForm" class="edit-modal-form" action="../backend/update_profile.php" method="POST">
                <div class="form-group">
                    <label for="editFullName">Full Name</label>
                    <input type="text" id="editFullName" name="fullname" value="<?php echo htmlspecialchars($user_details['fullname'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="editProgramAndYear">Program & Year</label>
                    <input type="text" id="editProgramAndYear" name="program_and_year" value="<?php echo htmlspecialchars($user_details['program_and_year'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Student ID</label>
                    <div class="read-only-field"><?php echo htmlspecialchars($_SESSION['id_number']); ?></div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <div class="read-only-field"><?php echo htmlspecialchars($user_details['email'] ?? ''); ?></div>
                </div>
                <div class="edit-modal-buttons">
                    <button type="button" id="cancelEditBtn" class="cancel-btn">Cancel</button>
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const borrowModal = document.getElementById("borrowModal");
            if (borrowModal) {
                const borrowModalCloseBtn = borrowModal.querySelector(".close-btn");
                const bookNameEl = document.getElementById("modalBookName");
                const bookIdInput = document.getElementById("modalBookId");
                const returnDateInput = document.getElementById("returnDate");
                const productContainer = document.querySelector(".product-container");

                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                const maxDate = new Date(today);
                maxDate.setDate(maxDate.getDate() + 30);
                const formatDate = (date) => date.toISOString().split('T')[0];
                if (returnDateInput) {
                    returnDateInput.setAttribute('min', formatDate(tomorrow));
                    returnDateInput.setAttribute('max', formatDate(maxDate));
                }

                if (productContainer) {
                    productContainer.addEventListener('click', (event) => {
                        if (event.target.classList.contains('open-borrow-modal-btn')) {
                            event.stopPropagation();
                            const bookId = event.target.dataset.bookId;
                            const bookName = event.target.dataset.bookName;
                            bookNameEl.textContent = bookName;
                            bookIdInput.value = bookId;
                            borrowModal.style.display = "flex";
                        }
                    });
                }
                const closeBorrowModal = () => { borrowModal.style.display = "none"; };
                if (borrowModalCloseBtn) {
                    borrowModalCloseBtn.onclick = closeBorrowModal;
                }
            }

            const profileModal = document.getElementById("profileModal");
            const profileBtn = document.getElementById("profileBtn");
            const profileModalCloseBtn = document.getElementById("profileModalCloseBtn");
            
            const editProfileModal = document.getElementById("editProfileModal");
            const openEditProfileBtn = document.getElementById("openEditProfileBtn");
            const editProfileModalCloseBtn = document.getElementById("editProfileModalCloseBtn");
            const cancelEditBtn = document.getElementById("cancelEditBtn");

            if (profileBtn && profileModal) {
                profileBtn.onclick = () => { profileModal.style.display = "flex"; };
                if(profileModalCloseBtn) profileModalCloseBtn.onclick = () => { profileModal.style.display = "none"; };
            }
            
            if (editProfileModal && openEditProfileBtn) {
                openEditProfileBtn.onclick = () => {
                    profileModal.style.display = "none";
                    editProfileModal.style.display = "flex";
                };
                const closeEditModal = () => { editProfileModal.style.display = "none"; };
                if(editProfileModalCloseBtn) editProfileModalCloseBtn.onclick = closeEditModal;
                if(cancelEditBtn) cancelEditBtn.onclick = closeEditModal;
            }

            window.onclick = (event) => { 
                if (event.target == borrowModal) { borrowModal.style.display = "none"; }
                if (event.target == profileModal) { profileModal.style.display = "none"; }
                if (event.target == editProfileModal) { editProfileModal.style.display = "none"; }
            };
        });
    </script>

</body>
</html>