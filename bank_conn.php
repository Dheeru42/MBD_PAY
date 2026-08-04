<?php

$serverConnected = false;

try {

    $bank_conn = @mysqli_connect(
        "localhost",
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
