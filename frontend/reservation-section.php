<?php
// frontend/reservation-section.php
session_start();
include '../backend/db_connect.php';

if (!isset($_SESSION["id_number"])) {
    header("location: index.html");
    exit;
}

$current_user_id = $_SESSION['id_number'];

// Fetch Profile Data (Still needed for modal)
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_details = $user_result->fetch_assoc();
$stmt_user->close();
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
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
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
        .button-group { margin-top: 10px; }
        
        /* Loader */
        #loading-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%; 
            background: rgba(0,0,0,0.5); z-index:9999; display:none;
            align-items: center; justify-content: center; color: white;
        }
    </style>
</head>
<body>

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
        <div class="prof-notif-icon" >
            <button id="profileBtn" class="profile-btn" title="View Profile"><img class="profile-icons" src="profile-icon.png" alt="Profile"></button>
            <a href="../backend/logout.php" style="margin-left: 15px; color: white; text-decoration: none; font-weight: bold;">Logout</a>
        </div>  
    </header>

    <!-- ON LOAN SECTION -->
    <div class="recommend-reads" style="margin-top: 40px;">
        <div class="recommended"><h1>On Loan</h1><p>These are the books you currently have checked out.</p></div> 
    </div>
    <div class="product-container" id="container-on-loan">
        <p class="empty-message">Loading...</p>
    </div>

    <!-- READY FOR PICKUP SECTION -->
    <div class="recommend-reads" style="margin-top: 50px;">
        <div class="recommended"><h1>Ready for Pickup</h1><p>These items are being held for you at the library desk.</p></div> 
    </div>
    <div class="product-container" id="container-pickup">
        <p class="empty-message">Loading...</p>
    </div>

    <!-- PENDING SECTION -->
    <div class="recommend-reads" style="margin-top: 50px;">
        <div class="recommended"><h1>Pending Requests & Reservations</h1><p>You are on the waiting list for these items.</p></div> 
    </div>
    <div class="product-container" id="container-pending">
        <p class="empty-message">Loading...</p>
    </div>

    <!-- Profile Modals (Same as Home) -->
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
            // Modal Logic
            const profileModal = document.getElementById("profileModal");
            const profileBtn = document.getElementById("profileBtn");
            const editProfileModal = document.getElementById("editProfileModal");

            if(profileBtn) {
                profileBtn.onclick = () => profileModal.style.display = "flex";
                document.getElementById("profileModalCloseBtn").onclick = () => profileModal.style.display = "none";
            }
            if(editProfileModal) {
                document.getElementById("openEditProfileBtn").onclick = () => {
                    profileModal.style.display = "none"; editProfileModal.style.display = "flex";
                };
                const closeEdit = () => editProfileModal.style.display = "none";
                document.getElementById("editProfileModalCloseBtn").onclick = closeEdit;
                document.getElementById("cancelEditBtn").onclick = closeEdit;
            }
            window.onclick = (e) => {
                if(e.target == profileModal) profileModal.style.display = "none";
                if(e.target == editProfileModal) editProfileModal.style.display = "none";
            };

            // ==========================================================
            // ============ REAL-TIME POLLING LOGIC =====================
            // ==========================================================
            
            const containerLoan = document.getElementById('container-on-loan');
            const containerPickup = document.getElementById('container-pickup');
            const containerPending = document.getElementById('container-pending');

            async function fetchRequests() {
                try {
                    const response = await fetch('../backend/fetch_my_requests_api.php');
                    const data = await response.json();

                    renderSection(containerLoan, data.on_loan, 'loan');
                    renderSection(containerPickup, data.pickup, 'pickup');
                    renderSection(containerPending, data.pending, 'pending');

                } catch (error) { console.error("Polling error:", error); }
            }

            function renderSection(container, items, type) {
                if (!items || items.length === 0) {
                    container.innerHTML = '<p class="empty-message">No items in this section.</p>';
                    return;
                }

                let html = '';
                items.forEach(book => {
                    const imgSrc = book.book_image_path ? `../${book.book_image_path}` : 'placeholder.png';
                    
                    let footer = '';
                    if (type === 'loan') {
                        footer = `<div class="info-bar"><strong>Due Date:</strong> ${book.formatted_due_date}</div>`;
                    } else if (type === 'pickup') {
                        footer = `<div class="status-message">${book.status_msg}</div>`;
                    } else if (type === 'pending') {
                        footer = `
                            <div class="queue-position">${book.status_msg}</div>
                            <div class="button-group">
                                <form action="../backend/cancel_request.php" method="POST" style="width: 100%;">
                                    <input type="hidden" name="request_id" value="${book.borrow_id}">
                                    <input type="hidden" name="request_type" value="${book.type}">
                                    <button type="submit" class="cancel-button">Cancel Request</button>
                                </form>
                            </div>
                        `;
                    }

                    html += `
                        <div class="product-card">
                            <img src="${imgSrc}" alt="${book.book_name}">
                            <div class="card-info">
                                <h3>${book.book_name}</h3>
                                <p>${book.book_description}</p>
                            </div>
                            ${footer}
                        </div>
                    `;
                });
                
                // Only update DOM if content changed to prevent flickering
                if (container.innerHTML !== html) {
                    container.innerHTML = html;
                }
            }

            // Run
            fetchRequests();
            setInterval(fetchRequests, 3000); 
        });
    </script>
</body>
</html>