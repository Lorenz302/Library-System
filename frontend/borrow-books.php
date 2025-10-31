<?php
// Start the session to check for login status
session_start();

// If not logged in as a student, redirect to the login page
if (!isset($_SESSION["id_number"]) || $_SESSION['role'] !== 'student') {
    header("location: index.html");
    exit;
}

// Include the database connection
include '../backend/db_connect.php';

// --- FETCH USER DETAILS FOR PROFILE MODAL ---
$current_user_id = $_SESSION['id_number'];
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_details = $user_result->fetch_assoc();
$stmt_user->close();


// --- FETCH ALL BORROW-RELATED BOOKS FOR THE CURRENT USER ---
$sql = "SELECT br.borrow_status, br.borrow_date, br.due_date, br.return_date, b.book_name, b.book_description, b.book_image_path
        FROM borrow_requests br
        JOIN books b ON br.book_id = b.book_id
        WHERE br.id_number = ? AND br.borrow_status IN ('Approved', 'Borrowed', 'Returned')
        ORDER BY br.borrow_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

// --- ORGANIZE ALL BOOKS INTO ARRAYS ---
$currently_borrowed_all = [];
$overdue_books_all = [];
$returned_books_all = [];
$today = new DateTime();
$today->setTime(0,0); // Set time to midnight for accurate date comparison

while ($book = $result->fetch_assoc()) {
    if ($book['borrow_status'] === 'Returned') {
        $returned_books_all[] = $book;
    } else if ($book['borrow_status'] === 'Borrowed') {
        if (!empty($book['due_date'])) {
            $due_date = new DateTime($book['due_date']);
            if ($due_date < $today) {
                $overdue_books_all[] = $book;
            } else {
                $currently_borrowed_all[] = $book;
            }
        } else {
            $currently_borrowed_all[] = $book;
        }
    } else if ($book['borrow_status'] === 'Approved') {
        $currently_borrowed_all[] = $book;
    }
}
$stmt->close();
$conn->close();

// ***** START OF PAGINATION LOGIC *****
$books_per_page_cards = 4;
$books_per_page_list = 8;

$ob_page = isset($_GET['ob_page']) ? (int)$_GET['ob_page'] : 1;
$cb_page = isset($_GET['cb_page']) ? (int)$_GET['cb_page'] : 1;
$rh_page = isset($_GET['rh_page']) ? (int)$_GET['rh_page'] : 1;

$total_overdue = count($overdue_books_all);
$total_pages_overdue = ceil($total_overdue / $books_per_page_cards);
$total_current = count($currently_borrowed_all);
$total_pages_current = ceil($total_current / $books_per_page_cards);
$total_returned = count($returned_books_all);
$total_pages_returned = ceil($total_returned / $books_per_page_list);

$offset_overdue = ($ob_page - 1) * $books_per_page_cards;
$offset_current = ($cb_page - 1) * $books_per_page_cards;
$offset_returned = ($rh_page - 1) * $books_per_page_list;

$paginated_overdue = array_slice($overdue_books_all, $offset_overdue, $books_per_page_cards);
$paginated_current = array_slice($currently_borrowed_all, $offset_current, $books_per_page_cards);
$paginated_returned = array_slice($returned_books_all, $offset_returned, $books_per_page_list);
// ***** END OF PAGINATION LOGIC *****


// --- Reusable Pagination Function ---
function generate_pagination($current_page, $total_pages, $param_name) {
    if ($total_pages <= 1) {
        return;
    }

    $query_params = $_GET;
    echo '<div class="pagination">';

    if ($current_page > 1) {
        $query_params[$param_name] = $current_page - 1;
        echo '<a href="?' . http_build_query($query_params) . '">&laquo; Prev</a>';
    }

    for ($i = 1; $i <= $total_pages; $i++) {
        $query_params[$param_name] = $i;
        $active_class = ($i == $current_page) ? 'active' : '';
        echo '<a href="?' . http_build_query($query_params) . '" class="' . $active_class . '">' . $i . '</a>';
    }

    if ($current_page < $total_pages) {
        $query_params[$param_name] = $current_page + 1;
        echo '<a href="?' . http_build_query($query_params) . '">Next &raquo;</a>';
    }

    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="borrow-books.css"> 
    <title>My Borrowed Books</title>
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

    <main class="main-content">
        <!-- Section 1: OVERDUE BOOKS -->
        <div class="book-section overdue">
            <div class="section-header">
                <h1>Overdue Books</h1>
                <p>Please return these books to the library as soon as possible.</p>
            </div>
            <div class="product-container">
                <?php if (empty($paginated_overdue)): ?>
                    <p class="empty-message">You have no overdue books. Great job!</p>
                <?php else: ?>
                    <?php foreach ($paginated_overdue as $book): ?>
                        <div class="product-card overdue-card">
                            <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                            <div class="card-info">
                                <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                                <div class="date-info">
                                    <span><strong>Borrowed:</strong> <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></span>
                                    <span class="due-date"><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($book['due_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php generate_pagination($ob_page, $total_pages_overdue, 'ob_page'); ?>
        </div>

        <!-- Section 2: CURRENTLY BORROWED & APPROVED BOOKS -->
        <div class="book-section">
            <div class="section-header">
                <h1>Currently Borrowed</h1>
                <p>Books you have borrowed or are approved for pickup.</p>
            </div>
            <div class="product-container">
                <?php if (empty($paginated_current)): ?>
                    <p class="empty-message">You have no active borrows or books waiting for pickup.</p>
                <?php else: ?>
                    <?php foreach ($paginated_current as $book): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                            <div class="card-info">
                                <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                                <?php if ($book['borrow_status'] == 'Approved'): ?>
                                    <p class="pickup-status">Status: Ready for Pickup</p>
                                <?php endif; ?>
                                <div class="date-info">
                                    <span><strong>Borrowed:</strong> <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></span>
                                    <span><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($book['due_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php generate_pagination($cb_page, $total_pages_current, 'cb_page'); ?>
        </div>

        <!-- Section 3: RETURNED BOOKS HISTORY (NEW DESIGN) -->
        <div class="book-section">
            <div class="section-header">
                <h1>Returned History</h1>
                <p>A history of the books you have successfully returned.</p>
            </div>
            <div class="history-container">
                <?php if (empty($paginated_returned)): ?>
                    <p class="empty-message">You have not returned any books yet.</p>
                <?php else: ?>
                    <div class="history-list">
                        <div class="history-item header-row">
                            <div class="book-details">Book Title</div>
                            <div class="history-dates"><span>Borrowed</span><span>Returned</span></div>
                        </div>
                        <?php foreach ($paginated_returned as $book): ?>
                            <div class="history-item">
                                <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                                <div class="book-details"><h3><?php echo htmlspecialchars($book['book_name']); ?></h3></div>
                                <div class="history-dates">
                                    <span><?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></span>
                                    <span><?php echo date('M d, Y', strtotime($book['return_date'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php generate_pagination($rh_page, $total_pages_returned, 'rh_page'); ?>
        </div>
    </main>

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
    
    <!-- Profile Modals Copied from home.php -->
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
                if (event.target == profileModal) { profileModal.style.display = "none"; }
                if (event.target == editProfileModal) { editProfileModal.style.display = "none"; }
            };
        });
    </script>
</body>
</html>