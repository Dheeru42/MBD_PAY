<?php

session_start();


$conn = @mysqli_connect(
    "localhost",
    "root",
    "",
    "ram_pay"
);

// secret key
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


if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $_SESSION['user'] = $_COOKIE['remember_user'];
}

if (!isset($_SESSION['account'])) {
    header("location:index.php");
    exit;
}

$u_account = $_SESSION['account'];

if (!isset($_SESSION['mobile'])) {
    header("location:index.php");
    exit;
}

$u_mobile = $_SESSION['mobile'];

if (!isset($_SESSION['mode'])) {
    header("location:index.php");
    exit;
}

$transactions = [];
$message = "";
$message1 = "";

$account_number = "";

$total_credit = 0;
$total_debit = 0;

$current_balance = 0;
$account_holder = "";

try {
    // Fetch Account Details

    $accountSql =
        "SELECT balance,name 
     FROM users 
     WHERE mobile=?";


    $stmt = mysqli_prepare($conn, $accountSql);


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $u_mobile
    );


    mysqli_stmt_execute($stmt);



    $accountResult = mysqli_stmt_get_result($stmt);



    if (mysqli_num_rows($accountResult) == 1) {


        $account = mysqli_fetch_assoc($accountResult);


        $current_balance = decryptData($account['balance']);

        $account_holder = $account['name'];



        // Fetch Transactions


        $sql =
            "SELECT 
        transaction_id,
        mobile,
        type,
        amount,
        balance_before,
        balance_after,
        status,
        description,
        created_at

        FROM transactions

        WHERE mobile=?

        ORDER BY created_at DESC";



        $stmt2 = mysqli_prepare(
            $conn,
            $sql
        );



        mysqli_stmt_bind_param(
            $stmt2,
            "s",
            $u_mobile
        );



        mysqli_stmt_execute($stmt2);


        $transactions = mysqli_stmt_get_result($stmt2);


        // Calculate Total Credit and Debit

        while ($row = mysqli_fetch_assoc($transactions)) {


            if ($row['type'] == "Credit") {

                $total_credit += decryptData($row['amount']);
            } elseif ($row['type'] == "Debit") {

                $total_debit += decryptData($row['amount']);
            } elseif ($row['type'] == "Transfer") {

                $total_debit += decryptData($row['amount']);
            } elseif ($row['type'] == "Currency Generated") {

                $total_debit += decryptData($row['amount']);
            } elseif ($row['type'] == "Currency Received") {

                $total_debit += decryptData($row['amount']);
            }
        }


        // Reset pointer for table display

        mysqli_data_seek($transactions, 0);
    } else {

        $message1 = "Account Not Found.";
    }
} catch (\Throwable $th) {
    $message1 = "Account Not Found.";
}


?>



<!DOCTYPE html>

<html>

