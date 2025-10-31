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

// Get the current user's ID from the session
$current_user_id = $_SESSION['id_number'];

// --- FETCH USER DETAILS FOR PROFILE MODAL ---
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_details = $user_result->fetch_assoc();
$stmt_user->close();


// --- 1. Fetch BORROWED books (On Loan) ---
$sql_borrowed = "SELECT b.book_name, b.book_description, b.book_image_path, br.due_date
                 FROM borrow_requests br
                 JOIN books b ON br.book_id = b.book_id
                 WHERE br.id_number = ? AND br.borrow_status = 'Borrowed'
                 ORDER BY br.due_date ASC";
$stmt_borrowed = $conn->prepare($sql_borrowed);
$stmt_borrowed->bind_param("s", $current_user_id);
$stmt_borrowed->execute();
$borrowed_result = $stmt_borrowed->get_result();

// --- 2. Fetch all items READY FOR PICKUP ---
$sql_approved_borrows = "SELECT br.borrow_id as request_id, 'borrow' as type, b.book_name, b.book_description, b.book_image_path, NULL as pickup_expiry_date
                         FROM borrow_requests br
                         JOIN books b ON br.book_id = b.book_id
                         WHERE br.id_number = ? AND br.borrow_status = 'Approved'";
$stmt_approved_borrows = $conn->prepare($sql_approved_borrows);
$stmt_approved_borrows->bind_param("s", $current_user_id);
$stmt_approved_borrows->execute();
$approved_borrows_result = $stmt_approved_borrows->get_result();

$sql_available_reservations = "SELECT rr.reservation_id as request_id, 'reservation' as type, b.book_name, b.book_description, b.book_image_path, rr.pickup_expiry_date
                               FROM reservation_requests rr
                               JOIN books b ON rr.book_id = b.book_id
                               WHERE rr.id_number = ? AND rr.reservation_status = 'Available'";
$stmt_available_reservations = $conn->prepare($sql_available_reservations);
$stmt_available_reservations->bind_param("s", $current_user_id);
$stmt_available_reservations->execute();
$available_reservations_result = $stmt_available_reservations->get_result();

// --- 3. Fetch all PENDING items ---
$sql_pending_borrows = "SELECT br.borrow_id as request_id, 'borrow' as type, b.book_name, b.book_description, b.book_image_path
                        FROM borrow_requests br
                        JOIN books b ON br.book_id = b.book_id
                        WHERE br.id_number = ? AND br.borrow_status = 'Pending'";
$stmt_pending_borrows = $conn->prepare($sql_pending_borrows);
$stmt_pending_borrows->bind_param("s", $current_user_id);
$stmt_pending_borrows->execute();
$pending_borrows_result = $stmt_pending_borrows->get_result();

$sql_pending_reservations = "SELECT rr.reservation_id as request_id, 'reservation' as type, b.book_name, b.book_description, b.book_image_path, rr.book_id
                             FROM reservation_requests rr
                             JOIN books b ON rr.book_id = b.book_id
                             WHERE rr.id_number = ? AND rr.reservation_status = 'Pending'
                             ORDER BY rr.reservation_date ASC";
