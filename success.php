<?php

session_start();

$transaction = $_SESSION['success_transaction'];

// $pay_mode        = $transaction['pay_mode'];
// $token_id        = $transaction['token_id'];
// $sender_mobile   = $transaction['sender_mobile'];
// $receiver_mobile = $transaction['receiver_mobile'];
// $timestamp       = $transaction['timestamp'];
$amount          = $transaction['amount'];

echo "Recieve amount: " . $amount;

session_abort();
?>
