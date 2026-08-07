<?php

session_start();

date_default_timezone_set('Asia/Kolkata');

require 'conn.php';

// bank conn
require 'bank_conn.php';


$message = "";

$message1 = "";

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


/* Save User Data */
function saveUserCache($name, $account, $mobile, $email, $password, $pin, $balance = 0)
{
    $userId = hash("sha256", $mobile);

    $folder = CACHE_DIR . $userId;

    // Create folders
    if (!is_dir($folder)) {
        mkdir($folder . "/currency", 0777, true);
        mkdir($folder . "/transactions", 0777, true);
        mkdir($folder . "/logs", 0777, true);
    }

    $data = [

        "identifier" => $userId,

        "name" => encryptData($name),

        "mobile" => encryptData($mobile),

        "email" => encryptData($email),

        "account" => encryptData($account),

        "password" => $password,

        "pin" => $pin,

        "balance" => encryptData($balance),

        "created_at" => date("Y-m-d H:i:s"),

        "update_at" => date("Y-m-d H:i:s"),

        "server_sync" => false

    ];

    file_put_contents(
        $folder . "/profile.json",
        json_encode($data, JSON_PRETTY_PRINT)
    );

    return true;
}



// SIGNUP

try {

    if (isset($_POST['signup'])) {

        $name   = $_POST['name'];
        $mobile = $_POST['mobile'];
        $email  = $_POST['email'];
        $acc1    = $_POST['account'];
        $_SESSION['account'] = $acc1;
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pin      = password_hash($_POST['pin'], PASSWORD_DEFAULT);

        if ($bank_conn and $conn) {
            // Verify account on Bank Server
            $bankSql = "SELECT * FROM users
                        WHERE account_number='$acc1'
                        AND name='$name'
                        AND mobile='$mobile'";
            $b_result = mysqli_query($bank_conn, $bankSql);
            if (mysqli_num_rows($b_result) > 0) {
                // Check if already registered in MBD Pay
                $checkSql = "SELECT id FROM users
                             WHERE account_no='$acc1'";
                $result = mysqli_query($conn, $checkSql);
                if (mysqli_num_rows($result) > 0) {
                    $message1 = "Already Exist Account in MBD PAY.";
                } else {
                    // Store user in MBD Pay Server
                    $acc = $_POST['account'];

                    // intial MBD PAY Balance
                    $s_balance = 0;
                    $e_s_balance = encryptData($s_balance);

                    $insertSql = "INSERT INTO users
                    (name,account_no,mobile,email,password,pin,balance)
                    VALUES
                    ('$name',$acc,'$mobile','$email','$password','$pin','$e_s_balance')";
                    if (mysqli_query($conn, $insertSql)) {

                        $message = "Account Verified.";

                        // create catch file
                        saveUserCache($name, $acc, $mobile, $email, $password, $pin, 0);
                    } else {
                        $message1 = "Account Not Verified.";
                    }
                }
            } else {
                $message1 = "Enter Correct Details.";
            }
        } else {
            $message1 = "Please connect to the Internet.";
        }
    }
} catch (\Throwable $th) {

    $message1 = "Please connect to the Internet.";
}



// LOGIN

