<?php
session_start();
require 'conn.php';
require 'currency_con.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user'])) {
    header("location:login.php");
    exit;
}


if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}

if (isset($_SESSION['mobile'])) {
    $user_mob = $_SESSION['mobile'];
}

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

if (!isset($_SESSION['account'])) {
    header("location:index.php");
    exit;
}

$u_account = $_SESSION['account'];

if (!isset($_SESSION['mobile'])) {
    header("location:index.php");
    exit;
}

if (!isset($_SESSION['mode'])) {
    header("location:index.php");
    exit;
}

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


// QR scan data is served to server for credit/debit processing.
// Transaction result is stored in the session so sensitive transaction
// information is not exposed in the success/failure URL.
$qrData = '';

function createTransactionId(): string
{
    return 'MBD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

function setQrFailure(string $reason, array $details = []): void
{
    $_SESSION['qr_result'] = array_merge([
        'transaction_id' => createTransactionId(),
        'status'         => 'FAILED',
        'reason'         => $reason,
        'amount'         => $details['amount'] ?? null,
        'serial_no'      => $details['serial_no'] ?? null,
        'sender_mobile'  => $details['sender_mobile'] ?? null,
        'receiver_mobile' => $details['receiver_mobile'] ?? null,
        'generated_at'   => $details['generated_at'] ?? null,
        'completed_at'   => date('Y-m-d H:i:s'),
    ], $details);

    header('Location: qr_fail.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $qrData = trim($_POST['qr_data'] ?? '');

        if ($qrData === '') {
            setQrFailure('QR data is empty. Please scan a valid QR code.');
        }

        $data = json_decode($qrData, true);

        if (!is_array($data)) {
            setQrFailure('Invalid QR code. The QR data format is not supported.');
        }

        $encrypted_currency_serial_no =
            trim((string)($data['encrypted_currency_serial_no'] ?? ''));

        $currency_serial_no =
            trim((string)($data['currency_serial_no'] ?? ''));

        if ($encrypted_currency_serial_no === '' || $currency_serial_no === '') {
            setQrFailure('This QR code does not contain valid currency information.');
        }

        $stmt = mysqli_prepare(
            $c_conn,
            "SELECT
                id,
                wallet_id,
                serial_no,
                encrypted_serial,
                amount,
                sender_mobile,
                receiver_mobile,
                status,
                generated_at
             FROM currency
             WHERE encrypted_serial = ?
             AND serial_no = ?
             AND status = 'GENERATED'
             LIMIT 1"
        );

        if (!$stmt) {
            throw new Exception('Database prepare failed.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'ss',
            $encrypted_currency_serial_no,
            $currency_serial_no
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Database query failed.');
        }

        $currency_result = mysqli_stmt_get_result($stmt);

        if (!$currency_result) {
            throw new Exception('Unable to read currency record.');
        }

        $currency = mysqli_fetch_assoc($currency_result);

        if (!$currency) {
            setQrFailure('Currency not found, invalid, or already scanned.', [
                'serial_no' => $currency_serial_no
            ]);
        }

        $sen_mob = (string)($currency['sender_mobile'] ?? '');

        // A user cannot scan their own currency.
        if ((string)$user_mob === $sen_mob) {
            setQrFailure('You cannot scan your own currency.', [
                'amount'        => decryptData($currency['amount']) ?? null,
                'serial_no'     => $currency['serial_no'] ?? null,
                'sender_mobile' => $currency['sender_mobile'] ?? null,
                'generated_at'  => $currency['generated_at'] ?? null,
            ]);
        }

        $currency_id = (int)$currency['id'];

        $updateStmt = mysqli_prepare(
            $c_conn,
            "UPDATE currency
             SET status = 'SCANNED', receiver_mobile = ?
             WHERE id = ?
             AND status = 'GENERATED'
             LIMIT 1"
        );

        if (!$updateStmt) {
            throw new Exception('Status update could not be prepared.');
        }

        mysqli_stmt_bind_param($updateStmt, 'si', $user_mob, $currency_id);

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('Transaction could not be completed.');
        }

        // If another request scanned it first, do not report a false success.
        if (mysqli_stmt_affected_rows($updateStmt) !== 1) {
            setQrFailure('This currency has already been processed or is no longer available.', [
                'amount'        => decryptData($currency['amount']) ?? null,
                'serial_no'     => $currency['serial_no'] ?? null,
                'sender_mobile' => $currency['sender_mobile'] ?? null,
                'receiver_mobile' => $user_mob,
                'generated_at'  => $currency['generated_at'] ?? null,
            ]);
        }

        $_SESSION['qr_result'] = [
            'transaction_id' => createTransactionId(),
            'status'         => 'SUCCESS',
            'amount'         => decryptData($currency['amount']) ?? null,
            'serial_no'      => $currency['serial_no'] ?? null,
            'sender_mobile'  => $currency['sender_mobile'] ?? null,
            'receiver_mobile' => $user_mob,
            'generated_at'   => $currency['generated_at'] ?? null,
            'completed_at'   => date('Y-m-d H:i:s'),
        ];

        header('Location: qr_success.php');
        exit;
    }
} catch (Throwable $e) {
    // Keep technical database errors out of the customer-facing page.
    setQrFailure('We could not complete the transaction. Please try again.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>MBD PAY | QR Scanner</title>

    <link rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        body {
            font-family: Arial, sans-serif;

            background:
                radial-gradient(circle at top left,
                    #bbf7d0,
                    #ecfdf5 50%,
                    #d1fae5);

            min-height: 100vh;

            padding-bottom: 90px;

            overflow-x: hidden;
        }

        /* =========================================================
           MAIN
        ========================================================= */

        .qr-wrapper {

            width: calc(100% - 30px);

            max-width: 950px;

            margin: 25px auto 80px;

            position: relative;

            z-index: 1;
        }

        /* =========================================================
           HEADER
        ========================================================= */

        .qr-heading {

            text-align: center;

            margin-bottom: 20px;

            width: 100%;
        }

        .qr-icon {

            width: 60px;
            height: 60px;

            margin: 0 auto 10px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 18px;

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            font-size: 32px;

            box-shadow:
                0 8px 20px rgba(245, 158, 11, .25);
        }

        .qr-heading h1 {

            margin: 0;

            color: #022c22;

            font-size: 29px;
        }

        .qr-heading p {

            margin: 7px 0 0;

            color: #64748b;

            font-size: 14px;
        }

        /* =========================================================
           CARD
        ========================================================= */

        .qr-card {

            width: 100%;

            max-width: 950px;

            margin: 0 auto;

            padding: 25px;

            background: rgba(255, 255, 255, .96);

            border-radius: 24px;

            border: 1px solid rgba(5, 150, 105, .15);

            box-shadow:
                0 15px 45px rgba(0, 0, 0, .15);

            overflow: hidden;
        }

        /* =========================================================
           BUTTONS
        ========================================================= */

        .qr-actions {

            width: 100%;

            display: flex;

            gap: 15px;

            margin: 0 0 22px;

            padding: 0;

            position: relative;

            z-index: 5;
        }

        .qr-action {

            flex: 1 1 0;

            width: 50%;

            min-width: 0;

            height: 58px;

            border: none;

            border-radius: 14px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            cursor: pointer;

            font-size: 15px;

            font-weight: bold;

            transition: all .25s ease;

            white-space: nowrap;
        }

        .qr-action:hover {

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(0, 0, 0, .15);
        }

        .camera-btn {

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            color: white;
        }

        .upload-btn {

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            color: #422006;
        }

        /* =========================================================
           CAMERA
        ========================================================= */

        #cameraSection {

            width: 100%;

            display: block;
        }

        .scanner-box {

            width: 100%;

            max-width: 520px;

            margin: 0 auto;

            padding: 8px;

            background: #022c22;

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 10px 30px rgba(2, 44, 34, .25);
        }

        #reader {

            width: 100% !important;

            max-width: 100% !important;

            border: none !important;

            border-radius: 14px;

            overflow: hidden;
        }

        #reader video {

            width: 100% !important;

            max-width: 100% !important;

            display: block;

            border-radius: 14px;
        }

        #reader img {

            max-width: 100% !important;
        }

        .scan-help {

            text-align: center;

            margin-top: 13px;

            color: #64748b;

            font-size: 14px;
        }

        #scanStatus {

            text-align: center;

            margin-top: 8px;

            color: #059669;

            font-size: 13px;

            font-weight: bold;
        }

        /* =========================================================
           UPLOAD
        ========================================================= */

        #uploadSection {

            display: none;

            width: 100%;
        }

        .upload-area {

            width: 100%;

            max-width: 650px;

            margin: 0 auto;

            padding: 45px 20px;

            border: 2px dashed #059669;

            border-radius: 20px;

            background: #f0fdf4;

            text-align: center;

            cursor: pointer;

            display: block;

            transition: .25s;
        }

        .upload-area:hover {

            background: #dcfce7;

            border-color: #047857;
        }

        .upload-icon {

            width: 75px;
            height: 75px;

            margin: 0 auto 15px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 20px;

            background:
                linear-gradient(135deg,
                    #facc15,
                    #f59e0b);

            font-size: 40px;
        }

        .upload-area h2 {

            margin: 0 0 8px;

            color: #022c22;

            font-size: 21px;
        }

        .upload-area p {

            margin: 0;

            color: #64748b;

            font-size: 14px;
        }

        .choose-btn {

            display: inline-block;

            margin-top: 18px;

            padding: 11px 20px;

            border-radius: 10px;

            background:
                linear-gradient(135deg,
                    #022c22,
                    #059669);

            color: white;

            font-size: 14px;

            font-weight: bold;
        }

        #qrImageInput {

            display: none;
        }

        /* =========================================================
           SUCCESS MESSAGE
        ========================================================= */

        #scanSuccess {

            display: none;

            width: 100%;

            max-width: 520px;

            margin: 20px auto 0;

            padding: 15px;

            text-align: center;

            background: #dcfce7;

            border: 1px solid #86efac;

            border-radius: 12px;

            color: #166534;

            font-weight: bold;

            font-size: 14px;
        }

        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 650px) {

            .qr-wrapper {

                width: calc(100% - 20px);

                margin-top: 15px;

                margin-bottom: 90px;
            }

            .qr-card {

                padding: 15px;

                border-radius: 18px;
            }

            .qr-actions {

                flex-direction: column;

                gap: 10px;
            }

            .qr-action {

                width: 100%;

                height: 54px;

                flex: none;

                font-size: 14px;
            }

            .qr-heading h1 {

                font-size: 24px;
            }

            .scanner-box {

                padding: 5px;

                border-radius: 15px;
            }

            .upload-area {

                padding: 35px 15px;
            }
        }

        /* =========================================================
           VERY SMALL MOBILE
        ========================================================= */

        @media (max-width: 380px) {

            .qr-wrapper {

                width: calc(100% - 12px);
            }

            .qr-card {

                padding: 10px;
            }

            .qr-action {

                font-size: 13px;
            }
        }
    </style>

