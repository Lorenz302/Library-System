<?php
session_start();

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

include '../backend/db_connect.php';

// Get categories for filter
$cat_sql = "SELECT DISTINCT category FROM books ORDER BY category ASC";
$cat_result = $conn->query($cat_sql);
$categories = [];
while($row = $cat_result->fetch_assoc()) {
    $categories[] = $row['category'];
}

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* ========================================= */
        /* ======= REVAMPED TABLE DESIGN =========== */
        /* ========================================= */
        
        /* Action Bar & Filters */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
        }
        
        .filter-group input, .filter-group select {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #4b5563; /* Dark grey border */
            background: #1f2937; /* Dark background */
            color: #f3f4f6; /* Light text */
            outline: none;
            transition: border-color 0.2s;
        }
        
        .filter-group input:focus, .filter-group select:focus {
            border-color: #3b82f6; /* Blue focus */
        }

        .add-new-btn {
            background-color: #10b981; /* Emerald Green */
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s, transform 0.1s;
        }
        
        .add-new-btn:hover {
            background-color: #059669;
            transform: translateY(-1px);
        }

        /* Table Container */
        .table-container {
            background-color: #1f2937; /* Card Background */
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden; /* Rounds corners of table */
            border: 1px solid #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            color: #e5e7eb; /* Light Text */
            font-size: 0.95rem;
        }

        /* Header Styling */
        thead tr {
            background-color: #111827; /* Very dark header */
            text-align: left;
        }

        th {
            padding: 18px 24px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #9ca3af; /* Muted text */
            border-bottom: 1px solid #374151;
        }

        /* Body Styling */
        tbody tr {
            border-bottom: 1px solid #374151;
            transition: background-color 0.15s ease;
        }
        
        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background-color: #374151; /* Highlight Row on Hover */
        }

        td {
            padding: 16px 24px;
            vertical-align: middle; /* Centers content vertically with image */
        }

        /* Image Styling */
        .book-cover-wrapper {
            width: 50px;
            height: 75px;
            border-radius: 6px;
            overflow: hidden;
            background-color: #4b5563;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .book-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Text Highlights */
        .book-title {
            font-weight: 600;
            color: #f9fafb;
            display: block;
            margin-bottom: 4px;
        }
        
        .book-author {
            font-size: 0.85em;
            color: #9ca3af;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 9999px; /* Pill shape */
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-available {
            background-color: rgba(16, 185, 129, 0.2); /* Green with opacity */
            color: #34d399; /* Bright green text */
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-out {
            background-color: rgba(239, 68, 68, 0.2); /* Red with opacity */
            color: #f87171; /* Bright red text */
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Action Buttons */
        .action-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .edit-btn {
            color: #60a5fa; /* Blue */
            background-color: rgba(59, 130, 246, 0.1);
        }
        .edit-btn:hover {
            background-color: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }
        .edit-btn { text-decoration: none; } /* Remove underline from link */

        .delete-btn {
            color: #f87171; /* Red */
            background-color: rgba(239, 68, 68, 0.1);
        }
        .delete-btn:hover {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        #loading-indicator { font-size: 12px; color: #f39c12; margin-left: 10px; display: none; }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="LIBRARY_LOGO.png" alt="Logo" class="sidebar-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_requests.php">Manage Requests</a></li>
                    <li><a href="manage_books.php" class="active">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Manage Books <span id="loading-indicator">Syncing...</span></h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <!-- Action Bar -->
                <div class="action-bar">
                    <a href="add_book.php" class="add-new-btn">+ Add New Book</a>
                    <div class="filter-group">
                        <input type="text" id="searchInput" placeholder="Search Title or Author...">
                        <select id="categoryFilter">
                            <option value="all">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <section class="content-section">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th width="80">Image</th>
                                    <th>Book Details</th> <!-- Combined Title/Author -->
                                    <th>Category</th>
                                    <th style="text-align: center;">Total</th>
                                    <th style="text-align: center;">Available</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="booksTableBody">
                                <tr><td colspan="7" style="text-align:center; padding: 30px;">Loading Books...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        const tableBody = document.getElementById('booksTableBody');
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const loadingIndicator = document.getElementById('loading-indicator');

        async function fetchBooks() {
            loadingIndicator.style.display = 'inline';
            
            const params = new URLSearchParams({
                search: searchInput.value,
                category: categoryFilter.value
            });

            try {
                const response = await fetch(`../backend/fetch_books_api.php?${params.toString()}`);
                const books = await response.json();
                renderTable(books);
            } catch (error) {
                console.error("Error fetching books:", error);
            } finally {
                setTimeout(() => { loadingIndicator.style.display = 'none'; }, 300);
            }
        }

        function renderTable(books) {
            if (books.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 30px; color: #9ca3af;">No books found matching your criteria.</td></tr>';
                return;
            }

            let html = '';
            books.forEach(book => {
                // Image Handling (Root folder logic)
                let imgHtml = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10px;">No Img</div>';
                
                if (book.book_image_path && book.book_image_path !== "") {
                    let imgSrc = "../" + book.book_image_path;
                    // We use the wrapper to force the rounded shape and size
                    imgHtml = `<img src="${imgSrc}" alt="Cover" class="book-thumb" onerror="this.style.display='none'; this.parentNode.innerHTML='No Img'">`;
                }

                html += `
                    <tr>
                        <td>
                            <div class="book-cover-wrapper">
                                ${imgHtml}
                            </div>
                        </td>
                        <td>
                            <span class="book-title">${book.book_name}</span>
                            <span class="book-author">by ${book.author}</span>
                        </td>
                        <td><span style="color: #d1d5db;">${book.category}</span></td>
                        <td style="text-align:center; color: #d1d5db;">${book.total_copies}</td>
                        <td style="text-align:center; font-weight:bold; color: #fff;">${book.available_copies}</td>
                        <td><span class="badge ${book.status_class}">${book.status_text}</span></td>
                        <td class="action-links">
                            <a href="edit_book.php?id=${book.book_id}" class="btn-icon edit-btn">Edit</a>
                            
                            <form action="../backend/book_handler.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete &quot;${book.book_name}&quot;? This cannot be undone.');">
                                <input type="hidden" name="book_id" value="${book.book_id}">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-icon delete-btn" title="Delete Book">Delete</button>
                            </form>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        // Initial Load
        fetchBooks();
        // Real-time polling (3 seconds)
        setInterval(fetchBooks, 3000);
        // Listeners
        searchInput.addEventListener('input', fetchBooks);
        categoryFilter.addEventListener('change', fetchBooks);
    </script>
</body>
</html>