<?php
// Include database connection
require_once 'conn.php';

define("SECRET_KEY", "MBDPAY@2026_SUPER_SECRET_KEY_32");

/* Encrypt Function */
function encryptData($text)
{
    $key = hash("sha256", SECRET_KEY, true);
    $iv  = random_bytes(16);

    $cipher = openssl_encrypt(
        $text,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode($iv . $cipher);
}

/* Decrypt Function */

function decryptData($text)
{
    $key = hash("sha256", SECRET_KEY, true);

    $data = base64_decode($text);

    $iv = substr($data, 0, 16);

    $cipher = substr($data, 16);


    return openssl_decrypt(
        $cipher,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBD PAY | Offline Transactions</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">
    <style>
        /* Container Layout */
        .page-container {
            width: 95%;
            max-width: 1250px;
            margin: 20px auto 100px auto;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .icon-total {
            background: linear-gradient(135deg, #059669, #022c22);
        }

        .icon-synced {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .icon-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-info h4 {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-info p {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
            margin-top: 4px;
        }

        /* Card Wrapper */
        .content-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        /* Header & Action Controls */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecfdf5;
        }

        .card-header h2 {
            color: #022c22;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 16px 10px 38px;
            border: 1px solid #d1d5db;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            width: 220px;
        }

        .search-box input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            width: 260px;
        }

        .search-box::before {
            content: "🔍";
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            opacity: 0.6;
        }

        .filter-btn {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            text-align: left;
        }

        .transaction-table th {
            background: linear-gradient(135deg, #022c22, #059669);
            color: #ffffff;
            padding: 14px 16px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transaction-table th:first-child {
            border-top-left-radius: 12px;
        }

        .transaction-table th:last-child {
            border-top-right-radius: 12px;
        }

        .transaction-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            transition: background 0.2s;
        }

        .transaction-table tbody tr {
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .transaction-table tbody tr:hover {
            background-color: #f0fdf4;
        }

        /* Info Button Styling */
        .info-btn {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .info-btn:hover {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
            transform: scale(1.1);
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .sync-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .sync-yes {
            background: #e0e7ff;
            color: #3730a3;
        }

        .sync-no {
            background: #fef3c7;
            color: #92400e;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-style: italic;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(3px);
        }

        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            animation: modalFadeIn 0.25s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-icon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        .modal-text {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .modal-close-btn {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .modal-close-btn:hover {
            background: #047857;
        }

        /* Responsive Mobile Adjustments */
        @media (max-width: 800px) {
            .page-container {
                width: 100%;
                padding: 10px;
                margin-bottom: 100px;
            }

            .search-box input,
            .search-box input:focus {
                width: 100%;
            }

            .controls {
                width: 100%;
                justify-content: space-between;
            }

            .transaction-table th,
            .transaction-table td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    <!-- INCLUDE NAVBAR HERE -->
    <?php include 'navbar.php'; ?>

    <div class="page-container">

        <?php
        // Fetch counters for stats cards
        $total_query = "SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN server_sync = 1 OR LOWER(server_sync) = 'yes' OR LOWER(server_sync) = 'synced' THEN 1 ELSE 0 END) as synced_count,
            SUM(CASE WHEN server_sync = 0 OR LOWER(server_sync) = 'no' OR LOWER(server_sync) = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM offline_transactions";

        $stats_res = mysqli_query($conn, $total_query);
        $stats = mysqli_fetch_assoc($stats_res);

        $totalTx = $stats['total_count'] ?? 0;
        $syncedTx = $stats['synced_count'] ?? 0;
        $pendingTx = $stats['pending_count'] ?? 0;
        ?>

        <!-- Dynamic Summary Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total">📊</div>
                <div class="stat-info">
                    <h4>Total Transactions</h4>
                    <p><?php echo number_format($totalTx); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-synced">☁️</div>
                <div class="stat-info">
                    <h4>Synced to Server</h4>
                    <p><?php echo number_format($syncedTx); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-pending">⏳</div>
                <div class="stat-info">
                    <h4>Pending Sync</h4>
                    <p><?php echo number_format($pendingTx); ?></p>
                </div>
            </div>
        </div>

        <!-- Main Content Box -->
        <div class="content-card">
            <div class="card-header">
                <h2>📲 Offline Transactions Log</h2>

                <!-- Interactive Filters & Search -->
                <div class="controls">
                    <div class="search-box">
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search ID or Mobile...">
                    </div>
                    <button class="filter-btn active" onclick="filterSync('all', this)">All</button>
                    <button class="filter-btn" onclick="filterSync('synced', this)">Synced</button>
                    <button class="filter-btn" onclick="filterSync('pending', this)">Pending</button>
                </div>
            </div>

            <!-- Responsive Table -->
            <div class="table-responsive">
                <table class="transaction-table" id="txTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sent Mobile</th>
                            <th>Sent Balance</th>
                            <th>Status</th>
                            <th>Mobile Status</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Server Sync</th>
                            <th>Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, sender_mobile, reciever_mobile ,send_balance, status,reciever_check ,created_at, update_at, server_sync FROM offline_transactions ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $status = strtolower($row['status']);
                                $statusClass = 'badge-pending';
                                $statusDot = '🟡';
                                if (in_array($status, ['scanned', 'success'])) {
                                    $statusClass = 'badge-success';
                                    $statusDot = '🟢';
                                } elseif (in_array($status, ['not scanned'])) {
                                    $statusClass = 'badge-failed';
                                    $statusDot = '🟡';
                                } else {
                                    $statusClass = 'badge-failed';
                                    $statusDot = '🔴';
                                }

                                $syncVal = $row['server_sync'];
                                $syncValLower = strtolower($syncVal);

                                if ($syncValLower === 'failed' || $syncVal === '0') {
                                    $syncText  = 'Failed';
                                    $syncClass = 'sync-failed';
                                    $syncAttr  = 'failed';
                                } elseif ($syncVal == 1 || $syncValLower === 'yes' || $syncValLower === 'synced') {
                                    $syncText  = 'Synced';
                                    $syncClass = 'sync-yes';
                                    $syncAttr  = 'synced';
                                } else {
                                    $syncText  = 'Pending';
                                    $syncClass = 'sync-no';
                                    $syncAttr  = 'pending';
                                }

                                $res_check = $row['reciever_check'];
                                if ($res_check == 'Verified') {
                                    $res_yes = 1;
                                    $resClass = 'sync-yes';
                                } else {
                                    $res_yes = 0;
                                    $resClass = 'sync-no';
                                }

                                $syncIcon = match ($syncAttr) {
                                    'synced' => '✓ ',
                                    'failed' => '❌ ', // or '🔴 '
                                    default  => '🔄 ',
                                };

                                /* decrypted data to display in table */

                                $send_bal = decryptData($row['send_balance']);

                                $decryptedMobile = decryptData($row['reciever_mobile']);

                                $maskedMobile = substr($decryptedMobile, 0, 2) . '******' . substr($decryptedMobile, -2);

                                echo "<tr data-sync='{$syncAttr}'>";
                                echo "<td><strong>" . htmlspecialchars($row['id']) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($maskedMobile) . "</td>";
                                echo "<td><strong>₹" . number_format((float)$send_bal, 2) . "</strong></td>";
                                echo "<td><span class='badge {$statusClass}'>{$statusDot} " . htmlspecialchars(ucfirst($row['status'])) . "</span></td>";
                                echo "<td><span class='sync-badge {$resClass}'>" . ($res_yes ? '✓ ' : '❌') . htmlspecialchars($res_check) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['update_at']) . "</td>";
                                echo "<td><span class='sync-badge {$syncClass}'>" . $syncIcon . htmlspecialchars($syncText) . "</span></td>";
                                echo "<td><button class='info-btn' onclick='openModal()' title='View Information'>i</button></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr id='noDataRow'><td colspan='9' class='no-data'>No offline transactions found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info Text Message Modal -->
    <div id="infoModal" class="modal-overlay" onclick="closeModalOnOverlay(event)">
        <div class="modal-box">
            <div class="modal-icon">ℹ️</div>
            <div class="modal-title">Transaction Information</div>
            <div class="modal-text">If your QR money is timeout then deducted amount will be settled after Sync</div>
            <button class="modal-close-btn" onclick="closeModal()">OK</button>
        </div>
    </div>

    <!-- INCLUDE FOOTER HERE -->
    <?php include 'footer.php'; ?>

    <!-- Interactive Client-Side Features -->
    <script>
        let currentSyncFilter = 'all';

        // Filter Table by Search Input (Mobile or ID)
        function filterTable() {
            const input = document.getElementById("searchInput").value.toUpperCase();
            const rows = document.querySelectorAll("#txTable tbody tr");

            rows.forEach(row => {
                if (row.id === 'noDataRow') return;

                const idCell = row.cells[0]?.textContent || "";
                const mobileCell = row.cells[1]?.textContent || "";
                const matchesSearch = idCell.toUpperCase().includes(input) || mobileCell.toUpperCase().includes(input);

                const syncAttr = row.getAttribute('data-sync');
                const matchesSync = (currentSyncFilter === 'all' || syncAttr === currentSyncFilter);

                row.style.display = (matchesSearch && matchesSync) ? "" : "none";
            });
        }

        // Filter Table by Sync Status Tabs
        function filterSync(type, btnElement) {
            currentSyncFilter = type;

            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            filterTable();
        }

        // Modal Controls
        function openModal() {
            document.getElementById('infoModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('infoModal').style.display = 'none';
        }

        function closeModalOnOverlay(event) {
            if (event.target.id === 'infoModal') {
                closeModal();
            }
        }

        // Close Modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>