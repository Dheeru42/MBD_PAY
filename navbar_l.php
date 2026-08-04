<?php require 'conn.php' ?>


<style>
    /* ================= NAVBAR ================= */

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



    /* LOGO */


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

        font-size: 28px;

        color: white;

    }



    /* LINKS */


    .mbd-links {

        display: flex;

        gap: 25px;

        list-style: none;

    }



    .mbd-links a {

        color: white;

        text-decoration: none;

        font-size: 15px;

        transition: .3s;

    }



    .mbd-links a:hover {

        color: #fde047;

        transform: translateY(-3px);

    }



    /* SERVER STATUS */


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

        box-shadow:
            0 0 10px #22c55e;

    }



    .disconnected {

        background: #ef4444;

        box-shadow:
            0 0 10px #ef4444;

    }



    @keyframes pulse {


        50% {

            opacity: .4;

            transform: scale(1.3);

        }


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


    }


    .logout-btn {

        background: #dc2626;

        padding: 10px 18px;

        border-radius: 25px;

        color: white !important;

        font-weight: bold;

        transition: .3s;

    }

    .logout-btn:hover {

        background: #b91c1c;

        color: white !important;

        transform: translateY(-2px);

    }
</style>




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

            <a href="login.php">

                🏠 Home

            </a>

        </li>



        <li>

            <a href="#">

                👛 Wallet

            </a>

        </li>



        <li>

            <a href="#">

                🔄 Transaction

            </a>

        </li>



        <li>

            <a href="#">

                📱 QR Currency

            </a>

        </li>



        <li>

            <a href="#">

                👤 Profile

            </a>

        </li>

        <li>

            <a href="logout.php" class="logout-btn">

                🚪 Logout

            </a>

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

            echo "🔴 Offline - Cache Mode";
        }

        ?>


    </div>



</nav>