</head>

<body>
    <?php require "navbar.php"; ?>

    <div class="qr-wrapper">

        <!-- HEADER -->

        <div class="qr-heading">

            <div class="qr-icon">
                📱
            </div>

            <h1>
                QR Scanner
            </h1>

            <p>
                Scan a QR code or upload a QR image
            </p>

        </div>


        <!-- PHP QR SUBMIT FORM -->

        <form
            id="qrSubmitForm"
            method="POST"
            action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>"
            style="display:none;">

            <input
                type="hidden"
                name="qr_data"
                id="qrDataInput">
        </form>


        <!-- CARD -->

        <div class="qr-card">

            <!-- BUTTONS -->

            <div class="qr-actions">

                <button
                    type="button"
                    class="qr-action camera-btn"
                    onclick="showCamera()">

                    📷 Scan with Camera

                </button>


                <button
                    type="button"
                    class="qr-action upload-btn"
                    onclick="openUpload()">

                    🖼️ Upload QR Image

                </button>

            </div>


            <!-- CAMERA -->

            <div id="cameraSection">

                <div class="scanner-box">

                    <div id="reader"></div>

                </div>

                <div class="scan-help">

                    Place the QR code inside the camera area

                </div>

                <div id="scanStatus">

                    Starting camera...

                </div>

            </div>


            <!-- UPLOAD -->

            <div id="uploadSection">

                <label
                    class="upload-area"
                    for="qrImageInput">

                    <div class="upload-icon">
                        🖼️
                    </div>

                    <h2>
                        Upload QR Code
                    </h2>

                    <p>
                        Select a QR image from your device
                    </p>

                    <span class="choose-btn">
                        Choose QR Image
                    </span>

                </label>


                <input
                    type="file"
                    id="qrImageInput"
                    accept="image/png,image/jpeg,image/jpg,image/webp">

            </div>


        </div>

    </div>


    <script>
        /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

        let scanner = null;
        let currentResult = "";
        let processingQR = false;


        /*
        |--------------------------------------------------------------------------
        | SEND QR DATA TO PHP USING POST
        |--------------------------------------------------------------------------
        */

        function sendQRToPHP(decodedText) {

            if (!decodedText) {
                return;
            }

            const qrInput = document.getElementById("qrDataInput");
            const qrForm = document.getElementById("qrSubmitForm");

            if (!qrInput || !qrForm) {
                console.error("QR submit form not found.");
                return;
            }

            qrInput.value = decodedText;

            /*
            |--------------------------------------------------------------------------
            | NORMAL FORM SUBMIT
            |--------------------------------------------------------------------------
            | No fetch(), no AJAX response handling and no JavaScript DOM display.
            | The browser submits the QR data to PHP, and PHP echoes it immediately.
            |--------------------------------------------------------------------------
            */

            qrForm.submit();
        }


        /*
        |--------------------------------------------------------------------------
        | QR FOUND
        |--------------------------------------------------------------------------
        */

        function qrFound(decodedText) {

            /*
            |--------------------------------------------------------------------------
            | PREVENT MULTIPLE SCANS
            |--------------------------------------------------------------------------
            */

            if (processingQR) {
                return;
            }

            processingQR = true;


            // QR payload is intentionally not logged or displayed.


            /*
            |--------------------------------------------------------------------------
            | STOP CAMERA
            |--------------------------------------------------------------------------
            */

            stopCamera();


            /*
            |--------------------------------------------------------------------------
            | SEND QR DATA TO PHP
            |--------------------------------------------------------------------------
            */

            sendQRToPHP(decodedText);


            /*
            |--------------------------------------------------------------------------
            | ALLOW ANOTHER SCAN
            |--------------------------------------------------------------------------
            */

            setTimeout(function() {

                processingQR = false;

            }, 1500);

        }


        /*
        |--------------------------------------------------------------------------
        | START CAMERA
        |--------------------------------------------------------------------------
        */

        function startCamera() {

            if (scanner !== null) {
                return;
            }


            scanner =
                new Html5Qrcode("reader");


            scanner.start(

                    {
                        facingMode: "environment"
                    },

                    {
                        fps: 10,

                        qrbox: {
                            width: 250,
                            height: 250
                        }
                    },


                    /*
                    |--------------------------------------------------------------------------
                    | QR SCANNED
                    |--------------------------------------------------------------------------
                    */

                    function(decodedText) {

                        qrFound(decodedText);

                    },


                    /*
                    |--------------------------------------------------------------------------
                    | SCAN ERROR
                    |--------------------------------------------------------------------------
                    */

                    function(errorMessage) {

                        /*
                        Normal scanning errors are ignored.
                        */

                    }

                )


                /*
                |--------------------------------------------------------------------------
                | CAMERA STARTED
                |--------------------------------------------------------------------------
                */

                .then(function() {

                    const statusElement =
                        document.getElementById("scanStatus");

                    if (statusElement) {

                        statusElement.innerText =
                            "🟢 Camera ready";

                    }

                })


                /*
                |--------------------------------------------------------------------------
                | CAMERA ERROR
                |--------------------------------------------------------------------------
                */

                .catch(function(error) {

                    const statusElement =
                        document.getElementById("scanStatus");

                    if (statusElement) {

                        statusElement.innerText =
                            "❌ Camera permission required.";

                    }

                    console.error(error);

                });

        }


        /*
        |--------------------------------------------------------------------------
        | STOP CAMERA
        |--------------------------------------------------------------------------
        */

        function stopCamera() {

            if (scanner === null) {
                return;
            }


            scanner.stop()

                .then(function() {

                    scanner.clear();

                    scanner = null;

                })

                .catch(function(error) {

                    console.error(
                        "Camera stop error:",
                        error
                    );

                    scanner = null;

                });

        }


        /*
        |--------------------------------------------------------------------------
        | SHOW CAMERA
        |--------------------------------------------------------------------------
        */

        function showCamera() {

            stopCamera();


            document.getElementById(
                "cameraSection"
            ).style.display = "block";


            document.getElementById(
                "uploadSection"
            ).style.display = "none";


            setTimeout(function() {

                startCamera();

            }, 300);

        }


        /*
        |--------------------------------------------------------------------------
        | OPEN UPLOAD
        |--------------------------------------------------------------------------
        */

        function openUpload() {

            stopCamera();


            document.getElementById(
                "cameraSection"
            ).style.display = "none";


            document.getElementById(
                "uploadSection"
            ).style.display = "block";


            document.getElementById(
                "qrImageInput"
            ).click();

        }


        /*
        |--------------------------------------------------------------------------
        | READ UPLOADED QR IMAGE
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | READ UPLOADED QR IMAGE
        |--------------------------------------------------------------------------
        | html5-qrcode is tried first. If the image is difficult to decode
        | because of resolution, scaling, rotation, or compression, jsQR is
        | used as a fallback. The decoded value is never written to the page.
        */

        function submitDecodedUpload(decodedText) {
            if (!decodedText || processingQR) {
                return;
            }

            processingQR = true;

            const statusElement = document.getElementById("scanStatus");
            if (statusElement) {
                statusElement.innerText = "🟢 QR detected. Processing...";
            }

            // Do not display/log the QR payload.
            sendQRToPHP(decodedText);
        }

        function decodeUploadedImageWithJsQR(file) {
            return new Promise(function(resolve, reject) {
                if (typeof jsQR !== "function") {
                    reject(new Error("QR fallback library not loaded."));
                    return;
                }

                const image = new Image();
                const objectUrl = URL.createObjectURL(file);

                image.onload = function() {
                    try {
                        const maxSize = 2400;
                        let width = image.naturalWidth;
                        let height = image.naturalHeight;

                        // Keep enough resolution for small QR modules.
                        if (Math.max(width, height) > maxSize) {
                            const scale = maxSize / Math.max(width, height);
                            width = Math.round(width * scale);
                            height = Math.round(height * scale);
                        }

                        const canvas = document.createElement("canvas");
                        canvas.width = width;
                        canvas.height = height;

                        const ctx = canvas.getContext("2d", {
                            willReadFrequently: true
                        });

                        ctx.drawImage(image, 0, 0, width, height);

                        let imageData = ctx.getImageData(
                            0,
                            0,
                            width,
                            height
                        );

                        // First attempt: normal image.
                        let result = jsQR(
                            imageData.data,
                            imageData.width,
                            imageData.height, {
                                inversionAttempts: "attemptBoth"
                            }
                        );

                        // Second attempt: grayscale/contrast.
                        if (!result) {
                            const data = imageData.data;

                            for (let i = 0; i < data.length; i += 4) {
                                const gray = Math.round(
                                    0.299 * data[i] +
                                    0.587 * data[i + 1] +
                                    0.114 * data[i + 2]
                                );

                                data[i] = gray;
                                data[i + 1] = gray;
                                data[i + 2] = gray;
                            }

                            result = jsQR(
                                data,
                                imageData.width,
                                imageData.height, {
                                    inversionAttempts: "attemptBoth"
                                }
                            );
                        }

                        // Third attempt: rotate 90 degrees.
                        if (!result) {
                            const rotated = document.createElement("canvas");
                            rotated.width = height;
                            rotated.height = width;

                            const rctx = rotated.getContext("2d", {
                                willReadFrequently: true
                            });

                            rctx.translate(height, 0);
                            rctx.rotate(Math.PI / 2);
                            rctx.drawImage(image, 0, 0, width, height);

                            const rotatedData = rctx.getImageData(
                                0,
                                0,
                                height,
                                width
                            );

                            result = jsQR(
                                rotatedData.data,
                                rotatedData.width,
                                rotatedData.height, {
                                    inversionAttempts: "attemptBoth"
                                }
                            );
                        }

                        URL.revokeObjectURL(objectUrl);

                        if (result && result.data) {
                            resolve(result.data);
                        } else {
                            reject(new Error("QR code could not be decoded."));
                        }
                    } catch (error) {
                        URL.revokeObjectURL(objectUrl);
                        reject(error);
                    }
                };

                image.onerror = function() {
                    URL.revokeObjectURL(objectUrl);
                    reject(new Error("Unable to read image."));
                };

                image.src = objectUrl;
            });
        }

        document.getElementById(
            "qrImageInput"
        ).addEventListener(
            "change",
            async function(event) {

                const file = event.target.files[0];

                if (!file) {
                    return;
                }

                const statusElement =
                    document.getElementById("scanStatus");

                if (statusElement) {
                    statusElement.innerText =
                        "🔎 Reading QR image...";
                }

                processingQR = false;

                const imageScanner =
                    new Html5Qrcode("reader");

                try {
                    /*
                    |--------------------------------------------------------------------------
                    | METHOD 1: html5-qrcode
                    |--------------------------------------------------------------------------
                    */
                    const decodedText =
                        await imageScanner.scanFile(file, true);

                    imageScanner.clear();

                    submitDecodedUpload(decodedText);

                } catch (firstError) {

                    try {
                        imageScanner.clear();
                    } catch (e) {
                        // Ignore cleanup errors.
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | METHOD 2: jsQR FALLBACK
                    |--------------------------------------------------------------------------
                    */
                    try {
                        const decodedText =
                            await decodeUploadedImageWithJsQR(file);

                        submitDecodedUpload(decodedText);

                    } catch (secondError) {

                        if (statusElement) {
                            statusElement.innerText =
                                "❌ QR code could not be read. Please upload a clear QR image.";
                        }

                        console.error(
                            "QR image decoding failed."
                        );
                    }
                }

                // Allow selecting the same image again.
                event.target.value = "";
            }
        );

        /*
        |--------------------------------------------------------------------------
        | PAGE LOAD
        |--------------------------------------------------------------------------
        */

        window.addEventListener(

            "load",

            function() {

                startCamera();

            }

        );


        /*
        |--------------------------------------------------------------------------
        | CLEANUP
        |--------------------------------------------------------------------------
        */

        window.addEventListener(

            "beforeunload",

            function() {

                stopCamera();

            }

        );
    </script>


    <?php
    require 'footer.php';
    ?>

</body>

</html>