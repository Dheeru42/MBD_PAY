<?php

session_start();

// default time zone 
date_default_timezone_set('Asia/Kolkata');

define("CACHE_DIR", __DIR__ . "/cache/users/");
define("SECRET_KEY", "MBDPAY@2026_SUPER_SECRET_KEY_32");

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

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

function encryptData($value)
{
    $key = hash(
        'sha256',
        SECRET_KEY,
        true
    );

    $iv = random_bytes(16);

    $encrypted = openssl_encrypt(
        (string) $value,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {

        throw new Exception(
            'Unable to encrypt cache data.'
        );
    }

    return base64_encode(
        $iv . $encrypted
    );
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

$u_wallet_id = $_SESSION['wallet_id'];

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
require 'conn.php';

try {
    // mbd pay balance
    $Sql1 = "SELECT * FROM users WHERE mobile='$u_mob' AND account_no = '$u_account'";
    $result1 = mysqli_query($conn, $Sql1);
    if ($w_user = mysqli_fetch_assoc($result1)) {
        $available_wallet = $w_user['balance'];
        echo 'hello';
        $userId = hash("sha256", $u_mob);
        
        $file = "cache/users/$userId/profile.json";
        

        if (file_exists($file)) {

            $data = json_decode(
                file_get_contents($file),
                true
            );

            $data['balance'] = $available_wallet;

            $data['update_at'] = date("Y-m-d H:i:s");



            file_put_contents(
                $file,
                json_encode(
                    $data,
                    JSON_PRETTY_PRINT
                )
            );
        }
    }
} catch (\Throwable $th) {
    echo 'aagyaa';
}
?>