try {

    if (isset($_POST['login'])) {

        $mobile = $_POST['mobile'];

        $password = $_POST['password'];


        // user cache path

        $userId = hash("sha256", $mobile);

        $profile = CACHE_DIR . $userId . "/profile.json";


        // Check server

        $serverConnected = ($conn && mysqli_ping($conn));



        if ($serverConnected) {

            // -------- ONLINE LOGIN --------


            $stmt = mysqli_prepare(
                $conn,
                "SELECT * FROM users WHERE mobile=?"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $mobile
            );


            mysqli_stmt_execute($stmt);


            $result = mysqli_stmt_get_result($stmt);



            if (mysqli_num_rows($result) > 0) {

                $user = mysqli_fetch_assoc($result);



                if (password_verify(
                    $password,
                    $user['password']
                )) {


                    // recreate cache if deleted

                    if (!file_exists($profile)) {


                        saveUserCache(

                            $user['name'],

                            $user['account_no'],

                            $user['mobile'],

                            $user['email'],

                            $user['password'],

                            $user['pin'],

                            $user['balance']

                        );
                    }



                    $_SESSION['user'] = $user['name'];

                    $_SESSION['mobile'] = $user['mobile'];

                    $_SESSION['balance'] = decryptData($user['balance']);

                    $_SESSION['account'] = $user['account_no'];

                    $_SESSION['mode'] = "ONLINE";

                    header("location:index.php");

                    exit;
                } else {

                    $message = "Invalid Password";
                }
            } else {

                $message = "User Not Found";
            }
        } else {


            // -------- OFFLINE LOGIN --------

            if (!file_exists($profile)) {
                $message1 =
                    "No local cache found. Connect internet first.";
            } else {

                $cache = json_decode(
                    file_get_contents($profile),
                    true
                );


                if (password_verify(
                    $password,
                    $cache['password']
                )) {
                    $_SESSION['user']
                        =
                        decryptData($cache['name']);



                    $_SESSION['mobile']
                        =
                        decryptData($cache['mobile']);



                    $_SESSION['email']
                        =
                        decryptData($cache['email']);



                    $_SESSION['account']
                        =
                        decryptData($cache['account']);



                    $_SESSION['balance']
                        =
                        decryptData($cache['balance']);



                    $_SESSION['mode']
                        =
                        "OFFLINE";



                    header("location:index.php");

                    exit;
                } else {

                    $message = "Invalid Password";
                }
            }
        }
    }
} catch (Throwable $th) {

    $message1 = "Login Error. Please try again.";
}

?>


<!DOCTYPE html>
<html>

