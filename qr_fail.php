<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

$result = $_SESSION['qr_result'] ?? null;

if (!is_array($result) || ($result['status'] ?? '') !== 'FAILED') {
    $result = [
        'transaction_id' => '—',
        'status' => 'FAILED',
        'reason' => 'Unable to process the QR code.',
        'amount' => null,
        'serial_no' => null,
        'sender_mobile' => null,
        'receiver_mobile' => null,
        'completed_at' => date('Y-m-d H:i:s')
    ];
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function maskMobile($mobile): string
{
    $mobile = preg_replace('/\D+/', '', (string)$mobile);
    if ($mobile === '') return 'Not available';
    if (strlen($mobile) <= 4) return str_repeat('*', strlen($mobile));
    return str_repeat('*', max(0, strlen($mobile) - 4)) . substr($mobile, -4);
}
function money($amount): string
{
    if ($amount === null || $amount === '') return '₹—';
    return '₹' . number_format((float)$amount, 2);
}
function formatDateTime($value): string
{
    if (!$value) return date('d M Y, h:i A');
    $time = strtotime((string)$value);
    return $time ? date('d M Y, h:i A', $time) : e($value);
}

$transactionId = $result['transaction_id'] ?? '—';
$reason = $result['reason'] ?? 'Unable to process the QR code.';
$amount = money($result['amount'] ?? null);
$serial = $result['serial_no'] ?? '—';
$sender = maskMobile($result['sender_mobile'] ?? '');
$receiver = maskMobile($result['receiver_mobile'] ?? '');
$completed = formatDateTime($result['completed_at'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#7f1d1d">
    <title>MBD PAY | Payment Failed</title>
    <link rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">
    <style>
        * {
            box-sizing: border-box
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: Inter, Arial, sans-serif
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at 15% 5%, #fee2e2 0, transparent 30%), linear-gradient(145deg, #fff 0%, #fff7f7 55%, #fee2e2 100%);
            color: #18201d;
            padding: 18px
        }

        .app {
            width: 100%;
            max-width: 520px;
            margin: auto;
            min-height: calc(100vh - 36px);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(127, 29, 29, .14)
        }

        .topbar {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #fee2e2
        }

        .back {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: #fff1f2;
            color: #991b1b;
            text-decoration: none;
            display: grid;
            place-items: center;
            font-size: 27px;
            font-weight: 800
        }

        .brand {
            font-size: 20px;
            font-weight: 950;
            letter-spacing: -.8px
        }

        .brand span {
            color: #dc2626
        }

        .live {
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #991b1b;
            background: #fee2e2;
            padding: 8px 10px;
            border-radius: 999px
        }

        .content {
            padding: 30px 20px 25px;
            text-align: center
        }

        .status-orbit {
            width: 124px;
            height: 124px;
            margin: 0 auto 22px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: conic-gradient(#ef4444 0 78%, #fee2e2 78% 100%);
            box-shadow: 0 18px 45px rgba(220, 38, 38, .18);
            position: relative;
            animation: float 3s ease-in-out infinite
        }

        .status-orbit:before {
            content: "";
            position: absolute;
            inset: 9px;
            border-radius: 50%;
            background: #fff7f7
        }

        .fail-icon {
            position: relative;
            z-index: 1;
            width: 76px;
            height: 76px;
            border-radius: 24px;
            background: linear-gradient(145deg, #dc2626, #991b1b);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 45px;
            font-weight: 900;
            box-shadow: 0 12px 25px rgba(153, 27, 27, .25)
        }

        @keyframes float {
            50% {
                transform: translateY(-5px)
            }
        }

        h1 {
            margin: 0;
            color: #7f1d1d;
            font-size: 30px;
            letter-spacing: -1px
        }

        .subtitle {
            margin: 8px 0 24px;
            color: #64748b;
            font-size: 14px
        }

        .alert {
            display: flex;
            gap: 12px;
            text-align: left;
            padding: 15px;
            border-radius: 18px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            margin-bottom: 16px
        }

        .alert-icon {
            width: 34px;
            height: 34px;
            flex: none;
            border-radius: 11px;
            background: #dc2626;
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 900
        }

        .alert b {
            display: block;
            color: #991b1b;
            font-size: 12px;
            margin-bottom: 3px
        }

        .alert span {
            font-size: 13px;
            color: #7f1d1d;
            line-height: 1.45
        }

        .card {
            background: #fff;
            border: 1px solid #f3e4e4;
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .06);
            text-align: left
        }

        .amount-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 17px;
            border-bottom: 1px dashed #eadada
        }

        .caption {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800
        }

        .amount {
            font-size: 30px;
            font-weight: 950;
            color: #991b1b;
            margin-top: 4px
        }

        .badge {
            padding: 8px 11px;
            border-radius: 999px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 950
        }

        .section {
            font-size: 12px;
            color: #991b1b;
            font-weight: 950;
            margin: 18px 0 5px;
            text-transform: uppercase;
            letter-spacing: .8px
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 0
        }

        .row+.row {
            border-top: 1px solid #f8eeee
        }

        .key {
            font-size: 12px;
            color: #64748b
        }

        .value {
            font-size: 12px;
            font-weight: 800;
            text-align: right;
            max-width: 62%;
            word-break: break-word
        }

        .actions {
            display: grid;
            gap: 10px;
            margin-top: 17px
        }

        .try,
        .home {
            height: 52px;
            border-radius: 16px;
            text-decoration: none;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 14px
        }

        .try {
            background: linear-gradient(135deg, #991b1b, #dc2626);
            color: #fff;
            box-shadow: 0 12px 24px rgba(220, 38, 38, .2)
        }

        .home {
            background: #fff;
            border: 1px solid #fecaca;
            color: #7f1d1d
        }



        .professional-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 18px;
        }

        .action-btn {
            min-height: 68px;
            padding: 11px 12px;
            border-radius: 18px;
            text-decoration: none;
            border: 1px solid #f3dddd;
            display: flex;
            align-items: center;
            gap: 11px;
            text-align: left;
            font-family: inherit;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, .09);
        }

        .primary-action {
            background: linear-gradient(135deg, #991b1b, #dc2626);
            color: #fff;
            border-color: #991b1b;
            box-shadow: 0 10px 22px rgba(220, 38, 38, .16);
        }

        .secondary-action {
            background: #fff;
            color: #18201d;
        }

        .secondary-action:hover {
            border-color: #fecaca;
        }

        .action-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 900;
            background: #fff1f2;
            color: #991b1b;
        }

        .primary-action .action-icon {
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        .action-btn b {
            display: block;
            font-size: 12px;
            line-height: 1.2;
        }

        .action-btn small {
            display: block;
            margin-top: 4px;
            color: #94a3b8;
            font-size: 10px;
            line-height: 1.25;
        }

        .primary-action small {
            color: rgba(255, 255, 255, .78);
        }

        @media(max-width:420px) {
            .professional-actions {
                gap: 9px;
            }

            .action-btn {
                min-height: 64px;
                padding: 9px;
                gap: 8px;
            }

            .action-icon {
                width: 34px;
                height: 34px;
                flex-basis: 34px;
                font-size: 18px;
            }

            .action-btn b {
                font-size: 11px;
            }

            .action-btn small {
                font-size: 9px;
            }
        }

        .utility-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }

        .utility-btn {
            height: 48px;
            border-radius: 15px;
            border: 1px solid #fecaca;
            background: #fff7f7;
            color: #991b1b;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .utility-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(220, 38, 38, .12);
        }

        .share-btn {
            background: #991b1b;
            color: #fff;
            border-color: #991b1b;
        }

        .utility-message {
            min-height: 16px;
            margin: 7px 0 0;
            text-align: center;
            color: #64748b;
            font-size: 11px;
        }

        .footer-note {
            margin: 15px 0 0;
            color: #94a3b8;
            font-size: 11px
        }

        @media(max-width:420px) {
            body {
                padding: 8px
            }

            .app {
                min-height: calc(100vh - 16px);
                border-radius: 25px
            }

            .content {
                padding: 25px 14px 20px
            }

            h1 {
                font-size: 27px
            }
        }

        @media print {
            body {
                padding: 0;
                background: #fff
            }

            .app {
                box-shadow: none;
                border: 0;
                max-width: none
            }

            .topbar,
            .actions,
            .footer-note {
                display: none
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <header class="topbar">
            <a class="back" href="qr_scanner.php" aria-label="Back">‹</a>
            <div class="brand">MBD <span>PAY</span></div>
            <div class="live">SECURE</div>
        </header>
        <main class="content">
            <div class="status-orbit">
                <div class="fail-icon">×</div>
            </div>
            <h1>Payment Unsuccessful</h1>
            <p class="subtitle">Your transaction could not be completed.</p>

            <div class="alert">
                <div class="alert-icon">!</div>
                <div><b>WHAT HAPPENED?</b><span><?= e($reason) ?></span></div>
            </div>

            <section class="card">
                <div class="amount-head">
                    <div>
                        <div class="caption">Transaction Amount</div>
                        <div class="amount"><?= e($amount) ?></div>
                    </div>
                    <div class="badge">FAILED</div>
                </div>

                <div class="section">Transaction details</div>
                <div class="row"><span class="key">Transaction ID</span><span class="value"><?= e($transactionId) ?></span></div>
                <div class="row"><span class="key">Currency Serial No.</span><span class="value"><?= e($serial) ?></span></div>
                <div class="row"><span class="key">From (Sender)</span><span class="value"><?= e($sender) ?></span></div>
                <div class="row"><span class="key">To (Receiver)</span><span class="value"><?= e($receiver) ?></span></div>
                <div class="row"><span class="key">Date &amp; Time</span><span class="value"><?= e($completed) ?></span></div>
                <div class="row"><span class="key">Status</span><span class="value" style="color:#dc2626">Payment Failed</span></div>
            </section>

            <div class="actions professional-actions">
                <a class="action-btn primary-action" href="qr_scanner.php">
                    <span class="action-icon">↻</span>
                    <span><b>Scan Again</b><small>Try another QR code</small></span>
                </a>
                <a class="action-btn secondary-action" href="index.php">
                    <span class="action-icon">⌂</span>
                    <span><b>Back to Home</b><small>Return to dashboard</small></span>
                </a>
                <button type="button" class="action-btn secondary-action" onclick="shareReceipt()">
                    <span class="action-icon">↗</span>
                    <span><b>Share Receipt</b><small>Send transaction details</small></span>
                </button>
                <button type="button" class="action-btn primary-action" onclick="printReceipt()">
                    <span class="action-icon">▣</span>
                    <span><b>Print Receipt</b><small>Print or save as PDF</small></span>
                </button>
            </div>
            <p id="utilityMessage" class="utility-message"></p>
            <p class="footer-note">Keep your Transaction ID if you need support.</p>
        </main>
    </div>

    <script>
        const receiptData = {
            title: "MBD PAY - Failed Transaction Receipt",
            status: "Payment Failed",
            amount: <?= json_encode($amount) ?>,
            transactionId: <?= json_encode((string)$transactionId) ?>,
            serial: <?= json_encode((string)$serial) ?>,
            sender: <?= json_encode((string)$sender) ?>,
            receiver: <?= json_encode((string)$receiver) ?>,
            dateTime: <?= json_encode((string)$completed) ?>,
            reason: <?= json_encode((string)$reason) ?>
        };

        function receiptText() {
            return [
                "MBD PAY - TRANSACTION RECEIPT",
                "-----------------------------",
                "Status: " + receiptData.status,
                "Reason: " + receiptData.reason,
                "Amount: " + receiptData.amount,
                "Transaction ID: " + receiptData.transactionId,
                "Currency Serial No.: " + receiptData.serial,
                "From (Sender): " + receiptData.sender,
                "To (Receiver): " + receiptData.receiver,
                "Date & Time: " + receiptData.dateTime
            ].join("\n");
        }

        async function shareReceipt() {
            const text = receiptText();
            const message = document.getElementById("utilityMessage");

            if (navigator.share) {
                try {
                    await navigator.share({
                        title: receiptData.title,
                        text: text
                    });
                    message.textContent = "Receipt shared successfully.";
                    return;
                } catch (error) {
                    if (error && error.name === "AbortError") return;
                }
            }

            try {
                await navigator.clipboard.writeText(text);
                message.textContent = "Receipt copied. Paste it into WhatsApp, SMS or email.";
            } catch (error) {
                window.prompt("Copy this receipt:", text);
            }
        }

        function printReceipt() {
            window.print();
        }
    </script>

</body>

</html>