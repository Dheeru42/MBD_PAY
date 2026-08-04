<?php

session_start();

// Optional login protection
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MBD Pay | Synchronizing</title>

<link rel="icon" type="image/svg+xml"
href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%23059669'/%3E%3Ctext x='50' y='72' text-anchor='middle' font-size='70' font-family='Arial' font-weight='bold' fill='white'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{

    min-height:100vh;

    display:flex;

    justify-content:center;

    align-items:center;

    font-family:Arial, sans-serif;

    background:
        radial-gradient(
            circle at top,
            #bbf7d0,
            #ecfdf5,
            #d1fae5
        );

    color:#064e3b;

}


/* MAIN */

.sync-box{

    width:90%;

    max-width:420px;

    text-align:center;

    background:rgba(255,255,255,.85);

    backdrop-filter:blur(15px);

    padding:45px 30px;

    border-radius:30px;

    box-shadow:
        0 25px 60px rgba(0,0,0,.15);

    animation:appear .5s ease;

}


@keyframes appear{

    from{

        opacity:0;

        transform:translateY(20px);

    }

    to{

        opacity:1;

        transform:translateY(0);

    }

}


/* LOGO */

.logo{

    width:70px;

    height:70px;

    margin:0 auto 25px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:20px;

    background:linear-gradient(
        135deg,
        #059669,
        #047857
    );

    color:white;

    font-size:40px;

    font-weight:bold;

    box-shadow:
        0 10px 25px rgba(5,150,105,.3);

}


/* LOADER */

.loader{

    width:55px;

    height:55px;

    margin:25px auto;

    border:5px solid #d1fae5;

    border-top:5px solid #059669;

    border-radius:50%;

    animation:spin 1s linear infinite;

}


@keyframes spin{

    to{

        transform:rotate(360deg);

    }

}


/* TEXT */

h1{

    font-size:25px;

    margin-bottom:10px;

}


p{

    color:#64748b;

    font-size:14px;

    line-height:1.6;

}


.status{

    margin-top:20px;

    font-size:13px;

    color:#059669;

    font-weight:bold;

}


/* DOTS */

.dots::after{

    content:"";

    animation:dots 1.5s infinite;

}


@keyframes dots{

    0%{
        content:"";
    }

    25%{
        content:".";
    }

    50%{
        content:"..";
    }

    75%{
        content:"...";
    }

    100%{
        content:"";
    }

}

</style>

</head>


<body>


<div class="sync-box">


    <div class="logo">
        ₹
    </div>


    <h1>
        Synchronizing
    </h1>


    <p>
        Updating your MBD Pay data.
        Please wait while the latest information
        is being loaded.
    </p>


    <div class="loader"></div>


    <div class="status">

        Syncing<span class="dots"></span>

    </div>


</div>


<script>

setTimeout(function(){

    window.location.href = "index.php";

}, 1500);

</script>


</body>

</html>

