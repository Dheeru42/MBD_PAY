<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location:login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>

    <title>MBD Pay | Scan QR</title>

    <meta name="viewport" content="width=device-width,initial-scale=1">

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        body {
            margin: 0;
            background: #f4f4f4;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {

            width: 420px;
            max-width: 95%;
            margin: 30px auto;
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .15);

        }

        h2 {

            text-align: center;
            color: #059669;

        }

        #reader {

            width: 100%;
            margin-top: 20px;

        }

        .upload {

            margin-top: 25px;
            text-align: center;

        }

        .upload input {

            width: 100%;
            padding: 10px;

        }

        button {

            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;

        }

        button:hover {

            background: #047857;

        }

        #result {

            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            display: none;
            text-align: center;
            font-weight: bold;

        }

        .success {

            background: #dcfce7;
            color: #166534;

        }

        .error {

            background: #fee2e2;
            color: #991b1b;

        }

        .loading {

            background: #dbeafe;
            color: #1d4ed8;

        }
    </style>

</head>

<body>

    <?php require "navbar.php"; ?>

    <div class="container">

        <h2>📷 Scan Payment QR</h2>

        <div id="reader"></div>

        <div class="upload">

            <h3>OR</h3>

            <input
                type="file"
                id="qrFile"
                accept="image/*">

        </div>

        <button onclick="startScanner()">

            Start Camera

        </button>

        <button onclick="restartScanner()">

            Scan Again

        </button>

        <div id="result"></div>

    </div>

    <?php require "footer.php"; ?>

    <script>
        let scanner = new Html5Qrcode("reader");

        let cameraRunning = false;

        function showMessage(msg, type) {

            let box = document.getElementById("result");

            box.style.display = "block";

            box.className = type;

            box.innerHTML = msg;

        }

        function startScanner() {

            if (cameraRunning)
                return;

            Html5Qrcode.getCameras()

                .then(function(devices) {

                    if (devices.length == 0) {

                        showMessage("No Camera Found", "error");

                        return;

                    }

                    scanner.start(

                        {
                            facingMode: "environment"
                        },

                        {

                            fps: 10,

                            qrbox: 250

                        },

                        function(decodedText) {

                            scanner.stop();

                            cameraRunning = false;

                            sendQR(decodedText);

                        },

                        function(error) {

                        }

                    );

                    cameraRunning = true;

                })

                .catch(function() {

                    showMessage("Unable to Open Camera", "error");

                });

        }

        function restartScanner() {

            document.getElementById("result").style.display = "none";

            startScanner();

        }

        document.getElementById("qrFile")

            .addEventListener("change", function(e) {

                const file = e.target.files[0];

                if (!file)
                    return;

                showMessage("Reading QR...", "loading");

                scanner.scanFile(file, true)

                    .then(function(decodedText) {

                        sendQR(decodedText);

                    })

                    .catch(function() {

                        showMessage("Invalid QR Image", "error");

                    });

            });

        function sendQR(qr) {

            showMessage("Processing Payment...", "loading");

            fetch("process_qr.php", {

                    method: "POST",

                    headers: {

                        "Content-Type": "application/x-www-form-urlencoded"

                    },

                    body: "qr=" + encodeURIComponent(qr)

                })

                .then(function(response) {

                    return response.json();

                })

                .then(function(data) {

                    if (data.status) {

                        showMessage(

                            "✅ " + data.message,

                            "success"

                        );

                    } else {

                        showMessage(

                            "❌ " + data.message,

                            "error"

                        );

                    }

                })

                .catch(function() {

                    showMessage(

                        "Server Connection Failed",

                        "error"

                    );

                });

        }

        startScanner();
    </script>

</body>

</html>