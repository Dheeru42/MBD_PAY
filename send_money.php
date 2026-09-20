<?php
session_start();

require_once 'conn.php';

/* =========================================================
   LOGIN CHECK
========================================================= */

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

if (!isset($_SESSION['mobile'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}


/* =========================================================
   USER SESSION DATA
========================================================= */

$u_wallet_id = $_SESSION['wallet_id'];
$u_account   = $_SESSION['account'];
$u_mob       = $_SESSION['mobile'];

$username = $_SESSION['user'];


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
$wallet_status = 'Inactive';


/* =========================================================
   CACHE DIRECTORY
========================================================= */

define("CACHE_DIR", __DIR__ . "/cache/users/");


/* =========================================================
   SECRET KEY
========================================================= */

define(
    "SECRET_KEY",
    "MBDPAY@2026_SUPER_SECRET_KEY_32"
);


/* =========================================================
   ENCRYPT FUNCTION
========================================================= */

function encryptData($text)
{
    $key = hash("sha256", SECRET_KEY, true);

    $iv = random_bytes(16);

    $cipher = openssl_encrypt(
        (string)$text,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipher === false) {
        throw new Exception("Unable to encrypt data.");
    }

    return base64_encode($iv . $cipher);
}


/* =========================================================
   DECRYPT FUNCTION
========================================================= */

function decryptData($text)
{
    if (empty($text)) {
        return 0.0;
    }

    $key = hash("sha256", SECRET_KEY, true);

    $data = base64_decode($text, true);

    if ($data === false || strlen($data) < 17) {
        return 0.0;
    }

    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);

    $decrypted = openssl_decrypt(
        $cipher,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($decrypted === false || !is_numeric($decrypted)) {
        return 0.0;
    }

    return (float)$decrypted;
}

/* synchronize if balance mismatch */

if ($serverConnected) {
    try {

        // server balance

        $stmt = mysqli_prepare($conn, "SELECT name,balance FROM users WHERE mobile=? AND account_no=?");
        mysqli_stmt_bind_param($stmt, "ss", $u_mob, $u_account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        $server_bal = decryptData($user['balance']);

        // user cache path

        $userId = hash("sha256", $u_mob);

        $profile = CACHE_DIR . $userId . "/profile.json";

        $cache = json_decode(
            file_get_contents($profile),
            true
        );

        $cache_bal = decryptData($cache['balance']);

        // start
        if ($server_bal != $cache_bal) {
            header("location:synchronize_login.php");
        }
    } catch (\Throwable $th) {
        header("location:synchronize_login.php");
    }
}

/* =========================================================
   TRANSACTION ID
========================================================= */

function generateTransactionId()
{
    return "MBD"
        . date("ymdHis")
        . strtoupper(bin2hex(random_bytes(4)));
}


/* =========================================================
   GET SENDER BALANCE
========================================================= */

try {

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE wallet_id = ?
        AND account_no = ?
        AND mobile = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "sss",
        $u_wallet_id,
        $u_account,
        $u_mob
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        /*
         * IMPORTANT:
         * Database balance is encrypted.
         * Decrypt it before using number_format().
         */

        $senderBalance = decryptData(
            $row['balance']
        );

        $senderBalance = (float)$senderBalance;

        $wallet_status = $row['Wallet Status'];
    }

    $stmt->close();
} catch (Throwable $th) {

    $senderBalance = 0;

    $message1 = "Unable To Fetch Balance.";
    $messageType = "error";
}


/* =========================================================
   FIND RECIPIENT
========================================================= */

try {

    if (isset($_POST['find_user'])) {

        /* CSRF CHECK */

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        ) {

            $message = "Invalid security token. Please refresh the page.";
            $messageType = "error";
        } else {

            $mobile = trim(
                $_POST['mobile'] ?? ''
            );

            $_SESSION['recipient_mob'] = $mobile;


            /* MOBILE VALIDATION */

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

                $stmt->bind_param(
                    "s",
                    $mobile
                );

                $stmt->execute();

                $result = $stmt->get_result();


                if ($row = $result->fetch_assoc()) {

                    /* PREVENT SELF TRANSFER */

                    if (
                        (string)$row['wallet_id']
                        ===
                        (string)$u_wallet_id
                    ) {

                        $message =
                            "You cannot send money to your own account.";

                        $messageType = "error";
                    } else {

                        $recipient = $row;
                    }
                } else {

                    $message =
                        "No MBD PAY user was found with this mobile number.";

                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
} catch (Throwable $th) {

    $message = "Unable To Find MBD PAY user.";
    $messageType = "error";
}


/* =========================================================
   SEND MONEY
   PIN IS REQUIRED HERE
========================================================= */

try {

    if (isset($_POST['send_money'])) {

        /* =====================================================
           CSRF CHECK
        ===================================================== */

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        ) {

            $message =
                "Invalid security token. Please refresh the page.";

            $messageType = "error";
        } else {

            /* =================================================
               GET FORM DATA
            ================================================= */

            $receiverId = trim(
                $_POST['receiver_id'] ?? ''
            );

            $receiverMob =
                $_SESSION['recipient_mob'] ?? '';

            $amountInput =
                trim($_POST['amount'] ?? '');

            $transactionPin =
                trim($_POST['transaction_pin'] ?? '');


            /* =================================================
               PIN VALIDATION
            ================================================= */

            if (
                !preg_match(
                    '/^[0-9]{4}$/',
                    $transactionPin
                )
            ) {

                $message =
                    "Please enter a valid 4-digit PIN.";

                $messageType = "error";
            } else {

                /* =============================================
                   VERIFY PIN FROM DATABASE
                ============================================= */

                $stmt = $conn->prepare("
                    SELECT pin
                    FROM users
                    WHERE wallet_id = ?
                    AND account_no = ?
                    AND mobile = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "sss",
                    $u_wallet_id,
                    $u_account,
                    $u_mob
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $userRow = $result->fetch_assoc();

                $stmt->close();


                /* =============================================
                   CHECK PASSWORD HASH
                ============================================= */

                if (
                    !$userRow ||
                    empty($userRow['pin']) ||
                    !password_verify(
                        $transactionPin,
                        $userRow['pin']
                    )
                ) {

                    $message =
                        "Incorrect PIN. Payment was not processed.";

                    $messageType = "error";
                } else {

                    /* =========================================
                       PIN CORRECT
                       NOW PROCESS PAYMENT
                    ========================================= */

                    /* =========================================
                       AMOUNT VALIDATION
                    ========================================= */

                    if (
                        $amountInput === '' ||
                        !is_numeric($amountInput)
                    ) {

                        $message =
                            "Please enter a valid amount.";

                        $messageType = "error";
                    } else {

                        $amount = (float)$amountInput;


                        if ($amount <= 0) {

                            $message =
                                "Amount must be greater than ₹0.";

                            $messageType = "error";
                        } elseif ($amount > 100000) {

                            $message =
                                "Maximum transfer amount is ₹1,00,000.";

                            $messageType = "error";
                        } elseif (
                            round($amount, 2) != $amount
                        ) {

                            $message =
                                "Amount can contain a maximum of 2 decimal places.";

                            $messageType = "error";
                        } elseif ($receiverId === '') {

                            $message =
                                "Invalid recipient.";

                            $messageType = "error";
                        } elseif (
                            (string)$receiverId
                            ===
                            (string)$u_wallet_id
                        ) {

                            $message =
                                "You cannot send money to yourself.";

                            $messageType = "error";
                        } elseif ($receiverMob === '') {

                            $message =
                                "Recipient information is missing.";

                            $messageType = "error";
                        } else {

                            /* =================================
                               ENCRYPT AMOUNT
                            ================================= */

                            $e_amount =
                                encryptData(
                                    (string)$amount
                                );


                            /* =================================
                               START DATABASE TRANSACTION
                            ================================= */

                            $conn->begin_transaction();

                            try {

                                /* =================================
                                   LOCK SENDER WALLET
                                ================================= */

                                $stmt = $conn->prepare("
                                    SELECT balance
                                    FROM users
                                    WHERE wallet_id = ?
                                    AND account_no = ?
                                    AND mobile = ?
                                    FOR UPDATE
                                ");

                                $stmt->bind_param(
                                    "sss",
                                    $u_wallet_id,
                                    $u_account,
                                    $u_mob
                                );

                                $stmt->execute();

                                $result =
                                    $stmt->get_result();

                                $senderWallet =
                                    $result->fetch_assoc();

                                $stmt->close();


                                if (!$senderWallet) {

                                    throw new Exception(
                                        "Sender wallet was not found."
                                    );
                                }


                                /* =================================
                                   DECRYPT SENDER BALANCE
                                ================================= */

                                $d_currentBalance =
                                    decryptData(
                                        $senderWallet['balance']
                                    );

                                $d_currentBalance =
                                    (float)$d_currentBalance;


                                /* =================================
                                   CHECK BALANCE
                                ================================= */

                                if (
                                    $d_currentBalance
                                    <
                                    $amount
                                ) {

                                    throw new Exception(
                                        "Insufficient wallet balance."
                                    );
                                }


                                /* =================================
                                   LOCK RECEIVER WALLET
                                ================================= */

                                $stmt = $conn->prepare("
                                    SELECT balance
                                    FROM users
                                    WHERE wallet_id = ?
                                    AND mobile = ?
                                    FOR UPDATE
                                ");

                                $stmt->bind_param(
                                    "ss",
                                    $receiverId,
                                    $receiverMob
                                );

                                $stmt->execute();

                                $result =
                                    $stmt->get_result();

                                $receiverWallet =
                                    $result->fetch_assoc();

                                $stmt->close();


                                if (!$receiverWallet) {

                                    throw new Exception(
                                        "Recipient wallet was not found."
                                    );
                                }


                                /* =================================
                                   DECRYPT RECEIVER BALANCE
                                ================================= */

                                $d_receiver_bal =
                                    decryptData(
                                        $receiverWallet['balance']
                                    );

                                $d_receiver_bal =
                                    (float)$d_receiver_bal;


                                /* =================================
                                   SAVE BALANCE BEFORE
                                ================================= */

                                $e_currentBalance =
                                    encryptData(
                                        (string)$d_currentBalance
                                    );

                                $e_receiver_bal =
                                    encryptData(
                                        (string)$d_receiver_bal
                                    );


                                /* =================================
                                   CALCULATE NEW BALANCES
                                ================================= */

                                $update_sender_bal =
                                    round(
                                        $d_currentBalance - $amount,
                                        2
                                    );

                                $update_receiver_bal =
                                    round(
                                        $d_receiver_bal + $amount,
                                        2
                                    );


                                /* =================================
                                   ENCRYPT NEW BALANCES
                                ================================= */

                                $e_update_sender_bal =
                                    encryptData(
                                        (string)$update_sender_bal
                                    );

                                $e_update_receiver_bal =
                                    encryptData(
                                        (string)$update_receiver_bal
                                    );


                                /* =================================
                                   UPDATE SENDER
                                ================================= */

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


                                /* =================================
                                   UPDATE RECEIVER
                                ================================= */

                                $stmt = $conn->prepare("
                                    UPDATE users
                                    SET balance = ?
                                    WHERE wallet_id = ?
                                    AND mobile = ?
                                ");

                                $stmt->bind_param(
                                    "sss",
                                    $e_update_receiver_bal,
                                    $receiverId,
                                    $receiverMob
                                );

                                if (!$stmt->execute()) {

                                    throw new Exception(
                                        "Unable to credit recipient wallet."
                                    );
                                }

                                $stmt->close();


                                /* =================================
                                   TRANSACTION IDs
                                ================================= */

                                $transaction_id_receiver =
                                    generateTransactionId();

                                $transaction_id_sender =
                                    generateTransactionId();


                                /* =================================
                                   RECEIVER TRANSACTION
                                ================================= */

                                $transactionTypeReceiver =
                                    "Credit";

                                $statusReceiver =
                                    "Success";

                                $descriptionReceiver =
                                    "Money Transfer By "
                                    . $u_mob
                                    . "/"
                                    . $u_wallet_id;


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
                                    $transaction_id_receiver,
                                    $receiverMob,
                                    $transactionTypeReceiver,
                                    $e_amount,
                                    $e_receiver_bal,
                                    $e_update_receiver_bal,
                                    $descriptionReceiver,
                                    $statusReceiver
                                );

                                if (!$stmt->execute()) {

                                    throw new Exception(
                                        "Receiver transaction record could not be created."
                                    );
                                }

                                $stmt->close();


                                /* =================================
                                   SENDER TRANSACTION
                                ================================= */

                                $transactionTypeSender =
                                    "Debit";

                                $statusSender =
                                    "Success";

                                $descriptionSender =
                                    "Money Transfer To "
                                    . $receiverMob
                                    . "/"
                                    . $receiverId;


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
                                    $transaction_id_sender,
                                    $u_mob,
                                    $transactionTypeSender,
                                    $e_amount,
                                    $e_currentBalance,
                                    $e_update_sender_bal,
                                    $descriptionSender,
                                    $statusSender
                                );

                                if (!$stmt->execute()) {

                                    throw new Exception(
                                        "Sender transaction record could not be created."
                                    );
                                }

                                $stmt->close();


                                /* =================================
                                   COMMIT EVERYTHING
                                ================================= */

                                $conn->commit();


                                /* =================================
                                   UPDATE LOCAL CACHE
                                ================================= */

                                $userId =
                                    hash(
                                        "sha256",
                                        $u_mob
                                    );

                                $file =
                                    __DIR__
                                    . "/cache/users/"
                                    . $userId
                                    . "/profile.json";


                                if (file_exists($file)) {

                                    $data =
                                        json_decode(
                                            file_get_contents($file),
                                            true
                                        );

                                    if (!is_array($data)) {
                                        $data = [];
                                    }

                                    /*
                                     * Store encrypted balance
                                     */

                                    $data['balance'] =
                                        $e_update_sender_bal;

                                    $data['server_sync'] =
                                        true;

                                    $data['update_at'] =
                                        date(
                                            "Y-m-d h:i:s A"
                                        );

                                    $data['last_transaction'] =
                                        $transaction_id_sender;


                                    file_put_contents(
                                        $file,
                                        json_encode(
                                            $data,
                                            JSON_PRETTY_PRINT
                                        )
                                    );
                                }


                                /* =================================
                                   UPDATE DISPLAYED BALANCE
                                ================================= */

                                $senderBalance =
                                    $update_sender_bal;


                                /* =================================
                                   SUCCESS MESSAGE
                                ================================= */

                                $message =
                                    "₹"
                                    . number_format(
                                        $amount,
                                        2
                                    )
                                    . " sent successfully.";

                                $messageType =
                                    "success";


                                /* =================================
                                   CLEAR RECIPIENT
                                ================================= */

                                $recipient = null;

                                unset(
                                    $_SESSION['recipient_mob']
                                );
                            } catch (Throwable $e) {

                                /*
                                 * ROLLBACK EVERYTHING
                                 */

                                $conn->rollback();

                                $message =
                                    $e->getMessage();

                                $messageType =
                                    "error";
                            }
                        }
                    }
                }
            }
        }
    }
} catch (Throwable $th) {

    $message =
        "Unable to process the payment.";

    $messageType =
        "error";
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


    <link
        rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">


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
           GRID
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

            background:
                linear-gradient(135deg,
                    #022c22 0%,
                    #064e3b 45%,
                    #059669 100%);

            box-shadow:
                0 25px 60px rgba(2, 44, 34, .28);

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            isolation: isolate;

            transition:
                transform .35s ease,
                box-shadow .35s ease;
        }


        .balance-card:hover {

            transform: translateY(-6px);

            box-shadow:
                0 30px 70px rgba(2, 44, 34, .35);
        }


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

            background:
                rgba(255, 255, 255, .10);
        }


        .glow-two {

            width: 180px;

            height: 180px;

            left: -100px;

            bottom: -100px;

            background:
                rgba(16, 185, 129, .20);
        }


        /* =====================================================
           BALANCE HEADER
        ===================================================== */

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

            background:
                rgba(255, 255, 255, .14);

            border:
                1px solid rgba(255, 255, 255, .18);

            backdrop-filter: blur(10px);

            font-size: 23px;

            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .2);
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


        /* =====================================================
           STATUS
        ===================================================== */

        .balance-status {

            display: flex;

            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            border-radius: 20px;

            background:
                rgba(255, 255, 255, .10);

            border:
                1px solid rgba(255, 255, 255, .12);

            font-size: 11px;

            font-weight: 600;
        }


        .balance-status span {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            <?php if ($wallet_status == 'Active') { ?>background: #4ade80;

            box-shadow:
                0 0 10px #4ade80;

            <?php } else { ?>background: #b60505;

            box-shadow:
                0 0 10px #b31414;

            <?php } ?>animation:
                walletPulse 1.8s infinite;
        }


        @keyframes walletPulse {

            50% {

                opacity: .35;

                transform: scale(.75);
            }
        }


        /* =====================================================
           BALANCE CONTENT
        ===================================================== */

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
        }


        .balance-amount {

            font-size: 39px;

            line-height: 1.1;

            font-weight: 800;

            letter-spacing: -.8px;

            text-shadow:
                0 4px 15px rgba(0, 0, 0, .15);
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

            background:
                linear-gradient(90deg,
                    rgba(255, 255, 255, .35),
                    rgba(255, 255, 255, 0));
        }


        /* =====================================================
           BALANCE FOOTER
        ===================================================== */

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

            background:
                rgba(255, 255, 255, .10);

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


        .send-icon {

            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, .12);

            border:
                1px solid rgba(255, 255, 255, .15);

            font-size: 20px;

            transition: .3s;
        }


        .balance-card:hover .send-icon {

            transform:
                translateX(5px);

            background:
                rgba(255, 255, 255, .20);
        }


        /* =====================================================
           FORM CARD
        ===================================================== */

        .form-card {

            background:
                rgba(255, 255, 255, .95);

            border-radius: 25px;

            padding: 30px;

            box-shadow:
                0 15px 45px rgba(15, 23, 42, .12);

            border:
                1px solid rgba(255, 255, 255, .8);
        }


        .form-title {

            margin:
                0 0 8px;

            font-size: 23px;

            color: #022c22;
        }


        .form-subtitle {

            margin:
                0 0 25px;

            color: #64748b;

            font-size: 14px;
        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            padding:
                14px 16px;

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

            padding:
                6px 10px;

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

            transform:
                translateY(-2px);

            box-shadow:
                0 15px 30px rgba(5, 150, 105, .3);
        }


        .btn:active {

            transform:
                translateY(0);
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
           PIN MODAL
        ===================================================== */

        .pin-modal {

            display: none;

            position: fixed;

            inset: 0;

            z-index: 99999;

            background:
                rgba(2, 44, 34, .60);

            backdrop-filter:
                blur(7px);

            align-items: center;

            justify-content: center;

            padding: 20px;
        }


        .pin-modal.show {

            display: flex;
        }


        .pin-box {

            width: 100%;

            max-width: 390px;

            background: white;

            border-radius: 25px;

            padding: 30px;

            box-shadow:
                0 25px 80px rgba(0, 0, 0, .30);

            text-align: center;

            animation:
                pinPopup .22s ease;
        }


        @keyframes pinPopup {

            from {

                opacity: 0;

                transform:
                    scale(.90) translateY(15px);
            }

            to {

                opacity: 1;

                transform:
                    scale(1) translateY(0);
            }
        }


        .pin-icon {

            width: 65px;

            height: 65px;

            margin:
                0 auto 15px;

            border-radius: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(135deg,
                    #059669,
                    #047857);

            color: white;

            font-size: 30px;

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .25);
        }


        .pin-box h2 {

            margin:
                0 0 7px;

            color: #022c22;

            font-size: 23px;
        }


        .pin-box p {

            margin:
                0 0 20px;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }


        .pin-input {

            width: 100%;

            height: 58px;

            border:
                2px solid #d1d5db;

            border-radius: 15px;

            text-align: center;

            font-size: 28px;

            font-weight: 800;

            letter-spacing: 12px;

            padding-left: 12px;

            outline: none;

            background: #f8fafc;
        }


        .pin-input:focus {

            border-color: #059669;

            background: white;

            box-shadow:
                0 0 0 4px rgba(5, 150, 105, .10);
        }


        .pin-error {

            min-height: 20px;

            margin-top: 10px;

            color: #dc2626;

            font-size: 13px;

            font-weight: 600;
        }


        .pin-buttons {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 10px;

            margin-top: 10px;
        }


        .pin-cancel {

            height: 50px;

            border: 0;

            border-radius: 13px;

            background: #e5e7eb;

            color: #374151;

            font-weight: 700;

            cursor: pointer;
        }


        .pin-confirm {

            height: 50px;

            border: 0;

            border-radius: 13px;

            background:
                linear-gradient(135deg,
                    #059669,
                    #047857);

            color: white;

            font-weight: 800;

            cursor: pointer;
        }


        .pin-confirm:disabled {

            opacity: .6;

            cursor: not-allowed;
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


            .balance-status {

                display: none;
            }
        }


        @media(max-width: 450px) {

            .pin-box {

                padding: 25px 20px;

                border-radius: 22px;
            }


            .pin-input {

                font-size: 25px;

                letter-spacing: 9px;
            }
        }
    </style>

</head>


<body>


    <?php require_once 'navbar.php'; ?>


    <div class="send-page">


        <!-- =====================================================
         HEADER
    ====================================================== -->

        <div class="page-header">

            <div class="icon">
                ₹
            </div>

            <h1>
                Send Money
            </h1>

            <p>
                Transfer money instantly to another MBD PAY user
                using their mobile number.
            </p>

        </div>


        <!-- =====================================================
         MAIN GRID
    ====================================================== -->

        <div class="send-grid">


            <!-- =================================================
             BALANCE CARD
        ================================================== -->

            <div class="balance-card">

                <div class="balance-glow glow-one"></div>

                <div class="balance-glow glow-two"></div>


                <?php if ($message1 !== ''): ?>

                    <div class="
                    alert
                    <?php
                    echo $messageType === 'success'
                        ? 'alert-success'
                        : 'alert-error';
                    ?>
                ">

                        <?php
                        echo htmlspecialchars(
                            $message1
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <!-- CARD HEADER -->

                <div class="balance-header">

                    <div class="wallet-icon">
                        💳
                    </div>


                    <div class="balance-label">

                        <span>
                            WALLET ID
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $u_wallet_id
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="balance-status">

                        <span></span>

                        <?php

                        if ($wallet_status == 'Active') {

                            echo 'Active';
                        } else {

                            echo 'Inactive';
                        }

                        ?>

                    </div>

                </div>


                <!-- BALANCE -->

                <div class="balance-content">

                    <div class="currency-label">

                        TOTAL AVAILABLE BALANCE

                    </div>


                    <div class="balance-amount">

                        <small>₹</small>

                        <?php

                        echo number_format(
                            (float)$senderBalance,
                            2
                        );

                        ?>

                    </div>


                    <div class="balance-line"></div>

                </div>


                <!-- FOOTER -->

                <div class="balance-footer">

                    <div class="secure">

                        <div class="secure-icon">
                            🔒
                        </div>

                        <div>

                            <strong>
                                Secure Wallet
                            </strong>

                            <span>
                                Your money is protected
                            </span>

                        </div>

                    </div>


                    <div class="send-icon">
                        →
                    </div>

                </div>

            </div>


            <!-- =================================================
             FORM CARD
        ================================================== -->

            <div class="form-card">


                <h2 class="form-title">
                    Send Money
                </h2>


                <p class="form-subtitle">

                    Enter the recipient's registered mobile number.

                </p>


                <!-- MESSAGE -->

                <?php if ($message !== ''): ?>

                    <div class="
                    alert
                    <?php
                    echo $messageType === 'success'
                        ? 'alert-success'
                        : 'alert-error';
                    ?>
                ">

                        <?php
                        echo htmlspecialchars(
                            $message
                        );
                        ?>

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
                            value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken
                                    );
                                    ?>">


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

                    <form
                        method="POST"
                        id="sendMoneyForm">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken
                                    );
                                    ?>">


                        <input
                            type="hidden"
                            name="receiver_id"
                            value="<?php
                                    echo htmlspecialchars(
                                        $recipient['wallet_id']
                                    );
                                    ?>">


                        <!-- AMOUNT -->

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
                                    id="amount"
                                    class="form-control amount-input"
                                    placeholder="0.00"
                                    min="1"
                                    max="100000"
                                    step="0.01"
                                    inputmode="decimal"
                                    required>

                            </div>

                        </div>


                        <!-- SEND BUTTON -->

                        <button
                            type="button"
                            class="btn"
                            onclick="openPinModal();">

                            💸 Send Money

                        </button>


                    </form>


                    <div class="security-note">

                        🔐 Your transfer is processed securely.
                        You must enter your 4-digit transaction PIN
                        before the payment is processed.

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </div>


    <?php require_once 'footer.php'; ?>


    <!-- =========================================================
     PIN MODAL
========================================================= -->

    <div
        class="pin-modal"
        id="pinModal"
        onclick="closePinFromOutside(event);">


        <div
            class="pin-box"
            onclick="event.stopPropagation();">


            <div class="pin-icon">
                🔐
            </div>


            <h2>
                Enter Transaction PIN
            </h2>


            <p>

                Enter your 4-digit PIN to confirm this payment.

            </p>


            <input
                type="password"
                id="pinInput"
                class="pin-input"
                maxlength="4"
                minlength="4"
                inputmode="numeric"
                pattern="[0-9]{4}"
                autocomplete="off"
                placeholder="••••">


            <div
                class="pin-error"
                id="pinError"></div>


            <div class="pin-buttons">


                <button
                    type="button"
                    class="pin-cancel"
                    onclick="closePinModal();">

                    Cancel

                </button>


                <button
                    type="button"
                    class="pin-confirm"
                    id="pinConfirmButton"
                    onclick="confirmPin();">

                    Confirm Payment

                </button>

            </div>

        </div>

    </div>


    <script>
        /* =========================================================
   OPEN PIN MODAL
========================================================= */

        function openPinModal() {
            const amountInput =
                document.getElementById("amount");

            const amount =
                amountInput.value.trim();

            const pinModal =
                document.getElementById("pinModal");

            const pinInput =
                document.getElementById("pinInput");

            const pinError =
                document.getElementById("pinError");


            /* CLEAR OLD ERROR */

            pinError.textContent = "";


            /* CHECK AMOUNT */

            if (
                amount === "" ||
                isNaN(amount) ||
                Number(amount) <= 0
            ) {

                alert(
                    "Please enter a valid amount."
                );

                amountInput.focus();

                return;
            }


            if (Number(amount) > 100000) {

                alert(
                    "Maximum transfer amount is ₹1,00,000."
                );

                amountInput.focus();

                return;
            }


            /* OPEN MODAL */

            pinModal.classList.add("show");

            pinInput.value = "";

            setTimeout(
                function() {
                    pinInput.focus();
                },
                100
            );
        }


        /* =========================================================
           CLOSE PIN MODAL
        ========================================================= */

        function closePinModal() {
            const pinModal =
                document.getElementById("pinModal");

            const pinInput =
                document.getElementById("pinInput");

            const pinError =
                document.getElementById("pinError");


            pinModal.classList.remove("show");

            pinInput.value = "";

            pinError.textContent = "";
        }


        /* =========================================================
           CLOSE WHEN CLICK OUTSIDE
        ========================================================= */

        function closePinFromOutside(event) {
            if (
                event.target.id === "pinModal"
            ) {

                closePinModal();
            }
        }


        /* =========================================================
           CONFIRM PIN
        ========================================================= */

        function confirmPin() {
            const pinInput =
                document.getElementById("pinInput");

            const pinError =
                document.getElementById("pinError");

            const confirmButton =
                document.getElementById(
                    "pinConfirmButton"
                );

            const form =
                document.getElementById(
                    "sendMoneyForm"
                );


            const pin =
                pinInput.value.trim();


            /* VALIDATE PIN */

            if (!/^[0-9]{4}$/.test(pin)) {

                pinError.textContent =
                    "Please enter your 4-digit PIN.";

                pinInput.focus();

                return;
            }


            /* PREVENT DOUBLE CLICK */

            confirmButton.disabled = true;

            confirmButton.textContent =
                "Processing...";


            /* REMOVE OLD PIN FIELD */

            const oldPin =
                form.querySelector(
                    'input[name="transaction_pin"]'
                );

            if (oldPin) {

                oldPin.remove();
            }


            /* CREATE PIN FIELD */

            const pinField =
                document.createElement("input");

            pinField.type = "hidden";

            pinField.name =
                "transaction_pin";

            pinField.value = pin;


            form.appendChild(
                pinField
            );


            /* CREATE SEND MONEY FIELD */

            const sendField =
                document.createElement("input");

            sendField.type = "hidden";

            sendField.name =
                "send_money";

            sendField.value = "1";


            form.appendChild(
                sendField
            );


            /* SUBMIT FORM */

            form.submit();
        }


        /* =========================================================
           ENTER KEY = CONFIRM PIN
        ========================================================= */

        document
            .getElementById("pinInput")
            ?.addEventListener(
                "keydown",
                function(event) {

                    if (
                        event.key === "Enter"
                    ) {

                        event.preventDefault();

                        confirmPin();
                    }

                    if (
                        event.key === "Escape"
                    ) {

                        closePinModal();
                    }
                }
            );


        /* =========================================================
           ONLY ALLOW NUMBERS IN PIN
        ========================================================= */

        document
            .getElementById("pinInput")
            ?.addEventListener(
                "input",
                function() {

                    this.value =
                        this.value
                        .replace(
                            /[^0-9]/g,
                            ''
                        )
                        .slice(0, 4);
                }
            );
    </script>


</body>

</html>