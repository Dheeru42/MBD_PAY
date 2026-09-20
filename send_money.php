<?php
session_start();

require 'conn.php';

// LOGIN CHECK

if (!isset($_SESSION['user'])) {

    header("location:login.php");
    exit;
}
if ($serverConnected) {
    if (!isset($_SESSION['last_update'])) {

        header("location:synchronize_login.php");
        exit;
    }
}

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

$u_account = $_SESSION['account'];
$u_mob = $_SESSION['mobile'];

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}



if (isset($_SESSION['user'])) {

    $username = $_SESSION['user'];
}

if (!isset($_SESSION['mobile'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


/* =========================================================
   VARIABLES
========================================================= */

$message = '';

$message1 = '';

$messageType = '';

$recipient = null;

$amount = '';

$senderBalance = 0;

// cache directory

define("CACHE_DIR", __DIR__ . "/cache/users/");

// secrete key

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


// GET SENDER BALANCE FROM DATABASE

try {
    $stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE wallet_id = ?
    AND account_no = ?
    AND mobile = ?
    LIMIT 1
");

    $stmt->bind_param("sss", $u_wallet_id, $u_account, $u_mob);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $senderBalance = decryptData($row['balance']);
        $wallet_status = $row['Wallet Status'];
    }

    $stmt->close();
} catch (\Throwable $th) {
    $message1 = "Unable To Fetch Balance.";
    $messageType = "error";
}


// FIND RECIPIENT

try {

    if (isset($_POST['find_user'])) {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {

            $message = "Invalid security token. Please refresh the page.";
            $messageType = "error";
        } else {

            $mobile = trim($_POST['mobile'] ?? '');

            $_SESSION['recipient_mob'] = $mobile;

            //  mobile validation.

            if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {

                $message = "Please enter a valid 10-digit mobile number.";
                $messageType = "error";
            } else {

                $stmt = $conn->prepare("
                SELECT *
                FROM users
                WHERE mobile = ?
                LIMIT 1
            ");

                $stmt->bind_param("s", $mobile);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {

                    if ($row['wallet_id'] === $u_wallet_id) {

                        $message = "You cannot send money to your own account.";
                        $messageType = "error";
                    } else {

                        $recipient = $row;
                    }
                } else {

                    $message = "No MBD PAY user was found with this mobile number.";
                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
} catch (\Throwable $th) {
    $message = "Unable To Find MBD PAY user.";
    $messageType = "error";
}

// SEND MONEY

try {

    if (isset($_POST['send_money'])) {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {

            $message = "Invalid security token. Please refresh the page.";
            $messageType = "error";
        } else {

            $receiverId = $_POST['receiver_id'];
            $receiverMob = $_SESSION['recipient_mob'];
            $amount = trim($_POST['amount'] ?? '');
            $e_amount = encryptData($amount);

            // Convert amount safely.

            if (!is_numeric($amount)) {

                $message = "Please enter a valid amount.";
                $messageType = "error";
            } else {

                //  Amount must be positive and limited to 2 decimals.

                if ($amount <= 0) {

                    $message = "Amount must be greater than ₹0.";
                    $messageType = "error";
                } elseif ($amount > 100000) {

                    $message = "Maximum transfer amount is ₹1,00,000.";
                    $messageType = "error";
                } elseif (round($amount, 2) != $amount) {

                    $message = "Amount can contain a maximum of 2 decimal places.";
                    $messageType = "error";
                } elseif ($receiverId <= 0) {

                    $message = "Invalid recipient.";
                    $messageType = "error";
                } elseif ($receiverId === $u_wallet_id) {

                    $message = "You cannot send money to yourself.";
                    $messageType = "error";
                } else {

                    // START DATABASE TRANSACTION


                    $conn->begin_transaction();

                    try {

                        // LOCK SENDER WALLET


                        $stmt = $conn->prepare("
                        SELECT balance
                        FROM users
                        WHERE wallet_id = ?
                        AND account_no = ?
                        AND mobile = ?
                        FOR UPDATE
                    ");

                        $stmt->bind_param("sss", $u_wallet_id, $u_account, $u_mob);
                        $stmt->execute();

                        $result = $stmt->get_result();

                        $senderWallet = $result->fetch_assoc();

                        $stmt->close();


                        if (!$senderWallet) {
                            throw new Exception("Sender wallet was not found.");
                        }


                        $d_currentBalance = decryptData($senderWallet['balance']);

                        $e_currentBalance = encryptData($senderWallet['balance']);

                        // CHECK BALANCE

                        if ($d_currentBalance < $amount) {

                            throw new Exception(
                                "Insufficient wallet balance."
                            );
                        }

                        // LOCK RECEIVER WALLET

                        $stmt = $conn->prepare("
                        SELECT balance
                        FROM users
                        WHERE wallet_id = ?
                        AND mobile = ?
                        FOR UPDATE
                    ");

                        $stmt->bind_param("ss", $receiverId, $receiverMob);
                        $stmt->execute();

                        $result = $stmt->get_result();

                        $receiverWallet = $result->fetch_assoc();

                        $d_receiver_bal = decryptData($receiverWallet);

                        $e_receiver_bal = encryptData($receiverWallet);

                        $stmt->close();


                        if (!$receiverWallet) {
                            throw new Exception(
                                "Recipient wallet was not found."
                            );
                        }


                        // DEDUCT FROM SENDER

                        /* prepare balance of sender for update*/

                        $update_sender_bal = $d_currentBalance - $amount;

                        $e_update_sender_bal = encryptData($update_sender_bal);

                        $stmt = $conn->prepare("
                        UPDATE users
                        SET balance = ?
                        WHERE wallet_id = ?
                        AND mobile = ?
                        AND account_no = ?
                    ");

                        $stmt->bind_param(
                            "ssss",
                            $e_update_sender_bal,
                            $u_wallet_id,
                            $u_mob,
                            $u_account
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to debit sender wallet."
                            );
                        }

                        $stmt->close();

                        // ADD TO RECEIVER

                        /* prepare reciever balance for update*/
                        $update_reciever_bal = $d_receiver_bal + $amount;

                        $e_update_reciever_bal = encryptData($update_reciever_bal);

                        $stmt = $conn->prepare("
                        UPDATE users
                        SET balance = ?
                        WHERE wallet_id = ?
                        AND mobile = ?
                    ");

                        $stmt->bind_param(
                            "sss",
                            $e_update_reciever_bal,
                            $receiverId,
                            $receiverMob
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Unable to credit recipient wallet."
                            );
                        }

                        $stmt->close();

                        // CREATE TRANSACTION RECORD FOR RECIEVER

                        /* Generate transaction ID */

                        function generateTransactionId()
                        {
                            return "MBD" . date("ymdHis") . strtoupper(bin2hex(random_bytes(4)));
                        }

                        $transaction_id1 = generateTransactionId();

                        $transaction_id2 = generateTransactionId();

                        $transactionType = "Credit";
                        $status = 'Success';
                        $description = "Money Trasfer By " . $u_mob . "/" . $u_wallet_id;

                        $stmt = $conn->prepare("
                        INSERT INTO transactions
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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                        $stmt->bind_param(
                            "ssssssss",
                            $transaction_id1,
                            $receiverMob,
                            $transactionType,
                            $e_amount,
                            $e_receiver_bal,
                            $e_update_reciever_bal,
                            $description,
                            $status
                        );

                        if (!$stmt->execute()) {
                            throw new Exception(
                                "Transaction record could not be created."
                            );
                        }

                        $stmt->close();


                        // COMMIT
                        $conn->commit();

                        // CREATE TRANSACTION RECORD FOR SENDER

                        $transaction_id2 = generateTransactionId();

                        $transactionType2 = "Debit";
                        $status2 = 'Success';
                        $description2 = "Money Trasfer To " . $receiverMob . "/" . $receiverId;

                        $stmt2 = $conn->prepare("
                        INSERT INTO transactions
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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                        $stmt2->bind_param(
                            "ssssssss",
                            $transaction_id2,
                            $u_mob,
                            $transactionType2,
                            $e_amount,
                            $e_currentBalance,
                            $e_update_sender_bal,
                            $description2,
                            $status2
                        );

                        if (!$stmt2->execute()) {
                            throw new Exception(
                                "Transaction record could not be created."
                            );
                        }

                        $stmt2->close();


                        // COMMIT
                        $conn->commit();

                        /* update sender cache */

                        $userId = hash("sha256", $u_mob);

                        $file = "cache/users/$userId/profile.json";


                        if (file_exists($file)) {

                            $data = json_decode(
                                file_get_contents($file),
                                true
                            );

                            $U_balance = $e_update_sender_bal;

                            $data['balance'] = $U_balance;

                            $data['server_sync'] = true;

                            $data['update_at'] = date("Y-m-d h:i:s A");

                            $data['last_transaction'] = $transaction_id2;


                            file_put_contents(
                                $file,
                                json_encode(
                                    $data,
                                    JSON_PRETTY_PRINT
                                )
                            );
                        }

                        // Update displayed balance.

                        $senderBalance = $d_currentBalance - $amount;

                        //  Success message.

                        $message =
                            "₹" .
                            number_format($amount, 2) .
                            " sent successfully.";

                        $messageType = "success";


                        //  Clear recipient after successful transfer.

                        $recipient = null;
                    } catch (Throwable $e) {


                        // ROLLBACK

                        $conn->rollback();

                        $message = $e->getMessage();
                        $messageType = "error";
                    }
                }
            }
        }
    }
} catch (\Throwable $th) {
    //
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>MBD PAY | Send Money</title>

    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20'
fill='%23059669'/%3E%3Ctext x='50' y='72'
text-anchor='middle' font-size='70'
font-family='Arial'
font-weight='bold'
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding-bottom: 70px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                radial-gradient(circle at top left,
                    #bbf7d0,
                    #ecfdf5 45%,
                    #d1fae5);

            color: #022c22;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .send-page {

            width: 100%;

            max-width: 1050px;

            margin: 30px auto 90px;

            padding: 20px;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .page-header {

            text-align: center;

            margin-bottom: 30px;
        }


        .page-header .icon {

            width: 70px;

            height: 70px;

            margin: 0 auto 15px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 22px;

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            color: white;

            font-size: 35px;

            box-shadow:
                0 12px 30px rgba(245, 158, 11, .3);
        }


        .page-header h1 {

            margin: 0;

            font-size: 32px;

            color: #022c22;
        }


        .page-header p {

            margin-top: 8px;

            color: #64748b;

            font-size: 15px;
        }


        /* =====================================================
           MAIN GRID
        ===================================================== */

        .send-grid {

            display: grid;

            grid-template-columns:
                1fr 1.25fr;

            gap: 25px;

            align-items: start;
        }


        /* =====================================================
           BALANCE CARD
        ===================================================== */

        .balance-card {
            position: relative;
            min-height: 285px;
            padding: 28px;
            overflow: hidden;
            border-radius: 28px;
            color: white;
            background: linear-gradient(135deg, #022c22 0%, #064e3b 45%, #059669 100%);
            box-shadow: 0 25px 60px rgba(2, 44, 34, .28);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            isolation: isolate;
            transition: transform .35s ease, box-shadow .35s ease;
        }

        /* Hover Effect */
        .balance-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 30px 70px rgba(2, 44, 34, .35);
        }

        /* ===================================================== DECORATIVE GLOW ===================================================== */
        .balance-glow {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            filter: blur(2px);
        }

        .glow-one {
            width: 230px;
            height: 230px;
            right: -90px;
            top: -100px;
            background: rgba(255, 255, 255, .10);
        }

        .glow-two {
            width: 180px;
            height: 180px;
            left: -100px;
            bottom: -100px;
            background: rgba(16, 185, 129, .20);
        }

        /* ===================================================== HEADER ===================================================== */
        .balance-header {
            display: flex;
            align-items: center;
            gap: 13px;
            position: relative;
            z-index: 2;
        }

        .wallet-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .18);
            backdrop-filter: blur(10px);
            font-size: 23px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .2);
        }

        .balance-label {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
        }

        .balance-label span {
            font-size: 10px;
            letter-spacing: 1.8px;
            opacity: .65;
            font-weight: 700;
        }

        .balance-label strong {
            font-size: 15px;
            font-weight: 700;
        }

        /* ===================================================== STATUS ===================================================== */
        <?php if ($wallet_status == 'Active') { ?>.balance-status {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .12);
            font-size: 11px;
            font-weight: 600;
        }

        .balance-status span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 10px #4ade80;
            animation: walletPulse 1.8s infinite;
        }

        <?php } else { ?>.balance-status {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .12);
            font-size: 11px;
            font-weight: 600;
        }

        .balance-status span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #b60505;
            box-shadow: 0 0 10px #b31414;
            animation: walletPulse 1.8s infinite;
        }

        <?php } ?>@keyframes walletPulse {
            50% {
                opacity: .35;
                transform: scale(.75);
            }
        }

        /* ===================================================== BALANCE CONTENT ===================================================== */
        .balance-content {
            position: relative;
            z-index: 2;
            margin-top: 20px;
        }

        .currency-label {
            font-size: 10px;
            letter-spacing: 2px;
            font-weight: 700;
            opacity: .55;
            margin-bottom: 7px;
            margin-left: 80px;
        }

        .balance-amount {
            font-size: 39px;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -.8px;
            text-shadow: 0 4px 15px rgba(0, 0, 0, .15);
        }

        .balance-amount small {
            font-size: 23px;
            vertical-align: 6px;
            margin-right: 3px;
            opacity: .8;
        }

        .balance-line {
            width: 100%;
            height: 1px;
            margin-top: 18px;
            background: linear-gradient(90deg, rgba(255, 255, 255, .35), rgba(255, 255, 255, 0));
        }

        /* ===================================================== FOOTER ===================================================== */
        .balance-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
            margin-top: 20px;
        }

        .secure {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .secure-icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: rgba(255, 255, 255, .10);
            font-size: 15px;
        }

        .secure strong {
            display: block;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .secure span {
            display: block;
            font-size: 10px;
            opacity: .55;
        }

        /* ===================================================== SEND ARROW ===================================================== */
        .send-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .15);
            font-size: 20px;
            transition: .3s;
        }

        .balance-card:hover .send-icon {
            transform: translateX(5px);
            background: rgba(255, 255, 255, .20);
        }

        /* ===================================================== ALERT INSIDE CARD ===================================================== */
        .balance-card .alert {
            position: relative;
            z-index: 10;
            padding: 11px 13px;
            margin-bottom: 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .balance-card .alert-success {
            color: #dcfce7;
            background: rgba(22, 101, 52, .35);
            border: 1px solid rgba(134, 239, 172, .25);
        }

        .balance-card .alert-error {
            color: #fee2e2;
            background: rgba(153, 27, 27, .35);
            border: 1px solid rgba(252, 165, 165, .25);
        }

        /* ===================================================== MOBILE ===================================================== */
        @media(max-width:800px) {
            .balance-card {
                min-height: 260px;
                padding: 24px;
                border-radius: 24px;
            }

            .balance-amount {
                font-size: 34px;
            }

            .balance-status {
                display: none;
            }

            .wallet-icon {
                width: 44px;
                height: 44px;
            }
        }

        .balance-card::before {

            content: "";

            position: absolute;

            width: 180px;

            height: 180px;

            right: -70px;

            top: -70px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, .08);
        }


        .balance-card .small-title {

            font-size: 14px;

            opacity: .8;

            margin-bottom: 12px;

            margin-left: 100px;
        }


        .balance-amount {

            font-size: 38px;

            font-weight: 800;

            margin-bottom: 25px;

            margin-left: 100px;
        }


        .balance-card .secure {

            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 13px;

            opacity: .9;

            margin-left: 100px;
        }


        /* =====================================================
           FORM CARD
        ===================================================== */

        .form-card {

            background: rgba(255, 255, 255, .95);

            border-radius: 25px;

            padding: 30px;

            box-shadow:
                0 15px 45px rgba(15, 23, 42, .12);

            border:
                1px solid rgba(255, 255, 255, .8);
        }


        .form-title {

            margin: 0 0 8px;

            font-size: 23px;

            color: #022c22;
        }


        .form-subtitle {

            margin: 0 0 25px;

            color: #64748b;

            font-size: 14px;
        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            padding: 14px 16px;

            border-radius: 14px;

            margin-bottom: 20px;

            font-size: 14px;

            font-weight: 600;
        }


        .alert-success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #86efac;
        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fca5a5;
        }


        /* =====================================================
           FORM GROUP
        ===================================================== */

        .form-group {

            margin-bottom: 20px;
        }


        .form-group label {

            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: 700;

            color: #334155;
        }


        .input-wrap {

            position: relative;
        }


        .input-icon {

            position: absolute;

            left: 15px;

            top: 50%;

            transform:
                translateY(-50%);

            font-size: 18px;

            z-index: 2;
        }


        .form-control {

            width: 100%;

            height: 54px;

            padding:
                0 16px 0 46px;

            border:
                1px solid #d1d5db;

            border-radius: 14px;

            background: #f8fafc;

            font-size: 16px;

            outline: none;

            transition: .25s;
        }


        .form-control:focus {

            background: white;

            border-color: #059669;

            box-shadow:
                0 0 0 4px rgba(5, 150, 105, .10);
        }


        .amount-input {

            font-size: 25px;

            font-weight: 700;
        }


        /* =====================================================
           RECIPIENT CARD
        ===================================================== */

        .recipient-card {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding: 18px;

            margin-bottom: 20px;

            background:
                linear-gradient(135deg,
                    #ecfdf5,
                    #f0fdf4);

            border:
                1px solid #bbf7d0;

            border-radius: 18px;
        }


        .recipient-left {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .avatar {

            width: 48px;

            height: 48px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(135deg,
                    #059669,
                    #047857);

            color: white;

            font-size: 20px;

            font-weight: bold;
        }


        .recipient-name {

            font-weight: 800;

            color: #022c22;
        }


        .recipient-mobile {

            color: #64748b;

            font-size: 13px;

            margin-top: 3px;
        }


        .verified {

            padding: 6px 10px;

            border-radius: 20px;

            background: #dcfce7;

            color: #15803d;

            font-size: 12px;

            font-weight: bold;
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .btn {

            width: 100%;

            height: 55px;

            border: 0;

            border-radius: 15px;

            cursor: pointer;

            font-size: 16px;

            font-weight: 800;

            color: white;

            background:
                linear-gradient(135deg,
                    #059669,
                    #047857);

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .25);

            transition: .25s;
        }


        .btn:hover {

            transform: translateY(-2px);

            box-shadow:
                0 15px 30px rgba(5, 150, 105, .3);
        }


        .btn:active {

            transform: translateY(0);
        }


        .btn-find {

            margin-top: 5px;
        }


        /* =====================================================
           SECURITY NOTE
        ===================================================== */

        .security-note {

            margin-top: 20px;

            padding: 14px;

            border-radius: 13px;

            background: #f8fafc;

            color: #64748b;

            font-size: 12px;

            line-height: 1.5;

            text-align: center;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media(max-width: 800px) {

            .send-page {

                margin-top: 15px;

                padding: 15px;
            }


            .send-grid {

                grid-template-columns: 1fr;
            }


            .balance-card {

                padding: 24px;
            }


            .form-card {

                padding: 22px;
            }


            .page-header h1 {

                font-size: 26px;
            }


            .balance-amount {

                font-size: 32px;
            }
        }
    </style>

</head>


<body>

    <?php require 'navbar.php'; ?>

    <div class="send-page">


        <!-- =====================================================
         HEADER
    ====================================================== -->

        <div class="page-header">

            <div class="icon">
                ₹
            </div>

            <h1>Send Money</h1>

            <p>
                Transfer money instantly to another MBD PAY user
                using their mobile number.
            </p>

        </div>



        <!-- =====================================================
         GRID
    ====================================================== -->

        <div class="send-grid">


            <!-- =================================================
             BALANCE
        ================================================== -->

            <div class="balance-card"> <!-- Decorative background -->
                <div class="balance-glow glow-one"></div>
                <div class="balance-glow glow-two"></div>
                <?php if ($message1 !== ''): ?> <div class=" alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?> "> <?php echo htmlspecialchars($message1); ?> </div> <?php endif; ?> <!-- Card Header -->
                <div class="balance-header">
                    <div class="wallet-icon"> 💳 </div>
                    <div class="balance-label"> <span>WALLET ID </span> <strong><?php echo $u_wallet_id; ?></strong> </div>
                    <div class="balance-status">
                        <span></span><?php if ($wallet_status == 'Active') {
                                            echo 'Active';
                                        } else {
                                            echo 'Inactive';
                                        }
                                        ?>
                    </div>
                </div> <!-- Balance -->
                <div class="balance-content">
                    <div class="currency-label"> TOTAL AVAILABLE BALANCE </div>
                    <div class="balance-amount"> <small>₹</small><?php echo number_format($senderBalance, 2); ?> </div>
                    <div class="balance-line"></div>
                </div> <!-- Bottom Information -->
                <div class="balance-footer">
                    <div class="secure">
                        <div class="secure-icon"> 🔒 </div>
                        <div> <strong>Secure Wallet</strong> <span>Your money is protected</span> </div>
                    </div>
                    <div class="send-icon"> → </div>
                </div>
            </div>



            <!-- =================================================
             FORM
        ================================================== -->

            <div class="form-card">

                <h2 class="form-title">
                    Send Money
                </h2>

                <p class="form-subtitle">
                    Enter the recipient's registered mobile number.
                </p>


                <?php if ($message !== ''): ?>

                    <div class="
                    alert
                    <?php
                    echo $messageType === 'success'
                        ? 'alert-success'
                        : 'alert-error';
                    ?>
                ">

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>



                <?php if (!$recipient): ?>


                    <!-- =========================================
                     FIND USER
                ========================================== -->

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($csrfToken); ?>">


                        <div class="form-group">

                            <label>
                                Recipient Mobile Number
                            </label>

                            <div class="input-wrap">

                                <span class="input-icon">
                                    📱
                                </span>

                                <input
                                    type="tel"
                                    name="mobile"
                                    class="form-control"
                                    placeholder="Enter 10-digit mobile number"
                                    maxlength="10"
                                    pattern="[6-9][0-9]{9}"
                                    inputmode="numeric"
                                    required>

                            </div>

                        </div>


                        <button
                            type="submit"
                            name="find_user"
                            class="btn btn-find">

                            🔍 Find MBD PAY User

                        </button>

                    </form>


                <?php else: ?>


                    <!-- =========================================
                     RECIPIENT FOUND
                ========================================== -->

                    <div class="recipient-card">

                        <div class="recipient-left">

                            <div class="avatar">

                                <?php
                                echo strtoupper(
                                    substr(
                                        $recipient['name'],
                                        0,
                                        1
                                    )
                                );
                                ?>

                            </div>


                            <div>

                                <div class="recipient-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $recipient['name']
                                    );
                                    ?>

                                </div>


                                <div class="recipient-mobile">

                                    📱
                                    <?php
                                    echo htmlspecialchars(
                                        $recipient['mobile']
                                    );
                                    ?>

                                </div>

                            </div>

                        </div>


                        <div class="verified">
                            ✓ Verified
                        </div>

                    </div>



                    <!-- =========================================
                     SEND FORM
                ========================================== -->

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($csrfToken); ?>">


                        <input
                            type="hidden"
                            name="receiver_id"
                            value="<?php echo $recipient['wallet_id']; ?>">


                        <div class="form-group">

                            <label>
                                Amount
                            </label>

                            <div class="input-wrap">

                                <span class="input-icon">
                                    ₹
                                </span>

                                <input
                                    type="number"
                                    name="amount"
                                    class="form-control amount-input"
                                    placeholder="0.00"
                                    min="1"
                                    max="100000"
                                    step="0.01"
                                    inputmode="decimal"
                                    required>

                            </div>

                        </div>


                        <button
                            type="submit"
                            name="send_money"
                            class="btn"
                            onclick="
                            return confirm(
                                'Are you sure you want to send this money?'
                            );
                        ">

                            💸 Send Money

                        </button>

                    </form>


                    <div class="security-note">

                        🔐 Your transfer is processed securely.
                        Please verify the recipient and amount
                        before confirming the payment.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <?php require 'footer.php'; ?>


</body>

</html>