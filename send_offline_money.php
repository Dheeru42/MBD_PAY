<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

require_once "conn.php";

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

// verify pin
try {
    if (isset($_POST['verify_pin'])) {
        $pin = $_POST['pin'];
        if (password_verify(
            $pin,
            $cache['pin']
        )) {

            $verify = true;
            echo 'Pin verified';
        }
    }
} catch (\Throwable $th) {
    echo 'error';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBD PAY | Offline Send Money</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">
    <!-- Include QRCode.js library -->
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

        /* Two-Column Layout */
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

        /* Left Side: Balance Card */
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

        /* Right Side: Form */
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

        /* QR Output Section */
        .qr-wrapper {
            display: none;
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

        /* Modal Popup Styles */
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
            display: none;
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
            <div class="panel-title" style="color:#022c22;">📲 Send Money Offline</div>

            <form id="offlineForm" onsubmit="openPinModal(event)">
                <div class="form-group">
                    <label for="amount">Enter Amount (₹)</label>
                    <input type="number" id="amount" class="form-control" placeholder="0.00" min="1" max="<?php echo $u_balance; ?>" step="any" required>
                </div>

                <button type="submit" class="btn-submit">Proceed to Send</button>
            </form>
            <?php if ($verify) { ?>
                <!-- QR Code Container -->
                <div id="qrWrapper" class="qr-wrapper">
                    <div id="qrcode"></div>
                    <div>
                        <strong>Amount:</strong> ₹<span id="displayAmount"></span><br>
                        <div class="token-badge">Token ID: <span id="displayToken"></span></div>
                    </div>
                </div>
        </div>
    </div>
<?php } ?>

<!-- PIN VERIFICATION MODAL -->
<div id="pinModal" class="modal-overlay">
    <div class="modal-card">
        <form method="post">
            <h3>Enter Offline PIN</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Please enter your 4-digit security PIN to authorize this offline transaction.</p>

            <div id="modalAlert" class="alert-error"></div>

            <div class="form-group">
                <input type="password" name='pin' id="modalPin" class="form-control" style="text-align:center; font-size: 22px; letter-spacing: 5px;" maxlength="4" placeholder="••••" required>
            </div>

            <button onclick="verifyPinAndGenerateQR()" class="btn-submit" name='verify_pin' style="margin-bottom: 10px;">Verify & Generate QR</button>
            <button onclick="closePinModal()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size: 14px;">Cancel</button>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
    let pendingAmount = 0;

    function openPinModal(e) {
        e.preventDefault();
        pendingAmount = document.getElementById('amount').value;
        document.getElementById('modalPin').value = '';
        document.getElementById('modalAlert').style.display = 'none';
        document.getElementById('pinModal').style.display = 'flex';
    }

    function closePinModal() {
        document.getElementById('pinModal').style.display = 'none';
    }

    function verifyPinAndGenerateQR() {
        const enteredPin = document.getElementById('modalPin').value;
        const modalAlert = document.getElementById('modalAlert');

        // Retrieve PIN cached locally during login/signup
        const cachedPin = localStorage.getItem('user_pin') || localStorage.getItem('cached_pin');

        if (!cachedPin) {
            modalAlert.innerText = "No cached PIN found! Please login online first.";
            modalAlert.style.display = 'block';
            return;
        }

        // Verify PIN against local cache
        if (enteredPin !== cachedPin) {
            modalAlert.innerText = "Incorrect PIN! Please try again.";
            modalAlert.style.display = 'block';
            return;
        }

        // PIN verified successfully: Close modal and generate QR
        closePinModal();
        generateQRCode(pendingAmount);
    }

    function generateQRCode(amount) {
        const qrWrapper = document.getElementById('qrWrapper');
        const qrcodeContainer = document.getElementById('qrcode');

        // Generate Unique Token ID
        const timestamp = Date.now();
        const randomPart = Math.random().toString(36).substring(2, 8).toUpperCase();
        const uniqueToken = `MBD-${timestamp}-${randomPart}`;

        // Build payload
        const payload = {
            token_id: uniqueToken,
            amount: parseFloat(amount).toFixed(2),
            sender_wallet: "<?php echo $u_wallet_id; ?>",
            timestamp: timestamp
        };

        // Render QR Code
        qrcodeContainer.innerHTML = '';
        new QRCode(qrcodeContainer, {
            text: JSON.stringify(payload),
            width: 180,
            height: 180,
            colorDark: "#022c22",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        document.getElementById('displayAmount').innerText = parseFloat(amount).toFixed(2);
        document.getElementById('displayToken').innerText = uniqueToken;
        qrWrapper.style.display = 'block';
    }
</script>
</body>

</html>