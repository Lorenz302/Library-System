<?php
// Start the session to check for login status
session_start();

// Include the database connection
include '../backend/db_connect.php';

// If not logged in, redirect them to the login page
// Also check if the role is 'librarian', if so, they should not be on the student home page
if (!isset($_SESSION["id_number"]) || $_SESSION['role'] === 'librarian') {
    header("location: index.html");
    exit;
}

// FETCH CURRENT USER'S FULL DETAILS FOR THE PROFILE MODAL
$current_user_id_number = $_SESSION['id_number'];
$user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $current_user_id_number);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_details = $user_result->fetch_assoc();
$stmt_user->close();


// --- START: SEARCH AND FILTER LOGIC ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';

$sql_books = "SELECT * FROM books";
$conditions = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $conditions[] = "(book_name LIKE ? OR author LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}
if (!empty($filter_year)) {
    $conditions[] = "publication_year = ?";
    $params[] = $filter_year;
    $types .= 'i';
}
if (!empty($filter_category)) {
    $conditions[] = "category = ?";
    $params[] = $filter_category;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql_books .= " WHERE " . implode(" AND ", $conditions);
}

$sql_books .= " ORDER BY book_name ASC";

$stmt_books = $conn->prepare($sql_books);
if (!empty($params)) {
    $stmt_books->bind_param($types, ...$params);
}
$stmt_books->execute();
$all_books_result = $stmt_books->get_result();
// --- END: SEARCH AND FILTER LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="home.css">
    <title>Bataan Heroes College Library</title>
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

    <div class="flex-container">
        <div class="flex-item">
            <section class="intro">
                <h1>Bataan Heroes College Library:<br>Your Gateway to Knowledge, Anytime, Anywhere.</h1>
                <p>Discover books, journals, and other resources that support your learning. Stay updated with the latest arrivals and announcements from the library.</p>
                
                <form class="search-container" action="home.php" method="GET">
                    <input type="text" class="text-search" name="search" placeholder="Search by book name or author" value="<?php echo htmlspecialchars($search_term); ?>">
                    
                    <select name="year" class="year-filter">
                        <option value="">All Years</option>
                        <?php
                        include '../backend/db_connect.php';
                        $year_query = "SELECT DISTINCT publication_year FROM books WHERE publication_year IS NOT NULL ORDER BY publication_year DESC";
                        $year_result = $conn->query($year_query);
                        while ($row = $year_result->fetch_assoc()) {
                            $year = $row['publication_year'];
                            $selected = ($year == $filter_year) ? 'selected' : '';
                            echo "<option value='{$year}' {$selected}>{$year}</option>";
                        }
                        ?>
                    </select>
                    
                    <button type="submit">Search Library</button>
                </form>

            </section>
        </div>
        <div class="flex-item">
            <div class="pict">
                <img class="intro-pic" src="LIBRARY_BG.JPG" alt="INTRO PIC">
            </div>
        </div>
    </div>

    <div class="recommend-reads">
        <div class="recommended"><h1> Recommended Reads </h1>
            <p>Explore handpicked books suggested by our librarians.</p>
        </div> 
        
        <div class="button-category">
            <?php
                $sql_categories = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
                $categories_result = $conn->query($sql_categories);
                $view_all_params = $_GET;
                unset($view_all_params['category']);
            ?>
            <a href="home.php?<?php echo http_build_query($view_all_params); ?>" class="btn-category <?php if(empty($filter_category)) echo 'active'; ?>">View all</a>
            <?php while($category_row = $categories_result->fetch_assoc()):
                $current_category = htmlspecialchars($category_row['category']);
                $is_active = ($filter_category == $current_category) ? 'active' : '';
                $query_params = http_build_query(array_merge($_GET, ['category' => $current_category]));
            ?>
                <a href="home.php?<?php echo $query_params; ?>" class="btn-category <?php echo $is_active; ?>">
                    <?php echo $current_category; ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="product-container">
        <?php
            $current_user_id = $_SESSION['id_number'];
            $favorite_book_ids = [];
            $sql_favorites = "SELECT book_id FROM user_favorites WHERE id_number = ?";
            $stmt_favorites = $conn->prepare($sql_favorites);
            $stmt_favorites->bind_param("s", $current_user_id);
            $stmt_favorites->execute();
            $result_favorites = $stmt_favorites->get_result();
            while($fav_row = $result_favorites->fetch_assoc()) { $favorite_book_ids[] = $fav_row['book_id']; }
            $stmt_favorites->close();

            $borrowed_book_ids = [];
            $sql_borrowed = "SELECT book_id FROM borrow_requests WHERE id_number = ? AND borrow_status IN ('Pending', 'Approved')";
            $stmt_borrowed = $conn->prepare($sql_borrowed);
            $stmt_borrowed->bind_param("s", $current_user_id);
            $stmt_borrowed->execute();
            $result_borrowed = $stmt_borrowed->get_result();
            while($borrow_row = $result_borrowed->fetch_assoc()) { $borrowed_book_ids[] = $borrow_row['book_id']; }
            $stmt_borrowed->close();

            $reserved_book_ids = [];
            $sql_reserved = "SELECT book_id FROM reservation_requests WHERE id_number = ? AND reservation_status IN ('Pending', 'Available')";
            $stmt_reserved = $conn->prepare($sql_reserved);
            $stmt_reserved->bind_param("s", $current_user_id);
            $stmt_reserved->execute();
            $result_reserved = $stmt_reserved->get_result();
            while($reserve_row = $result_reserved->fetch_assoc()) { $reserved_book_ids[] = $reserve_row['book_id']; }
            $stmt_reserved->close();
        ?>

        <?php if ($all_books_result->num_rows > 0): ?>
            <?php while($book = $all_books_result->fetch_assoc()): ?>
                <?php
                    $image_path = !empty($book['book_image_path']) ? '../' . htmlspecialchars($book['book_image_path']) : 'placeholder.png';
                    $is_favorite = in_array($book['book_id'], $favorite_book_ids);
                    $favorite_class = $is_favorite ? 'is-favorite' : '';
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
                            <button type="submit" class="favorite-btn <?php echo $favorite_class; ?>"><?php echo $is_favorite ? '&#9733;' : '&#9734;'; ?></button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; grid-column: 1 / -1; margin-top: 20px;">No books found matching your criteria.</p>
        <?php endif; ?>
    </div>
    
    <div class="guidelines">
        <div class="guidelines-header">
          <h4 class="guidelines-title">How to Borrow Books at <br>Bataan Heroes College Library</h4>
          <p class="guidelines-desc">
            Borrowing books is simple and convenient. Just follow these steps to
            request, get approval, and pick up your books on time.
          </p>
        </div>
        <div class="timeline">
          <div class="step">
            <img class="step-image" src="placeholder.png" alt="Search Book" />
            <div class="step-content">
              <div class="step-title">Step 1: Search for a Book</div>
              <div class="step-text">- Go to the library system.<br />- Use the Search Bar to find the book by title, author, or subject.</div>
            </div>
          </div>
          <div class="step">
            <img class="step-image" src="placeholder.png" alt="Borrow Request" />
            <div class="step-content">
              <div class="step-title">Step 2: Submit a Borrow Request</div>
              <div class="step-text">- Click the Borrow button.<br />- Fill out the request form.</div>
            </div>
          </div>
          <div class="step">
            <img class="step-image" src="placeholder.png" alt="Approval" />
            <div class="step-content">
              <div class="step-title">Step 3: Wait for Approval</div>
              <div class="step-text">- Librarian reviews your request.<br />- If the book is available and your account is clear, your request will be approved.</div>
            </div>
          </div>
          <div class="step">
            <img class="step-image" src="placeholder.png" alt="Notification" />
            <div class="step-content">
              <div class="step-title">Step 4: Get Notification</div>
              <div class="step-text">You’ll receive a notification about your request:<ul><li>Approved → “Pick up your book at the library desk.”</li><li>Rejected → “Sorry, your request was not approved.”</li></ul></div>
            </div>
          </div>
          <div class="step">
            <img class="step-image" src="placeholder.png" alt="Pick Up Book" />
            <div class="step-content">
              <div class="step-title">Step 5: Pick Up Your Book</div>
              <div class="step-text">- Visit the library front desk.<br />- Present your ID.</div>
            </div>
          </div>
        </div>
    </div>
    
    <div class="faq-section">
        <div class="faq-container">
          <div class="faq-heading">Frequently Asked Questions</div>
          <div class="faq-text">
            Frequently asked questions ordered by popularity. If visitors haven’t committed yet, 
            they may still have doubts that can be answered here.
          </div>
          <div class="faq-list">
            <div class="faq-accordion">
              <div class="faq-question">
                <div class="faq-title">Question text goes here</div>
                <img class="faq-icon" src="faq-icon.png" alt="toggle icon" />
              </div>
              <div class="faq-answer">
                <div class="faq-description">
                  Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse varius 
                  enim in eros elementum tristique. Duis cursus, mi quis viverra ornare.
                </div>
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
            const accordions = document.querySelectorAll(".faq-accordion");
            accordions.forEach(acc => {
                const question = acc.querySelector(".faq-question");
                question.addEventListener("click", () => acc.classList.toggle("active"));
            });

            // --- ALL MODAL DEFINITIONS ---
            const borrowModal = document.getElementById("borrowModal");
            const profileModal = document.getElementById("profileModal");
            const editProfileModal = document.getElementById("editProfileModal");
            
            // --- BORROW MODAL LOGIC ---
            if (borrowModal) {
                const closeBtn = borrowModal.querySelector(".close-btn");
                const bookNameEl = document.getElementById("modalBookName");
                const bookIdInput = document.getElementById("modalBookId");
                const returnDateInput = document.getElementById("returnDate");
                const productContainer = document.querySelector(".product-container");
                const borrowForm = document.getElementById('borrowForm');

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const tomorrow = new Date(today);
                tomorrow.setDate(today.getDate() + 1);

                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 30);

                const formatDate = (date) => date.toISOString().split('T')[0];
                returnDateInput.setAttribute('min', formatDate(tomorrow));
                returnDateInput.setAttribute('max', formatDate(maxDate));

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
                
                if (borrowForm) {
                    borrowForm.addEventListener('submit', function(event) {
                        const selectedDateStr = returnDateInput.value;
                        if (!selectedDateStr) return;
                        
                        const parts = selectedDateStr.split('-');
                        const selectedDate = new Date(parts[0], parts[1] - 1, parts[2]);

                        if (selectedDate < tomorrow || selectedDate > maxDate) {
                            event.preventDefault(); 
                            alert('Please select a return date between tomorrow and 30 days from now.');
                        }
                    });
                }
                if (closeBtn) closeBtn.onclick = () => { borrowModal.style.display = "none"; };
            }
            
            // --- PROFILE & EDIT PROFILE MODAL LOGIC ---
            const profileBtn = document.getElementById("profileBtn");
            if (profileModal && profileBtn) {
                const profileModalCloseBtn = document.getElementById("profileModalCloseBtn");
                profileBtn.onclick = () => { profileModal.style.display = "flex"; };
                if(profileModalCloseBtn) profileModalCloseBtn.onclick = () => { profileModal.style.display = "none"; };
            }
            
            if (editProfileModal) {
                const openEditProfileBtn = document.getElementById("openEditProfileBtn");
                const editProfileModalCloseBtn = document.getElementById("editProfileModalCloseBtn");
                const cancelEditBtn = document.getElementById("cancelEditBtn");

                if (openEditProfileBtn) {
                    openEditProfileBtn.onclick = () => {
                        if (profileModal) profileModal.style.display = "none";
                        editProfileModal.style.display = "flex";
                    };
                }
                const closeEditModal = () => { editProfileModal.style.display = "none"; };
                if(editProfileModalCloseBtn) editProfileModalCloseBtn.onclick = closeEditModal;
                if(cancelEditBtn) cancelEditBtn.onclick = closeEditModal;
            }

            // --- UNIFIED WINDOW CLICK HANDLER FOR CLOSING MODALS ---
            window.onclick = (event) => { 
                if (event.target == borrowModal) { borrowModal.style.display = "none"; }
                if (event.target == profileModal) { profileModal.style.display = "none"; }
                if (event.target == editProfileModal) { editProfileModal.style.display = "none"; }
            };
        });


    </script>
</body>
</html> 