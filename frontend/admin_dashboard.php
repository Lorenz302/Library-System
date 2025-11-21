<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: index.html");
    exit;
}

$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* --- DASHBOARD SPECIFIC STYLES --- */
        
        /* Live Clock */
        .live-clock {
            font-size: 1.2rem;
            font-weight: bold;
            color: #ecf0f1;
            background: rgba(255,255,255,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            margin-left: auto;
            margin-right: 20px;
            border: 1px solid #7f8c8d;
        }

        /* Activity Feed Section */
        .activity-section {
            margin-top: 30px;
            background-color: #34495e;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .activity-section h2 {
            color: #ecf0f1;
            margin-bottom: 15px;
            border-bottom: 1px solid #7f8c8d;
            padding-bottom: 10px;
        }
        .activity-list {
            list-style: none;
            padding: 0;
        }
        .activity-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #2c3e50;
            color: #bdc3c7;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            margin-right: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .icon-borrow { background-color: #3498db; box-shadow: 0 0 5px #3498db; }
        .icon-reserve { background-color: #f1c40f; box-shadow: 0 0 5px #f1c40f; }
        
        .activity-details { flex-grow: 1; }
        .activity-details strong { color: #fff; }
        .activity-time { font-size: 0.85rem; color: #7f8c8d; }

        /* Toast Notification */
        .toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #e74c3c; /* Red for attention */
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 16px;
            position: fixed;
            z-index: 1000;
            right: 30px;
            bottom: 30px;
            font-size: 17px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.4);
            opacity: 0;
            transition: opacity 0.5s, bottom 0.5s;
        }
        .toast.show {
            visibility: visible;
            opacity: 1;
            bottom: 50px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="LIBRARY_LOGO.png" alt="Logo" class="sidebar-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="manage_requests.php">Manage Requests</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-wrapper">
            <header class="main-header">
                <h1>Dashboard</h1>
                
                <!-- NEW: Live Clock -->
                <div id="liveClock" class="live-clock">--:--:--</div>

                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <a href="../backend/logout.php">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <!-- Summary Cards -->
                <section class="summary-cards">
                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/task.png" alt="Requests"/>
                        </div>
                        <div class="card-info">
                            <h2 id="stat-pending">...</h2> <!-- ID added for JS -->
                            <p>Pending Requests</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/books.png" alt="Books"/>
                        </div>
                        <div class="card-info">
                            <h2 id="stat-books">...</h2> <!-- ID added for JS -->
                            <p>Total Books</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <img src="https://img.icons8.com/ios-filled/50/C81D14/reading.png" alt="Loan"/>
                        </div>
                        <div class="card-info">
                            <h2 id="stat-loan">...</h2> <!-- ID added for JS -->
                            <p>Books on Loan</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                             <img src="https://img.icons8.com/ios-filled/50/C81D14/user-group-man-man.png" alt="Users"/>
                        </div>
                        <div class="card-info">
                            <h2 id="stat-users">...</h2> <!-- ID added for JS -->
                            <p>Total Users</p>
                        </div>
                    </div>
                </section>

                <!-- NEW: Recent Activity Feed -->
                <section class="activity-section">
                    <h2>Recent Activity</h2>
                    <ul class="activity-list" id="activity-list">
                        <li class="activity-item">Loading activity...</li>
                    </ul>
                </section>
            </main>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notificationToast" class="toast">New Request Received!</div>

    <!-- JAVASCRIPT -->
    <script>
        // --- 1. LIVE CLOCK LOGIC ---
        function updateClock() {
            const now = new Date();
            // Format: Mon, Nov 21 - 10:30:05 PM
            const options = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            document.getElementById('liveClock').innerText = now.toLocaleString('en-US', options);
        }
        setInterval(updateClock, 1000); // Update every second
        updateClock(); // Run immediately on load

        // --- 2. DASHBOARD POLLING LOGIC ---
        let previousPendingCount = null; // Track count to trigger alerts

        async function fetchDashboardData() {
            try {
                const response = await fetch('../backend/get_dashboard_data.php');
                const data = await response.json();

                if (data.error) return; // Stop if unauthorized

                // A. Update Stats
                document.getElementById('stat-pending').innerText = data.stats.pending_requests;
                document.getElementById('stat-books').innerText = data.stats.total_books;
                document.getElementById('stat-loan').innerText = data.stats.books_on_loan;
                document.getElementById('stat-users').innerText = data.stats.total_users;

                // B. Notification Logic
                // If we have data, and the new count is HIGHER than the old count...
                if (previousPendingCount !== null && data.stats.pending_requests > previousPendingCount) {
                    showToast("🔔 New Request Received!");
                }
                previousPendingCount = data.stats.pending_requests;

                // C. Update Activity Feed
                const activityList = document.getElementById('activity-list');
                if (data.recent_activity.length > 0) {
                    let html = '';
                    data.recent_activity.forEach(act => {
                        // Determine icon color based on type
                        const iconClass = act.request_type === 'Borrow' ? 'icon-borrow' : 'icon-reserve';
                        const actionText = act.request_type === 'Borrow' ? 'requested to borrow' : 'reserved';
                        
                        html += `
                        <li class="activity-item">
                            <div class="activity-icon ${iconClass}"></div>
                            <div class="activity-details">
                                <strong>${act.user_name}</strong> ${actionText} <em>"${act.book_title}"</em>
                            </div>
                            <div class="activity-time">${act.formatted_date}</div>
                        </li>`;
                    });
                    activityList.innerHTML = html;
                } else {
                    activityList.innerHTML = '<li class="activity-item">No recent activity.</li>';
                }

            } catch (error) {
                console.error("Error syncing dashboard:", error);
            }
        }

        // --- 3. TOAST FUNCTION ---
        function showToast(message) {
            const toast = document.getElementById("notificationToast");
            toast.innerText = message;
            toast.className = "toast show";
            
            // Hide after 3 seconds
            setTimeout(function(){ 
                toast.className = toast.className.replace("show", ""); 
            }, 3000);
        }

        // --- 4. INIT ---
        fetchDashboardData(); // Fetch immediately
        setInterval(fetchDashboardData, 3000); // Poll every 3 seconds

    </script>
</body>
</html>