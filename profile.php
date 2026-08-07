<?php
session_start();
require "conn.php";

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

try {
    $mobile = $_SESSION['mobile'];

    $sql = "SELECT * FROM users WHERE mobile='$mobile'";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    $name = $user['name'];
    $email = $user['email'];
    $account = $user['account_no'];
    $mobile = $user['mobile'];
    $balance = decryptData($user['balance']);

    $initial = strtoupper(substr($name, 0, 1));
} catch (\Throwable $th) {
    // user cache path

    $userId = hash("sha256", $mobile);

    $profile = CACHE_DIR . $userId . "/profile.json";

    $cache = json_decode(
        file_get_contents($profile),
        true
    );
    $_SESSION['name'] = decryptData($cache['name']);
    $initial = strtoupper(substr($_SESSION['name'], 0, 1));
    $_SESSION['email'] = decryptData($cache['email']);
    $_SESSION['account'] = decryptData($cache['account']);
    $_SESSION['mobile'] = decryptData($cache['mobile']);
    $balance = decryptData($cache['balance']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MBD Pay | Profile</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' 
viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext 
x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' 
fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <style>
        /* ==========================
   MBD PAY PROFILE PAGE CSS
   Soft Green Theme
========================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .mbd-profile {
            font-family: 'Poppins', sans-serif;
            background: #f4fbf7;
            min-height: 100vh;
            padding: 40px 20px;
        }

        /* Container */

        .mbd-profile .profile-container {
            max-width: 1150px;
            margin: auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
        }

        /* Left Profile Card */

        .mbd-profile .profile-left {
            background: #ffffff;
            border-radius: 25px;
            padding: 35px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
            border: 1px solid #e6efe9;
        }

        /* Avatar */

        .mbd-profile .profile-avatar {
            width: 130px;
            height: 130px;
            margin: auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #89c7a5, #6daf89);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 55px;
            font-weight: 700;
            border: 5px solid #eef8f2;
        }

        /* Name */

        .mbd-profile .profile-name {
            margin-top: 20px;
            color: #365c4b;
            font-size: 28px;
            font-weight: 600;
        }

        .mbd-profile .profile-status {
            margin-top: 8px;
            color: #7ca48e;
            font-size: 15px;
        }

        /* Balance Card */

        .mbd-profile .profile-balance {
            margin-top: 30px;
            padding: 22px;
            border-radius: 18px;
            background: linear-gradient(135deg, #8bc7a6, #6faf89);
            color: #fff;
        }

        .mbd-profile .profile-balance h4 {
            font-weight: 400;
            font-size: 15px;
        }

        .mbd-profile .profile-balance h1 {
            margin-top: 10px;
            font-size: 38px;
            font-weight: 700;
        }

        /* Buttons */

        .mbd-profile .profile-actions {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .mbd-profile .profile-actions a {
            display: block;
            text-decoration: none;
            background: #f8fcf9;
            color: #4c8265;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #dceee3;
            font-weight: 600;
            transition: .3s;
        }

        .mbd-profile .profile-actions a:hover {
            background: #7ebd99;
            color: #fff;
            transform: translateY(-3px);
        }

        /* Right Side */

        .mbd-profile .profile-right {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Cards */

        .mbd-profile .profile-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 28px;
            border: 1px solid #e6efe9;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .06);
            transition: .3s;
        }

        .mbd-profile .profile-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, .08);
        }

        .mbd-profile .profile-card h3 {
            margin-bottom: 20px;
            color: #5b9a79;
            font-size: 22px;
            font-weight: 600;
        }

        /* Information Rows */

        .mbd-profile .profile-info {
            display: grid;
            grid-template-columns: 180px 1fr;
            padding: 16px 0;
            border-bottom: 1px solid #edf3ef;
        }

        .mbd-profile .profile-info:last-child {
            border-bottom: none;
        }

        .mbd-profile .profile-label {
            color: #6b9a83;
            font-weight: 600;
        }

        .mbd-profile .profile-value {
            color: #3b4b43;
            font-weight: 500;
            word-break: break-word;
        }

        /* Security */

        .mbd-profile .profile-security {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .mbd-profile .profile-security p {
            color: #66766d;
            line-height: 1.7;
        }

        .mbd-profile .security-badge {
            background: #7ebd99;
            color: #fff;
            padding: 12px 22px;
            border-radius: 30px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Statistics */

        .mbd-profile .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .mbd-profile .profile-stat-box {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e6efe9;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .05);
            transition: .3s;
        }

        .mbd-profile .profile-stat-box:hover {
            transform: translateY(-5px);
            background: #f8fcf9;
        }

        .mbd-profile .profile-stat-box h2 {
            color: #5e9d7b;
            font-size: 34px;
            margin-bottom: 10px;
        }

        .mbd-profile .profile-stat-box p {
            color: #7d8e84;
            font-size: 15px;
        }

        /* Responsive */

        @media(max-width:992px) {

            .mbd-profile .profile-container {
                grid-template-columns: 1fr;
            }

            .mbd-profile .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media(max-width:768px) {

            .mbd-profile {
                padding: 20px 15px;
            }

            .mbd-profile .profile-info {
                grid-template-columns: 1fr;
                row-gap: 8px;
            }

            .mbd-profile .profile-security {
                flex-direction: column;
                align-items: flex-start;
            }

            .mbd-profile .profile-avatar {
                width: 110px;
                height: 110px;
                font-size: 46px;
            }

            .mbd-profile .profile-name {
                font-size: 24px;
            }

            .mbd-profile .profile-balance h1 {
                font-size: 30px;
            }

            .mbd-profile .profile-stats {
                grid-template-columns: 1fr;
            }

        }

        @media(max-width:480px) {

            .mbd-profile .profile-left,
            .mbd-profile .profile-card {
                padding: 20px;
            }

            .mbd-profile .profile-actions a {
                padding: 12px;
                font-size: 14px;
            }

            .mbd-profile .profile-card h3 {
                font-size: 20px;
            }

        }
    </style>

</head>

<body>
    <?php require 'navbar.php' ?>


    <div class="mbd-profile">

        <div class="profile-container">

            <!-- Left Side -->
            <div class="profile-left">

                <div class="profile-avatar">
                    <?php echo $initial; ?>
                </div>

                <h2 class="profile-name"><?php if ($serverConnected) {

                                                echo htmlspecialchars($name);
                                            } else {
                                                echo htmlspecialchars($_SESSION['name']);
                                            } ?></h2>

                <p class="profile-status">
                    ✔ Verified MBD Pay User
                </p>

                <div class="profile-balance">
                    <h4>Wallet Balance</h4>
                    <h1>₹<?php if ($serverConnected) {

                                echo htmlspecialchars($balance);
                            } else {
                                echo htmlspecialchars($balance);
                            } ?></h1>
                </div>

                <div class="profile-actions">
                    <?php if ($serverConnected) { ?>

                        <a href="#">
                            ✏ Edit Profile
                        </a>

                        <a href="#">
                            🔒 Change Password
                        </a>

                        <a href="transaction.php">
                            💳 Transaction History
                        </a>
                    <?php } ?>
                    <a href="logout.php">
                        🚪 Logout
                    </a>

                </div>

            </div>

            <!-- Right Side -->
            <div class="profile-right">

                <!-- Personal Information -->
                <div class="profile-card">

                    <h3>Personal Information</h3>

                    <div class="profile-info">
                        <div class="profile-label">Full Name</div>
                        <div class="profile-value">
                            <?php
                            if ($serverConnected) {

                                echo htmlspecialchars($name);
                            } else {
                                echo htmlspecialchars($_SESSION['name']);
                            }
                            ?>
                        </div>
                    </div>

                    <div class="profile-info">
                        <div class="profile-label">Mobile Number</div>
                        <div class="profile-value">
                            <?php if ($serverConnected) {

                                echo htmlspecialchars($mobile);
                            } else {
                                echo htmlspecialchars($_SESSION['mobile']);
                            } ?>
                        </div>
                    </div>

                    <div class="profile-info">
                        <div class="profile-label">Email Address</div>
                        <div class="profile-value">
                            <?php if ($serverConnected) {

                                echo htmlspecialchars($email);
                            } else {
                                echo htmlspecialchars($_SESSION['email']);
                            } ?>
                        </div>
                    </div>

                </div>

                <!-- Bank Information -->
                <div class="profile-card">

                    <h3>Bank Information</h3>

                    <div class="profile-info">
                        <div class="profile-label">Linked Account Number</div>
                        <div class="profile-value">
                            <?php if ($serverConnected) {

                                echo htmlspecialchars($account);
                            } else {
                                echo htmlspecialchars($_SESSION['account']);
                            } ?>
                        </div>
                    </div>

                    <div class="profile-info">
                        <div class="profile-label">Wallet Status</div>
                        <div class="profile-value">
                            Active
                        </div>
                    </div>

                    <div class="profile-info">
                        <div class="profile-label">KYC Status</div>
                        <div class="profile-value">
                            ✔ Verified
                        </div>
                    </div>

                </div>

                <!-- Security -->
                <div class="profile-card profile-security">

                    <div>

                        <h3>Security Status</h3>

                        <p>
                            Your MBD Pay account is protected using a secure
                            login password and transaction PIN.
                        </p>

                    </div>

                    <div class="security-badge">
                        Protected
                    </div>

                </div>

                <!-- Statistics -->
                <div class="profile-stats">

                    <div class="profile-stat-box">
                        <h2>₹<?php if ($serverConnected) {

                                    echo htmlspecialchars($balance);
                                } else {
                                    echo htmlspecialchars($balance);
                                } ?></h2>
                        <p>Available Balance</p>
                    </div>

                    <div class="profile-stat-box">
                        <h2>100%</h2>
                        <p>Account Secure</p>
                    </div>

                    <div class="profile-stat-box">
                        <h2>24×7</h2>
                        <p>Server Status</p>
                    </div>

                </div>

            </div>

        </div>

    </div>

</body>
<?php require 'footer.php' ?>
</body>

</html>