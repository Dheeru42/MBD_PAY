<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

require 'conn.php';
require 'currency_con.php';

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}

$u_account = $_SESSION['account'];

if (!isset($_SESSION['mobile'])) {
    header("location:index.php");
    exit;
}

$u_mobile = $_SESSION['mobile'];


if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

/*
|--------------------------------------------------------------------------
| ENCRYPT / DECRYPT
| Same structure used by generated_currency.php
|--------------------------------------------------------------------------
*/
define("SECRET_KEY", "MBDPAY@2026_SUPER_SECRET_KEY_32");

function decryptData($text)
{
    if ($text === null || $text === '') {
        return '';
    }

    $key = hash("sha256", SECRET_KEY, true);
    $data = base64_decode($text, true);

    if ($data === false || strlen($data) < 17) {
        return $text;
    }

    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);

    $value = openssl_decrypt(
        $cipher,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return ($value === false) ? $text : $value;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$currency_rows = [];
$total_currency = 0;
$total_value = 0;
$status_counts = [];
$server_error = "";

try {

    $sql = "SELECT
                id,
                serial_no,
                encrypted_serial,
                amount,
                sender_mobile,
                receiver_mobile,
                status,
                generated_at
            FROM currency
            WHERE sender_mobile = $u_mobile
            AND wallet_id = '$u_wallet_id'
            ORDER BY generated_at DESC";

    $result = mysqli_query($c_conn, $sql);

    if (!$result) {
        throw new Exception(mysqli_error($c_conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {

        $status = strtoupper(trim($row['status'] ?? 'UNKNOWN'));

        /*
        | Amount follows the same encrypted structure as generated_currency.php.
        */
        $amount = (float) decryptData($row['amount'] ?? '');

        $row['_amount'] = $amount;
        $row['_status'] = $status;

        $currency_rows[] = $row;

        $total_value += $amount;

        if (!isset($status_counts[$status])) {
            $status_counts[$status] = 0;
        }

        $status_counts[$status]++;
    }

    $total_currency = count($currency_rows);

    ksort($status_counts);
} catch (Throwable $th) {

    $server_error = "Unable to load currency data from server.";

    $currency_rows = [];
    $total_currency = 0;
    $total_value = 0;
    $status_counts = [];
}

/*
|--------------------------------------------------------------------------
| STATUS COLORS / LABELS
|--------------------------------------------------------------------------
*/
function statusClass($status)
{
    $status = strtoupper($status);

    switch ($status) {
        case 'GENERATED':
            return 'status-generated';

        case 'RECEIVED':
        case 'COMPLETED':
        case 'SUCCESS':
        case 'ACTIVE':
            return 'status-success';

        case 'PENDING':
            return 'status-pending';

        case 'USED':
        case 'TRANSFERRED':
            return 'status-used';

        case 'CANCELLED':
        case 'CANCELED':
        case 'FAILED':
        case 'REJECTED':
        case 'EXPIRED':
            return 'status-danger';

        default:
            return 'status-other';
    }
}

function statusIcon($status)
{
    $status = strtoupper($status);

    switch ($status) {
        case 'GENERATED':
            return '✓';

        case 'RECEIVED':
        case 'COMPLETED':
        case 'SUCCESS':
        case 'ACTIVE':
            return '●';

        case 'PENDING':
            return '◷';

        case 'USED':
        case 'TRANSFERRED':
            return '↗';

        case 'CANCELLED':
        case 'CANCELED':
        case 'FAILED':
        case 'REJECTED':
        case 'EXPIRED':
            return '×';

        default:
            return '•';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MBD PAY | Currency Status</title>

    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: Inter, Arial, Helvetica, sans-serif;
            color: #022c22;
            background:
                radial-gradient(circle at 10% 10%, rgba(16, 185, 129, .22), transparent 30%),
                radial-gradient(circle at 90% 20%, rgba(52, 211, 153, .18), transparent 28%),
                linear-gradient(135deg, #ecfdf5, #f0fdf4, #d1fae5);
            overflow-x: hidden;
        }

        .currency-status-page {
            width: 100%;
            max-width: 1400px;
            margin: auto;
            padding: 30px 25px 120px;
        }

        /* NAVBAR
           navbar.php owns the navbar styling. Do not override it here.
        */

        /* HERO */

        .hero {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: 30px;
            background: linear-gradient(135deg, rgba(255, 255, 255, .92), rgba(236, 253, 245, .82));
            border: 1px solid rgba(255, 255, 255, .95);
            box-shadow: 0 25px 60px rgba(0, 0, 0, .08);
            backdrop-filter: blur(20px);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(16, 185, 129, .10);
            right: -70px;
            top: -140px;
        }

        .hero-left {
            position: relative;
            z-index: 2;
        }

        .title-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            font-weight: bold;
            background: linear-gradient(135deg, #047857, #10b981);
            box-shadow: 0 10px 25px rgba(5, 150, 105, .30);
        }

        .hero h1 {
            color: #022c22;
            font-size: 32px;
            font-weight: 800;
        }

        .hero p {
            margin-top: 6px;
            color: #64748b;
            font-size: 14px;
        }

        .server-pill {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 16px;
            border-radius: 25px;
            background: #022c22;
            color: white;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .server-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 10px #22c55e;
            animation: pulse 1.5s infinite;
        }

        .server-dot.offline {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }

        @keyframes pulse {
            50% {
                opacity: .45;
                transform: scale(1.25);
            }
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 20px;
            border-radius: 22px;
            background: rgba(255, 255, 255, .86);
            border: 1px solid rgba(255, 255, 255, .95);
            box-shadow: 0 15px 35px rgba(0, 0, 0, .07);
        }

        .summary-label {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 7px;
        }

        .summary-value {
            color: #047857;
            font-size: 27px;
            font-weight: 850;
        }

        /* CONTROLS */

        .controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-box span {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input,
        .status-filter {
            width: 100%;
            height: 46px;
            border: 1px solid #d1fae5;
            border-radius: 14px;
            outline: none;
            background: rgba(255, 255, 255, .9);
            color: #064e3b;
            font-size: 13px;
            transition: .2s;
        }

        .search-box input {
            padding: 0 15px 0 42px;
        }

        .status-filter {
            width: 190px;
            padding: 0 13px;
        }

        .search-box input:focus,
        .status-filter:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, .10);
        }

        /* TABLE */

        .table-card {
            overflow: hidden;
            border-radius: 25px;
            background: rgba(255, 255, 255, .88);
            border: 1px solid rgba(255, 255, 255, .95);
            box-shadow: 0 20px 55px rgba(0, 0, 0, .08);
            backdrop-filter: blur(20px);
        }

        .table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-head h2 {
            color: #064e3b;
            font-size: 18px;
        }

        .record-count {
            color: #64748b;
            font-size: 12px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th {
            padding: 14px 16px;
            text-align: left;
            color: #64748b;
            background: #f0fdf4;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
            white-space: nowrap;
        }

        td {
            padding: 15px 16px;
            border-bottom: 1px solid #eef2f7;
            color: #334155;
            font-size: 12px;
            vertical-align: middle;
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: rgba(236, 253, 245, .75);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .id {
            color: #94a3b8;
            font-weight: 700;
        }

        .serial {
            max-width: 180px;
            color: #047857;
            font-family: "Courier New", monospace;
            font-size: 11px;
            font-weight: 700;
            word-break: break-all;
        }

        .amount {
            color: #022c22;
            font-size: 15px;
            font-weight: 850;
            white-space: nowrap;
        }

        .amount .rupee {
            color: #059669;
        }

        .mobile {
            white-space: nowrap;
            font-weight: 600;
        }

        .date {
            color: #64748b;
            white-space: nowrap;
            font-size: 11px;
        }

        .currency-status-page .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 18px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-generated {
            color: #047857;
            background: #dcfce7;
        }

        .status-success {
            color: #166534;
            background: #dcfce7;
        }

        .status-pending {
            color: #a16207;
            background: #fef3c7;
        }

        .status-used {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status-danger {
            color: #b91c1c;
            background: #fee2e2;
        }

        .status-other {
            color: #475569;
            background: #f1f5f9;
        }

        .status-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .mini-status {
            padding: 6px 10px;
            border-radius: 15px;
            background: #f8fafc;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
        }

        /* ERROR / EMPTY */

        .alert {
            padding: 15px 18px;
            margin-bottom: 20px;
            border-radius: 15px;
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            font-size: 13px;
        }

        .empty {
            padding: 80px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            background: #dcfce7;
            color: #059669;
            font-size: 38px;
        }

        .empty h2 {
            color: #064e3b;
            margin-bottom: 7px;
        }

        .empty p {
            color: #64748b;
            font-size: 13px;
        }

        .no-results {
            display: none;
            padding: 35px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        /* MOBILE */

        @media (max-width: 900px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .server-pill {
                align-self: flex-start;
            }
        }

        @media (max-width: 600px) {
            .currency-status-page {
                padding: 20px 12px 110px;
            }

            .hero {
                padding: 22px;
                border-radius: 24px;
            }

            .hero h1 {
                font-size: 26px;
            }

            .title-icon {
                width: 52px;
                height: 52px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .status-filter {
                width: 100%;
            }

            .table-card {
                border-radius: 20px;
            }
        }
    </style>
</head>

<body>

    <?php require 'navbar.php'; ?>

    <main class="currency-status-page">

        <!-- HERO -->
        <section class="hero">

            <div class="hero-left">
                <div class="title-row">
                    <div class="title-icon">₹</div>

                    <div>
                        <h1>Currency Status</h1>
                        <p>All currency records from the server with every available status.</p>
                    </div>
                </div>
            </div>

        </section>

        <?php if ($server_error !== ""): ?>
            <div class="alert">
                <?php echo e($server_error); ?>
            </div>
        <?php endif; ?>

        <!-- SUMMARY -->
        <section class="summary-grid">

            <div class="summary-card">
                <span class="summary-label">Total Currency</span>
                <span class="summary-value" id="totalCount">
                    <?php echo number_format($total_currency); ?>
                </span>
            </div>

            <div class="summary-card">
                <span class="summary-label">Total Value</span>
                <span class="summary-value">
                    ₹<?php echo number_format($total_value, 2); ?>
                </span>
            </div>

            <div class="summary-card">
                <span class="summary-label">Generated</span>
                <span class="summary-value">
                    <?php echo number_format($status_counts['GENERATED'] ?? 0); ?>
                </span>
            </div>

            <div class="summary-card">
                <span class="summary-label">Scanned</span>
                <span class="summary-value">
                    <?php
                    $otherCount = 0;

                    foreach ($status_counts as $status => $count) {
                        if ($status !== 'GENERATED') {
                            $otherCount += $count;
                        }
                    }

                    echo number_format($otherCount);
                    ?>
                </span>
            </div>

        </section>

        <!-- CONTROLS -->
        <section class="controls">

            <div class="search-box">
                <span>⌕</span>
                <input
                    type="text"
                    id="currencySearch"
                    placeholder="Search ID, serial, sender, receiver or status..."
                    autocomplete="off">
            </div>

            <select id="statusFilter" class="status-filter">
                <option value="ALL">All Status</option>

                <?php foreach ($status_counts as $status => $count): ?>
                    <option value="<?php echo e($status); ?>">
                        <?php echo e($status); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

        </section>

        <!-- STATUS SUMMARY -->
        <?php if (!empty($status_counts)): ?>
            <div class="status-summary" style="margin-bottom:18px;">

                <?php foreach ($status_counts as $status => $count): ?>
                    <span class="mini-status">
                        <?php echo e($status); ?>:
                        <?php echo number_format($count); ?>
                    </span>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

        <!-- TABLE -->
        <section class="table-card">

            <div class="table-head">
                <h2>MBD Currency Records</h2>

                <span class="record-count" id="visibleCount">
                    Showing <?php echo number_format($total_currency); ?> records
                </span>
            </div>

            <?php if ($total_currency > 0): ?>

                <div class="table-wrapper">

                    <table id="currencyTable">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Serial Number</th>
                                <th>Amount</th>
                                <th>Sender</th>
                                <th>Receiver</th>
                                <th>Status</th>
                                <th>Generated At</th>
                                <th>Scanned At</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($currency_rows as $index => $currency): ?>

                                <?php
                                $status = $currency['_status'];

                                /*
                        | generated_currency.php uses the server fields directly
                        | for online currency. Keep that structure here.
                        */
                                $serial = $currency['serial_no'] ?? '';
                                $sender = $currency['sender_mobile'] ?? '';
                                $receiver = $currency['receiver_mobile'] ?? '';

                                $searchText = strtolower(
                                    ($currency['id'] ?? '') . ' ' .
                                        $serial . ' ' .
                                        $sender . ' ' .
                                        $receiver . ' ' .
                                        $status . ' ' .
                                        ($currency['generated_at'] ?? '')
                                );
                                ?>

                                <tr
                                    class="currency-row"
                                    data-status="<?php echo e($status); ?>"
                                    data-search="<?php echo e($searchText); ?>">

                                    <td class="id">
                                        <?php echo $index + 1; ?>
                                    </td>


                                    <td>
                                        <div class="serial">
                                            <?php echo e($serial); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="amount">
                                            <span class="rupee">₹</span>
                                            <?php echo number_format($currency['_amount'], 2); ?>
                                        </div>
                                    </td>

                                    <td class="mobile">
                                        <?php echo e(substr($sender, 0, 3) . '****' . substr($sender, -3)); ?>
                                    </td>

                                    <td class="mobile">
                                        <?php if (!empty($receiver)) {
                                            echo e(substr($receiver, 0, 3) . '****' . substr($receiver, -3));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <span class="status <?php echo statusClass($status); ?>">
                                            <?php echo statusIcon($status); ?>
                                            <?php echo e($status); ?>
                                        </span>
                                    </td>

                                    <td class="date">
                                        <?php
                                        if (!empty($currency['generated_at'])) {
                                            echo e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($currency['generated_at'])
                                                )
                                            );
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>

                                    <td class="date">
                                        <?php
                                        if (!empty($currency['scanned_at'])) {
                                            echo e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($currency['scanned_at'])
                                                )
                                            );
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="no-results" id="noResults">
                    No currency records match your search/filter.
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">₹</div>
                    <h2>No Currency Found</h2>
                    <p>No currency records were returned from the server.</p>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php require 'footer.php'; ?>

    <script>
        (function() {

            const searchInput = document.getElementById('currencySearch');
            const statusFilter = document.getElementById('statusFilter');
            const rows = Array.from(document.querySelectorAll('.currency-row'));
            const visibleCount = document.getElementById('visibleCount');
            const noResults = document.getElementById('noResults');

            function filterRows() {

                const search = (searchInput.value || '').toLowerCase().trim();
                const status = statusFilter.value;

                let visible = 0;

                rows.forEach(function(row) {

                    const rowStatus = row.dataset.status || '';
                    const rowSearch = row.dataset.search || '';

                    const matchesSearch =
                        search === '' || rowSearch.includes(search);

                    const matchesStatus =
                        status === 'ALL' || rowStatus === status;

                    const show = matchesSearch && matchesStatus;

                    row.style.display = show ? '' : 'none';

                    if (show) {
                        visible++;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent =
                        'Showing ' + visible.toLocaleString() + ' records';
                }

                if (noResults) {
                    noResults.style.display =
                        rows.length > 0 && visible === 0 ? 'block' : 'none';
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterRows);
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', filterRows);
            }

        })();
    </script>

</body>

</html>