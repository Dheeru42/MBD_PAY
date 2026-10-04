<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

require_once "conn.php";

$message = "";
$message_f = "";

// catch create logic

define("CACHE_DIR", __DIR__ . "/cache/users/");
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

// Generate a new unique token for a new transaction.
// If a valid QR already exists in the session, it will be restored below.
$timestamp = time();
$randomPart = rand(1000, 9000);
$uniqueToken = "MBD-" . $timestamp . "-" . $randomPart;

// hashing it in PHP
$token_id = hash("sha256",$uniqueToken);

if ($serverConnected) {
    header("location:index.php");
    exit;
}

if (!isset($_SESSION['user'])) {

    header("location:login.php");
    exit;
}

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}

if (!isset($_SESSION['mobile'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

$u_account = $_SESSION['account'];

$u_mob = $_SESSION['mobile'];

$u_name = $_SESSION['user'];

// code to read cache data 

$userId = hash("sha256", $u_mob);

$profile = CACHE_DIR . $userId . "/profile.json";

$cache = json_decode(
    file_get_contents($profile),
    true
);

$u_balance = decryptData($cache['balance']);

$verify = false;

$submitted_amount = 0;
$d_send_amount = 0;
$receiver_mobile = "";

/*
 * Restore the last generated offline QR from the session.
 *
 * This makes the QR survive a normal browser refresh without
 * creating another transaction or deducting the balance again.
 *
 * QR lifetime = 40 seconds.
 */
if (isset($_SESSION['offline_qr']) && is_array($_SESSION['offline_qr'])) {

    $savedQr = $_SESSION['offline_qr'];

    $qrCreatedAt = (int)($savedQr['created_at'] ?? 0);
    $qrAge = time() - $qrCreatedAt;

    if (
        $qrCreatedAt > 0 &&
        $qrAge < 40 &&
        !empty($savedQr['token_id']) &&
        isset($savedQr['amount']) &&
        !empty($savedQr['receiver_mobile'])
    ) {
        $verify = true;

        // Restore the exact transaction data used by the QR.
        $token_id = $savedQr['token_id'];
        $submitted_amount = $savedQr['amount'];
        $d_send_amount = $savedQr['amount'];
        $receiver_mobile = $savedQr['receiver_mobile'];
    } else {
        // QR has expired. It must not be displayed after refresh.
        unset($_SESSION['offline_qr']);
    }
}

// Restore one-time success/error message after POST -> redirect -> GET.
if (!empty($_SESSION['offline_message'])) {
    $message = $_SESSION['offline_message'];
    unset($_SESSION['offline_message']);
}

if (!empty($_SESSION['offline_message_f'])) {
    $message_f = $_SESSION['offline_message_f'];
    unset($_SESSION['offline_message_f']);
}

// verify pin
try {
    if (isset($_POST['verify_pin'])) {
        $pin = $_POST['pin'];
        $submitted_amount = $_POST['form_amount'] ?? 0;
        $receiver_mobile = trim($_POST['receiver_mobile'] ?? '');

        // Validate receiver mobile before processing the transaction
        if (!is_numeric($submitted_amount) || $submitted_amount <= 0 || $submitted_amount > $u_balance) {
            $message_f = "Please enter a valid amount within your available wallet balance.";
        } elseif ($cache['send_limit'] == 0) {
            $message_f = "You have exceeded your sending limit.Please synchronize your wallet to continue.";
        } else {
            if (password_verify(
                $pin,
                $cache['pin']
            )) {
                $message = "Pin Verified & QR Generated";

                $verify = true;

                $d_send_amount = $submitted_amount;

                /* code to deduct offline money from cache  */

                // code to deduct balance from cache

                $profile = CACHE_DIR . $userId . "/profile.json";

                $cache_bal = json_decode(
                    file_get_contents($profile),
                    true
                );

                $offline_wallet_balance = decryptData($cache_bal['balance']);

                $updated_offline_wallet_bal = $offline_wallet_balance - $submitted_amount;

                $cache_bal['balance'] = encryptData($updated_offline_wallet_bal);

                $cache_bal['update_at'] = date("Y-m-d h:i:s A");

                file_put_contents(
                    $profile,
                    json_encode(
                        $cache_bal,
                        JSON_PRETTY_PRINT
                    )
                );

                $u_balance = $updated_offline_wallet_bal; // display updated balance


                /* code to insert offline transaction in transaction folder */

                $trx_folder = CACHE_DIR . $userId . "/transactions";

                // Create transactions cache folder if not exist
                if (!is_dir($trx_folder)) {
                    mkdir($trx_folder, 0777, true);
                }

                // insert trx in transaction

                $trx_data = [

                    "token_id" => $token_id,

                    "wallet_id" => encryptData($u_wallet_id),

                    "sender_mobile" => encryptData($u_mob),

                    "reciever_mobile" => encryptData($receiver_mobile),

                    "send_balance" => encryptData($submitted_amount),

                    "status" => "Not Scanned",

                    "created_at" => date("Y-m-d h:i:s A"),

                    "update_at" => date("Y-m-d h:i:s A"),

                    "server_sync" => "Pending"

                ];

                $trx_id = $token_id;

                $trx_file = hash("sha256", $trx_id);
                // Save currency in cache
                $trx_cacheFile = $trx_folder . "/" . $trx_file . ".json";

                file_put_contents(
                    $trx_cacheFile,
                    json_encode($trx_data, JSON_PRETTY_PRINT)
                );


                /* code to restrict the sender to send offline money by qr after limit = 2 */

                $profile = CACHE_DIR . $userId . "/profile.json";

                $cache_limit = json_decode(
                    file_get_contents($profile),
                    true
                );

                $send_limit = $cache_limit['send_limit'] - 1; // every qr generate

                $cache_limit['send_limit'] = $send_limit;

                file_put_contents(
                    $profile,
                    json_encode(
                        $cache_limit,
                        JSON_PRETTY_PRINT
                    )
                );

                /*
                 * Save the exact QR data in the session.
                 *
                 * The next request (including a browser refresh) can
                 * restore the same QR for the remaining 40 seconds.
                 */
                $_SESSION['offline_qr'] = [
                    'token_id' => $token_id,
                    'amount' => $submitted_amount,
                    'receiver_mobile' => $receiver_mobile,
                    'created_at' => time()
                ];

                /*
                 * Store the success message before redirecting.
                 * POST -> redirect -> GET prevents refresh from submitting
                 * the PIN form again and deducting the amount twice.
                 */
                $_SESSION['offline_message'] = $message;

                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message_f = "Pin Not Verified";
            }
        }
    }
} catch (\Throwable $th) {
    echo 'error';
}


// Fetch all offline transactions from the cache directory
$transactionsList = [];
$trxDir = CACHE_DIR . $userId . "/transactions";

try {
    if (is_dir($trxDir)) {
        $files = glob($trxDir . "/*.json");
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $data['send_balance_decrypted'] = isset($data['send_balance']) ? decryptData($data['send_balance']) : 0;
                $transactionsList[] = $data;
            }
        }

        // Sort transactions by created_at descending (latest first)
        usort($transactionsList, function ($a, $b) {
            return strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0);
        });
    }
} catch (\Throwable $th) {
    $transactionsList = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBD PAY | Offline Send Money</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">
    <!-- QRCode.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
        }

        .offline-container {
            max-width: 950px;
            margin: 40px auto;
            display: flex;
            gap: 25px;
            padding: 0 20px;
        }

        .left-panel,
        .right-panel {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #022c22, #059669);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .right-panel {
            flex: 1.3;
        }

        .panel-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .balance-box {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            backdrop-filter: blur(5px);
        }

        .balance-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .balance-amount {
            font-size: 36px;
            font-weight: bold;
            margin-top: 8px;
            color: #fde047;
        }

        .user-details {
            margin-top: 25px;
            font-size: 14px;
            line-height: 1.8;
            opacity: 0.95;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #374151;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            outline: none;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: #059669;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #022c22, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-submit:hover {
            opacity: 0.95;
        }

        .qr-wrapper {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px dashed #e5e7eb;
        }

        #qrcode {
            display: inline-block;
            padding: 12px;
            background: #ffffff;
            border: 2px solid #059669;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .token-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-family: monospace;
            margin-top: 8px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-card {
            background: white;
            padding: 30px;
            border-radius: 18px;
            width: 90%;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-card h3 {
            margin-bottom: 15px;
            color: #022c22;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 8px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .message {
            text-align: center;
            color: #047857;
            margin-bottom: 15px;
        }

        .message_f {
            text-align: center;
            color: #ec0707;
            margin-bottom: 15px;
        }

        /* Full Width Table Section Styling */
        .table-container {
            max-width: 950px;
            margin: 0 auto 40px auto;
            padding: 0 20px;
        }

        .table-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }

        .custom-table th,
        .custom-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .custom-table th {
            background-color: #f8fafc;
            color: #022c22;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .custom-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            text-transform: capitalize;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }

        .status-completed,
        .status-success {
            background-color: #d1fae5;
            color: #059669;
        }

        .status-failed {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .sync-badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .sync-yes {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .sync-no {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .info-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .info-btn:hover {
            background: #bae6fd;
            transform: scale(1.05);
        }

        .transaction-info-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
        }

        .transaction-info-modal.show {
            display: flex;
        }

        .transaction-info-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            border-radius: 18px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
            animation: infoPopup 0.2s ease-out;
        }

        @keyframes infoPopup {
            from {
                opacity: 0;
                transform: scale(0.92) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .transaction-info-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #e0f2fe;
            color: #0369a1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 700;
        }

        .transaction-info-card h3 {
            color: #022c22;
            margin-bottom: 12px;
        }

        .transaction-info-card p {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .transaction-info-close {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #022c22, #059669);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        @media(max-width: 768px) {
            .offline-container {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <?php require_once 'navbar.php'; ?>

    <div class="offline-container">
        <!-- LEFT SIDE: Wallet Balance Section -->
        <div class="left-panel">
            <div>
                <div class="panel-title">💳 Wallet Overview</div>
                <div class="balance-box">
                    <div class="balance-label">Offline Wallet Balance</div>
                    <div class="balance-amount">₹<?php echo number_format((float)$u_balance, 2); ?></div>
                </div>
            </div>

            <div class="user-details">
                <p><strong>Account Holder:</strong> <?php echo htmlspecialchars($u_name); ?></p>
                <p><strong>Wallet ID:</strong> <?php echo htmlspecialchars($u_wallet_id); ?></p>
                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($u_mob); ?></p>
            </div>
        </div>

        <!-- RIGHT SIDE: Send Money Option -->
        <div class="right-panel">
            <?php

            if ($message != "") {

                echo "
        <div class='message'>
        $message
        </div>
                ";
            }

            if ($message_f != "") {

                echo "
        <div class='message_f'>
        $message_f
        </div>
                ";
            }

            ?>
            <div class="panel-title" style="color:#022c22;">📲 Send Money Offline</div>

            <!-- Message banner displayed when QR is generated -->
            <?php if ($verify): ?>
                <div class="alert-error" id="qrActiveMsg" style="background-color: #fef3c7; color: #92400e; padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: 600; text-align: center;">
                    ⚠ A QR code has been generated. Please wait for the QR code to expire before initiating a new transaction.
                </div>
            <?php endif; ?>

            <form id="offlineForm" onsubmit="openPinModal(event)">
                <div class="form-group">
                    <label for="receiver_mobile">Enter Receiver Mobile No.</label>
                    <p style="color:#dc2626; font-size:13px; margin-top:6px; font-weight:600;">
                        * Please fill this field carefully.</p>
                    <input type="tel"
                        id="receiver_mobile"
                        class="form-control"
                        placeholder="10-digit mobile number"
                        inputmode="numeric"
                        maxlength="10"
                        pattern="[0-9]{10}"
                        oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10);"
                        value="<?php echo $verify ? htmlspecialchars($receiver_mobile, ENT_QUOTES) : ''; ?>"
                        <?php echo $verify ? 'readonly' : ''; ?>
                        required>
                </div>

                <div class="form-group">
                    <label for="amount">Enter Amount (₹)</label>
                    <input type="number"
                        id="amount"
                        class="form-control"
                        placeholder="0.00"
                        min="1"
                        max="<?php echo $u_balance; ?>"
                        step="any"
                        value="<?php echo $verify ? htmlspecialchars((string)$submitted_amount, ENT_QUOTES) : ''; ?>"
                        <?php echo $verify ? 'readonly' : ''; ?>
                        required>
                </div>

                <!-- Hidden when QR is generated ($verify is true) -->
                <button type="submit" id="btnProceed" class="btn-submit" style="display: <?php echo $verify ? 'none' : 'block'; ?>;">
                    Proceed to Send
                </button>
            </form>

            <!-- QR Container -->
            <div id="qrWrapper" class="qr-wrapper" style="display: <?php echo $verify ? 'block' : 'none'; ?>;">
                <div id="qrcode"></div>
                <div style="margin: 8px 0 15px; font-size: 18px; font-weight: bold; color: #dc2626;">
                    QR expires in <span id="qrTimer">40</span> seconds
                </div>
                <div>
                    <strong>Amount:</strong> ₹<span id="displayAmount"></span><br>
                </div>
            </div>
        </div>
    </div>

    <!-- BELOW SECTION: All Offline Transactions Data Table -->
    <div class="table-container">
        <div class="table-card">
            <div class="panel-title" style="color:#022c22;">📊 Transaction History</div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>S No.</th>
                            <th>Date & Time</th>
                            <th>Reciever Mobile</th>
                            <th>Amount (₹)</th>
                            <th>Status</th>
                            <th>Server Sync</th>
                            <th>Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactionsList)): ?>
                            <?php foreach ($transactionsList as $index => $trx): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($trx['created_at'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $recieverMobile = isset($trx['reciever_mobile'])
                                            ? decryptData($trx['reciever_mobile'])
                                            : 'N/A';

                                        if (preg_match('/^[0-9]{10}$/', $recieverMobile)) {
                                            $maskedMobile = substr($recieverMobile, 0, 2) . '******' . substr($recieverMobile, -2);
                                        } else {
                                            $maskedMobile = $recieverMobile;
                                        }
                                        ?>
                                        <?php echo htmlspecialchars($maskedMobile); ?>
                                    </td>
                                    <td><strong>₹<?php echo number_format((float)($trx['send_balance_decrypted'] ?? 0), 2); ?></strong></td>
                                    <td>
                                        <?php
                                        $status = strtolower($trx['status'] ?? 'Not Scanned');
                                        $statusClass = 'status-' .$status;
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($trx['status'] ?? 'Not Scanned'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($trx['server_sync'])): ?>
                                            <span class="sync-badge sync-yes"> <?php echo htmlspecialchars($trx['server_syn'] ?? 'Pending'); ?></span>
                                        <?php else: ?>
                                            <span class="sync-badge sync-no">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button"
                                            class="info-btn"
                                            aria-label="Transaction information"
                                            onclick="openTransactionInfo()"
                                            title="Transaction information">i</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #6b7280; padding: 20px;">No transaction records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TRANSACTION INFORMATION POPUP -->
    <div id="transactionInfoModal"
        class="transaction-info-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="transactionInfoTitle"
        onclick="closeTransactionInfo(event)">
        <div class="transaction-info-card" onclick="event.stopPropagation()">
            <div class="transaction-info-icon">i</div>
            <h3 id="transactionInfoTitle">Transaction Information</h3>
            <p>
                If the mobile number is wrong, the deducted amount will be settled after synchronization.
            </p>
            <button type="button"
                class="transaction-info-close"
                onclick="closeTransactionInfo()">Close</button>
        </div>
    </div>

    <!-- PIN VERIFICATION MODAL -->
    <div id="pinModal" class="modal-overlay" style="display: <?php echo !empty($pin_error) ? 'flex' : 'none'; ?>;">
        <div class="modal-card">
            <form method="post">
                <h3>Enter Offline PIN</h3>
                <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Please enter your 4-digit security PIN to authorize this offline transaction.</p>

                <?php if (!empty($pin_error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($pin_error); ?></div>
                <?php endif; ?>

                <!-- Hidden Inputs to preserve transaction details across POST request -->
                <input type="hidden" name="form_amount" id="modalHiddenAmount" value="<?php echo htmlspecialchars($submitted_amount ?? ''); ?>">
                <input type="hidden" name="receiver_mobile" id="modalReceiverMobile" value="<?php echo htmlspecialchars($receiver_mobile ?? ''); ?>">

                <div class="form-group">
                    <input type="password" name="pin" id="modalPin" class="form-control" style="text-align:center; font-size: 22px; letter-spacing: 5px;" maxlength="4" placeholder="••••" required>
                </div>

                <button type="submit" name="verify_pin" class="btn-submit" style="margin-bottom: 10px;">Verify & Generate QR</button>
                <button type="button" onclick="closePinModal()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size: 14px;">Cancel</button>
            </form>
        </div>
    </div>

    <?php require_once 'footer.php'; ?>

    <script>
        function openPinModal(e) {
            e.preventDefault();

            const receiverMobile = document.getElementById('receiver_mobile').value.trim();
            const amount = document.getElementById('amount').value;

            // Check receiver mobile
            if (!/^[0-9]{10}$/.test(receiverMobile)) {
                alert("Please enter a valid 10-digit receiver mobile number.");
                return;
            }

            // Check if receiver is the sender
            const senderMobile = "<?php echo htmlspecialchars($u_mob, ENT_QUOTES); ?>";

            if (receiverMobile === senderMobile) {
                alert("You cannot send money to your own mobile number.");
                return; // Stop here — PIN modal and QR will NOT open
            }

            // Check amount
            if (!amount || parseFloat(amount) <= 0) {
                alert("Please enter a valid amount.");
                return;
            }

            // Everything is valid, now open PIN modal
            document.getElementById('modalReceiverMobile').value = receiverMobile;
            document.getElementById('modalHiddenAmount').value = amount;
            document.getElementById('modalPin').value = '';

            document.getElementById('pinModal').style.display = 'flex';
        }

        function closePinModal() {
            document.getElementById('pinModal').style.display = 'none';
        }

        function openTransactionInfo() {
            document.getElementById('transactionInfoModal').classList.add('show');
        }

        function closeTransactionInfo(event) {
            // Close when the Close button is clicked or the dark backdrop is clicked.
            if (!event || event.target.id === 'transactionInfoModal') {
                document.getElementById('transactionInfoModal').classList.remove('show');
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('transactionInfoModal').classList.remove('show');
            }
        });

        function getFormattedDateTime(timestamp = Date.now()) {
            const d = new Date(timestamp);

            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');

            let hours = d.getHours();
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const seconds = String(d.getSeconds()).padStart(2, '0');

            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // convert 0 to 12
            const hoursStr = String(hours).padStart(2, '0');

            return `${year}-${month}-${day} ${hoursStr}:${minutes}:${seconds} ${ampm}`;
        }

        function generateQRCode(amount) {
            const qrcodeContainer = document.getElementById('qrcode');
            const timestamp = Date.now();
            const formattedDateTime = getFormattedDateTime(timestamp);

            <?php

            $trans_mode = 'offline';

            $s_amount =$d_send_amount;

            ?>

            const payload = {
                pay_mode: "<?php echo encryptData($trans_mode); ?>",
                token_id: "<?php echo $token_id; ?>",
                amount: "<?php echo encryptData($s_amount); ?>",
                sender_mobile: "<?php echo encryptData($u_mob); ?>",
                receiver_mobile: "<?php echo $verify ? encryptData($receiver_mobile) : ''; ?>",
            };

            qrcodeContainer.innerHTML = '';
            new QRCode(qrcodeContainer, {
                text: JSON.stringify(payload),
                width: 250,
                height: 250,
                colorDark: "#022c22",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            document.getElementById('displayAmount').innerText = parseFloat(amount).toFixed(2);
        }

        // Auto-generate QR if a valid QR exists in the PHP session.
        // The original creation time is used so refreshing the page does
        // not restart the 40-second countdown.
        <?php if ($verify &&$submitted_amount > 0): ?>
            window.addEventListener('DOMContentLoaded', () => {
                const qrWrapper = document.getElementById('qrWrapper');
                const qrContainer = document.getElementById('qrcode');
                const timerElement = document.getElementById('qrTimer');
                const btnProceed = document.getElementById('btnProceed');
                const qrActiveMsg = document.getElementById('qrActiveMsg');
                const receiverMobileInput = document.getElementById('receiver_mobile');
                const amountInput = document.getElementById('amount');

                // Function to restore form state when QR expires
                function restoreFormOnExpire() {
                    qrContainer.innerHTML = '';
                    qrWrapper.style.display = 'none';
                    timerElement.innerText = '0';

                    // Display Proceed button and hide QR message
                    if (btnProceed) btnProceed.style.display = 'block';
                    if (qrActiveMsg) qrActiveMsg.style.display = 'none';

                    // Make input fields editable again
                    if (receiverMobileInput) receiverMobileInput.removeAttribute('readonly');
                    if (amountInput) amountInput.removeAttribute('readonly');
                }

                // Generate the same transaction QR from the restored session data.
                generateQRCode(<?php echo json_encode((float)$submitted_amount); ?>);

                // Use the server-side creation time, not the page-load time.
                const qrCreatedAt = <?php
                                    echo json_encode(
                                        isset($_SESSION['offline_qr']['created_at'])
                                            ? ((int)$_SESSION['offline_qr']['created_at'] * 1000)
                                            : (time() * 1000)
                                    );
                                    ?>;

                const qrExpiresAt = qrCreatedAt + 40000;

                qrWrapper.style.display = 'block';

                // Calculate remaining time immediately.
                const initialRemainingMs = qrExpiresAt - Date.now();

                if (initialRemainingMs <= 0) {
                    restoreFormOnExpire();
                    return;
                }

                timerElement.innerText = Math.ceil(initialRemainingMs / 1000);

                const qrCountdown = setInterval(() => {
                    const remainingMs = qrExpiresAt - Date.now();
                    const remainingSeconds = Math.max(
                        0,
                        Math.ceil(remainingMs / 1000)
                    );

                    timerElement.innerText = remainingSeconds;

                    if (remainingMs <= 0) {
                        clearInterval(qrCountdown);
                        restoreFormOnExpire();
                    }
                }, 100);
            });
        <?php endif; ?>
    </script>
</body>

</html>