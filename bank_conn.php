<?php

$serverConnected = false;

try {

    $bank_conn = @mysqli_connect(
        "127.0.0.1",
        "root",
        "",
        "ram_bank"
    );

    if ($bank_conn) {
        $serverConnected = true;
    }
} catch (\Throwable $th) {
    $bank_conn = false;
    $message1 = "Please connect to the Internet.";
}
