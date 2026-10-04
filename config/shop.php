<?php

return [
    // Printed on receipts. Override in .env (SHOP_NAME, SHOP_TAGLINE, SHOP_ADDRESS, SHOP_CLAIM_DAYS).
    'name' => env('SHOP_NAME', 'SSK Laba Dami'),
    'tagline' => env('SHOP_TAGLINE', 'Laundry Hub'),
    'address' => env('SHOP_ADDRESS', 'Matina, Davao City'),
    'claim_days' => (int) env('SHOP_CLAIM_DAYS', 7),
];
