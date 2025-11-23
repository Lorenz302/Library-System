<?php
// frontend/borrow-books.php
session_start();

if (!isset($_SESSION["id_number"]) || $_SESSION['role'] !== 'student') {
    header("location: index.html");
    exit;
}

include '../backend/db_connect.php';

$current_user_id = $_SESSION['id_number'];
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
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="borrow-books.css"> 
    <title>My Borrowed Books</title>
    <style>
        /* Styles for JS Pagination Controls */
        .js-pagination { margin-top: 20px; text-align: center; }
        .js-pagination button {
            background: #1f2937; color: #d1d5db; border: 1px solid #4b5563;
            padding: 8px 12px; margin: 0 2px; border-radius: 4px; cursor: pointer;
        }
        .js-pagination button.active { background-color: #3b82f6; border-color: #3b82f6; color: white; }
        .js-pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Overdue Alert Box */
        #overdue-alert {
            background-color: #e74c3c; color: white; padding: 15px;
            border-radius: 8px; margin-bottom: 20px; text-align: center;
            font-weight: bold; display: none; /* Hidden by default */
            border: 2px solid #c0392b;
        }
        .pickup-status { color: #2ecc71; font-weight: bold; margin-top: 5px; }
        
        /* Status Badges for Table */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .status-returned { background-color: rgba(52, 211, 153, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.2); }
        .status-rejected { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-expired { background-color: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.2); }

        /* System Time Display */
        .system-time-container {
            text-align: center; margin-top: 20px; margin-bottom: 10px;
            color: #aaa; font-size: 0.9rem;
        }
        #server-clock { color: #fff; font-weight: bold; }

        /* History Table Styles */
        .history-table-container {
            background-color: #1e1e1e;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #333;
            margin-top: 10px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            color: #e0e0e0;
        }
        .history-table th, .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        .history-table th {
            background-color: #2c2c2c;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: #aaa;
        }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:hover { background-color: #2a2a2a; }
        .book-thumb-mini { width: 40px; height: 60px; object-fit: cover; border-radius: 4px; vertical-align: middle; margin-right: 10px; }
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
            <button id="profileBtn" class="profile-btn" title="View Profile">
                <img class="profile-icons" src="profile-icon.png" alt="Profile">
            </button>
            <a href="../backend/logout.php" style="margin-left: 15px; color: white; text-decoration: none; font-weight: bold;">Logout</a>
        </div>  
    </header>

    <main class="main-content">
        
        <!-- NEW: Server Time Display -->
        <div class="system-time-container">
            System Date: <span id="server-clock">Syncing...</span>
        </div>

        <!-- OVERDUE ALERT BANNER -->
        <div id="overdue-alert">
            ⚠️ WARNING: You have overdue books! Please return them to the librarian immediately.
        </div>

        <!-- Section 1: OVERDUE BOOKS -->
        <div class="book-section overdue">
            <div class="section-header">
                <h1>Overdue Books</h1>
                <p>Please return these books to the library as soon as possible.</p>
            </div>
            <div class="product-container" id="container-overdue">
                <p class="empty-message">Loading...</p>
            </div>
            <div id="pagination-overdue" class="js-pagination"></div>
        </div>

        <!-- Section 2: CURRENTLY BORROWED & APPROVED BOOKS -->
        <div class="book-section">
            <div class="section-header">
                <h1>Currently Borrowed</h1>
                <p>Books you have borrowed or are approved for pickup.</p>
            </div>
            <div class="product-container" id="container-current">
                <p class="empty-message">Loading...</p>
            </div>
            <div id="pagination-current" class="js-pagination"></div>
        </div>

        <!-- Section 3: RETURNED BOOKS HISTORY (TABLE LAYOUT) -->
        <div class="book-section">
            <div class="section-header">
                <h1>Returned History</h1>
                <p>A history of the books you have successfully returned or cancelled.</p>
            </div>
            
            <div class="history-table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Date Borrowed</th>
                            <th>Date Returned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="container-returned">
                        <tr><td colspan="4" style="text-align:center; padding:20px;">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div id="pagination-returned" class="js-pagination"></div>
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
                    <div class="footer-bottom-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Cookies Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Profile Modals -->
    <div id="profileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="profileModalCloseBtn">&times;</span>
            <div class="profile-modal-header"><h2>Your Profile</h2></div>
            <div class="profile-modal-body">
                <div class="profile-info-row"><strong>Full Name:</strong> <span><?php echo htmlspecialchars($user_details['fullname'] ?? 'N/A'); ?></span></div>
                <div class="profile-info-row"><strong>Student ID:</strong> <span><?php echo htmlspecialchars($_SESSION['id_number']); ?></span></div>
                <div class="profile-info-row"><strong>Email:</strong> <span><?php echo htmlspecialchars($user_details['email'] ?? 'N/A'); ?></span></div>
                <div class="profile-info-row"><strong>Program & Year:</strong> <span><?php echo htmlspecialchars($user_details['program_and_year'] ?? 'N/A'); ?></span></div>
            </div>
            <div class="profile-modal-footer"><button id="openEditProfileBtn" class="edit-profile-btn">Edit Profile</button></div>
        </div>
    </div>

    <div id="editProfileModal" class="modal">
        <div class="modal-content-wrapper">
            <span class="close-btn" id="editProfileModalCloseBtn">&times;</span>
            <div class="profile-modal-header"><h2>Edit Your Profile</h2></div>
            <form id="editProfileForm" class="edit-modal-form" action="../backend/update_profile.php" method="POST">
                <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="<?php echo htmlspecialchars($user_details['fullname'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Program & Year</label><input type="text" name="program_and_year" value="<?php echo htmlspecialchars($user_details['program_and_year'] ?? ''); ?>" required></div>
                <div class="edit-modal-buttons"><button type="button" id="cancelEditBtn" class="cancel-btn">Cancel</button><button type="submit" class="save-btn">Save Changes</button></div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Modal Logic ---
            const profileModal = document.getElementById("profileModal");
            const editProfileModal = document.getElementById("editProfileModal");
            const profileBtn = document.getElementById("profileBtn");

            if (profileBtn) profileBtn.onclick = () => profileModal.style.display = "flex";
            if (document.getElementById("profileModalCloseBtn")) document.getElementById("profileModalCloseBtn").onclick = () => profileModal.style.display = "none";
            
            if (document.getElementById("openEditProfileBtn")) {
                document.getElementById("openEditProfileBtn").onclick = () => {
                    profileModal.style.display = "none"; editProfileModal.style.display = "flex";
                };
            }
            if (document.getElementById("editProfileModalCloseBtn")) document.getElementById("editProfileModalCloseBtn").onclick = () => editProfileModal.style.display = "none";
            if (document.getElementById("cancelEditBtn")) document.getElementById("cancelEditBtn").onclick = () => editProfileModal.style.display = "none";

            window.onclick = (event) => { 
                if (event.target == profileModal) profileModal.style.display = "none";
                if (event.target == editProfileModal) editProfileModal.style.display = "none";
            };

            // ================================================================
            // ============ REAL-TIME & PAGINATION LOGIC ======================
            // ================================================================
            
            // State for Pagination
            const state = {
                overdue: { page: 1, limit: 4, data: [] },
                current: { page: 1, limit: 4, data: [] },
                returned: { page: 1, limit: 8, data: [] }
            };

            async function fetchData() {
                try {
                    const response = await fetch('../backend/fetch_my_borrow_history_api.php');
                    const data = await response.json();

                    // 1. Update Server Time Display
                    if(data.server_time) {
                        document.getElementById('server-clock').textContent = data.server_time;
                    }

                    // 2. Update State Data
                    state.overdue.data = data.overdue;
                    state.current.data = data.current;
                    state.returned.data = data.returned;

                    // 3. Render All Sections
                    renderSection('overdue', 'container-overdue', 'pagination-overdue');
                    renderSection('current', 'container-current', 'pagination-current');
                    renderSection('returned', 'container-returned', 'pagination-returned', true); // true for list view

                    // 4. Show/Hide Overdue Alert
                    const alertBox = document.getElementById('overdue-alert');
                    if (data.overdue.length > 0) {
                        alertBox.style.display = 'block';
                    } else {
                        alertBox.style.display = 'none';
                    }

                } catch (error) { console.error("Fetch error:", error); }
            }

            function renderSection(type, containerId, paginationId, isListView = false) {
                const container = document.getElementById(containerId);
                const pagination = document.getElementById(paginationId);
                const config = state[type];
                const totalPages = Math.ceil(config.data.length / config.limit);

                // Ensure current page is valid
                if (config.page > totalPages) config.page = totalPages || 1;

                // Slice Data for current Page
                const start = (config.page - 1) * config.limit;
                const paginatedItems = config.data.slice(start, start + config.limit);

                // Generate HTML
                let html = '';
                if (paginatedItems.length === 0) {
                    if(isListView) {
                        html = `<tr><td colspan="4" style="text-align:center; padding:20px; color:#aaa;">No history found.</td></tr>`;
                    } else {
                        html = `<p class="empty-message">No items found.</p>`;
                    }
                } else {
                    paginatedItems.forEach(book => {
                        if (isListView) {
                            // ==================================================
                            // RENDER TABLE ROW (History)
                            // ==================================================
                            const statusClass = 'status-' + book.borrow_status.toLowerCase();
                            
                            html += `
                                <tr>
                                    <td>
                                        <img src="${book.book_image_path}" alt="Cover" class="book-thumb-mini">
                                        <strong>${book.book_name}</strong>
                                    </td>
                                    <td>${book.formatted_borrow_date}</td>
                                    <td>${book.formatted_return_date}</td>
                                    <td><span class="badge ${statusClass}">${book.borrow_status}</span></td>
                                </tr>
                            `;
                        } else {
                            // Render Card View (Overdue & Current)
                            let statusHtml = '';
                            if (book.is_approved) {
                                statusHtml = `<p class="pickup-status">Status: Ready for Pickup</p>`;
                            }
                            
                            const cardClass = type === 'overdue' ? 'product-card overdue-card' : 'product-card';

                            html += `
                                <div class="${cardClass}">
                                    <img src="${book.book_image_path}" alt="${book.book_name}">
                                    <div class="card-info">
                                        <h3>${book.book_name}</h3>
                                        ${statusHtml}
                                        <div class="date-info">
                                            <span><strong>Borrowed:</strong> ${book.formatted_borrow_date}</span>
                                            <span class="due-date"><strong>Due Date:</strong> ${book.formatted_due_date}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }
                
                if (container.innerHTML !== html) container.innerHTML = html;

                renderPagination(pagination, totalPages, config);
            }

            function renderPagination(container, totalPages, config) {
                if (totalPages <= 1) {
                    container.innerHTML = '';
                    return;
                }

                let html = '';
                
                // Prev Button
                html += `<button ${config.page === 1 ? 'disabled' : ''} onclick="changePage('${container.id}', -1)">« Prev</button>`;

                // Number Buttons
                for (let i = 1; i <= totalPages; i++) {
                    const activeClass = (i === config.page) ? 'active' : '';
                    html += `<button class="${activeClass}" onclick="setPage('${container.id}', ${i})">${i}</button>`;
                }

                // Next Button
                html += `<button ${config.page === totalPages ? 'disabled' : ''} onclick="changePage('${container.id}', 1)">Next »</button>`;

                container.innerHTML = html;
            }

            // Expose Pagination Functions
            window.changePage = function(elementId, direction) {
                const type = elementId.split('-')[1]; 
                state[type].page += direction;
                const isList = (type === 'returned');
                renderSection(type, `container-${type}`, elementId, isList);
            };

            window.setPage = function(elementId, pageNum) {
                const type = elementId.split('-')[1];
                state[type].page = pageNum;
                const isList = (type === 'returned');
                renderSection(type, `container-${type}`, elementId, isList);
            };

            // Init
            fetchData();
            setInterval(fetchData, 3000); // Poll every 3 seconds
        });
    </script>
</body>
</html>