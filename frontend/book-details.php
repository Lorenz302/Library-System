<?php
// frontend/book-details.php
session_start();
include '../backend/db_connect.php';

// Security Check
if (!isset($_SESSION["id_number"])) {
    header("location: index.html");
    exit;
}

// Validate Book ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: home.php");
    exit;
}

$book_id = $_GET['id'];
$current_user_id = $_SESSION['id_number'];

// 1. Fetch Book Details
$sql = "SELECT * FROM books WHERE book_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header("location: home.php");
    exit;
}

// 2. Fetch User Details (For Profile Modal)
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id);
$stmt_user->execute();
$user_details = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// 3. Initial Status Check (PHP fallback)
$sql_b = "SELECT book_id FROM borrow_requests WHERE id_number = ? AND book_id = ? AND borrow_status IN ('Pending', 'Approved', 'Borrowed')";
$stmt_b = $conn->prepare($sql_b);
$stmt_b->bind_param("si", $current_user_id, $book_id);
$stmt_b->execute();
$has_borrowed = $stmt_b->get_result()->num_rows > 0;
$stmt_b->close();

$sql_r = "SELECT book_id FROM reservation_requests WHERE id_number = ? AND book_id = ? AND reservation_status IN ('Pending', 'Available')";
$stmt_r = $conn->prepare($sql_r);
$stmt_r->bind_param("si", $current_user_id, $book_id);
$stmt_r->execute();
$has_reserved = $stmt_r->get_result()->num_rows > 0;
$stmt_r->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['book_name']); ?> - Details</title>
    <link rel="stylesheet" href="home.css">
    
    <style>
        body { background-color: #121212; color: #ffffff; }
        .details-page-container { max-width: 1100px; margin: 40px auto; padding: 20px; min-height: calc(100vh - 400px); }
        .details-card { display: flex; flex-wrap: wrap; gap: 40px; padding: 40px; background-color: #1e1e1e; border-radius: 12px; border: 1px solid #333; }
        .details-image-section { flex: 1 1 300px; max-width: 350px; }
        .details-image-section img { width: 100%; height: auto; border-radius: 8px; object-fit: cover; border: 1px solid #444; }
        .details-info-section { flex: 2 1 500px; }
        .details-info-section h1 { font-size: 2.8rem; font-weight: bold; color: #c81d14; margin: 0 0 20px 0; }
        .details-info-section .info-item { font-size: 1.1rem; line-height: 1.8; color: #ccc; }
        .details-info-section .info-item strong { color: #ffffff; min-width: 150px; display: inline-block; }
        .description-section { margin-top: 30px; font-size: 1rem; line-height: 1.7; text-align: justify; color: #b0b0b0; }
        
        /* Buttons */
        .action-area { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
        .back-button { padding: 12px 30px; background-color: #333; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.3s; }
        .back-button:hover { background-color: #555; }
        
        .open-borrow-modal-btn { background-color: #c81d14; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 1rem; transition: 0.3s; }
        .open-borrow-modal-btn:hover { background-color: #a5120f; }
        .reserve-btn { background-color: #e67e22; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 1rem; }
        .reserve-btn:hover { background-color: #d35400; }
        .borrowed-btn, .reserved-btn { background-color: #555; color: #aaa; border: 1px solid #444; padding: 12px 30px; border-radius: 6px; cursor: not-allowed; font-weight: bold; font-size: 1rem; }

        /* Toast Styles */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast { background-color: #333; color: #fff; padding: 15px 20px; margin-bottom: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); display: flex; align-items: center; opacity: 0; transform: translateX(100%); transition: all 0.5s ease; min-width: 250px; }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast.success { border-left: 5px solid #2ecc71; }
        .toast.error { border-left: 5px solid #e74c3c; }
        .toast-content { flex-grow: 1; }
        .toast-close { cursor: pointer; margin-left: 10px; font-weight: bold; }

        @media (max-width: 768px) {
            .details-card { flex-direction: column; align-items: center; text-align: center; padding: 25px; }
            .details-info-section h1 { font-size: 2.2rem; }
            .details-info-section .info-item strong { display: block; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <header class="header">
         <div class="logo-container"><img src="LIBRARY_LOGO.png" alt="Logo"></div>  
        <nav>
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="reservation-section.php">Reservations</a></li>
                <li><a href="borrow-books.php">Borrowed Books</a></li>
                <li><a href="bookmark.php">Bookmarks</a></li>
            </ul>
        </nav>
        <div class="prof-notif-icon">
            <button id="profileBtn" class="profile-btn" title="View Profile"><img class="profile-icons" src="profile-icon.png" alt="Profile"></button>
            <a href="../backend/logout.php" style="margin-left: 15px; color: white; text-decoration: none; font-weight: bold;">Logout</a>
        </div> 
    </header>

    <div class="details-page-container">
        <div class="details-card">
            <div class="details-image-section">
                <?php $img_path = !empty($book['book_image_path']) ? '../' . $book['book_image_path'] : 'placeholder.png'; ?>
                <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Cover">
            </div>

            <div class="details-info-section">
                <h1><?php echo htmlspecialchars($book['book_name']); ?></h1>
                
                <p class="info-item"><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                <p class="info-item"><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></p>
                <p class="info-item"><strong>Publication Year:</strong> <?php echo htmlspecialchars($book['publication_year']); ?></p>
                
                <!-- Real-time Quantity -->
                <p class="info-item"><strong>Available Copies:</strong> <span id="qty-display"><?php echo htmlspecialchars($book['available_copies']); ?></span></p>

                <div class="description-section">
                    <p><?php echo nl2br(htmlspecialchars($book['book_description'])); ?></p>
                </div>
                
                <div class="action-area">
                    <a href="home.php" class="back-button">Back to Library</a>

                    <!-- Dynamic Button Group -->
                    <div id="dynamic-buttons">
                        <!-- 1. Disabled Button -->
                        <button id="btn-disabled" class="borrowed-btn" style="<?php echo ($has_borrowed || $has_reserved) ? '' : 'display:none;'; ?>" disabled>
                            <?php echo $has_borrowed ? 'Borrowed' : ($has_reserved ? 'Reserved' : 'Unavailable'); ?>
                        </button>

                        <!-- 2. Borrow Button -->
                        <button id="btn-borrow" class="open-borrow-modal-btn" style="<?php echo (!$has_borrowed && !$has_reserved && $book['available_copies'] > 0) ? '' : 'display:none;'; ?>">
                            Borrow Now
                        </button>
                        
                        <!-- 3. Reserve Button -->
                        <form id="form-reserve" action="../backend/create_reservation.php" method="POST" style="<?php echo (!$has_borrowed && !$has_reserved && $book['available_copies'] == 0) ? '' : 'display:none;'; ?>">
                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                            <button type="submit" class="reserve-btn">Reserve</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
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
                        <li><a href="reservation-section.php">Reservations</a></li>
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
                    <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms</a></div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Borrow Modal -->
    <div id="borrowModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="closeBorrow">&times;</span>
            <div class="modal-body">
                <div class="modal-title">Borrow Book</div>
                <div class="modal-info"><p>You are requesting: <strong><?php echo htmlspecialchars($book['book_name']); ?></strong></p></div>
                <form id="borrowForm" class="modal-form"> <!-- Action Removed for AJAX -->
                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                    <label for="returnDate">Select a return date:</label>
                    <input type="date" id="returnDate" name="return_date" required>
                    <button type="submit">Confirm Borrow Request</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Modals -->
    <div id="profileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="profileModalCloseBtn">&times;</span>
            <div class="profile-modal-header"><h2>Your Profile</h2></div>
            <div class="profile-modal-body">
                <div class="profile-info-row"><strong>Name:</strong> <?php echo htmlspecialchars($user_details['fullname'] ?? 'N/A'); ?></div>
                <div class="profile-info-row"><strong>ID:</strong> <?php echo htmlspecialchars($_SESSION['id_number']); ?></div>
            </div>
            <div class="profile-modal-footer"><button id="openEditProfileBtn" class="edit-profile-btn">Edit Profile</button></div>
        </div>
    </div>

    <div id="editProfileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="editProfileModalCloseBtn">&times;</span>
            <div class="profile-modal-header"><h2>Edit Profile</h2></div>
            <form id="editProfileForm" class="edit-modal-form" action="../backend/update_profile.php" method="POST">
                <label>Full Name</label>
                <input type="text" name="fullname" value="<?php echo htmlspecialchars($user_details['fullname'] ?? ''); ?>" required>
                <label>Program</label>
                <input type="text" name="program_and_year" value="<?php echo htmlspecialchars($user_details['program_and_year'] ?? ''); ?>" required>
                <div class="edit-modal-buttons"><button type="button" id="cancelEditBtn" class="cancel-btn">Cancel</button><button type="submit" class="save-btn">Save</button></div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Modal UI Logic ---
            const borrowModal = document.getElementById("borrowModal");
            const btnBorrow = document.getElementById("btn-borrow");
            const closeBorrow = document.getElementById("closeBorrow");
            
            // Date Validation Setup
            const returnDateInput = document.getElementById("returnDate");
            const borrowForm = document.getElementById('borrowForm');
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
            const maxDate = new Date(today); maxDate.setDate(today.getDate() + 30);
            const formatDate = (date) => date.toISOString().split('T')[0];
            returnDateInput.setAttribute('min', formatDate(tomorrow));
            returnDateInput.setAttribute('max', formatDate(maxDate));

            if(btnBorrow) btnBorrow.onclick = () => borrowModal.style.display = "flex";
            if(closeBorrow) closeBorrow.onclick = () => borrowModal.style.display = "none";
            
            // --- AJAX BORROW SUBMISSION ---
            if(borrowForm) {
                borrowForm.addEventListener('submit', async (e) => {
                    e.preventDefault(); // STOP PAGE REFRESH
                    
                    const d = new Date(returnDateInput.value);
                    if(d < tomorrow || d > maxDate) { 
                        alert('Invalid date. Please select a date between tomorrow and 30 days from now.');
                        return;
                    }

                    const formData = new FormData(borrowForm);
                    const submitBtn = borrowForm.querySelector('button');
                    const originalText = submitBtn.innerText;
                    submitBtn.disabled = true;
                    submitBtn.innerText = "Processing...";

                    try {
                        const response = await fetch('../backend/borrow_book.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();

                        if (result.success) {
                            borrowModal.style.display = 'none';
                            showToast(result.message, "success");
                            updateSingleBook(); // Immediate Update
                        } else {
                            console.error("Backend Error:", result.message);
                            showToast(result.message, "error");
                        }

                    } catch (error) {
                        console.error("Network/JSON Error:", error);
                        showToast("An unexpected error occurred. Check console.", "error");
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.innerText = originalText;
                    }
                });
            }

            // Profile Modals
            const profileModal = document.getElementById("profileModal");
            const editProfileModal = document.getElementById("editProfileModal");
            document.getElementById("profileBtn").onclick = () => profileModal.style.display = "flex";
            document.getElementById("profileModalCloseBtn").onclick = () => profileModal.style.display = "none";
            document.getElementById("openEditProfileBtn").onclick = () => { profileModal.style.display = "none"; editProfileModal.style.display = "flex"; };
            const closeEdit = () => editProfileModal.style.display = "none";
            document.getElementById("editProfileModalCloseBtn").onclick = closeEdit;
            document.getElementById("cancelEditBtn").onclick = closeEdit;

            window.onclick = (e) => {
                if(e.target == borrowModal) borrowModal.style.display = "none";
                if(e.target == profileModal) profileModal.style.display = "none";
                if(e.target == editProfileModal) editProfileModal.style.display = "none";
            };

            // ==================================================
            // ============ REAL-TIME SINGLE BOOK POLL ==========
            // ==================================================
            const currentBookId = <?php echo $book_id; ?>;

            async function updateSingleBook() {
                try {
                    // Reuse API to get latest status
                    const response = await fetch('../backend/fetch_book_status_api.php');
                    const books = await response.json();
                    
                    const book = books.find(b => b.id == currentBookId);
                    
                    if (book) {
                        // 1. Update Qty
                        const qtyEl = document.getElementById('qty-display');
                        if (qtyEl) qtyEl.textContent = book.qty;

                        // 2. Update Buttons
                        const btnDisabled = document.getElementById('btn-disabled');
                        const btnBorrow = document.getElementById('btn-borrow');
                        const formReserve = document.getElementById('form-reserve');

                        if (book.user_status === 'borrowed') {
                            btnDisabled.textContent = 'Borrowed';
                            btnDisabled.style.display = 'inline-block';
                            btnBorrow.style.display = 'none';
                            formReserve.style.display = 'none';
                        } else if (book.user_status === 'reserved') {
                            btnDisabled.textContent = 'Reserved';
                            btnDisabled.style.display = 'inline-block';
                            btnBorrow.style.display = 'none';
                            formReserve.style.display = 'none';
                        } else if (book.qty > 0) {
                            btnDisabled.style.display = 'none';
                            btnBorrow.style.display = 'inline-block';
                            formReserve.style.display = 'none';
                        } else {
                            // Out of stock
                            btnDisabled.style.display = 'none';
                            btnBorrow.style.display = 'none';
                            formReserve.style.display = 'block';
                        }
                    }
                } catch (error) { console.error("Polling error:", error); }
            }

            function showToast(message, type = 'success') {
                const container = document.getElementById('toastContainer');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `<div class="toast-content">${message}</div><span class="toast-close">&times;</span>`;
                container.appendChild(toast);
                setTimeout(() => toast.classList.add('show'), 100);
                setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 5000);
                toast.querySelector('.toast-close').onclick = () => toast.remove();
            }

            // Run
            updateSingleBook();
            setInterval(updateSingleBook, 3000);
        });
    </script>
</body>
</html>