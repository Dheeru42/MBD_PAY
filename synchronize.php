<?php

session_start();

date_default_timezone_set('Asia/Kolkata');


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/currency_con.php';


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

define(
    'SECRET_KEY',
    'MBDPAY@2026_SUPER_SECRET_KEY_32'
);

define(
    'CACHE_DIR',
    __DIR__ . '/cache/'
);

define(
    'USERS_CACHE_DIR',
    CACHE_DIR . 'users/'
);


/*
|--------------------------------------------------------------------------
| GET USER MOBILE
|--------------------------------------------------------------------------
*/

function getLoggedInMobile()
{
    if (
        isset($_SESSION['user']['mobile']) &&
        $_SESSION['user']['mobile'] !== ''
    ) {
        return trim(
            (string) $_SESSION['user']['mobile']
        );
    }

    if (
        isset($_SESSION['user']['u_mob']) &&
        $_SESSION['user']['u_mob'] !== ''
    ) {
        return trim(
            (string) $_SESSION['user']['u_mob']
        );
    }

    if (
        isset($_SESSION['mobile']) &&
        $_SESSION['mobile'] !== ''
    ) {
        return trim(
            (string) $_SESSION['mobile']
        );
    }

    return '';
}


$u_mob = getLoggedInMobile();


if ($u_mob === '') {

    die('Unable to synchronize: mobile number not found.');
}


/*
|--------------------------------------------------------------------------
| ENCRYPTION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| MOBILE HASH
|--------------------------------------------------------------------------
*/

$mobileHash = hash(
    'sha256',
    $u_mob
);


/*
|--------------------------------------------------------------------------
| EXACT CACHE PATH
|--------------------------------------------------------------------------
*/

$userCacheFolder =
    USERS_CACHE_DIR .
    $mobileHash;


$currencyCacheFolder =
    $userCacheFolder .
    DIRECTORY_SEPARATOR .
    'currency';


/*
|--------------------------------------------------------------------------
| CREATE DIRECTORY
|--------------------------------------------------------------------------
*/

