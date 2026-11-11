<?php

/*
 * Shop settings for the YokanCha PO app. Real values belong in .env; the
 * defaults here are placeholders so the app runs out of the box.
 */
return [

    // Seller WhatsApp number in international format without "+" (e.g. 6281234567890).
    // Used for the "Kirim lewat WhatsApp" button after checkout.
    'whatsapp' => env('YOKANCHA_WHATSAPP', '08984527337'),

    // Manual transfer instructions shown at checkout and on the confirmation page.
    'payment' => [
        'bank' => env('YOKANCHA_BANK_NAME', 'BCA'),
        'account_number' => env('YOKANCHA_BANK_ACCOUNT', '1234567890'),
        'account_name' => env('YOKANCHA_BANK_HOLDER', 'YokanCha'),
    ],

    // Bottle sizes normally sold. Used in landing-page sentences when no batch is open;
    // during a batch the sentences list the sizes the products actually offer.
    'sizes' => ['250 ml', '500 ml', '1 liter'],

    // "Maksimal 20 botol per varian untuk batch kecil ini." (Figma, Cara pesan step 02)
    'max_per_variant' => (int) env('YOKANCHA_MAX_PER_VARIANT', 20),

];
