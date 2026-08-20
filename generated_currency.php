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

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

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
                 AND wallet_id = ?
                 AND status='GENERATED'
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iss",
                $currency_id,
                $u_mob,
                $u_wallet_id
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

            max-width: 1450px;

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

            width: 64px;
            height: 64px;

            border-radius: 18px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 31px;

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

            /* Exactly 3 currency cards per row on desktop */
            grid-template-columns: repeat(3, minmax(0, 1fr));

            gap: 25px;
        }


        /*     
   CURRENCY CARD

  INDIAN-CURRENCY-INSPIRED DIGITAL NOTE CARD
   
*/

        .currency-card {
            --note-main: #2f7d55;
            --note-dark: #17553a;
            --note-light: #dcefe3;
            --note-paper: #f4f8ed;

            position: relative;
            overflow: hidden;
            min-height: 500px;
            padding: 0;
            border-radius: 18px;
            background:
                radial-gradient(circle at 82% 20%, rgba(255, 255, 255, .78) 0 8%, transparent 9%),
                radial-gradient(circle at 18% 78%, rgba(255, 255, 255, .45) 0 7%, transparent 8%),
                linear-gradient(135deg, #d8eadc 0%, #f8f5df 48%, #cfe7d5 100%);
            border: 1px solid rgba(23, 85, 58, .28);
            box-shadow:
                0 18px 42px rgba(23, 85, 58, .15),
                inset 0 0 0 3px rgba(255, 255, 255, .42);
            transition: transform .3s ease, box-shadow .3s ease;
            animation: cardAppear .6s ease both;
        }

        /* =========================================================
           DENOMINATION COLORS — INDIAN BANKNOTE INSPIRED
           ========================================================= */

        .currency-card.note-10 {
            --note-main: #8a6248;
            --note-dark: #563c2d;
            --note-light: #ead9cb;
            --note-paper: #f5eee8;
            background: linear-gradient(135deg, #ead9cb, #f8f1e8 48%, #d9c2b2);
        }

        .currency-card.note-20 {
            --note-main: #6d8b4e;
            --note-dark: #3f5b2d;
            --note-light: #dce9cc;
            --note-paper: #f4f7e9;
            background: linear-gradient(135deg, #dce9cc, #f7f4df 48%, #c9dbb6);
        }

        .currency-card.note-50 {
            --note-main: #4f719b;
            --note-dark: #294c75;
            --note-light: #d6e1ef;
            --note-paper: #eff4fa;
            background: linear-gradient(135deg, #d6e1ef, #f4f1e7 48%, #c5d6e9);
        }

        .currency-card.note-100 {
            --note-main: #7866a8;
            --note-dark: #4d3d79;
            --note-light: #e1dcef;
            --note-paper: #f4f1fa;
            background: linear-gradient(135deg, #e1dcef, #f7f0e7 48%, #d4cbe6);
        }

        .currency-card.note-200 {
            --note-main: #c99b28;
            --note-dark: #7d5c0c;
            --note-light: #f2e5b8;
            --note-paper: #fff8df;
            background: linear-gradient(135deg, #f2e5b8, #fff7df 48%, #e8d18c);
        }

        .currency-card.note-500 {
            --note-main: #69706a;
            --note-dark: #3e4741;
            --note-light: #dfe3df;
            --note-paper: #f1f3ef;
            background: linear-gradient(135deg, #dfe3df, #f5f3e8 48%, #cdd2cd);
        }

        /* Apply the selected denomination color throughout the note. */
        .currency-card.note-10 .currency-logo,
        .currency-card.note-20 .currency-logo,
        .currency-card.note-50 .currency-logo,
        .currency-card.note-100 .currency-logo,
        .currency-card.note-200 .currency-logo,
        .currency-card.note-500 .currency-logo,
        .currency-card.note-2000 .currency-logo {
            color: var(--note-dark);
            border-color: var(--note-main);
            background:
                radial-gradient(circle, var(--note-paper) 0 43%, transparent 44%),
                repeating-radial-gradient(circle, var(--note-main) 0 2px, transparent 3px 5px);
        }

        .currency-card.note-10 .card-top,
        .currency-card.note-20 .card-top,
        .currency-card.note-50 .card-top,
        .currency-card.note-100 .card-top,
        .currency-card.note-200 .card-top,
        .currency-card.note-500 .card-top,
        .currency-card.note-2000 .card-top {
            background: linear-gradient(90deg,
                    rgba(255, 255, 255, .48),
                    color-mix(in srgb, var(--note-light) 70%, transparent),
                    rgba(255, 255, 255, .48));
            border-bottom-color: color-mix(in srgb, var(--note-main) 35%, transparent);
        }

        .currency-card.note-10 .card-top::before,
        .currency-card.note-20 .card-top::before,
        .currency-card.note-50 .card-top::before,
        .currency-card.note-100 .card-top::before,
        .currency-card.note-200 .card-top::before,
        .currency-card.note-500 .card-top::before,
        .currency-card.note-2000 .card-top::before {
            color: var(--note-dark);
        }

        .currency-card.note-10 .card-top::after,
        .currency-card.note-20 .card-top::after,
        .currency-card.note-50 .card-top::after,
        .currency-card.note-100 .card-top::after,
        .currency-card.note-200 .card-top::after,
        .currency-card.note-500 .card-top::after,
        .currency-card.note-2000 .card-top::after {
            color: color-mix(in srgb, var(--note-dark) 72%, transparent);
        }

        .currency-card.note-10 .amount,
        .currency-card.note-20 .amount,
        .currency-card.note-50 .amount,
        .currency-card.note-100 .amount,
        .currency-card.note-200 .amount,
        .currency-card.note-500 .amount,
        .currency-card.note-2000 .amount,
        .currency-card.note-10 .serial-value,
        .currency-card.note-20 .serial-value,
        .currency-card.note-50 .serial-value,
        .currency-card.note-100 .serial-value,
        .currency-card.note-200 .serial-value,
        .currency-card.note-500 .serial-value,
        .currency-card.note-2000 .serial-value,
        .currency-card.note-10 .qr-lock strong,
        .currency-card.note-20 .qr-lock strong,
        .currency-card.note-50 .qr-lock strong,
        .currency-card.note-100 .qr-lock strong,
        .currency-card.note-200 .qr-lock strong,
        .currency-card.note-500 .qr-lock strong,
        .currency-card.note-2000 .qr-lock strong {
            color: var(--note-dark);
        }

        .currency-card.note-10 .amount .rupee,
        .currency-card.note-20 .amount .rupee,
        .currency-card.note-50 .amount .rupee,
        .currency-card.note-100 .amount .rupee,
        .currency-card.note-200 .amount .rupee,
        .currency-card.note-500 .amount .rupee,
        .currency-card.note-2000 .amount .rupee {
            color: var(--note-main);
        }

        .currency-card.note-10 .serial-box,
        .currency-card.note-20 .serial-box,
        .currency-card.note-50 .serial-box,
        .currency-card.note-100 .serial-box,
        .currency-card.note-200 .serial-box,
        .currency-card.note-500 .serial-box,
        .currency-card.note-2000 .serial-box,
        .currency-card.note-10 .qr-section,
        .currency-card.note-20 .qr-section,
        .currency-card.note-50 .qr-section,
        .currency-card.note-100 .qr-section,
        .currency-card.note-200 .qr-section,
        .currency-card.note-500 .qr-section,
        .currency-card.note-2000 .qr-section {
            border-color: color-mix(in srgb, var(--note-main) 32%, transparent);
        }

        .currency-card.note-10 .note-denomination-number,
        .currency-card.note-20 .note-denomination-number,
        .currency-card.note-50 .note-denomination-number,
        .currency-card.note-100 .note-denomination-number,
        .currency-card.note-200 .note-denomination-number,
        .currency-card.note-500 .note-denomination-number,
        .currency-card.note-2000 .note-denomination-number {
            color: var(--note-dark);
            border-color: color-mix(in srgb, var(--note-main) 60%, transparent);
            background: color-mix(in srgb, var(--note-paper) 75%, transparent);
        }

        .currency-card.note-10 .lock-icon,
        .currency-card.note-20 .lock-icon,
        .currency-card.note-50 .lock-icon,
        .currency-card.note-100 .lock-icon,
        .currency-card.note-200 .lock-icon,
        .currency-card.note-500 .lock-icon,
        .currency-card.note-2000 .lock-icon {
            color: var(--note-dark);
            background: var(--note-light);
            border-color: color-mix(in srgb, var(--note-main) 35%, transparent);
        }

        .currency-card.note-10 .show-qr-btn,
        .currency-card.note-20 .show-qr-btn,
        .currency-card.note-50 .show-qr-btn,
        .currency-card.note-100 .show-qr-btn,
        .currency-card.note-200 .show-qr-btn,
        .currency-card.note-500 .show-qr-btn,
        .currency-card.note-2000 .show-qr-btn {
            border-color: var(--note-dark);
            background: linear-gradient(135deg, var(--note-main), var(--note-dark));
        }

        .currency-card.note-10::before,
        .currency-card.note-20::before,
        .currency-card.note-50::before,
        .currency-card.note-100::before,
        .currency-card.note-200::before,
        .currency-card.note-500::before,
        .currency-card.note-2000::before {
            border-color: color-mix(in srgb, var(--note-dark) 28%, transparent);
        }

        .currency-card.note-10::after,
        .currency-card.note-20::after,
        .currency-card.note-50::after,
        .currency-card.note-100::after,
        .currency-card.note-200::after,
        .currency-card.note-500::after,
        .currency-card.note-2000::after {
            color: color-mix(in srgb, var(--note-dark) 10%, transparent);
        }

        /* fine engraved-style pattern */
        .currency-card::before {
            content: "";
            position: absolute;
            inset: 8px;
            border: 1px solid rgba(23, 85, 58, .28);
            border-radius: 12px;
            pointer-events: none;
            background:
                repeating-linear-gradient(0deg,
                    transparent 0 5px,
                    rgba(23, 85, 58, .035) 6px 7px);
        }

        /* denomination watermark */
        .currency-card::after {
            content: attr(data-denomination);
            position: absolute;
            right: 18px;
            bottom: 55px;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 118px;
            line-height: .8;
            font-weight: 900;
            color: rgba(23, 85, 58, .075);
            transform: rotate(-7deg);
            pointer-events: none;
            user-select: none;
        }

        .currency-card:hover {
            transform: translateY(-7px);
            box-shadow:
                0 28px 58px rgba(23, 85, 58, .22),
                inset 0 0 0 3px rgba(255, 255, 255, .5);
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(25px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* NOTE HEADER */
        .card-top {
            position: relative;
            z-index: 3;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            min-height: 96px;
            margin: 0;
            padding: 24px 28px 16px;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, .42), rgba(184, 220, 197, .32), rgba(255, 255, 255, .42));
            border-bottom: 1px solid rgba(23, 85, 58, .22);
        }

        .currency-logo {
            width: 58px;
            height: 58px;
            flex: 0 0 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #17553a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 29px;
            font-weight: 900;
            background:
                radial-gradient(circle, #f7f1d5 0 43%, transparent 44%),
                repeating-radial-gradient(circle, #2f7d55 0 2px, transparent 3px 5px);
            border: 2px solid #2f7d55;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, .45);
        }

        .card-top::before {
            content: "MBD PAY • DIGITAL NOTE";
            position: absolute;
            left: 102px;
            top: 25px;
            color: #17553a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 10px;
            letter-spacing: 1.5px;
            font-weight: 800;
        }

        .card-top::after {
            content: "SECURE • VERIFIED";
            position: absolute;
            left: 102px;
            top: 43px;
            color: rgba(23, 85, 58, .72);
            font-size: 8px;
            letter-spacing: 1px;
            font-weight: 800;
        }

        .status-badge,
        .status-badgef {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 4px;
            background: rgba(247, 241, 213, .72);
            color: #17553a;
            border: 1px solid rgba(47, 125, 85, .3);
            font-size: 9px;
            letter-spacing: .8px;
            font-weight: 900;
        }

        .status-badgef {
            color: #8b2e27;
            border-color: rgba(139, 46, 39, .25);
        }

        /* DENOMINATION */
        .amount-section {
            position: relative;
            z-index: 3;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 14px;
            margin: 0;
            padding: 22px 28px 14px;
        }

        .amount {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 50px;
            line-height: 1;
            font-weight: 900;
            color: #17553a;
            text-shadow: 0 1px 0 rgba(255, 255, 255, .8);
        }

        .amount .rupee {
            color: #2f7d55;
            font-size: 27px;
            vertical-align: 7px;
            margin-right: 2px;
        }

        .amount-label {
            margin-top: 5px;
            color: #496e58;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 10px;
            letter-spacing: .7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Decorative denomination number */
        .note-denomination-number {
            position: relative;
            width: 78px;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #17553a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 23px;
            font-weight: 900;
            border: 2px solid rgba(47, 125, 85, .55);
            border-radius: 50%;
            background: rgba(247, 241, 213, .56);
            box-shadow: inset 0 0 0 5px rgba(255, 255, 255, .32);
        }

        .note-denomination-number::after {
            content: "";
            position: absolute;
            inset: 7px;
            border: 1px dashed rgba(47, 125, 85, .55);
            border-radius: 50%;
        }

        /* SECURITY STRIP */
        .serial-box {
            position: relative;
            z-index: 3;
            margin: 0 28px 16px;
            padding: 12px 15px 11px;
            border-radius: 5px;
            background:
                linear-gradient(90deg,
                    rgba(220, 239, 227, .92),
                    rgba(247, 241, 213, .88),
                    rgba(220, 239, 227, .92));
            border: 1px solid rgba(47, 125, 85, .34);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8);
        }

        .serial-label {
            display: block;
            color: #496e58;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            margin-bottom: 3px;
        }

        .serial-value {
            font-family: "Courier New", monospace;
            color: #17553a;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1.1px;
            word-break: break-all;
        }

        /* QR AREA */
        .qr-section {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 175px;
            margin: 0 24px 14px;
            border-radius: 8px;
            background:
                repeating-linear-gradient(135deg,
                    rgba(255, 255, 255, .36) 0 4px,
                    rgba(47, 125, 85, .035) 4px 8px),
                rgba(247, 241, 213, .55);
            border: 1px solid rgba(47, 125, 85, .27);
            overflow: hidden;
        }

        .qr-lock {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 15px;
        }

        .lock-icon {
            width: 43px;
            height: 43px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e0eee4;
            color: #17553a;
            border: 1px solid rgba(47, 125, 85, .35);
            font-size: 19px;
            margin-bottom: 8px;
        }

        .qr-lock strong {
            color: #17553a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 13px;
        }

        .qr-lock span {
            color: #607b69;
            font-size: 9px;
            margin-top: 4px;
        }

        .show-qr-btn {
            margin-top: 10px;
            border: 1px solid #17553a;
            outline: none;
            cursor: pointer;
            padding: 9px 17px;
            border-radius: 5px;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .4px;
            background: linear-gradient(135deg, #2f7d55, #17553a);
            box-shadow: 0 5px 12px rgba(23, 85, 58, .2);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .show-qr-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 18px rgba(23, 85, 58, .28);
        }

        .qr-unlocked {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
        }

        .qr-code {
            width: 145px;
            height: 145px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 7px;
            background: #fff;
            border: 4px solid #dcefe3;
            border-radius: 4px;
            box-shadow: 0 5px 18px rgba(23, 85, 58, .14);
        }

        .qr-title {
            margin-top: 7px;
            color: #17553a;
            font-size: 9px;
            font-weight: 800;
        }

        /* NOTE FOOTER */
        .details {
            position: relative;
            z-index: 3;
            margin: 0 28px;
            padding: 12px 0 17px;
            border-top: 1px solid rgba(23, 85, 58, .22);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 4px 0;
        }

        .detail-label {
            color: #557363;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 9px;
            font-weight: 700;
        }

        .detail-value {
            color: #294d38;
            font-size: 10px;
            font-weight: 750;
            text-align: right;
            word-break: break-all;
        }

        .mode {
            display: inline-flex;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(220, 239, 227, .75);
            color: #17553a;
            border: 1px solid rgba(47, 125, 85, .22);
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .5px;
        }

        /* NOTE-SIDE MICROTEXT */
        .currency-card .details::after {
            content: "MBD PAY • DIGITAL CURRENCY • NOT LEGAL TENDER";
            display: block;
            margin-top: 6px;
            color: rgba(23, 85, 58, .52);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 7px;
            letter-spacing: 1px;
            text-align: center;
        }

        @media (max-width: 550px) {
            .currency-card {
                min-height: 460px;
            }

            .card-top {
                padding-left: 18px;
                padding-right: 18px;
            }

            .amount-section {
                padding-left: 18px;
                padding-right: 18px;
            }

            .serial-box,
            .qr-section,
            .details {
                margin-left: 18px;
                margin-right: 18px;
            }

            .amount {
                font-size: 36px;
            }

            .currency-card::after {
                font-size: 90px;
            }
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

            padding: 0;

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

        /* =========================================================
           TABLET — 2 CARDS PER ROW
           ========================================================= */

        @media (max-width: 1100px) {

            .currency-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }


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
           QR MODAL — MATCHES THE CURRENCY CARD DENOMINATION
           ========================================================= */
        .qr-display-modal {
            --qr-main: #2f7d55;
            --qr-dark: #17553a;
            --qr-light: #dcefe3;
            --qr-paper: #f4f8ed;
        }

        .qr-display-modal.qr-note-10 {
            --qr-main: #8a6248;
            --qr-dark: #563c2d;
            --qr-light: #ead9cb;
            --qr-paper: #f5eee8;
        }

        .qr-display-modal.qr-note-20 {
            --qr-main: #6d8b4e;
            --qr-dark: #3f5b2d;
            --qr-light: #dce9cc;
            --qr-paper: #f4f7e9;
        }

        .qr-display-modal.qr-note-50 {
            --qr-main: #4f719b;
            --qr-dark: #294c75;
            --qr-light: #d6e1ef;
            --qr-paper: #eff4fa;
        }

        .qr-display-modal.qr-note-100 {
            --qr-main: #7866a8;
            --qr-dark: #4d3d79;
            --qr-light: #e1dcef;
            --qr-paper: #f4f1fa;
        }

        .qr-display-modal.qr-note-200 {
            --qr-main: #c99b28;
            --qr-dark: #7d5c0c;
            --qr-light: #f2e5b8;
            --qr-paper: #fff8df;
        }

        .qr-display-modal.qr-note-500 {
            --qr-main: #69706a;
            --qr-dark: #3e4741;
            --qr-light: #dfe3df;
            --qr-paper: #f1f3ef;
        }

        .qr-display-modal.qr-note-2000 {
            --qr-main: #7b5a9e;
            --qr-dark: #4e3869;
            --qr-light: #e6d9ef;
            --qr-paper: #f7f0fa;
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

            /* Neutral overlay: NEVER changes/tints the page background */
            background: rgba(15, 23, 42, .72);

            backdrop-filter: blur(10px);
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
                    color-mix(in srgb, var(--qr-paper) 96%, white),
                    var(--qr-paper));

            border: 1px solid rgba(255, 255, 255, .95);

            box-shadow:
                0 35px 100px rgba(0, 0, 0, .38),
                0 0 0 1px color-mix(in srgb, var(--qr-main) 10%, transparent);

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

            background: color-mix(in srgb, var(--qr-main) 15%, transparent);

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

            background: color-mix(in srgb, var(--qr-main) 9%, transparent);

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

            color: var(--qr-dark);

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
                    var(--qr-dark),
                    var(--qr-main));

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

            background: var(--qr-light);

            color: var(--qr-dark);

            border: 1px solid color-mix(in srgb, var(--qr-main) 25%, white);

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
                    color-mix(in srgb, var(--qr-main) 25%, white));

            color: var(--qr-main);

            border: 1px solid color-mix(in srgb, var(--qr-main) 30%, white);

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

            color: var(--qr-dark);

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

            border: 1px solid color-mix(in srgb, var(--qr-main) 25%, white);

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

            border-color: var(--qr-main);
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
                    var(--qr-light),
                    color-mix(in srgb, var(--qr-main) 25%, white));

            border: 1px solid color-mix(in srgb, var(--qr-main) 30%, white);
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

            color: var(--qr-dark);

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

            background: var(--qr-main);

            box-shadow:
                0 0 0 4px color-mix(in srgb, var(--qr-main) 15%, transparent);
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
                    var(--qr-dark),
                    var(--qr-main));

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
            color: var(--qr-main);
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
Please ensure your currencies are synchronized. Currencies generated online are securely cached on your device for offline use.
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


                    <?php
                    $displayAmount = (float) decryptData($currency['amount']);
                    $displayDenomination = number_format($displayAmount, 0, '.', '');
                    ?>
                    <?php
                    $denominationClass = 'note-default';

                    if ($displayAmount == 10) {
                        $denominationClass = 'note-10';
                    } elseif ($displayAmount == 20) {
                        $denominationClass = 'note-20';
                    } elseif ($displayAmount == 50) {
                        $denominationClass = 'note-50';
                    } elseif ($displayAmount == 100) {
                        $denominationClass = 'note-100';
                    } elseif ($displayAmount == 200) {
                        $denominationClass = 'note-200';
                    } elseif ($displayAmount == 500) {
                        $denominationClass = 'note-500';
                    }
                    ?>
                    <article class="currency-card <?php echo $denominationClass; ?>"
                        data-denomination="<?php echo htmlspecialchars($displayDenomination, ENT_QUOTES, 'UTF-8'); ?>">


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

                                        MBD Digital Currency

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
                                            Generated By
                                        </span>

                                        <span class="detail-value">

                                            <?php if ($serverConnected) {
                                                echo htmlspecialchars(
                                                    substr($currency['sender_mobile'], 0, 3) . '****' . substr($currency['sender_mobile'], -3)
                                                );
                                            } else {
                                                $sender_mob = decryptData($currency['sender_mobile']);
                                                echo htmlspecialchars(
                                                    substr($sender_mob, 0, 3) . '****' . substr($sender_mob, -3)
                                                );
                                            }
                                            ?>

                                        </span>

                                    </div>


                                    <div class="detail-row">

                                        <span class="detail-label">
                                            Receive By
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
    $qrDenominationClass = "qr-note-default";

    if ($unlocked_currency !== null) {

        if ($serverConnected) {
            $verifiedQrData = $unlocked_currency['encrypted_serial'] ?? "";
            $currency_serial_no = $unlocked_currency['serial_no'] ?? "";
        } else {
            $verifiedQrData = $unlocked_currency['currency_serial_no'] ?? "";
            $currency_serial_no = decryptData($unlocked_currency['serial_no'] ?? "");
        }

        if (!empty($unlocked_currency['amount'])) {
            $verifiedQrAmount = decryptData($unlocked_currency['amount']);
        }

        switch ((int)$verifiedQrAmount) {
            case 10:
                $qrDenominationClass = "qr-note-10";
                break;
            case 20:
                $qrDenominationClass = "qr-note-20";
                break;
            case 50:
                $qrDenominationClass = "qr-note-50";
                break;
            case 100:
                $qrDenominationClass = "qr-note-100";
                break;
            case 200:
                $qrDenominationClass = "qr-note-200";
                break;
            case 500:
                $qrDenominationClass = "qr-note-500";
                break;
            case 2000:
                $qrDenominationClass = "qr-note-2000";
                break;
        }
    }
    ?>

    <?php if ($verifiedQrData !== ""): ?>

        <div class="qr-display-modal active <?php echo $qrDenominationClass; ?>" id="qrDisplayModal">

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