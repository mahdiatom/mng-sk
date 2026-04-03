<?php
$token = "230873628:lhsGNvhF0iigj2Nnz8gpi_ilSnOEQ7aSrOA";
$chat_id = "1863682744";
$text = "سلام! من ربات PHP سایت شما هستم.";

$url = "https://tapi.bale.ai/bot" . $token . "/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => $text
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$response = curl_exec($ch);
curl_close($ch);

?>