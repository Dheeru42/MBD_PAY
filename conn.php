<?php

$serverConnected = false;

try {

    $conn = @mysqli_connect(
        "127.0.0.1",
        "root",
        "",
        "ram_pay"
    );

    if ($conn) {
        $serverConnected = true;
    }
} catch (\Throwable $th) {
    $conn = false;
    $message1 = "Please connect to the Internet.";
}
