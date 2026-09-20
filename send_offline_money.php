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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBD Pay - Offline Send Money</title>
    <!-- Include QRCode.js library for offline QR generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* Card Container Styling matching the theme */
        .pay-card {
            max-width: 450px;
            margin: 30px auto 100px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .pay-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #022c22;
            margin-bottom: 20px;
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

        .btn-generate {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #022c22, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-generate:hover {
            opacity: 0.95;
            transform: translateY(-2px);
        }

        /* Error/Status Alerts */
        .alert {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            display: none;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }

        /* Display area for output QR Code */
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

        .qr-info {
            font-size: 14px;
            color: #1f2937;
            word-break: break-all;
        }

        .token-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: bold;
            font-family: monospace;
            margin-top: 5px;
        }
    </style>
</head>

<body>

    <!-- NAV BAR -->
    <?php require_once 'navbar.php'; ?>

    <div class="pay-card">
        <div class="pay-title">📲 Send Money Offline</div>

        <div id="errorAlert" class="alert alert-error"></div>

        <form id="offlinePayForm" onsubmit="handleGenerateQR(event)">
            <div class="form-group">
                <label for="amount">Enter Amount (₹)</label>
                <input type="number" id="amount" class="form-control" placeholder="0.00" min="1" step="any" required>
            </div>

            <div class="form-group">
                <label for="userPin">Enter Offline Security PIN</label>
                <input type="password" id="userPin" class="form-control" placeholder="••••" maxlength="6" required>
            </div>

            <button type="submit" class="btn-generate">Generate Payment QR</button>
        </form>

        <!-- QR Code Output Display -->
        <div id="qrWrapper" class="qr-wrapper">
            <div id="qrcode"></div>
            <div class="qr-info">
                <strong>Amount:</strong> ₹<span id="displayAmount"></span><br>
                <div class="token-badge">Token: <span id="displayToken"></span></div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
   <?php require_once 'footer.php'; ?>

    <script>
        // Set a default offline PIN in localStorage if none exists for testing
        if (!localStorage.getItem('cached_pin')) {
            localStorage.setItem('cached_pin', '1234'); // Default PIN for demonstration
        }

        function handleGenerateQR(e) {
            e.preventDefault();

            const amountInput = document.getElementById('amount').value;
            const pinInput = document.getElementById('userPin').value;
            const cachedPin = localStorage.getItem('cached_pin');
            const alertBox = document.getElementById('errorAlert');
            const qrWrapper = document.getElementById('qrWrapper');
            const qrcodeContainer = document.getElementById('qrcode');

            // Hide existing alert and QR container
            alertBox.style.display = 'none';
            qrWrapper.style.display = 'none';

            // Step 1: Verify PIN against local cache
            if (pinInput !== cachedPin) {
                alertBox.innerText = 'Invalid PIN! Please check your offline security PIN.';
                alertBox.style.display = 'block';
                return;
            }

            // Step 2: Generate Unique Token Number
            const timestamp = Date.now();
            const randomStr = Math.random().toString(36).substring(2, 8).toUpperCase();
            const uniqueToken = `MBD-${timestamp}-${randomStr}`;

            // Step 3: Construct Payload Object & Convert to JSON String
            const paymentPayload = {
                token: uniqueToken,
                amount: parseFloat(amountInput).toFixed(2),
                timestamp: timestamp,
                type: 'OFFLINE_PAYMENT'
            };

            const qrDataString = JSON.stringify(paymentPayload);

            // Step 4: Clear previous QR code and construct new QR
            qrcodeContainer.innerHTML = '';
            new QRCode(qrcodeContainer, {
                text: qrDataString,
                width: 180,
                height: 180,
                colorDark: "#022c22",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            // Display details
            document.getElementById('displayAmount').innerText = parseFloat(amountInput).toFixed(2);
            document.getElementById('displayToken').innerText = uniqueToken;
            qrWrapper.style.display = 'block';
        }
    </script>

</body>

</html>