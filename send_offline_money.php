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

    $cipher = openssl_encrypt($text,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode($iv .$cipher);
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

// Generate the unique token in PHP
$timestamp = time();
$randomPart = rand(1000, 9000);$uniqueToken = "MBD-" . $timestamp . "-" . $randomPart;

// Encrypt it in PHP
$token_id = encryptData($uniqueToken);

if($serverConnected){
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
    $_SESSION['user'] =$_COOKIE['remember_user'];
}

if (!isset($_SESSION['mobile'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}

$u_wallet_id =$_SESSION['wallet_id'];

$u_account =$_SESSION['account'];

$u_mob =$_SESSION['mobile'];

$u_name =$_SESSION['user'];

// code to read cache data 

$userId = hash("sha256", $u_mob);

$profile = CACHE_DIR .$userId . "/profile.json";

$cache = json_decode(
    file_get_contents($profile),
    true
);

$u_balance = decryptData($cache['balance']);

$verify = false;

$d_send_amount = 0;

// verify pin
try {
    if (isset($_POST['verify_pin'])) {
        $pin =$_POST['pin'];
        $submitted_amount =$_POST['form_amount'] ?? 0;
        if ($cache['send_limit'] == 0) {$message_f = "You have exceeded your sending limit.Please synchronize your wallet to continue.";
        } else {
            if (password_verify(
                $pin,$cache['pin']
            )) {
                $message = "Pin Verified & QR Generated";

                $verify = true;

                $d_send_amount =$submitted_amount;

                /* code to deduct offline money from cache  */

                // code to deduct balance from cache

                $profile = CACHE_DIR .$userId . "/profile.json";

                $cache_bal = json_decode(
                    file_get_contents($profile),
                    true
                );

                $offline_wallet_balance = decryptData($cache_bal['balance']);

                $updated_offline_wallet_bal = $offline_wallet_balance -$submitted_amount;

                $cache_bal['balance'] = encryptData($updated_offline_wallet_bal);

                $cache_bal['update_at'] = date("Y-m-d h:i:s A");

                file_put_contents(
                    $profile,
                    json_encode(
                        $cache_bal,
                        JSON_PRETTY_PRINT
                    )
                );

                $u_balance =$updated_offline_wallet_bal; // display updated balance


                /* code to insert offline transaction in transaction folder */

                $trx_folder = CACHE_DIR .$userId . "/transactions";

                // Create transactions cache folder if not exist
                if (!is_dir($trx_folder)) {
                    mkdir($trx_folder, 0777, true);
                }

                // insert trx in transaction

                $trx_data = [

                    "token_id" => $token_id,

                    "wallet_id" => encryptData($u_wallet_id),

                    "mobile" => encryptData($u_mob),

                    "send_balance" => encryptData($submitted_amount),

                    "status" => "pending",

                    "created_at" => date("Y-m-d h:i:s A"),

                    "update_at" => date("Y-m-d h:i:s A"),

                    "server_sync" => false

                ];

                $trx_id =$token_id;

                $trx_file = hash("sha256", $trx_id);
                // Save currency in cache
                $trx_cacheFile = $trx_folder . "/" . $trx_file . ".json";

                file_put_contents(
                    $trx_cacheFile,
                    json_encode($trx_data, JSON_PRETTY_PRINT)
                );


                /* code to restrict the sender to send offline money by qr after limit = 2 */

                $profile = CACHE_DIR .$userId . "/profile.json";

                $cache_limit = json_decode(
                    file_get_contents($profile),
                    true
                );

                $send_limit =$cache_limit['send_limit'] - 1; // every qr generate

                $cache_limit['send_limit'] =$send_limit;

                file_put_contents(
                    $profile,
                    json_encode(
                        $cache_limit,
                        JSON_PRETTY_PRINT
                    )
                );
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
$trxDir = CACHE_DIR .$userId . "/transactions";

try{
if (is_dir($trxDir)) {
    $files = glob($trxDir . "/*.json");
    foreach ($files as$file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {$data['send_balance_decrypted'] = isset($data['send_balance']) ? decryptData($data['send_balance']) : 0;
            $transactionsList[] =$data;
        }
    }
    
    // Sort transactions by created_at descending (latest first)
    usort($transactionsList, function ($a,$b) {
        return strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0);
    });
}
}catch(\Throwable $th)
{
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

        .status-completed, .status-success {
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

            <form id="offlineForm" onsubmit="openPinModal(event)">
                <div class="form-group">
                    <label for="amount">Enter Amount (₹)</label>
                    <input type="number" id="amount" class="form-control" placeholder="0.00" min="1" max="<?php echo $u_balance; ?>" step="any" required>
                </div>

                <button type="submit" class="btn-submit">Proceed to Send</button>
            </form>

            <!-- QR Container -->
            <div id="qrWrapper" class="qr-wrapper" style="display: <?php echo $verify ? 'block' : 'none'; ?>;">
                <div id="qrcode"></div>
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
                            <th>Amount (₹)</th>
                            <th>Status</th>
                            <th>Server Sync</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactionsList)): ?>
                            <?php foreach ($transactionsList as $index =>$trx): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($trx['created_at'] ?? 'N/A'); ?></td>
                                    <td><strong>₹<?php echo number_format((float)($trx['send_balance_decrypted'] ?? 0), 2); ?></strong></td>
                                    <td>
                                        <?php 
                                            $status = strtolower($trx['status'] ?? 'pending');
                                            $statusClass = 'status-' .$status;
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($trx['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($trx['server_sync'])): ?>
                                            <span class="sync-badge sync-yes">Synced</span>
                                        <?php else: ?>
                                            <span class="sync-badge sync-no">Pending Sync</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #6b7280; padding: 20px;">No transaction records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

                <!-- Hidden Input to preserve amount across POST request -->
                <input type="hidden" name="form_amount" id="modalHiddenAmount" value="<?php echo htmlspecialchars($submitted_amount); ?>">

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
            const amt = document.getElementById('amount').value;
            document.getElementById('modalHiddenAmount').value = amt;
            document.getElementById('modalPin').value = '';
            document.getElementById('pinModal').style.display = 'flex';
        }

        function closePinModal() {
            document.getElementById('pinModal').style.display = 'none';
        }

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
                sender_wallet_id: "<?php echo encryptData($u_wallet_id); ?>",
                sender_mobile: "<?php echo encryptData($u_mob); ?>",
                sender_account: "<?php echo encryptData($u_account); ?>",
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
            document.getElementById('displayToken').innerText = uniqueToken;
        }

        // Auto-generate QR if verified via PHP form submission
        <?php if ($verify &&$submitted_amount > 0): ?>
            window.addEventListener('DOMContentLoaded', () => {
                generateQRCode(<?php echo json_encode($submitted_amount); ?>);
            });
        <?php endif; ?>
    </script>
</body>

</html>