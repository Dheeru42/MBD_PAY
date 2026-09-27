<?php

session_start();

// if (!isset($_SESSION['failed_transaction'])) {
//     header("Location: index.php");
//     exit;
// }

$transaction = $_SESSION['failed_transaction'];

$tr_token_id         = $transaction['token_id'] ?? '';
$trx_amount          = $transaction['amount'] ?? 0;
$trx_sender_mobile   = $transaction['sender_mobile'] ?? '';
$trx_receiver_mobile = $transaction['receiver_mobile'] ?? '';
$trx_timestamp       = $transaction['timestamp'] ?? date('Y-m-d H:i:s');
$trx_reason          = $transaction['reason'] ?? 'Transaction could not be completed.';

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

$formattedDate = date(
    "d M Y",
    strtotime($trx_timestamp)
);

$formattedTime = date(
    "h:i A",
    strtotime($trx_timestamp)
);

session_abort();
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Payment Failed | MBD Pay</title>

    <style>
        /* =========================
   BASIC RESET
========================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            min-height: 100vh;

            background:
                radial-gradient(circle at 20% 20%,
                    rgba(255, 70, 70, 0.12),
                    transparent 30%),
                radial-gradient(circle at 80% 80%,
                    rgba(255, 130, 50, 0.10),
                    transparent 30%),
                linear-gradient(135deg,
                    #1b0808,
                    #2b0d0d,
                    #120404);

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 25px;

            color: white;

        }


        /* =========================
   BACKGROUND GLOW
========================= */

        .glow {

            position: fixed;

            width: 300px;

            height: 300px;

            border-radius: 50%;

            background:
                rgba(255, 50, 50, 0.08);

            filter: blur(75px);

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


        /* =========================
   MAIN CARD
========================= */

        .failed-card {

            position: relative;

            z-index: 2;

            width: 100%;

            max-width: 470px;

            padding: 35px 30px 30px;

            border-radius: 28px;

            background:
                rgba(255, 255, 255, 0.065);

            border:
                1px solid rgba(255, 255, 255, 0.11);

            backdrop-filter:
                blur(20px);

            box-shadow:

                0 25px 80px rgba(0, 0, 0, 0.5),

                inset 0 1px 0 rgba(255, 255, 255, 0.06);

            text-align: center;

            animation:
                cardEnter 0.7s ease;

        }

        @keyframes cardEnter {

            from {

                opacity: 0;

                transform:
                    translateY(30px) scale(0.96);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0) scale(1);

            }

        }


        /* =========================
   LOGO
========================= */

        .logo {

            font-size: 18px;

            font-weight: bold;

            letter-spacing: 2px;

            color: #ff7777;

            margin-bottom: 25px;

        }

        .logo span {

            color: white;

        }


        /* =========================
   FAILED ICON
========================= */

        .failed-icon {

            width: 100px;

            height: 100px;

            margin:
                0 auto 22px;

            border-radius: 50%;

            background:
                rgba(255, 65, 65, 0.10);

            border:
                2px solid rgba(255, 75, 75, 0.35);

            display: flex;

            align-items: center;

            justify-content: center;

            position: relative;

            animation:
                iconPop 0.7s ease 0.2s both;

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


        .failed-icon::before {

            content: "";

            position: absolute;

            inset: -9px;

            border-radius: 50%;

            border:
                1px solid rgba(255, 70, 70, 0.18);

            animation:
                pulse 2s infinite;

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


        /* =========================
   CROSS
========================= */

        .cross {

            position: relative;

            width: 35px;

            height: 35px;

        }

        .cross::before,
        .cross::after {

            content: "";

            position: absolute;

            left: 16px;

            top: 1px;

            width: 5px;

            height: 35px;

            border-radius: 5px;

            background:
                #ff6868;

        }

        .cross::before {

            transform:
                rotate(45deg);

        }

        .cross::after {

            transform:
                rotate(-45deg);

        }


        /* =========================
   HEADING
========================= */

        .title {

            font-size: 29px;

            font-weight: 700;

            margin-bottom: 8px;

        }

        .subtitle {

            color: #c5a7a7;

            font-size: 14px;

            margin-bottom: 27px;

        }


        /* =========================
   AMOUNT
========================= */

        .amount-box {

            padding:
                22px 15px;

            border-radius: 20px;

            background:
                linear-gradient(135deg,
                    rgba(255, 65, 65, 0.11),
                    rgba(255, 120, 50, 0.06));

            border:
                1px solid rgba(255, 100, 100, 0.14);

            margin-bottom: 20px;

        }

        .amount-label {

            color: #b99b9b;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 1.5px;

            margin-bottom: 7px;

        }

        .amount {

            font-size: 40px;

            font-weight: 800;

            color: #ff8585;

        }


        /* =========================
   FAILURE REASON
========================= */

        .reason-box {

            padding:
                14px 16px;

            border-radius: 14px;

            background:
                rgba(255, 65, 65, 0.07);

            border:
                1px solid rgba(255, 80, 80, 0.12);

            margin-bottom: 20px;

            text-align: left;

        }

        .reason-title {

            color: #ff8585;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 5px;

        }

        .reason {

            color: #e9cccc;

            font-size: 13px;

            line-height: 1.5;

        }


        /* =========================
   DETAILS
========================= */

        .details {

            text-align: left;

            background:
                rgba(0, 0, 0, 0.15);

            border-radius: 18px;

            padding:
                8px 18px;

            margin-bottom: 20px;

        }

        .detail-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding: 13px 0;

            border-bottom:
                1px solid rgba(255, 255, 255, 0.07);

        }

        .detail-row:last-child {

            border-bottom: none;

        }

        .detail-label {

            color: #a18d8d;

            font-size: 12px;

        }

        .detail-value {

            color: #f3dddd;

            font-size: 13px;

            font-weight: 600;

            text-align: right;

            max-width: 60%;

            word-break: break-word;

        }

        .token {

            color: #ff9b9b;

            font-family: monospace;

            font-size: 11px;

        }


        /* =========================
   STATUS
========================= */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                7px 13px;

            border-radius: 30px;

            background:
                rgba(255, 60, 60, 0.09);

            color: #ff8585;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1px;

        }

        .status-dot {

            width: 7px;

            height: 7px;

            background:
                #ff6666;

            border-radius: 50%;

            box-shadow:
                0 0 10px #ff5555;

        }


        /* =========================
   BUTTONS
========================= */

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 22px;

        }

        .btn {

            flex: 1;

            border: none;

            padding:
                13px 15px;

            border-radius: 13px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 700;

            transition:
                0.25s ease;

            text-decoration: none;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        .btn-primary {

            background:
                linear-gradient(135deg,
                    #ff6868,
                    #e84848);

            color: white;

            box-shadow:
                0 8px 25px rgba(255, 60, 60, 0.18);

        }

        .btn-primary:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 12px 30px rgba(255, 60, 60, 0.3);

        }

        .btn-secondary {

            background:
                rgba(255, 255, 255, 0.06);

            color:
                #eadada;

            border:
                1px solid rgba(255, 255, 255, 0.1);

        }

        .btn-secondary:hover {

            background:
                rgba(255, 255, 255, 0.11);

            transform:
                translateY(-2px);

        }


        /* =========================
   FOOTER
========================= */

        .footer {

            margin-top: 22px;

            color:
                #806c6c;

            font-size: 10px;

            letter-spacing: 0.5px;

        }


        /* =========================
   MOBILE
========================= */

        @media(max-width: 500px) {

            body {

                padding: 15px;

            }

            .failed-card {

                padding:
                    28px 20px 22px;

            }

            .failed-icon {

                width: 85px;

                height: 85px;

            }

            .title {

                font-size: 25px;

            }

            .amount {

                font-size: 35px;

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

    <div class="failed-card">

        <!-- LOGO -->

        <div class="logo">

            ₹ <span>MBD</span> PAY

        </div>


        <!-- FAILED ICON -->

        <div class="failed-icon">

            <div class="cross"></div>

        </div>


        <!-- TITLE -->

        <h1 class="title">

            Payment Failed

        </h1>


        <p class="subtitle">

            We couldn't complete this payment.

        </p>


        <!-- AMOUNT -->

        <div class="amount-box">

            <div class="amount-label">

                Payment Amount

            </div>

            <div class="amount">

                ₹ <?php echo e($formattedAmount); ?>

            </div>

        </div>


        <!-- FAILURE REASON -->

        <div class="reason-box">

            <div class="reason-title">

                Reason

            </div>

            <div class="reason">

                <?php echo e($trx_reason); ?>

            </div>

        </div>


        <!-- TRANSACTION DETAILS -->

        <div class="details">


            <div class="detail-row">

                <span class="detail-label">

                    From

                </span>

                <span class="detail-value">

                    <?php

                    echo e(
                        maskMobile($trx_sender_mobile)
                    );

                    ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">

                    To

                </span>

                <span class="detail-value">

                    <?php

                    echo e(
                        maskMobile($trx_receiver_mobile)
                    );

                    ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">

                    Transaction ID

                </span>

                <span class="detail-value token">

                    <?php echo e($tr_token_id); ?>

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


        <!-- STATUS -->

        <div class="status">

            <span class="status-dot"></span>

            Transaction Failed

        </div>


        <!-- BUTTONS -->

        <div class="buttons">


            <a
                href="index.php"
                class="btn btn-secondary">

                Back

            </a>


            <a
                href="payment.php"
                class="btn btn-primary">

                Try Again

            </a>


        </div>

    </div>

</body>

</html>