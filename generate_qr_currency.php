<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

require 'conn.php';
require 'bank_conn.php';
require 'currency_con.php';

define(
    "SECRET_KEY",
    "MBDPAY@2026_SUPER_SECRET_KEY_32"
);

define("CACHE_DIR", __DIR__ . "/cache/users/");

$message = "";
$message_type = "";

$generated_currency = null;


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {

    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['account'])) {

    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user'];

$u_account = $_SESSION['account'];

$mess_c = "";
$m_type = "";

if (isset($_SESSION['message_cur'])) {
    $mess_c = $_SESSION['message_cur'];
    $m_type = $_SESSION['message_type'];
    unset($_SESSION['message_cur']);
    unset($_SESSION['message_type']);
}

if (isset($_SESSION['mobile'])) {
    $u_mob = $_SESSION['mobile'];
}


/*
|--------------------------------------------------------------------------
| ENCRYPT DATA
|--------------------------------------------------------------------------
*/

function encryptData($text)
{

    $key = hash(
        "sha256",
        SECRET_KEY,
        true
    );

    $iv = random_bytes(16);

    $cipher = openssl_encrypt(
        $text,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode(
        $iv . $cipher
    );
}


/*
|--------------------------------------------------------------------------
| DECRYPT DATA
|--------------------------------------------------------------------------
*/

function decryptData($text)
{

    $key = hash(
        "sha256",
        SECRET_KEY,
        true
    );

    $data = base64_decode($text);

    if (
        $data === false ||
        strlen($data) < 17
    ) {

        return false;
    }

    $iv = substr(
        $data,
        0,
        16
    );

    $cipher = substr(
        $data,
        16
    );

    return openssl_decrypt(
        $cipher,
        "AES-256-CBC",
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}

// store currency in cache
function storecache($u_mob, $serial_no, $encrypted_serial, $encrypted_amount, $currency_status)
{

    $userId = hash("sha256", $u_mob);

    $folder = CACHE_DIR . $userId . "/currency";

    // Create currency cache folder
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }


    // Currency data
    $currencyData = [

        "serial_no" => encryptData($serial_no),

        "currency_serial_no" => $encrypted_serial,

        "amount" => $encrypted_amount,

        "currency_status" => encryptData($currency_status),

        "receiver_mobile" => encryptData(null),

        "sender_mobile" => encryptData($u_mob),

        "synced" => true,

        "generated_at" => date("Y-m-d H:i:s"),

        "completed_at" => null,

        "scanned_at" => null

    ];

    $e_serial = $serial_no;

    $s_file = hash("sha256", $e_serial);
    // Save currency in cache
    $cacheFile = $folder . "/" . $s_file . ".json";

    file_put_contents(
        $cacheFile,
        json_encode(
            $currencyData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ),
        LOCK_EX
    );
}



/*
|--------------------------------------------------------------------------
| GENERATE CURRENCY
|--------------------------------------------------------------------------
*/

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {


        /*
        |--------------------------------------------------------------------------
        | FORM DATA
        |--------------------------------------------------------------------------
        */

        $amount = isset($_POST['amount'])
            ? (float) $_POST['amount']
            : 0;

        $pin = isset($_POST['pin'])
            ? trim($_POST['pin'])
            : "";


        /*
        |--------------------------------------------------------------------------
        | VALIDATE AMOUNT
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {

            throw new Exception(
                "Please enter a valid amount."
            );
        }


        if ($amount > 500) {

            throw new Exception(
                "Maximum currency amount is ₹500."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE PIN
        |--------------------------------------------------------------------------
        */

        if (!preg_match('/^\d{4}$/', $pin)) {

            throw new Exception(
                "Please enter a valid 4 digit PIN."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SERVER CONNECTION
        |--------------------------------------------------------------------------
        */

        if (
            isset($serverConnected) &&
            !$serverConnected
        ) {

            throw new Exception(
                "MBD Pay server is offline. Currency generation is unavailable."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET USER
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT
                id,
                name,
                balance,
                pin
             FROM users
             WHERE account_no = ?
             LIMIT 1"
        );


        if (!$stmt) {

            throw new Exception(
                "Unable to access user account."
            );
        }


        $stmt->bind_param(
            "s",
            $u_account
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $user =
            $result->fetch_assoc();


        $stmt->close();


        if (!$user) {

            throw new Exception(
                "User account not found."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY PIN
        |--------------------------------------------------------------------------
        */

        if (
            empty($user['pin']) ||
            !password_verify(
                $pin,
                $user['pin']
            )
        ) {

            throw new Exception(
                "Incorrect PIN. Currency was not generated."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();


        try {


            /*
            |--------------------------------------------------------------------------
            | LOCK WALLET
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "SELECT
                    id,
                    name,
                    balance,
                    pin
                 FROM users
                 WHERE account_no = ?
                 LIMIT 1
                 FOR UPDATE"
            );


            if (!$stmt) {

                throw new Exception(
                    "Unable to lock wallet."
                );
            }


            $stmt->bind_param(
                "s",
                $u_account
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            $locked_user =
                $result->fetch_assoc();


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

            if (!$locked_user) {

                throw new Exception(
                    "User account not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY PIN AGAIN
            |--------------------------------------------------------------------------
            */

            if (
                empty($locked_user['pin']) ||
                !password_verify(
                    $pin,
                    $locked_user['pin']
                )
            ) {

                throw new Exception(
                    "PIN verification failed."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET BALANCE
            |--------------------------------------------------------------------------
            */

            $current_balance =
                decryptData(
                    $locked_user['balance']
                );


            if ($current_balance === false) {

                throw new Exception(
                    "Unable to read wallet balance."
                );
            }


            $current_balance =
                (float) $current_balance;


            /*
            |--------------------------------------------------------------------------
            | CHECK BALANCE
            |--------------------------------------------------------------------------
            */

            if (
                $current_balance < $amount
            ) {

                throw new Exception(
                    "Insufficient wallet balance."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | NEW BALANCE
            |--------------------------------------------------------------------------
            */

            $new_balance =
                round(
                    $current_balance - $amount,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | GENERATE SERIAL
            |--------------------------------------------------------------------------
            */

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
            | ENCRYPT CURRENCY DATA
            |--------------------------------------------------------------------------
            */

            $encrypted_serial =
                encryptData(
                    $c_serial_no
                );


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


            /*
            |--------------------------------------------------------------------------
            | TRANSACTION ID
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
            | UPDATE WALLET
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
                $locked_user['id']
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to update wallet balance."
                );
            }


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | INSERT CURRENCY
            |--------------------------------------------------------------------------
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


            $currency_status =
                "GENERATED";


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
            | INSERT TRANSACTION
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
            | LOCAL CACHE UPDATE
            |--------------------------------------------------------------------------
            */

            $userId =
                hash(
                    "sha256",
                    $u_mob
                );


            $file =
                "cache/users/$userId/profile.json";


            if (file_exists($file)) {

                $data =
                    json_decode(
                        file_get_contents($file),
                        true
                    );


                if (!is_array($data)) {

                    $data = [];
                }


                $data['balance'] =
                    $encrypted_new_balance;


                $data['server_sync'] =
                    true;


                $data['last_transaction'] =
                    $transaction_id;


                file_put_contents(
                    $file,
                    json_encode(
                        $data,
                        JSON_PRETTY_PRINT
                    )
                );
            }


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


            $message_type =
                "success";


            $generated_currency = [

                "serial_no" =>
                $c_serial_no,

                "amount" =>
                number_format(
                    $amount,
                    2
                ),

                "transaction_id" =>
                $transaction_id

            ];

            // save currency in local catche
            storecache($u_mob, $serial_no, $encrypted_serial, $encrypted_amount, $currency_status);
            $_SESSION['message_cur'] = $message;
            $_SESSION['message_type'] = $message_type;
            header("location:generate_qr_currency.php");
        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            $conn->rollback();


            throw $e;
        }
    }
} catch (Throwable $e) {

    $message =
        $e->getMessage();

    $message_type =
        "error";
}


/*
|--------------------------------------------------------------------------
| RECENT CURRENCY HISTORY
|--------------------------------------------------------------------------
*/

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


    if ($stmt) {

        $stmt->bind_param(
            "s",
            $u_mob
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $history[] =
                $row;
        }


        $stmt->close();
    }
} catch (Throwable $e) {

    /*
    | Keep page working even if history
    | cannot be loaded.
    */
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        MBDPAY | Generate Currency
    </title>


    <link
        rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">


    <style>
        /* =========================================================
   RESET
========================================================= */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

        }


        body {

            font-family:
                Arial,
                sans-serif;

            min-height: 100vh;

            background:
                radial-gradient(circle at top,
                    #bbf7d0,
                    #ecfdf5,
                    #d1fae5);

            color: #022c22;

            overflow-x: hidden;

        }


        /* =========================================================
   MAIN PAGE
========================================================= */

        .currency-page {

            min-height:
                calc(100vh - 135px);

            padding:
                25px 20px 70px;

        }


        /* =========================================================
   MESSAGE
========================================================= */

        .message {

            max-width: 1050px;

            margin:
                0 auto 18px;

            padding:
                14px 18px;

            border-radius: 15px;

            font-weight: bold;

            text-align: center;

        }


        .message.success {

            background:
                #dcfce7;

            color:
                #166534;

        }


        .message.error {

            background:
                #fee2e2;

            color:
                #991b1b;

        }


        /* =========================================================
   MAIN GRID
========================================================= */

        .currency-container {

            max-width:
                1050px;

            margin:
                0 auto;

            display:
                grid;

            grid-template-columns:
                1fr 1.1fr;

            gap:
                25px;

        }


        /* =========================================================
   CARD
========================================================= */

        .currency-card {

            background:
                rgba(255, 255, 255, .90);

            backdrop-filter:
                blur(18px);

            border-radius:
                30px;

            padding:
                30px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .15);

            border:
                1px solid rgba(255, 255, 255, .7);

        }


        /* =========================================================
   TITLE
========================================================= */

        .currency-title {

            display:
                flex;

            align-items:
                center;

            gap:
                15px;

            margin-bottom:
                25px;

        }


        .currency-icon {

            width:
                55px;

            height:
                55px;

            border-radius:
                17px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                30px;

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            color:
                white;

            box-shadow:
                0 8px 20px rgba(245, 158, 11, .35);

        }


        .currency-title h1 {

            color:
                #022c22;

            font-size:
                27px;

        }


        .currency-title p {

            margin-top:
                5px;

            color:
                #64748b;

            font-size:
                14px;

        }


        /* =========================================================
   AMOUNT
========================================================= */

        .amount-label {

            display:
                block;

            font-weight:
                bold;

            color:
                #334155;

            margin-bottom:
                10px;

        }


        .amount-wrapper {

            position:
                relative;

            margin-bottom:
                20px;

        }


        .amount-symbol {

            position:
                absolute;

            left:
                18px;

            top:
                50%;

            transform:
                translateY(-50%);

            font-size:
                25px;

            font-weight:
                bold;

            color:
                #059669;

        }


        .amount-input {

            width:
                100%;

            height:
                65px;

            border:
                2px solid #d1fae5;

            border-radius:
                18px;

            padding:
                0 20px 0 50px;

            font-size:
                25px;

            font-weight:
                bold;

            color:
                #022c22;

            background:
                #f0fdf4;

            outline:
                none;

            transition:
                .3s;

        }


        .amount-input:focus {

            border-color:
                #059669;

            box-shadow:
                0 0 0 4px rgba(5, 150, 105, .12);

        }


        /* =========================================================
   QUICK AMOUNT
========================================================= */

        .quick-title {

            font-size:
                13px;

            color:
                #64748b;

            margin-bottom:
                10px;

        }


        .quick-buttons {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                9px;

            margin-bottom:
                25px;

        }


        .quick-buttons button {

            border:
                none;

            padding:
                9px 15px;

            border-radius:
                20px;

            background:
                #dcfce7;

            color:
                #047857;

            font-weight:
                bold;

            cursor:
                pointer;

            transition:
                .25s;

        }


        .quick-buttons button:hover {

            background:
                #059669;

            color:
                white;

            transform:
                translateY(-2px);

        }


        /* =========================================================
   GENERATE BUTTON
========================================================= */

        .generate-btn {

            width:
                100%;

            border:
                none;

            border-radius:
                18px;

            padding:
                17px;

            font-size:
                17px;

            font-weight:
                bold;

            color:
                white;

            cursor:
                pointer;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .3);

            transition:
                .3s;

        }


        .generate-btn:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 15px 30px rgba(5, 150, 105, .4);

        }


        /* =========================================================
   LEFT INFORMATION
========================================================= */

        .info-box {

            margin-top:
                20px;

            padding:
                15px;

            border-radius:
                15px;

            background:
                #ecfdf5;

            color:
                #065f46;

            font-size:
                13px;

            line-height:
                1.6;

        }


        .info-box strong {

            color:
                #022c22;

        }


        /* =========================================================
   RIGHT INSTRUCTION CARD
========================================================= */

        .instruction-card {

            min-height:
                520px;

        }


        /* =========================================================
   GENERATION GUIDE
========================================================= */

        .generation-guide {

            display:
                flex;

            flex-direction:
                column;

            gap:
                12px;

        }


        /* =========================================================
   GUIDE STEP
========================================================= */

        .guide-step {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                14px;

            padding:
                13px;

            background:
                linear-gradient(135deg,
                    #f0fdf4,
                    #ffffff);

            border:
                1px solid #d1fae5;

            border-radius:
                17px;

            transition:
                .25s;

        }


        .guide-step:hover {

            transform:
                translateX(4px);

            box-shadow:
                0 8px 20px rgba(5, 150, 105, .10);

        }


        /* =========================================================
   STEP NUMBER
========================================================= */

        .step-number {

            min-width:
                38px;

            width:
                38px;

            height:
                38px;

            border-radius:
                12px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            color:
                white;

            font-weight:
                900;

            font-size:
                15px;

            box-shadow:
                0 5px 12px rgba(5, 150, 105, .20);

        }


        /* =========================================================
   STEP CONTENT
========================================================= */

        .step-content h3 {

            color:
                #022c22;

            font-size:
                15px;

            margin-bottom:
                5px;

        }


        .step-content p {

            color:
                #64748b;

            font-size:
                12px;

            line-height:
                1.55;

        }


        .step-content strong {

            color:
                #047857;

        }


        /* =========================================================
   SECURITY BOX
========================================================= */

        .security-box {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                13px;

            margin-top:
                17px;

            padding:
                15px;

            border-radius:
                17px;

            background:
                linear-gradient(135deg,
                    #ecfdf5,
                    #d1fae5);

            border:
                1px solid #a7f3d0;

        }


        .security-icon {

            font-size:
                25px;

        }


        .security-box h3 {

            color:
                #065f46;

            font-size:
                14px;

            margin-bottom:
                5px;

        }


        .security-box p {

            color:
                #047857;

            font-size:
                12px;

            line-height:
                1.5;

        }


        /* =========================================================
   IMPORTANT NOTICE
========================================================= */

        .instruction-note {

            margin-top:
                15px;

            padding:
                14px;

            background:
                #fef3c7;

            border-left:
                5px solid #f59e0b;

            border-radius:
                10px;

            color:
                #92400e;

            font-size:
                12px;

            line-height:
                1.5;

        }


        .instruction-note strong {

            color:
                #78350f;

        }


        /* =========================================================
   HISTORY
========================================================= */

        .history-card {

            max-width:
                1050px;

            margin:
                25px auto 0;

        }


        .history-card h2 {

            color:
                #022c22;

            margin-bottom:
                18px;

        }


        .history-table {

            width:
                100%;

            border-collapse:
                collapse;

        }


        .history-table th {

            text-align:
                left;

            padding:
                12px;

            background:
                #ecfdf5;

            color:
                #065f46;

            font-size:
                13px;

        }


        .history-table td {

            padding:
                12px;

            border-bottom:
                1px solid #e2e8f0;

            font-size:
                12px;

            color:
                #475569;

        }


        .serial-cell {

            max-width:
                170px;

            word-break:
                break-all;

        }


        .status-badge {

            display:
                inline-block;

            padding:
                5px 10px;

            border-radius:
                20px;

            font-weight:
                bold;

            font-size:
                11px;

        }


        .status-GENERATED {

            background:
                #dcfce7;

            color:
                #166534;

        }


        .status-SCANNED {

            background:
                #dbeafe;

            color:
                #1d4ed8;

        }


        .status-EXPIRED {

            background:
                #fee2e2;

            color:
                #991b1b;

        }


        .status-CANCELLED {

            background:
                #f1f5f9;

            color:
                #475569;

        }


        /* =========================================================
   PIN OVERLAY
========================================================= */

        .pin-overlay {

            position:
                fixed;

            inset:
                0;

            background:
                rgba(2, 44, 34, .65);

            backdrop-filter:
                blur(8px);

            display:
                none;

            align-items:
                center;

            justify-content:
                center;

            z-index:
                9999;

            padding:
                20px;

        }


        .pin-overlay.active {

            display:
                flex;

            animation:
                fadeIn .2s ease;

        }


        @keyframes fadeIn {

            from {

                opacity:
                    0;

            }

            to {

                opacity:
                    1;

            }

        }


        /* =========================================================
   PIN MODAL
========================================================= */

        .pin-modal {

            width:
                100%;

            max-width:
                420px;

            background:
                rgba(255, 255, 255, .98);

            border-radius:
                28px;

            padding:
                30px;

            text-align:
                center;

            box-shadow:
                0 30px 80px rgba(0, 0, 0, .30);

            animation:
                modalIn .25s ease;

        }


        @keyframes modalIn {

            from {

                opacity:
                    0;

                transform:
                    translateY(25px) scale(.95);

            }

            to {

                opacity:
                    1;

                transform:
                    translateY(0) scale(1);

            }

        }


        /* =========================================================
   PIN ICON
========================================================= */

        .pin-icon {

            width:
                70px;

            height:
                70px;

            margin:
                0 auto 18px;

            border-radius:
                22px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                34px;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            color:
                white;

            box-shadow:
                0 12px 25px rgba(5, 150, 105, .3);

        }


        .pin-modal h2 {

            color:
                #022c22;

            margin-bottom:
                7px;

        }


        .pin-modal p {

            color:
                #64748b;

            font-size:
                14px;

            line-height:
                1.5;

            margin-bottom:
                20px;

        }


        .pin-amount {

            display:
                inline-block;

            padding:
                8px 15px;

            border-radius:
                20px;

            background:
                #ecfdf5;

            color:
                #047857;

            font-weight:
                bold;

            margin-bottom:
                18px;

        }


        /* =========================================================
   PIN INPUT
========================================================= */

        .pin-wrapper {

            position:
                relative;

            margin-bottom:
                15px;

        }


        .pin-input {

            width:
                100%;

            height:
                60px;

            border:
                2px solid #d1fae5;

            border-radius:
                16px;

            background:
                #f0fdf4;

            outline:
                none;

            text-align:
                center;

            font-size:
                26px;

            font-weight:
                bold;

            letter-spacing:
                10px;

            padding:
                0 55px 0 20px;

            color:
                #022c22;

            transition:
                .2s;

        }


        .pin-input:focus {

            border-color:
                #059669;

            box-shadow:
                0 0 0 4px rgba(5, 150, 105, .12);

        }


        /* =========================================================
   SHOW PIN
========================================================= */

        .show-pin {

            position:
                absolute;

            right:
                15px;

            top:
                50%;

            transform:
                translateY(-50%);

            border:
                none;

            background:
                transparent;

            cursor:
                pointer;

            font-size:
                20px;

            opacity:
                .65;

        }


        /* =========================================================
   PIN ERROR
========================================================= */

        .pin-error {

            display:
                none;

            background:
                #fee2e2;

            color:
                #991b1b;

            border-radius:
                12px;

            padding:
                10px;

            font-size:
                13px;

            margin-bottom:
                15px;

            font-weight:
                bold;

        }


        /* =========================================================
   PIN BUTTONS
========================================================= */

        .pin-actions {

            display:
                flex;

            gap:
                10px;

        }


        .pin-cancel {

            flex:
                1;

            border:
                none;

            padding:
                14px;

            border-radius:
                15px;

            background:
                #f1f5f9;

            color:
                #475569;

            font-weight:
                bold;

            cursor:
                pointer;

        }


        .pin-confirm {

            flex:
                2;

            border:
                none;

            padding:
                14px;

            border-radius:
                15px;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            color:
                white;

            font-weight:
                bold;

            cursor:
                pointer;

            box-shadow:
                0 8px 20px rgba(5, 150, 105, .25);

        }


        .pin-confirm:disabled {

            opacity:
                .6;

            cursor:
                not-allowed;

        }


        /* =========================================================
   RESPONSIVE
========================================================= */

        @media(max-width:850px) {

            .currency-container {

                grid-template-columns:
                    1fr;

            }


            .instruction-card {

                min-height:
                    auto;

            }

        }


        @media(max-width:600px) {

            .currency-page {

                padding:
                    10px 10px 75px;

            }


            .currency-card {

                padding:
                    20px;

                border-radius:
                    22px;

            }


            .history-table {

                display:
                    block;

                overflow-x:
                    auto;

                white-space:
                    nowrap;

            }


            .pin-modal {

                padding:
                    25px 20px;

            }

        }
    </style>

</head>


<body>


    <?php require 'navbar.php'; ?>


    <main class="currency-page">


        <?php if ($message !== "") { ?>

            <div
                class="message <?php echo htmlspecialchars($message_type); ?>">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php } ?>

        <?php if ($mess_c !== "") { ?>

            <div class="message <?php echo $m_type; ?>">

                <?php echo htmlspecialchars($mess_c); ?>

            </div>

        <?php } ?>


        <div class="currency-container">


            <!-- =========================================================
     LEFT CARD
========================================================= -->

            <section class="currency-card">


                <div class="currency-title">

                    <div class="currency-icon">

                        ₹

                    </div>


                    <div>

                        <h1>
                            Generate Currency
                        </h1>

                        <p>
                            Create MBD Digital Currency
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    id="currencyForm"
                    autocomplete="off">


                    <!-- PIN IS INSERTED HERE BY JAVASCRIPT -->

                    <input
                        type="hidden"
                        name="pin"
                        id="hiddenPin">


                    <label class="amount-label">

                        Enter Currency Amount

                    </label>


                    <div class="amount-wrapper">

                        <span class="amount-symbol">

                            ₹

                        </span>


                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="amount-input"
                            placeholder="0.00"
                            min="1"
                            max="1000"
                            step="0.01"
                            readonly
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
                            onclick="setAmount(200)">

                            ₹200

                        </button>
                        
                        <button
                            type="button"
                            onclick="setAmount(500)">

                            ₹500

                        </button>

                    </div>


                    <!--
        | This button opens PIN modal.
        | It does NOT directly submit.
        -->

                    <button
                        type="button"
                        class="generate-btn"
                        onclick="openPinModal()">

                        🔐 Generate QR Currency

                    </button>


                </form>


                <div class="info-box">

                    <strong>
                        Secure Currency Generation
                    </strong>

                    <br>

                    Your wallet PIN is required before
                    currency generation.

                    <br><br>

                    After successful PIN verification,
                    MBD Pay checks your balance and
                    generates a unique digital currency
                    serial number.

                    <br><br>

                    <strong>
                        Security:
                    </strong>

                    Your PIN and wallet balance are never
                    placed inside the generated currency.

                </div>


            </section>


            <!-- =========================================================
     RIGHT INSTRUCTION CARD
========================================================= -->

            <section class="currency-card instruction-card">


                <div class="currency-title">

                    <div class="currency-icon">

                        ₹

                    </div>


                    <div>

                        <h1>
                            MBD Digital Currency
                        </h1>

                        <p>
                            How currency generation works
                        </p>

                    </div>

                </div>


                <div class="generation-guide">


                    <!-- STEP 1 -->

                    <div class="guide-step">

                        <div class="step-number">

                            1

                        </div>


                        <div class="step-content">

                            <h3>
                                Enter Amount
                            </h3>


                            <p>

                                Enter the amount of MBD Digital
                                Currency you want to generate.
                                You can also select a quick amount.

                            </p>

                        </div>

                    </div>


                    <!-- STEP 2 -->

                    <div class="guide-step">

                        <div class="step-number">

                            2

                        </div>


                        <div class="step-content">

                            <h3>
                                Start Generation
                            </h3>


                            <p>

                                Click
                                <strong>
                                    Generate QR Currency
                                </strong>
                                to begin the secure generation
                                process.

                            </p>

                        </div>

                    </div>


                    <!-- STEP 3 -->

                    <div class="guide-step">

                        <div class="step-number">

                            3

                        </div>


                        <div class="step-content">

                            <h3>
                                Verify PIN
                            </h3>


                            <p>

                                Enter your wallet PIN in the
                                security window. The currency
                                will only be generated after
                                successful PIN verification.

                            </p>

                        </div>

                    </div>


                    <!-- STEP 4 -->

                    <div class="guide-step">

                        <div class="step-number">

                            4

                        </div>


                        <div class="step-content">

                            <h3>
                                Balance Verification
                            </h3>


                            <p>

                                MBD Pay checks your available
                                wallet balance before reserving
                                the requested amount.

                            </p>

                        </div>

                    </div>


                    <!-- STEP 5 -->

                    <div class="guide-step">

                        <div class="step-number">

                            5

                        </div>


                        <div class="step-content">

                            <h3>
                                Currency Creation
                            </h3>


                            <p>

                                Once everything is verified,
                                the amount is deducted from
                                your wallet and a unique
                                currency serial number is created.

                            </p>

                        </div>

                    </div>


                    <!-- STEP 6 -->

                    <div class="guide-step">

                        <div class="step-number">

                            6

                        </div>


                        <div class="step-content">

                            <h3>
                                Ready for Payment
                            </h3>


                            <p>

                                The generated digital currency
                                becomes available for use in
                                the MBD Pay payment system.

                            </p>

                        </div>

                    </div>


                </div>


                <!-- SECURITY -->

                <div class="security-box">


                    <div class="security-icon">

                        🛡️

                    </div>


                    <div>

                        <h3>
                            Security Protection
                        </h3>


                        <p>

                            Your PIN, password and wallet
                            balance are never stored inside
                            the currency payload.

                        </p>

                    </div>


                </div>


                <!-- IMPORTANT NOTICE -->

                <div class="instruction-note">

                    <strong>
                        Important:
                    </strong>

                    <br><br>

                    Currency generation requires an active
                    connection to the MBD Pay server and
                    sufficient wallet balance.

                </div>


            </section>


        </div>


        <!-- =========================================================
     RECENT HISTORY
========================================================= -->

        <section class="currency-card history-card">


            <h2>
                Recent Generated Currency
            </h2>


            <?php if (count($history) > 0) { ?>


                <table class="history-table">


                    <thead>

                        <tr>

                            <th>
                                Serial Number
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Receiver
                            </th>

                            <th>
                                Generated
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($history as $row) { ?>


                            <tr>


                                <td class="serial-cell">

                                    <?php

                                    echo htmlspecialchars(
                                        $row['serial_no']
                                    );

                                    ?>

                                </td>


                                <td>

                                    ₹<?php

                                        $historyAmount =
                                            decryptData(
                                                $row['amount']
                                            );


                                        if ($historyAmount !== false) {

                                            echo number_format(
                                                (float) $historyAmount,
                                                2
                                            );
                                        } else {

                                            echo "—";
                                        }

                                        ?>

                                </td>


                                <td>


                                    <span
                                        class="status-badge status-<?php

                                                                    echo htmlspecialchars(
                                                                        strtoupper(
                                                                            $row['status']
                                                                        )
                                                                    );

                                                                    ?>">


                                        <?php

                                        echo ucfirst(
                                            strtolower(
                                                $row['status']
                                            )
                                        );

                                        ?>


                                    </span>


                                </td>


                                <td>

                                    <?php

                                    if (
                                        !empty($row['receiver_mobile'])
                                    ) {

                                        echo htmlspecialchars(
                                            $row['receiver_mobile']
                                        );
                                    } else {

                                        echo "—";
                                    }

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $row['generated_at']
                                    );

                                    ?>

                                </td>


                            </tr>


                        <?php } ?>


                    </tbody>


                </table>


            <?php } else { ?>


                <p
                    style="
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


    <!-- =========================================================
     PIN MODAL
========================================================= -->

    <div
        class="pin-overlay"
        id="pinOverlay">


        <div class="pin-modal">


            <div class="pin-icon">

                🔐

            </div>


            <h2>

                Verify Your PIN

            </h2>


            <p>

                Enter your wallet PIN to authorize
                currency generation.

            </p>


            <div
                class="pin-amount"
                id="pinAmount">

                ₹0.00

            </div>


            <div class="pin-wrapper">


                <input
                    type="password"
                    id="pinInput"
                    class="pin-input"
                    inputmode="numeric"
                    maxlength="4"
                    autocomplete="off"
                    placeholder="••••">


                <button
                    type="button"
                    class="show-pin"
                    id="showPinButton"
                    onclick="togglePin()">

                    👁

                </button>


            </div>


            <div
                class="pin-error"
                id="pinError">

                Please enter a valid PIN.

            </div>


            <div class="pin-actions">


                <button
                    type="button"
                    class="pin-cancel"
                    onclick="closePinModal()">

                    Cancel

                </button>


                <button
                    type="button"
                    class="pin-confirm"
                    id="confirmPinButton"
                    onclick="confirmPin()">

                    ✓ Verify & Generate

                </button>


            </div>


        </div>


    </div>


    <script>
        /*
|--------------------------------------------------------------------------
| QUICK AMOUNT
|--------------------------------------------------------------------------
*/

        function setAmount(value) {

            document.getElementById(
                "amount"
            ).value = value;

        }


        /*
        |--------------------------------------------------------------------------
        | OPEN PIN MODAL
        |--------------------------------------------------------------------------
        */

        function openPinModal() {

            const amountInput =
                document.getElementById(
                    "amount"
                );


            const amount =
                parseFloat(
                    amountInput.value
                );


            if (
                isNaN(amount) ||
                amount <= 0
            ) {

                alert(
                    "Please enter a valid amount."
                );

                amountInput.focus();

                return;

            }


            if (amount > 1000) {

                alert(
                    "Maximum currency amount is ₹1,000."
                );

                amountInput.focus();

                return;

            }


            document.getElementById(
                    "pinAmount"
                ).textContent =
                "₹" + amount.toFixed(2);


            document.getElementById(
                "pinInput"
            ).value = "";


            document.getElementById(
                "hiddenPin"
            ).value = "";


            document.getElementById(
                    "pinError"
                ).style.display =
                "none";


            document.getElementById(
                    "confirmPinButton"
                ).disabled =
                false;


            document.getElementById(
                    "confirmPinButton"
                ).textContent =
                "✓ Verify & Generate";


            document.getElementById(
                "pinOverlay"
            ).classList.add(
                "active"
            );


            setTimeout(
                function() {

                    document.getElementById(
                        "pinInput"
                    ).focus();

                },
                200
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE PIN MODAL
        |--------------------------------------------------------------------------
        */

        function closePinModal() {

            document.getElementById(
                "pinOverlay"
            ).classList.remove(
                "active"
            );


            document.getElementById(
                "pinInput"
            ).value = "";


            document.getElementById(
                "hiddenPin"
            ).value = "";


            document.getElementById(
                    "pinError"
                ).style.display =
                "none";

        }


        /*
        |--------------------------------------------------------------------------
        | SHOW / HIDE PIN
        |--------------------------------------------------------------------------
        */

        function togglePin() {

            const input =
                document.getElementById(
                    "pinInput"
                );


            const button =
                document.getElementById(
                    "showPinButton"
                );


            if (
                input.type === "password"
            ) {

                input.type =
                    "text";

                button.textContent =
                    "🙈";

            } else {

                input.type =
                    "password";

                button.textContent =
                    "👁";

            }

        }


        /*
        |--------------------------------------------------------------------------
        | PIN INPUT
        |--------------------------------------------------------------------------
        */

        document
            .getElementById("pinInput")
            .addEventListener(
                "input",
                function() {

                    this.value =
                        this.value.replace(
                            /[^0-9]/g,
                            ""
                        );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | ENTER KEY
        |--------------------------------------------------------------------------
        */

        document
            .getElementById("pinInput")
            .addEventListener(
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


        /*
        |--------------------------------------------------------------------------
        | CONFIRM PIN
        |--------------------------------------------------------------------------
        */

        function confirmPin() {

            const pinInput =
                document.getElementById(
                    "pinInput"
                );


            const pin =
                pinInput.value.trim();


            const errorBox =
                document.getElementById(
                    "pinError"
                );


            const confirmButton =
                document.getElementById(
                    "confirmPinButton"
                );


            /*
            | 4-6 digit PIN
            */

            if (
                !/^\d{4,6}$/.test(pin)
            ) {

                errorBox.textContent =
                    "Please enter a valid 4-6 digit PIN.";

                errorBox.style.display =
                    "block";

                pinInput.focus();

                return;

            }


            /*
            | Put PIN into hidden field.
            */

            document.getElementById(
                    "hiddenPin"
                ).value =
                pin;


            /*
            | Disable double submission.
            */

            confirmButton.disabled =
                true;


            confirmButton.textContent =
                "Verifying PIN...";


            /*
            | Submit form.
            |
            | Server will verify PIN again
            | using password_verify().
            */

            document.getElementById(
                "currencyForm"
            ).submit();

        }


        /*
        |--------------------------------------------------------------------------
        | CLICK OUTSIDE MODAL
        |--------------------------------------------------------------------------
        */

        document
            .getElementById("pinOverlay")
            .addEventListener(
                "click",
                function(event) {

                    if (
                        event.target === this
                    ) {

                        closePinModal();

                    }

                }
            );
    </script>


</body>

</html>