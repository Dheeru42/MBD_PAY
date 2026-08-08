<?php
session_start();
require 'conn.php';
require 'currency_con.php';

$qrData = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $qrData = trim($_POST['qr_data'] ?? '');

        if ($qrData !== '') {
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
                    WHERE encrypted_serial=?
                    AND status='GENERATED'
                    LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $qrData
            );

            mysqli_stmt_execute($stmt);

            $currency_result = mysqli_stmt_get_result($stmt);

            $currency = mysqli_fetch_assoc($currency_result);

            // to show currency is scanned
            echo $currency['status'] . "->";

            $currency['status'] = 'SCANNED';

            echo $currency['status'];
        }
    }
} catch (Throwable $e) {
    // echo 'hogaya';
}
require 'navbar.php';
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


            console.log(
                "QR DATA:",
                decodedText
            );


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

        document.getElementById(
            "qrImageInput"
        ).addEventListener(

            "change",

            function(event) {

                const file =
                    event.target.files[0];


                if (!file) {
                    return;
                }


                const imageScanner =
                    new Html5Qrcode("reader");


                imageScanner.scanFile(

                        file,

                        true

                    )


                    /*
                    |--------------------------------------------------------------------------
                    | QR IMAGE FOUND
                    |--------------------------------------------------------------------------
                    */

                    .then(function(decodedText) {

                        console.log(
                            "UPLOADED IMAGE QR:",
                            decodedText
                        );


                        qrFound(decodedText);


                        imageScanner.clear();

                    })


                    /*
                    |--------------------------------------------------------------------------
                    | QR IMAGE ERROR
                    |--------------------------------------------------------------------------
                    */

                    .catch(function(error) {

                        imageScanner.clear();


                        document.getElementById(
                                "scanStatus"
                            ).innerText =
                            "❌ No QR code found in this image.";


                        console.error(error);

                    });

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