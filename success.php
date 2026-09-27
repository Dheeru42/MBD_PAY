<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

$current_date_time = date("Y-m-d h:i:s A");

if (!isset($_SESSION['user'])) {
    header("location:login.php");
    exit;
}

if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}

if (!isset($_SESSION['wallet_id'])) {

    header("location:login.php");
    exit;
}

if (!isset($_SESSION['account'])) {
    header("location:index.php");
    exit;
}

if (!isset($_SESSION['mobile'])) {
    header("location:index.php");
    exit;
}

if (!isset($_SESSION['success_transaction'])) {
    header("Location: index.php");
    exit;
}

// session data
$user_mob = $_SESSION['mobile'];

$u_wallet_id = $_SESSION['wallet_id'];

$u_account = $_SESSION['account'];

$transaction = $_SESSION['success_transaction'];

$trx_token_id         = $transaction['token_id'] ?? '';
$trx_id               = $transaction['transaction_id'] ?? '';
$trx_amount          = $transaction['amount'] ?? 0;
$trx_sender_mobile   = $transaction['sender_mobile'] ?? '';
$trx_receiver_mobile = $transaction['receiver_mobile'] ?? '';
$trx_timestamp       = $transaction['timestamp'] ?? date('Y-m-d H:i:s');


function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function maskMobile($mobile)
{
    $mobile = (string)$mobile;

    if (strlen($mobile) >= 10) {
        return substr($mobile, 0, 2) . "******" . substr($mobile, -2);
    }

    return $mobile;
}

$formattedAmount = number_format((float)$trx_amount, 2);

$formattedDate = date("d M Y", strtotime($trx_timestamp));

$formattedTime = date("h:i A", strtotime($trx_timestamp));

