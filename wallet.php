<?php
session_start();

require 'conn.php';
require 'bank_conn.php';


$mess = "";
$mess_f = "";

if (isset($_SESSION['message_pay'])) {
    $mess = $_SESSION['message_pay'];
    unset($_SESSION['message_pay']);
}

if (isset($_SESSION['message_pay_f'])) {
    $mess_f = $_SESSION['message_pay_f'];
    unset($_SESSION['message_pay_f']);
}

// catch create logic

define("CACHE_DIR", __DIR__ . "/cache/users/");
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


$message = '';
$message1 = "";

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

if (!isset($_SESSION['mode'])) {
    header("location:index.php");
    exit;
}

// make serever connection
try {
    $mobile = $_SESSION['mobile'];
    $stmt = mysqli_prepare($conn, "SELECT name,balance FROM users WHERE mobile=? AND account_no = ?");
    mysqli_stmt_bind_param($stmt, "ss", $mobile, $u_account);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    $_SESSION['balance'] = decryptData($user['balance']);
    $e_u_bal = encryptData($_SESSION['balance']);
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


// add money to wallet
try {
    if (isset($_POST['add_money'])) {
        $mobile = $_SESSION['mobile'];
        $amount = floatval($_POST['amount']);
        $e_amount = encryptData($amount);

        if ($amount <= 0) {
            $message = "Invalid Amount";
        } else {
            if ($conn && $bank_conn) {
                // bank balance balace
                $bankSql = "SELECT balance FROM users WHERE mobile='$mobile' AND account_number = '$u_account'";
                $b_result = mysqli_query($bank_conn, $bankSql);

                if ($w_user = mysqli_fetch_assoc($b_result)) {
                    $available_wallet = decryptData($w_user['balance']);
                    $e_available_wallet = encryptData($available_wallet);
                    $s_u_balance = encryptData($available_wallet - $amount);
                    if ($available_wallet >= $amount) {

                        // Debit from Bank
                        $bankQuery = "UPDATE users SET balance = ? WHERE account_number = ?";
                        $stmt = mysqli_prepare($bank_conn, $bankQuery);
                        mysqli_stmt_bind_param($stmt, "ss", $s_u_balance, $u_account);
                        mysqli_stmt_execute($stmt);

                        if (mysqli_stmt_affected_rows($stmt) > 0) {
                            $e_u_bal1 = $_SESSION['balance'];
                            $e_m_u_bal = encryptData($e_u_bal1 + $amount);

                            // Credit to MBD Wallet
                            $walletQuery = "UPDATE users SET balance = ? WHERE mobile = ?";

                            $stmt2 = mysqli_prepare($conn, $walletQuery);
                            mysqli_stmt_bind_param($stmt2, "ss", $e_m_u_bal, $mobile);
                            mysqli_stmt_execute($stmt2);
                            if (mysqli_stmt_affected_rows($stmt2) > 0) {


                                // Generate transaction ID

                                $transaction_id = "MBD" . time() . rand(1000, 9999);


                                /*
        Update MBD Pay Transaction
    */


                                $walletBalanceQuery = "
        SELECT balance 
        FROM users 
        WHERE mobile='$mobile'
    ";

                                $walletResult = mysqli_query($conn, $walletBalanceQuery);

                                $walletData = mysqli_fetch_assoc($walletResult);

                                $wallet_balance = $walletData['balance'];



                                $walletTransaction = "
        INSERT INTO transactions
        (
        transaction_id,
        mobile,
        type,
        amount,
        balance_before,
        balance_after,
        description,
        status
        )
        VALUES
        (?,?,?,?,?,?,?,?)
    ";


                                $stmt3 = mysqli_prepare($conn, $walletTransaction);


                                $type = "Credit";
                                $st = 'Success';
                                $desc = "Wallet recharge from bank account";


                                mysqli_stmt_bind_param(
                                    $stmt3,
                                    "ssssssss",
                                    $transaction_id,
                                    $mobile,
                                    $type,
                                    $e_amount,
                                    $e_u_bal,
                                    $wallet_balance,
                                    $desc,
                                    $st
                                );


                                mysqli_stmt_execute($stmt3);



                                /*
        Update Bank Transaction
    */


                                $bankBalanceQuery = "
        SELECT balance 
        FROM users 
        WHERE account_number='$u_account'
    ";


                                $bankResult = mysqli_query($bank_conn, $bankBalanceQuery);

                                $bankData = mysqli_fetch_assoc($bankResult);


                                $bank_balance = $bankData['balance'];



                                $bankTransaction = "
        INSERT INTO transactions
        (
        transaction_id,
        account_number,
        transaction_type,
        amount,
        balance_before,
        balance_after,
        status,
        remarks
        )
        VALUES
        (?,?,?,?,?,?,?,?)
    ";



                                $stmt4 = mysqli_prepare($bank_conn, $bankTransaction);



                                $bankType = "Debit";
                                $bankDesc = "Money transferred to MBD Pay wallet";



                                mysqli_stmt_bind_param(
                                    $stmt4,
                                    "ssssssss",
                                    $transaction_id,
                                    $u_account,
                                    $bankType,
                                    $e_amount,
                                    $e_available_wallet,
                                    $bank_balance,
                                    $st,
                                    $bankDesc
                                );



                                mysqli_stmt_execute($stmt4);



                                /*
        Update Offline Cache
    */


                                $userId = hash("sha256", $mobile);

                                $file = "cache/users/$userId/profile.json";


                                if (file_exists($file)) {

                                    $data = json_decode(
                                        file_get_contents($file),
                                        true
                                    );

                                    $U_balance = $wallet_balance;

                                    $data['balance'] = $U_balance;

                                    $data['server_sync'] = true;

                                    $data['last_transaction'] = $transaction_id;


                                    file_put_contents(
                                        $file,
                                        json_encode(
                                            $data,
                                            JSON_PRETTY_PRINT
                                        )
                                    );
                                }


                                $message = "Money Added Successfully";
                                $_SESSION['message_pay'] = $message;
                                header("location:wallet.php");
                            } else {
                                $message1 =  "Wallet credit failed.";
                                $_SESSION['message_pay_f'] = $message1;
                                header("location:wallet.php");
                            }
                        }
                    } else {
                        $message1 = "Insufficient balance.";
                        $_SESSION['message_pay_f'] = $message1;
                        header("location:wallet.php");
                    }
                }
            }
        }
    }
} catch (\Throwable $th) {
    $message1 = "Please connect to the Internet.";
}



// ============================================================
// WITHDRAWAL MONEY LOGIC
// ============================================================

try {

    if (isset($_POST['wtdl_money'])) {

        /*
         * ========================================================
         * 1. GET USER INFORMATION
         * ========================================================
         */

        $mobile = $_SESSION['mobile'];
        $u_account = $_SESSION['account'];

        $amount = floatval($_POST['amount']);

        /*
         * Encrypt transaction amount
         */
        $e_amount = encryptData($amount);


        /*
         * ========================================================
         * 2. VALIDATE AMOUNT
         * ========================================================
         */

        if ($amount <= 0) {

            $message1 = "Invalid Amount";
        } else {

            /*
             * ====================================================
             * 3. CHECK SERVER CONNECTION
             * ====================================================
             */

            if ($conn && $bank_conn) {


                /*
                 * =================================================
                 * 4. GET MBD PAY WALLET BALANCE
                 * =================================================
                 *
                 * Database balance is encrypted.
                 * First decrypt it.
                 */

                $walletSql = "
                    SELECT balance
                    FROM users
                    WHERE mobile = ?
                    AND account_no = ?
                ";

                $stmtWallet = mysqli_prepare(
                    $conn,
                    $walletSql
                );

                mysqli_stmt_bind_param(
                    $stmtWallet,
                    "ss",
                    $mobile,
                    $u_account
                );

                mysqli_stmt_execute(
                    $stmtWallet
                );

                $walletResult =
                    mysqli_stmt_get_result(
                        $stmtWallet
                    );


                /*
                 * =================================================
                 * 5. CHECK WALLET USER
                 * =================================================
                 */

                if (
                    $walletUser =
                    mysqli_fetch_assoc($walletResult)
                ) {


                    /*
                     * =================================================
                     * 6. DECRYPT MBD WALLET BALANCE
                     * =================================================
                     */

                    $wallet_before =
                        decryptData(
                            $walletUser['balance']
                        );


                    /*
                     * =================================================
                     * 7. CHECK SUFFICIENT BALANCE
                     * =================================================
                     */

                    if ($wallet_before >= $amount) {


                        /*
                         * =================================================
                         * 8. CALCULATE NEW MBD WALLET BALANCE
                         * =================================================
                         */

                        $wallet_after =
                            $wallet_before - $amount;


                        /*
                         * Encrypt new wallet balance
                         */

                        $e_wallet_after =
                            encryptData(
                                $wallet_after
                            );


                        /*
                         * =================================================
                         * 9. GET BANK ACCOUNT BALANCE
                         * =================================================
                         */

                        $bankSql = "
                            SELECT balance
                            FROM users
                            WHERE account_number = ?
                            AND mobile = ?
                        ";

                        $stmtBank =
                            mysqli_prepare(
                                $bank_conn,
                                $bankSql
                            );

                        mysqli_stmt_bind_param(
                            $stmtBank,
                            "ss",
                            $u_account,
                            $mobile
                        );

                        mysqli_stmt_execute(
                            $stmtBank
                        );

                        $bankResult =
                            mysqli_stmt_get_result(
                                $stmtBank
                            );


                        /*
                         * =================================================
                         * 10. CHECK BANK ACCOUNT
                         * =================================================
                         */

                        if (
                            $bankUser =
                            mysqli_fetch_assoc(
                                $bankResult
                            )
                        ) {


                            /*
                             * =================================================
                             * 11. DECRYPT BANK BALANCE
                             * =================================================
                             */

                            $bank_before =
                                floatval(
                                    decryptData(
                                        $bankUser['balance']
                                    )
                                );


                            /*
                             * =================================================
                             * 12. CALCULATE BANK NEW BALANCE
                             * =================================================
                             *
                             * Withdrawal from MBD Pay means:
                             *
                             * MBD Pay  : - amount
                             * Bank     : + amount
                             */

                            $bank_after =
                                $bank_before + $amount;


                            /*
                             * Encrypt new bank balance
                             */

                            $e_bank_after =
                                encryptData(
                                    $bank_after
                                );


                            /*
                             * =================================================
                             * 13. UPDATE BANK BALANCE
                             * =================================================
                             */

                            $bankUpdateSql = "
                                UPDATE users
                                SET balance = ?
                                WHERE account_number = ?
                                AND mobile = ?
                            ";

                            $stmtBankUpdate =
                                mysqli_prepare(
                                    $bank_conn,
                                    $bankUpdateSql
                                );

                            mysqli_stmt_bind_param(
                                $stmtBankUpdate,
                                "sss",
                                $e_bank_after,
                                $u_account,
                                $mobile
                            );

                            mysqli_stmt_execute(
                                $stmtBankUpdate
                            );


                            /*
                             * =================================================
                             * 14. CHECK BANK UPDATE
                             * =================================================
                             */

                            if (
                                mysqli_stmt_affected_rows(
                                    $stmtBankUpdate
                                ) > 0
                            ) {


                                /*
                                 * =================================================
                                 * 15. UPDATE MBD PAY WALLET
                                 * =================================================
                                 */

                                $walletUpdateSql = "
                                    UPDATE users
                                    SET balance = ?
                                    WHERE mobile = ?
                                    AND account_no = ?
                                ";

                                $stmtWalletUpdate =
                                    mysqli_prepare(
                                        $conn,
                                        $walletUpdateSql
                                    );

                                mysqli_stmt_bind_param(
                                    $stmtWalletUpdate,
                                    "sss",
                                    $e_wallet_after,
                                    $mobile,
                                    $u_account
                                );

                                mysqli_stmt_execute(
                                    $stmtWalletUpdate
                                );


                                /*
                                 * =================================================
                                 * 16. CHECK WALLET UPDATE
                                 * =================================================
                                 */

                                if (
                                    mysqli_stmt_affected_rows(
                                        $stmtWalletUpdate
                                    ) > 0
                                ) {


                                    /*
                                     * =============================================
                                     * 17. UPDATE SESSION BALANCE
                                     * =============================================
                                     */

                                    $_SESSION['balance'] =
                                        $wallet_after;


                                    /*
                                     * =============================================
                                     * 18. GENERATE TRANSACTION ID
                                     * =============================================
                                     */

                                    $transaction_id =
                                        "MBD" .
                                        time() .
                                        rand(
                                            1000,
                                            9999
                                        );


                                    $status = "Success";


                                    /*
                                     * =============================================
                                     * 19. MBD PAY TRANSACTION
                                     * =============================================
                                     */

                                    $walletTransaction = "
                                        INSERT INTO transactions
                                        (
                                            transaction_id,
                                            mobile,
                                            type,
                                            amount,
                                            balance_before,
                                            balance_after,
                                            description,
                                            status
                                        )
                                        VALUES
                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?
                                        )
                                    ";

                                    $stmt3 =
                                        mysqli_prepare(
                                            $conn,
                                            $walletTransaction
                                        );


                                    $type =
                                        "Debit";


                                    $desc =
                                        "Withdrawal to MBD bank account";


                                    /*
                                     * Encrypt old wallet balance
                                     */

                                    $e_wallet_before =
                                        encryptData(
                                            $wallet_before
                                        );


                                    mysqli_stmt_bind_param(
                                        $stmt3,
                                        "ssssssss",
                                        $transaction_id,
                                        $mobile,
                                        $type,
                                        $e_amount,
                                        $e_wallet_before,
                                        $e_wallet_after,
                                        $desc,
                                        $status
                                    );


                                    mysqli_stmt_execute(
                                        $stmt3
                                    );


                                    /*
                                     * =============================================
                                     * 20. BANK TRANSACTION
                                     * =============================================
                                     */

                                    $bankTransaction = "
                                        INSERT INTO transactions
                                        (
                                            transaction_id,
                                            account_number,
                                            transaction_type,
                                            amount,
                                            balance_before,
                                            balance_after,
                                            status,
                                            remarks
                                        )
                                        VALUES
                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?
                                        )
                                    ";


                                    $stmt4 =
                                        mysqli_prepare(
                                            $bank_conn,
                                            $bankTransaction
                                        );


                                    $bankType =
                                        "Credit";


                                    $remark =
                                        "Money received from MBD Pay wallet";


                                    /*
                                     * Encrypt OLD bank balance
                                     */

                                    $e_bank_before =
                                        encryptData(
                                            $bank_before
                                        );


                                    /*
                                     * IMPORTANT:
                                     *
                                     * bank_before = old bank balance
                                     * bank_after  = old bank balance + amount
                                     */

                                    mysqli_stmt_bind_param(
                                        $stmt4,
                                        "ssssssss",
                                        $transaction_id,
                                        $u_account,
                                        $bankType,
                                        $e_amount,
                                        $e_bank_before,
                                        $e_bank_after,
                                        $status,
                                        $remark
                                    );


                                    mysqli_stmt_execute(
                                        $stmt4
                                    );


                                    /*
                                     * =============================================
                                     * 21. UPDATE OFFLINE CACHE
                                     * =============================================
                                     */

                                    $userId =
                                        hash(
                                            "sha256",
                                            $mobile
                                        );


                                    $file =
                                        "cache/users/$userId/profile.json";


                                    if (
                                        file_exists($file)
                                    ) {


                                        $data =
                                            json_decode(
                                                file_get_contents(
                                                    $file
                                                ),
                                                true
                                            );


                                        /*
                                         * Store encrypted
                                         * wallet balance
                                         */

                                        $data['balance'] =
                                            encryptData(
                                                $wallet_after
                                            );


                                        /*
                                         * Server is synchronized
                                         */

                                        $data['server_sync'] =
                                            true;


                                        /*
                                         * Save transaction ID
                                         */

                                        $data['last_transaction'] =
                                            $transaction_id;


                                        /*
                                         * Save cache
                                         */

                                        file_put_contents(
                                            $file,
                                            json_encode(
                                                $data,
                                                JSON_PRETTY_PRINT
                                            )
                                        );
                                    }


                                    /*
                                     * =============================================
                                     * 22. SUCCESS MESSAGE
                                     * =============================================
                                     */

                                    $message =
                                        "Money Withdrawal Successfully";
                                    $_SESSION['message_pay'] = $message;
                                    header("location:wallet.php");
                                } else {


                                    /*
                                     * =============================================
                                     * WALLET UPDATE FAILED
                                     * =============================================
                                     */

                                    $message1 =
                                        "Wallet debit failed.";
                                    $_SESSION['message_pay_f'] = $message1;
                                    header("location:wallet.php");
                                }
                            } else {


                                /*
                                 * =============================================
                                 * BANK UPDATE FAILED
                                 * =============================================
                                 */

                                $message1 =
                                    "Bank credit failed.";
                                $_SESSION['message_pay_f'] = $message1;
                                header("location:wallet.php");
                            }
                        } else {


                            /*
                             * =============================================
                             * BANK ACCOUNT NOT FOUND
                             * =============================================
                             */

                            $message1 =
                                "Bank account not found.";
                            $_SESSION['message_pay_f'] = $message1;
                            header("location:wallet.php");
                        }
                    } else {


                        /*
                         * =============================================
                         * INSUFFICIENT WALLET BALANCE
                         * =============================================
                         */

                        $message1 =
                            "Insufficient wallet balance.";
                        $_SESSION['message_pay_f'] = $message1;
                        header("location:wallet.php");
                    }
                } else {


                    /*
                     * =============================================
                     * MBD WALLET ACCOUNT NOT FOUND
                     * =============================================
                     */

                    $message1 =
                        "Wallet account not found.";
                    $_SESSION['message_pay_f'] = $message1;
                    header("location:wallet.php");
                }
            } else {


                /*
                 * =============================================
                 * SERVER CONNECTION FAILED
                 * =============================================
                 */

                $message1 =
                    "Please connect to the Internet.";
                $_SESSION['message_pay_f'] = $message1;
                header("location:wallet.php");
            }
        }
    }
} catch (Throwable $th) {

    /*
     * ============================================================
     * ERROR HANDLING
     * ============================================================
     */

    $message1 =
        "Please connect to the Internet.";
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>MBD Pay | Wallet</title>

    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        body {
            font-family: Arial;
            background: #f3f4f6;
        }

        .card {
            width: 380px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            text-align: center;
        }

        .balance {
            font-size: 40px;
            font-weight: bold;
            color: #059669;
            margin: 20px 0;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #059669;
            color: #fff;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
        }

        .message {


            text-align: center;

            color: #047857;

            margin-bottom: 15px;

        }

        .message1 {


            text-align: center;

            color: #e60404;

            margin-bottom: 15px;

        }
    </style>

</head>

<body>
    <?php require "navbar.php"; ?>
    <div class="card">
        <?php

        if ($message != "") {

            echo "

        <div class='message'>
        $message
            </div>

                ";
        }

        ?>

        <?php

        if ($mess != "") {

            echo "

        <div class='message'>
        $mess
            </div>

                ";
        }

        ?>

        <?php

        if ($mess_f != "") {

            echo "

        <div class='message1'>
        $mess_f
            </div>

                ";
        }

        ?>

        <?php

        if ($message1 != "") {

            echo "

        <div class='message1'>
        $message1
            </div>

                ";
        }

        ?>
        <h2>My Wallet</h2>

        <div class="balance">
            <?php
            if ($_SESSION['mode'] == "ONLINE") {
                echo "₹" . $_SESSION['balance'];
            }
            if ($_SESSION['mode'] == "OFFLINE") {
                echo "₹" . $_SESSION['balance'];
            }
            ?>
        </div>
        <?php if ($conn && $bank_conn) { ?>
            <form action="wallet.php" method="post">
                <input type="number" name="amount" id="amount" min="1" step="0.01" placeholder="Enter Amount" required>
                <button name='add_money'>Deposit</button>
            </form>

            <br>

            <form action="wallet.php" method="post">
                <input type="number" id="amount" name="amount" placeholder="Withdraw Amount" min="1" step="0.01" required>
                <button name='wtdl_money'>Withdraw</button>
            </form>

    </div>

<?php } else { ?>
    <!-- Offline Mode -->
    <div class="offline-card" style="text-align:center; display:block;">

        <h3>📴 Offline Mode</h3>

        <p style="margin-top:10px;">
            MBD Pay is currently running in offline mode.
        </p>

        <p style="margin-top:8px; opacity:.8;">
            Connect to the internet to add amount and withdraw available amount.
        </p>

    </div>

<?php } ?>

<?php require "footer.php"; ?>
</body>

</html>