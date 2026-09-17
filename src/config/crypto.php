<?php

return [

    'pbkdf2' => [
        'iterations' => (int) env('CRYPTO_PBKDF2_ITERATIONS', 250000),
        'hash' => 'SHA-256',
        'salt_length' => 16,
    ],

    'aes_gcm' => [
        'iv_length' => 12,
        'key_length' => 256,
    ],

    'rsa' => [
        'modulus_length' => 2048,
        'public_exponent' => [1, 0, 1],
        'hash' => 'SHA-256',
    ],

    'recovery_code' => [
        'length' => 24,
        'group_size' => 4,
        'charset' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    ],
];