session_abort();
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>MBD PAY | Payment Successful</title>

    <link rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;

            background:
                radial-gradient(circle at 20% 20%, rgba(0, 255, 180, 0.12), transparent 30%),
                radial-gradient(circle at 80% 80%, rgba(0, 190, 255, 0.12), transparent 30%),
                linear-gradient(135deg, #061b18, #082d28, #031411);

            display: flex;
            justify-content: center;
            align-items: center;

            padding: 25px;

            color: #ffffff;
        }

        /* Background glow */

        .glow {
            position: fixed;
            width: 300px;
            height: 300px;

            border-radius: 50%;

            background: rgba(0, 255, 170, 0.08);

            filter: blur(70px);

            z-index: 0;
        }

        .glow.one {
            top: -100px;
            left: -100px;
        }

        .glow.two {
            bottom: -100px;
            right: -100px;
        }

        /* Main card */

        .success-card {
            position: relative;
            z-index: 2;

            width: 100%;
            max-width: 470px;

            padding: 35px 30px 30px;

            border-radius: 28px;

            background: rgba(255, 255, 255, 0.075);

            border: 1px solid rgba(255, 255, 255, 0.13);

            backdrop-filter: blur(20px);

            box-shadow:
                0 25px 80px rgba(0, 0, 0, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);

            text-align: center;

            animation: cardEnter 0.7s ease;
        }

        @keyframes cardEnter {

            from {
                opacity: 0;
                transform: translateY(30px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

        }

        /* MBD logo */

        .logo {
            font-size: 18px;
            font-weight: bold;

            letter-spacing: 2px;

            color: #7affd8;

            margin-bottom: 25px;
        }

        .logo span {
            color: white;
        }

        /* Success icon */

        .success-icon {
            width: 100px;
            height: 100px;

            margin: 0 auto 22px;

            border-radius: 50%;

            background: rgba(0, 255, 166, 0.12);

            border: 2px solid rgba(0, 255, 166, 0.35);

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;

            animation: iconPop 0.7s ease 0.2s both;
        }

        @keyframes iconPop {

            from {
                transform: scale(0);
                opacity: 0;
            }

            70% {
                transform: scale(1.1);
            }

            to {
                transform: scale(1);
                opacity: 1;
            }

        }

        .success-icon::before {

            content: "";

            position: absolute;

            inset: -9px;

            border-radius: 50%;

            border: 1px solid rgba(0, 255, 166, 0.18);

            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0% {
                transform: scale(0.9);
                opacity: 1;
            }

            100% {
                transform: scale(1.3);
                opacity: 0;
            }

        }

        .check {
            width: 35px;
            height: 20px;

            border-left: 5px solid #4dffc2;
            border-bottom: 5px solid #4dffc2;

            transform: rotate(-45deg);

            margin-top: -7px;

            animation: checkDraw 0.5s ease 0.7s both;
        }

        @keyframes checkDraw {

            from {
                opacity: 0;
                transform: rotate(-45deg) scale(0.4);
            }

            to {
                opacity: 1;
                transform: rotate(-45deg) scale(1);
            }

        }

        /* Text */

        .title {
            font-size: 29px;
            font-weight: 700;

            margin-bottom: 8px;

            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #a9c7c1;

            font-size: 14px;

            margin-bottom: 27px;
        }

        /* Amount */

        .amount-box {

            padding: 22px 15px;

            border-radius: 20px;

            background:
                linear-gradient(135deg,
                    rgba(0, 255, 170, 0.13),
                    rgba(0, 190, 255, 0.07));

            border: 1px solid rgba(91, 255, 207, 0.16);

            margin-bottom: 22px;
        }

        .amount-label {

            color: #9dbab4;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 1.5px;

            margin-bottom: 7px;
        }

        .amount {

            font-size: 42px;

            font-weight: 800;

            color: #66ffd0;

            letter-spacing: -1px;
        }

        /* Transaction details */

        .details {

            text-align: left;

            background: rgba(0, 0, 0, 0.15);

            border-radius: 18px;

            padding: 8px 18px;

            margin-bottom: 20px;
        }

        .detail-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding: 13px 0;

            border-bottom: 1px solid rgba(255, 255, 255, 0.07);

        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {

            color: #8fa9a4;

            font-size: 12px;
        }

        .detail-value {

            color: #e8fffa;

            font-size: 13px;

            font-weight: 600;

            text-align: right;

            max-width: 60%;

            word-break: break-word;
        }

        .token {

            color: #70ffd4;

            font-family: monospace;

            font-size: 11px;
        }

        /* Status */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 7px 13px;

            border-radius: 30px;

            background: rgba(0, 255, 150, 0.09);

            color: #6dffca;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .status-dot {

            width: 7px;
            height: 7px;

            background: #45ffc0;

            border-radius: 50%;

            box-shadow: 0 0 10px #45ffc0;
        }

        /* Buttons */

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 22px;
        }

        .btn {

            flex: 1;

            border: none;

            padding: 13px 15px;

            border-radius: 13px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 700;

            transition: 0.25s ease;

            text-decoration: none;
        }

        .btn-primary {

            background: linear-gradient(135deg,
                    #43e6b0,
                    #22c997);

            color: #03231b;

            box-shadow: 0 8px 25px rgba(40, 220, 165, 0.18);
        }

        .btn-primary:hover {

            transform: translateY(-2px);

            box-shadow: 0 12px 30px rgba(40, 220, 165, 0.3);
        }

        .btn-secondary {

            background: rgba(255, 255, 255, 0.06);

            color: #d9efeb;

            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {

            background: rgba(255, 255, 255, 0.11);

            transform: translateY(-2px);
        }

        /* Footer */

        .footer {

            margin-top: 22px;

            color: #69837d;

            font-size: 10px;

            letter-spacing: 0.5px;
        }

        /* Mobile */

        @media(max-width: 500px) {

            body {
                padding: 15px;
            }

            .success-card {
                padding: 28px 20px 22px;
            }

            .success-icon {
                width: 85px;
                height: 85px;
            }

            .title {
                font-size: 25px;
            }

            .amount {
                font-size: 36px;
            }

            .detail-value {
                max-width: 55%;
            }

        }
    </style>

</head>

<body>

    <div class="glow one"></div>
    <div class="glow two"></div>

    <div class="success-card">

        <div class="logo">
            ₹ <span>MBD</span> PAY
        </div>

        <div class="success-icon">
            <div class="check"></div>
        </div>

        <h1 class="title">
            Payment Successful
        </h1>

        <p class="subtitle">
            Your payment has been successfully completed.
        </p>

        <div class="amount-box">

            <div class="amount-label">
                Amount Received
            </div>

            <div class="amount">
                ₹<?php echo e($formattedAmount); ?>
            </div>

        </div>

        <div class="details">

            <div class="detail-row">

                <span class="detail-label">
                    Transaction ID
                </span>

                <span class="detail-value token">
                    <?php echo e($trx_id); ?>
                </span>

            </div>

            <div class="detail-row">

                <span class="detail-label">
                    From
                </span>

                <span class="detail-value">
                    <?php echo e(maskMobile($trx_sender_mobile)); ?>
                </span>

            </div>

            <div class="detail-row">

                <span class="detail-label">
                    To
                </span>

                <span class="detail-value">
                    <?php echo e(maskMobile($trx_receiver_mobile)); ?>
                </span>

            </div>

            <div class="detail-row">

                <span class="detail-label">
                    Date
                </span>

                <span class="detail-value">
                    <?php echo e($formattedDate); ?>
                </span>

            </div>

            <div class="detail-row">

                <span class="detail-label">
                    Time
                </span>

                <span class="detail-value">
                    <?php echo e($formattedTime); ?>
                </span>

            </div>

        </div>

        <div class="status">

            <span class="status-dot"></span>

            Payment Completed

        </div>

        <div class="buttons">

            <button
                class="btn btn-secondary"
                onclick="window.print()">

                Print Receipt

            </button>

            <a
                href="qr_scanner.php"
                class="btn btn-primary">

                Done

            </a>

        </div>

    </div>

</body>

</html>