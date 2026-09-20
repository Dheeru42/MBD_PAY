<?php

$serverConnected = false;

try {

    $c_conn = @mysqli_connect(
        "127.0.0.1",
        "root",
        "",
        "ram_currency"
    );

    if ($c_conn) {
        $serverConnected = true;
    }
} catch (\Throwable $th) {
    $c_conn = false;
    $message1 = "Please connect to the Internet.";
}
