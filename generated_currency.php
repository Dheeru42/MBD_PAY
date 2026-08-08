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

if (isset($_SESSION['refresh'])) {

    header("location:generated_currency.php");
    unset($_SESSION['refresh']);
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

    if (isset($_POST['online'])) {
        if (
            isset($_POST['action']) &&
            $_POST['action'] === "verify_pin"
        ) {

            $currency_id = (int)($_POST['currency_id'] ?? 0);
            $entered_pin = trim($_POST['pin'] ?? "");

            if ($currency_id <= 0 || $entered_pin == "") {

                $pin_error = "Please enter your PIN.";
            } else {

                // Get selected currency
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

                $currency_result = mysqli_stmt_get_result($stmt);

                $currency = mysqli_fetch_assoc($currency_result);

                mysqli_stmt_close($stmt);

                if (!$currency) {

                    unset($_SESSION['unlocked_currency_id']);
                    $pin_error = "Currency not found.";
                } else {

                    // Get sender PIN
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
                        $currency['sender_mobile']
                    );

                    mysqli_stmt_execute($stmt);

                    $user_result = mysqli_stmt_get_result($stmt);

                    $user = mysqli_fetch_assoc($user_result);

                    mysqli_stmt_close($stmt);

                    if (
                        $user &&
                        password_verify($entered_pin, $user['pin'])
                    ) {

                        // Unlock selected currency
                        $unlocked_currency = $currency;

                        // Keep unlocked after refresh
                        $_SESSION['unlocked_currency_id'] = $currency['id'];
                        $_SESSION['refresh'] = true;
                    } else {

                        unset($_SESSION['unlocked_currency_id']);

                        $pin_error = "Incorrect PIN. Please try again.";
                    }
                }
            }
        }
    }
} catch (Throwable $th) {
    if (isset($_POST['offline'])) {

        /*
    |--------------------------------------------------------------------------
    | OFFLINE PIN VERIFICATION
    |--------------------------------------------------------------------------
    */

        $serialNo = trim($_POST['serialNo'] ?? "");
        $entered_pin = trim($_POST['pin'] ?? "");


        /*
    |--------------------------------------------------------------------------
    | CHECK INPUT
    |--------------------------------------------------------------------------
    */

        if ($serialNo === "" || $entered_pin === "") {

            $pin_error = "Please enter your PIN.";
        } else {

            /*
        |--------------------------------------------------------------------------
        | USER CACHE
        |--------------------------------------------------------------------------
        */

            $userId = hash("sha256", $u_mob);

            $profile =
                CACHE_DIR .
                $userId .
                "/profile.json";


            /*
        |--------------------------------------------------------------------------
        | CHECK PROFILE
        |--------------------------------------------------------------------------
        */

            if (!file_exists($profile)) {

                $pin_error =
                    "Offline profile not found.";
            } else {

                /*
            |--------------------------------------------------------------------------
            | READ PROFILE
            |--------------------------------------------------------------------------
            */

                $cache = json_decode(
                    file_get_contents($profile),
                    true
                );


                /*
            |--------------------------------------------------------------------------
            | GET CACHED PIN
            |--------------------------------------------------------------------------
            */

                $cachedPin = $cache['pin'] ?? "";


                /*
            |--------------------------------------------------------------------------
            | VERIFY PIN
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
                | FIND CURRENCY FROM CACHE
                |--------------------------------------------------------------------------
                */

                    if (is_dir($currencyDir)) {

                        $files = glob(
                            $currencyDir . "*.json"
                        );


                        foreach ($files as $file) {

                            $cachedCurrency =
                                json_decode(
                                    file_get_contents($file),
                                    true
                                );


                            if (!$cachedCurrency) {
                                continue;
                            }


                            /*
                        |--------------------------------------------------------------------------
                        | DECRYPT SERIAL NUMBER
                        |--------------------------------------------------------------------------
                        |
                        | serial_no is encrypted in cache.
                        |--------------------------------------------------------------------------
                        */

                            $cachedSerial = "";

                            if (
                                !empty($cachedCurrency['serial_no'])
                            ) {

                                $cachedSerial =
                                    decryptData(
                                        $cachedCurrency['serial_no']
                                    );
                            }


                            /*
                        |--------------------------------------------------------------------------
                        | MATCH SERIAL
                        |--------------------------------------------------------------------------
                        */

                            if (
                                $cachedSerial ===
                                $serialNo
                            ) {

                                /*
                            |--------------------------------------------------------------------------
                            | DECRYPT ALL CACHE FIELDS
                            |--------------------------------------------------------------------------
                            */

                                $cachedCurrency['serial_no'] = decryptData(
                                    $cachedCurrency['serial_no']
                                );


                                if (
                                    isset(
                                        $cachedCurrency['currency_serial_no']
                                    )
                                ) {

                                    $cachedCurrency['currency_serial_no'] = decryptData(
                                        $cachedCurrency['currency_serial_no']
                                    );
                                }


                                if (
                                    isset(
                                        $cachedCurrency['amount']
                                    )
                                ) {

                                    $cachedCurrency['amount'] = decryptData(
                                        $cachedCurrency['amount']
                                    );
                                }


                                if (
                                    isset(
                                        $cachedCurrency['currency_status']
                                    )
                                ) {

                                    $cachedCurrency['currency_status'] = decryptData(
                                        $cachedCurrency['currency_status']
                                    );
                                }


                                if (
                                    isset(
                                        $cachedCurrency['receiver_mobile']
                                    )
                                ) {

                                    $cachedCurrency['receiver_mobile'] = decryptData(
                                        $cachedCurrency['receiver_mobile']
                                    );
                                }


                                if (
                                    isset(
                                        $cachedCurrency['sender_mobile']
                                    )
                                ) {

                                    $cachedCurrency['sender_mobile'] = decryptData(
                                        $cachedCurrency['sender_mobile']
                                    );
                                }


                                /*
                            |--------------------------------------------------------------------------
                            | KEEP ENCRYPTED SERIAL FOR QR
                            |--------------------------------------------------------------------------
                            |
                            | QR must contain encrypted serial.
                            |
                            */

                                if (
                                    isset(
                                        $cachedCurrency['encrypted_serial']
                                    )
                                ) {

                                    /*
                                | Already encrypted
                                */
                                    $qrSerial =
                                        $cachedCurrency['encrypted_serial'];
                                } else {

                                    /*
                                | If encrypted_serial is not separately
                                | available, use encrypted serial_no
                                | from original cache file.
                                */

                                    $originalCurrency =
                                        json_decode(
                                            file_get_contents($file),
                                            true
                                        );

                                    $qrSerial =
                                        $originalCurrency['serial_no'];
                                }


                                /*
                            |--------------------------------------------------------------------------
                            | STORE QR DATA
                            |--------------------------------------------------------------------------
                            */

                                $cachedCurrency['encrypted_serial'] = $qrSerial;


                                /*
                            |--------------------------------------------------------------------------
                            | CREATE OFFLINE ID
                            |--------------------------------------------------------------------------
                            */

                                $cachedCurrency['id'] =
                                    md5($cachedSerial);


                                /*
                            |--------------------------------------------------------------------------
                            | FOUND
                            |--------------------------------------------------------------------------
                            */

                                $foundCurrency =
                                    $cachedCurrency;


                                break;
                            }
                        }
                    }


                    /*
                |--------------------------------------------------------------------------
                | CURRENCY FOUND
                |--------------------------------------------------------------------------
                */

                    if ($foundCurrency) {

                        /*
                    |--------------------------------------------------------------------------
                    | UNLOCK SELECTED CURRENCY
                    |--------------------------------------------------------------------------
                    */

                        $unlocked_currency =
                            $foundCurrency;


                        /*
                    |--------------------------------------------------------------------------
                    | REMEMBER UNLOCKED SERIAL
                    |--------------------------------------------------------------------------
                    */

                        $_SESSION['unlocked_currency_serial'] = $serialNo;


                        /*
                    |--------------------------------------------------------------------------
                    | NO ERROR
                    |--------------------------------------------------------------------------
                    */

                        $pin_error = "";
                    } else {

                        $pin_error =
                            "Offline currency not found.";
                    }
                }
            }
        }
    }
}try {

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


                    $_SESSION[
                        'unlocked_currency_id'
                    ] =
                        $online_currency['id'];


                    /*
                    | Clear old offline unlock
                    */

                    unset(
                        $_SESSION[
                            'unlocked_currency_serial'
                        ]
                    );


                    $pin_error = "";

                    $_SESSION['refresh'] = true;

                } else {

                    /*
                    | PIN WRONG
                    */

                    unset(
                        $_SESSION[
                            'unlocked_currency_id'
                        ]
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
                        $_SESSION[
                            'unlocked_currency_serial'
                        ]
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
                                empty(
                                    $cachedCurrency[
                                        'serial_no'
                                    ]
                                )
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
                                    $cachedCurrency[
                                        'serial_no'
                                    ]
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

                        $_SESSION[
                            'unlocked_currency_serial'
                        ] =
                            $serialNo;


                        /*
                        | Clear online unlock
                        */

                        unset(
                            $_SESSION[
                                'unlocked_currency_id'
                            ]
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

}
catch (Throwable $th) {

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


                                    <?php

                                    /*
|--------------------------------------------------------------------------
| SHOW QR ONLY AFTER CORRECT PIN
|--------------------------------------------------------------------------
*/

                                    $showQR = false;

                                    if ($unlocked_currency) {

                                        if ($serverConnected) {

                                            /*
        |--------------------------------------------------------------------------
        | ONLINE
        |--------------------------------------------------------------------------
        */

                                            $showQR =
                                                isset($unlocked_currency['id']) &&
                                                isset($currency['id']) &&
                                                $unlocked_currency['id'] == $currency['id'];
                                        } else {

                                            /*
        |--------------------------------------------------------------------------
        | OFFLINE
        |--------------------------------------------------------------------------
        */

                                            $currentSerial = "";

                                            if (!empty($currency['serial_no'])) {

                                                $currentSerial =
                                                    decryptData(
                                                        $currency['serial_no']
                                                    );
                                            }

                                            $unlockedSerial =
                                                $_SESSION['unlocked_currency_serial'] ?? "";

                                            $showQR =
                                                $currentSerial !== "" &&
                                                $unlockedSerial !== "" &&
                                                $currentSerial === $unlockedSerial;
                                        }
                                    }


                                    if ($showQR):


                                        /*
    |--------------------------------------------------------------------------
    | QR DATA
    |--------------------------------------------------------------------------
    */

                                        if ($serverConnected) {

                                            /*
        | Online encrypted serial
        */

                                            $qrData =
                                                $currency['encrypted_serial'];
                                        } else {

                                            /*
        | Offline encrypted currency serial
        |
        | currency_serial_no is encrypted in cache.
        */

                                            $qrData =
                                                $currency['currency_serial_no'];
                                        }

                                    ?>

                                        <div class="qr-unlocked">

                                            <div
                                                class="qr-code"
                                                id="qr-<?php
                                                        echo htmlspecialchars(
                                                            $currency['id'] ?? md5($currentSerial ?? ''),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>">
                                            </div>


                                            <div class="qr-title">

                                                Scan to receive ₹<?php

                                                                    /*
                |--------------------------------------------------------------------------
                | AMOUNT
                |--------------------------------------------------------------------------
                */

                                                                    if ($serverConnected) {

                                                                        echo number_format(
                                                                            decryptData(
                                                                                $currency['amount']
                                                                            ),
                                                                            2
                                                                        );
                                                                    } else {

                                                                        /*
                    | Amount is encrypted in cache
                    */

                                                                        echo number_format(
                                                                            decryptData(
                                                                                $currency['amount']
                                                                            ),
                                                                            2
                                                                        );
                                                                    }

                                                                    ?>

                                            </div>

                                        </div>


                                        <script>
                                            document.addEventListener(
                                                "DOMContentLoaded",
                                                function() {

                                                    const qrElement =
                                                        document.getElementById(
                                                            "qr-<?php
                                                                echo htmlspecialchars(
                                                                    $currency['id'] ?? md5($currentSerial ?? ''),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                                ?>"
                                                        );

                                                    if (qrElement) {

                                                        new QRCode(
                                                            qrElement, {

                                                                /*
                                                                |--------------------------------------------------------------------------
                                                                | ENCRYPTED SERIAL
                                                                |--------------------------------------------------------------------------
                                                                */

                                                                text: <?php
                                                                        echo json_encode(
                                                                            $qrData
                                                                        );
                                                                        ?>,

                                                                width: 130,

                                                                height: 130,

                                                                colorDark: "#022c22",

                                                                colorLight: "#ffffff",

                                                                correctLevel: QRCode.CorrectLevel.H

                                                            }
                                                        );
                                                    }

                                                }
                                            );
                                        </script>

                                    <?php else: ?>

                                        <!-- QR LOCKED -->

                                        <div class="qr-lock">

                                            <div class="lock-icon">
                                                🔒
                                            </div>

                                            <strong>
                                                QR Code Locked
                                            </strong>

                                            <span>
                                                Enter your PIN to display this currency QR
                                            </span>

                                            <button
                                                type="button"
                                                class="show-qr-btn"
                                                onclick="openPinModal(
                '<?php

                                        if ($serverConnected) {

                                            echo (int)$currency['id'];
                                        } else {

                                            /*
                    | Button sends decrypted serial
                    | to offline PIN verification.
                    */

                                            echo htmlspecialchars(
                                                decryptData(
                                                    $currency['serial_no']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                        }

                    ?>'
            )">

                                                🔐 Show QR

                                            </button>

                                        </div>

                                    <?php endif; ?>

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

                <?php if ($serverConnected) { ?>
                    <input
                        type="hidden"
                        name="currency_id"
                        id="currencyId">

                <?php } else { ?>
                    <input type="hidden" id="serialNo" name="serialNo">
                <?php } ?>

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


    <?php require 'footer.php'; ?>


    <script>
        /*
|--------------------------------------------------------------------------
| PIN MODAL
|--------------------------------------------------------------------------
*/
        <?php if ($serverConnected) { ?>

            function openPinModal(currencyId) {

                document.getElementById(
                    "currencyId"
                ).value = currencyId;


                document.getElementById(
                    "pinModal"
                ).classList.add("active");


                setTimeout(
                    function() {

                        const input =
                            document.querySelector(
                                ".pin-input"
                            );

                        if (input) {

                            input.focus();
                        }

                    },
                    100
                );
            }


            function closePinModal() {

                document.getElementById(
                    "pinModal"
                ).classList.remove("active");
            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE WHEN CLICKING OUTSIDE
            |--------------------------------------------------------------------------
            */

            document
                .getElementById("pinModal")
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


            /*
            |--------------------------------------------------------------------------
            | AUTO OPEN MODAL AFTER WRONG PIN
            |--------------------------------------------------------------------------
            */

            <?php if ($pin_error !== ""): ?>

                document.addEventListener(
                    "DOMContentLoaded",
                    function() {

                        document
                            .getElementById("pinModal")
                            .classList.add("active");

                    }
                );

            <?php endif; ?>


            // for offline cache
        <?php } else { ?>
            /*
|--------------------------------------------------------------------------
| OPEN PIN MODAL
|--------------------------------------------------------------------------
*/

            function openPinModal(serialNo) {

                document.getElementById("serialNo").value = serialNo;

                document
                    .getElementById("pinModal")
                    .classList.add("active");

                setTimeout(function() {

                    const input = document.querySelector(".pin-input");

                    if (input) {
                        input.focus();
                    }

                }, 100);
            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE PIN MODAL
            |--------------------------------------------------------------------------
            */

            function closePinModal() {

                document
                    .getElementById("pinModal")
                    .classList.remove("active");
            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE WHEN CLICKING OUTSIDE
            |--------------------------------------------------------------------------
            */

            document
                .getElementById("pinModal")
                .addEventListener("click", function(event) {

                    if (event.target === this) {
                        closePinModal();
                    }

                });


            /*
            |--------------------------------------------------------------------------
            | AUTO OPEN MODAL AFTER WRONG PIN
            |--------------------------------------------------------------------------
            */

            <?php if ($pin_error !== ""): ?>

                document.addEventListener("DOMContentLoaded", function() {

                    document
                        .getElementById("pinModal")
                        .classList.add("active");

                });

            <?php endif; ?>
        <?php } ?>
    </script>


</body>

</html>