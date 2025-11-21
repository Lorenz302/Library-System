<?php
// frontend/manage_requests.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: ../frontend/index.html");
    exit;
}

include '../backend/db_connect.php';
$welcome_message = "Welcome, " . htmlspecialchars($_SESSION['fullname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* --- REVAMPED DARK STYLES --- */
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        
        .filter-form { display: flex; gap: 10px; width: 100%; }
        .filter-form input, .filter-form select { padding: 10px 15px; border-radius: 6px; border: 1px solid #4b5563; background: #1f2937; color: #f3f4f6; outline: none; }
        
        .table-container { background-color: #1f2937; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #374151; }
        table { width: 100%; border-collapse: collapse; color: #e5e7eb; font-size: 0.95rem; }
        
        thead tr { background-color: #111827; text-align: left; }
        th { padding: 18px 24px; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; color: #9ca3af; border-bottom: 1px solid #374151; }
        
        tbody tr { border-bottom: 1px solid #374151; transition: background-color 0.15s ease; }
        tbody tr:hover { background-color: #374151; }
        td { padding: 16px 24px; vertical-align: middle; }

        /* Status Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .status-pending { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-approved { background-color: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-available { background-color: rgba(52, 211, 153, 0.2); color: #6ee7b7; border: 1px solid rgba(52, 211, 153, 0.2); }
        .status-borrowed { background-color: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }

        /* Buttons */
        .action-buttons form { display: flex; gap: 8px; }
        .btn-mini { padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; color: white; }
        
        .btn-approve { background-color: #10b981; } .btn-approve:hover { background-color: #059669; }
        .btn-reject { background-color: #ef4444; } .btn-reject:hover { background-color: #dc2626; }
        .btn-pickup { background-color: #3b82f6; } .btn-pickup:hover { background-color: #2563eb; }
        .btn-return { background-color: #8b5cf6; } .btn-return:hover { background-color: #7c3aed; } /* Purple */
        .btn-fulfill { background-color: #ec4899; } .btn-fulfill:hover { background-color: #db2777; } /* Pink */

        /* Loading */
        #loading-indicator { font-size: 12px; color: #f59e0b; margin-left: 10px; display: none; }
        
        /* Info Icon */
        .info-icon { cursor: pointer; color: #9ca3af; margin-left: 10px; font-size: 1.2rem; }
        .info-icon:hover { color: white; }
        
        /* Modal (kept simple) */
        .info-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .info-modal-content { background: #1f2937; color: #f3f4f6; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; position: relative; border: 1px solid #374151; }
        .info-modal-close { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; }
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
                    <li><a href="manage_requests.php" class="active">Manage Requests</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="borrow_history.php">Borrow History</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="main-header">
                <h1>Manage Requests <span id="loading-indicator">Syncing...</span></h1>
                <div class="user-info">
                    <span><?php echo $welcome_message; ?></span>
                    <span id="infoBtn" class="info-icon" title="Help">ⓘ</span>
                    <a href="../backend/logout.php" style="margin-left:15px;">Logout</a>
                </div>
            </header>

            <main class="main-content">
                <!-- Filters -->
                <section class="action-bar">
                    <div class="filter-form">
                        <input type="text" id="searchInput" placeholder="Search User or Book..." style="flex-grow:1;">
                        <select id="typeFilter">
                            <option value="all">All Types</option>
                            <option value="Borrow">Borrow</option>
                            <option value="Reservation">Reservation</option>
                        </select>
                        <select id="statusFilter">
                            <option value="all">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Available">Available</option>
                            <option value="Borrowed">Borrowed</option>
                        </select>
                        <select id="sortFilter">
                            <option value="DESC">Newest First</option>
                            <option value="ASC">Oldest First</option>
                        </select>
                    </div>
                </section>

                <section class="content-section">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Book Title</th>
                                    <th>Request Date</th>
                                    <th>Due / Expiry</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTableBody">
                                <tr><td colspan="7" style="text-align:center; padding:30px; color:#9ca3af;">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Info Modal -->
    <div id="infoModal" class="info-modal">
        <div class="info-modal-content">
            <span id="infoModalClose" class="info-modal-close">&times;</span>
            <h2>Status Guide</h2>
            <ul style="line-height: 2; margin-top: 15px;">
                <li><span class="badge status-pending">Pending</span> Needs approval.</li>
                <li><span class="badge status-approved">Approved</span> Waiting for pickup.</li>
                <li><span class="badge status-available">Available</span> Reservation ready.</li>
                <li><span class="badge status-borrowed">Borrowed</span> Book is out.</li>
            </ul>
        </div>
    </div>

    <script>
        const tableBody = document.getElementById('requestsTableBody');
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const sortFilter = document.getElementById('sortFilter');
        const loadingIndicator = document.getElementById('loading-indicator');

        // Modal Logic
        const infoModal = document.getElementById('infoModal');
        document.getElementById('infoBtn').onclick = () => infoModal.style.display = 'flex';
        document.getElementById('infoModalClose').onclick = () => infoModal.style.display = 'none';
        window.onclick = (e) => { if(e.target == infoModal) infoModal.style.display = 'none'; };

        async function fetchRequests() {
            loadingIndicator.style.display = 'inline';
            const params = new URLSearchParams({
                search: searchInput.value, type: typeFilter.value, status: statusFilter.value, sort: sortFilter.value
            });

            try {
                const response = await fetch(`../backend/fetch_requests_api.php?${params.toString()}`);
                const data = await response.json();
                updateTable(data);
            } catch (error) { console.error(error); } 
            finally { setTimeout(() => { loadingIndicator.style.display = 'none'; }, 500); }
        }

        function updateTable(data) {
            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#9ca3af;">No active requests found.</td></tr>';
                return;
            }

            let html = '';
            data.forEach(row => {
                // Generate Buttons based on status
                let buttons = '';
                const hiddenInputs = `
                    <input type="hidden" name="request_id" value="${row.request_id}">
                    <input type="hidden" name="request_type" value="${row.request_type}">
                    <input type="hidden" name="book_id" value="${row.book_id}">
                    <input type="hidden" name="user_id_number" value="${row.id_number}">
                    <input type="hidden" name="search" value="${searchInput.value}">
                    <input type="hidden" name="type" value="${typeFilter.value}">
                    <input type="hidden" name="status" value="${statusFilter.value}">
                    <input type="hidden" name="sort" value="${sortFilter.value}">
                `;

                if (row.request_type === 'Borrow') {
                    if (row.status === 'Pending') {
                        buttons = `<button type="submit" name="action" value="Approve" class="btn-mini btn-approve">Approve</button>
                                   <button type="submit" name="action" value="Reject" class="btn-mini btn-reject">Reject</button>`;
                    } else if (row.status === 'Approved') {
                        buttons = `<button type="submit" name="action" value="MarkPickedUp" class="btn-mini btn-pickup">Picked Up</button>`;
                    } else if (row.status === 'Borrowed') {
                        buttons = `<button type="submit" name="action" value="MarkReturned" class="btn-mini btn-return">Returned</button>`;
                    }
                } else if (row.request_type === 'Reservation') {
                    if (row.status === 'Pending') {
                        buttons = `<button type="submit" name="action" value="CancelReservation" class="btn-mini btn-reject">Cancel</button>`;
                    } else if (row.status === 'Available') {
                        buttons = `<button type="submit" name="action" value="Fulfill" class="btn-mini btn-fulfill">Fulfill</button>
                                   <button type="submit" name="action" value="MarkAsExpired" class="btn-mini btn-reject">Expired</button>`;
                    }
                }

                html += `
                    <tr>
                        <td><strong>${row.user_name}</strong></td>
                        <td>${row.book_title}</td>
                        <td>${row.request_date_formatted}</td>
                        <td style="color:#d1d5db;">${row.relevant_date_formatted}</td>
                        <td>${row.request_type}</td>
                        <td><span class="badge status-${row.status.toLowerCase()}">${row.status}</span></td>
                        <td class="action-buttons">
                            <form action="../backend/update_request_status.php" method="POST">
                                ${hiddenInputs}
                                ${buttons}
                            </form>
                        </td>
                    </tr>`;
            });
            tableBody.innerHTML = html;
        }

        fetchRequests();
        setInterval(fetchRequests, 3000);
        searchInput.addEventListener('input', fetchRequests);
        typeFilter.addEventListener('change', fetchRequests);
        statusFilter.addEventListener('change', fetchRequests);
        sortFilter.addEventListener('change', fetchRequests);
    </script>
</body>
</html>