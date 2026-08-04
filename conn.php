<?php

$serverConnected = false;

try {

    $conn = @mysqli_connect(
        "localhost",
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