function createDirectory($path)
{
    if (is_dir($path)) {
        return true;
    }

    if (
        !mkdir(
            $path,
            0777,
            true
        )
    ) {

        throw new Exception(
            'Unable to create directory: ' .
                $path
        );
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| REMOVE DIRECTORY
|--------------------------------------------------------------------------
*/

function removeDirectory($directory)
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir(
        $directory
    );

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {

        if (
            $item === '.' ||
            $item === '..'
        ) {
            continue;
        }

        $path =
            $directory .
            DIRECTORY_SEPARATOR .
            $item;

        if (is_dir($path)) {

            removeDirectory($path);
        } else {

            @unlink($path);
        }
    }

    @rmdir($directory);
}


/*
|--------------------------------------------------------------------------
| DELETE ONLY JSON CACHE FILES
|--------------------------------------------------------------------------
*/

function clearCurrencyCache($folder)
{
    if (!is_dir($folder)) {
        return;
    }

    $files = glob(
        $folder .
            DIRECTORY_SEPARATOR .
            '*.json'
    );

    if ($files === false) {
        throw new Exception(
            'Unable to read currency cache.'
        );
    }

    foreach ($files as $file) {

        if (
            is_file($file) &&
            !unlink($file)
        ) {

            throw new Exception(
                'Unable to remove old currency cache file.'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| CREATE CURRENCY JSON FILE
|--------------------------------------------------------------------------
*/

function createCurrencyCacheFile(
    $folder,
    $mobile,
    $serialNo,
    $encryptedSerial,
    $amount,
    $status,
    $g_date
) {

    $serialNo =
        trim(
            (string) $serialNo
        );


    if ($serialNo === '') {

        throw new Exception(
            'Currency serial number is empty.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CACHE DATA
    |--------------------------------------------------------------------------
    */

    $currencyData = [

        'serial_no' =>
        encryptData(
            $serialNo
        ),

        'currency_serial_no' =>
        $encryptedSerial,

        'amount' =>
        $amount,

        'currency_status' =>
        encryptData(
            $status
        ),

        'receiver_mobile' =>
        encryptData(
            ''
        ),

        'sender_mobile' =>
        encryptData(
            $mobile
        ),

        'synced' =>
        true,

        'generated_at' => $g_date,

        'update_at' => date("Y-m-d H:i:s"),

        'completed_at' =>
        null,

        'scanned_at' =>
        null
    ];


    /*
    |--------------------------------------------------------------------------
    | JSON ENCODE
    |--------------------------------------------------------------------------
    */

    $json = json_encode(
        $currencyData,
        JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
    );


    if ($json === false) {

        throw new Exception(
            'Unable to create currency JSON.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FILE NAME
    |--------------------------------------------------------------------------
    */

    $fileName =
        hash(
            'sha256',
            $serialNo
        ) .
        '.json';


    $filePath =
        $folder .
        DIRECTORY_SEPARATOR .
        $fileName;


    /*
    |--------------------------------------------------------------------------
    | WRITE FILE
    |--------------------------------------------------------------------------
    */

    $written =
        file_put_contents(
            $filePath,
            $json,
            LOCK_EX
        );


    if ($written === false) {

        throw new Exception(
            'Unable to write currency cache file: ' .
                $fileName
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY FILE
    |--------------------------------------------------------------------------
    */

    if (!is_file($filePath)) {

        throw new Exception(
            'Currency cache file was not created: ' .
                $fileName
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY JSON
    |--------------------------------------------------------------------------
    */

    $check =
        file_get_contents(
            $filePath
        );


    if ($check === false) {

        throw new Exception(
            'Unable to verify currency cache file.'
        );
    }


    $decoded =
        json_decode(
            $check,
            true
        );


    if (
        !is_array($decoded) ||
        !isset($decoded['serial_no'])
    ) {

        throw new Exception(
            'Currency cache JSON verification failed.'
        );
    }


    return $filePath;
}


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($c_conn) ||
    !($c_conn instanceof mysqli)
) {

    die('Database connection not available.');
}


if ($c_conn->connect_errno) {

    die('Database connection failed: ' .
        $c_conn->connect_error);
}


/*
|--------------------------------------------------------------------------
| SYNCHRONIZATION
|--------------------------------------------------------------------------
*/

$success = false;

$message = '';

$tempFolder = null;


try {

    /*
    |--------------------------------------------------------------------------
    | CREATE CACHE DIRECTORIES
    |--------------------------------------------------------------------------
    */

    createDirectory(
        CACHE_DIR
    );

    createDirectory(
        USERS_CACHE_DIR
    );

    createDirectory(
        $userCacheFolder
    );

    createDirectory(
        $currencyCacheFolder
    );


    /*
    |--------------------------------------------------------------------------
    | DATABASE QUERY
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Actual database fields:
    |
    | serial_no
    | encrypted_serial
    | amount
    | status
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            serial_no,
            encrypted_serial,
            amount,
            generated_at,
            status
        FROM currency
        WHERE sender_mobile = ?
        AND status = 'GENERATED'
    ";


    /*
    |--------------------------------------------------------------------------
    | PREPARE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $c_conn->prepare(
            $sql
        );


    if (!$stmt) {

        throw new Exception(
            'Database prepare failed: ' .
                $c_conn->error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BIND MOBILE
    |--------------------------------------------------------------------------
    */

    $stmt->bind_param(
        's',
        $u_mob
    );


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'Database query failed: ' .
                $error
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET RESULT
    |--------------------------------------------------------------------------
    */

    $result =
        $stmt->get_result();


    if (!$result) {

        $stmt->close();

        throw new Exception(
            'Unable to get currency records.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FETCH RECORDS
    |--------------------------------------------------------------------------
    */

    $records = [];


    while (
        $row =
        $result->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | Validate fields
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $row['serial_no']
            )
        ) {
            continue;
        }

        if (
            !isset(
                $row['encrypted_serial']
            )
        ) {
            continue;
        }

        if (
            !isset(
                $row['amount']
            )
        ) {
            continue;
        }

        if (
            !isset(
                $row['status']
            )
        ) {
            continue;
        }


        $serialNo =
            trim(
                (string)
                $row['serial_no']
            );


        if ($serialNo === '') {
            continue;
        }


        $records[] = [

            'serial_no' =>
            $serialNo,

            'encrypted_serial' =>
            $row['encrypted_serial'],

            'amount' =>
            $row['amount'],

            'status' =>
            $row['status'],

            'generated_at' =>
            $row['generated_at']
        ];
    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | CREATE TEMP DIRECTORY
    |--------------------------------------------------------------------------
    |
    | New cache is created here first.
    |
    |--------------------------------------------------------------------------
    */

    $tempFolder =
        $userCacheFolder .
        DIRECTORY_SEPARATOR .
        'currency_sync_' .
        date('YmdHis') .
        '_' .
        bin2hex(
            random_bytes(5)
        );


    createDirectory(
        $tempFolder
    );


    /*
    |--------------------------------------------------------------------------
    | CREATE NEW CACHE FILES
    |--------------------------------------------------------------------------
    */

    $createdCount = 0;


    foreach (
        $records as $record
    ) {

        createCurrencyCacheFile(

            $tempFolder,

            $u_mob,

            $record['serial_no'],

            $record['encrypted_serial'],

            $record['amount'],

            $record['status'],

            $record['generated_at'],
        );


        $createdCount++;
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY TEMP CACHE
    |--------------------------------------------------------------------------
    */

    $tempFiles =
        glob(
            $tempFolder .
                DIRECTORY_SEPARATOR .
                '*.json'
        );


    if ($tempFiles === false) {

        throw new Exception(
            'Unable to verify temporary cache.'
        );
    }


    if (
        count($tempFiles) !==
        $createdCount
    ) {

        throw new Exception(
            'Temporary cache verification failed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR OLD CACHE
    |--------------------------------------------------------------------------
    */

    clearCurrencyCache(
        $currencyCacheFolder
    );


    /*
    |--------------------------------------------------------------------------
    | MOVE NEW CACHE
    |--------------------------------------------------------------------------
    */

    foreach (
        $tempFiles as $tempFile
    ) {

        $fileName =
            basename(
                $tempFile
            );


        $destination =
            $currencyCacheFolder .
            DIRECTORY_SEPARATOR .
            $fileName;


        if (
            !rename(
                $tempFile,
                $destination
            )
        ) {

            throw new Exception(
                'Unable to move cache file: ' .
                    $fileName
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE TEMP DIRECTORY
    |--------------------------------------------------------------------------
    */

    if (
        is_dir(
            $tempFolder
        )
    ) {

        @rmdir(
            $tempFolder
        );
    }


    $tempFolder = null;


    /*
    |--------------------------------------------------------------------------
    | FINAL VERIFICATION
    |--------------------------------------------------------------------------
    */

    $finalFiles =
        glob(
            $currencyCacheFolder .
                DIRECTORY_SEPARATOR .
                '*.json'
        );


    if ($finalFiles === false) {

        throw new Exception(
            'Unable to read final currency cache.'
        );
    }


    if (
        count($finalFiles) !==
        $createdCount
    ) {

        throw new Exception(
            'Final cache verification failed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $success = true;


    $message =
        $createdCount .
        ' currency record(s) synchronized.';
} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | REMOVE TEMP CACHE
    |--------------------------------------------------------------------------
    */

    if (
        $tempFolder !== null &&
        is_dir($tempFolder)
    ) {

        removeDirectory(
            $tempFolder
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ERROR LOG
    |--------------------------------------------------------------------------
    */

    error_log(
        'MBD PAY CURRENCY SYNC ERROR: ' .
            $e->getMessage()
    );


    $success = false;

    $message =
        'Synchronization failed.';
}


/*
|--------------------------------------------------------------------------
| FAILURE PAGE
|--------------------------------------------------------------------------
*/

if (!$success) {

?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0">

        <title>
            MBD Pay | Synchronization Failed
        </title>

        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                padding: 20px;

                font-family:
                    Arial,
                    sans-serif;

                background:
                    #ecfdf5;
            }

            .card {

                width: 100%;

                max-width: 420px;

                background: #fff;

                padding: 40px 25px;

                border-radius: 25px;

                text-align: center;

                box-shadow:
                    0 20px 50px rgba(0,
                        0,
                        0,
                        .12);
            }

            .icon {

                width: 65px;

                height: 65px;

                margin: 0 auto 20px;

                display: flex;

                align-items: center;

                justify-content: center;

                border-radius: 50%;

                background: #fee2e2;

                color: #dc2626;

                font-size: 32px;

                font-weight: bold;
            }

            h1 {

                color: #064e3b;

                font-size: 23px;

                margin-bottom: 12px;
            }

            p {

                color: #64748b;

                line-height: 1.6;

                font-size: 14px;

                margin-bottom: 25px;
            }

            .retry {

                display: inline-block;

                padding: 12px 25px;

                border-radius: 12px;

                background: #059669;

                color: #fff;

                text-decoration: none;

                font-weight: bold;
            }
        </style>

    </head>

    <body>

        <div class="card">

            <div class="icon">
                !
            </div>

            <h1>
                Synchronization Failed
            </h1>

            <p>
                Currency data could not be synchronized.
                Please try again.
            </p>

            <a
                href="synchronize.php"
                class="retry">
                Try Again
            </a>

        </div>

    </body>

    </html>

<?php

    exit;
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
        MBD Pay | Synchronizing
    </title>

    <style>
        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;
        }

        body {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

            font-family:
                Arial,
                sans-serif;

            background:
                radial-gradient(circle at top,
                    #bbf7d0,
                    #ecfdf5,
                    #d1fae5);

            color:
                #064e3b;
        }

        .sync-card {

            width: 100%;

            max-width: 420px;

            padding:
                45px 25px;

            background:
                rgba(255,
                    255,
                    255,
                    .94);

            border-radius:
                30px;

            text-align:
                center;

            box-shadow:
                0 25px 60px rgba(0,
                    0,
                    0,
                    .15);
        }

        .logo {

            width: 70px;

            height: 70px;

            margin:
                0 auto 25px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                20px;

            background:
                linear-gradient(135deg,
                    #059669,
                    #047857);

            color:
                #ffffff;

            font-size:
                38px;

            font-weight:
                bold;
        }

        .loader {

            width:
                55px;

            height:
                55px;

            margin:
                25px auto;

            border:
                5px solid #d1fae5;

            border-top:
                5px solid #059669;

            border-radius:
                50%;

            animation:
                spin 1s linear infinite;
        }

        @keyframes spin {

            to {

                transform:
                    rotate(360deg);
            }
        }

        h1 {

            font-size:
                25px;

            margin-bottom:
                10px;
        }

        .description {

            color:
                #64748b;

            font-size:
                14px;

            line-height:
                1.6;
        }

        .status {

            margin-top:
                20px;

            color:
                #059669;

            font-size:
                13px;

            font-weight:
                bold;
        }

        .dots::after {

            content:
                "";

            animation:
                dots 1.5s infinite;
        }

        @keyframes dots {

            0% {
                content: "";
            }

            25% {
                content: ".";
            }

            50% {
                content: "..";
            }

            75% {
                content: "...";
            }

            100% {
                content: "";
            }
        }
    </style>

</head>

<body>

    <div class="sync-card">

        <div class="logo">
            ₹
        </div>

        <h1>
            Synchronizing
        </h1>

        <p class="description">
            Updating your MBD Pay currency data.
            Please wait...
        </p>

        <div class="loader"></div>

        <div class="status">

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <span class="dots"></span>

        </div>

    </div>


    <script>
        setTimeout(function() {

            window.location.replace(
                "index.php"
            );

        }, 1200);
    </script>

</body>

</html>