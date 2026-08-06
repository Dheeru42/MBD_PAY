<?php

session_start();


require 'conn.php';
require 'bank_conn.php';
require 'currency_con.php';

define("SECRET_KEY", "MBDPAY@2026_SUPER_SECRET_KEY_32");


if (isset($_SESSION['mobile'])) {
    $u_mob = $_SESSION['mobile'];
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}

$u_account = $_SESSION['account'];

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

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| USER INFORMATION
|--------------------------------------------------------------------------
|
| This assumes your login session stores the user's ID in:
| $_SESSION['user']
|
*/

$user_id = (int) $_SESSION['user'];

$message = "";
$message_type = "";

$generated_currency = null;


/*
|--------------------------------------------------------------------------
| GENERATE CURRENCY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = isset($_POST['amount'])
        ? (float) $_POST['amount']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($amount <= 0) {

        $message = "Please enter a valid amount.";
        $message_type = "error";
    } elseif ($amount > 1000) {

        $message = "Maximum currency amount is ₹1,000.";
        $message_type = "error";


        /*
    |--------------------------------------------------------------------------
    | CHECK SERVER CONNECTION
    |--------------------------------------------------------------------------
    */
    } elseif (!isset($serverConnected) || !$serverConnected) {

        $message =
            "MBD Server is offline. Currency generation is available only when online.";

        $message_type = "error";
    } else {

        $u_account = $_SESSION['account'];
        $u_mob     = $_SESSION['mobile'];


        /*
        |--------------------------------------------------------------------------
        | START DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | LOCK USER WALLET
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "SELECT id, name, balance
                 FROM users
                 WHERE account_no = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception(
                    "Unable to access wallet."
                );
            }

            $stmt->bind_param(
                "s",
                $u_account
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $user = $result->fetch_assoc();

            $stmt->close();

            // fetch latest id 
            $result = mysqli_query(
                $c_conn,
                "SELECT id FROM currency ORDER BY id DESC LIMIT 1"
            );

            $row = mysqli_fetch_assoc($result);

            $last_id = $row['id'];

            $next_id = $last_id + 1;

            // GENERATE UNIQUE CURRENCY SERIAL
            $serial_no = "MBD-" . str_pad(
                $next_id,
                3,
                "0",
                STR_PAD_LEFT
            );


            /*
            |--------------------------------------------------------------------------
            | CHECK USER
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                throw new Exception(
                    "User account not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DECRYPT CURRENT BALANCE
            |--------------------------------------------------------------------------
            */

            $current_balance =
                (float) decryptData(
                    $user['balance']
                );


            /*
            |--------------------------------------------------------------------------
            | CHECK BALANCE
            |--------------------------------------------------------------------------
            */

            if ($current_balance < $amount) {

                throw new Exception(
                    "Insufficient wallet balance."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CALCULATE NEW BALANCE
            |--------------------------------------------------------------------------
            */

            $new_balance =
                round(
                    $current_balance - $amount,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | ENCRYPT AMOUNT AND BALANCES
            |--------------------------------------------------------------------------
            */

            $encrypted_amount =
                encryptData(
                    number_format(
                        $amount,
                        2,
                        '.',
                        ''
                    )
                );

            $encrypted_old_balance =
                encryptData(
                    number_format(
                        $current_balance,
                        2,
                        '.',
                        ''
                    )
                );

            $encrypted_new_balance =
                encryptData(
                    number_format(
                        $new_balance,
                        2,
                        '.',
                        ''
                    )
                );


            // GENERATE UNIQUE CURRENCY SERIAL
            $c_serial_no =
                "MBD-" .
                date("YmdHis") .
                "-" .
                strtoupper(
                    bin2hex(
                        random_bytes(8)
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | ENCRYPT SERIAL
            |--------------------------------------------------------------------------
            */

            $encrypted_serial =
                encryptData(
                    $c_serial_no
                );


            /*
            |--------------------------------------------------------------------------
            | GENERATE TRANSACTION ID
            |--------------------------------------------------------------------------
            */

            $transaction_id =
                "MBD" .
                date("YmdHis") .
                strtoupper(
                    bin2hex(
                        random_bytes(5)
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | UPDATE USER BALANCE
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "UPDATE users
                 SET balance = ?
                 WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "Unable to update wallet."
                );
            }

            $stmt->bind_param(
                "si",
                $encrypted_new_balance,
                $user['id']
            );

            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to reserve wallet balance."
                );
            }

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | INSERT CURRENCY
            |--------------------------------------------------------------------------
            |
            | Currency remains active until scanned.
            |
            */

            $stmt = $c_conn->prepare(
                "INSERT INTO currency
                (
                    serial_no,
                    encrypted_serial,
                    sender_mobile,
                    amount,
                    status,
                    generated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )"
            );

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare currency creation."
                );
            }


            $currency_status = "GENERATED";


            $stmt->bind_param(
                "sssss",
                $serial_no,
                $encrypted_serial,
                $u_mob,
                $encrypted_amount,
                $currency_status
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to create currency."
                );
            }

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | INSERT WALLET TRANSACTION
            |--------------------------------------------------------------------------
            */

            $transaction_type =
                "Currency Generated";

            $description =
                "MBD Pay currency generated";

            $transaction_status =
                "Success";


            $stmt = $conn->prepare(
                "INSERT INTO transactions
                (
                    transaction_id,
                    mobile,
                    type,
                    amount,
                    balance_before,
                    balance_after,
                    description,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );


            if (!$stmt) {
                throw new Exception(
                    "Unable to create transaction."
                );
            }


            $stmt->bind_param(
                "ssssssss",
                $transaction_id,
                $u_mob,
                $transaction_type,
                $encrypted_amount,
                $encrypted_old_balance,
                $encrypted_new_balance,
                $description,
                $transaction_status
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to record transaction."
                );
            }

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | QR PAYLOAD
            |--------------------------------------------------------------------------
            |
            | No expiry.
            | No balance.
            | No password.
            | No PIN.
            |
            */

            $qr_payload = json_encode([
                "type"     => "MBD_CURRENCY",
                "version"  => 1,
                "serial"   => $serial_no,
                "currency" => "INR"
            ], JSON_UNESCAPED_SLASHES);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | UPDATE SESSION BALANCE
            |--------------------------------------------------------------------------
            */

            $_SESSION['balance'] =
                $new_balance;


            /*
            |--------------------------------------------------------------------------
            | GENERATED CURRENCY
            |--------------------------------------------------------------------------
            */

            $generated_currency = [

                "serial_no" =>
                $serial_no,

                "amount" =>
                number_format(
                    $amount,
                    2
                ),

                "qr_payload" =>
                $qr_payload,

                "transaction_id" =>
                $transaction_id

            ];


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $message =
                "₹" .
                number_format(
                    $amount,
                    2
                ) .
                " currency generated successfully.";

            $message_type = "success";
        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            $conn->rollback();

            $message =
                $e->getMessage();

            $message_type =
                "error";
        }
    }
}

