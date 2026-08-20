<?php

// data from server,add in server
$pendingSync = 0;

date_default_timezone_set('Asia/Kolkata');

session_start();

require "conn.php";
require "bank_conn.php";
require "currency_con.php";

date_default_timezone_set('Asia/Kolkata');


// catch create logic

define("CACHE_DIR", __DIR__ . "/cache/users/");
define("SECRET_KEY", "MBDPAY@2026_SUPER_SECRET_KEY_32");

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

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

$u_wallet_id = $_SESSION['wallet_id'];

$u_account = $_SESSION['account'];
$u_mob = $_SESSION['mobile'];

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}



if (isset($_SESSION['user'])) {

    $username = $_SESSION['user'];
}

if (!isset($_SESSION['mobile'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:login.php");
    exit;
}

// SERVER STATUS

if (!$serverConnected) {
    // Update last successful sync time
    $_SESSION['last_sync'] = date("h:i:s A");
}


// available balance

try {
    $mobile = $_SESSION['mobile'];
    $stmt = mysqli_prepare($conn, "SELECT name,balance FROM users WHERE mobile=? AND account_no=?");
    mysqli_stmt_bind_param($stmt, "ss", $mobile, $u_account);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    $_SESSION['balance'] = decryptData($user['balance']);
} catch (\Throwable $th) {

    // user cache path

    $userId = hash("sha256", $mobile);

    $profile = CACHE_DIR . $userId . "/profile.json";

    $cache = json_decode(
        file_get_contents($profile),
        true
    );
    $cache['balance'] = decryptData($cache['balance']);
    $_SESSION['balance'] = $cache['balance'];
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
            WHERE status = 'GENERATED'
              AND sender_mobile = '$u_mob'
              AND generated_at >= CURDATE()
              AND generated_at < CURDATE() + INTERVAL 1 DAY
            ORDER BY generated_at DESC";


    $result = mysqli_query(
        $c_conn,
        $sql
    );


    if (!$result) {

        die("Currency query failed: "
            . mysqli_error($c_conn));
    }


    $total_currency =
        mysqli_num_rows($result);


    $total_value = 0;
    $generatedToday = 0;

    $currency_rows = [];


    while (
        $row = mysqli_fetch_assoc($result)
    ) {

        $currency_rows[] = $row;

        $total_value +=
            decryptData(
                $row['amount']
            );

        $generatedToday = $total_value;
    }
} catch (\Throwable $th) {

    $total_currency = 0;

    $total_value = 0;

    $generatedToday = $total_value;
    $currency_rows = [];
}

// FETCH Recived CURRENCIES


try {

    // Fetch Today's Currency Received Transactions

    $sql = "
        SELECT
            type,
            amount,
            status,
            created_at
        FROM transactions
        WHERE mobile = ?
          AND status = 'Currency Received'
          AND created_at >= CURDATE()
          AND created_at < CURDATE() + INTERVAL 1 DAY
        ORDER BY created_at DESC
    ";


    $stmt2 = mysqli_prepare(
        $conn,
        $sql
    );


    if (!$stmt2) {

        throw new Exception(
            "Transaction query preparation failed: "
                . mysqli_error($conn)
        );
    }


    mysqli_stmt_bind_param(
        $stmt2,
        "s",
        $u_mobile
    );


    if (!mysqli_stmt_execute($stmt2)) {

        throw new Exception(
            "Transaction query failed: "
                . mysqli_stmt_error($stmt2)
        );
    }


    $transactions =
        mysqli_stmt_get_result($stmt2);


    if (!$transactions) {

        throw new Exception(
            "Unable to fetch transactions."
        );
    }


    // Reset totals before calculation

    $total_credit = 0;

    $receivedToday = 0;


    // Calculate Today's Total Credit

    while (
        $row = mysqli_fetch_assoc($transactions)
    ) {

        if (
            $row['type'] === 'Currency Received'
            &&
            $row['status'] === 'Currency Received'
        ) {

            $amount =
                decryptData(
                    $row['amount']
                );

            $total_credit += $amount;

            $receivedToday += $amount;
        }
    }


    // Reset pointer for table display

    mysqli_data_seek(
        $transactions,
        0
    );


    mysqli_stmt_close($stmt2);
} catch (\Throwable $th) {

    $total_credit = 0;

    $receivedToday = 0;

    $transactions = false;

    $message1 =
        "Unable to fetch today's transactions.";
}

session_abort();
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <title>MBD Pay | Digital Currency</title>



    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20'
fill='%23059669'/%3E%3Ctext x='50' y='72'
text-anchor='middle' font-size='70'
font-family='Arial'
font-weight='bold'
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        /* wallet card css */
        .mbd-wallet {
            background: linear-gradient(135deg, #0f766e, #065f46);
            color: #fff;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .25);
        }

        .wallet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .wallet-header h2 {
            font-size: 28px;
        }

        .wallet-status {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .15);
            padding: 8px 16px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .online {
            background: #22c55e;
        }

        .offline {
            background: #ef4444;
        }

        .wallet-balance-section {
            text-align: center;
            margin: 30px 0;
        }

        .balance-title {
            opacity: .8;
            font-size: 16px;
        }

        .wallet-balance {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            font-size: 42px;
            font-weight: bold;
        }

        .eye-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
        }

        .sync-time {
            margin-top: 10px;
            opacity: .75;
            font-size: 14px;
        }

        .wallet-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            text-decoration: none;
            background: #fff;
            color: #065f46;
            border-radius: 18px;
            padding: 18px;
            text-align: center;
            font-weight: bold;
            transition: .3s;
        }

        .action-btn span {
            display: block;
            margin-top: 8px;
            font-size: 14px;
        }

        .action-btn:hover {
            transform: translateY(-5px);
        }

        .wallet-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .stat-box {
            background: rgba(255, 255, 255, .12);
            padding: 18px;
            border-radius: 18px;
            text-align: center;
        }

        .stat-box small {
            opacity: .8;
        }

        .stat-box h3 {
            margin-top: 8px;
            font-size: 24px;
        }

        .offline-card {
            margin-top: 25px;
            background: rgba(255, 255, 255, .10);
            padding: 18px 20px;
            border-radius: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .offline-count {
            font-size: 34px;
            font-weight: bold;
        }

        @keyframes pulse {

            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, .8);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }

        }
    </style>


    <style>
        * {

            margin: 0;
            padding: 0;
            box-sizing: border-box;

        }


        body {

            font-family: Arial, sans-serif;

            min-height: 100vh;

            display: flex;

            flex-direction: column;


            background:
                radial-gradient(circle at top, #bbf7d0, #ecfdf5, #d1fae5);

        }



        /* MAIN */

        .mbd-home {

            flex: 1;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 30px;

        }



        /* CARD */


        .mbd-card {


            width: 950px;

            background:

                rgba(255, 255, 255, .85);


            backdrop-filter: blur(15px);


            border-radius: 35px;


            padding: 40px;


            display: grid;


            grid-template-columns: 1fr 1fr;


            gap: 35px;



            box-shadow:

                0 25px 60px rgba(0, 0, 0, .2);


            animation: show .8s ease;


        }



        @keyframes show {


            from {

                opacity: 0;

                transform: translateY(40px);

            }


            to {

                opacity: 1;

                transform: translateY(0);

            }


        }





        /* LOGO */


        .mbd-logo-box {


            display: flex;

            align-items: center;

            gap: 15px;


        }



        .mbd-rupee {


            height: 75px;

            width: 75px;


            display: flex;

            justify-content: center;

            align-items: center;


            font-size: 50px;

            font-weight: bold;


            color: white;


            border-radius: 22px;


            background:

                linear-gradient(135deg, #059669, #022c22);



            box-shadow:

                0 10px 25px #059669;



            animation: logoPulse 2s infinite;


        }



        @keyframes logoPulse {


            50% {

                transform: scale(1.08);

            }


        }



        .mbd-title {


            font-size: 45px;

            color: #064e3b;

        }



        .mbd-title span {

            color: #059669;

        }





        .mbd-text {


            margin-top: 20px;

            font-size: 18px;

            line-height: 1.6;

            color: #555;


        }




        /* FEATURES */


        .mbd-features {


            margin-top: 30px;

            display: flex;

            flex-direction: column;

            gap: 15px;


        }



        .mbd-feature {


            background: white;

            padding: 15px;

            border-radius: 15px;


            display: flex;

            align-items: center;

            gap: 15px;


            box-shadow:

                0 5px 15px rgba(0, 0, 0, .08);


            transition: .3s;


        }



        .mbd-feature:hover {


            transform: translateX(10px);


        }



        .mbd-feature-icon {


            font-size: 28px;


        }


        .welcome-box {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-top: 25px;

            padding: 18px 25px;

            background:

                linear-gradient(135deg,
                    #ffffff,
                    #ecfdf5);

            border-radius: 25px;


            box-shadow:

                0 10px 30px rgba(5, 150, 105, .15);


            width: max-content;


            animation: welcomeAnimation .8s ease;


        }


        .welcome-icon {

            height: 60px;

            width: 60px;

            border-radius: 50%;


            display: flex;

            justify-content: center;

            align-items: center;


            font-size: 32px;


            background:

                linear-gradient(135deg, #059669, #022c22);


            box-shadow:

                0 8px 20px rgba(5, 150, 105, .5);


        }


        .welcome-text {

            display: flex;

            flex-direction: column;


        }



        .welcome-small {

            font-size: 14px;

            color: #64748b;

        }



        .welcome-text h2 {

            font-size: 26px;

            margin: 3px 0;

            color: #064e3b;

        }



        .welcome-text h2 span {

            color: #059669;

        }



        .welcome-text p {

            font-size: 14px;

            color: #059669;

            margin: 0;

        }



        @keyframes welcomeAnimation {

            from {

                opacity: 0;

                transform: translateY(-20px);

            }


            to {

                opacity: 1;

                transform: translateY(0);

            }

        }



        /* MOBILE */


        @media(max-width:800px) {


            .mbd-card {


                grid-template-columns: 1fr;

                padding: 25px;

            }



            .mbd-title {


                font-size: 35px;


            }



        }
    </style>


</head>


<body>



    <?php require "navbar.php"; ?>





    <section class="mbd-home">


        <div class="mbd-card">



            <!-- LEFT SIDE -->


            <div>



                <div class="mbd-logo-box">


                    <div class="mbd-rupee">

                        ₹

                    </div>



                    <h1 class="mbd-title">

                        MBD <span>Pay</span>

                    </h1>



                </div>




                <br>
                <p class="mbd-text">


                <div class="welcome-box">

                    <div class="welcome-icon">
                        👋
                    </div>

                    <div class="welcome-text">

                        <span class="welcome-small">
                            Welcome back,
                        </span>

                        <h2>
                            <?php echo htmlspecialchars($username); ?>
                            <span>✨</span>
                        </h2>

                        <p>
                            Your MBD Pay wallet is ready
                        </p>

                    </div>

                </div>


                <br><br>


                Next generation digital payment system.

                Transfer money using secure QR currency,
                online synchronization and offline transaction support.



                </p>





                <div class="mbd-features">



                    <div class="mbd-feature">

                        <div class="mbd-feature-icon">

                            📱

                        </div>

                        QR Currency Transfer

                    </div>



                    <div class="mbd-feature">

                        <div class="mbd-feature-icon">

                            ⚡

                        </div>

                        Instant Wallet Update

                    </div>



                    <div class="mbd-feature">

                        <div class="mbd-feature-icon">

                            🔒

                        </div>

                        Encrypted PIN Security

                    </div>



                    <div class="mbd-feature">

                        <div class="mbd-feature-icon">

                            🌐

                        </div>

                        Online + Offline Payment

                    </div>



                </div>


            </div>

            <!-- ================= WALLET CARD ================= -->

            <div class="mbd-wallet">

                <!-- Wallet Header -->
                <div class="wallet-header">
                    <h2>💳 MBD Wallet</h2>

                    <div class="wallet-status">
                        <?php if ($serverConnected) { ?>
                            <span class="status-dot online"></span>
                            <span>Connected</span>
                        <?php } else { ?>
                            <span class="status-dot offline"></span>
                            <span>Offline Mode</span>
                        <?php } ?>
                    </div>
                </div>

                <!-- Balance -->
                <div class="wallet-balance-section">

                    <span class="balance-title">
                        Available Balance
                    </span>

                    <div class="wallet-balance">

                        <span id="balanceText">
                            ₹<?php echo "••••••" ?>
                        </span>

                        <button id="toggleBalance"
                            onclick="toggleBalance()"
                            class="eye-btn">

                            👁

                        </button>

                    </div>

                    <div class="sync-time">
                        Last Sync •

                        <?php
                        if ($serverConnected) {
                            echo "Just Now";
                        } else {
                            echo $_SESSION['last_sync'];
                        }
                        ?>
                    </div>

                </div>

                <?php if ($conn && $bank_conn) { ?>

                    <!-- Quick Actions -->
                    <div class="wallet-actions">

                        <a href="generate_qr_currency.php" class="action-btn">
                            💸
                            <span>Generate QR Currency</span>
                        </a>

                        <a href="qr_scanner.php" class="action-btn">
                            📷
                            <span>Scan QR Currency</span>
                        </a>

                        <a href="synchronize.php" class="action-btn">
                            🔄
                            <span>Syncronization</span>
                        </a>

                    </div>

                    <!-- Statistics -->
                    <div class="wallet-stats">

                        <div class="stat-box">
                            <small>Generated Today</small>
                            <h3>₹ <?php echo number_format($generatedToday, 2); ?></h3>
                        </div>

                        <div class="stat-box">
                            <small>Received Today</small>
                            <h3>₹ <?php echo number_format($receivedToday, 2); ?></h3>
                        </div>

                    </div>

                    <!-- Offline Transactions -->
                    <div class="offline-card">

                        <div>
                            <strong>Offline Transactions</strong><br>
                            <small>Waiting for Synchronization</small>
                        </div>

                        <div class="offline-count">
                            <?php echo $pendingSync; ?>
                        </div>

                    </div>

                <?php } else { ?>
                    <br>
                    <br>
                    <br>
                    <br>
                    <!-- Offline Mode -->
                    <div class="offline-card" style="text-align:center; display:block;">

                        <h3>📴 Offline Mode</h3>

                        <p style="margin-top:10px;">
                            MBD Pay is currently running in offline mode.
                        </p>

                        <p style="margin-top:8px; opacity:.8;">
                            Connect to the internet to generate QR, scan currency,
                            synchronize transactions, and update your wallet balance.
                        </p>

                    </div>

                <?php } ?>

            </div>


        </div>



        </div>



    </section>




    <?php require "footer.php"; ?>


</body>

<script>
    let visible = false;

    function toggleBalance() {

        let balance = document.getElementById("balanceText");

        if (visible) {

            balance.innerHTML = "₹••••••";

        } else {

            balance.innerHTML = "₹<?php echo $_SESSION['balance']; ?>";

        }

        visible = !visible;

    }
</script>

</html>