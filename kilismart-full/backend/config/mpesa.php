<?php
return [
    'env'                     => env('MPESA_ENV', 'sandbox'),
    'consumer_key'            => env('MPESA_CONSUMER_KEY'),
    'consumer_secret'         => env('MPESA_CONSUMER_SECRET'),
    'shortcode'               => env('MPESA_SHORTCODE', '174379'),
    'passkey'                 => env('MPESA_PASSKEY'),
    'b2c_initiator'           => env('MPESA_B2C_INITIATOR', 'testapi'),
    'b2c_security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
    'callback_base'           => env('MPESA_CALLBACK_BASE', 'https://test.kilismart.co.tz'),
    'min_withdrawal'          => (int) env('MPESA_MIN_WITHDRAWAL', 5000),
];
