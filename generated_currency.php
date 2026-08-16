<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

$message = "";

$message1 = "";

require 'conn.php';
require 'bank_conn.php';
require 'currency_con.php';

// catch logic

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

$u_account = $_SESSION['account'];

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}


if (isset($_SESSION['user'])) {

    $username = $_SESSION['user'];
}

if (isset($_SESSION['mobile'])) {
    $u_mob = $_SESSION['mobile'];
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}



/*
|--------------------------------------------------------------------------
| PIN VERIFICATION
|--------------------------------------------------------------------------
*/

$unlocked_currency = null;
$pin_error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | ONLINE PIN VERIFICATION
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['online'])) {

        $currency_id =
            (int)($_POST['currency_id'] ?? 0);

        $entered_pin =
            trim($_POST['pin'] ?? "");


        if (
            $currency_id <= 0 ||
            $entered_pin === ""
        ) {

            $pin_error =
                "Please enter your PIN.";
        } else {

            /*
            |--------------------------------------------------------------------------
            | GET CURRENCY FROM SERVER
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $c_conn,
                "SELECT
                    id,
                    serial_no,
                    encrypted_serial,
                    amount,
                    sender_mobile,
                    receiver_mobile,
                    status,
                    generated_at
                 FROM currency
                 WHERE id=?
                 AND sender_mobile=?
                 AND status='GENERATED'
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "is",
                $currency_id,
                $u_mob
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $online_currency =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);


            if (!$online_currency) {

                $pin_error =
                    "Currency not found.";
            } else {

                /*
                |--------------------------------------------------------------------------
                | GET USER PIN FROM SERVER
                |--------------------------------------------------------------------------
                */

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT pin
                     FROM users
                     WHERE mobile=?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $u_mob
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                $user =
                    mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);


                /*
                |--------------------------------------------------------------------------
                | VERIFY ONLINE PIN
                |--------------------------------------------------------------------------
                */

                if (
                    $user &&
                    password_verify(
                        $entered_pin,
                        $user['pin']
                    )
                ) {

                    /*
                    | PIN CORRECT
                    */

                    $unlocked_currency =
                        $online_currency;


                    $_SESSION['unlocked_currency_id'] =
                        $online_currency['id'];


                    /*
                    | Clear old offline unlock
                    */

                    unset(
                        $_SESSION['unlocked_currency_serial']
                    );


                    $pin_error = "";
                } else {

                    /*
                    | PIN WRONG
                    */

                    unset(
                        $_SESSION['unlocked_currency_id']
                    );

                    $pin_error =
                        "Incorrect PIN. Please try again.";
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | OFFLINE PIN VERIFICATION
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This is INSIDE try.
    | It must NOT be inside catch.
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['offline'])) {

        $serialNo =
            trim($_POST['serialNo'] ?? "");

        $entered_pin =
            trim($_POST['pin'] ?? "");


        if (
            $serialNo === "" ||
            $entered_pin === ""
        ) {

            $pin_error =
                "Please enter your PIN.";
        } else {

            /*
            |--------------------------------------------------------------------------
            | USER CACHE DIRECTORY
            |--------------------------------------------------------------------------
            */

            $userId =
                hash(
                    "sha256",
                    $u_mob
                );


            /*
            |--------------------------------------------------------------------------
            | PROFILE CACHE
            |--------------------------------------------------------------------------
            */

            $profile =
                CACHE_DIR .
                $userId .
                "/profile.json";


            if (!file_exists($profile)) {

                $pin_error =
                    "Offline profile not found.";
            } else {

                /*
                |--------------------------------------------------------------------------
                | READ PROFILE
                |--------------------------------------------------------------------------
                */

                $profileData =
                    json_decode(
                        file_get_contents(
                            $profile
                        ),
                        true
                    );


                /*
                |--------------------------------------------------------------------------
                | GET ENCRYPTED/HASHED PIN
                |--------------------------------------------------------------------------
                */

                $cachedPin =
                    $profileData['pin'] ?? "";


                /*
                |--------------------------------------------------------------------------
                | VERIFY OFFLINE PIN
                |--------------------------------------------------------------------------
                */

                if (
                    empty($cachedPin) ||
                    !password_verify(
                        $entered_pin,
                        $cachedPin
                    )
                ) {

                    unset(
                        $_SESSION['unlocked_currency_serial']
                    );

                    $pin_error =
                        "Incorrect PIN. Please try again.";
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | PIN CORRECT
                    |--------------------------------------------------------------------------
                    */

                    $currencyDir =
                        CACHE_DIR .
                        $userId .
                        "/currency/";


                    $foundCurrency = null;


                    /*
                    |--------------------------------------------------------------------------
                    | READ CACHED CURRENCIES
                    |--------------------------------------------------------------------------
                    */

                    if (
                        is_dir(
                            $currencyDir
                        )
                    ) {

                        $files =
                            glob(
                                $currencyDir .
                                    "*.json"
                            );


                        foreach (
                            $files
                            as $file
                        ) {

                            $cachedCurrency =
                                json_decode(
                                    file_get_contents(
                                        $file
                                    ),
                                    true
                                );


                            if (
                                !$cachedCurrency
                            ) {
                                continue;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | SERIAL_NO IS ENCRYPTED
                            |--------------------------------------------------------------------------
                            */

                            if (
                                empty($cachedCurrency['serial_no'])
                            ) {
                                continue;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | DECRYPT ONLY FOR COMPARISON
                            |--------------------------------------------------------------------------
                            */

                            $cachedSerial =
                                decryptData(
                                    $cachedCurrency['serial_no']
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | FIND SELECTED CURRENCY
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $cachedSerial ===
                                $serialNo
                            ) {

                                /*
                                |--------------------------------------------------------------------------
                                | FOUND
                                |--------------------------------------------------------------------------
                                |
                                | IMPORTANT:
                                | Keep all original encrypted values.
                                |
                                | serial_no             -> encrypted
                                | currency_serial_no    -> encrypted
                                | amount                -> encrypted
                                | currency_status       -> encrypted
                                | receiver_mobile       -> encrypted
                                | sender_mobile         -> encrypted
                                |
                                */

                                $foundCurrency =
                                    $cachedCurrency;

                                break;
                            }
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK CURRENCY
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $foundCurrency !== null
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | CREATE LOCAL ID
                        |--------------------------------------------------------------------------
                        */

                        $foundCurrency['id'] =
                            md5(
                                $serialNo
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | UNLOCK CURRENCY
                        |--------------------------------------------------------------------------
                        */

                        $unlocked_currency =
                            $foundCurrency;


                        /*
                        |--------------------------------------------------------------------------
                        | SAVE UNLOCKED SERIAL
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['unlocked_currency_serial'] =
                            $serialNo;


                        /*
                        | Clear online unlock
                        */

                        unset(
                            $_SESSION['unlocked_currency_id']
                        );


                        $pin_error = "";
                    } else {

                        $pin_error =
                            "Offline currency not found.";
                    }
                }
            }
        }
    }
} catch (Throwable $th) {

    /*
    |--------------------------------------------------------------------------
    | SERVER/DATABASE ERROR
    |--------------------------------------------------------------------------
    |
    | Do not put the offline PIN verification here.
    |
    */

    $pin_error =
        "Server connection unavailable.";
}

/*FETCH GENERATED CURRENCIES*/

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
        WHERE status = 'GENERATED' AND sender_mobile=$u_mob
        ORDER BY generated_at DESC";


    $result = mysqli_query(
        $c_conn,
        $sql
    );


    if (!$result) {

        die("Currency query failed: "
            . mysqli_error($conn));
    }


    $total_currency =
        mysqli_num_rows($result);


    $total_value = 0;

    $currency_rows = [];


    while (
        $row = mysqli_fetch_assoc($result)
    ) {

        $currency_rows[] = $row;

        $total_value +=
            decryptData($row['amount']);
    }
} catch (\Throwable $th) {

    $total_currency = 0;
    $total_value = 0;
    $currency_rows = [];

    // User cache path
    $userId = hash("sha256", $u_mob);

    // User currency folder
    $currencyDir = CACHE_DIR . $userId . "/currency/";

    if (is_dir($currencyDir)) {

        $files = glob($currencyDir . "*.json");

        foreach ($files as $file) {

            $currency = json_decode(file_get_contents($file), true);

            if (!$currency) {
                continue;
            }

            // File name is the encrypted serial number
            $currency_rows[] = $currency;

            $total_value +=
                decryptData($currency['amount']);

            $total_currency = count(glob($currencyDir . "*.json"));
        }
    }
    usort($currency_rows, function ($a, $b) {
        return strtotime($b['generated_at']) <=> strtotime($a['generated_at']);
    });
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        MBD PAY | Generated Currency
    </title>


    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">


    <!--
|--------------------------------------------------------------------------
| QR CODE LIBRARY
|--------------------------------------------------------------------------
-->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>


    <style>
        /* =========================================================
   RESET
========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =========================================================
   BODY
========================================================= */

        body {

            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            min-height: 100vh;

            color: #022c22;

            background:

                radial-gradient(circle at 10% 10%,
                    rgba(16, 185, 129, .25),
                    transparent 30%),

                radial-gradient(circle at 90% 20%,
                    rgba(52, 211, 153, .18),
                    transparent 28%),

                linear-gradient(135deg,
                    #ecfdf5,
                    #f0fdf4,
                    #d1fae5);

            overflow-x: hidden;
        }


        /* =========================================================
   PAGE
========================================================= */

        .currency-page {

            width: 100%;

            max-width: 1250px;

            margin: auto;

            padding: 35px 25px 130px;
        }


        /* =========================================================
   HEADER
========================================================= */

        .hero-header {

            position: relative;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 25px;

            margin-bottom: 30px;

            padding: 30px;

            border-radius: 30px;

            overflow: hidden;

            background:
                linear-gradient(135deg,
                    rgba(255, 255, 255, .9),
                    rgba(236, 253, 245, .78));

            backdrop-filter: blur(20px);

            border:
                1px solid rgba(255, 255, 255, .9);

            box-shadow:
                0 25px 60px rgba(0, 0, 0, .08);
        }


        .hero-header::before {

            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            background:
                rgba(16, 185, 129, .10);

            top: -130px;

            right: -50px;
        }


        .hero-content {

            position: relative;

            z-index: 2;
        }


        .title-row {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .title-icon {

            width: 58px;
            height: 58px;

            border-radius: 18px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 29px;

            color: white;

            background:
                linear-gradient(135deg,
                    #047857,
                    #10b981);

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .3);
        }


        .hero-content h1 {

            font-size: 34px;

            font-weight: 800;

            color: #022c22;
        }


        .hero-content p {

            margin-top: 7px;

            color: #64748b;

            font-size: 14px;
        }

        .message {


            text-align: center;

            color: #047857;

            margin-bottom: 15px;

        }

        .message1 {


            text-align: center;

            color: #e60404;

            margin-bottom: 15px;

        }

        /* =========================================================
   SUMMARY
========================================================= */

        .summary {

            position: relative;

            z-index: 2;

            display: flex;

            gap: 14px;
        }


        .summary-card {

            min-width: 150px;

            padding: 18px;

            border-radius: 20px;

            background:
                rgba(255, 255, 255, .8);

            border:
                1px solid rgba(255, 255, 255, .9);

            box-shadow:
                0 12px 30px rgba(0, 0, 0, .07);
        }


        .summary-label {

            display: block;

            color: #64748b;

            font-size: 11px;

            margin-bottom: 5px;

            text-transform: uppercase;

            letter-spacing: .7px;
        }


        .summary-value {

            font-size: 24px;

            font-weight: 800;

            color: #047857;
        }


        /* =========================================================
   SECTION
========================================================= */

        .section-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin: 30px 3px 18px;
        }


        .section-title {

            font-size: 20px;

            font-weight: 750;

            color: #064e3b;
        }


        .live-indicator {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 7px 12px;

            border-radius: 20px;

            background:
                rgba(220, 252, 231, .85);

            color: #15803d;

            font-size: 11px;

            font-weight: 700;
        }


        /* =========================================================
   LIVE DOT
========================================================= */

        .live-dot {

            position: relative;

            width: 9px;
            height: 9px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 6px rgba(34, 197, 94, .9),
                0 0 14px rgba(34, 197, 94, .6);

            animation:
                liveGlow 1.5s ease-in-out infinite;
        }

        .offline-dot {

            position: relative;

            width: 9px;
            height: 9px;

            border-radius: 50%;

            background: #e70303;

            box-shadow:
                0 0 6px rgba(187, 17, 54, 0.9),
                0 0 14px rgba(227, 66, 66, 0.6);

            animation:
                liveGlow 1.5s ease-in-out infinite;
        }


        .live-dot::before {

            content: "";

            position: absolute;

            top: 50%;
            left: 50%;

            width: 100%;
            height: 100%;

            border-radius: 50%;

            border: 2px solid #22c55e;

            transform:
                translate(-50%, -50%);

            animation:
                liveRing 1.5s ease-out infinite;
        }

        .offline-dot::before {

            content: "";

            position: absolute;

            top: 50%;
            left: 50%;

            width: 100%;
            height: 100%;

            border-radius: 50%;

            border: 2px solid #b50909;

            transform:
                translate(-50%, -50%);

            animation:
                liveRing 1.5s ease-out infinite;
        }


        @keyframes liveGlow {

            0%,
            100% {

                transform: scale(1);

                box-shadow:
                    0 0 5px rgba(34, 197, 94, .8),
                    0 0 12px rgba(34, 197, 94, .5);
            }

            50% {

                transform: scale(1.25);

                box-shadow:
                    0 0 9px rgba(34, 197, 94, 1),
                    0 0 20px rgba(34, 197, 94, .75);
            }
        }

        @keyframes offlineGlow {

            0%,
            100% {

                transform: scale(1);

                box-shadow:
                    0 0 5px rgba(162, 10, 10, 0.8),
                    0 0 12px rgba(173, 11, 11, 0.5);
            }

            50% {

                transform: scale(1.25);

                box-shadow:
                    0 0 9px rgb(153, 7, 19),
                    0 0 20px rgba(182, 8, 8, 0.75);
            }
        }


        @keyframes liveRing {

            0% {

                width: 100%;
                height: 100%;

                opacity: .9;
            }

            100% {

                width: 300%;
                height: 300%;

                opacity: 0;
            }
        }

        @keyframes offlineRing {

            0% {

                width: 100%;
                height: 100%;

                opacity: .9;
            }

            100% {

                width: 300%;
                height: 300%;

                opacity: 0;
            }
        }


        /* =========================================================
   GRID
========================================================= */

        .currency-grid {

            display: grid;

            grid-template-columns:
                repeat(auto-fit,
                    minmax(350px, 1fr));

            gap: 25px;
        }


        /* =========================================================
   CURRENCY CARD
========================================================= */

        .currency-card {

            position: relative;

            overflow: hidden;

            padding: 25px;

            border-radius: 30px;

            background:
                rgba(255, 255, 255, .88);

            backdrop-filter:
                blur(20px);

            border:
                1px solid rgba(255, 255, 255, .95);

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .09);

            transition:
                transform .3s ease,
                box-shadow .3s ease;

            animation:
                cardAppear .6s ease both;
        }


        .currency-card:hover {

            transform:
                translateY(-8px);

            box-shadow:
                0 30px 65px rgba(0, 0, 0, .14);
        }


        @keyframes cardAppear {

            from {

                opacity: 0;

                transform:
                    translateY(25px) scale(.97);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0) scale(1);
            }
        }


        /* =========================================================
   CARD TOP
========================================================= */

        .card-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 23px;
        }


        .currency-logo {

            width: 55px;
            height: 55px;

            border-radius: 17px;

            display: flex;

            align-items: center;
            justify-content: center;

            color: white;

            font-size: 28px;

            font-weight: bold;

            background:
                linear-gradient(135deg,
                    #059669,
                    #10b981);

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .25);
        }


        .status-badge {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 8px 13px;

            border-radius: 20px;

            background:
                #ecfdf5;

            color:
                #047857;

            font-size: 11px;

            font-weight: 800;
        }

        .status-badgef {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 8px 13px;

            border-radius: 20px;

            background:
                #ecfdf5;

            color:
                #e10606;

            font-size: 11px;

            font-weight: 800;
        }


        /* =========================================================
   AMOUNT
========================================================= */

        .amount-section {

            margin-bottom: 22px;
        }


        .amount {

            font-size: 42px;

            font-weight: 850;

            color: #022c22;
        }


        .amount .rupee {

            color: #059669;

            font-size: 29px;

            vertical-align: 5px;
        }


        .amount-label {

            color: #94a3b8;

            font-size: 12px;

            margin-top: 3px;
        }


        /* =========================================================
   SERIAL
========================================================= */

        .serial-box {

            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 15px;

            background:
                linear-gradient(135deg,
                    #f0fdf4,
                    #ecfdf5);

            border:
                1px dashed #86efac;
        }


        .serial-label {

            display: block;

            color: #64748b;

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 5px;
        }


        .serial-value {

            font-family:
                "Courier New",
                monospace;

            color: #047857;

            font-size: 13px;

            font-weight: bold;

            word-break: break-all;
        }


        /* =========================================================
   QR LOCKED AREA
========================================================= */

        .qr-section {

            position: relative;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            min-height: 190px;

            margin-bottom: 20px;

            border-radius: 22px;

            background:
                linear-gradient(135deg,
                    #f8fafc,
                    #ecfdf5);

            border:
                1px solid #d1fae5;

            overflow: hidden;
        }


        /* QR hidden before PIN */

        .qr-lock {

            display: flex;

            flex-direction: column;

            align-items: center;

            text-align: center;

            padding: 20px;
        }


        .lock-icon {

            width: 55px;
            height: 55px;

            border-radius: 17px;

            display: flex;

            align-items: center;
            justify-content: center;

            background:
                #dcfce7;

            color:
                #059669;

            font-size: 25px;

            margin-bottom: 12px;
        }


        .qr-lock strong {

            color: #064e3b;

            font-size: 14px;
        }


        .qr-lock span {

            color: #94a3b8;

            font-size: 11px;

            margin-top: 5px;
        }


        /* =========================================================
   SHOW QR BUTTON
========================================================= */

        .show-qr-btn {

            margin-top: 15px;

            border: none;

            outline: none;

            cursor: pointer;

            padding: 11px 18px;

            border-radius: 13px;

            color: white;

            font-size: 12px;

            font-weight: 750;

            background:
                linear-gradient(135deg,
                    #047857,
                    #10b981);

            box-shadow:
                0 8px 18px rgba(5, 150, 105, .25);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }


        .show-qr-btn:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 12px 25px rgba(5, 150, 105, .35);
        }


        /* =========================================================
   UNLOCKED QR
========================================================= */

        .qr-unlocked {

            display: flex;

            flex-direction: column;

            align-items: center;

            padding: 15px;
        }


        .qr-code {

            width: 150px;
            height: 150px;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 8px;

            background: white;

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, .12);
        }


        .qr-title {

            margin-top: 10px;

            color: #047857;

            font-size: 11px;

            font-weight: 750;
        }


        /* =========================================================
   DETAILS
========================================================= */

        .details {

            border-top:
                1px solid #e2e8f0;

            padding-top: 15px;
        }


        .detail-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding: 7px 0;
        }


        .detail-label {

            color: #94a3b8;

            font-size: 11px;
        }


        .detail-value {

            color: #334155;

            font-size: 12px;

            font-weight: 650;

            text-align: right;

            word-break: break-all;
        }


        .mode {

            display: inline-flex;

            padding: 5px 9px;

            border-radius: 9px;

            background: #f0fdf4;

            color: #15803d;

            font-size: 10px;

            font-weight: 800;
        }


        /* =========================================================
   PIN MODAL
========================================================= */

        .pin-modal {

            position: fixed;

            inset: 0;

            z-index: 9999;

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background:
                rgba(2, 44, 34, .55);

            backdrop-filter:
                blur(10px);
        }


        .pin-modal.active {

            display: flex;
        }


        .pin-box {

            width: 100%;

            max-width: 380px;

            padding: 30px;

            border-radius: 28px;

            background:
                rgba(255, 255, 255, .96);

            box-shadow:
                0 30px 80px rgba(0, 0, 0, .25);

            animation:
                modalShow .25s ease;
        }


        @keyframes modalShow {

            from {

                opacity: 0;

                transform:
                    scale(.9) translateY(15px);
            }

            to {

                opacity: 1;

                transform:
                    scale(1) translateY(0);
            }
        }


        .pin-icon {

            width: 60px;
            height: 60px;

            margin:
                0 auto 17px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 19px;

            background:
                #dcfce7;

            color:
                #059669;

            font-size: 27px;
        }


        .pin-box h2 {

            text-align: center;

            color: #064e3b;

            font-size: 22px;

            margin-bottom: 7px;
        }


        .pin-box p {

            text-align: center;

            color: #64748b;

            font-size: 12px;

            margin-bottom: 22px;
        }


        .pin-input {

            width: 100%;

            height: 52px;

            border: 1px solid #d1d5db;

            border-radius: 14px;

            outline: none;

            text-align: center;

            letter-spacing: 8px;

            font-size: 20px;

            font-weight: bold;

            color: #064e3b;

            transition:
                border .2s ease,
                box-shadow .2s ease;
        }


        .pin-input:focus {

            border-color:
                #10b981;

            box-shadow:
                0 0 0 4px rgba(16, 185, 129, .12);
        }


        .pin-actions {

            display: flex;

            gap: 10px;

            margin-top: 17px;
        }


        .pin-cancel {

            flex: 1;

            border: none;

            cursor: pointer;

            border-radius: 13px;

            padding: 12px;

            background: #f1f5f9;

            color: #475569;

            font-weight: 700;
        }


        .pin-submit {

            flex: 1;

            border: none;

            cursor: pointer;

            border-radius: 13px;

            padding: 12px;

            background:
                linear-gradient(135deg,
                    #047857,
                    #10b981);

            color: white;

            font-weight: 750;
        }


        .pin-error {

            margin-top: 12px;

            padding: 10px;

            border-radius: 10px;

            background: #fef2f2;

            color: #dc2626;

            text-align: center;

            font-size: 12px;
        }


        /* =========================================================
   EMPTY
========================================================= */

        .empty-box {

            padding: 80px 25px;

            text-align: center;

            border-radius: 30px;

            background:
                rgba(255, 255, 255, .8);

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .08);
        }


        .empty-icon {

            width: 85px;
            height: 85px;

            margin:
                0 auto 20px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 25px;

            background: #dcfce7;

            color: #059669;

            font-size: 42px;
        }


        .empty-box h2 {

            color: #064e3b;

            margin-bottom: 8px;
        }


        .empty-box p {

            color: #64748b;

            font-size: 13px;
        }


        /* =========================================================
   MOBILE
========================================================= */

        @media (max-width: 800px) {

            .hero-header {

                flex-direction: column;

                align-items: flex-start;
            }

            .summary {

                width: 100%;
            }

            .summary-card {

                flex: 1;
            }
        }


        @media (max-width: 550px) {

            .currency-page {

                padding:
                    20px 15px 120px;
            }

            .hero-header {

                padding: 23px;
            }

            .hero-content h1 {

                font-size: 27px;
            }

            .summary {

                flex-direction: column;
            }

            .summary-card {

                width: 100%;
            }

            .currency-grid {

                grid-template-columns: 1fr;
            }

            .currency-card {

                padding: 20px;
            }

            .amount {

                font-size: 37px;
            }
        }

        /* =========================================================
   PREMIUM QR DISPLAY MODAL
========================================================= */

        .qr-display-modal {
            position: fixed;
            inset: 0;
            z-index: 99999;

            display: none;
            align-items: center;
            justify-content: center;

            padding: 18px;

            background:
                radial-gradient(circle at 50% 20%,
                    rgba(16, 185, 129, .18),
                    transparent 35%),
                rgba(2, 44, 34, .78);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            animation: qrOverlayIn .25s ease;
        }

        .qr-display-modal.active {
            display: flex !important;
        }

        /* Main modal */

        .qr-display-box {
            position: relative;

            width: min(440px, 100%);
            max-height: calc(100vh - 36px);

            overflow-y: auto;

            padding: 24px;

            border-radius: 32px;

            background:
                linear-gradient(145deg,
                    rgba(255, 255, 255, .98),
                    rgba(240, 253, 244, .96));

            border: 1px solid rgba(255, 255, 255, .95);

            box-shadow:
                0 35px 100px rgba(0, 0, 0, .38),
                0 0 0 1px rgba(16, 185, 129, .08);

            text-align: center;

            animation: qrModalIn .35s cubic-bezier(.22, 1, .36, 1);

            scrollbar-width: none;
        }

        .qr-display-box::-webkit-scrollbar {
            display: none;
        }

        /* Decorative glow */

        .qr-display-box::before {
            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            top: -100px;
            right: -80px;

            border-radius: 50%;

            background: rgba(16, 185, 129, .12);

            pointer-events: none;
        }

        .qr-display-box::after {
            content: "";

            position: absolute;

            width: 140px;
            height: 140px;

            bottom: -80px;
            left: -70px;

            border-radius: 50%;

            background: rgba(52, 211, 153, .08);

            pointer-events: none;
        }


        /* Header */

        .qr-modal-header {
            position: relative;
            z-index: 2;

            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 18px;
        }

        .qr-brand {
            display: flex;
            align-items: center;
            gap: 10px;

            color: #064e3b;

            font-size: 13px;
            font-weight: 850;
        }

        .qr-brand-icon {
            width: 36px;
            height: 36px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            color: white;

            background:
                linear-gradient(135deg,
                    #047857,
                    #10b981);

            box-shadow:
                0 7px 18px rgba(5, 150, 105, .25);

            font-size: 18px;
        }

        .qr-secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 7px 10px;

            border-radius: 20px;

            background: #ecfdf5;

            color: #047857;

            border: 1px solid #d1fae5;

            font-size: 10px;
            font-weight: 800;
        }


        /* QR Icon */

        .qr-display-icon {
            position: relative;
            z-index: 2;

            width: 66px;
            height: 66px;

            margin: 5px auto 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 21px;

            background:
                linear-gradient(135deg,
                    #dcfce7,
                    #d1fae5);

            color: #059669;

            border: 1px solid #bbf7d0;

            font-size: 29px;

            box-shadow:
                0 12px 28px rgba(5, 150, 105, .13);

            animation: qrIconFloat 3s ease-in-out infinite;
        }


        /* Heading */

        .qr-display-box h2 {
            position: relative;
            z-index: 2;

            margin: 0;

            color: #022c22;

            font-size: 25px;
            font-weight: 850;

            letter-spacing: -.5px;
        }

        .qr-display-subtitle {
            position: relative;
            z-index: 2;

            margin: 7px 0 18px;

            color: #64748b;

            font-size: 12px;
            line-height: 1.6;
        }


        /* QR Area */

        .qr-code-frame {
            position: relative;
            z-index: 2;

            width: 272px;
            height: 272px;

            margin: 0 auto 18px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 15px;

            border-radius: 27px;

            background:
                linear-gradient(145deg,
                    #ffffff,
                    #f8fafc);

            border: 1px solid #d1fae5;

            box-shadow:
                0 18px 45px rgba(2, 44, 34, .12),
                0 0 0 7px rgba(16, 185, 129, .045);
        }

        /* QR corner decorations */

        .qr-code-frame::before,
        .qr-code-frame::after {
            content: "";

            position: absolute;

            width: 32px;
            height: 32px;

            border-color: #10b981;
            border-style: solid;

            pointer-events: none;
        }

        .qr-code-frame::before {
            top: 9px;
            left: 9px;

            border-width: 3px 0 0 3px;

            border-radius: 10px 0 0 0;
        }

        .qr-code-frame::after {
            right: 9px;
            bottom: 9px;

            border-width: 0 3px 3px 0;

            border-radius: 0 0 10px 0;
        }

        #qrDisplayCode {
            width: 238px;
            height: 238px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 9px;

            background: white;

            border-radius: 16px;

            box-shadow:
                0 7px 20px rgba(0, 0, 0, .08);
        }

        #qrDisplayCode img,
        #qrDisplayCode canvas {
            display: block;

            max-width: 100%;
            max-height: 100%;
        }


        /* Amount */

        .qr-display-amount-box {
            position: relative;
            z-index: 2;

            margin: 0 auto 18px;

            padding: 12px 18px;

            width: fit-content;
            min-width: 170px;

            border-radius: 16px;

            background:
                linear-gradient(135deg,
                    #ecfdf5,
                    #d1fae5);

            border: 1px solid #bbf7d0;
        }

        .qr-display-amount-label {
            display: block;

            margin-bottom: 2px;

            color: #64748b;

            font-size: 9px;

            text-transform: uppercase;
            letter-spacing: 1px;

            font-weight: 750;
        }

        .qr-display-amount {
            margin: 0;

            color: #047857;

            font-size: 23px;
            font-weight: 900;
        }


        /* Instruction */

        .qr-scan-hint {
            position: relative;
            z-index: 2;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            margin-bottom: 17px;

            color: #64748b;

            font-size: 11px;
        }

        .qr-scan-hint span:first-child {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #10b981;

            box-shadow:
                0 0 0 4px rgba(16, 185, 129, .12);
        }


        /* Close button */

        .qr-close-btn {
            position: relative;
            z-index: 2;

            width: 100%;

            border: 0;

            border-radius: 15px;

            padding: 13px 16px;

            background:
                linear-gradient(135deg,
                    #047857,
                    #10b981);

            color: white;

            font-size: 13px;
            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 10px 25px rgba(5, 150, 105, .25);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .qr-close-btn:hover {
            transform: translateY(-2px);

            box-shadow:
                0 15px 32px rgba(5, 150, 105, .34);
        }

        .qr-close-btn:active {
            transform: translateY(0);
        }


        /* Footer */

        .qr-modal-footer {
            position: relative;
            z-index: 2;

            margin-top: 15px;

            color: #94a3b8;

            font-size: 10px;
        }

        .qr-modal-footer strong {
            color: #059669;
            font-weight: 850;
        }


        /* Animations */

        @keyframes qrOverlayIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes qrModalIn {
            from {
                opacity: 0;
                transform: translateY(25px) scale(.94);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes qrIconFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-4px);
            }
        }


        /* Mobile */

        @media (max-width: 480px) {

            .qr-display-modal {
                padding: 12px;
            }

            .qr-display-box {
                padding: 19px;

                border-radius: 27px;
            }

            .qr-modal-header {
                margin-bottom: 14px;
            }

            .qr-display-icon {
                width: 58px;
                height: 58px;

                border-radius: 18px;

                font-size: 25px;
            }

            .qr-display-box h2 {
                font-size: 22px;
            }

            .qr-code-frame {
                width: 245px;
                height: 245px;

                padding: 12px;

                border-radius: 23px;
            }

            #qrDisplayCode {
                width: 218px;
                height: 218px;
            }

            .qr-display-amount {
                font-size: 21px;
            }
        }

        body.modal-open {
            overflow: hidden;
        }
    </style>

</head>


<body>


    <?php require 'navbar.php'; ?>


    <main class="currency-page">

        <?php

        if ($message != "") {

            echo "

<div class='message'>
$message
</div>

";
        }

        ?>
        <?php

        if ($message1 != "") {

            echo "

<div class='message1'>
$message1
</div>

";
        }

        ?>
        <!-- =====================================================
     HEADER
====================================================== -->

        <section class="hero-header">


            <div class="hero-content">

                <div class="title-row">

                    <div class="title-icon">
                        ₹
                    </div>

                    <div>

                        <h1>
                            Generated Currency
                        </h1>

                        <p>
                            Secure digital currency generated through MBD Pay
                        </p>

                    </div>

                </div>

            </div>


            <div class="summary">


                <div class="summary-card">

                    <span class="summary-label">
                        Currency
                    </span>

                    <span class="summary-value">
                        <?php echo $total_currency; ?>
                    </span>

                </div>


                <div class="summary-card">

                    <span class="summary-label">
                        Total Value
                    </span>

                    <span class="summary-value">

                        ₹<?php
                            echo number_format(
                                $total_value,
                                2
                            );
                            ?>

                    </span>

                </div>


            </div>


        </section>

        <?php

        if (!$serverConnected) {

            echo "

<div class='message1'>
These currencies are generated online but stored in cache memory(use for offline currency).
</div>

";
        }

        ?>

        <!-- =====================================================
     CURRENCY CARDS
====================================================== -->

        <?php if ($total_currency > 0): ?>


            <div class="currency-grid">


                <?php foreach (
                    $currency_rows
                    as $currency
                ): ?>


                    <article class="currency-card">


                        <!-- CARD HEADER -->

                        <div class="card-top">


                            <div class="currency-logo">
                                ₹
                            </div>


                            <?php if ($serverConnected) { ?>
                                <div class="status-badge">
                                    <span class="live-dot"></span>

                                    GENERATED
                                <?php } else { ?>
                                    <div class="status-badgef">
                                        <span class="offline-dot"></span>

                                        GENERATED
                                    <?php } ?>
                                    </div>


                                </div>


                                <!-- AMOUNT -->

                                <div class="amount-section">

                                    <div class="amount">

                                        <span class="rupee">
                                            ₹
                                        </span>

                                        <?php
                                        echo number_format(
                                            decryptData($currency['amount']),
                                            2
                                        );
                                        ?>

                                    </div>


                                    <div class="amount-label">

                                        MBD Pay Digital Currency

                                    </div>

                                </div>


                                <!-- SERIAL -->

                                <div class="serial-box">

                                    <span class="serial-label">
                                        Currency Serial Number
                                    </span>

                                    <span class="serial-value">

                                        <?php if ($serverConnected) {
                                            echo htmlspecialchars(
                                                $currency['serial_no']
                                            );
                                        } else {
                                            echo htmlspecialchars(
                                                decryptData($currency['serial_no'])
                                            );
                                        }
                                        ?>

                                    </span>

                                </div>


                                <!-- =================================================
         QR AREA
    ================================================== -->

                                <div class="qr-section">

                                    <div class="qr-lock">
                                        <div class="lock-icon">🔒</div>

                                        <strong>QR Code Locked</strong>

                                        <span>
                                            Enter your PIN to display this currency QR
                                        </span>

                                        <button
                                            type="button"
                                            class="show-qr-btn"
                                            data-pin-mode="<?php echo $serverConnected ? 'online' : 'offline'; ?>"
                                            data-pin-value="<?php
                                                            if ($serverConnected) {
                                                                echo htmlspecialchars(
                                                                    (string)$currency['id'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                            } else {
                                                                echo htmlspecialchars(
                                                                    (string)decryptData($currency['serial_no']),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                            }
                                                            ?>">
                                            🔐 Show QR
                                        </button>
                                    </div>

                                </div>

                                <!-- =================================================
         DETAILS
    ================================================== -->

                                <div class="details">


                                    <div class="detail-row">

                                        <span class="detail-label">
                                            Sender
                                        </span>

                                        <span class="detail-value">

                                            <?php if ($serverConnected) {
                                                echo htmlspecialchars(
                                                    $currency['sender_mobile']
                                                );
                                            } else {
                                                echo htmlspecialchars(
                                                    decryptData($currency['sender_mobile'])
                                                );
                                            }
                                            ?>

                                        </span>

                                    </div>


                                    <div class="detail-row">

                                        <span class="detail-label">
                                            Receiver
                                        </span>

                                        <span class="detail-value">

                                            <?php

                                            if ($serverConnected) {
                                                if (
                                                    !empty($currency['receiver_mobile'])
                                                ) {

                                                    echo htmlspecialchars(
                                                        $currency['receiver_mobile']
                                                    );
                                                } else {

                                                    echo "Not scanned";
                                                }
                                            } else {
                                                if (
                                                    !empty(decryptData($currency['receiver_mobile']))
                                                ) {

                                                    echo htmlspecialchars(
                                                        decryptData($currency['receiver_mobile'])
                                                    );
                                                } else {

                                                    echo "Not scanned";
                                                }
                                            }
                                            ?>

                                        </span>

                                    </div>

                                    <div class="detail-row">

                                        <span class="detail-label">
                                            Status
                                        </span>

                                        <span class="detail-value">

                                            <?php
                                            if ($serverConnected) {
                                                echo htmlspecialchars(
                                                    $currency['status']
                                                );
                                            } else {
                                                echo htmlspecialchars(
                                                    decryptData($currency['currency_status'])
                                                );
                                            }
                                            ?>

                                        </span>

                                    </div>


                                    <div class="detail-row">

                                        <span class="detail-label">
                                            Created Date
                                        </span>

                                        <span class="detail-value">

                                            <?php
                                            echo date(
                                                'd M Y, h:i A',
                                                strtotime(
                                                    $currency['generated_at']
                                                )
                                            );
                                            ?>

                                        </span>

                                    </div>


                                </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <!-- EMPTY STATE -->

            <div class="empty-box">

                <div class="empty-icon">
                    ₹
                </div>

                <h2>
                    No Currency Generated
                </h2>

                <p>
                    Your MBD Pay system currently has
                    no active generated currency.
                </p>

            </div>


        <?php endif; ?>


    </main>


    <!-- =====================================================
     PIN MODAL
====================================================== -->

    <div
        class="pin-modal"
        id="pinModal">


        <div class="pin-box">


            <div class="pin-icon">
                🔐
            </div>


            <h2>
                Unlock Currency
            </h2>


            <p>
                Enter your MBD Pay PIN to display
                the QR code.
            </p>


            <form
                method="POST"
                autocomplete="off">


                <input
                    type="hidden"
                    name="action"
                    value="verify_pin">

                <input
                    type="hidden"
                    name="currency_id"
                    id="currencyId"
                    value="">

                <input
                    type="hidden"
                    name="serialNo"
                    id="serialNo"
                    value="">

                <input
                    type="password"
                    name="pin"
                    class="pin-input"
                    maxlength="4"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    placeholder="••••"
                    required
                    autofocus>


                <div class="pin-actions">


                    <button
                        type="button"
                        class="pin-cancel"
                        onclick="closePinModal()">
                        Cancel
                    </button>

                    <?php if ($serverConnected) { ?>
                        <button
                            type="submit"
                            name="online"
                            class="pin-submit">
                            Unlock QR
                        </button>

                    <?php } else { ?>
                        <button
                            type="submit"
                            name="offline"
                            class="pin-submit">
                            Unlock QR
                        </button>
                    <?php } ?>
                </div>


            </form>


            <?php if ($pin_error !== ""): ?>


                <div class="pin-error">

                    <?php
                    echo htmlspecialchars(
                        $pin_error
                    );
                    ?>

                </div>


            <?php endif; ?>


        </div>


    </div>



    <?php
    /*
    |--------------------------------------------------------------------------
    | QR MODAL DATA
    |--------------------------------------------------------------------------
    */
    $verifiedQrData = "";
    $verifiedQrAmount = "";

    if ($unlocked_currency !== null) {

        if ($serverConnected) {
            $verifiedQrData = $unlocked_currency['encrypted_serial'] ?? "";
            $currency_serial_no = $unlocked_currency['serial_no'] ?? "";
        } else {
            $verifiedQrData = $unlocked_currency['currency_serial_no'] ?? "";
            $currency_serial_no = $unlocked_currency['serial_no'] ?? "";
        }

        if (!empty($unlocked_currency['amount'])) {
            $verifiedQrAmount = decryptData($unlocked_currency['amount']);
        }
    }
    ?>

    <?php if ($verifiedQrData !== ""): ?>

        <div class="qr-display-modal active" id="qrDisplayModal">

            <div class="qr-display-box">

                <!-- HEADER -->
                <div class="qr-modal-header">

                    <div class="qr-brand">

                        <div class="qr-brand-icon">
                            ₹
                        </div>

                        MBD PAY

                    </div>

                    <div class="qr-secure-badge">
                        🔒 SECURE
                    </div>

                </div>


                <!-- ICON -->
                <div class="qr-display-icon">
                    ▦
                </div>


                <!-- TITLE -->
                <h2>
                    Receive Money
                </h2>
                <br>
                <b><?php echo "Serial No: " . $currency_serial_no; ?></b>
                <br>
                <div class="qr-display-subtitle">
                    Scan this QR code using your MBD payment app
                    to receive the digital currency.
                </div>


                <!-- QR -->
                <div class="qr-code-frame">

                    <div id="qrDisplayCode"></div>

                </div>


                <!-- AMOUNT -->
                <?php if ($verifiedQrAmount !== ""): ?>

                    <div class="qr-display-amount-box">

                        <span class="qr-display-amount-label">
                            Amount
                        </span>

                        <div class="qr-display-amount">
                            ₹<?php echo number_format(
                                    (float)$verifiedQrAmount,
                                    2
                                ); ?>
                        </div>

                    </div>

                <?php endif; ?>

                <!-- CLOSE -->
                <button
                    type="button"
                    class="qr-close-btn"
                    onclick="closeQrModal()">

                    Close

                </button>

            </div>

        </div>

    <?php endif; ?>


    <?php require 'footer.php'; ?>


    <script>
        /*
        |--------------------------------------------------------------------------
        | PIN MODAL - ONLINE + OFFLINE
        |--------------------------------------------------------------------------
        */

        function openPinModal(button) {

            const modal = document.getElementById("pinModal");
            const currencyId = document.getElementById("currencyId");
            const serialNo = document.getElementById("serialNo");
            const pinInput = document.querySelector("#pinModal .pin-input");

            if (!modal) {
                console.error("MBD Pay: #pinModal was not found.");
                return;
            }

            if (!button) {
                console.error("MBD Pay: Show QR button was not supplied.");
                return;
            }

            const mode = button.getAttribute("data-pin-mode");
            const value = button.getAttribute("data-pin-value") || "";

            if (currencyId) {
                currencyId.value = "";
            }

            if (serialNo) {
                serialNo.value = "";
            }

            if (mode === "online") {

                if (!currencyId) {
                    console.error("MBD Pay: #currencyId was not found.");
                    return;
                }

                currencyId.value = value;

            } else {

                if (!serialNo) {
                    console.error("MBD Pay: #serialNo was not found.");
                    return;
                }

                serialNo.value = value;
            }

            if (pinInput) {
                pinInput.value = "";
            }

            modal.classList.add("active");
            document.body.classList.add("modal-open");

            setTimeout(function() {
                if (pinInput) {
                    pinInput.focus();
                }
            }, 100);
        }


        function closePinModal() {

            const modal = document.getElementById("pinModal");

            if (modal) {
                modal.classList.remove("active");
            }

            document.body.classList.remove("modal-open");
        }


        document.addEventListener("DOMContentLoaded", function() {

            /*
            | Attach click handlers WITHOUT inline JavaScript.
            | This fixes offline serial values containing quotes/special
            | characters and guarantees the same flow online/offline.
            */
            document.querySelectorAll(".show-qr-btn").forEach(function(button) {

                button.addEventListener("click", function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    openPinModal(this);
                });

            });


            const pinModal = document.getElementById("pinModal");

            if (pinModal) {

                pinModal.addEventListener("click", function(event) {

                    if (event.target === pinModal) {
                        closePinModal();
                    }

                });

            }


            /*
            | Re-open after an incorrect PIN.
            */
            <?php if ($pin_error !== ""): ?>
                if (pinModal) {
                    pinModal.classList.add("active");
                    document.body.classList.add("modal-open");

                    const input = pinModal.querySelector(".pin-input");

                    if (input) {
                        setTimeout(function() {
                            input.focus();
                        }, 100);
                    }
                }
            <?php endif; ?>

            // set currency data for qr
            <?php

            $qrPayload = [
                "encrypted_currency_serial_no" =>  $verifiedQrData ?? "",
                "currency_serial_no"          =>  $currency_serial_no ?? ""
            ];

            $qrData = json_encode($qrPayload, JSON_UNESCAPED_SLASHES);

            ?>


            // QR DISPLAY MODAL


            const qrModal = document.getElementById("qrDisplayModal");

            if (qrModal) {

                const qrTarget = document.getElementById("qrDisplayCode");

                if (qrTarget && typeof QRCode !== "undefined") {

                    // Clear previous QR
                    qrTarget.innerHTML = "";

                    // QR payload generated by PHP
                    const qrData = <?php echo json_encode($qrData ?? ""); ?>;

                    console.log("MBD Pay QR Data:", qrData);

                    if (qrData !== "") {

                        new QRCode(qrTarget, {
                            text: qrData,
                            width: 210,
                            height: 210,
                            colorDark: "#022c22",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });

                    } else {

                        console.error("MBD Pay: QR data is empty.");

                    }

                } else if (qrTarget) {

                    console.error("MBD Pay: QRCode library was not loaded.");

                }

                qrModal.addEventListener("click", function(event) {

                    if (event.target === qrModal) {
                        closeQrModal();
                    }

                });

            }

        });


        function closeQrModal() {

            const modal = document.getElementById("qrDisplayModal");

            if (modal) {
                modal.classList.remove("active");
            }
        }


        document.addEventListener("keydown", function(event) {

            if (event.key === "Escape") {

                const pinModal = document.getElementById("pinModal");

                if (pinModal && pinModal.classList.contains("active")) {
                    closePinModal();
                    return;
                }

                closeQrModal();
            }

        });
    </script>

</body>

</html>