// FETCH RECENT CURRENCY

$history = [];

try {
    $stmt = $c_conn->prepare(
        "SELECT
        serial_no,
        amount,
        status,
        receiver_mobile,
        generated_at,
        scanned_at
     FROM currency
     WHERE sender_mobile = ?
     ORDER BY generated_at DESC
     LIMIT 10"
    );

    $stmt->bind_param("i", $u_mob);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $history[] = $row;
    }
    $stmt->close();
} catch (Throwable $th) {
    $message = "Please Connect to Internet.";
    $message_type = "error";
}
session_abort();
?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>MBDPAY | Generate QR Currency</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <!-- QR CODE LIBRARY -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>


    <style>
        .currency-page {

            min-height: calc(100vh - 135px);

            padding: 15px 20px 70px;

        }


        /*
|--------------------------------------------------------------------------
| MAIN CONTAINER
|--------------------------------------------------------------------------
*/

        .currency-container {

            max-width: 1050px;

            margin: 0 auto;

            display: grid;

            grid-template-columns: 1fr 1.1fr;

            gap: 25px;

        }


        /*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

        .currency-card {

            background: rgba(255, 255, 255, .88);

            backdrop-filter: blur(18px);

            border-radius: 30px;

            padding: 30px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .15);

            border:
                1px solid rgba(255, 255, 255, .7);

        }


        /*
|--------------------------------------------------------------------------
| TITLE
|--------------------------------------------------------------------------
*/

        .currency-title {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 25px;

        }


        .currency-icon {

            width: 55px;

            height: 55px;

            border-radius: 17px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 30px;

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            color: white;

            box-shadow:
                0 8px 20px rgba(245, 158, 11, .35);

        }


        .currency-title h1 {

            color: #022c22;

            font-size: 27px;

        }


        .currency-title p {

            margin-top: 5px;

            color: #64748b;

            font-size: 14px;

        }


        /*
|--------------------------------------------------------------------------
| AMOUNT
|--------------------------------------------------------------------------
*/

        .amount-label {

            display: block;

            font-weight: bold;

            color: #334155;

            margin-bottom: 10px;

        }


        .amount-wrapper {

            position: relative;

            margin-bottom: 20px;

        }


        .amount-symbol {

            position: absolute;

            left: 18px;

            top: 50%;

            transform: translateY(-50%);

            font-size: 25px;

            font-weight: bold;

            color: #059669;

        }


        .amount-input {

            width: 100%;

            height: 65px;

            border: 2px solid #d1fae5;

            border-radius: 18px;

            padding:
                0 20px 0 50px;

            font-size: 25px;

            font-weight: bold;

            color: #022c22;

            background: #f0fdf4;

            outline: none;

            transition: .3s;

        }


        .amount-input:focus {

            border-color: #059669;

            box-shadow:
                0 0 0 4px rgba(5, 150, 105, .12);

        }


        /*
|--------------------------------------------------------------------------
| QUICK AMOUNT
|--------------------------------------------------------------------------
*/

        .quick-title {

            font-size: 13px;

            color: #64748b;

            margin-bottom: 10px;

        }


        .quick-buttons {

            display: flex;

            flex-wrap: wrap;

            gap: 9px;

            margin-bottom: 25px;

        }


        .quick-buttons button {

            border: none;

            padding: 9px 15px;

            border-radius: 20px;

            background: #dcfce7;

            color: #047857;

            font-weight: bold;

            cursor: pointer;

            transition: .25s;

        }


        .quick-buttons button:hover {

            background: #059669;

            color: white;

            transform: translateY(-2px);

        }


        /*
|--------------------------------------------------------------------------
| GENERATE BUTTON
|--------------------------------------------------------------------------
*/

        .generate-btn {

            width: 100%;

            border: none;

            border-radius: 18px;

            padding: 17px;

            font-size: 17px;

            font-weight: bold;

            color: white;

            cursor: pointer;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .3);

            transition: .3s;

        }


        .generate-btn:hover {

            transform: translateY(-3px);

            box-shadow:
                0 15px 30px rgba(5, 150, 105, .4);

        }


        /*
|--------------------------------------------------------------------------
| INFO BOX
|--------------------------------------------------------------------------
*/

        .info-box {

            margin-top: 20px;

            padding: 15px;

            border-radius: 15px;

            background: #ecfdf5;

            color: #065f46;

            font-size: 13px;

            line-height: 1.6;

        }


        .info-box strong {

            color: #022c22;

        }


        /*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

        .message {

            max-width: 1050px;

            margin: 0 auto 18px;

            padding: 14px 18px;

            border-radius: 15px;

            font-weight: bold;

        }


        .message.success {

            background: #dcfce7;

            color: #166534;

        }


        .message.error {

            background: #fee2e2;

            color: #991b1b;

        }


        /*
|--------------------------------------------------------------------------
| QR CARD
|--------------------------------------------------------------------------
*/

        .qr-card {

            text-align: center;

            min-height: 520px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

        }


        .qr-heading {

            color: #022c22;

            font-size: 20px;

            margin-bottom: 5px;

        }


        .qr-subtitle {

            color: #64748b;

            font-size: 13px;

            margin-bottom: 20px;

        }


        .qr-box {

            width: 280px;

            height: 280px;

            background: white;

            border-radius: 22px;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 15px;

            box-shadow:
                0 12px 35px rgba(0, 0, 0, .15);

            border:
                5px solid #dcfce7;

        }


        #qrcode {

            display: flex;

            align-items: center;

            justify-content: center;

        }


        #qrcode img {

            width: 240px !important;

            height: 240px !important;

        }


        /*
|--------------------------------------------------------------------------
| AMOUNT DISPLAY
|--------------------------------------------------------------------------
*/

        .qr-amount {

            margin-top: 20px;

            font-size: 35px;

            font-weight: 900;

            color: #059669;

        }


        .qr-serial {

            margin-top: 5px;

            font-size: 11px;

            color: #64748b;

            word-break: break-all;

            max-width: 320px;

        }


        /*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

        .timer-box {

            margin-top: 15px;

            padding: 10px 18px;

            border-radius: 25px;

            background: #fef3c7;

            color: #92400e;

            font-weight: bold;

        }


        .timer-number {

            font-size: 20px;

        }


        /*
|--------------------------------------------------------------------------
| EXPIRED
|--------------------------------------------------------------------------
*/

        .expired-box {

            display: none;

            padding: 50px 20px;

            text-align: center;

        }


        .expired-icon {

            font-size: 65px;

            margin-bottom: 15px;

        }


        .expired-box h2 {

            color: #991b1b;

        }


        .expired-box p {

            color: #64748b;

            margin-top: 8px;

        }


        /*
|--------------------------------------------------------------------------
| EMPTY QR
|--------------------------------------------------------------------------
*/

        .empty-qr {

            color: #94a3b8;

            text-align: center;

        }


        .empty-qr-icon {

            font-size: 80px;

            opacity: .4;

            margin-bottom: 15px;

        }


        .empty-qr h2 {

            color: #475569;

            font-size: 20px;

        }


        .empty-qr p {

            font-size: 13px;

            margin-top: 7px;

        }


        /*
|--------------------------------------------------------------------------
| HISTORY
|--------------------------------------------------------------------------
*/

        .history-card {

            max-width: 1050px;

            margin: 25px auto 0;

        }


        .history-card h2 {

            color: #022c22;

            margin-bottom: 18px;

        }


        .history-table {

            width: 100%;

            border-collapse: collapse;

        }


        .history-table th {

            text-align: left;

            padding: 12px;

            background: #ecfdf5;

            color: #065f46;

            font-size: 13px;

        }


        .history-table td {

            padding: 12px;

            border-bottom: 1px solid #e2e8f0;

            font-size: 12px;

            color: #475569;

        }


        .serial-cell {

            max-width: 170px;

            word-break: break-all;

        }


        .status-badge {

            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-weight: bold;

            font-size: 11px;

        }


        .status-generated {

            background: #dcfce7;

            color: #166534;

        }


        .status-scanned {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .status-expired {

            background: #fee2e2;

            color: #991b1b;

        }


        .status-cancelled {

            background: #f1f5f9;

            color: #475569;

        }


        /*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

        @media(max-width:850px) {

            .currency-container {

                grid-template-columns: 1fr;

            }

            .qr-card {

                min-height: 450px;

            }

        }


        @media(max-width:600px) {

            .currency-page {

                padding:
                    10px 10px 75px;

            }

            .currency-card {

                padding: 20px;

                border-radius: 22px;

            }

            .qr-box {

                width: 250px;

                height: 250px;

            }

            #qrcode img {

                width: 210px !important;

                height: 210px !important;

            }

            .history-table {

                display: block;

                overflow-x: auto;

                white-space: nowrap;

            }

        }

        .bank-instructions {
            margin-top: 20px;
            text-align: left;
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
        }

        .bank-instructions h3 {
            color: #047857;
            margin-bottom: 15px;
        }

        .bank-instructions ul {
            padding-left: 20px;
            line-height: 1.8;
        }

        .bank-instructions li {
            margin-bottom: 10px;
            color: #374151;
        }

        .instruction-note {
            margin-top: 20px;
            padding: 15px;
            background: #fef3c7;
            border-left: 5px solid #f59e0b;
            border-radius: 8px;
            color: #92400e;
            font-size: 15px;
        }
    </style>

</head>


<body>


    <?php require 'navbar.php'; ?>


    <main class="currency-page">


        <?php if ($message !== "") { ?>

            <div class="message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>

        <div class="currency-container">


            <!-- =========================================================
     GENERATE FORM
========================================================= -->

            <section class="currency-card">


                <div class="currency-title">

                    <div class="currency-icon">
                        ₹
                    </div>

                    <div>

                        <h1>Generate Currency</h1>

                        <p>Create a digital MBD Pay currency</p>

                    </div>

                </div>


                <form method="POST" autocomplete="off">


                    <label class="amount-label">

                        Enter Currency Amount

                    </label>


                    <div class="amount-wrapper">

                        <span class="amount-symbol">₹</span>

                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="amount-input"
                            placeholder="0.00"
                            min="1"
                            max="100000"
                            step="0.01"
                            required>

                    </div>


                    <div class="quick-title">

                        Quick Amount

                    </div>


                    <div class="quick-buttons">

                        <button
                            type="button"
                            onclick="setAmount(10)">
                            ₹10
                        </button>

                        <button
                            type="button"
                            onclick="setAmount(20)">
                            ₹20
                        </button>

                        <button
                            type="button"
                            onclick="setAmount(50)">
                            ₹50
                        </button>

                        <button
                            type="button"
                            onclick="setAmount(100)">
                            ₹100
                        </button>

                        <button
                            type="button"
                            onclick="setAmount(500)">
                            ₹500
                        </button>

                        <button
                            type="button"
                            onclick="setAmount(1000)">
                            ₹1,000
                        </button>

                    </div>


                    <button
                        type="submit"
                        class="generate-btn">

                        📱 Generate QR Currency

                    </button>


                </form>


                <div class="info-box">

                    <strong>How it works</strong><br>

                    Enter an amount and generate a unique digital
                    currency serial number. The receiver scans the QR
                    to submit the currency serial number to the MBD Pay
                    server.

                    <br><br>

                    <strong>Security:</strong>
                    Never put your password, PIN or wallet balance
                    inside the QR code.

                </div>


            </section>


            <!-- =========================================================
     QR DISPLAY
========================================================= -->

            <section class="currency-card">

                <div class="currency-title">
                    <div class="currency-icon">₹</div>
                    <div>
                        <h1>MBD Digital Currency</h1>
                        <p>Usage Guidelines</p>
                    </div>
                </div>

                <div class="bank-instructions">

                    <h3>About MBD Digital Currency</h3>

                    <p>
                        MBD Digital Currency is a secure payment token generated within the
                        MBD Pay application. It is designed to make payments simple, fast,
                        and secure between MBD Pay users.
                    </p>

                    <ul>
                        <li>💳 Generated currency can only be used to make payments within the MBD Pay application.</li>

                        <li>🚫 Generated currency cannot be withdrawn as cash or transferred directly to a bank account.</li>

                        <li>🔒 Each generated currency contains a unique encrypted serial number to ensure secure transactions.</li>

                        <li>📱 The recipient can use the generated currency by scanning its QR code or entering the serial number in the MBD Pay app.</li>

                        <li>✅ Once the currency is successfully used, it becomes invalid and cannot be used again.</li>

                        <li>⏳ Unused currency remains valid until it is used or expires according to system rules.</li>

                        <li>🛡️ Do not share your currency QR code or serial number with unauthorized persons.</li>

                        <li>📋 You can track all generated and used currencies from your transaction history.</li>

                    </ul>

                    <div class="instruction-note">
                        <strong>Important:</strong><br>
                        MBD Digital Currency is intended exclusively for secure digital payments within the MBD Pay ecosystem. It is <strong>not withdrawable</strong> and cannot be converted directly into cash or deposited into a bank account.
                    </div>

                </div>

            </section>
        </div>


        <!-- =========================================================
     HISTORY
========================================================= -->

        <section class="currency-card history-card">


            <h2>

                Recent Generated Currency

            </h2>


            <?php if (count($history) > 0) { ?>


                <table class="history-table">

                    <thead>

                        <tr>

                            <th>Serial Number</th>

                            <th>Amount</th>

                            <th>Status</th>

                            <th>Receiver</th>

                            <th>Generated</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($history as $row) { ?>


                            <tr>

                                <td class="serial-cell">

                                    <?php echo htmlspecialchars(
                                        $row['serial_no']
                                    ); ?>

                                </td>


                                <td>

                                    ₹<?php echo number_format(
                                            decryptData($row['amount']),
                                            2
                                        ); ?>

                                </td>


                                <td>

                                    <span
                                        class="status-badge status-<?php
                                                                    echo htmlspecialchars(
                                                                        $row['status']
                                                                    );
                                                                    ?>">

                                        <?php
                                        echo ucfirst(
                                            $row['status']
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php

                                    echo $row['receiver_mobile']
                                        ? htmlspecialchars(
                                            $row['receiver_mobile']
                                        )
                                        : "—";

                                    ?>

                                </td>


                                <td>

                                    <?php echo htmlspecialchars(
                                        $row['generated_at']
                                    ); ?>

                                </td>

                            </tr>


                        <?php } ?>


                    </tbody>

                </table>


            <?php } else { ?>


                <p style="
            text-align:center;
            color:#64748b;
            padding:25px;
        ">

                    No currency generated yet.

                </p>


            <?php } ?>


        </section>


    </main>


    <?php require 'footer.php'; ?>


    <script>
        /*
|--------------------------------------------------------------------------
| QUICK AMOUNT
|--------------------------------------------------------------------------
*/

        function setAmount(value) {

            document.getElementById("amount").value = value;

        }


        /*
        |--------------------------------------------------------------------------
        | QR GENERATION
        |--------------------------------------------------------------------------
        */

        <?php if (!empty($generated_currency)) { ?>

            const qrPayload =
                <?php
                echo json_encode(
                    $generated_currency['qr_payload']
                );
                ?>;


            const qrElement =
                document.getElementById("qrcode");


            /*
            |--------------------------------------------------------------------------
            | CLEAR PREVIOUS QR
            |--------------------------------------------------------------------------
            */

            if (qrElement) {

                qrElement.innerHTML = "";

            }


            /*
            |--------------------------------------------------------------------------
            | GENERATE QR
            |--------------------------------------------------------------------------
            */

            if (qrElement) {

                new QRCode(qrElement, {

                    text: qrPayload,

                    width: 240,

                    height: 240,

                    correctLevel: QRCode.CorrectLevel.H

                });

            }


        <?php } ?>
    </script>


</body>

</html>