<head>

    <title>MBD Pay | Login</title>

    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <meta name="viewport"
        content="width=device-width,initial-scale=1">


    <style>
        * {

            margin: 0;
            padding: 0;
            box-sizing: border-box;

        }


        body {

            min-height: 100vh;

            font-family: Arial, sans-serif;

            background:
                radial-gradient(circle at top, #bbf7d0, #ecfdf5, #d1fae5);

        }



        /* ================= NAVBAR ================= */


        .mbd-navbar {


            width: 95%;

            margin: 20px auto;

            height: 70px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 25px;

            background:
                linear-gradient(135deg, #022c22, #059669);

            border-radius: 18px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, .3);

            color: white;

        }



        .mbd-brand {


            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 24px;

            font-weight: bold;

        }



        .mbd-icon {


            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(135deg, #facc15, #f59e0b);

            border-radius: 12px;

            font-size: 24px;

        }



        .mbd-links {


            display: flex;

            gap: 25px;

            list-style: none;

        }



        .mbd-links a {


            color: white;

            text-decoration: none;

            font-size: 15px;

        }


        .mbd-links a:hover {

            color: #fde047;

        }


        .server-status {

            display: flex;

            align-items: center;

            gap: 10px;

            background: #1f2937;

            padding: 8px 15px;

            border-radius: 30px;

            font-size: 13px;

        }

        .status {

            width: 13px;
            height: 13px;

            border-radius: 50%;

            animation: pulse 1.5s infinite;

        }


        .connected {

            background: #22c55e;

            box-shadow: 0 0 10px #22c55e;

        }


        .disconnected {

            background: #ef4444;

            box-shadow: 0 0 10px #ef4444;

        }

        @keyframes pulse {


            50% {

                opacity: .4;

            }

        }


        /* ================= LOGIN AREA ================= */


        .auth-wrapper {


            min-height: calc(100vh - 120px);

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

        }



        .container {


            width: 400px;

            background: white;

            border-radius: 25px;

            padding: 35px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .3);

        }



        .auth-logo {


            text-align: center;

            font-size: 30px;

            font-weight: bold;

            color: #047857;

            margin-bottom: 20px;

        }



        .tabs {


            display: flex;

            margin-bottom: 25px;

        }



        .tabs button {


            width: 50%;

            padding: 12px;

            border: 0;

            cursor: pointer;

            background: #e5e7eb;

            font-size: 16px;

        }



        .tabs button.active {


            background: #059669;

            color: white;

        }



        .form {


            display: none;

        }


        .form.active {


            display: block;

        }



        input {


            width: 100%;

            padding: 13px;

            margin: 10px 0;

            border-radius: 12px;

            border: 1px solid #ddd;

        }



        input:focus {


            outline: none;

            border-color: #059669;

        }



        .submit {


            width: 100%;

            padding: 13px;

            border: 0;

            border-radius: 12px;

            background: #047857;

            color: white;

            font-size: 17px;

            cursor: pointer;

        }



        .submit:hover {

            background: #065f46;

        }



        .message {


            text-align: center;

            color: #047857;

            margin-bottom: 15px;

        }

        .message1 {


            text-align: center;

            color: red;

            margin-bottom: 15px;

        }



        small {

            color: #666;

        }



        /* MOBILE */


        @media(max-width:800px) {


            .mbd-navbar {

                width: 100%;

                margin: 0;

                border-radius: 0;

            }



            .mbd-links {

                display: none;

            }



            .server-status {

                display: none;

            }



            .container {

                width: 95%;

            }



        }
    </style>


</head>



<body>



    <!-- NAVBAR -->

    <nav class="mbd-navbar">


        <div class="mbd-brand">


            <div class="mbd-icon">

                ₹

            </div>


            MBD Pay


        </div>



        <ul class="mbd-links">

            <li>
                <a href="login.php">🏠 Home</a>
            </li>


        </ul>



        <div class="server-status">


            <span class="status 
<?php echo $serverConnected ? 'connected' : 'disconnected'; ?>">
            </span>


            <?php

            if ($serverConnected) {

                echo "🟢 Online - Sync Ready";
            } else {

                echo "🔴 Offline - Local Cache";
            }

            ?>


        </div>


    </nav>





    <!-- LOGIN / SIGNUP -->


    <div class="auth-wrapper">


        <div class="container">



            <div class="auth-logo">

                ₹ MBD Pay

            </div>



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

            if ($message1 != "") {

                echo "

<div class='message1'>
$message1
</div>

";
            }

            ?>



            <div class="tabs">


                <button
                    onclick="showLogin()"
                    id="loginBtn"
                    class="active">

                    Login

                </button>



                <button
                    onclick="showSignup()"
                    id="signupBtn">

                    Signup

                </button>



            </div>





            <!-- LOGIN -->


            <form method="post"
                class="form active"
                id="login">


                <input

                    type="tel"

                    name="mobile"

                    placeholder="Enter 10 digit mobile number"

                    pattern="[6-9][0-9]{9}"

                    maxlength="10"

                    required>



                <input

                    type="password"

                    name="password"

                    placeholder="Password"

                    required>



                <button class="submit"
                    name="login">

                    Login

                </button>


            </form>






            <!-- SIGNUP -->


            <form method="post"
                class="form"
                id="signup">


                <input

                    name="name"

                    placeholder="Full Name"

                    required>



                <input

                    type="tel"

                    name="mobile"

                    placeholder="Enter mobile number"

                    pattern="[6-9][0-9]{9}"

                    maxlength="10"

                    required>



                <input

                    type="email"

                    name="email"

                    placeholder="email"

                    required>


                <input

                    <input
                    type="tel"
                    name="account"
                    placeholder="Enter Account Number"
                    maxlength="12"
                    pattern="[0-9]{10,12}"
                    inputmode="numeric"
                    required>



                <input

                    type="password"

                    name="password"

                    placeholder="Password"

                    required>



                <input

                    type="password"

                    name="pin"

                    placeholder="4 Digit Offline PIN"

                    maxlength="4"

                    required>



                <small>
                    PIN required to show offline QR currency
                </small>



                <br><br>



                <button class="submit"
                    name="signup">

                    Create Account

                </button>


            </form>



        </div>


    </div>





    <script>
        function showLogin() {


            login.classList.add("active");

            signup.classList.remove("active");

            loginBtn.classList.add("active");

            signupBtn.classList.remove("active");


        }



        function showSignup() {


            signup.classList.add("active");

            login.classList.remove("active");

            signupBtn.classList.add("active");

            loginBtn.classList.remove("active");


        }
    </script>

    <?php require "footer.php"; ?>
</body>

</html>