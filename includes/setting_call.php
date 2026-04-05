<?php
if ( ! defined('ABSPATH') ) exit;
$ch = curl_init("https://s.api.ir/api/sw1/SendSms");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer YkjI+TofpYc0UCbQb0+J6lF+axlk95ka27b94LxzNSgb0/RFrTntvZs9ZY/GCsjuSv6G39qUNBJEsOWrKUmS96C2IyCuAnW8nebzkNA9FAw=']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'message' => 'کاربر گرامی بسته شما با شماره 1828772 به پست ارسال شد',
  'mobiles' => [
    '09120001111',
    '09120002222'
  ]
]));

curl_exec($ch);

curl_close($ch);