$stmt_pending_reservations = $conn->prepare($sql_pending_reservations);
$stmt_pending_reservations->bind_param("s", $current_user_id);
$stmt_pending_reservations->execute();
$pending_reservations_result = $stmt_pending_reservations->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservation</title>
    <link rel="stylesheet" href="home.css">
    <style>
        .info-bar, .status-message, .queue-position {
            padding: 10px;
            text-align: center;
            border-radius: 0 0 6px 6px;
            font-size: 0.9rem;
            margin-top: auto;
            color: white;
        }
        .info-bar { background-color: #3498db; }
        .status-message { background-color: #2ecc71; }
        .queue-position { background-color: #f39c12; }
        .cancel-button {
            background-color: #e74c3c !important;
            color: white !important;
            width: 100%;
        }
        .cancel-button:hover { background-color: #c0392b !important; }
        .empty-message {
            text-align: center;
            grid-column: 1 / -1;
            color: #ccc;
            padding: 40px;
            background-color: #1e1e1e;
            border-radius: 8px;
            border: 1px dashed #444;
        }
        .button-group {
            margin-top: 10px;
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

    <!-- ========================= ON LOAN SECTION =============================== -->
    <div class="recommend-reads" style="margin-top: 40px;">
        <div class="recommended"><h1>On Loan</h1>
            <p>These are the books you currently have checked out.</p>
        </div> 
    </div>
    
    <div class="product-container">
        <?php if ($borrowed_result->num_rows > 0): ?>
            <?php while($book = $borrowed_result->fetch_assoc()): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                    </div>
                    <div class="info-bar">
                        <strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($book['due_date'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-message">You have no books currently on loan.</p>
        <?php endif; ?>
    </div>

    <!-- ========================= READY FOR PICKUP SECTION =============================== -->
    <div class="recommend-reads" style="margin-top: 50px;">
        <div class="recommended"><h1>Ready for Pickup</h1>
            <p>These items are being held for you at the library desk.</p>
        </div> 
    </div>
    
    <div class="product-container">
        <?php if ($approved_borrows_result->num_rows > 0 || $available_reservations_result->num_rows > 0): ?>
            <?php while($book = $approved_borrows_result->fetch_assoc()): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                    </div>
                    <div class="status-message">Approved! Please pick up within 3 days.</div>
                </div>
            <?php endwhile; ?>
            <?php while($book = $available_reservations_result->fetch_assoc()): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                    </div>
                    <div class="status-message">
                        Available! Pick up by: <?php echo date('F j, Y, g:i a', strtotime($book['pickup_expiry_date'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-message">You have no items ready for pickup.</p>
        <?php endif; ?>
    </div>

    <!-- ========================= PENDING REQUESTS & RESERVATIONS SECTION =============================== -->
    <div class="recommend-reads" style="margin-top: 50px;">
        <div class="recommended"><h1>Pending Requests & Reservations</h1>
            <p>You are on the waiting list for these items.</p>
        </div> 
    </div>
    
    <div class="product-container">
        <?php if ($pending_borrows_result->num_rows > 0 || $pending_reservations_result->num_rows > 0): ?>
            <?php while($book = $pending_borrows_result->fetch_assoc()): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                    </div>
                    <div class="queue-position">Awaiting Librarian Approval</div>
                    <div class="button-group">
                        <form action="../backend/cancel_request.php" method="POST" style="width: 100%;">
                            <input type="hidden" name="request_id" value="<?php echo $book['request_id']; ?>">
                            <input type="hidden" name="request_type" value="borrow">
                            <button type="submit" class="cancel-button">Cancel Request</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php while($book = $pending_reservations_result->fetch_assoc()): ?>
                <?php
                    $stmt_queue = $conn->prepare("SELECT COUNT(*) as position FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' AND reservation_date <= (SELECT reservation_date FROM reservation_requests WHERE reservation_id = ?)");
                    $stmt_queue->bind_param("ii", $book['book_id'], $book['request_id']);
                    $stmt_queue->execute();
                    $position = $stmt_queue->get_result()->fetch_assoc()['position'];
                    $stmt_queue->close();
                ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars(!empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($book['book_name']); ?>">
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p><?php echo htmlspecialchars($book['book_description']); ?></p>
                    </div>
                    <div class="queue-position">You are #<?php echo $position; ?> in the queue.</div>
                    <div class="button-group">
                        <form action="../backend/cancel_request.php" method="POST" style="width: 100%;">
                            <input type="hidden" name="request_id" value="<?php echo $book['request_id']; ?>">
                            <input type="hidden" name="request_type" value="reservation">
                            <button type="submit" class="cancel-button">Cancel Reservation</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-message">You have no pending requests or reservations.</p>
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