<head>


    <meta charset="UTF-8">


    <title>MBD PAY | Transaction</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">



    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }


        body {
            background: #eef2f7;
        }


        /* MAIN */

        .container {

            width: 95%;
            max-width: 1500px;
            margin: 35px auto;
            background: #fff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .12);

        }


        /* HEADER */

        .statement-header {

            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;

        }


        .statement-header h1 {

            color: #003366;
            font-size: 32px;

        }


        .bank-label {

            color: #777;
            font-size: 14px;

        }



        /* SEARCH */


        .search-box {

            background: #f7f9fc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;

        }


        form {

            display: flex;
            gap: 15px;

        }


        input {

            flex: 1;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;

        }


        input:focus {

            border-color: #0066cc;
            outline: none;

        }


        button {

            background: #0066cc;
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;

        }


        button:hover {

            background: #004b99;

        }



        /* ACCOUNT PROFILE */


        .account-box {

            background: linear-gradient(135deg, #003366, #0073e6);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;

        }


        .account-box h2 {

            font-size: 28px;
            margin-bottom: 10px;

        }


        .account-box p {

            opacity: .9;

        }



        /* SUMMARY */


        .summary {

            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;

        }


        .summary-card {

            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
            border-left: 5px solid #0066cc;

        }


        .summary-card h4 {

            color: #666;
            margin-bottom: 12px;

        }


        .summary-card h2 {

            color: #003366;

        }



        .credit-card {

            border-left-color: #008000;

        }


        .debit-card {

            border-left-color: #d00000;

        }



        /* TABLE */


        .table-container {

            overflow-x: auto;

        }


        table {

            width: 100%;
            border-collapse: collapse;

        }


        thead {

            background: #003366;
            color: white;

        }


        th {

            padding: 15px;
            font-size: 14px;

        }


        td {

            padding: 14px;
            border-bottom: 1px solid #ddd;
            text-align: center;
            font-size: 14px;

        }


        tbody tr:hover {

            background: #f5f9ff;

        }



        /* BADGES */


        .badge {

            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;

        }


        .credit {

            background: #d8f8df;
            color: #008000;

        }


        .debit {

            background: #ffe0e0;
            color: #d00000;

        }


        .transfer {

            background: #dcecff;
            color: #0066cc;

        }

        .currency {

            background: #d7d3ce;
            color: #e26e09;

        }

        .withdrawl {

            background: #dcecff;
            color: #908235;

        }



        .success {

            background: #d8f8df;
            color: #008000;

        }


        .failed {

            background: #ffe0e0;
            color: #d00000;

        }



        /* ACTION BUTTONS */


        .actions {

            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;

        }


        .action-btn {

            background: #003366;
            padding: 12px 25px;
            color: white;
            border-radius: 8px;
            text-decoration: none;

        }



        .message {

            background: #1be332;
            color: red;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;

        }

        .message1 {

            background: #ef0202;
            color: red;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;

        }



        /* MOBILE */


        @media(max-width:900px) {


            .summary {

                grid-template-columns: 1fr;

            }


            form {

                flex-direction: column;

            }


            .statement-header {

                flex-direction: column;
                align-items: flex-start;

            }

        }
    </style>


</head>



<body>


    <?php require 'navbar.php'; ?>



    <div class="container">

        <?php

        if ($message != "") {

            echo "<div class='message'>$message</div>";
        }

        ?>

        <?php

        if ($message1 != "") {

            echo "<div class='message1'>$message1</div>";
        }

        ?>

        <?php

        if (isset($account)) {

        ?>

            <div class="statement-header">

                <div>

                    <h1>Account Statement</h1>

                    <p class="bank-label">
                        MBD PAY • Official Digital Statement
                    </p>

                </div>


                <div>

                    <button onclick="window.print()">
                        Print Statement
                    </button>

                </div>


            </div>




            <div class="account-box">

                <h2>
                    <?php echo $account_holder; ?>
                </h2>

                <p>
                    Mobile Number : <?php echo $u_mobile; ?>
                </p>

                <p>
                    Available Balance :
                    ₹ <?php echo number_format($current_balance, 2); ?>
                </p>


            </div>





            <div class="summary">


                <div class="summary-card credit-card">

                    <h4>Total Credit</h4>

                    <h2>
                        ₹ <?php echo number_format($total_credit, 2); ?>
                    </h2>

                </div>




                <div class="summary-card debit-card">

                    <h4>Total Debit</h4>

                    <h2>
                        ₹ <?php echo number_format($total_debit, 2); ?>
                    </h2>

                </div>





                <div class="summary-card">

                    <h4>Current Balance</h4>

                    <h2>
                        ₹ <?php echo number_format($current_balance, 2); ?>
                    </h2>

                </div>



            </div>





            <div class="table-container">


                <table>


                    <thead>

                        <tr>

                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance Before</th>
                            <th>Balance After</th>
                            <th>Status</th>
                            <th>Description</th>

                        </tr>

                    </thead>



                    <tbody>



                        <?php


                        if (mysqli_num_rows($transactions) > 0) {

                            while ($row = mysqli_fetch_assoc($transactions)) {
                                // decrypted balance and amount 
                                $d_amount = decryptData($row['amount']);
                                $d_balance_before = decryptData($row['balance_before']);
                                $d_balance_after = decryptData($row['balance_after']);

                                $type = "";

                                if ($row['type'] == "Credit") {

                                    $type = "credit";
                                } elseif ($row['type'] == "Debit") {

                                    $type = "debit";
                                } elseif ($row['type'] == "Currency Received") {

                                    $type = "withdrawl";
                                } elseif ($row['type'] == "Currency Generated") {

                                    $type = "currency";
                                } else {

                                    $type = "transfer";
                                }



                                $status =
                                    $row['status'] == "Success"
                                    ?
                                    "success"
                                    :
                                    "failed";

                        ?>


                                <tr>


                                    <td>
                                        #<?php echo $row['transaction_id']; ?>
                                    </td>



                                    <td>

                                        <?php

                                        echo date(
                                            "d M Y h:i A",
                                            strtotime($row['created_at'])
                                        );

                                        ?>

                                    </td>



                                    <td>

                                        <span class="badge <?php echo $type; ?>">

                                            <?php echo $type; ?>

                                        </span>

                                    </td>




                                    <td>

                                        ₹ <?php echo number_format($d_amount); ?>

                                    </td>




                                    <td>

                                        ₹ <?php echo number_format($d_balance_before); ?>

                                    </td>



                                    <td>

                                        ₹ <?php echo number_format($d_balance_after); ?>

                                    </td>




                                    <td>

                                        <span class="badge <?php echo $status; ?>">

                                            <?php echo $row['status']; ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?php echo $row['description']; ?>

                                    </td>



                                </tr>



                        <?php

                            }
                        } else {

                            echo "

<tr>

<td colspan='8'>

No Transactions Available

</td>

</tr>

";
                        }

                        ?>


                    </tbody>


                </table>


            </div>



        <?php } ?>

    </div>





    <?php require 'footer.php'; ?>

</body>

</html>